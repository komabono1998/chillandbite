<?php
declare(strict_types=1);

/* ================= Public: track a booking by reference ================= */
function page_track(): void
{
    render('site/track', ['title' => 'Track Your Booking | Chill & Bite MEATINGS', 'noindex' => true]);
}
function post_track(): void
{
    if (!empty($_POST['website'])) redirect('track');                                    // honeypot
    if (rate_limited('track', 15, 3600)) { flash('Too many lookups from your connection. Please try again in a little while.', 'err'); redirect('track'); }
    $ref = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)($_POST['reference'] ?? '')));
    // Be forgiving about a missing "CB" prefix (people often type just the 6 characters)
    if (preg_match('/^[A-Z2-9]{6}$/', $ref)) $ref = 'CB' . $ref;
    if (!preg_match('/^CB[A-Z2-9]{6}$/', $ref) || !val('SELECT id FROM reservations WHERE reference = ?', [$ref])) {
        keep_old(['reference' => (string)($_POST['reference'] ?? '')]);
        flash('We could not find a booking with that reference. Please check it and try again.', 'err');
        redirect('track');
    }
    clear_old();
    redirect('booking/' . $ref);
}

/* ================= Admin: Tickets tab ================= */
function admin_ticket_events(): array
{
    return rows("SELECT id, title, start_at FROM events WHERE status IN ('published','archived') ORDER BY start_at DESC LIMIT 60");
}
function admin_ticket_default_event(array $events): int
{
    $eid = (int)($_GET['event'] ?? 0);
    if ($eid) return $eid;
    return (int)(val("SELECT id FROM events WHERE status='published' AND COALESCE(end_at,start_at) >= NOW() ORDER BY start_at LIMIT 1") ?: ($events[0]['id'] ?? 0));
}
function admin_tickets(): void
{
    require_can('tickets');
    $events = admin_ticket_events();
    $eid = admin_ticket_default_event($events);
    $status = in_array($_GET['status'] ?? '', ['confirmed', 'pending', 'withdrawn', 'cancelled'], true) ? $_GET['status'] : 'confirmed';
    $q = trim((string)($_GET['q'] ?? ''));
    $w = 'r.event_id = ? AND ' . (['withdrawn' => "r.status = 'cancelled' AND r.withdrawn_at IS NOT NULL", 'cancelled' => "r.status = 'cancelled' AND r.withdrawn_at IS NULL"][$status] ?? 'r.status = ?');
    $p = in_array($status, ['withdrawn', 'cancelled'], true) ? [$eid] : [$eid, $status];
    if ($q !== '') { $w .= ' AND (r.full_name LIKE ? OR r.phone LIKE ? OR r.reference LIKE ?)'; $like = '%' . addcslashes($q, '%_\\') . '%'; array_push($p, $like, $like, $like); }
    $ev = $eid ? row('SELECT * FROM events WHERE id = ?', [$eid]) : null;
    $counts = $eid ? array_column(rows("SELECT CASE WHEN status = 'cancelled' AND withdrawn_at IS NOT NULL THEN 'withdrawn' ELSE status END AS s, COUNT(*) n FROM reservations WHERE event_id = ? GROUP BY s", [$eid]), 'n', 's') : [];
    admin_view('tickets', [
        'title' => 'Tickets', 'events' => $events, 'ev' => $ev, 'status' => $status, 'q' => $q, 'counts' => $counts,
        'list' => $eid ? rows(RES_SELECT . " WHERE $w ORDER BY r.full_name LIMIT 300", $p) : [],
    ]);
}

