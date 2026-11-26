<?php
declare(strict_types=1);

/* ================= Adverts: flyers that run on the website, managed under Admin > Sponsors & ads ================= */

function adverts_ensure(): void
{
    static $done = false; if ($done) return; $done = true;
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS adverts (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(120) NOT NULL,
            subtitle VARCHAR(160) NOT NULL DEFAULT '',
            image VARCHAR(255) NOT NULL,
            link VARCHAR(255) NULL,
            show_home TINYINT(1) NOT NULL DEFAULT 1,
            show_pages TINYINT(1) NOT NULL DEFAULT 1,
            active TINYINT(1) NOT NULL DEFAULT 1,
            starts_on DATE NULL,
            ends_on DATE NULL,
            sort INT NOT NULL DEFAULT 0,
            clicks INT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_adverts_active (active, sort)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        advert_seed();
    } catch (PDOException $e) { error_log('adverts_ensure: ' . $e->getMessage()); }
}
/** The seven flyers of the 29 November edition become the first adverts (once). From then on the admin owns them. */
function advert_seed(): void
{
    if (setting('seed_adverts', '') === '1') return;
    $items = [
        'adv-01.jpg' => ['Official flyer', 'Table packages and rates'], 'adv-02.webp' => ['MC Kash Owakabi', 'Your host'], 'adv-03.webp' => ['DJ Joelfresh', 'On the decks'],
        'adv-04.webp' => ['DJ Shad', 'On the decks'], 'adv-05.webp' => ['DJ Big Val', 'On the decks'], 'adv-06.webp' => ['Shakira', 'Red Carpet'], 'adv-07.webp' => ['DJ Drey', 'On the decks'],
    ];
    $i = 0;
    foreach ($items as $f => [$t, $s]) {
        $file = 'img/advert/' . $f;
        if (is_file(ROOT . '/assets/' . $file) && !val('SELECT id FROM adverts WHERE image = ?', [$file])) q('INSERT INTO adverts (title, subtitle, image, sort) VALUES (?,?,?,?)', [$t, $s, $file, ++$i]);
    }
    try {   // the flyers used to live in a Gallery album; they are adverts now
        $album = (int)val("SELECT id FROM gallery_albums WHERE slug = 'headline-advert'");
        if ($album) { q('DELETE FROM gallery_images WHERE album_id = ?', [$album]); q('DELETE FROM gallery_albums WHERE id = ?', [$album]); }
    } catch (PDOException $e) { /* no gallery tables yet */ }
    save_setting('seed_adverts', '1');
}
function advert_state(array $a): string
{
    if (!(int)$a['active']) return 'off';
    $today = date('Y-m-d');
    if ($a['starts_on'] && $a['starts_on'] > $today) return 'scheduled';
    if ($a['ends_on'] && $a['ends_on'] < $today) return 'expired';
    return 'running';
}
/** Adverts that are running today for a place: 'home' (the headline carousel), 'pages' (the strip on inner pages) or 'all' (the Advertise page). */
function adverts_running(string $where = 'all', int $limit = 20): array
{
    adverts_ensure();
    $col = $where === 'home' ? ' AND show_home = 1' : ($where === 'pages' ? ' AND show_pages = 1' : '');
    try {
        $rows = rows("SELECT * FROM adverts WHERE active = 1 AND (starts_on IS NULL OR starts_on <= CURDATE()) AND (ends_on IS NULL OR ends_on >= CURDATE())$col ORDER BY sort, id LIMIT " . ($limit * 2));
    } catch (PDOException $e) { return []; }
    $rows = array_values(array_filter($rows, fn($r) => gallery_path((string)$r['image']) !== null));
    return array_slice($rows, 0, $limit);
}
/** Where a visitor goes when they click an advert: its link (counted) or its picture. */
function advert_href(array $a): string { return !empty($a['link']) ? url('ad/' . (int)$a['id']) : upload_url($a['image']); }

function page_advert_click(array $a): void
{
    adverts_ensure();
    $r = row('SELECT * FROM adverts WHERE id = ?', [(int)$a['id']]) ?? not_found();
    q('UPDATE adverts SET clicks = clicks + 1 WHERE id = ?', [$r['id']]);
    $to = (string)($r['link'] ?? '');
    if (!preg_match('#^https?://#i', $to)) $to = upload_url($r['image']);
    header('Location: ' . $to, true, 302);
    exit;
}

