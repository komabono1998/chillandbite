<?php
declare(strict_types=1);

/*
 * One-time web setup for hosts without SSH.
 * Works only when .env contains SETUP_TOKEN (16+ characters), the address carries the same key, and no admin exists yet.
 * After setup, remove SETUP_TOKEN from .env.
 */
function setup_page(string $title, string $body, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>' . h($title) . '</title>'
        . '<style>body{margin:0;font-family:system-ui,Segoe UI,Arial,sans-serif;background:#171717;color:#fff7eb;line-height:1.55}main{max-width:640px;margin:6vh auto;padding:0 18px}'
        . '.card{background:#fff;color:#171717;border-radius:18px;padding:28px;box-shadow:0 20px 60px rgba(0,0,0,.45)}h1{margin:0 0 8px;font-size:1.6rem}'
        . '.ok{background:#e6f6ea;color:#16632c;padding:10px 14px;border-radius:10px;font-weight:600}.bad{background:#fde7e7;color:#a11;padding:10px 14px;border-radius:10px;font-weight:600}'
        . 'button,.btn{display:inline-block;border:0;background:#d96b27;color:#fff;font:700 1rem inherit;padding:14px 24px;border-radius:12px;cursor:pointer;text-decoration:none}'
        . 'pre{background:#171717;color:#e9dfcf;padding:14px;border-radius:12px;overflow:auto;font-size:.85rem;white-space:pre-wrap}code{background:#f1e9da;padding:2px 6px;border-radius:6px}</style></head><body><main><div class="card">'
        . $body . '</div></main></body></html>';
    exit;
}
/** Returns the key when the request is allowed to use setup; otherwise stops with a page. */
function setup_guard(): string
{
    $token = (string)env('SETUP_TOKEN', '');
    if (strlen($token) < 16) setup_page('Setup is off', '<h1>Setup is switched off</h1><p>To use it, add a line <code>SETUP_TOKEN=</code> followed by a random text of at least 16 letters and numbers to your <code>.env</code> file, then open <code>/setup?key=THAT_TEXT</code>.</p>', 403);
    $key = (string)($_POST['key'] ?? $_GET['key'] ?? '');
    if (!hash_equals($token, $key)) { sleep(1); setup_page('Not found', '<h1>Page not found</h1>', 404); }   // plain page: the site layout needs the database
    return $key;
}
function setup_installed(): bool
{
    try { return (int)val('SELECT COUNT(*) FROM users') > 0; } catch (Throwable $e) { return false; }
}
function page_setup(): void
{
    $key = setup_guard();
    if (setup_installed()) setup_page('Already set up', '<h1>Setup is already finished</h1><p class="ok">The website is installed and an administrator exists.</p><p>For safety, open your <code>.env</code> file and <b>delete the SETUP_TOKEN line</b>. Then <a href="' . h(url('admin')) . '">sign in to the admin</a>.</p>');
    $status = '<p class="ok">✔ Connected to the database <code>' . h((string)env('DB_NAME', '')) . '</code>.</p>';
    try { db()->query('SELECT 1'); }
    catch (Throwable $e) { $status = '<p class="bad">✖ Could not connect to the database.</p><pre>' . h($e->getMessage()) . '</pre><p>Check <code>DB_HOST</code>, <code>DB_NAME</code>, <code>DB_USER</code> and <code>DB_PASS</code> in your <code>.env</code> file, then reload this page.</p>'; }
    $admin = (string)env('ADMIN_EMAIL', '') !== '' && strlen((string)env('ADMIN_PASSWORD', '')) >= 10
        ? '<p class="ok">✔ Your administrator will be <b>' . h((string)env('ADMIN_EMAIL')) . '</b> with the password from your <code>.env</code> file.</p>'
        : '<p class="bad">ADMIN_EMAIL and ADMIN_PASSWORD (10+ characters) are not both set in <code>.env</code>. Setup will generate a password and show it once.</p>';
    $form = str_contains($status, 'bad') ? '' : '<form method="post" action="' . h(url('setup')) . '">' . csrf_field() . '<input type="hidden" name="key" value="' . h($key) . '"><button>Run setup now</button></form>';
    setup_page('Website setup', '<h1>Chill &amp; Bite setup</h1><p>This creates all the tables and your administrator account. It takes a few seconds and is safe to run once.</p>' . $status . $admin . $form);
}
function post_setup(): void
{
    $key = setup_guard();
    if (setup_installed()) setup_page('Already set up', '<h1>Setup is already finished</h1><p>Delete the SETUP_TOKEN line from your <code>.env</code> file.</p>');
    @set_time_limit(180);
    define('WEB_SETUP', true);
    $argv = [];
    ob_start();
    try { include ROOT . '/database/install.php'; $out = (string)ob_get_clean(); $failed = false; }
    catch (Throwable $e) { $out = (string)ob_get_clean() . "\n" . $e->getMessage(); $failed = true; }
    if ($failed) setup_page('Setup failed', '<h1>Setup could not finish</h1><p class="bad">Something went wrong. Nothing harmful happened; fix the problem below and run it again.</p><pre>' . h($out) . '</pre><p><a class="btn" href="' . h(url('setup?key=' . rawurlencode($key))) . '">Try again</a></p>', 500);
    setup_page('Setup finished', '<h1>Setup finished ✔</h1><p class="ok">The tables and your administrator account are ready.</p><pre>' . h($out) . '</pre>'
        . '<p><b>Now, for safety:</b> open your <code>.env</code> file and <b>delete the SETUP_TOKEN line and the ADMIN_PASSWORD line</b>.</p><p><a class="btn" href="' . h(url('admin')) . '">Go to the admin sign-in</a></p>');
}