/** Create a confirmed, free ticket directly (VIP, sponsor guest, walk-in, staff). Capacity is checked on the server. */
function admin_ticket_issue(): void
{
    require_can('tickets');
    $eid = (int)($_POST['event_id'] ?? 0);
    $name = post_str('full_name', 140); $phone = post_str('phone', 30); $email = strtolower(post_str('email', 190));
    $guests = (int)($_POST['guests'] ?? 1);
    $type = array_key_exists($_POST['type'] ?? '', RES_TYPES) ? $_POST['type'] : 'individual';
    $back = 'admin/tickets?event=' . $eid;
    $err = [];
    $ev = row('SELECT * FROM events WHERE id = ?', [$eid]);
    if (!$ev) $err[] = 'Choose an event.';
    if (mb_strlen($name) < 2) $err[] = 'Enter the guest name.';
    if ($phone !== '' && !valid_phone($phone)) $err[] = 'The phone number looks invalid.';
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $err[] = 'The email address looks invalid.';
    if ($guests < 1 || $guests > 100) $err[] = 'Guests must be between 1 and 100.';
    if ($err) { foreach ($err as $x) flash($x, 'err'); redirect($ev ? $back : 'admin/tickets'); }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $fresh = row('SELECT * FROM events WHERE id = ? FOR UPDATE', [$eid]);
        $left = event_remaining($fresh);
        if ($left !== null && $guests > $left) throw new RuntimeException($left > 0 ? "Only $left place(s) are left for this event." : 'This event is fully booked.');
        $ref = booking_reference();
        $note = 'Ticket issued by ' . current_user()['name'] . (post_str('note') !== '' ? ': ' . post_str('note', 150) : '');
        q("INSERT INTO reservations (reference, event_id, type, full_name, phone, email, guests, amount_due, status, admin_note)
           VALUES (?,?,?,?,?,?,?,0,'confirmed',?)", [$ref, $eid, $type, $name, $phone !== '' ? $phone : 'N/A', $email ?: null, $guests, mb_substr($note, 0, 255)]);
        $rid = (int)$pdo->lastInsertId();
        $pdo->commit();
    } catch (RuntimeException $e) {
        $pdo->rollBack(); flash($e->getMessage(), 'err'); redirect($back);
    } catch (Throwable $e) { $pdo->rollBack(); throw $e; }

    audit('ticket.issue', "#$rid $ref $name x$guests");
    $sent = $email !== '' && send_ticket($rid);
    flash("Ticket $ref created for $name ($guests guest" . ($guests === 1 ? '' : 's') . ')' . ($sent ? ' and emailed to them.' : '. Use the buttons to share it.'));
    redirect($back . '&q=' . urlencode($ref));
}

/** Replace a ticket's secret link. The old QR code and link stop working immediately. */
function admin_ticket_regenerate(array $a): void
{
    require_can('tickets');
    $id = (int)$a['id'];
    $r = row('SELECT * FROM reservations WHERE id = ?', [$id]) ?? not_found();
    if ($r['status'] !== 'confirmed') { flash('Only confirmed bookings have a ticket.', 'err'); redirect('admin/tickets?event=' . $r['event_id']); }
    q('UPDATE reservations SET ticket_token = ? WHERE id = ?', [bin2hex(random_bytes(12)), $id]);
    audit('ticket.regenerate', "#$id {$r['reference']}");
    flash("New ticket created for {$r['full_name']}. The old link and QR code no longer work, so send them the new one.");
    $back = (string)($_POST['back'] ?? '');
    redirect(preg_match('#^admin/tickets\?[A-Za-z0-9=&_%.+-]*$#', $back) ? $back : 'admin/tickets?event=' . $r['event_id']);
}

/** All confirmed tickets of one event on printable pages. */
function admin_tickets_print(): void
{
    require_can('tickets');
    $events = admin_ticket_events();
    $eid = admin_ticket_default_event($events);
    $ev = row('SELECT * FROM events WHERE id = ?', [$eid]) ?? not_found();
    $list = rows(RES_SELECT . " WHERE r.event_id = ? AND r.status = 'confirmed' ORDER BY r.full_name LIMIT 500", [$eid]);
    foreach ($list as &$r) { $r['ticket_url'] = ticket_url((int)$r['id']); $r['checkin_url'] = abs_url('admin/checkin/' . ticket_token((int)$r['id'])); }
    unset($r);
    audit('tickets.print', "event #$eid, " . count($list) . ' tickets');
    admin_view('tickets_print', ['title' => 'Print tickets', 'ev' => $ev, 'list' => $list]);
}

