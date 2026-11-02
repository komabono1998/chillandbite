<?php
declare(strict_types=1);

function admin_view(string $view, array $data = []): void { render('admin/' . $view, $data, 'admin'); }
function dt_in(?string $s): ?string
{
    $s = trim((string)$s);
    if ($s === '' || !($t = strtotime($s))) return null;
    return date('Y-m-d H:i:s', $t);
}
function dt_out(?string $s): string { return $s ? date('Y-m-d\TH:i', strtotime($s)) : ''; }
function post_str(string $k, int $max = 255): string { return mb_substr(trim((string)($_POST[$k] ?? '')), 0, $max); }

/* ================= Auth ================= */
function admin_login_form(): void
{
    if (current_user()) redirect('admin');
    admin_view('login', ['title' => 'Staff login']);
}
function admin_login(): void
{
    if (rate_limited('login', 10, 900)) { flash('Too many login attempts. Please wait 15 minutes.', 'err'); redirect('admin/login'); }
    $email = strtolower(post_str('email', 190));
    $u = row('SELECT * FROM users WHERE email = ? AND active = 1', [$email]);
    // Verify against a dummy hash when the user is unknown so timing does not reveal valid emails.
    $hash = $u['password_hash'] ?? password_hash('not-a-real-password', PASSWORD_DEFAULT);
    if (!password_verify((string)($_POST['password'] ?? ''), $hash) || !$u) {
        flash('Invalid email or password.', 'err');
        redirect('admin/login');
    }
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$u['id'];
    if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT))
        q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash((string)$_POST['password'], PASSWORD_DEFAULT), $u['id']]);
    q('UPDATE users SET last_login = NOW() WHERE id = ?', [$u['id']]);
    q('INSERT INTO audit_log (user_id, action, detail, ip) VALUES (?,?,?,?)', [$u['id'], 'login', $u['email'], client_ip()]);
    $next = (string)($_SESSION['next'] ?? '');
    unset($_SESSION['next']);
    // Only follow same-site admin paths (no open redirect)
    if (preg_match('#^' . preg_quote(BASE, '#') . '/admin(/[A-Za-z0-9/_-]*)?(\?[A-Za-z0-9=&_%.-]*)?$#', $next)) redirect(substr($next, strlen(BASE)));
    redirect('admin');
}
function admin_logout(): void
{
    $_SESSION = [];
    session_destroy();
    redirect('admin/login');
}

