<?php
declare(strict_types=1);

/* ================= Guest: pay a booking with mobile money ================= */
function pay_json(array $d, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    echo json_encode($d);
    exit;
}
/**
 * Check a payment request and start it. Returns ['at' => attempt row, 'amount' => int, 'net' => 'mtn'|'airtel']
 * or ['error' => message, 'code' => http status]. The slow Daraza call is started by the caller after it has answered the browser.
 */
function pay_begin(array $r, string $phoneIn, string $amountIn, string $mail): array
{
    $err = fn(string $m, int $c = 422) => ['error' => $m, 'code' => $c];
    if (!daraza_enabled()) return $err('Online payment is not switched on yet. Please pay through WhatsApp or call us.', 503);
    if (!in_array($r['status'], ['pending', 'confirmed'], true)) return $err('This booking can no longer be paid.');
    $balance = (int)max(0, (float)$r['amount_due'] - res_paid((int)$r['id']));
    if ($balance < 1) return $err('This booking is already paid in full.');
    $phone = daraza_phone($phoneIn);
    if (!$phone) return $err('Enter your mobile money number, for example 0772 123 456.');
    $net = daraza_network($phone);
    $mail = trim($mail);
    if ($mail !== '' && !filter_var($mail, FILTER_VALIDATE_EMAIL)) return $err('That email address looks invalid.');
    $amount = (int)preg_replace('/\D+/', '', $amountIn) ?: min($balance, DARAZA_MAX);
    if ($amount > $balance) return $err('That is more than the balance of ' . money($balance) . '.');
    if ($amount > DARAZA_MAX) return $err('The largest single payment is ' . money(DARAZA_MAX) . '. Pay part now and the rest after.');
    if ($amount < DARAZA_MIN && $balance >= DARAZA_MIN) return $err('The smallest payment is ' . money(DARAZA_MIN) . '.');
    if (rate_limited('paystart', 6, 900)) return $err('Too many attempts. Please wait a few minutes and try again.', 429);
    try {
        if (daraza_has_pending((int)$r['id'], 'collect')) return $err('A payment request is already waiting for approval on your phone. Please approve it or wait a few minutes.', 409);
        if ((int)val("SELECT COUNT(*) FROM pay_attempts WHERE phone = ? AND created_at > (NOW() - INTERVAL 1 HOUR)", [$phone]) >= 4) return $err('Too many payment requests to this number. Please try again later.', 429);
    } catch (PDOException $e) { if ($e->getCode() === '42S02') daraza_ensure_table(); else throw $e; }
    if ($mail !== '' && empty($r['email'])) q('UPDATE reservations SET email = ? WHERE id = ?', [$mail, $r['id']]);
    $at = daraza_start((int)$r['id'], 'collect', $amount, $phone);
    audit('pay.start', $r['reference'] . ' ' . money($amount));
    return ['at' => $at, 'amount' => $amount, 'net' => $net];
}
/** Pay (all or part of) an existing booking. */
function post_booking_pay(array $a): void
{
    $r = row('SELECT * FROM reservations WHERE reference = ?', [strtoupper($a['ref'])]);
    if (!$r) pay_json(['ok' => false, 'error' => 'Booking not found.'], 404);
    $o = pay_begin($r, (string)($_POST['phone'] ?? ''), (string)($_POST['amount'] ?? ''), (string)($_POST['email'] ?? ''));
    if (isset($o['error'])) pay_json(['ok' => false, 'error' => $o['error']], $o['code']);
    // answer the browser now; the customer approves on their phone and the result is recorded when Daraza replies
    daraza_respond_then(['ok' => true, 'token' => $o['at']['token'], 'amount' => $o['amount'], 'network' => $o['net']], fn() => daraza_run((int)$o['at']['id']));
}
/** Polled by the booking page while the guest approves the prompt on their phone. */
function page_pay_status(array $a): void
{
    if (!preg_match('/^[a-f0-9]{24}$/', $a['token'])) pay_json(['error' => 'bad token'], 404);
    if (rate_limited('paypoll', 200, 1800)) pay_json(['error' => 'slow down'], 429);
    try { daraza_expire_stale(); } catch (Throwable $e) {}
    $at = row('SELECT a.status, a.message, a.amount, a.created_at, r.reference, r.id AS rid, r.status AS rstatus FROM pay_attempts a JOIN reservations r ON r.id = a.reservation_id WHERE a.token = ? AND r.reference = ? AND a.direction = ?',
        [$a['token'], strtoupper($a['ref']), 'collect']);
    if (!$at) pay_json(['error' => 'not found'], 404);
    $msg = $at['message'];
    if ($at['status'] === 'unconfirmed') $msg = 'We could not confirm your payment yet. If money left your phone, please do not pay again. Quote ' . $at['reference'] . ' to the team and we will confirm it.';
    $out = ['status' => $at['status'], 'message' => $msg, 'elapsed' => time() - strtotime((string)$at['created_at'])];
    if ($at['status'] === 'success' && $at['rstatus'] === 'confirmed') { $t = ticket_url((int)$at['rid']); $out['ticket'] = $t; $out['download'] = $t . '?download=1'; }
    pay_json($out);
}

