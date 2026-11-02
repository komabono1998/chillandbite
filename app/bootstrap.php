<?php
declare(strict_types=1);

define('ROOT', dirname(__DIR__));
date_default_timezone_set('Africa/Kampala');

/* ---------- Environment ---------- */
function env(string $key, $default = null)
{
    static $vars = null;
    if ($vars === null) {
        $vars = [];
        $file = ROOT . '/.env';
        if (is_file($file)) {
            foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) continue;
                [$k, $v] = explode('=', $line, 2);
                $vars[trim($k)] = trim($v, " \t\"'");
            }
        }
    }
    return $vars[$key] ?? $default;
}

define('DEBUG', env('APP_DEBUG', '0') === '1');
error_reporting(E_ALL);
ini_set('display_errors', DEBUG ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', ROOT . '/storage/logs/php-error.log');

$script = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
define('BASE', rtrim($script === '/' ? '' : $script, '/'));

/* ---------- Session ---------- */
if (PHP_SAPI !== 'cli') {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => $https, 'path' => BASE ?: '/']);
    session_name('cb_session');
    ini_set('session.use_strict_mode', '1');
    session_start();
}

/* ---------- Database ---------- */
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            env('DB_HOST', '127.0.0.1'), env('DB_PORT', '3306'), env('DB_NAME', 'chillandbite'));
        $pdo = new PDO($dsn, env('DB_USER', 'root'), env('DB_PASS', ''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec("SET time_zone = '+03:00'");
    }
    return $pdo;
}
function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}
function rows(string $sql, array $p = []): array { return q($sql, $p)->fetchAll(); }
function row(string $sql, array $p = []): ?array { $r = q($sql, $p)->fetch(); return $r ?: null; }
function val(string $sql, array $p = []) { $r = q($sql, $p)->fetchColumn(); return $r === false ? null : $r; }

/* ---------- Output helpers ---------- */
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function url(string $path = ''): string { return BASE . '/' . ltrim($path, '/'); }
function abs_url(string $path = ''): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . url($path);
}
function asset(string $path): string { return url('assets/' . ltrim($path, '/')); }
function upload_url(?string $file): string
{
    if (!$file) return '';
    // Bundled site photos live under assets/ and are referenced as "img/..."
    return str_starts_with($file, 'img/') ? asset($file) : url('uploads/' . $file);
}
function redirect(string $path): never
{
    header('Location: ' . (str_starts_with($path, 'http') ? $path : url($path)));
    exit;
}
function money($n): string { return 'UGX ' . number_format((float)$n); }
function fmt_date($d, string $f = 'D, j M Y'): string { return $d ? date($f, strtotime((string)$d)) : ''; }
function fmt_time($d): string { return $d ? date('g:i A', strtotime((string)$d)) : ''; }
function slugify(string $s): string
{
    $s = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $s), '-'));
    return $s !== '' ? $s : 'item';
}
function unique_slug(string $table, string $title, ?int $ignoreId = null): string
{
    $base = slugify($title); $slug = $base; $i = 2;
    while (val("SELECT id FROM `$table` WHERE slug = ? AND id <> ?", [$slug, $ignoreId ?? 0])) {
        $slug = $base . '-' . $i++;
    }
    return $slug;
}
function paragraphs(string $text): string
{
    $out = '';
    foreach (preg_split('/\R{2,}/', trim($text)) as $p) {
        if (trim($p) !== '') $out .= '<p>' . nl2br(h(trim($p))) . '</p>';
    }
    return $out;
}
function excerpt(string $text, int $len = 140): string
{
    $text = trim(preg_replace('/\s+/', ' ', $text));
    return mb_strlen($text) > $len ? mb_substr($text, 0, $len - 1) . '…' : $text;
}
function youtube_id(string $url): ?string
{
    if (preg_match('~(?:youtu\.be/|youtube(?:-nocookie)?\.com/(?:watch\?v=|embed/|shorts/))([A-Za-z0-9_-]{11})~', $url, $m)) return $m[1];
    return null;
}

/* ---------- Flash, old input, CSRF ---------- */
function flash(?string $msg = null, string $type = 'ok')
{
    if ($msg !== null) { $_SESSION['flash'][] = [$type, $msg]; return; }
    $f = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $f;
}
function old(string $k, $default = '') { return $_SESSION['old'][$k] ?? $default; }
function keep_old(array $data): void { $_SESSION['old'] = $data; }
function clear_old(): void { unset($_SESSION['old']); }

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="' . h(csrf_token()) . '">'; }
function csrf_verify(): void
{
    $sent = $_POST['_csrf'] ?? '';
    $known = $_SESSION['csrf'] ?? '';
    if (!is_string($sent) || $sent === '' || $known === '' || !hash_equals($known, $sent)) {
        http_response_code(403);
        exit('Your session expired. Please go back, refresh the page and try again.');
    }
}