/* ---------- admin ---------- */
function admin_adverts_data(): array
{
    adverts_ensure();
    return array_map(fn($r) => $r + ['state' => advert_state($r), 'missing' => gallery_path((string)$r['image']) === null], rows('SELECT * FROM adverts ORDER BY sort, id'));
}
function admin_adverts_save(): void
{
    require_can('advertising');
    adverts_ensure();
    $act = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    $clean = function (): array {
        $link = post_str('link', 255);
        if ($link !== '' && !preg_match('#^https?://#i', $link)) { flash('The advert link must start with http:// or https://', 'err'); redirect('admin/advertising#adverts'); }
        $d = fn($k) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_POST[$k] ?? '')) ? $_POST[$k] : null;
        return ['title' => post_str('title', 120) ?: 'Advert', 'subtitle' => post_str('subtitle', 160), 'link' => $link ?: null, 'show_home' => empty($_POST['show_home']) ? 0 : 1,
                'show_pages' => empty($_POST['show_pages']) ? 0 : 1, 'active' => empty($_POST['active']) ? 0 : 1, 'starts_on' => $d('starts_on'), 'ends_on' => $d('ends_on'), 'sort' => (int)($_POST['sort'] ?? 0)];
    };
    if ($act === 'delete') {
        $img = (string)val('SELECT image FROM adverts WHERE id = ?', [$id]);
        q('DELETE FROM adverts WHERE id = ?', [$id]);
        if (preg_match('#^adverts/[a-f0-9]+\.(jpg|png|webp|gif)$#', $img)) @unlink(ROOT . '/uploads/' . $img);
        audit('advert.delete', '#' . $id); flash('Advert deleted.');
    } elseif ($act === 'toggle') {
        q('UPDATE adverts SET active = 1 - active WHERE id = ?', [$id]); flash('Updated.');
    } elseif ($act === 'edit') {
        $c = $clean();
        try { $img = save_upload('image', 'adverts'); } catch (RuntimeException $ex) { flash($ex->getMessage(), 'err'); redirect('admin/advertising#adverts'); }
        q('UPDATE adverts SET title=?, subtitle=?, link=?, show_home=?, show_pages=?, active=?, starts_on=?, ends_on=?, sort=?' . ($img ? ', image=?' : '') . ' WHERE id=?', [...array_values($c), ...($img ? [$img] : []), $id]);
        audit('advert.edit', '#' . $id); flash('Advert saved.');
    } else {   // add one or many flyers at once
        $c = $clean(); $n = 0;
        $files = $_FILES['images'] ?? null;
        if ($files && is_array($files['name'])) {
            foreach ($files['name'] as $i => $_) {
                $_FILES['one'] = ['name' => $files['name'][$i], 'type' => $files['type'][$i], 'tmp_name' => $files['tmp_name'][$i], 'error' => $files['error'][$i], 'size' => $files['size'][$i]];
                try {
                    if ($f = save_upload('one', 'adverts')) {
                        $title = $c['title'] !== 'Advert' || count($files['name']) === 1 ? $c['title'] : ucfirst(str_replace(['-', '_'], ' ', pathinfo((string)$files['name'][$i], PATHINFO_FILENAME)));
                        q('INSERT INTO adverts (title, subtitle, image, link, show_home, show_pages, active, starts_on, ends_on, sort) VALUES (?,?,?,?,?,?,?,?,?,?)', [$title, $c['subtitle'], $f, $c['link'], $c['show_home'], $c['show_pages'], 1, $c['starts_on'], $c['ends_on'], $c['sort'] + $n]);
                        $n++;
                    }
                } catch (RuntimeException $ex) { flash($files['name'][$i] . ': ' . $ex->getMessage(), 'err'); }
            }
        }
        if ($n) { audit('advert.create', "$n adverts"); flash("$n advert(s) added and running."); } elseif (!$files || !array_filter((array)$files['name'])) flash('Choose at least one flyer picture.', 'err');
    }
    redirect('admin/advertising#adverts');
}

/** Slides for the homepage headline carousel (same shape the carousel always used). */
function advert_slides(): array
{
    return array_map(fn($r) => ['id' => (int)$r['id'], 'file' => $r['image'], 'name' => $r['title'], 'sub' => $r['subtitle'], 'link' => (string)($r['link'] ?? '')], adverts_running('home', 12));
}
function page_advert_download(array $a): void
{
    adverts_ensure();
    if (!gallery_downloads_on()) not_found();
    $r = row('SELECT * FROM adverts WHERE id = ?', [(int)$a['id']]) ?? not_found();
    $path = gallery_path((string)$r['image']) ?? not_found();
    gallery_send($path, 'chill-and-bite-flyer-' . (int)$r['id']);
}