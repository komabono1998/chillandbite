<?php
declare(strict_types=1);

/* ================= Dashboard: Overview, Collections by event, Activity trail, Visitors ================= */

const DASH_CHANNELS = ['daraza_mtn' => 'MTN MoMo (online)', 'daraza_airtel' => 'Airtel Money (online)', 'daraza_refund' => 'Refund (online)', 'cash' => 'Cash', 'mobile_money' => 'Mobile money (recorded)', 'bank' => 'Bank', 'pesapal' => 'Pesapal', 'refund' => 'Refund'];
function dash_channel(?string $c): string { return DASH_CHANNELS[(string)$c] ?? ucfirst(str_replace('_', ' ', (string)$c)); }

function dash_tabs(): array
{
    $t = ['overview' => ['Overview', '🏠']];
    if (can_any(['payments', 'reports', 'collections', 'reservations'])) $t['events'] = ['Collections by event', '💰'];
    if (can_any(['reservations', 'reports', 'collections'])) $t['trail'] = ['Activity trail', '🧾'];
    if (can_any(['payments', 'collections', 'reservations'])) $t['ledger'] = ['Payments ledger', '📒'];
    if (can('analytics')) $t['visitors'] = ['Visitors', '📈'];
    if (can('withdraw')) $t['withdraw'] = ['Withdraw money', '📲'];
    return $t;
}

