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
    // A table package is priced per table, so the guest count is not asked: use the table's minimum (staff can change it on the ticket)
    if ($opt && $d['type'] === 'table' && event_packages((int)$e['id'])) $d['guests'] = max(1, (int)$opt['min_guests']);
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
            "You will get your ticket as soon as it is approved.\n\nYour booking code: $ref (keep it safe)\nEvent: {$e['title']}\nWhen: " .
            fmt_date($e['start_at']) . ' ' . fmt_time($e['start_at']) . "\nWhere: {$e['venue']}, {$e['location']}\nGuests: {$d['guests']}\n" . ($d['package'] ? "Table package: {$d['package']}\n" : '') .
            ($d['amount'] > 0 ? 'Amount due: ' . money($d['amount']) . "\n" : '') . "\nTrack your booking and get your ticket once approved: " . abs_url('booking/' . $ref));
    }
    flash($status === 'confirmed' ? 'Your reservation is confirmed!' : 'Booking received. We will confirm it shortly.');
    redirect('booking/' . $ref . '?new=1');
}

function page_booking(array $a): void
{
    $r = row('SELECT r.*, e.title, e.slug, e.short_desc, e.description, e.poster, e.start_at, e.end_at, e.venue, e.location FROM reservations r JOIN events e ON e.id = r.event_id WHERE r.reference = ?', [strtoupper($a['ref'])]) ?? not_found();
    $ticket = $r['status'] === 'confirmed' ? ticket_url((int)$r['id']) : null;
    render('site/booking', ['title' => 'Booking ' . $r['reference'], 'is_new' => isset($_GET['new']), 'r' => $r, 'paid' => res_paid((int)$r['id']), 'ticket' => $ticket, 'noindex' => true]);
}

/** Live status for an open booking page: it reloads itself when the booking is approved. */
function page_booking_status(array $a): void
{
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    if (rate_limited('bkstatus', 240, 3600)) { http_response_code(429); echo json_encode(['error' => 'slow down']); return; }
    $r = row('SELECT id, status, withdrawn_at FROM reservations WHERE reference = ?', [strtoupper($a['ref'])]);
    if (!$r) { http_response_code(404); echo json_encode(['error' => 'not found']); return; }
    echo json_encode(['status' => $r['status'], 'withdrawn' => $r['withdrawn_at'] !== null, 'ticket' => $r['status'] === 'confirmed' ? ticket_url((int)$r['id']) : null]);
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
        'Open your ticket (show the QR code at the entrance): ' . ticket_url((int)$r['id']) . "\n\n" .
        "Lost this message? Track or download your ticket any time with reference {$r['reference']}: " . abs_url('track') . "\n";
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
    render('site/ticket', ['title' => 'Ticket ' . $r['reference'], 'r' => $r, 'noindex' => true, 'withdrawn' => $r['status'] === 'cancelled' && $r['withdrawn_at'] !== null,
        'ver' => ticket_version($r), 'serial' => ticket_serial($a['token']), 'seal' => ticket_seal_svg($a['token'], 200),
        'code' => ticket_live_code($a['token']), 'code_left' => 30 - (time() % 30), 'valid' => $r['status'] === 'confirmed', 'checkin_url' => abs_url('admin/checkin/' . $a['token'])]);
}
/** Short fingerprint of everything a guest sees on the ticket, so an open ticket page can refresh itself when it changes. */
function ticket_version(array $r): string
{
    return substr(md5(implode('|', [$r['status'], $r['withdrawn_at'], $r['guests'], $r['full_name'], $r['type'], $r['package'], $r['company'], $r['checked_in_at'], $r['event_id']])), 0, 12);
}
function page_ticket_status(array $a): void
{
    if (!preg_match('/^[a-f0-9]{24}$/', $a['token'])) not_found();
    $r = row('SELECT * FROM reservations WHERE ticket_token = ?', [$a['token']]) ?? not_found();
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    $out = ['v' => ticket_version($r)];
    if ($r['status'] === 'confirmed') { $out['code'] = ticket_live_code($a['token']); $out['left'] = 30 - (time() % 30); }
    echo json_encode($out);
}

/* ================= Ticket security: gold seal, serial and live code ================= */
/** Private signing key (APP_KEY in .env, otherwise a random key kept in storage/app.key). */
function app_secret(): string
{
    static $k = null;
    if ($k !== null) return $k;
    $k = (string)env('APP_KEY', '');
    if ($k === '') {
        $file = ROOT . '/storage/app.key';
        if (!is_file($file)) @file_put_contents($file, bin2hex(random_bytes(32)));
        $k = is_file($file) ? trim((string)file_get_contents($file)) : '';
        if ($k === '') $k = hash('sha256', ROOT . php_uname());
    }
    return $k;
}
/** Hex fingerprint that only this server can produce for a given ticket. */
function ticket_hash(string $token): string { return hash_hmac('sha256', 'seal|' . $token, app_secret()); }
/** Unique serial printed on the seal, for example CB-4F2A-91C7-03DE. */
function ticket_serial(string $token): string
{
    $h = strtoupper(ticket_hash($token));
    return 'CB-' . substr($h, 0, 4) . '-' . substr($h, 4, 4) . '-' . substr($h, 8, 4);
}
/** Four-digit code that changes every 30 seconds. Only the live ticket page (and the door screen) can show it. */
function ticket_live_code(string $token, int $offset = 0): string
{
    $win = intdiv(time(), 30) + $offset;
    $n = hexdec(substr(hash_hmac('sha256', 'live|' . $token . '|' . $win, app_secret()), 0, 8));
    return str_pad((string)($n % 10000), 4, '0', STR_PAD_LEFT);
}

