<?php
declare(strict_types=1);

/* ================= Collections dashboard: every payment, split by the route it came through ================= */
function collections_report(string $from, string $to, int $event = 0, int $limit = 60): array
{
    pay_ensure_schema();
    $w = 'p.created_at >= ? AND p.created_at < DATE_ADD(?, INTERVAL 1 DAY)'; $p = [$from, $to];
    if ($event) { $w .= ' AND COALESCE(r.event_id, p.event_id) = ?'; $p[] = $event; }
    $j = 'FROM payments p LEFT JOIN reservations r ON r.id = p.reservation_id LEFT JOIN events e ON e.id = COALESCE(r.event_id, p.event_id) LEFT JOIN payment_tabs t ON t.id = p.tab_id';
    $jEv = 'FROM payments p LEFT JOIN reservations r ON r.id = p.reservation_id JOIN events e ON e.id = COALESCE(r.event_id, p.event_id)';
    $ch = 'COALESCE(p.channel, p.method)';
    $by = [];
    foreach (rows("SELECT $ch ch, COUNT(*) n, SUM(p.amount) amt $j WHERE $w GROUP BY $ch", $p) as $x) $by[$x['ch']] = ['n' => (int)$x['n'], 'amt' => (int)$x['amt']];
    $in = 0; $out = 0;
    foreach ($by as $c => $v) { if ($v['amt'] >= 0) $in += $v['amt']; else $out += -$v['amt']; }
    $daily = [];
    for ($d = strtotime($from), $n = 0; $d <= strtotime($to) && $n < 400; $d = strtotime('+1 day', $d), $n++) $daily[date('Y-m-d', $d)] = [];
    foreach (rows("SELECT DATE(p.created_at) d, $ch ch, SUM(p.amount) amt $j WHERE $w AND p.amount > 0 GROUP BY DATE(p.created_at), $ch", $p) as $x)
        if (isset($daily[$x['d']])) $daily[$x['d']][$x['ch']] = (int)$x['amt'];
    $events = rows("SELECT e.id, e.title, e.start_at, $ch ch, SUM(p.amount) amt $jEv WHERE $w GROUP BY e.id, e.title, e.start_at, $ch ORDER BY e.start_at DESC", $p);
    $tabs = $event ? [] : rows("SELECT t.id, t.title, $ch ch, COUNT(*) n, SUM(p.amount) amt $j WHERE $w AND p.tab_id IS NOT NULL GROUP BY t.id, t.title, $ch ORDER BY t.title", $p);
    $recent = rows("SELECT p.*, $ch ch, r.reference, r.full_name, r.id rid, e.title, t.title tab_title, u.name by_name $j LEFT JOIN users u ON u.id = p.recorded_by WHERE $w ORDER BY p.id DESC LIMIT $limit", $p);
    try { daraza_expire_stale(); $attention = rows("SELECT a.*, t.title tab_title FROM pay_attempts a JOIN payment_tabs t ON t.id = a.tab_id WHERE a.status = 'unconfirmed' ORDER BY a.id DESC LIMIT 10"); } catch (Throwable $e) { $attention = []; }
    try {
        dw_expire_stale();
        $withdrawn = (int)val("SELECT COALESCE(SUM(amount),0) FROM daraza_withdrawals WHERE status='success' AND created_at >= ? AND created_at < DATE_ADD(?, INTERVAL 1 DAY)", [$from, $to]);
        $wds = rows('SELECT w.*, u.name by_name FROM daraza_withdrawals w LEFT JOIN users u ON u.id = w.user_id ORDER BY w.id DESC LIMIT 15');
    } catch (Throwable $e) { $withdrawn = 0; $wds = []; }
    return ['by' => $by, 'in' => $in, 'out' => $out, 'net' => $in - $out, 'daily' => $daily, 'events' => $events, 'tabs' => $tabs, 'attention' => $attention, 'recent' => $recent, 'withdrawn' => $withdrawn, 'withdrawals' => $wds, 'from' => $from, 'to' => $to];
}
function admin_collections(): void
{
    require_can('collections');
    [$from, $to, $range] = analytics_range();
    $event = (int)($_GET['event'] ?? 0);
    $rep = collections_report($from, $to, $event);
    $isAdmin = can('withdraw');
    admin_view('collections', ['title' => 'Collections', 'r' => $rep, 'range' => $range, 'event' => $event, 'all_events' => rows('SELECT id, title, start_at FROM events ORDER BY start_at DESC LIMIT 100'),
        'wgap' => wallet_gap(), 'wcand' => wallet_candidates(), 'wallet' => $isAdmin || can('collections') ? (daraza_enabled() ? daraza_wallet(isset($_GET['refresh'])) : null) : null, 'can_withdraw' => $isAdmin && daraza_enabled(), 'daraza_on' => daraza_enabled()]);
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

/** Wallet balance against what is recorded here: money that reached Daraza but has no name and number on file yet. */
function wallet_gap(bool $auto = true, bool $internal = false): ?array
{
    if (!daraza_enabled() || (!$internal && !(can('withdraw') || can('collections') || can('payments')))) return null;
    pay_ensure_schema();
    $w = daraza_wallet(isset($_GET['refresh']) || $internal);
    if (!$w || $w['balance'] === null) return null;
    $in = (int)val("SELECT COALESCE(SUM(amount),0) FROM payments WHERE channel IN ('daraza_mtn','daraza_airtel','daraza_refund')");
    try { $out = (int)val("SELECT COALESCE(SUM(amount),0) FROM daraza_withdrawals WHERE status IN ('success','pending','unconfirmed')"); } catch (Throwable $e) { $out = 0; }
    $bal = (int)$w['balance'];
    $g = ['balance' => $bal, 'recorded' => $in, 'withdrawn' => $out, 'gap' => max(0, $bal - ($in - $out)), 'auto' => 0];
    if ($auto && $g['gap'] > 0) {
        $n = wallet_auto_match($g['gap']);
        if ($n) { $g = wallet_gap(false, $internal) ?? $g; $g['auto'] = $n; }
    }
    return $g;
}
/**
 * Read the wallet and file new money by itself. When the unrecorded wallet money equals the amounts of waiting online payments
 * (never answered by Daraza), those attempts are recorded against their bookings. Daraza fees may take up to about 5%, so the
 * wallet can be a little under the amount. If more than one set of attempts fits, nothing is guessed: staff match it by hand.
 * Returns the number of payments recorded.
 */
function wallet_auto_match(int $gap): int
{
    try {
        $lock = (int)val("SELECT GET_LOCK('cb_wallet_match', 0)");
        if (!$lock) return 0;
        $cand = rows("SELECT id, amount FROM pay_attempts WHERE direction = 'collect' AND status IN ('pending','unconfirmed') AND created_at >= (NOW() - INTERVAL 30 DAY) ORDER BY id DESC LIMIT 14");
        // pending ones younger than 6 minutes are still being answered by Daraza itself
        $cand = array_values(array_filter($cand, fn($a) => (int)val("SELECT status = 'unconfirmed' OR created_at < (NOW() - INTERVAL 6 MINUTE) FROM pay_attempts WHERE id = ?", [$a['id']]) === 1));
        $n = count($cand); $best = null; $bestSum = 0; $ties = 0;
        for ($mask = 1; $mask < (1 << $n) && $mask < 16384; $mask++) {
            $sum = 0; $cnt = 0;
            for ($i = 0; $i < $n; $i++) if ($mask & (1 << $i)) { $sum += (int)$cand[$i]['amount']; $cnt++; }
            if ($cnt > 4 || $sum * 0.95 > $gap + 0.5 || $sum > $gap * 1.0527) continue;   // within the wallet, allowing for fees
            if ($sum > $bestSum) { $bestSum = $sum; $best = $mask; $ties = 1; } elseif ($sum === $bestSum) $ties++;
        }
        $done = 0;
        if ($best !== null && $ties === 1) {
            for ($i = 0; $i < $n; $i++) if ($best & (1 << $i)) {
                if (daraza_settle((int)$cand[$i]['id'], 'success', 'Found in the Daraza wallet and matched automatically', null, null)) { $done++; audit('pay.auto_match', '#' . $cand[$i]['id'] . ' ' . money((int)$cand[$i]['amount'])); }
            }
        }
        q("SELECT RELEASE_LOCK('cb_wallet_match')");
        return $done;
    } catch (Throwable $e) { error_log('wallet auto match: ' . $e->getMessage()); return 0; }
}
/** Called by a scheduled task (hPanel cron) so the wallet is read and filed even when nobody has the dashboard open. */
function page_wallet_sync(): void
{
    header('Content-Type: text/plain; charset=utf-8'); header('Cache-Control: no-store');
    $key = setting('cron_key', '');
    if ($key === '') { $key = bin2hex(random_bytes(12)); save_setting('cron_key', $key); }
    if (!hash_equals($key, (string)($_GET['key'] ?? ''))) { http_response_code(403); echo "Forbidden\n"; return; }
    $g = wallet_gap(true, true);
    echo $g ? 'wallet ' . $g['balance'] . ', unrecorded ' . $g['gap'] . ', filed ' . $g['auto'] . "\n" : "wallet not available\n";
}
/** Online payment attempts that may be the money sitting in the wallet: never answered or marked failed, newest first. */
function wallet_candidates(int $limit = 40): array
{
    try {
        return rows("SELECT a.id, a.amount, a.phone, a.status, a.message, a.created_at, a.payer_name, r.reference, r.full_name, r.package, e.title ev_title, t.title tab_title
                     FROM pay_attempts a LEFT JOIN reservations r ON r.id = a.reservation_id LEFT JOIN events e ON e.id = r.event_id LEFT JOIN payment_tabs t ON t.id = a.tab_id
                     WHERE a.direction = 'collect' AND a.status IN ('pending','unconfirmed','failed') AND a.created_at >= (NOW() - INTERVAL 90 DAY)
                     ORDER BY (a.status = 'failed'), a.id DESC LIMIT " . (int)$limit);
    } catch (Throwable $e) { return []; }
}
/** The wallet received this attempt's money: file it against the booking or payment tab it was made for. */
function admin_pay_match(array $a): void
{
    require_can('payments');
    $back = ($_POST['back'] ?? '') === 'dash' ? 'admin' : 'admin/collections';
    $fail = function (string $m) use ($back): never { flash($m, 'err'); redirect($back); };
    $at = row("SELECT * FROM pay_attempts WHERE id = ? AND direction = 'collect'", [(int)$a['id']]) ?? not_found();
    if (!in_array($at['status'], ['pending', 'unconfirmed', 'failed'], true)) $fail('That payment is already recorded.');
    $g = wallet_gap();
    if (!$g) $fail('The wallet balance could not be read, so nothing was recorded.');
    if ((int)$at['amount'] > $g['gap']) $fail('The wallet only holds ' . money($g['gap']) . ' that is not recorded yet, which is less than this payment (' . money((int)$at['amount']) . '). Refresh the balance, or choose another payment.');
    if ($at['status'] !== 'unconfirmed') q("UPDATE pay_attempts SET status = 'unconfirmed' WHERE id = ?", [(int)$at['id']]);
    daraza_settle((int)$at['id'], 'success', 'Matched to money in the Daraza wallet by ' . (current_user()['name'] ?? 'staff'), null, (int)current_user()['id']);
    audit('pay.wallet_match', '#' . $at['id'] . ' ' . money((int)$at['amount']) . ' ' . $at['phone']);
    flash('Recorded ' . money((int)$at['amount']) . ' against the booking or payment page it was made for.');
    redirect($back);
}
/** Record money that is already in the wallet, with the payer's name and phone. */
function admin_record_wallet(): void
{
    require_can('payments');
    $back = ($_POST['back'] ?? '') === 'dash' ? 'admin' : 'admin/collections';
    $fail = function (string $m) use ($back): never { flash($m, 'err'); redirect($back); };
    $g = wallet_gap();
    if (!$g) $fail('The wallet balance could not be read, so nothing was recorded.');
    $name = trim(mb_substr((string)($_POST['payer_name'] ?? ''), 0, 140));
    $phone = daraza_phone((string)($_POST['payer_phone'] ?? ''));
    $amt = (int)preg_replace('/\D+/', '', (string)($_POST['amount'] ?? '0'));
    if ($name === '') $fail('Enter the name of the person who paid.');
    if (!$phone) $fail('Enter the phone number of the person who paid.');
    if ($amt < 1) $fail('Enter the amount.');
    if ($amt > $g['gap']) $fail('That is more than the unrecorded wallet money (' . money($g['gap']) . '). Nothing was recorded.');
    $rid = null; $ref = strtoupper(trim((string)($_POST['reference'] ?? '')));
    if ($ref !== '') { $rid = val('SELECT id FROM reservations WHERE reference = ?', [$ref]); if (!$rid) $fail('No booking has the reference ' . $ref . '.'); $rid = (int)$rid; }
    $eid = (int)($_POST['event_id'] ?? 0);
    if ($rid) $eid = (int)val('SELECT event_id FROM reservations WHERE id = ?', [$rid]);
    if (!$eid || !val('SELECT id FROM events WHERE id = ?', [$eid])) $fail('Choose the event this payment is for.');
    $note = 'Received in Daraza wallet, recorded by ' . current_user()['name'] . ': ' . $name . ', ' . $phone;
    q('INSERT INTO payments (reservation_id, amount, method, channel, note, recorded_by, payer_name, payer_phone, event_id) VALUES (?,?,?,?,?,?,?,?,?)',
        [$rid, $amt, 'mobile_money', 'daraza_' . daraza_network($phone), mb_substr($note, 0, 255), current_user()['id'], $name, $phone, $eid]);
    $msg = 'Recorded ' . money($amt) . ' from ' . $name . '.';
    if ($rid) {
        $r = row('SELECT status, amount_due FROM reservations WHERE id = ?', [$rid]);
        if ($r && $r['status'] === 'pending' && res_paid($rid) >= (float)$r['amount_due']) { q("UPDATE reservations SET status='confirmed' WHERE id = ?", [$rid]); try { send_ticket($rid); } catch (Throwable $e) {} $msg .= ' Booking confirmed and ticket issued.'; }
    }
    audit('payment.wallet_record', money($amt) . ' ' . $name . ' ' . $phone);
    flash($msg); redirect($back);
}
/** Withdrawals started from the dashboard Withdraw tab return there. */
function wd_back(): string { return ($_POST['back'] ?? '') === 'dash' ? 'admin?tab=withdraw' : 'admin/collections#withdraw'; }

/** Take money out of the Daraza wallet to a mobile money number. Administrator only, with password re-entry. */
function admin_withdraw(): void
{
    require_can('withdraw');
    $u = current_user();
    $fail = function (string $m): never { flash($m, 'err'); redirect(wd_back()); };
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
    daraza_respond_then([], fn() => dw_run($id), url(wd_back()));
}
/** A person decides what happened to a withdrawal Daraza never answered. */
function admin_withdraw_resolve(array $a): void
{
    require_can('withdraw');
    $w = row('SELECT * FROM daraza_withdrawals WHERE id = ?', [(int)$a['id']]) ?? not_found();
    if ($w['status'] !== 'unconfirmed') { flash('That withdrawal is no longer waiting for a decision.', 'err'); redirect(wd_back()); }
    $who = current_user()['name'] ?? 'admin';
    if (($_POST['choice'] ?? '') === 'received') { dw_settle((int)$w['id'], 'success', 'Confirmed by ' . $who . ' from the Daraza statement'); audit('withdraw.resolve_sent', '#' . $w['id']); flash('Marked as sent.'); }
    else { dw_settle((int)$w['id'], 'failed', 'Dismissed by ' . $who . ': no money moved'); audit('withdraw.resolve_dismissed', '#' . $w['id']); flash('Dismissed. Nothing was recorded as sent.'); }
    redirect(wd_back());
}

/** Export of every payment in the chosen period with its channel. */
function collections_export_dataset(): ?array
{
    [$from, $to] = analytics_range();
    $rep = collections_report($from, $to, (int)($_GET['event'] ?? 0), 10000);
    $rows = [];
    foreach ($rep['recent'] as $x) $rows[] = [$x['created_at'], $x['reference'] ?? 'PAGE', $x['full_name'] ?? '', $x['title'] ?? ($x['tab_title'] ?? ''), PAY_CHANNELS[$x['ch']][0] ?? $x['ch'], (int)$x['amount'], (string)$x['by_name'] ?: (str_contains((string)$x['ch'], 'daraza') ? 'Online' : ''), (string)$x['note']];
    $sum = [];
    foreach ($rep['by'] as $c => $v) $sum[] = (PAY_CHANNELS[$c][0] ?? $c) . ': ' . number_format($v['amt']) . ' (' . $v['n'] . ')';
    return ['Collections by channel', 'Period ' . $from . ' to ' . $to . ' | Net ' . number_format($rep['net']) . ' UGX | ' . implode('; ', $sum),
        ['Date', 'Reference', 'Guest', 'Event', 'Channel', 'Amount (UGX)', 'Recorded by', 'Note'], $rows];
}