/* ================= Events ================= */
function admin_events(): void
{
    require_can('events');
    admin_view('events', [
        'title' => 'Events',
        'events' => rows("SELECT e.*, c.name AS category, (SELECT COALESCE(SUM(guests),0) FROM reservations r WHERE r.event_id=e.id AND r.status IN ('pending','confirmed')) AS booked
                          FROM events e LEFT JOIN event_categories c ON c.id=e.category_id ORDER BY e.start_at DESC LIMIT 200"),
    ]);
}
function admin_event_form(array $a = []): void
{
    require_can('events');
    $e = null; $opts = [];
    if (isset($a['id'])) {
        $e = row('SELECT * FROM events WHERE id = ?', [(int)$a['id']]) ?? not_found();
        foreach (event_options((int)$e['id']) as $o) $opts[$o['type']] = $o;
    }
    admin_view('event_form', ['title' => $e ? 'Edit event' : 'New event', 'e' => $e, 'opts' => $opts, 'cats' => rows('SELECT * FROM event_categories ORDER BY id'),
        'old' => $_SESSION['old'] ?? [],
        'pkgText' => $e ? implode("\n", array_map(fn($p) => $p['name'] . ' | ' . (int)$p['price'], event_packages((int)$e['id']))) : '']);
    clear_old();
}
function admin_event_save(): void
{
    require_can('events');
    $id = (int)($_POST['id'] ?? 0);
    $back = $id ? "admin/events/$id" : 'admin/events/new';
    $title = post_str('title', 190);
    $start = dt_in($_POST['start_at'] ?? '');
    $end = dt_in($_POST['end_at'] ?? '');
    $err = [];
    if (mb_strlen($title) < 3) $err[] = 'Title is required.';
    if (!$start) $err[] = 'Start date and time are required.';
    if ($start && $end && $end < $start) $err[] = 'End must be after start.';
    $enabled = array_filter(array_keys(RES_TYPES), fn($t) => !empty($_POST['opt'][$t]['on']));
    if ($err) { keep_old($_POST); foreach ($err as $x) flash($x, 'err'); redirect($back); }

    try { $poster = save_upload('poster', 'posters'); }
    catch (RuntimeException $ex) { keep_old($_POST); flash($ex->getMessage(), 'err'); redirect($back); }

    $cap = trim((string)($_POST['capacity'] ?? ''));
    $status = in_array($_POST['status'] ?? '', ['draft', 'published', 'cancelled', 'archived'], true) ? $_POST['status'] : 'draft';
    $vals = [
        (int)($_POST['category_id'] ?? 0) ?: null, $title, post_str('short_desc', 300), (string)($_POST['description'] ?? ''),
        post_str('venue', 190) ?: 'Revelation Cottages', post_str('location', 190) ?: 'Gulu City, Uganda',
        $start, $end, $status, $cap === '' ? null : max(0, (int)$cap),
        dt_in($_POST['reservation_opens'] ?? ''), dt_in($_POST['reservation_closes'] ?? ''),
        ($_POST['booking_mode'] ?? '') === 'approval' ? 'approval' : 'instant', empty($_POST['featured']) ? 0 : 1,
        post_str('video_url') ?: null,
    ];
    if ($id) {
        $slug = val('SELECT slug FROM events WHERE id = ?', [$id]) ?? not_found();
        q('UPDATE events SET category_id=?, title=?, short_desc=?, description=?, venue=?, location=?, start_at=?, end_at=?, status=?, capacity=?,
           reservation_opens=?, reservation_closes=?, booking_mode=?, featured=?, video_url=? WHERE id=?', [...$vals, $id]);
        if ($poster) q('UPDATE events SET poster = ? WHERE id = ?', [$poster, $id]);
        audit('event.update', "#$id $title");
    } else {
        q('INSERT INTO events (category_id, title, short_desc, description, venue, location, start_at, end_at, status, capacity,
           reservation_opens, reservation_closes, booking_mode, featured, video_url, slug, poster) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [...$vals, unique_slug('events', $title), $poster]);
        $id = (int)db()->lastInsertId();
        audit('event.create', "#$id $title");
    }
    q('DELETE FROM event_reservation_options WHERE event_id = ?', [$id]);
    foreach ($enabled as $t) {
        $o = $_POST['opt'][$t];
        $min = max(1, (int)($o['min'] ?? 1)); $max = max($min, (int)($o['max'] ?? 20));
        q('INSERT INTO event_reservation_options (event_id, type, price_per_guest, min_guests, max_guests) VALUES (?,?,?,?,?)',
            [$id, $t, max(0, (int)($o['price'] ?? 0)), $min, $max]);
    }
    // Table packages: one per line as "Name | price"
    q('DELETE FROM event_packages WHERE event_id = ?', [$id]);
    $sort = 0;
    foreach (preg_split('/\R/', (string)($_POST['packages'] ?? '')) as $line) {
        if (!str_contains($line, '|')) continue;
        [$pn, $pp] = array_map('trim', explode('|', $line, 2));
        $pn = mb_substr($pn, 0, 80); $pp = (int)preg_replace('/\D+/', '', $pp);
        if ($pn === '' || $pp < 0) continue;
        q('INSERT IGNORE INTO event_packages (event_id, name, price, sort) VALUES (?,?,?,?)', [$id, $pn, $pp, $sort++]);
    }
    flash('Event saved.');
    redirect("admin/events/$id");
}
function admin_event_status(array $a): void
{
    require_can('events');
    $s = $_POST['status'] ?? '';
    if (!in_array($s, ['draft', 'published', 'cancelled', 'archived'], true)) not_found();
    q('UPDATE events SET status = ? WHERE id = ?', [$s, (int)$a['id']]);
    audit('event.status', "#{$a['id']} -> $s");
    flash("Event marked $s.");
    redirect('admin/events');
}
/** Generate the next N first-Sunday editions from a template event. Each becomes a normal, editable draft. */
function admin_event_recurring(): void
{
    require_can('events');
    $tpl = row('SELECT * FROM events WHERE id = ?', [(int)($_POST['template_id'] ?? 0)]) ?? not_found();
    $n = min(12, max(1, (int)($_POST['months'] ?? 3)));
    $made = 0;
    $time = date('H:i:s', strtotime($tpl['start_at']));
    $dur = $tpl['end_at'] ? strtotime($tpl['end_at']) - strtotime($tpl['start_at']) : 0;
    $cursor = new DateTimeImmutable('first day of this month');
    for ($i = 0; $i < 14 && $made < $n; $i++, $cursor = $cursor->modify('+1 month')) {
        $d = first_sunday((int)$cursor->format('Y'), (int)$cursor->format('n'));
        $start = new DateTimeImmutable($d->format('Y-m-d') . ' ' . $time);
        if ($start < new DateTimeImmutable('now')) continue;
        if (val('SELECT id FROM events WHERE category_id <=> ? AND DATE(start_at) = ?', [$tpl['category_id'], $start->format('Y-m-d')])) continue;
        $title = 'Chill & Bite MEATINGS: ' . $start->format('F Y');
        q("INSERT INTO events (category_id, title, slug, short_desc, description, poster, venue, location, start_at, end_at, status, capacity, booking_mode)
           VALUES (?,?,?,?,?,?,?,?,?,?, 'draft', ?, ?)", [
            $tpl['category_id'], $title, unique_slug('events', $title), $tpl['short_desc'], $tpl['description'], $tpl['poster'], $tpl['venue'], $tpl['location'],
            $start->format('Y-m-d H:i:s'), $dur ? date('Y-m-d H:i:s', $start->getTimestamp() + $dur) : null, $tpl['capacity'], $tpl['booking_mode']]);
        $nid = (int)db()->lastInsertId();
        q('INSERT INTO event_reservation_options (event_id, type, price_per_guest, min_guests, max_guests)
           SELECT ?, type, price_per_guest, min_guests, max_guests FROM event_reservation_options WHERE event_id = ?', [$nid, $tpl['id']]);
        $made++;
    }
    audit('event.recurring', "from #{$tpl['id']}: $made created");
    flash("$made draft event(s) created on first Sundays. Review and publish them; you can change any date individually.");
    redirect('admin/events');
}

/* ================= Reservations ================= */
function reservation_filters(): array
{
    $w = ['1=1']; $p = [];
    if (!empty($_GET['event'])) { $w[] = 'r.event_id = ?'; $p[] = (int)$_GET['event']; }
    if (in_array($_GET['status'] ?? '', ['pending', 'confirmed', 'rejected', 'cancelled'], true)) { $w[] = 'r.status = ?'; $p[] = $_GET['status']; }
    if (!empty($_GET['date']) && strtotime($_GET['date'])) { $w[] = 'DATE(r.created_at) = ?'; $p[] = date('Y-m-d', strtotime($_GET['date'])); }
    if (($qq = trim((string)($_GET['q'] ?? ''))) !== '') {
        $w[] = '(r.full_name LIKE ? OR r.phone LIKE ? OR r.reference LIKE ?)';
        $like = '%' . addcslashes($qq, '%_\\') . '%'; array_push($p, $like, $like, $like);
    }
    return [implode(' AND ', $w), $p];
}
const RES_SELECT = 'SELECT r.*, e.title AS event_title, e.capacity, (SELECT COALESCE(SUM(amount),0) FROM payments p WHERE p.reservation_id = r.id) AS paid
                    FROM reservations r JOIN events e ON e.id = r.event_id';
function admin_reservations(): void
{
    require_can('reservations');
    [$w, $p] = reservation_filters();
    $eventId = (int)($_GET['event'] ?? 0);
    $ev = $eventId ? row('SELECT * FROM events WHERE id = ?', [$eventId]) : null;
    admin_view('reservations', [
        'title' => 'Reservations', 'ev' => $ev, 'booked' => $ev ? event_booked((int)$ev['id']) : null,
        'checked' => $ev ? (int)val("SELECT COALESCE(SUM(guests),0) FROM reservations WHERE event_id = ? AND checked_in_at IS NOT NULL AND status='confirmed'", [$ev['id']]) : null,
        'list' => rows(RES_SELECT . " WHERE $w ORDER BY r.id DESC LIMIT 300", $p),
        'events' => rows('SELECT id, title, start_at FROM events ORDER BY start_at DESC LIMIT 100'),
    ]);
}
function admin_reservation(array $a): void
{
    require_can('reservations');
    $r = row(RES_SELECT . ' WHERE r.id = ?', [(int)$a['id']]) ?? not_found();
    $ticket = $wa = null;
    if ($r['status'] === 'confirmed') {
        $ticket = ticket_url((int)$r['id']);
        $wa = wa_link($r['phone'], ticket_message(reservation_with_event((int)$r['id'])));
    }
    admin_view('reservation', ['title' => 'Reservation ' . $r['reference'], 'r' => $r, 'ticket' => $ticket, 'wa' => $wa,
        'payments' => rows('SELECT p.*, u.name AS by_name FROM payments p LEFT JOIN users u ON u.id=p.recorded_by WHERE reservation_id = ? ORDER BY id', [$r['id']])]);
}
/** Door check-in: the ticket's QR code opens this page for logged-in staff. */
function admin_checkin(array $a): void
{
    require_can_any(['reservations', 'checkin']);
    if (!preg_match('/^[a-f0-9]{24}$/', $a['token'])) not_found();
    $r = row(RES_SELECT . ' WHERE r.ticket_token = ?', [$a['token']]);
    admin_view('checkin', ['title' => 'Check-in', 'r' => $r, 'token' => $a['token']]);
}
function admin_reservation_action(array $a): void
{
    // Each action needs its own permission: door staff can only check in, finance can only record money.
    $needs = ['checkin' => 'checkin', 'undo_checkin' => 'checkin', 'payment' => 'payments', 'refund' => 'payments'];
    require_can($needs[$_POST['action'] ?? ''] ?? 'reservations_manage');
    $id = (int)$a['id'];
    $r = row('SELECT * FROM reservations WHERE id = ?', [$id]) ?? not_found();
    $act = $_POST['action'] ?? '';
    $back = $_POST['back'] ?? '';
    $dest = (str_starts_with($back, 'admin/reservations') || preg_match('#^admin/checkin(/[a-f0-9]{24}|\?[A-Za-z0-9=&_%.-]*)?$#', $back)) ? $back
        : (can('reservations') ? "admin/reservations/$id" : 'admin/checkin');
    switch ($act) {
        case 'approve':
            if ($r['status'] !== 'pending') { flash('Only pending bookings can be approved.', 'err'); break; }
            q("UPDATE reservations SET status='confirmed' WHERE id = ?", [$id]);
            flash(send_ticket($id) ? 'Booking approved and the ticket was emailed to the guest.'
                : 'Booking approved. This guest left no email, so send the ticket on WhatsApp from the booking page.'); break;
        case 'send_ticket':
            if ($r['status'] !== 'confirmed') { flash('Tickets are only issued for confirmed bookings.', 'err'); break; }
            $ok = send_ticket($id);
            flash($ok ? 'Ticket emailed to the guest.' : 'No email on file for this guest. Use the WhatsApp button instead.', $ok ? 'ok' : 'err'); break;
        case 'reject':
            q("UPDATE reservations SET status='rejected' WHERE id = ? AND status='pending'", [$id]); flash('Booking rejected.'); break;
        case 'cancel':
            q("UPDATE reservations SET status='cancelled' WHERE id = ? AND status IN ('pending','confirmed')", [$id]);
            flash('Booking cancelled. Record a refund below if money was paid.'); break;
        case 'checkin':
            if ($r['status'] !== 'confirmed') { flash('Only confirmed bookings can be checked in.', 'err'); break; }
            q('UPDATE reservations SET checked_in_at = NOW() WHERE id = ?', [$id]); flash('Guest checked in.'); break;
        case 'undo_checkin':
            q('UPDATE reservations SET checked_in_at = NULL WHERE id = ?', [$id]); flash('Check-in undone.'); break;
        case 'payment':
        case 'refund':
            $amt = abs((int)preg_replace('/\D+/', '', (string)($_POST['amount'] ?? '0')));
            if ($amt < 1) { flash('Enter an amount.', 'err'); break; }
            $method = in_array($_POST['method'] ?? '', ['cash', 'mobile_money', 'bank'], true) ? $_POST['method'] : 'cash';
            if ($act === 'refund') { $method = 'refund'; $amt = -$amt; }
            q('INSERT INTO payments (reservation_id, amount, method, note, recorded_by) VALUES (?,?,?,?,?)', [$id, $amt, $method, post_str('note') ?: null, current_user()['id']]);
            $paid = res_paid($id);
            $confirmedNow = $act === 'payment' && $r['status'] === 'pending' && $paid >= (float)$r['amount_due'];
            if ($confirmedNow) { q("UPDATE reservations SET status='confirmed' WHERE id = ?", [$id]); send_ticket($id); }
            flash($act === 'refund' ? 'Refund recorded.' : ($confirmedNow ? 'Payment recorded in full. Booking confirmed and ticket issued.' : 'Payment recorded.')); break;
        case 'note':
            q('UPDATE reservations SET admin_note = ? WHERE id = ?', [post_str('admin_note') ?: null, $id]); flash('Note saved.'); break;
        default: not_found();
    }
    audit('reservation.' . $act, "#{$id} {$r['reference']}");
    redirect($dest);
}

/* ================= Users ================= */
function admin_users(): void
{
    require_can('users');
    admin_view('users', ['title' => 'Users & permissions', 'users' => rows('SELECT * FROM users ORDER BY id')]);
}
function admin_users_save(): void
{
    require_can('users');
    $me = current_user();
    $act = $_POST['action'] ?? '';
    if ($act === 'create') {
        $email = strtolower(post_str('email', 190)); $pw = (string)($_POST['password'] ?? '');
        $role = $_POST['role'] ?? '';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !isset(ROLES[$role]) || mb_strlen(post_str('name')) < 2) flash('Name, valid email and role are required.', 'err');
        elseif (strlen($pw) < 10) flash('Password must be at least 10 characters.', 'err');
        elseif (val('SELECT id FROM users WHERE email = ?', [$email])) flash('That email is already used.', 'err');
        else {
            q('INSERT INTO users (name, email, password_hash, role) VALUES (?,?,?,?)', [post_str('name', 120), $email, password_hash($pw, PASSWORD_DEFAULT), $role]);
            audit('user.create', "$email ($role)"); flash('User created.');
        }
    } else {
        $id = (int)($_POST['id'] ?? 0);
        if ($act === 'toggle' && $id !== (int)$me['id']) { q('UPDATE users SET active = 1 - active WHERE id = ?', [$id]); audit('user.toggle', "#$id"); flash('User updated.'); }
        elseif ($act === 'role' && $id !== (int)$me['id'] && isset(ROLES[$_POST['role'] ?? ''])) { q('UPDATE users SET role = ? WHERE id = ?', [$_POST['role'], $id]); audit('user.role', "#$id -> {$_POST['role']}"); flash('Role updated.'); }
        elseif ($act === 'password') {
            $pw = (string)($_POST['password'] ?? '');
            if (strlen($pw) < 10) flash('Password must be at least 10 characters.', 'err');
            else { q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($pw, PASSWORD_DEFAULT), $id]); audit('user.password', "#$id"); flash('Password changed.'); }
        } elseif ($act === 'profile') {
            $name = post_str('name', 120); $email = strtolower(post_str('email', 190));
            if (mb_strlen($name) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL)) flash('Enter a name and a valid email.', 'err');
            elseif (val('SELECT id FROM users WHERE email = ? AND id <> ?', [$email, $id])) flash('That email belongs to another user.', 'err');
            else { q('UPDATE users SET name = ?, email = ? WHERE id = ?', [$name, $email, $id]); audit('user.profile', "#$id $email"); flash('Details updated.'); }
        } else flash('You cannot change your own role or status.', 'err');
    }
    redirect('admin/users');
}

