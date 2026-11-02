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
        $rid = (int)$pdo->lastInsertId();
        $pdo->commit();
    } catch (RuntimeException $ex) {
        $pdo->rollBack();
        flash($ex->getMessage(), 'err');
        redirect($back);
    } catch (Throwable $ex) {
        $pdo->rollBack();
        throw $ex;
    }

    if ($status === 'confirmed') {
        send_ticket((int)$rid);   // free + instant bookings get their ticket straight away
    } elseif ($d['email']) {
        send_mail($d['email'], "Chill & Bite booking $ref received", "Hello {$d['full_name']},\n\nYour booking has been received and is awaiting approval from the event team. " .
            "You will get your ticket as soon as it is approved.\n\nReference: $ref\nEvent: {$e['title']}\nWhen: " .
            fmt_date($e['start_at']) . ' ' . fmt_time($e['start_at']) . "\nWhere: {$e['venue']}, {$e['location']}\nGuests: {$d['guests']}\n" . ($d['package'] ? "Table package: {$d['package']}\n" : '') .
            ($d['amount'] > 0 ? 'Amount due: ' . money($d['amount']) . "\n" : '') . "\nTrack your booking: " . abs_url('booking/' . $ref));
    }
    flash($status === 'confirmed' ? 'Your reservation is confirmed!' : 'Booking received. We will confirm it shortly.');
    redirect('booking/' . $ref);
}

function page_booking(array $a): void
{
    $r = row('SELECT r.*, e.title, e.slug, e.short_desc, e.description, e.poster, e.start_at, e.end_at, e.venue, e.location FROM reservations r JOIN events e ON e.id = r.event_id WHERE r.reference = ?', [strtoupper($a['ref'])]) ?? not_found();
    $ticket = $r['status'] === 'confirmed' ? ticket_url((int)$r['id']) : null;
    render('site/booking', ['title' => 'Booking ' . $r['reference'], 'r' => $r, 'paid' => res_paid((int)$r['id']), 'ticket' => $ticket, 'noindex' => true]);
}

/* ================= Tickets ================= */
/** Unguessable per-booking token, created the first time a ticket is needed. */
function ticket_token(int $reservationId): string
{
    $t = val('SELECT ticket_token FROM reservations WHERE id = ?', [$reservationId]);
    if (!$t) {
        q('UPDATE reservations SET ticket_token = ? WHERE id = ? AND ticket_token IS NULL', [bin2hex(random_bytes(12)), $reservationId]);
        $t = val('SELECT ticket_token FROM reservations WHERE id = ?', [$reservationId]);
    }
    return (string)$t;
}
function ticket_url(int $reservationId): string { return abs_url('ticket/' . ticket_token($reservationId)); }

/** Message a guest receives when their booking is confirmed (email body / WhatsApp text). */
function ticket_message(array $r): string
{
    $line = "Hello {$r['full_name']},\n\nYour booking is CONFIRMED. Here is your ticket for {$r['title']}.\n\n";
    return $line . 'Date: ' . fmt_date($r['start_at'], 'l, j F Y') . ' at ' . fmt_time($r['start_at']) . "\nVenue: {$r['venue']}, {$r['location']}\n" .
        'Reference: ' . $r['reference'] . "\n" . ($r['package'] ? "Table package: {$r['package']}\n" : '') . "Admits: {$r['guests']} guest(s)\n\n" .
        'Open your ticket (show the QR code at the entrance): ' . ticket_url((int)$r['id']) . "\n";
}
function reservation_with_event(int $id): ?array
{
    return row('SELECT r.*, e.title, e.start_at, e.end_at, e.venue, e.location FROM reservations r JOIN events e ON e.id = r.event_id WHERE r.id = ?', [$id]);
}
/** Email the ticket link to the guest. Returns false when the guest left no email or the booking is not confirmed. */
function send_ticket(int $reservationId): bool
{
    $r = reservation_with_event($reservationId);
    if (!$r || $r['status'] !== 'confirmed' || empty($r['email'])) return false;
    return send_mail($r['email'], 'Your Chill & Bite ticket: ' . $r['reference'], ticket_message($r));
}

function page_ticket(array $a): void
{
    if (!preg_match('/^[a-f0-9]{24}$/', $a['token'])) not_found();
    $r = row('SELECT r.*, e.title, e.slug, e.start_at, e.end_at, e.venue, e.location, e.poster FROM reservations r JOIN events e ON e.id = r.event_id WHERE r.ticket_token = ?', [$a['token']]) ?? not_found();
    render('site/ticket', ['title' => 'Ticket ' . $r['reference'], 'r' => $r, 'noindex' => true,
        'valid' => $r['status'] === 'confirmed', 'checkin_url' => abs_url('admin/checkin/' . $a['token'])]);
}