/**
 * Gold rosette seal. The pattern (petal count, ring shapes, phases) is derived from the ticket's secret hash,
 * so every ticket gets a different seal that cannot be recreated without the server key.
 */
function ticket_seal_svg(string $token, int $size = 200): string
{
    $hash = ticket_hash($token);
    $b = array_values(unpack('C*', hex2bin(substr($hash, 0, 48))));
    $f = fn(float $n) => number_format($n, 2, '.', '');
    $id = 'g' . substr($hash, 0, 6);
    $cx = 100; $cy = 100;
    // scalloped outer edge
    $bumps = 28 + ($b[0] % 9); $edge = '';
    for ($i = 0; $i <= $bumps * 8; $i++) {
        $t = $i / ($bumps * 8) * 2 * M_PI;
        $r = 96 - 2.6 * (0.5 + 0.5 * cos($bumps * $t));
        $edge .= ($i ? 'L' : 'M') . $f($cx + $r * cos($t)) . ' ' . $f($cy + $r * sin($t));
    }
    $edge .= 'Z';
    // three guilloche rings, each with its own petal count, depth and phase
    $rings = '';
    for ($k = 0; $k < 3; $k++) {
        $petals = 7 + ($b[1 + $k] % 10) + $k * 2;
        $depth = 0.05 + ($b[5 + $k] % 8) / 100;
        $phase = ($b[9 + $k] / 255) * 2 * M_PI;
        $base = 80 - $k * 10;
        $d = '';
        for ($i = 0; $i <= 240; $i++) {
            $t = $i / 240 * 2 * M_PI;
            $r = $base * (1 + $depth * cos($petals * $t + $phase)) - $k * 3;
            $d .= ($i ? 'L' : 'M') . $f($cx + $r * cos($t)) . ' ' . $f($cy + $r * sin($t));
        }
        $rings .= '<path d="' . $d . 'Z" fill="none" stroke="#7a4d0c" stroke-opacity=".75" stroke-width=".7"/>';
    }
    $serial = ticket_serial($token);
    $ring = 'CHILL &amp; BITE MEATINGS · ORIGINAL TICKET · ' . $serial . ' · ';
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200" width="' . $size . '" height="' . $size . '" role="img" aria-label="Gold seal of authenticity, serial ' . $serial . '">'
        . '<defs><linearGradient id="' . $id . '" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#8a5a12"/><stop offset=".25" stop-color="#f6d77a"/><stop offset=".5" stop-color="#c98d2b"/><stop offset=".75" stop-color="#fff0b0"/><stop offset="1" stop-color="#a8741c"/></linearGradient>'
        . '<radialGradient id="' . $id . 'r" cx=".35" cy=".3" r=".8"><stop offset="0" stop-color="#fff8d8" stop-opacity=".45"/><stop offset="1" stop-color="#fff8d8" stop-opacity="0"/></radialGradient>'
        . '<path id="' . $id . 'c" d="M100 100m-70 0a70 70 0 1 1 140 0a70 70 0 1 1-140 0"/></defs>'
        . '<path d="' . $edge . '" fill="url(#' . $id . ')" stroke="#6b430a" stroke-width="1.2"/>'
        . '<circle cx="100" cy="100" r="89" fill="none" stroke="#fff3bf" stroke-opacity=".7" stroke-width="1"/>'
        . $rings
        . '<circle cx="100" cy="100" r="43" fill="url(#' . $id . ')" stroke="#6b430a" stroke-width="1.4"/>'
        . '<circle cx="100" cy="100" r="38" fill="none" stroke="#6b430a" stroke-opacity=".6" stroke-width=".8" stroke-dasharray="2 2"/>'
        . '<text font-family="Arial,Helvetica,sans-serif" font-size="6.1" font-weight="700" letter-spacing="1" fill="#2e1a02"><textPath href="#' . $id . 'c" startOffset="0">' . str_repeat($ring, 1) . '</textPath></text>'
        . '<text x="100" y="97" text-anchor="middle" font-family="Georgia,serif" font-size="21" font-weight="700" fill="#2e1a02">C&amp;B</text>'
        . '<text x="100" y="111" text-anchor="middle" font-family="Arial,Helvetica,sans-serif" font-size="6.5" font-weight="700" letter-spacing="1.2" fill="#2e1a02">AUTHENTIC</text>'
        . '<text x="100" y="121" text-anchor="middle" font-family="ui-monospace,Consolas,monospace" font-size="5.2" fill="#2e1a02">' . substr($serial, 3) . '</text>'
        . '<circle cx="100" cy="100" r="96" fill="url(#' . $id . 'r)"/>'
        . '</svg>';
}
