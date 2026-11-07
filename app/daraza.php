<?php
declare(strict_types=1);

/*
 * Daraza mobile money payments.
 *   request_to_pay  takes money from a guest's phone (they approve a prompt; the answer can take up to ~3 minutes)
 *   remit           sends money to a phone (refunds)
 * The slow call runs after the page has already answered the browser, and the page polls pay_attempts for the result.
 * Settings in .env: DARAZA_API_KEY, DARAZA_SANDBOX=1 (Daraza test endpoints, no real money), DARAZA_BASE, DARAZA_ENABLED=0.
 * Method is chosen from the phone number: 1 = MTN Mobile Money, 2 = Airtel Money. Amount: UGX 500 to 5,000,000.
 * DARAZA_MOCK=1 simulates the gateway on localhost only, for testing without real money.
 */

function daraza_mock(): bool
{
    $host = strtolower(preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost')));
    return env('DARAZA_MOCK', '0') === '1' && in_array($host, ['localhost', '127.0.0.1', '[::1]'], true);
}
function daraza_enabled(): bool
{
    if (env('DARAZA_ENABLED', '1') === '0') return false;
    return daraza_mock() || strlen((string)env('DARAZA_API_KEY', '')) >= 8;
}
function daraza_ensure_table(): void
{
    db()->exec("CREATE TABLE IF NOT EXISTS pay_attempts (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        token CHAR(24) NOT NULL UNIQUE,
        reservation_id INT UNSIGNED NOT NULL,
        direction ENUM('collect','refund') NOT NULL DEFAULT 'collect',
        amount INT UNSIGNED NOT NULL,
        phone VARCHAR(20) NOT NULL,
        method SMALLINT NOT NULL DEFAULT 1,
        status ENUM('pending','success','failed','unconfirmed') NOT NULL DEFAULT 'pending',
        request_id VARCHAR(80) NULL,
        message VARCHAR(255) NULL,
        started_by INT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        completed_at DATETIME NULL,
        KEY idx_pa_res (reservation_id, status), KEY idx_pa_phone (phone, created_at),
        CONSTRAINT fk_pa_res FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
/** Runs a query on pay_attempts, creating the table first if an update has not been applied yet. */
function pa_q(string $sql, array $p = []): PDOStatement
{
    try { return q($sql, $p); }
    catch (PDOException $e) {
        if ($e->getCode() !== '42S02') throw $e;
        daraza_ensure_table();
        return q($sql, $p);
    }
}

/** +256XXXXXXXXX from what a guest typed, or null when it is not 9 digits after the country code. Daraza decides whether the number can be charged. */
function daraza_phone(string $in): ?string
{
    $d = preg_replace('/\D+/', '', $in);
    if (str_starts_with($d, '0')) $d = '256' . substr($d, 1);
    elseif (strlen($d) === 9) $d = '256' . $d;
    return preg_match('/^256\d{9}$/', $d) ? '+' . $d : null;
}
/** 'airtel' for Airtel prefixes, otherwise 'mtn'. Unusual numbers are sent as MTN and Daraza answers if they cannot be charged. */
function daraza_network(string $phone): string
{
    $p = substr(preg_replace('/\D+/', '', $phone), 3, 2);
    return in_array($p, ['70', '74', '75', '20'], true) ? 'airtel' : 'mtn';
}
function daraza_method(string $phone): int { return daraza_network($phone) === 'airtel' ? 2 : 1; }
const DARAZA_MIN = 500, DARAZA_MAX = 5000000;

/**
 * One call to Daraza. Returns [outcome, message, request_id] where outcome is
 *   success      Daraza confirmed the money moved
 *   failed       Daraza (or the network) said no, nothing moved
 *   unconfirmed  no clear answer (timeout, server error): money may have moved, a person must check
 */
function daraza_call(string $endpoint, int $method, int $amount, string $phone, string $note): array
{
    if (daraza_mock()) {
        sleep(4);
        $last = substr($phone, -1);
        $rid = 'MOCK' . random_int(10000, 99999);
        if ($last === '0') return ['failed', 'The customer declined the payment request.', $rid];
        if ($last === '1') return ['failed', 'Insufficient Account Balance', $rid];
        if ($last === '2') return ['unconfirmed', 'No answer from the payment service.', null];
        return ['success', 'Payment processed successfully', $rid];
    }
    $base = rtrim((string)env('DARAZA_BASE', 'https://daraza.net/api'), '/') . (env('DARAZA_SANDBOX', '0') === '1' ? '/sandbox' : '');
    $c = curl_init($base . '/' . $endpoint . '/');
    curl_setopt_array($c, [
        CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 20, CURLOPT_TIMEOUT => 230,
        CURLOPT_HTTPHEADER => ['Authorization: Api-Key ' . env('DARAZA_API_KEY', ''), 'Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_POSTFIELDS => json_encode(['method' => $method, 'amount' => $amount, 'phone' => $phone, 'note' => mb_substr($note, 0, 150)]),
    ]);
    $body = curl_exec($c);
    $errno = curl_errno($c);
    $http = (int)curl_getinfo($c, CURLINFO_HTTP_CODE);
    curl_close($c);
    if ($body === false) {
        // could not connect at all: nothing was sent. Any other failure (timeout) leaves the result unknown.
        if (in_array($errno, [CURLE_COULDNT_RESOLVE_HOST, CURLE_COULDNT_CONNECT, CURLE_SSL_CONNECT_ERROR], true)) return ['failed', 'The payment service could not be reached. Please try again in a few minutes.', null];
        return ['unconfirmed', 'No answer from the payment service.', null];
    }
    $j = json_decode((string)$body, true);
    if (!is_array($j)) return ['unconfirmed', 'Unreadable answer from the payment service (HTTP ' . $http . ').', null];
    $rid = (string)($j['request_id'] ?? $j['response_details']['request_id'] ?? '') ?: null;
    if (strcasecmp((string)($j['code'] ?? ''), 'Success') === 0) return ['success', (string)($j['message'] ?? 'Payment successful'), $rid];
    if (in_array($http, [401, 403, 404, 405], true)) { error_log('daraza: request refused (HTTP ' . $http . '): ' . mb_substr((string)($j['details'] ?? ''), 0, 200)); return ['failed', 'Online payments are temporarily unavailable. Please pay through WhatsApp or call us.', $rid]; }
    if ($http >= 500) return ['unconfirmed', 'The payment service had an error (HTTP ' . $http . ').', $rid];
    return ['failed', (string)($j['details'] ?? $j['message'] ?? 'The payment was not completed.'), $rid];
}

/* ---------- Payment channels (which route a payment came through) ---------- */
/** Make sure payments.channel exists on a database that was set up before it was added. */
function pay_ensure_schema(): void
{
    static $done = false;
    if ($done) return;
    $done = true;
    if (!(int)val("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payments' AND COLUMN_NAME = 'channel'")) {
        db()->exec("ALTER TABLE payments ADD COLUMN channel VARCHAR(20) NULL AFTER method");
        pay_backfill_channels();
    }
}
/** Label payments made through Daraza before the channel column existed, using the note that was saved with them. */
function pay_backfill_channels(): void
{
    q("UPDATE payments SET channel = 'daraza_refund' WHERE channel IS NULL AND note LIKE 'Refund sent via Daraza%'");
    q("UPDATE payments SET channel = 'daraza_airtel' WHERE channel IS NULL AND (note LIKE 'Paid online via Daraza from +25670%' OR note LIKE 'Paid online via Daraza from +25674%' OR note LIKE 'Paid online via Daraza from +25675%' OR note LIKE 'Paid online via Daraza from +25620%')");
    q("UPDATE payments SET channel = 'daraza_mtn' WHERE channel IS NULL AND note LIKE 'Paid online via Daraza from%'");
}
const PAY_CHANNELS = [
    'daraza_mtn' => ['MTN MoMo (online)', '#f5b800'], 'daraza_airtel' => ['Airtel Money (online)', '#e4002b'],
    'mobile_money' => ['Mobile money (staff recorded)', '#7b4fc4'], 'cash' => ['Cash', '#2e9d57'], 'bank' => ['Bank', '#2f6fd6'],
    'pesapal' => ['Card / Pesapal', '#0e8f8f'], 'refund' => ['Refunds (staff recorded)', '#8a8174'], 'daraza_refund' => ['Refunds (sent via Daraza)', '#b3261e'],
];

/* ---------- Attempts ---------- */
function daraza_start(int $reservationId, string $direction, int $amount, string $phone, ?int $userId = null): array
{
    $token = bin2hex(random_bytes(12));
    pa_q('INSERT INTO pay_attempts (token, reservation_id, direction, amount, phone, method, started_by) VALUES (?,?,?,?,?,?,?)',
        [$token, $reservationId, $direction, $amount, $phone, daraza_method($phone), $userId]);
    return row('SELECT * FROM pay_attempts WHERE token = ?', [$token]);
}
/** Attempts still waiting after 6 minutes had no answer: flag them for a person to check in Daraza. */
function daraza_expire_stale(): void
{
    pa_q("UPDATE pay_attempts SET status='unconfirmed', message='No answer from the payment service. Check your Daraza statement before trying again.', completed_at=NOW()
          WHERE status='pending' AND created_at < (NOW() - INTERVAL 6 MINUTE)");
}
function daraza_has_pending(int $reservationId, string $direction): bool
{
    daraza_expire_stale();
    return (bool)val("SELECT id FROM pay_attempts WHERE reservation_id = ? AND direction = ? AND status IN ('pending') LIMIT 1", [$reservationId, $direction]);
}

/** Do the call for an attempt and record the outcome. Safe to run once per attempt. */
function daraza_run(int $attemptId): void
{
    $a = row('SELECT a.*, r.reference FROM pay_attempts a JOIN reservations r ON r.id = a.reservation_id WHERE a.id = ?', [$attemptId]);
    if (!$a || $a['status'] !== 'pending') return;
    try {
        [$outcome, $msg, $rid] = daraza_call($a['direction'] === 'refund' ? 'remit' : 'request_to_pay', (int)$a['method'], (int)$a['amount'], (string)$a['phone'],
            ($a['direction'] === 'refund' ? 'Refund ' : 'Chill & Bite ') . $a['reference']);
    } catch (Throwable $e) {
        error_log('daraza: ' . $e->getMessage());
        [$outcome, $msg, $rid] = ['unconfirmed', 'Unexpected error while contacting the payment service.', null];
    }
    daraza_settle($attemptId, $outcome, $msg, $rid);
}
/** Record an outcome once. Late answers may still turn an "unconfirmed" attempt into success or failure. */
function daraza_settle(int $attemptId, string $outcome, string $msg, ?string $requestId = null, ?int $userId = null): bool
{
    pay_ensure_schema();
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $done = q("UPDATE pay_attempts SET status = ?, message = ?, request_id = COALESCE(?, request_id), completed_at = NOW() WHERE id = ? AND status IN ('pending','unconfirmed')",
            [$outcome, mb_substr($msg, 0, 255), $requestId, $attemptId])->rowCount();
        $a = row('SELECT * FROM pay_attempts WHERE id = ?', [$attemptId]);
        $confirmNow = false;
        if ($done === 1 && $outcome === 'success' && $a) {
            $refund = $a['direction'] === 'refund';
            q('INSERT INTO payments (reservation_id, amount, method, channel, note, recorded_by) VALUES (?,?,?,?,?,?)',
                [$a['reservation_id'], $refund ? -(int)$a['amount'] : (int)$a['amount'], $refund ? 'refund' : 'mobile_money', $refund ? 'daraza_refund' : 'daraza_' . daraza_network((string)$a['phone']),
                 mb_substr(($refund ? 'Refund sent via Daraza to ' : 'Paid online via Daraza from ') . $a['phone'] . ($a['request_id'] ? ' (ref ' . $a['request_id'] . ')' : ''), 0, 255), $userId ?? $a['started_by']]);
            if (!$refund) {
                $r = row('SELECT status, amount_due FROM reservations WHERE id = ?', [$a['reservation_id']]);
                $confirmNow = $r && $r['status'] === 'pending' && res_paid((int)$a['reservation_id']) >= (float)$r['amount_due'];
                if ($confirmNow) q("UPDATE reservations SET status='confirmed' WHERE id = ? AND status='pending'", [$a['reservation_id']]);
            }
        }
        $pdo->commit();
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
    if ($done === 1 && $outcome === 'success' && $a && $a['direction'] === 'collect') {
        // booked and paid: email the ticket (full payment) or a receipt (part payment)
        try { $confirmNow ? send_ticket((int)$a['reservation_id']) : daraza_receipt((int)$a['reservation_id'], (int)$a['amount']); }
        catch (Throwable $e) { error_log('daraza mail: ' . $e->getMessage()); }
    }
    return $done === 1;
}

/** Answer the browser now, then keep working after the visitor has their response. */
function daraza_respond_then(array $json, callable $work, ?string $redirect = null): void
{
    ignore_user_abort(true);
    @set_time_limit(300);
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    while (ob_get_level()) @ob_end_clean();
    if ($redirect !== null) { http_response_code(303); header('Location: ' . $redirect); $body = ''; }
    else { header('Content-Type: application/json'); header('Cache-Control: no-store'); $body = json_encode($json); }
    header('Content-Length: ' . strlen($body));
    header('Connection: close');
    echo $body;
    flush();
    if (function_exists('fastcgi_finish_request')) @fastcgi_finish_request();
    elseif (function_exists('litespeed_finish_request')) @litespeed_finish_request();
    $work();
}

/* ---------- Receipt for part payments ---------- */
function daraza_receipt(int $reservationId, int $amount): void
{
    $r = reservation_with_event($reservationId);
    if (!$r || empty($r['email'])) return;
    $left = max(0, (float)$r['amount_due'] - res_paid($reservationId));
    send_mail($r['email'], 'Payment received for ' . $r['reference'], "Hello {$r['full_name']},\n\nWe received your payment of " . money($amount) . " for {$r['title']}.\n"
        . ($left > 0 ? 'Balance still to pay: ' . money($left) . ". Your ticket is issued as soon as the booking is paid in full.\n" : '')
        . "\nTrack your booking any time: " . abs_url('booking/' . $r['reference']) . "\n");
}

/* ---------- Wallet balance and fees ---------- */
function daraza_get(string $path, ?array $post = null): ?array
{
    $c = curl_init(rtrim((string)env('DARAZA_BASE', 'https://daraza.net/api'), '/') . '/' . ltrim($path, '/'));
    $opt = [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 25,
        CURLOPT_HTTPHEADER => ['Authorization: Api-Key ' . env('DARAZA_API_KEY', ''), 'Content-Type: application/json', 'Accept: application/json']];
    if ($post !== null) { $opt[CURLOPT_POST] = true; $opt[CURLOPT_POSTFIELDS] = json_encode($post); }
    curl_setopt_array($c, $opt);
    $body = curl_exec($c); curl_close($c);
    $j = is_string($body) ? json_decode($body, true) : null;
    return is_array($j) ? $j : null;
}
/** Money available in the Daraza wallet: ['balance' => int|null, 'error' => string|null]. Cached for a minute. */
function daraza_wallet(bool $fresh = false): array
{
    if (daraza_mock()) return ['balance' => max(0, 2000000 - (int)val("SELECT COALESCE(SUM(amount),0) FROM daraza_withdrawals WHERE status = 'success'")), 'error' => null];
    if (!daraza_enabled()) return ['balance' => null, 'error' => 'Daraza is not set up.'];
    if (!$fresh && !empty($_SESSION['dz_wallet']) && $_SESSION['dz_wallet']['t'] > time() - 60) return $_SESSION['dz_wallet']['v'];
    $j = daraza_get('app_wallet/balance/');
    if (is_array($j) && strcasecmp((string)($j['code'] ?? ''), 'Success') === 0) {
        $v = ['balance' => (int)preg_replace('/\D+/', '', explode('.', (string)($j['details']['balance'] ?? '0'))[0]), 'error' => null];
    } else {
        $v = ['balance' => null, 'error' => is_array($j) ? (string)($j['details'] ?? $j['message'] ?? 'Could not read the wallet balance.') : 'Daraza could not be reached.'];
    }
    $_SESSION['dz_wallet'] = ['t' => time(), 'v' => $v];
    return $v;
}
/** Withdrawal fee for an amount: ['fees' => int, 'tax' => int, 'total' => int] or null. */
function daraza_fees(int $amount): ?array
{
    if (daraza_mock()) { $f = (int)round($amount * 0.07); $t = (int)round($amount * 0.005); return ['fees' => $f, 'tax' => $t, 'total' => $amount + $f + $t]; }
    $j = daraza_get('calculate_fees/', ['amount' => (string)$amount]);
    $d = is_array($j) ? ($j['data'] ?? null) : null;
    if (!is_array($d) || !isset($d['total_amount'])) return null;
    return ['fees' => (int)$d['withdrawal_fees'], 'tax' => (int)$d['tax_amount'], 'total' => (int)$d['total_amount']];
}

/* ---------- Withdrawals from the wallet to a mobile money number ---------- */
function daraza_ensure_withdrawals(): void
{
    db()->exec("CREATE TABLE IF NOT EXISTS daraza_withdrawals (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, amount INT UNSIGNED NOT NULL, phone VARCHAR(20) NOT NULL, method SMALLINT NOT NULL DEFAULT 1,
        status ENUM('pending','success','failed','unconfirmed') NOT NULL DEFAULT 'pending', request_id VARCHAR(80) NULL, message VARCHAR(255) NULL,
        user_id INT UNSIGNED NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, completed_at DATETIME NULL, KEY idx_dw_time (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
function dw_q(string $sql, array $p = []): PDOStatement
{
    try { return q($sql, $p); }
    catch (PDOException $e) { if ($e->getCode() !== '42S02') throw $e; daraza_ensure_withdrawals(); return q($sql, $p); }
}
function dw_expire_stale(): void
{
    dw_q("UPDATE daraza_withdrawals SET status='unconfirmed', message='No answer from Daraza. Check your Daraza statement before trying again.', completed_at=NOW() WHERE status='pending' AND created_at < (NOW() - INTERVAL 6 MINUTE)");
}
function dw_start(int $amount, string $phone, int $userId): int
{
    dw_q('INSERT INTO daraza_withdrawals (amount, phone, method, user_id) VALUES (?,?,?,?)', [$amount, $phone, daraza_method($phone), $userId]);
    return (int)db()->lastInsertId();
}
function dw_run(int $id): void
{
    $w = row('SELECT * FROM daraza_withdrawals WHERE id = ?', [$id]);
    if (!$w || $w['status'] !== 'pending') return;
    try { [$outcome, $msg, $rid] = daraza_call('remit', (int)$w['method'], (int)$w['amount'], (string)$w['phone'], 'Chill & Bite wallet withdrawal'); }
    catch (Throwable $e) { error_log('daraza withdraw: ' . $e->getMessage()); [$outcome, $msg, $rid] = ['unconfirmed', 'Unexpected error while contacting Daraza.', null]; }
    dw_settle($id, $outcome, $msg, $rid);
}
function dw_settle(int $id, string $outcome, string $msg, ?string $rid = null): bool
{
    unset($_SESSION['dz_wallet']);
    return q("UPDATE daraza_withdrawals SET status = ?, message = ?, request_id = COALESCE(?, request_id), completed_at = NOW() WHERE id = ? AND status IN ('pending','unconfirmed')",
        [$outcome, mb_substr($msg, 0, 255), $rid, $id])->rowCount() === 1;
}
