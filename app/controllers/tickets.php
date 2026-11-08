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
    if (($_POST['go'] ?? '') === 'ticket') {
        $r = row('SELECT id, status, withdrawn_at FROM reservations WHERE reference = ?', [$ref]);
        if ($r['status'] === 'confirmed') redirect('ticket/' . ticket_token((int)$r['id']));
        flash($r['status'] === 'pending' ? 'Your booking is not approved yet. Your ticket will appear here as soon as it is.' : 'There is no active ticket for this booking.', $r['status'] === 'pending' ? 'ok' : 'err');
    }
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
/** Remember that a ticket was sent to its owner, by email or WhatsApp. */
function ticket_sent_log(int $reservationId, string $channel, string $note = ''): bool
{
    if (!in_array($channel, ['email', 'whatsapp'], true)) return false;
    $sql = 'INSERT INTO ticket_sends (reservation_id, channel, note, sent_by) VALUES (?,?,?,?)';
    $args = [$reservationId, $channel, mb_substr($note, 0, 80) ?: null, current_user()['id'] ?? null];
    try { q($sql, $args); return true; }
    catch (PDOException $e) {
        if ($e->getCode() !== '42S02') { $GLOBALS['tk_sends_error'] = $e->getMessage(); error_log('ticket_sent_log: ' . $e->getMessage()); return false; }
        try { ticket_sends_ensure(); q($sql, $args); return true; }
        catch (Throwable $e2) { $GLOBALS['tk_sends_error'] = $e2->getMessage(); error_log('ticket_sent_log: ' . $e2->getMessage()); return false; }
    }
}
function ticket_sends_ensure(): void
{
    db()->exec("CREATE TABLE IF NOT EXISTS ticket_sends (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, reservation_id INT UNSIGNED NOT NULL, channel ENUM('email','whatsapp') NOT NULL, note VARCHAR(80) NULL,
        sent_by INT UNSIGNED NULL, sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY idx_ts_res (reservation_id, channel),
        CONSTRAINT fk_ts_res FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
/** Latest send time and number of sends per channel for these bookings: [reservation id => [channel => [at, n]]]. */
function ticket_sends_for(array $ids): array
{
    $ids = array_values(array_filter(array_map('intval', $ids)));
    if (!$ids) return [];
    $sql = 'SELECT reservation_id, channel, MAX(sent_at) AS at, COUNT(*) AS n FROM ticket_sends WHERE reservation_id IN (' . implode(',', $ids) . ') GROUP BY reservation_id, channel';
    try { $rows = rows($sql); }
    catch (PDOException $e) {
        $rows = [];
        try { ticket_sends_ensure(); } catch (Throwable $e2) { $GLOBALS['tk_sends_error'] = $e2->getMessage(); }
    }
    $out = [];
    foreach ($rows as $x) $out[(int)$x['reservation_id']][$x['channel']] = ['at' => $x['at'], 'n' => (int)$x['n']];
    return $out;
}
/** The WhatsApp button was pressed: remember the ticket as sent on WhatsApp (answers JSON). */
function admin_ticket_sent(array $a): void
{
    require_can('tickets');
    $id = (int)$a['id'];
    $r = row("SELECT id, status, event_id FROM reservations WHERE id = ?", [$id]);
    $channel = in_array($_POST['channel'] ?? '', ['email', 'whatsapp'], true) ? $_POST['channel'] : 'whatsapp';
    $page = isset($_POST['back']);                       // a plain button on the list (redirects) or the WhatsApp press (JSON)
    if (!$r || $r['status'] !== 'confirmed') {
        if ($page) { flash('Only confirmed tickets can be marked as sent.', 'err'); redirect('admin/tickets'); }
        header('Content-Type: application/json'); http_response_code(404); echo json_encode(['ok' => false, 'error' => 'Ticket not found']); return;
    }
    $ok = ticket_sent_log($id, $channel, ($page ? 'marked as sent by ' : 'sent by ') . (current_user()['name'] ?? 'staff'));
    if ($ok) audit('ticket.sent_' . $channel, '#' . $id);
    if ($page) {
        flash($ok ? 'Marked as sent on ' . ($channel === 'email' ? 'email' : 'WhatsApp') . '. You can resend it any time.' : 'Could not save the mark: ' . ($GLOBALS['tk_sends_error'] ?? 'unknown error'), $ok ? 'ok' : 'err');
        $back = (string)$_POST['back'];
        redirect(preg_match('#^admin/tickets(\?[A-Za-z0-9=&_%.+-]*)?$#', $back) ? $back : 'admin/tickets?event=' . (int)$r['event_id']);
    }
    header('Content-Type: application/json'); header('Cache-Control: no-store');
    echo json_encode($ok ? ['ok' => true, 'at' => date('j M H:i')] : ['ok' => false, 'error' => (string)($GLOBALS['tk_sends_error'] ?? 'Could not save')]);
}
/** Mark the listed tickets of an event as already sent (for tickets that were sent before sends were recorded). */
function admin_tickets_mark_all(): void
{
    require_can('tickets');
    $eid = (int)($_POST['event_id'] ?? 0);
    $ch = in_array($_POST['channel'] ?? '', ['email', 'whatsapp', 'both'], true) ? $_POST['channel'] : 'both';
    $back = 'admin/tickets?event=' . $eid;
    if (!val('SELECT id FROM events WHERE id = ?', [$eid])) { flash('Choose an event.', 'err'); redirect('admin/tickets'); }
    try { ticket_sends_ensure(); } catch (Throwable $e) { flash('Could not prepare the send records: ' . $e->getMessage(), 'err'); redirect($back); }
    $n = ['email' => 0, 'whatsapp' => 0];
    foreach (['email', 'whatsapp'] as $c) {
        if ($ch !== 'both' && $ch !== $c) continue;
        $has = $c === 'email' ? "r.email IS NOT NULL AND r.email <> ''" : "r.phone <> 'N/A' AND r.phone <> ''";
        $list = rows("SELECT r.id FROM reservations r WHERE r.event_id = ? AND r.status = 'confirmed' AND $has AND NOT EXISTS (SELECT 1 FROM ticket_sends s WHERE s.reservation_id = r.id AND s.channel = ?)", [$eid, $c]);
        foreach ($list as $x) { ticket_sent_log((int)$x['id'], $c, 'marked as already sent'); $n[$c]++; }
    }
    audit('ticket.mark_all_sent', "event #$eid, {$n['email']} email, {$n['whatsapp']} WhatsApp");
    flash('Marked ' . ($n['email'] ? $n['email'] . ' as emailed' : '') . ($n['email'] && $n['whatsapp'] ? ' and ' : '') . ($n['whatsapp'] ? $n['whatsapp'] . ' as sent on WhatsApp' : '') . ($n['email'] + $n['whatsapp'] === 0 ? 'Nothing to mark: every ticket with those contact details is already marked' : '') . '. You can still resend any of them.');
    redirect($back);
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
        'list' => $list = ($eid ? rows(RES_SELECT . " WHERE $w ORDER BY r.full_name LIMIT 300", $p) : []),
        'sends' => ticket_sends_for(array_column($list, 'id')),
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
    // an optional table package (must be one of this event's packages); it can be complimentary or recorded as paid
    $pkgName = post_str('package', 80); $pkgPrice = null; $pay = in_array($_POST['pay'] ?? '', ['cash', 'mobile_money', 'bank'], true) ? $_POST['pay'] : 'free';
    if ($pkgName !== '') { foreach (event_packages($eid) as $pk) if ($pk['name'] === $pkgName) $pkgPrice = (int)$pk['price']; if ($pkgPrice === null) $pkgName = ''; else $type = 'table'; }
    if ($pkgName !== '') foreach (event_packages($eid) as $pk) if ($pk['name'] === $pkgName && (int)($pk['admits'] ?? 0) > 0) $guests = (int)$pk['admits'];
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
        $due = ($pkgName !== '' && $pay !== 'free') ? (int)$pkgPrice : 0;
        q("INSERT INTO reservations (reference, event_id, type, full_name, phone, email, guests, package, amount_due, status, admin_note)
           VALUES (?,?,?,?,?,?,?,?,?,'confirmed',?)", [$ref, $eid, $type, $name, $phone !== '' ? $phone : 'N/A', $email ?: null, $guests, $pkgName !== '' ? $pkgName : null, $due, mb_substr($note, 0, 255)]);
        $rid = (int)$pdo->lastInsertId();
        if ($due > 0) q('INSERT INTO payments (reservation_id, amount, method, note, recorded_by) VALUES (?,?,?,?,?)', [$rid, $due, $pay, 'Package ' . $pkgName . ' paid when the ticket was issued', current_user()['id']]);
        $pdo->commit();
    } catch (RuntimeException $e) {
        $pdo->rollBack(); flash($e->getMessage(), 'err'); redirect($back);
    } catch (Throwable $e) { $pdo->rollBack(); throw $e; }

    audit('ticket.issue', "#$rid $ref $name x$guests" . ($pkgName !== '' ? " package $pkgName" . ($due > 0 ? " paid $due via $pay" : ' complimentary') : ''));
    $sent = $email !== '' && send_ticket($rid);
    flash("Ticket $ref created for $name (" . ($pkgName !== '' ? $pkgName . ' table, ' : '') . "$guests guest" . ($guests === 1 ? '' : 's') . ')' . ($sent ? ' and emailed to them.' : '. Use the buttons to share it.'));
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
    $oldPhone = ($r['phone'] === 'N/A' ? '' : (string)$r['phone']);
    if ($phone !== '' && preg_replace('/\D+/', '', $phone) !== preg_replace('/\D+/', '', $oldPhone)) {
        try { q("DELETE FROM ticket_sends WHERE reservation_id = ? AND channel = 'whatsapp'", [$id]); } catch (Throwable $e) {}
        $msg .= ' The WhatsApp number changed, so the ticket shows as not sent on WhatsApp yet: tap 💬 WhatsApp to send it to the new number.';
    }
    if ($email !== '' && $email !== strtolower((string)$r['email'])) {
        try { q("DELETE FROM ticket_sends WHERE reservation_id = ? AND channel = 'email'", [$id]); } catch (Throwable $e) {}
        $msg .= ' The email changed, so use ✉️ Email to send it to the new address.';
    }
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
