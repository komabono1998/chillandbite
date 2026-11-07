<?php
declare(strict_types=1);

/* ================= Collections dashboard: every payment, split by the route it came through ================= */
function collections_report(string $from, string $to, int $event = 0, int $limit = 60): array
{
    pay_ensure_schema();
    $w = 'p.created_at >= ? AND p.created_at < DATE_ADD(?, INTERVAL 1 DAY)'; $p = [$from, $to];
    if ($event) { $w .= ' AND r.event_id = ?'; $p[] = $event; }
    $j = 'FROM payments p JOIN reservations r ON r.id = p.reservation_id JOIN events e ON e.id = r.event_id';
    $ch = 'COALESCE(p.channel, p.method)';
    $by = [];
    foreach (rows("SELECT $ch ch, COUNT(*) n, SUM(p.amount) amt $j WHERE $w GROUP BY $ch", $p) as $x) $by[$x['ch']] = ['n' => (int)$x['n'], 'amt' => (int)$x['amt']];
    $in = 0; $out = 0;
    foreach ($by as $c => $v) { if ($v['amt'] >= 0) $in += $v['amt']; else $out += -$v['amt']; }
    $daily = [];
    for ($d = strtotime($from), $n = 0; $d <= strtotime($to) && $n < 400; $d = strtotime('+1 day', $d), $n++) $daily[date('Y-m-d', $d)] = [];
    foreach (rows("SELECT DATE(p.created_at) d, $ch ch, SUM(p.amount) amt $j WHERE $w AND p.amount > 0 GROUP BY DATE(p.created_at), $ch", $p) as $x)
        if (isset($daily[$x['d']])) $daily[$x['d']][$x['ch']] = (int)$x['amt'];
    $events = rows("SELECT e.id, e.title, e.start_at, $ch ch, SUM(p.amount) amt $j WHERE $w GROUP BY e.id, e.title, e.start_at, $ch ORDER BY e.start_at DESC", $p);
    $recent = rows("SELECT p.*, $ch ch, r.reference, r.full_name, r.id rid, e.title, u.name by_name $j LEFT JOIN users u ON u.id = p.recorded_by WHERE $w ORDER BY p.id DESC LIMIT $limit", $p);
    try {
        dw_expire_stale();
        $withdrawn = (int)val("SELECT COALESCE(SUM(amount),0) FROM daraza_withdrawals WHERE status='success' AND created_at >= ? AND created_at < DATE_ADD(?, INTERVAL 1 DAY)", [$from, $to]);
        $wds = rows('SELECT w.*, u.name by_name FROM daraza_withdrawals w LEFT JOIN users u ON u.id = w.user_id ORDER BY w.id DESC LIMIT 15');
    } catch (Throwable $e) { $withdrawn = 0; $wds = []; }
    return ['by' => $by, 'in' => $in, 'out' => $out, 'net' => $in - $out, 'daily' => $daily, 'events' => $events, 'recent' => $recent, 'withdrawn' => $withdrawn, 'withdrawals' => $wds, 'from' => $from, 'to' => $to];
}
function admin_collections(): void
{
    require_can('collections');
    [$from, $to, $range] = analytics_range();
    $event = (int)($_GET['event'] ?? 0);
    $rep = collections_report($from, $to, $event);
    $isAdmin = can('withdraw');
    admin_view('collections', ['title' => 'Collections', 'r' => $rep, 'range' => $range, 'event' => $event, 'all_events' => rows('SELECT id, title, start_at FROM events ORDER BY start_at DESC LIMIT 100'),
        'wallet' => $isAdmin || can('collections') ? (daraza_enabled() ? daraza_wallet(isset($_GET['refresh'])) : null) : null, 'can_withdraw' => $isAdmin && daraza_enabled(), 'daraza_on' => daraza_enabled()]);
}
/** Fee preview for the withdraw form. */
function admin_collections_fees(): void
{
    require_can('withdraw');
    header('Content-Type: application/json'); header('Cache-Control: no-store');
    $a = (int)preg_replace('/\D+/', '', (string)($_GET['amount'] ?? '0'));
    if ($a < DARAZA_MIN || $a > DARAZA_MAX) { echo json_encode(['ok' => false, 'error' => 'Amount must be ' . money(DARAZA_MIN) . ' to ' . money(DARAZA_MAX)]); return; }
    $f = daraza_fees($a);
    echo json_encode($f ? ['ok' => true] + $f : ['ok' => false, 'error' => 'Could not get the fee from Daraza.']);
}

