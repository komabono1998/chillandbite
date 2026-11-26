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
        [$pc, $pa] = package_condition($pkg = trim((string)($_GET['package'] ?? ''))); $w .= $pc; array_push($p, ...$pa);
        $list = rows(RES_SELECT . " WHERE $w ORDER BY (r.checked_in_at IS NOT NULL), r.full_name LIMIT 300", $p);
        $s = row("SELECT COALESCE(SUM(guests),0) expected, COALESCE(SUM(CASE WHEN checked_in_at IS NOT NULL THEN guests END),0) in_guests, COUNT(*) bookings FROM reservations WHERE event_id = ? AND status='confirmed'", [$eid]);
        $stats = ['expected' => (int)$s['expected'], 'in' => (int)$s['in_guests'], 'bookings' => (int)$s['bookings'],
            'pending' => (int)val("SELECT COUNT(*) FROM reservations WHERE event_id = ? AND status='pending'", [$eid])];
    }
    admin_view('checkin_desk', ['title' => 'Check-in desk', 'events' => $events, 'ev' => $ev, 'q' => $q, 'pkg' => $pkg ?? '', 'package_opts' => package_options($eid), 'list' => $list, 'stats' => $stats]);
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