/* ================= Admin: email, update, withdraw and reinstate a ticket ================= */
/** Where to return after a ticket action (only ever back to the Tickets tab). */
function ticket_back(array $r): string
{
    $back = (string)($_POST['back'] ?? '');
    return preg_match('#^admin/tickets\?[A-Za-z0-9=&_%.+-]*$#', $back) ? $back : 'admin/tickets?event=' . $r['event_id'];
}
function is_withdrawn(array $r): bool { return $r['status'] === 'cancelled' && !empty($r['withdrawn_at']); }

/** Text sent to a guest when their ticket is withdrawn (email body / WhatsApp text). */
function ticket_withdrawn_message(array $r): string
{
    $why = trim((string)($r['withdrawn_reason'] ?? ''));
    return "Hello {$r['full_name']},\n\nYour ticket {$r['reference']} for {$r['title']} (" . fmt_date($r['start_at'], 'j F Y') . ") has been withdrawn and is no longer valid.\n" .
        ($why !== '' ? "Reason: $why\n" : '') . "\nThe QR code will not be accepted at the entrance. If you think this is a mistake, please contact us" .
        (setting('contact_phone') ? ' on ' . setting('contact_phone') : '') . ".\n\nChill & Bite MEATINGS\n";
}
/** Text sent to a guest when their ticket details change. */
function ticket_updated_message(array $r): string
{
    return "Hello {$r['full_name']},\n\nYour ticket details for {$r['title']} were updated.\n\nGuest: {$r['full_name']}\nAdmits: {$r['guests']} guest(s)\nReference: {$r['reference']}\n" .
        ($r['package'] ? "Table package: {$r['package']}\n" : '') . "\nYour ticket (the link always shows the latest details): " . ticket_url((int)$r['id']) . "\n\nChill & Bite MEATINGS\n";
}

function admin_ticket_email(array $a): void
{
    require_can('tickets');
    $id = (int)$a['id'];
    $r = reservation_with_event($id) ?? not_found();
    if ($r['status'] === 'confirmed') {
        $ok = send_ticket($id);
        flash($ok ? "Ticket emailed to {$r['email']}." : 'This guest has no email on file. Use the WhatsApp button, or add an email under Update.', $ok ? 'ok' : 'err');
        if ($ok) audit('ticket.email', "#$id {$r['reference']}");
    } elseif (is_withdrawn($r)) {
        $ok = !empty($r['email']) && send_mail($r['email'], 'Your Chill & Bite ticket was withdrawn: ' . $r['reference'], ticket_withdrawn_message($r));
        flash($ok ? "Withdrawal notice emailed to {$r['email']}." : 'This guest has no email on file. Use the WhatsApp button instead.', $ok ? 'ok' : 'err');
    } else {
        flash('Only confirmed tickets can be emailed.', 'err');
    }
    redirect(ticket_back($r));
}