/** Take money out of the Daraza wallet to a mobile money number. Administrator only, with password re-entry. */
function admin_withdraw(): void
{
    require_can('withdraw');
    $u = current_user();
    $fail = function (string $m): never { flash($m, 'err'); redirect('admin/collections#withdraw'); };
    if (!daraza_enabled()) $fail('Daraza is not set up on this site.');
    $phone = daraza_phone((string)($_POST['phone'] ?? ''));
    if (!$phone) $fail('Enter the mobile money number to send the money to.');
    $amount = (int)preg_replace('/\D+/', '', (string)($_POST['amount'] ?? '0'));
    if ($amount < DARAZA_MIN || $amount > DARAZA_MAX) $fail('The amount must be between ' . money(DARAZA_MIN) . ' and ' . money(DARAZA_MAX) . ' per withdrawal.');
    if (strtoupper(trim((string)($_POST['confirm'] ?? ''))) !== 'WITHDRAW') $fail('Type WITHDRAW to confirm.');
    $hash = (string)val('SELECT password_hash FROM users WHERE id = ?', [$u['id']]);
    if (!password_verify((string)($_POST['password'] ?? ''), $hash)) { audit('withdraw.bad_password', money($amount)); sleep(1); $fail('Your password was not correct. Nothing was sent.'); }
    if (rate_limited('withdraw', 5, 3600)) $fail('Too many withdrawals this hour. Please wait and try again.');
    $cap = (int)env('DARAZA_WITHDRAW_DAILY_MAX', '0');
    try {
        dw_expire_stale();
        if (val("SELECT id FROM daraza_withdrawals WHERE status = 'pending' LIMIT 1")) $fail('Another withdrawal is still being processed. Wait for it to finish.');
        if ($cap > 0 && (int)val("SELECT COALESCE(SUM(amount),0) FROM daraza_withdrawals WHERE status IN ('success','pending','unconfirmed') AND created_at >= CURDATE()") + $amount > $cap) $fail('This would pass the daily withdrawal limit of ' . money($cap) . '.');
    } catch (PDOException $e) { if ($e->getCode() === '42S02') daraza_ensure_withdrawals(); else throw $e; }
    $id = dw_start($amount, $phone, (int)$u['id']);
    audit('withdraw.start', money($amount) . ' to ' . $phone);
    flash('Withdrawal of ' . money($amount) . ' to ' . $phone . ' is being sent. Refresh in a moment to see the result.');
    daraza_respond_then([], fn() => dw_run($id), url('admin/collections#withdraw'));
}
/** A person decides what happened to a withdrawal Daraza never answered. */
function admin_withdraw_resolve(array $a): void
{
    require_can('withdraw');
    $w = row('SELECT * FROM daraza_withdrawals WHERE id = ?', [(int)$a['id']]) ?? not_found();
    if ($w['status'] !== 'unconfirmed') { flash('That withdrawal is no longer waiting for a decision.', 'err'); redirect('admin/collections#withdraw'); }
    $who = current_user()['name'] ?? 'admin';
    if (($_POST['choice'] ?? '') === 'received') { dw_settle((int)$w['id'], 'success', 'Confirmed by ' . $who . ' from the Daraza statement'); audit('withdraw.resolve_sent', '#' . $w['id']); flash('Marked as sent.'); }
    else { dw_settle((int)$w['id'], 'failed', 'Dismissed by ' . $who . ': no money moved'); audit('withdraw.resolve_dismissed', '#' . $w['id']); flash('Dismissed. Nothing was recorded as sent.'); }
    redirect('admin/collections#withdraw');
}

/** Export of every payment in the chosen period with its channel. */
function collections_export_dataset(): ?array
{
    [$from, $to] = analytics_range();
    $rep = collections_report($from, $to, (int)($_GET['event'] ?? 0), 10000);
    $rows = [];
    foreach ($rep['recent'] as $x) $rows[] = [$x['created_at'], $x['reference'], $x['full_name'], $x['title'], PAY_CHANNELS[$x['ch']][0] ?? $x['ch'], (int)$x['amount'], (string)$x['by_name'] ?: (str_contains((string)$x['ch'], 'daraza') ? 'Online' : ''), (string)$x['note']];
    $sum = [];
    foreach ($rep['by'] as $c => $v) $sum[] = (PAY_CHANNELS[$c][0] ?? $c) . ': ' . number_format($v['amt']) . ' (' . $v['n'] . ')';
    return ['Collections by channel', 'Period ' . $from . ' to ' . $to . ' | Net ' . number_format($rep['net']) . ' UGX | ' . implode('; ', $sum),
        ['Date', 'Reference', 'Guest', 'Event', 'Channel', 'Amount (UGX)', 'Recorded by', 'Note'], $rows];
}
