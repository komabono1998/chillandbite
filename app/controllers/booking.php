<?php
declare(strict_types=1);

function booking_reference(): string
{
    $alpha = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    do {
        $ref = 'CB';
        for ($i = 0; $i < 6; $i++) $ref .= $alpha[random_int(0, strlen($alpha) - 1)];
    } while (val('SELECT id FROM reservations WHERE reference = ?', [$ref]));
    return $ref;
}
function event_packages(int $eventId): array
{
    return rows('SELECT * FROM event_packages WHERE event_id = ? ORDER BY sort, price', [$eventId]);
}
function res_paid(int $id): float
{
    return (float)val('SELECT COALESCE(SUM(amount),0) FROM payments WHERE reservation_id = ?', [$id]);
}

function page_reserve(array $a): void
{
    $e = find_event($a['slug']) ?? not_found();
    render('site/reserve', [
        'title' => 'Reserve | ' . $e['title'], 'e' => $e, 'options' => event_options((int)$e['id']),
        'open' => event_open_for_booking($e), 'remaining' => event_remaining($e), 'step' => 'form',
        // ?type=table&package=Silver from the event page pre-selects the choice
        'data' => array_filter(['type' => is_string($_GET['type'] ?? null) ? $_GET['type'] : null, 'package' => is_string($_GET['package'] ?? null) ? $_GET['package'] : null]),
        'packages' => event_packages((int)$e['id']),
    ]);
}

/** Validate booking input against the event's enabled options. Returns [data, errors, option]. */
function validate_booking(array $e, array $options, array $in): array
{
    $d = [];
    foreach (['type', 'full_name', 'phone', 'email', 'company', 'seating', 'requests'] as $k) $d[$k] = trim((string)($in[$k] ?? ''));
    $d['guests'] = (int)($in['guests'] ?? 0);
    $err = [];
    $opt = null;
    foreach ($options as $o) if ($o['type'] === $d['type']) $opt = $o;
    if (!$opt) $err[] = 'Please choose an available reservation type.';
    if (mb_strlen($d['full_name']) < 2 || mb_strlen($d['full_name']) > 140) $err[] = 'Please enter your full name.';
    if (!valid_phone($d['phone'])) $err[] = 'Please enter a valid telephone number.';
    if ($d['email'] !== '' && !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $err[] = 'The email address looks invalid.';
    if ($opt) {
        if ($d['guests'] < $opt['min_guests'] || $d['guests'] > $opt['max_guests'])
            $err[] = "Guests for this type must be between {$opt['min_guests']} and {$opt['max_guests']}.";
        if ($d['type'] === 'corporate' && mb_strlen($d['company']) < 2) $err[] = 'Please enter the company name for corporate bookings.';
    }
    $d['requests'] = mb_substr($d['requests'], 0, 1000);
    $d['seating'] = mb_substr($d['seating'], 0, 190);

    // Table bookings can be sold as flat-price packages (e.g. Silver, Gold Pro); otherwise price is per guest.
    $d['package'] = trim((string)($in['package'] ?? ''));
    $flat = null;
    if ($d['type'] === 'table' && ($packages = event_packages((int)$e['id']))) {
        $found = null;
        foreach ($packages as $p) if ($p['name'] === $d['package']) $found = $p;
        if ($found) $flat = (float)$found['price']; else $err[] = 'Please choose a table package.';
    } else {
        $d['package'] = '';
    }
    $d['amount'] = $flat ?? ($opt ? (float)$opt['price_per_guest'] * max(0, $d['guests']) : 0);
    return [$d, $err, $opt];
}

function post_reserve(array $a): void
{
    $e = find_event($a['slug']) ?? not_found();
    $back = 'events/' . $e['slug'] . '/reserve';
    if (!event_open_for_booking($e)) { flash('Sorry, reservations are not open for this event.', 'err'); redirect($back); }
    if (!empty($_POST['website'])) redirect($back);          // honeypot
    if (rate_limited('reserve', 10, 3600)) { flash('Too many reservation attempts. Please try again later.', 'err'); redirect($back); }

    $options = event_options((int)$e['id']);
    [$d, $err, $opt] = validate_booking($e, $options, $_POST);
    $rem = event_remaining($e);
    if (!$err && $rem !== null && $d['guests'] > $rem) $err[] = "Only $rem place(s) remain for this event.";

    $ctx = ['e' => $e, 'options' => $options, 'open' => true, 'remaining' => $rem, 'data' => $d, 'packages' => event_packages((int)$e['id'])];
    if ($err) { render('site/reserve', $ctx + ['title' => 'Reserve | ' . $e['title'], 'step' => 'form', 'errors' => $err]); return; }

    if (($_POST['step'] ?? '') !== 'confirm') {
        render('site/reserve', $ctx + ['title' => 'Review your booking', 'step' => 'review', 'opt' => $opt]);
        return;
    }

    // Final confirmation: capacity is re-checked inside a transaction, on the server.
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $fresh = row('SELECT * FROM events WHERE id = ? FOR UPDATE', [$e['id']]);
        if (!$fresh || !event_open_for_booking($fresh)) throw new RuntimeException('Reservations just closed for this event.');
        $left = event_remaining($fresh);
        if ($left !== null && $d['guests'] > $left) throw new RuntimeException($left > 0 ? "Sorry, only $left place(s) are left." : 'Sorry, this event is now fully booked.');
        $status = ($fresh['booking_mode'] === 'instant' && $d['amount'] <= 0) ? 'confirmed' : 'pending';
        $ref = booking_reference();
        q('INSERT INTO reservations (reference, event_id, type, full_name, phone, email, company, guests, seating, package, requests, amount_due, status)
           VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [$ref, $e['id'], $d['type'], $d['full_name'], $d['phone'], $d['email'] ?: null, $d['company'] ?: null,
             $d['guests'], $d['seating'] ?: null, $d['package'] ?: null, $d['requests'] ?: null, $d['amount'], $status]);
        $pdo->commit();
    } catch (RuntimeException $ex) {
        $pdo->rollBack();
        flash($ex->getMessage(), 'err');
        redirect($back);
    } catch (Throwable $ex) {
        $pdo->rollBack();
        throw $ex;
    }

    if ($d['email']) {
        $line = $status === 'confirmed' ? 'Your place is CONFIRMED.' : 'Your booking has been received and is awaiting confirmation from the event team.';
        send_mail($d['email'], "Chill & Bite booking $ref", "Hello {$d['full_name']},\n\n$line\n\nReference: $ref\nEvent: {$e['title']}\nWhen: " .
            fmt_date($e['start_at']) . ' ' . fmt_time($e['start_at']) . "\nWhere: {$e['venue']}, {$e['location']}\nGuests: {$d['guests']}\n" . ($d['package'] ? "Table package: {$d['package']}\n" : '') .
            ($d['amount'] > 0 ? 'Amount due: ' . money($d['amount']) . "\n" : '') . "\nView: " . abs_url('booking/' . $ref));
    }
    flash($status === 'confirmed' ? 'Your reservation is confirmed!' : 'Booking received. We will confirm it shortly.');
    redirect('booking/' . $ref);
}

function page_booking(array $a): void
{
    $r = row('SELECT r.*, e.title, e.slug, e.start_at, e.end_at, e.venue, e.location FROM reservations r JOIN events e ON e.id = r.event_id WHERE r.reference = ?', [strtoupper($a['ref'])]) ?? not_found();
    render('site/booking', ['title' => 'Booking ' . $r['reference'], 'r' => $r, 'paid' => res_paid((int)$r['id'])]);
}
