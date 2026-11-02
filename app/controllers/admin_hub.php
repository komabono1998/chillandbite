<?php
declare(strict_types=1);

/* ================= Shared statistics ================= */
/** Events with booking, attendance and money totals. $where uses the alias "e". */
function event_stats(string $where = '1=1', array $params = [], string $order = 'e.start_at DESC', int $limit = 200): array
{
    return rows("SELECT e.*, c.name AS cat,
            (SELECT COALESCE(SUM(guests),0) FROM reservations r WHERE r.event_id=e.id AND r.status IN ('pending','confirmed')) AS booked,
            (SELECT COALESCE(SUM(guests),0) FROM reservations r WHERE r.event_id=e.id AND r.status='confirmed') AS confirmed,
            (SELECT COALESCE(SUM(guests),0) FROM reservations r WHERE r.event_id=e.id AND r.status='pending') AS pending,
            (SELECT COALESCE(SUM(guests),0) FROM reservations r WHERE r.event_id=e.id AND r.status='confirmed' AND r.checked_in_at IS NOT NULL) AS checked,
            (SELECT COALESCE(SUM(p.amount),0) FROM payments p JOIN reservations r ON r.id=p.reservation_id WHERE r.event_id=e.id) AS revenue,
            (SELECT COALESCE(SUM(GREATEST(r.amount_due - COALESCE((SELECT SUM(amount) FROM payments p WHERE p.reservation_id=r.id),0),0)),0)
               FROM reservations r WHERE r.event_id=e.id AND r.status IN ('pending','confirmed')) AS outstanding
        FROM events e LEFT JOIN event_categories c ON c.id = e.category_id WHERE $where ORDER BY $order LIMIT " . (int)$limit, $params);
}
function upcoming_filter(): string { return "e.status='published' AND COALESCE(e.end_at,e.start_at) >= NOW()"; }

/* ================= Dashboard ================= */
function admin_dashboard(): void
{
    require_can('dashboard');
    $u = current_user();
    $d = ['title' => 'Dashboard', 'u' => $u];
    $money = can_any(['payments', 'reports']);
    $res = can_any(['reservations', 'reports']);

    $d['events'] = (can_any(['events', 'reservations', 'reports', 'checkin'])) ? event_stats(upcoming_filter(), [], 'e.start_at ASC', 6) : [];
    $d['next'] = $d['events'][0] ?? null;

    if ($res) {
        $days = [];
        for ($i = 13; $i >= 0; $i--) $days[date('Y-m-d', strtotime("-$i day"))] = ['n' => 0, 'g' => 0];
        foreach (rows("SELECT DATE(created_at) d, COUNT(*) n, COALESCE(SUM(guests),0) g FROM reservations WHERE created_at >= (CURDATE() - INTERVAL 13 DAY) AND status <> 'rejected' GROUP BY DATE(created_at)") as $r)
            if (isset($days[$r['d']])) $days[$r['d']] = ['n' => (int)$r['n'], 'g' => (int)$r['g']];
        $d['daily'] = $days;
        $d['by_type'] = rows("SELECT type, COUNT(*) n, COALESCE(SUM(guests),0) g FROM reservations WHERE status IN ('pending','confirmed') GROUP BY type ORDER BY g DESC");
        $d['by_status'] = array_column(rows('SELECT status, COUNT(*) n FROM reservations GROUP BY status'), 'n', 'status');
        $d['pending_list'] = rows("SELECT r.*, e.title FROM reservations r JOIN events e ON e.id=r.event_id WHERE r.status='pending' ORDER BY r.id DESC LIMIT 6");
        $d['recent'] = rows('SELECT r.*, e.title FROM reservations r JOIN events e ON e.id=r.event_id ORDER BY r.id DESC LIMIT 7');
        $d['stat_pending'] = (int)val("SELECT COUNT(*) FROM reservations WHERE status='pending'");
        $d['stat_guests'] = (int)val("SELECT COALESCE(SUM(r.guests),0) FROM reservations r JOIN events e ON e.id=r.event_id WHERE r.status='confirmed' AND COALESCE(e.end_at,e.start_at) >= NOW()");
        $d['stat_today'] = (int)val("SELECT COUNT(*) FROM reservations WHERE DATE(created_at) = CURDATE()");
    }
    if ($money) {
        $d['revenue'] = (int)val('SELECT COALESCE(SUM(amount),0) FROM payments');
        $d['outstanding'] = (int)val("SELECT COALESCE(SUM(GREATEST(r.amount_due - COALESCE((SELECT SUM(amount) FROM payments p WHERE p.reservation_id=r.id),0),0)),0) FROM reservations r WHERE r.status IN ('pending','confirmed')");
        $d['rev_events'] = event_stats('1=1', [], 'revenue DESC', 6);
    }
    if (can('inquiries')) {
        $scope = inquiry_scope($u['role']);
        $in = implode(',', array_fill(0, max(1, count($scope)), '?'));
        $d['stat_inquiries'] = (int)val("SELECT COUNT(*) FROM inquiries WHERE status='new' AND type IN ($in)", $scope ?: ['']);
        $d['inq_list'] = rows("SELECT * FROM inquiries WHERE status='new' AND type IN ($in) ORDER BY id DESC LIMIT 5", $scope ?: ['']);
    }
    if (can('accreditation')) $d['stat_accred'] = (int)val("SELECT COUNT(*) FROM accreditation_applications WHERE status='pending'");
    if (can('news') && $u['role'] !== 'contributor') $d['stat_review'] = (int)val("SELECT COUNT(*) FROM articles WHERE status='pending'");
    if ($u['role'] === 'contributor') $d['stat_mine'] = (int)val("SELECT COUNT(*) FROM articles WHERE author_id = ? AND status='published'", [$u['id']]);
    if (can('advertising')) {
        $d['stat_partners'] = (int)val("SELECT COUNT(*) FROM partner_applications WHERE status='pending'");
        $d['partner_list'] = rows("SELECT id, company, partnership_type, created_at FROM partner_applications WHERE status='pending' ORDER BY id DESC LIMIT 5");
        $d['stat_sponsors'] = (int)val('SELECT COUNT(*) FROM sponsors WHERE active = 1');
    }
    if (can_any(['news', 'pages'])) {
        $d['stat_articles'] = (int)val("SELECT COUNT(*) FROM articles WHERE status IN ('published','scheduled') AND published_at <= NOW()");
        $d['stat_drafts'] = (int)val("SELECT COUNT(*) FROM articles WHERE status = 'draft'");
    }
    if (can('audit')) $d['activity'] = rows('SELECT l.*, u.name FROM audit_log l LEFT JOIN users u ON u.id = l.user_id ORDER BY l.id DESC LIMIT 8');
    admin_view('dashboard', $d);
}

/* ================= Reports ================= */
function admin_reports(): void
{
    require_can('reports');
    [$w, $p] = reservation_filters();
    [$from, $to] = report_range();
    if ($from) { $w .= ' AND DATE(r.created_at) >= ?'; $p[] = $from; }
    if ($to) { $w .= ' AND DATE(r.created_at) <= ?'; $p[] = $to; }
    $sub = RES_SELECT . " WHERE $w";
    $sum = row("SELECT COUNT(*) bookings, COALESCE(SUM(guests),0) guests,
            COALESCE(SUM(status='confirmed'),0) n_confirmed, COALESCE(SUM(status='pending'),0) n_pending,
            COALESCE(SUM(status IN ('cancelled','rejected')),0) n_lost,
            COALESCE(SUM(CASE WHEN status IN ('pending','confirmed') THEN amount_due END),0) due,
            COALESCE(SUM(CASE WHEN status IN ('pending','confirmed') THEN paid END),0) paid,
            COALESCE(SUM(CASE WHEN status='confirmed' AND checked_in_at IS NOT NULL THEN guests END),0) checked
        FROM ($sub) x", $p) ?: [];
    $pw = '1=1'; $pp = [];
    if (!empty($_GET['event'])) { $pw .= ' AND r.event_id = ?'; $pp[] = (int)$_GET['event']; }
    if ($from) { $pw .= ' AND DATE(p.created_at) >= ?'; $pp[] = $from; }
    if ($to) { $pw .= ' AND DATE(p.created_at) <= ?'; $pp[] = $to; }
    $methods = rows("SELECT p.method, COUNT(*) n, COALESCE(SUM(p.amount),0) amt FROM payments p JOIN reservations r ON r.id=p.reservation_id WHERE $pw GROUP BY p.method ORDER BY amt DESC", $pp);
    $revenue = (int)array_sum(array_column($methods, 'amt'));
    $eventFilter = !empty($_GET['event']) ? 'e.id = ?' : '1=1';
    admin_view('reports', [
        'title' => 'Reports', 'sum' => $sum, 'revenue' => $revenue, 'methods' => $methods,
        'by_type' => rows("SELECT type, COUNT(*) n, COALESCE(SUM(guests),0) g, COALESCE(SUM(amount_due),0) due, COALESCE(SUM(paid),0) paid FROM ($sub) x GROUP BY type ORDER BY g DESC", $p),
        'daily' => rows("SELECT DATE(created_at) d, COUNT(*) n, COALESCE(SUM(guests),0) g FROM ($sub) x GROUP BY DATE(created_at) ORDER BY d DESC LIMIT 30", $p),
        'events_stats' => event_stats($eventFilter, !empty($_GET['event']) ? [(int)$_GET['event']] : [], 'e.start_at DESC', 25),
        'preview' => rows($sub . ' ORDER BY r.id DESC LIMIT 60', $p),
        'all_events' => rows('SELECT id, title, start_at FROM events ORDER BY start_at DESC LIMIT 100'),
        'filters' => array_filter(['event' => $_GET['event'] ?? '', 'status' => $_GET['status'] ?? '', 'from' => $from, 'to' => $to]),
    ]);
}

/* ================= Exports ================= */
function admin_export(array $a): void
{
    $meta = dataset_meta()[$a['dataset']] ?? not_found();
    require_can_any($meta['perm']);
    $ds = export_dataset($a['dataset']) ?? not_found();
    [$title, $subtitle, $headers, $rows] = $ds;
    $format = in_array($_GET['format'] ?? '', ['pdf', 'docx', 'xlsx', 'csv'], true) ? $_GET['format'] : 'xlsx';
    audit('export.' . $a['dataset'], strtoupper($format) . ', ' . count($rows) . ' rows');
    export_send($format, $title, $subtitle, $headers, $rows, $a['dataset']);
}

/* ================= Door check-in desk ================= */
function admin_checkin_desk(): void
{
    require_can('checkin');
    $events = rows("SELECT id, title, start_at FROM events WHERE status IN ('published','archived') ORDER BY start_at DESC LIMIT 40");
    $eid = (int)($_GET['event'] ?? 0);
    if (!$eid) $eid = (int)(val("SELECT id FROM events WHERE status='published' AND COALESCE(end_at,start_at) >= NOW() ORDER BY start_at LIMIT 1") ?: ($events[0]['id'] ?? 0));
    $ev = $eid ? row('SELECT * FROM events WHERE id = ?', [$eid]) : null;
    $q = trim((string)($_GET['q'] ?? ''));
    $list = []; $stats = ['expected' => 0, 'in' => 0, 'bookings' => 0, 'pending' => 0];
    if ($ev) {
        $w = "r.event_id = ? AND r.status = 'confirmed'"; $p = [$eid];
        if ($q !== '') { $w .= ' AND (r.full_name LIKE ? OR r.phone LIKE ? OR r.reference LIKE ?)'; $like = '%' . addcslashes($q, '%_\\') . '%'; array_push($p, $like, $like, $like); }
        $list = rows(RES_SELECT . " WHERE $w ORDER BY (r.checked_in_at IS NOT NULL), r.full_name LIMIT 300", $p);
        $s = row("SELECT COALESCE(SUM(guests),0) expected, COALESCE(SUM(CASE WHEN checked_in_at IS NOT NULL THEN guests END),0) in_guests, COUNT(*) bookings FROM reservations WHERE event_id = ? AND status='confirmed'", [$eid]);
        $stats = ['expected' => (int)$s['expected'], 'in' => (int)$s['in_guests'], 'bookings' => (int)$s['bookings'],
            'pending' => (int)val("SELECT COUNT(*) FROM reservations WHERE event_id = ? AND status='pending'", [$eid])];
    }
    admin_view('checkin_desk', ['title' => 'Check-in desk', 'events' => $events, 'ev' => $ev, 'q' => $q, 'list' => $list, 'stats' => $stats]);
}

/* ================= Website pages manager ================= */
function admin_pages(): void
{
    require_can('pages');
    $mine = [];
    foreach (site_pages() as $id => $pg) if (can_edit_page($id)) $mine[$id] = $pg;
    $counts = [];
    foreach (content_overrides() as $k => $_) { $pid = explode('.', $k)[0]; $counts[$pid] = ($counts[$pid] ?? 0) + 1; }
    admin_view('pages', ['title' => 'Website pages', 'pages' => $mine, 'counts' => $counts]);
}
function admin_page_edit(array $a): void
{
    require_can('pages');
    $pg = site_pages()[$a['page']] ?? not_found();
    if (!can_edit_page($a['page'])) { http_response_code(403); render('admin/forbidden', [], 'admin'); return; }
    admin_view('page_edit', ['title' => 'Edit: ' . $pg['label'], 'id' => $a['page'], 'pg' => $pg, 'saved' => content_overrides()]);
}
function admin_page_save(array $a): void
{
    require_can('pages');
    $pid = $a['page'];
    $pg = site_pages()[$pid] ?? not_found();
    if (!can_edit_page($pid)) { http_response_code(403); render('admin/forbidden', [], 'admin'); return; }
    $uid = (int)current_user()['id'];
    $changed = 0; $errors = [];
    foreach ($pg['sections'] as $sid => $sec) {
        foreach ($sec['fields'] as $fd) {
            $key = "$pid.$sid.{$fd['key']}";
            $input = 'f_' . str_replace('.', '_', $key);
            if (!empty($_POST['reset'][$key])) { q('DELETE FROM site_content WHERE `key` = ?', [$key]); $changed++; continue; }
            if ($fd['type'] === 'image') {
                try { $up = save_upload($input, 'site'); }
                catch (RuntimeException $e) { $errors[] = $fd['label'] . ': ' . $e->getMessage(); continue; }
                if ($up) { q('INSERT INTO site_content (`key`, `value`, updated_by) VALUES (?,?,?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), updated_by = VALUES(updated_by)', [$key, $up, $uid]); $changed++; }
                continue;
            }
            $val = str_replace("\r\n", "\n", trim((string)($_POST[$input] ?? '')));
            $val = mb_substr($val, 0, $fd['type'] === 'text' ? 500 : 6000);
            $had = array_key_exists($key, content_overrides());
            if ($val === '' || $val === $fd['default']) {            // back to default: keep the table clean
                if ($had) { q('DELETE FROM site_content WHERE `key` = ?', [$key]); $changed++; }
            } elseif (!$had || content_overrides()[$key] !== $val) {
                q('INSERT INTO site_content (`key`, `value`, updated_by) VALUES (?,?,?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), updated_by = VALUES(updated_by)', [$key, $val, $uid]);
                $changed++;
            }
        }
    }
    audit('page.update', "$pid: $changed field(s)");
    foreach ($errors as $e) flash($e, 'err');
    flash($changed ? "Saved. {$pg['label']} is updated on the website." : 'No changes to save.');
    redirect('admin/pages/' . $pid);
}