function admin_ticket_update(array $a): void
{
    require_can('tickets');
    $id = (int)$a['id'];
    $r = row('SELECT * FROM reservations WHERE id = ?', [$id]) ?? not_found();
    $back = ticket_back($r);
    if (is_withdrawn($r)) { flash('This ticket is withdrawn. Reinstate it before changing its details.', 'err'); redirect($back); }
    $name = post_str('full_name', 140); $phone = post_str('phone', 30); $email = strtolower(post_str('email', 190));
    $guests = (int)($_POST['guests'] ?? $r['guests']);
    $type = array_key_exists($_POST['type'] ?? '', RES_TYPES) ? $_POST['type'] : $r['type'];
    $package = post_str('package', 80);
    $err = [];
    if (mb_strlen($name) < 2) $err[] = 'Enter the guest name.';
    if ($phone !== '' && !valid_phone($phone)) $err[] = 'The phone number looks invalid.';
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $err[] = 'The email address looks invalid.';
    if ($guests < 1 || $guests > 100) $err[] = 'Guests must be between 1 and 100.';
    if ($err) { foreach ($err as $x) flash($x, 'err'); redirect($back); }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $ev = row('SELECT * FROM events WHERE id = ? FOR UPDATE', [$r['event_id']]);
        $extra = $guests - (int)$r['guests'];
        if ($extra > 0 && in_array($r['status'], ['pending', 'confirmed'], true)) {
            $left = event_remaining($ev);
            if ($left !== null && $extra > $left) throw new RuntimeException($left > 0 ? "Only $left more place(s) are left for this event." : 'This event is fully booked.');
        }
        q('UPDATE reservations SET full_name=?, phone=?, email=?, guests=?, type=?, package=? WHERE id=?',
            [$name, $phone !== '' ? $phone : 'N/A', $email ?: null, $guests, $type, $package !== '' ? $package : null, $id]);
        $pdo->commit();
    } catch (RuntimeException $e) {
        $pdo->rollBack(); flash($e->getMessage(), 'err'); redirect($back);
    } catch (Throwable $e) { $pdo->rollBack(); throw $e; }

    audit('ticket.update', "#$id {$r['reference']} $name x$guests");
    $msg = "Ticket {$r['reference']} updated. The ticket link now shows the new details.";
    if (!empty($_POST['notify']) && $email !== '' && $r['status'] === 'confirmed') {
        $fresh = reservation_with_event($id);
        $msg .= send_mail($email, 'Your Chill & Bite ticket was updated: ' . $r['reference'], ticket_updated_message($fresh)) ? " The guest was emailed." : '';
    }
    flash($msg);
    redirect($back);
}

/** Withdraw a ticket: it stops working immediately and the guest's ticket link shows "withdrawn". */
function admin_ticket_withdraw(array $a): void
{
    require_can('tickets');
    $id = (int)$a['id'];
    $r = row('SELECT * FROM reservations WHERE id = ?', [$id]) ?? not_found();
    $back = ticket_back($r);
    if (!in_array($r['status'], ['pending', 'confirmed'], true)) { flash('This ticket is already inactive.', 'err'); redirect($back); }
    $reason = post_str('reason', 200);
    q("UPDATE reservations SET status='cancelled', withdrawn_at = NOW(), withdrawn_reason = ?, checked_in_at = NULL WHERE id = ?", [$reason !== '' ? $reason : null, $id]);
    audit('ticket.withdraw', "#$id {$r['reference']}" . ($reason !== '' ? ": $reason" : ''));
    $msg = "Ticket {$r['reference']} withdrawn. Its link and QR code now show it as withdrawn and the door will refuse it.";
    if (!empty($_POST['notify']) && !empty($r['email'])) {
        $fresh = reservation_with_event($id);
        $msg .= send_mail($r['email'], 'Your Chill & Bite ticket was withdrawn: ' . $r['reference'], ticket_withdrawn_message($fresh)) ? ' The guest was emailed.' : '';
    }
    flash($msg . ' Record a refund on the booking page if money was paid.');
    redirect($back);
}

/** Undo a withdrawal. The same ticket link works again, capacity permitting. */
function admin_ticket_reinstate(array $a): void
{
    require_can('tickets');
    $id = (int)$a['id'];
    $r = row('SELECT * FROM reservations WHERE id = ?', [$id]) ?? not_found();
    $back = ticket_back($r);
    if (!is_withdrawn($r)) { flash('Only withdrawn tickets can be reinstated.', 'err'); redirect($back); }
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $ev = row('SELECT * FROM events WHERE id = ? FOR UPDATE', [$r['event_id']]);
        $left = event_remaining($ev);
        if ($left !== null && (int)$r['guests'] > $left) throw new RuntimeException($left > 0 ? "Only $left place(s) are left, so this ticket for {$r['guests']} cannot be reinstated." : 'This event is fully booked, so the ticket cannot be reinstated.');
        q("UPDATE reservations SET status='confirmed', withdrawn_at = NULL, withdrawn_reason = NULL WHERE id = ?", [$id]);
        $pdo->commit();
    } catch (RuntimeException $e) {
        $pdo->rollBack(); flash($e->getMessage(), 'err'); redirect($back);
    } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
    audit('ticket.reinstate', "#$id {$r['reference']}");
    flash("Ticket {$r['reference']} reinstated. The guest's link works again.");
    redirect($back);
}