/* ---------- Settings ---------- */
function setting(string $key, string $default = ''): string
{
    static $all = null;
    if ($all === null) {
        try { $all = array_column(rows('SELECT `key`, `value` FROM settings'), 'value', 'key'); }
        catch (Throwable $e) { $all = []; }
    }
    return $all[$key] ?? $default;
}
function save_setting(string $key, string $value): void
{
    q('INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)', [$key, $value]);
}

/* ---------- Auth & roles ---------- */
const ROLES = [
    'admin' => 'System Administrator',
    'event_manager' => 'Event Manager',
    'editor' => 'Content Editor',
    'marketing' => 'Marketing & Advertising Manager',
];
/** module => roles allowed (admin is always allowed). Enforced server-side. */
const PERMS = [
    'dashboard'    => ['event_manager', 'editor', 'marketing'],
    'events'       => ['event_manager'],
    'reservations' => ['event_manager'],
    'news'         => ['editor'],
    'comedy'       => ['editor'],
    'gallery'      => ['editor'],
    'advertising'  => ['marketing'],
    'inquiries'    => ['event_manager', 'editor', 'marketing'],
    'users'        => [],
    'settings'     => [],
    'audit'        => [],
];
function current_user(): ?array
{
    static $u = false;
    if ($u === false) {
        $u = null;
        if (!empty($_SESSION['uid'])) {
            $u = row('SELECT id, name, email, role FROM users WHERE id = ? AND active = 1', [$_SESSION['uid']]);
            if (!$u) unset($_SESSION['uid']);
        }
    }
    return $u;
}
function can(string $module): bool
{
    $u = current_user();
    if (!$u) return false;
    return $u['role'] === 'admin' || in_array($u['role'], PERMS[$module] ?? [], true);
}
function require_can(string $module): void
{
    if (!current_user()) {
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') $_SESSION['next'] = $_SERVER['REQUEST_URI'] ?? ''; // return here after login (e.g. scanned ticket)
        redirect('admin/login');
    }
    if (!can($module)) { http_response_code(403); render('admin/forbidden', [], 'admin'); exit; }
}
function audit(string $action, string $detail = ''): void
{
    $u = current_user();
    q('INSERT INTO audit_log (user_id, action, detail, ip) VALUES (?,?,?,?)',
        [$u['id'] ?? null, $action, mb_substr($detail, 0, 500), client_ip()]);
}
function client_ip(): string { return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'; }

/* ---------- Rate limiting ---------- */
/** Returns true when the caller has exceeded $max hits in $seconds. Records the hit otherwise. */
function rate_limited(string $bucket, int $max, int $seconds): bool
{
    $key = $bucket . ':' . client_ip();
    q('DELETE FROM rate_limits WHERE created_at < (NOW() - INTERVAL 1 DAY)');
    $n = (int)val('SELECT COUNT(*) FROM rate_limits WHERE k = ? AND created_at > (NOW() - INTERVAL ? SECOND)', [$key, $seconds]);
    if ($n >= $max) return true;
    q('INSERT INTO rate_limits (k) VALUES (?)', [$key]);
    return false;
}

/* ---------- Uploads ---------- */
function save_upload(string $field, string $dir = ''): ?string
{
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) return null;
    $f = $_FILES[$field];
    if ($f['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('Upload failed. Please try again.');
    if ($f['size'] > 5 * 1024 * 1024) throw new RuntimeException('Images must be 5MB or smaller.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'][$mime] ?? null;
    if (!$ext) throw new RuntimeException('Only JPG, PNG, WebP or GIF images are allowed.');
    $sub = trim($dir, '/') ?: 'misc';
    $targetDir = ROOT . '/uploads/' . $sub;
    if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);
    $name = bin2hex(random_bytes(10)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], $targetDir . '/' . $name)) throw new RuntimeException('Could not save the upload.');
    return $sub . '/' . $name;
}