/* ================= Admin: refunds and unconfirmed payments ================= */
/** Send a refund to the guest's phone through Daraza. */
function admin_daraza_refund(array $a): void
{
    require_can('payments');
    $role = current_user()['role'] ?? '';
    $id = (int)$a['id'];
    $r = row('SELECT * FROM reservations WHERE id = ?', [$id]) ?? not_found();
    $back = 'admin/reservations/' . $id;
    $fail = function (string $m) use ($back): never { flash($m, 'err'); redirect($back); };
    if (!in_array($role, ['admin', 'finance', 'event_manager'], true)) $fail('Only an administrator, finance officer or event manager can send refunds.');
    if (!daraza_enabled()) $fail('Daraza is not set up on this site.');
    $phone = daraza_phone((string)($_POST['phone'] ?? ''));
    if (!$phone) $fail('Enter the mobile money number to send the refund to.');
    $net = (int)res_paid($id);
    $amount = (int)preg_replace('/\D+/', '', (string)($_POST['amount'] ?? '0'));
    if ($amount < DARAZA_MIN || $amount > min($net, DARAZA_MAX)) $fail('The refund must be between ' . money(DARAZA_MIN) . ' and ' . money(min($net, DARAZA_MAX)) . ' (what is currently paid, up to the Daraza limit). Send larger refunds in parts.');
    if (($_POST['confirm'] ?? '') !== $r['reference']) $fail('Type the booking reference to confirm sending money.');
    try { if (daraza_has_pending($id, 'refund')) $fail('A refund for this booking is already being processed.'); } catch (PDOException $e) { if ($e->getCode() === '42S02') daraza_ensure_table(); else throw $e; }
    $at = daraza_start($id, 'refund', $amount, $phone, (int)current_user()['id']);
    audit('pay.refund_start', $r['reference'] . ' ' . money($amount) . ' to ' . $phone);
    flash('Refund of ' . money($amount) . ' is being sent. Refresh this page in a moment to see the result.');
    daraza_respond_then([], fn() => daraza_run((int)$at['id']), url('admin/reservations/' . $id));
}
/** A person decides what happened to an attempt Daraza never answered. */
function admin_pay_resolve(array $a): void
{
    require_can('payments');
    $at = row('SELECT * FROM pay_attempts WHERE id = ?', [(int)$a['id']]) ?? not_found();
    $dest = 'admin/reservations/' . (int)$at['reservation_id'];
    if ($at['status'] !== 'unconfirmed') { flash('That payment is no longer waiting for a decision.', 'err'); redirect($dest); }
    $uid = (int)current_user()['id'];
    if (($_POST['choice'] ?? '') === 'received') {
        daraza_settle((int)$at['id'], 'success', 'Confirmed by ' . (current_user()['name'] ?? 'staff') . ' from the Daraza statement', null, $uid);
        audit('pay.resolve_received', '#' . $at['id'] . ' ' . money($at['amount']));
        flash('Marked as ' . ($at['direction'] === 'refund' ? 'sent' : 'received') . ' and recorded in the ledger.');
    } else {
        daraza_settle((int)$at['id'], 'failed', 'Dismissed by ' . (current_user()['name'] ?? 'staff') . ': no money moved', null, $uid);
        audit('pay.resolve_dismissed', '#' . $at['id']);
        flash('Dismissed. Nothing was recorded.');
    }
    redirect($dest);
}