/** Totals per event: payments by route, bookings by status, online payment attempts, and the money per package. */
function dash_event_totals(int $only = 0): array
{
    pay_ensure_schema();
    $ev = rows('SELECT e.id, e.title, e.start_at, e.venue, e.location, e.status, e.capacity FROM events e' . ($only ? ' WHERE e.id = ?' : '') . ' ORDER BY e.start_at DESC LIMIT 40', $only ? [$only] : []);
    $out = [];
    foreach ($ev as $e) $out[(int)$e['id']] = $e + ['paid' => 0, 'refunded' => 0, 'by_ch' => [], 'res' => [], 'guests' => 0, 'due' => 0, 'att' => ['success' => 0, 'failed' => 0, 'pending' => 0, 'unconfirmed' => 0], 'pkg' => []];
    $w = $only ? ' WHERE r.event_id = ' . (int)$only : '';
    foreach (rows("SELECT COALESCE(r.event_id, p.event_id) event_id, COALESCE(p.channel, p.method) ch, COUNT(*) n, SUM(p.amount) amt FROM payments p LEFT JOIN reservations r ON r.id = p.reservation_id WHERE COALESCE(r.event_id, p.event_id) IS NOT NULL" . ($only ? ' AND COALESCE(r.event_id, p.event_id) = ' . (int)$only : '') . " GROUP BY event_id, ch") as $x)
        if (isset($out[$x['event_id']])) { $o = &$out[$x['event_id']]; $o['by_ch'][$x['ch']] = ['n' => (int)$x['n'], 'amt' => (int)$x['amt']]; if ($x['amt'] >= 0) $o['paid'] += (int)$x['amt']; else $o['refunded'] += -(int)$x['amt']; unset($o); }
    foreach (rows("SELECT event_id, status, COUNT(*) n, COALESCE(SUM(guests),0) g, COALESCE(SUM(amount_due),0) due FROM reservations r$w GROUP BY event_id, status") as $x)
        if (isset($out[$x['event_id']])) { $o = &$out[$x['event_id']]; $o['res'][$x['status']] = ['n' => (int)$x['n'], 'g' => (int)$x['g']]; if (in_array($x['status'], ['pending', 'confirmed'], true)) { $o['guests'] += (int)$x['g']; $o['due'] += (int)$x['due']; } unset($o); }
    try {
        foreach (rows("SELECT r.event_id, a.status, COUNT(*) n FROM pay_attempts a JOIN reservations r ON r.id = a.reservation_id$w" . ($w ? ' AND' : ' WHERE') . " a.direction = 'collect' GROUP BY r.event_id, a.status") as $x)
            if (isset($out[$x['event_id']])) $out[$x['event_id']]['att'][$x['status']] = (int)$x['n'];
    } catch (PDOException $e) { /* attempts table not created yet */ }
    foreach (rows("SELECT r.event_id, COALESCE(NULLIF(r.package, ''), 'No package') pkg, COUNT(*) n, COALESCE(SUM(r.guests),0) g, COALESCE(SUM(r.amount_due),0) due, COALESCE(SUM((SELECT SUM(p.amount) FROM payments p WHERE p.reservation_id = r.id)),0) paid
                   FROM reservations r WHERE r.status IN ('pending','confirmed')" . ($only ? ' AND r.event_id = ' . (int)$only : '') . ' GROUP BY r.event_id, pkg ORDER BY due DESC') as $x)
        if (isset($out[$x['event_id']])) $out[$x['event_id']]['pkg'][] = $x;
    foreach ($out as &$o) { $o['net'] = $o['paid'] - $o['refunded']; $o['outstanding'] = max(0, $o['due'] - $o['net']); } unset($o);
    return array_values($out);
}
function dash_event_payments(int $eventId, int $limit = 300): array
{
    return rows('SELECT p.*, COALESCE(p.channel, p.method) ch, r.reference, COALESCE(r.full_name, p.payer_name) full_name, COALESCE(r.phone, p.payer_phone) phone, r.package, u.name by_name FROM payments p LEFT JOIN reservations r ON r.id = p.reservation_id LEFT JOIN users u ON u.id = p.recorded_by WHERE COALESCE(r.event_id, p.event_id) = ? ORDER BY p.created_at DESC, p.id DESC LIMIT ' . (int)$limit, [$eventId]);
}
function dash_event_attempts(int $eventId, int $limit = 200): array
{
    try { return rows('SELECT a.*, r.reference, r.full_name FROM pay_attempts a JOIN reservations r ON r.id = a.reservation_id WHERE r.event_id = ? ORDER BY a.id DESC LIMIT ' . (int)$limit, [$eventId]); }
    catch (PDOException $e) { return []; }
}
function dash_export_event(int $eventId): never
{
    $e = row('SELECT * FROM events WHERE id = ?', [$eventId]) ?? not_found();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="collections-' . preg_replace('/[^a-z0-9]+/i', '-', $e['title']) . '.csv"');
    $o = fopen('php://output', 'w'); fwrite($o, "\xEF\xBB\xBF");
    fputcsv($o, ['Date and time', 'Reference', 'Guest', 'Phone', 'Package', 'Amount (UGX)', 'Route', 'Recorded by', 'Note']);
    foreach (dash_event_payments($eventId, 5000) as $p) fputcsv($o, [$p['created_at'], $p['reference'], $p['full_name'], $p['phone'], $p['package'], $p['amount'], dash_channel($p['ch']), $p['by_name'] ?: ($p['channel'] ? 'Online (automatic)' : ''), $p['note']]);
    fputcsv($o, []); fputcsv($o, ['Payment attempts (every try, with start and finish time)']);
    fputcsv($o, ['Started', 'Finished', 'Reference', 'Guest', 'Phone', 'Amount (UGX)', 'Direction', 'Result', 'Message']);
    foreach (dash_event_attempts($eventId, 5000) as $a) fputcsv($o, [$a['created_at'], $a['completed_at'], $a['reference'], $a['full_name'], $a['phone'], $a['amount'], $a['direction'], $a['status'], $a['message']]);
    fclose($o);
    audit('dashboard.export', 'collections event #' . $eventId);
    exit;
}

/** One time-ordered trail of every booking, payment, payment attempt and admin action on bookings and money. */
function dash_trail(array $f, int $per = 40): array
{
    pay_ensure_schema();
    $page = max(1, min(40, (int)($f['page'] ?? 1))); $need = $page * $per;
    $ev = (int)($f['event'] ?? 0); $kind = $f['kind'] ?? 'all'; $q = trim((string)($f['q'] ?? ''));
    $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($f['from'] ?? '')) ? $f['from'] : null; $to = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($f['to'] ?? '')) ? $f['to'] : null;
    $like = '%' . $q . '%';
    $rowsOut = [];
    $range = function (string $col, array &$p) use ($from, $to): string {
        $s = '';
        if ($from) { $s .= " AND $col >= ?"; $p[] = $from; }
        if ($to) { $s .= " AND $col < DATE_ADD(?, INTERVAL 1 DAY)"; $p[] = $to; }
        return $s;
    };
    if (in_array($kind, ['all', 'booking'], true)) {
        $p = []; $w = 'WHERE 1=1' . ($ev ? ' AND r.event_id = ?' : '') . $range('r.created_at', $p);
        if ($ev) array_unshift($p, $ev);
        if ($q !== '') { $w .= ' AND (r.reference LIKE ? OR r.full_name LIKE ? OR r.phone LIKE ?)'; array_push($p, $like, $like, $like); }
        foreach (rows("SELECT r.*, e.title ev_title FROM reservations r JOIN events e ON e.id = r.event_id $w ORDER BY r.created_at DESC, r.id DESC LIMIT $need", $p) as $r)
            $rowsOut[] = ['ts' => $r['created_at'], 'kind' => 'booking', 'ev' => $r['ev_title'], 'rid' => (int)$r['id'], 'ref' => $r['reference'], 'who' => $r['full_name'], 'phone' => $r['phone'], 'amount' => (int)$r['amount_due'],
                          'title' => 'Reservation made', 'detail' => trim(RES_TYPES[$r['type']] . ($r['package'] ? ' · ' . $r['package'] : '') . ' · ' . (int)$r['guests'] . ' guest(s)'), 'status' => $r['status'], 'by' => 'Guest (online)'];
    }
    if (in_array($kind, ['all', 'payment'], true)) {
        $p = []; $w = 'WHERE 1=1' . ($ev ? ' AND COALESCE(r.event_id, p.event_id) = ?' : '') . $range('p.created_at', $p);
        if ($ev) array_unshift($p, $ev);
        if ($q !== '') { $w .= ' AND (r.reference LIKE ? OR r.full_name LIKE ? OR r.phone LIKE ? OR p.note LIKE ?)'; array_push($p, $like, $like, $like, $like); }
        foreach (rows("SELECT p.*, COALESCE(p.channel, p.method) ch, r.reference, r.full_name, r.phone, r.id rid, e.title ev_title, t.title tab_title, u.name by_name
                       FROM payments p LEFT JOIN reservations r ON r.id = p.reservation_id LEFT JOIN events e ON e.id = COALESCE(r.event_id, p.event_id) LEFT JOIN payment_tabs t ON t.id = p.tab_id LEFT JOIN users u ON u.id = p.recorded_by $w ORDER BY p.created_at DESC, p.id DESC LIMIT $need", $p) as $r)
            $rowsOut[] = ['ts' => $r['created_at'], 'kind' => 'payment', 'ev' => $r['ev_title'] ?: ($r['tab_title'] ? 'Pay tab: ' . $r['tab_title'] : ''), 'rid' => (int)$r['rid'], 'ref' => $r['reference'] ?: '', 'who' => $r['full_name'] ?: (string)$r['payer_name'], 'phone' => $r['phone'] ?: (string)$r['payer_phone'], 'amount' => (int)$r['amount'],
                          'title' => $r['amount'] < 0 ? 'Refund' : 'Payment received', 'detail' => dash_channel($r['ch']) . ($r['note'] ? ' · ' . $r['note'] : ''), 'status' => $r['amount'] < 0 ? 'refund' : 'paid', 'by' => $r['by_name'] ?: ($r['channel'] ? 'Online (automatic)' : 'Staff')];
    }
    if (in_array($kind, ['all', 'attempt'], true)) {
        try {
            $p = []; $w = 'WHERE 1=1' . ($ev ? ' AND r.event_id = ?' : '') . $range('a.created_at', $p);
            if ($ev) array_unshift($p, $ev);
            if ($q !== '') { $w .= ' AND (r.reference LIKE ? OR r.full_name LIKE ? OR a.phone LIKE ? OR a.payer_name LIKE ?)'; array_push($p, $like, $like, $like, $like); }
            foreach (rows("SELECT a.*, r.reference, r.full_name, r.id rid, e.title ev_title, t.title tab_title, u.name by_name FROM pay_attempts a LEFT JOIN reservations r ON r.id = a.reservation_id LEFT JOIN events e ON e.id = r.event_id LEFT JOIN payment_tabs t ON t.id = a.tab_id LEFT JOIN users u ON u.id = a.started_by $w ORDER BY a.created_at DESC, a.id DESC LIMIT $need", $p) as $r)
                $rowsOut[] = ['ts' => $r['created_at'], 'end' => $r['completed_at'], 'kind' => 'attempt', 'ev' => $r['ev_title'] ?: ($r['tab_title'] ? 'Pay tab: ' . $r['tab_title'] : ''), 'rid' => (int)$r['rid'], 'ref' => $r['reference'] ?: '', 'who' => $r['full_name'] ?: (string)$r['payer_name'], 'phone' => $r['phone'], 'amount' => (int)$r['amount'],
                              'title' => $r['direction'] === 'refund' ? 'Refund attempt' : 'Payment attempt', 'detail' => ((int)$r['method'] === 2 ? 'Airtel' : 'MTN') . ($r['message'] ? ' · ' . $r['message'] : ''), 'status' => $r['status'], 'by' => $r['by_name'] ?: 'Guest (online)'];
        } catch (PDOException $e) { /* attempts table not created yet */ }
    }
    if (!$ev && in_array($kind, ['all', 'admin'], true)) {
        $p = []; $w = "WHERE (l.action LIKE 'reservation%' OR l.action LIKE 'ticket%' OR l.action LIKE 'pay.%' OR l.action LIKE 'paytab%' OR l.action LIKE 'withdraw%' OR l.action LIKE 'checkin%')" . $range('l.created_at', $p);
        if ($q !== '') { $w .= ' AND (l.detail LIKE ? OR l.action LIKE ?)'; array_push($p, $like, $like); }
        foreach (rows("SELECT l.*, u.name FROM audit_log l LEFT JOIN users u ON u.id = l.user_id $w ORDER BY l.id DESC LIMIT $need", $p) as $r)
            $rowsOut[] = ['ts' => $r['created_at'], 'kind' => 'admin', 'ev' => '', 'rid' => 0, 'ref' => '', 'who' => $r['name'] ?: 'System', 'phone' => '', 'amount' => null, 'title' => ucfirst(str_replace(['.', '_'], ' ', (string)$r['action'])), 'detail' => (string)$r['detail'], 'status' => 'admin', 'by' => $r['name'] ?: 'System'];
    }
    usort($rowsOut, fn($a, $b) => strcmp($b['ts'], $a['ts']));
    return ['rows' => array_slice($rowsOut, ($page - 1) * $per, $per), 'more' => count($rowsOut) > $page * $per, 'page' => $page];
}

/** Every online payment attempt with who made it, what it was for and the payment record it created. */
function dash_ledger(array $f, int $per = 50): array
{
    pay_ensure_schema();
    $w = "WHERE a.direction = 'collect'"; $p = [];
    if ($f['status'] === 'success') $w .= " AND a.status = 'success'";
    elseif ($f['status'] === 'waiting') $w .= " AND a.status IN ('pending','unconfirmed')";
    elseif ($f['status'] === 'failed') $w .= " AND a.status = 'failed'";
    if ($f['event']) { $w .= ' AND r.event_id = ?'; $p[] = $f['event']; }
    if ($f['q'] !== '') { $l = '%' . $f['q'] . '%'; $w .= ' AND (r.reference LIKE ? OR r.full_name LIKE ? OR a.payer_name LIKE ? OR a.phone LIKE ? OR r.phone LIKE ?)'; array_push($p, $l, $l, $l, $l, $l); }
    $from = "FROM pay_attempts a LEFT JOIN reservations r ON r.id = a.reservation_id LEFT JOIN events e ON e.id = r.event_id LEFT JOIN payment_tabs t ON t.id = a.tab_id LEFT JOIN payments py ON py.id = a.payment_id";
    $sum = ['success' => ['n' => 0, 'amt' => 0], 'waiting' => ['n' => 0, 'amt' => 0], 'failed' => ['n' => 0, 'amt' => 0]];
    foreach (rows("SELECT CASE WHEN a.status = 'success' THEN 'success' WHEN a.status = 'failed' THEN 'failed' ELSE 'waiting' END s, COUNT(*) n, COALESCE(SUM(a.amount),0) amt $from WHERE a.direction = 'collect'" . ($f['event'] ? ' AND r.event_id = ' . (int)$f['event'] : '') . ' GROUP BY s') as $x) $sum[$x['s']] = ['n' => (int)$x['n'], 'amt' => (int)$x['amt']];
    $off = ($f['page'] - 1) * $per;
    $r = rows("SELECT a.id, a.status, a.message, a.amount, a.phone, a.created_at, a.completed_at, a.payer_name, a.payment_id, a.request_id, r.id rid, r.reference, r.full_name, r.package, e.title ev_title, t.title tab_title, py.id py_id $from $w ORDER BY a.id DESC LIMIT " . ($per + 1) . " OFFSET $off", $p);
    $more = count($r) > $per; if ($more) array_pop($r);
    return ['rows' => $r, 'more' => $more, 'sum' => $sum];
}
/** Is online payment switched on, are guests trying it, and does the Daraza wallet agree with what is recorded here? */
function dash_pay_health(): array
{
    $on = daraza_enabled();
    $h = ['mode' => !$on ? 'off' : (daraza_mock() ? 'mock' : (env('DARAZA_SANDBOX', '0') === '1' ? 'sandbox' : 'live')), 'att' => ['success' => 0, 'failed' => 0, 'pending' => 0, 'unconfirmed' => 0], 'last' => null, 'fails' => [], 'recorded' => 0, 'last_payment' => null, 'wallet' => null, 'expected' => null];
    try {
        foreach (rows("SELECT status, COUNT(*) n FROM pay_attempts WHERE direction = 'collect' AND created_at >= (NOW() - INTERVAL 14 DAY) GROUP BY status") as $x) $h['att'][$x['status']] = (int)$x['n'];
        $h['last'] = row("SELECT created_at, status, message FROM pay_attempts WHERE direction = 'collect' ORDER BY id DESC LIMIT 1");
        $h['fails'] = rows("SELECT created_at, message, phone FROM pay_attempts WHERE direction = 'collect' AND status IN ('failed','unconfirmed') ORDER BY id DESC LIMIT 3");
    } catch (PDOException $e) { /* attempts table not created yet */ }
    $h['recorded'] = (int)val('SELECT COUNT(*) FROM payments WHERE amount > 0');
    $h['last_payment'] = val('SELECT MAX(created_at) FROM payments WHERE amount > 0');
    if ($on && can('withdraw')) {
        $w = daraza_wallet();
        if ($w && $w['balance'] !== null) {
            $in = (int)val("SELECT COALESCE(SUM(amount),0) FROM payments WHERE channel IN ('daraza_mtn','daraza_airtel','daraza_refund')");
            try { $out = (int)val("SELECT COALESCE(SUM(amount),0) FROM daraza_withdrawals WHERE status = 'success'"); } catch (PDOException $e) { $out = 0; }
            $h['wallet'] = (int)$w['balance']; $h['expected'] = $in - $out;
        }
    }
    return $h;
}

function admin_dashboard(): void
{
    require_can('dashboard');
    $u = current_user();
    $tabs = dash_tabs();
    $tab = isset($tabs[$_GET['tab'] ?? '']) ? $_GET['tab'] : 'overview';
    if ($tab === 'events' && ($_GET['export'] ?? '') === 'csv' && (int)($_GET['event'] ?? 0)) dash_export_event((int)$_GET['event']);
    $d = ['title' => 'Dashboard', 'u' => $u, 'tabs' => $tabs, 'tab' => $tab];
    $money = can_any(['payments', 'reports', 'collections']);
    $res = can_any(['reservations', 'reports']);

    if ($tab === 'events') {
        $d['event_pick'] = (int)($_GET['event'] ?? 0);
        $d['totals'] = dash_event_totals($d['event_pick']);
        if ($d['event_pick'] && $d['totals']) { $d['pay_rows'] = dash_event_payments($d['event_pick']); $d['att_rows'] = dash_event_attempts($d['event_pick']); }
        $d['all_events'] = rows('SELECT id, title, start_at FROM events ORDER BY start_at DESC LIMIT 40');
    } elseif ($tab === 'ledger') {
        $d['lf'] = $lf = ['status' => in_array($_GET['status'] ?? '', ['success', 'waiting', 'failed'], true) ? $_GET['status'] : 'all', 'event' => (int)($_GET['event'] ?? 0), 'q' => trim((string)($_GET['q'] ?? '')), 'page' => max(1, (int)($_GET['page'] ?? 1))];
        $d['all_events'] = rows('SELECT id, title, start_at FROM events ORDER BY start_at DESC LIMIT 40');
        $d['ledger'] = dash_ledger($lf);
        $d['wgap'] = wallet_gap(); $d['wcand'] = [];
    } elseif ($tab === 'trail') {
        $f = ['event' => (int)($_GET['event'] ?? 0), 'kind' => in_array($_GET['kind'] ?? '', ['booking', 'payment', 'attempt', 'admin'], true) ? $_GET['kind'] : 'all', 'q' => (string)($_GET['q'] ?? ''), 'from' => $_GET['from'] ?? '', 'to' => $_GET['to'] ?? '', 'page' => (int)($_GET['page'] ?? 1)];
        $d['f'] = $f; $d['trail'] = dash_trail($f);
        $d['all_events'] = rows('SELECT id, title, start_at FROM events ORDER BY start_at DESC LIMIT 40');
    } elseif ($tab === 'withdraw') {
        pay_ensure_schema();
        $on = daraza_enabled();
        $d['daraza_on'] = $on;
        $d['wallet'] = $on ? daraza_wallet(isset($_GET['refresh'])) : null;
        $d['online_in'] = (int)val("SELECT COALESCE(SUM(amount),0) FROM payments WHERE channel IN ('daraza_mtn','daraza_airtel')");
        $d['online_refunds'] = (int)val("SELECT COALESCE(-SUM(amount),0) FROM payments WHERE channel = 'daraza_refund'");
        try {
            dw_expire_stale();
            $d['withdrawn'] = (int)val("SELECT COALESCE(SUM(amount),0) FROM daraza_withdrawals WHERE status = 'success'");
            $d['wd_pending'] = (int)val("SELECT COALESCE(SUM(amount),0) FROM daraza_withdrawals WHERE status IN ('pending','unconfirmed')");
            $d['wds'] = rows('SELECT w.*, u.name by_name FROM daraza_withdrawals w LEFT JOIN users u ON u.id = w.user_id ORDER BY w.id DESC LIMIT 40');
            $d['recent_numbers'] = array_column(rows("SELECT phone, MAX(id) m FROM daraza_withdrawals WHERE status = 'success' GROUP BY phone ORDER BY m DESC LIMIT 4"), 'phone');
        } catch (Throwable $e) { $d['withdrawn'] = $d['wd_pending'] = 0; $d['wds'] = []; $d['recent_numbers'] = []; }
        $d['cash_in'] = (int)val("SELECT COALESCE(SUM(amount),0) FROM payments WHERE channel IS NULL AND amount > 0");
    } elseif ($tab === 'visitors') {
        [$from, $to, $range] = analytics_range();
        $d['v'] = analytics_report($from, $to); $d['range'] = $range; $d['from'] = $from; $d['to'] = $to;
    } else {
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
            pay_ensure_schema();
            $d['revenue'] = (int)val('SELECT COALESCE(SUM(amount),0) FROM payments');
            $d['collected_today'] = (int)val('SELECT COALESCE(SUM(amount),0) FROM payments WHERE created_at >= CURDATE() AND amount > 0');
            $d['outstanding'] = (int)val("SELECT COALESCE(SUM(GREATEST(r.amount_due - COALESCE((SELECT SUM(amount) FROM payments p WHERE p.reservation_id=r.id),0),0)),0) FROM reservations r WHERE r.status IN ('pending','confirmed')");
            $d['ev_totals'] = array_slice(dash_event_totals(), 0, 6);
            $d['pay_health'] = dash_pay_health();
            $d['wgap'] = wallet_gap(); $d['wcand'] = $d['wgap'] && $d['wgap']['gap'] > 0 ? wallet_candidates() : [];
            try { $d['att_attention'] = (int)val("SELECT COUNT(*) FROM pay_attempts WHERE status = 'unconfirmed' AND direction = 'collect'"); $d['att_failed_today'] = (int)val("SELECT COUNT(*) FROM pay_attempts WHERE status = 'failed' AND created_at >= CURDATE()"); }
            catch (PDOException $e) { $d['att_attention'] = $d['att_failed_today'] = 0; }
        }
        if (can('analytics')) {
            try { $d['vis_today'] = (int)val('SELECT COUNT(DISTINCT visitor) FROM page_views WHERE viewed_at >= CURDATE()'); $d['vis_7'] = (int)val('SELECT COUNT(DISTINCT CONCAT(DATE(viewed_at), visitor)) FROM page_views WHERE viewed_at >= (CURDATE() - INTERVAL 6 DAY)'); $d['vis_online'] = (int)val('SELECT COUNT(DISTINCT visitor) FROM page_views WHERE viewed_at >= (NOW() - INTERVAL 5 MINUTE)'); }
            catch (PDOException $e) { $d['vis_today'] = $d['vis_7'] = $d['vis_online'] = 0; }
        }
        if (can('inquiries')) {
            $scope = inquiry_scope($u['role']);
            $in = implode(',', array_fill(0, max(1, count($scope)), '?'));
            $d['stat_inquiries'] = (int)val("SELECT COUNT(*) FROM inquiries WHERE status='new' AND type IN ($in)", $scope ?: ['']);
            $d['inq_list'] = rows("SELECT * FROM inquiries WHERE status='new' AND type IN ($in) ORDER BY id DESC LIMIT 5", $scope ?: ['']);
        }
        if (can('advertising')) {
            $d['stat_partners'] = (int)val("SELECT COUNT(*) FROM partner_applications WHERE status='pending'");
            $d['partner_list'] = rows("SELECT id, company, partnership_type, created_at FROM partner_applications WHERE status='pending' ORDER BY id DESC LIMIT 5");
            $d['stat_sponsors'] = (int)val('SELECT COUNT(*) FROM sponsors WHERE active = 1');
        }
        if (can('audit')) $d['activity'] = rows('SELECT l.*, u.name FROM audit_log l LEFT JOIN users u ON u.id = l.user_id ORDER BY l.id DESC LIMIT 8');
    }
    admin_view('dashboard', $d);
}
