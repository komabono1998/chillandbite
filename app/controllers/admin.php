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
    // a new event starts with the default rates saved under Ticket rates
    $tpl = !$e ? rates_template() : null; $tplOn = null; $tplPk = '';
    if ($tpl) { $tplOn = []; foreach ($tpl['opt'] as $t => $o) { $tplOn[$t] = 1; $opts[$t] = ['price_per_guest' => $o['price'], 'min_guests' => $o['min'], 'max_guests' => $o['max']]; } $tplPk = (string)$tpl['packages']; }
    admin_view('event_form', ['title' => $e ? 'Edit event' : 'New event', 'e' => $e, 'opts' => $opts, 'tplOn' => $tplOn, 'cats' => rows('SELECT * FROM event_categories ORDER BY id'),
        'old' => $_SESSION['old'] ?? [],
        'pkgText' => $e ? rates_packages_text(event_packages((int)$e['id'])) : $tplPk]);
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
    rates_ensure_cols();
    foreach (rates_parse_packages((string)($_POST['packages'] ?? '')) as $i => [$pn, $pp, $ad])
        q('INSERT IGNORE INTO event_packages (event_id, name, price, admits, sort) VALUES (?,?,?,?,?)', [$id, $pn, $pp, $ad, $i]);
    events_ensure_countdown_cols();
    $cdAt = dt_in($_POST['countdown_at'] ?? '');
    q('UPDATE events SET countdown_at = ?, countdown_label = ? WHERE id = ?', [$cdAt, $cdAt ? (post_str('countdown_label', 80) ?: null) : null, $id]);
    flash('Event saved.' . ($cdAt ? ' The homepage countdown now runs to ' . date('j M, g:i A', strtotime($cdAt)) . '.' : ''));
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
/** Table package names used by bookings (and set on events), for the "Table package" filters. $eventId 0 = all events. */
function package_options(int $eventId = 0): array
{
    $names = [];
    $a = $eventId ? [$eventId] : [];
    foreach (rows('SELECT DISTINCT package AS n FROM reservations WHERE package IS NOT NULL AND package <> \'\'' . ($eventId ? ' AND event_id = ?' : ''), $a) as $x) $names[$x['n']] = true;
    foreach (rows('SELECT DISTINCT name AS n FROM event_packages' . ($eventId ? ' WHERE event_id = ?' : ''), $a) as $x) $names[$x['n']] = true;
    $names = array_keys($names); natcasesort($names);
    return array_values($names);
}
/** SQL for the chosen package: '' = any, '__none' = bookings without a table package. */
function package_condition(string $pkg, string $col = 'r.package'): array
{
    if ($pkg === '') return ['', []];
    if ($pkg === '__none') return [" AND ($col IS NULL OR $col = '')", []];
    return [" AND $col = ?", [$pkg]];
}
function package_select(array $options, string $current, bool $autoSubmit = false): string
{
    $o = '<option value="">All packages</option>';
    foreach ($options as $n) $o .= '<option value="' . h($n) . '"' . ($current === $n ? ' selected' : '') . '>' . h($n) . '</option>';
    $o .= '<option value="__none"' . ($current === '__none' ? ' selected' : '') . '>No table package</option>';
    return '<select name="package"' . ($autoSubmit ? ' onchange="this.form.submit()"' : '') . ' aria-label="Table package">' . $o . '</select>';
}
function reservation_filters(): array
{
    $w = ['1=1']; $p = [];
    if (!empty($_GET['event'])) { $w[] = 'r.event_id = ?'; $p[] = (int)$_GET['event']; }
    if (in_array($_GET['status'] ?? '', ['pending', 'confirmed', 'rejected', 'cancelled'], true)) { $w[] = 'r.status = ?'; $p[] = $_GET['status']; }
    if (!empty($_GET['date']) && strtotime($_GET['date'])) { $w[] = 'DATE(r.created_at) = ?'; $p[] = date('Y-m-d', strtotime($_GET['date'])); }
    [$pc, $pa] = package_condition(trim((string)($_GET['package'] ?? '')));
    if ($pc !== '') { $w[] = ltrim(substr($pc, 5)); array_push($p, ...$pa); }
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
    $listRows = rows(RES_SELECT . " WHERE $w ORDER BY r.id DESC LIMIT 300", $p);
    admin_view('reservations', [
        'title' => 'Reservations', 'ev' => $ev, 'booked' => $ev ? event_booked((int)$ev['id']) : null,
        'checked' => $ev ? (int)val("SELECT COALESCE(SUM(guests),0) FROM reservations WHERE event_id = ? AND checked_in_at IS NOT NULL AND status='confirmed'", [$ev['id']]) : null,
        'list' => $listRows,
        'events' => rows('SELECT id, title, start_at FROM events ORDER BY start_at DESC LIMIT 100'),
        'package_opts' => package_options($eventId), 'purge' => can('purge') ? res_purge_counts($eventId) : null, 'force' => can('purge') ? res_force_counts($eventId) : null,
        'has_pay' => $listRows ? array_flip(array_map('intval', array_column(rows('SELECT DISTINCT reservation_id FROM payments WHERE reservation_id IN (' . implode(',', array_map('intval', array_column($listRows, 'id'))) . ')'), 'reservation_id'))) : [],
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
    try { daraza_expire_stale(); $attempts = rows('SELECT a.*, u.name AS by_name FROM pay_attempts a LEFT JOIN users u ON u.id = a.started_by WHERE a.reservation_id = ? ORDER BY a.id DESC LIMIT 20', [$r['id']]); } catch (Throwable $e) { $attempts = []; }
    admin_view('reservation', ['title' => 'Reservation ' . $r['reference'], 'r' => $r, 'ticket' => $ticket, 'wa' => $wa, 'attempts' => $attempts,
        'payments' => rows('SELECT p.*, u.name AS by_name FROM payments p LEFT JOIN users u ON u.id=p.recorded_by WHERE reservation_id = ? ORDER BY id', [$r['id']])]);
}
/** Door check-in: the ticket's QR code opens this page for logged-in staff. */
function admin_checkin(array $a): void
{
    require_can_any(['reservations', 'checkin']);
    if (!preg_match('/^[a-f0-9]{24}$/', $a['token'])) not_found();
    $r = row(RES_SELECT . ' WHERE r.ticket_token = ?', [$a['token']]);
    $dups = 0;
    if (!$r) {            // not a booking ticket: maybe a printed general ticket
        try { gen_ensure_cols(); $g = row("SELECT g.*, e.title AS event_title, COALESCE(NULLIF(b.label, ''), c.name) AS cat_name, c.prefix, COALESCE(b.admits, c.admits, 1) AS admits FROM gen_tickets g JOIN events e ON e.id = g.event_id LEFT JOIN gen_batches b ON b.id = g.batch_id LEFT JOIN ticket_categories c ON c.id = g.category_id WHERE g.token = ?", [$a['token']]); } catch (Throwable $e) { $g = null; }
        if ($g) {
            if ($g['status'] === 'used' && strtotime((string)$g['used_at']) < time() - 20) {
                audit('gentickets.duplicate_scan', '#' . $g['id'] . ' scanned again; first used ' . $g['used_at']);
                $dups = (int)val("SELECT COUNT(*) FROM audit_log WHERE action = 'gentickets.duplicate_scan' AND detail LIKE ?", ['#' . $g['id'] . ' %']);
            } elseif ($g['status'] === 'void') audit('gentickets.invalid_scan', '#' . $g['id'] . ' scanned while void');
            admin_view('checkin_general', ['title' => 'Check-in', 'g' => $g, 'token' => $a['token'], 'dups' => $dups]);
            return;
        }
    }
    if ($r && $r['status'] === 'confirmed' && $r['checked_in_at'] && strtotime($r['checked_in_at']) < time() - 20) {   // the redirect right after a check-in is not a duplicate
        audit('ticket.duplicate_scan', $r['reference'] . ' scanned again; first entry ' . $r['checked_in_at']);
        $dups = (int)val("SELECT COUNT(*) FROM audit_log WHERE action = 'ticket.duplicate_scan' AND detail LIKE ?", [$r['reference'] . ' %']);
    } elseif ($r && $r['status'] !== 'confirmed') {
        audit('ticket.invalid_scan', $r['reference'] . ' scanned while ' . ($r['withdrawn_at'] ? 'withdrawn' : $r['status']));
    }
    admin_view('checkin', ['title' => 'Check-in', 'r' => $r, 'token' => $a['token'], 'dups' => $dups]);
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
    $dest = (str_starts_with($back, 'admin/reservations') || preg_match('#^admin/checkin(/[a-f0-9]{24}|\?[A-Za-z0-9=&_%.+-]*)?$#', $back) || preg_match('#^admin/tickets(\?[A-Za-z0-9=&_%.+-]*)?$#', $back)) ? $back
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
    $creds = $_SESSION['new_creds'] ?? [];
    unset($_SESSION['new_creds']);                                  // login details are shown once
    admin_view('users', ['title' => 'Users & permissions', 'users' => rows('SELECT * FROM users ORDER BY id'), 'creds' => $creds]);
}
/** Creates a staff account and remembers its login details for the one-time "Login details" panel. */
function create_staff_user(string $name, string $email, string $role, ?string $password, bool $mustChange, bool $emailIt): void
{
    $pw = $password !== null && $password !== '' ? $password : generate_password();
    q('INSERT INTO users (name, email, password_hash, role, must_change) VALUES (?,?,?,?,?)', [$name, $email, password_hash($pw, PASSWORD_DEFAULT), $role, $mustChange ? 1 : 0]);
    audit('user.create', "$email ($role)");
    $login = abs_url('admin/login');
    $_SESSION['new_creds'][] = ['name' => $name, 'email' => $email, 'role' => ROLES[$role], 'password' => $pw, 'login' => $login, 'must_change' => $mustChange, 'emailed' => false];
    if ($emailIt) {
        $sent = send_mail($email, 'Your Chill & Bite admin login', "Hello $name,\n\nAn account was created for you as: " . ROLES[$role] . ".\n\nSign in: $login\nEmail: $email\nTemporary password: $pw\n" .
            ($mustChange ? "You will be asked to choose your own password the first time you sign in.\n" : '') . "\nChill & Bite MEATINGS\n");
        $_SESSION['new_creds'][array_key_last($_SESSION['new_creds'])]['emailed'] = $sent;
    }
}
function admin_users_save(): void
{
    require_can('users');
    $me = current_user();
    $act = $_POST['action'] ?? '';
    if ($act === 'create') {
        $email = strtolower(post_str('email', 190)); $pw = trim((string)($_POST['password'] ?? ''));
        $role = $_POST['role'] ?? '';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !isset(ROLES[$role]) || mb_strlen(post_str('name')) < 2) flash('Name, valid email and role are required.', 'err');
        elseif ($pw !== '' && strlen($pw) < 10) flash('A password you type must be at least 10 characters. Leave it empty to generate one.', 'err');
        elseif (val('SELECT id FROM users WHERE email = ?', [$email])) flash('That email is already used.', 'err');
        else {
            create_staff_user(post_str('name', 120), $email, $role, $pw, !empty($_POST['must_change']), !empty($_POST['email_login']));
            flash('Account created. Copy the login details below and share them securely: the password is shown only once.');
        }
    } elseif ($act === 'bulk') {
        $made = 0; $skipped = [];
        $byLabel = array_change_key_case(array_flip(ROLES), CASE_LOWER);
        foreach (preg_split('/\R/', (string)($_POST['lines'] ?? '')) as $line) {
            $line = trim($line);
            if ($line === '') continue;
            $p = array_map('trim', preg_split('/[,;\t]/', $line));
            $name = $p[0] ?? ''; $email = strtolower($p[1] ?? ''); $r = strtolower($p[2] ?? '');
            $role = isset(ROLES[$r]) ? $r : ($byLabel[$r] ?? ($byLabel[str_replace('_', ' ', $r)] ?? ''));
            if (mb_strlen($name) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL) || !isset(ROLES[$role])) { $skipped[] = "$line (needs: Name, email, role)"; continue; }
            if (val('SELECT id FROM users WHERE email = ?', [$email])) { $skipped[] = "$email (already exists)"; continue; }
            create_staff_user(mb_substr($name, 0, 120), $email, $role, null, true, !empty($_POST['email_login']));
            $made++;
        }
        if ($made) flash("$made account(s) created. Copy the login details below: passwords are shown only once.");
        foreach ($skipped as $x) flash("Skipped: $x", 'err');
        if (!$made && !$skipped) flash('Type one person per line: Name, email, role.', 'err');
    } else {
        $id = (int)($_POST['id'] ?? 0);
        if ($act === 'toggle' && $id !== (int)$me['id']) { q('UPDATE users SET active = 1 - active WHERE id = ?', [$id]); audit('user.toggle', "#$id"); flash('User updated.'); }
        elseif ($act === 'role' && $id !== (int)$me['id'] && isset(ROLES[$_POST['role'] ?? ''])) { q('UPDATE users SET role = ? WHERE id = ?', [$_POST['role'], $id]); audit('user.role', "#$id -> {$_POST['role']}"); flash('Role updated.'); }
        elseif ($act === 'password') {
            $pw = (string)($_POST['password'] ?? '');
            if (strlen($pw) < 10) flash('Password must be at least 10 characters.', 'err');
            else { q('UPDATE users SET password_hash = ?, must_change = ? WHERE id = ?', [password_hash($pw, PASSWORD_DEFAULT), $id === (int)$me['id'] ? 0 : 1, $id]); audit('user.password', "#$id"); flash('Password changed. The person will be asked to choose their own at next sign-in.'); }
        } elseif ($act === 'reset') {
            $u = row('SELECT * FROM users WHERE id = ?', [$id]);
            if (!$u || $id === (int)$me['id']) flash('Use the My account page to change your own password.', 'err');
            else {
                $pw = generate_password();
                q('UPDATE users SET password_hash = ?, must_change = 1 WHERE id = ?', [password_hash($pw, PASSWORD_DEFAULT), $id]);
                audit('user.reset', "#$id {$u['email']}");
                $_SESSION['new_creds'][] = ['name' => $u['name'], 'email' => $u['email'], 'role' => ROLES[$u['role']] ?? $u['role'], 'password' => $pw, 'login' => abs_url('admin/login'), 'must_change' => true, 'emailed' => false];
                if (!empty($_POST['email_login'])) {
                    $sent = send_mail($u['email'], 'Your Chill & Bite admin password was reset', "Hello {$u['name']},\n\nYour password was reset.\n\nSign in: " . abs_url('admin/login') . "\nTemporary password: $pw\nYou will be asked to choose your own password.\n");
                    $_SESSION['new_creds'][array_key_last($_SESSION['new_creds'])]['emailed'] = $sent;
                }
                flash('Password reset. Copy the new temporary password below: it is shown only once.');
            }
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

/* ================= Delete rejected and cancelled records (administrator only) ================= */
/** Why a booking cannot be deleted, or null when it can. Money records are never deleted from under the finance reports. */
function res_delete_block(array $r): ?string
{
    if (!in_array($r['status'], ['rejected', 'cancelled'], true)) return 'Only rejected or cancelled records can be deleted.';
    if ($r['status'] === 'cancelled' && !empty($r['withdrawn_at'])) return 'Withdrawn tickets are kept so the gate can still recognise them.';
    if ((int)val('SELECT COUNT(*) FROM payments WHERE reservation_id = ?', [$r['id']]) > 0) return 'It has payment records, and deleting it would change the finance reports. Keep it (a refund can be recorded on it).';
    try { if ((int)val("SELECT COUNT(*) FROM pay_attempts WHERE reservation_id = ? AND status IN ('success','unconfirmed','pending')", [$r['id']]) > 0) return 'It has an online payment that still needs checking.'; }
    catch (PDOException $e) { /* no online payments table yet */ }
    return null;
}
/** SQL that selects deletable rejected/cancelled bookings (without payments), optionally for one event. */
function res_purge_sql(string $which, int $eventId): array
{
    $st = $which === 'rejected' ? "('rejected')" : ($which === 'cancelled' ? "('cancelled')" : "('rejected','cancelled')");
    $w = "r.status IN $st AND NOT (r.status = 'cancelled' AND r.withdrawn_at IS NOT NULL) AND NOT EXISTS (SELECT 1 FROM payments p WHERE p.reservation_id = r.id)"; $a = [];
    if ($eventId) { $w .= ' AND r.event_id = ?'; $a[] = $eventId; }
    return [$w, $a];
}
/** How many rejected / cancelled bookings there are, and how many of them can be deleted. */
function res_purge_counts(int $eventId = 0): array
{
    $out = [];
    foreach (['rejected', 'cancelled'] as $s) {
        $all = (int)val('SELECT COUNT(*) FROM reservations r WHERE r.status = ? AND NOT (r.status = \'cancelled\' AND r.withdrawn_at IS NOT NULL)' . ($eventId ? ' AND r.event_id = ?' : ''), $eventId ? [$s, $eventId] : [$s]);
        [$w, $a] = res_purge_sql($s, $eventId);
        $del = (int)val("SELECT COUNT(*) FROM reservations r WHERE $w", $a);
        try {
            $att = (int)val("SELECT COUNT(*) FROM reservations r WHERE $w AND EXISTS (SELECT 1 FROM pay_attempts x WHERE x.reservation_id = r.id AND x.status IN ('success','unconfirmed','pending'))", $a);
            $del -= $att;
        } catch (PDOException $e) {}
        $out[$s] = ['all' => $all, 'deletable' => max(0, $del)];
    }
    return $out;
}
/** Save a copy of bookings (and their payments) about to be force-deleted. Returns the file name, or null when it could not be written. */
function res_safety_copy(array $res, string $which, int $eventId = 0): ?string
{
    if (!$res) return null;
    $ids = array_map('intval', array_column($res, 'id'));
    $pay = rows('SELECT * FROM payments WHERE reservation_id IN (' . implode(',', $ids) . ')');
    $dir = ROOT . '/storage/purged'; @mkdir($dir, 0775, true);
    $file = $dir . '/purge-' . date('Ymd-His') . '-' . substr(bin2hex(random_bytes(2)), 0, 4) . '.json';
    $ok = @file_put_contents($file, json_encode(['deleted_at' => date('c'), 'by' => current_user()['email'] ?? '', 'which' => $which, 'event' => $eventId, 'reservations' => $res, 'payments' => $pay], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_PARTIAL_OUTPUT_ON_ERROR));
    return $ok === false ? null : basename($file);
}
function admin_reservation_delete(array $a): void
{
    require_can('purge');
    $r = row('SELECT * FROM reservations WHERE id = ?', [(int)$a['id']]) ?? not_found();
    $force = !empty($_POST['force']);
    $why = $force ? (in_array($r['status'], ['rejected', 'cancelled'], true) ? null : 'Only rejected or cancelled records can be deleted.') : res_delete_block($r);
    $back = (string)($_POST['back'] ?? '');
    $dest = preg_match('#^admin/reservations(\?[A-Za-z0-9=&_%.+-]*)?$#', $back) ? $back : 'admin/reservations';
    if ($why) { flash('Not deleted: ' . $why, 'err'); redirect($dest); }
    if ($force) {                                              // forced: payments and withdrawn tickets go too, after a safety copy
        $copy = res_safety_copy([$r], 'single');
        if ($copy === null) { flash('Could not save the safety copy in storage/purged, so nothing was deleted.', 'err'); redirect($dest); }
        $np = (int)val('SELECT COUNT(*) FROM payments WHERE reservation_id = ?', [$r['id']]);
        q('DELETE FROM reservations WHERE id = ?', [$r['id']]);
        audit('reservation.force_delete', "{$r['reference']} {$r['full_name']} ({$r['status']}) force-deleted incl. $np payment record(s); copy: $copy");
        flash("Record {$r['reference']} for {$r['full_name']} was force deleted" . ($np ? ", with $np payment record" . ($np === 1 ? '' : 's') : '') . '. A safety copy was saved as storage/purged/' . $copy . '.');
        redirect($dest);
    }
    q('DELETE FROM reservations WHERE id = ?', [$r['id']]);
    audit('reservation.delete', "{$r['reference']} {$r['full_name']} ({$r['status']}) deleted");
    flash("Record {$r['reference']} for {$r['full_name']} was deleted. The dashboard and reports no longer count it.");
    redirect($dest);
}
function admin_reservations_purge(): void
{
    require_can('purge');
    $which = in_array($_POST['which'] ?? '', ['rejected', 'cancelled', 'both'], true) ? $_POST['which'] : 'rejected';
    $eventId = (int)($_POST['event'] ?? 0);
    $dest = 'admin/reservations' . ($eventId ? '?event=' . $eventId : '');
    if (strtoupper(trim((string)($_POST['confirm'] ?? ''))) !== 'DELETE') { flash('Type DELETE in the box to confirm. Nothing was deleted.', 'err'); redirect($dest); }
    [$w, $args] = res_purge_sql($which, $eventId);
    $list = rows("SELECT r.id, r.reference FROM reservations r WHERE $w", $args);
    $skipped = 0;
    $ids = []; $refs = [];
    foreach ($list as $x) {
        try { if ((int)val("SELECT COUNT(*) FROM pay_attempts WHERE reservation_id = ? AND status IN ('success','unconfirmed','pending')", [$x['id']]) > 0) { $skipped++; continue; } }
        catch (PDOException $e) {}
        $ids[] = (int)$x['id']; $refs[] = $x['reference'];
    }
    foreach (array_chunk($ids, 200) as $chunk) q('DELETE FROM reservations WHERE id IN (' . implode(',', array_fill(0, count($chunk), '?')) . ')', $chunk);
    audit('reservation.purge', count($ids) . " $which record(s) deleted" . ($eventId ? " for event #$eventId" : ' for all events') . '; refs: ' . implode(' ', array_slice($refs, 0, 12)));
    flash(count($ids) ? 'Deleted ' . count($ids) . ' ' . ($which === 'both' ? 'rejected and cancelled' : $which) . ' record' . (count($ids) === 1 ? '' : 's') . '. The dashboard and reports no longer count them.' . ($skipped ? " $skipped " . ($skipped === 1 ? 'booking' : 'bookings') . ' with an online payment to check ' . ($skipped === 1 ? 'was' : 'were') . ' kept.' : '')
        : 'Nothing to delete. Records with payments, and withdrawn tickets, are always kept.', count($ids) ? 'ok' : 'err');
    redirect($dest);
}
/** What a forced delete would remove: every rejected/cancelled booking (also withdrawn ones and ones with payments), and the payment records on them. */
function res_force_counts(int $eventId = 0): array
{
    $w = "r.status IN ('rejected','cancelled')" . ($eventId ? ' AND r.event_id = ?' : ''); $a = $eventId ? [$eventId] : [];
    $n = (int)val("SELECT COUNT(*) FROM reservations r WHERE $w", $a);
    $p = row("SELECT COUNT(*) n, COALESCE(SUM(p.amount),0) net, COALESCE(SUM(CASE WHEN p.amount > 0 THEN p.amount ELSE 0 END),0) gross FROM payments p JOIN reservations r ON r.id = p.reservation_id WHERE $w", $a) ?: ['n' => 0, 'net' => 0, 'gross' => 0];
    $withdrawn = (int)val("SELECT COUNT(*) FROM reservations r WHERE r.status = 'cancelled' AND r.withdrawn_at IS NOT NULL" . ($eventId ? ' AND r.event_id = ?' : ''), $a);
    return ['bookings' => $n, 'payments' => (int)$p['n'], 'net' => (int)$p['net'], 'gross' => (int)$p['gross'], 'withdrawn' => $withdrawn];
}
/**
 * Forced delete: removes every rejected and cancelled booking no matter what is attached to it (payment records, withdrawn tickets, online
 * payments being checked). A copy of everything removed is first saved to storage/purged/ so it can be restored from if ever needed.
 */
function admin_reservations_force_purge(): void
{
    require_can('purge');
    $which = in_array($_POST['which'] ?? '', ['rejected', 'cancelled', 'both'], true) ? $_POST['which'] : 'both';
    $eventId = (int)($_POST['event'] ?? 0);
    $dest = 'admin/reservations' . ($eventId ? '?event=' . $eventId : '');
    if (strtoupper(trim((string)($_POST['confirm'] ?? ''))) !== 'FORCE DELETE') { flash('Type FORCE DELETE in the box to confirm. Nothing was deleted.', 'err'); redirect($dest); }
    $st = $which === 'both' ? ['rejected', 'cancelled'] : [$which];
    $w = 'r.status IN (' . implode(',', array_fill(0, count($st), '?')) . ')'; $args = $st;
    if ($eventId) { $w .= ' AND r.event_id = ?'; $args[] = $eventId; }
    $res = rows("SELECT r.* FROM reservations r WHERE $w", $args);
    if (!$res) { flash('Nothing to delete: there are no ' . ($which === 'both' ? 'rejected or cancelled' : $which) . ' bookings.', 'err'); redirect($dest); }
    $ids = array_map('intval', array_column($res, 'id'));
    $in = implode(',', $ids);
    $pay = rows("SELECT * FROM payments WHERE reservation_id IN ($in)");
    $net = (int)array_sum(array_column($pay, 'amount'));
    $copy = res_safety_copy($res, $which, $eventId);                       // safety copy first
    if ($copy === null) { flash('Could not save the safety copy in storage/purged, so nothing was deleted.', 'err'); redirect($dest); }
    $file = $copy;
    foreach (array_chunk($ids, 200) as $chunk) q('DELETE FROM reservations WHERE id IN (' . implode(',', array_fill(0, count($chunk), '?')) . ')', $chunk);
    audit('reservation.force_purge', count($ids) . " $which record(s) force-deleted incl. " . count($pay) . " payment record(s) (net UGX $net)" . ($eventId ? " for event #$eventId" : ' for all events') . '; copy: ' . basename($file));
    flash('Force deleted ' . count($ids) . ' ' . ($which === 'both' ? 'rejected and cancelled' : $which) . ' booking' . (count($ids) === 1 ? '' : 's') . ', with ' . count($pay) . ' payment record' . (count($pay) === 1 ? '' : 's') . '. The dashboard, collections and reports no longer count them. A safety copy was saved as storage/purged/' . basename($file) . '.');
    redirect($dest);
}