/* ---------- Mail (logs to storage when MAIL_ENABLED=0) ---------- */
function send_mail(string $to, string $subject, string $body): bool
{
    if ($to === '') return false;
    $from = env('MAIL_FROM', 'no-reply@chillandbite.local');
    if (env('MAIL_ENABLED', '0') !== '1') {
        file_put_contents(ROOT . '/storage/logs/mail.log',
            "[" . date('c') . "] To: $to\nSubject: $subject\n$body\n---\n", FILE_APPEND);
        return true;
    }
    $headers = "From: Chill & Bite MEATINGS <$from>\r\nContent-Type: text/plain; charset=UTF-8";
    return @mail($to, $subject, $body, $headers);
}

/* ---------- Rendering ---------- */
require_once ROOT . '/app/views/partials.php';
function render(string $__view, array $__data = [], string $__layout = 'site'): void
{
    // Underscore-prefixed names so view variables like $view or $data are never shadowed.
    extract($__data, EXTR_SKIP);
    ob_start();
    require ROOT . '/app/views/' . $__view . '.php';
    $content = ob_get_clean();
    require ROOT . '/app/views/layout_' . $__layout . '.php';
}
function not_found(): never
{
    http_response_code(404);
    render('site/404', ['title' => 'Page not found']);
    exit;
}

/* ---------- Domain helpers ---------- */
function first_sunday(int $year, int $month): DateTimeImmutable
{
    $d = new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
    return $d->modify('first sunday of this month');
}
function next_first_sunday(?DateTimeImmutable $from = null): DateTimeImmutable
{
    $from = $from ?? new DateTimeImmutable('today 12:00');
    $c = first_sunday((int)$from->format('Y'), (int)$from->format('n'))->setTime(12, 0);
    if ($c < $from) { $n = $from->modify('first day of next month'); $c = first_sunday((int)$n->format('Y'), (int)$n->format('n'))->setTime(12, 0); }
    return $c;
}
function event_booked(int $eventId): int
{
    return (int)val("SELECT COALESCE(SUM(guests),0) FROM reservations WHERE event_id = ? AND status IN ('pending','confirmed')", [$eventId]);
}
function event_remaining(array $e): ?int
{
    if (empty($e['capacity'])) return null;
    return max(0, (int)$e['capacity'] - event_booked((int)$e['id']));
}
function event_open_for_booking(array $e): bool
{
    if ($e['status'] !== 'published') return false;
    $now = time();
    if (strtotime($e['end_at'] ?: $e['start_at']) < $now) return false;
    if ($e['reservation_opens'] && strtotime($e['reservation_opens']) > $now) return false;
    if ($e['reservation_closes'] && strtotime($e['reservation_closes']) < $now) return false;
    $rem = event_remaining($e);
    return $rem === null || $rem > 0;
}
const RES_TYPES = [
    'individual' => 'Individual Reservation',
    'couple'     => 'Couple Reservation',
    'group'      => 'Group Reservation',
    'corporate'  => 'Corporate Reservation',
    'vip'        => 'VIP Reservation',
    'table'      => 'Table Reservation',
];
const RES_TYPE_HINTS = [
    'individual' => 'One person attending alone.',
    'couple'     => 'Two guests attending together.',
    'group'      => 'Friends or colleagues, 3 or more.',
    'corporate'  => 'Book places for staff, clients or partners.',
    'vip'        => 'Premium seating and hospitality.',
    'table'      => 'A table for a specified number of people.',
];
const NEWS_LABELS = [
    'news' => 'Verified News', 'opinion' => 'Opinion', 'gossip' => 'Entertainment Gossip',
    'unconfirmed' => 'Unconfirmed Report', 'sponsored' => 'Sponsored',
];
function social_links(): array
{
    return array_filter([
        'Facebook' => setting('social_facebook'), 'Instagram' => setting('social_instagram'),
        'YouTube' => setting('social_youtube'), 'TikTok' => setting('social_tiktok'),
        'X' => setting('social_x'),
    ]);
}

/** WhatsApp click-to-chat link; local numbers starting with 0 get Uganda's 256 prefix. */
function wa_link(string $number, string $text = ''): string
{
    $d = preg_replace('/\D+/', '', $number);
    if (str_starts_with($d, '0')) $d = '256' . substr($d, 1);
    return 'https://wa.me/' . $d . ($text !== '' ? '?text=' . rawurlencode($text) : '');
}

/** Asset URL with a modification-time version so browsers refetch CSS/JS whenever it changes. */
function asset_v(string $path): string
{
    $file = ROOT . '/assets/' . ltrim($path, '/');
    return asset($path) . '?v=' . (is_file($file) ? filemtime($file) : 1);
}