/* ================= Settings & audit ================= */
const SETTING_KEYS = ['site_name', 'tagline', 'announcement', 'venue', 'location', 'contact_phone', 'contact_email', 'whatsapp', 'whatsapp_group', 'host_bio',
    'social_facebook', 'social_instagram', 'social_youtube', 'social_tiktok', 'social_x'];
function admin_settings(): void { require_can('settings'); admin_view('settings', ['title' => 'Website settings']); }
function admin_settings_save(): void
{
    require_can('settings');
    $group = post_str('whatsapp_group', 300);
    if ($group !== '' && !preg_match('#^https://chat\.whatsapp\.com/#', $group)) {
        flash('WhatsApp group link must start with https://chat.whatsapp.com/', 'err');
        redirect('admin/settings');
    }
    foreach (SETTING_KEYS as $k) save_setting($k, post_str($k, 1000));
    save_setting('announcement_on', empty($_POST['announcement_on']) ? '0' : '1');
    audit('settings.update');
    flash('Settings saved.');
    redirect('admin/settings');
}
function admin_audit(): void
{
    require_can('audit');
    admin_view('audit', ['title' => 'Audit log', 'log' => rows('SELECT l.*, u.name FROM audit_log l LEFT JOIN users u ON u.id = l.user_id ORDER BY l.id DESC LIMIT 300')]);
}
