<?php
declare(strict_types=1);

/* ================= Gallery: albums, public downloads, admin control ================= */

/** Add the hero/hidden columns to a site set up before they existed. */
function gallery_ensure_cols(): void
{
    static $done = false; if ($done) return; $done = true;
    try {
        if (!(int)val("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gallery_images' AND COLUMN_NAME = 'hero'"))
            db()->exec('ALTER TABLE gallery_images ADD COLUMN hero TINYINT(1) NOT NULL DEFAULT 0 AFTER featured, ADD COLUMN hidden TINYINT(1) NOT NULL DEFAULT 0 AFTER hero');
    } catch (PDOException $e) { error_log('gallery_ensure_cols: ' . $e->getMessage()); }
}
/** Small copy (900 px) of an uploaded photo so the website grid loads fast; the full photo is kept for the viewer and downloads. Needs the GD extension, otherwise the full photo is used. */
function gallery_thumb(string $file): string
{
    if (!str_starts_with($file, 'gallery/')) return $file;
    $t = 'gallery/t/' . pathinfo($file, PATHINFO_FILENAME) . '.jpg';
    return is_file(ROOT . '/uploads/' . $t) ? $t : $file;
}
function gallery_make_thumb(string $file): void
{
    if (!function_exists('imagecreatetruecolor') || !str_starts_with($file, 'gallery/')) return;
    $src = ROOT . '/uploads/' . $file; if (!is_file($src)) return;
    try {
        $info = @getimagesize($src); if (!$info) return;
        [$w, $h] = $info; if ($w * $h > 90000000) return;                       // skip absurdly large images, GD would run out of memory
        @ini_set('memory_limit', '512M');
        $im = match ($info[2]) { IMAGETYPE_JPEG => @imagecreatefromjpeg($src), IMAGETYPE_PNG => @imagecreatefrompng($src), IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($src) : false, IMAGETYPE_GIF => @imagecreatefromgif($src), default => false };
        if (!$im) return;
        if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data') && ($ex = @exif_read_data($src)) && !empty($ex['Orientation'])) {
            $rot = [3 => 180, 6 => -90, 8 => 90][(int)$ex['Orientation']] ?? 0;
            if ($rot && ($r = imagerotate($im, $rot, 0))) { $im = $r; [$w, $h] = [imagesx($im), imagesy($im)]; }
        }
        $max = 900; $s = min(1, $max / max($w, $h));
        $nw = max(1, (int)round($w * $s)); $nh = max(1, (int)round($h * $s));
        $out = imagecreatetruecolor($nw, $nh); imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
        imagecopyresampled($out, $im, 0, 0, 0, 0, $nw, $nh, imagesx($im), imagesy($im));
        $dir = ROOT . '/uploads/gallery/t'; if (!is_dir($dir)) mkdir($dir, 0755, true);
        imagejpeg($out, $dir . '/' . pathinfo($file, PATHINFO_FILENAME) . '.jpg', 82);
    } catch (Throwable $e) { error_log('gallery_make_thumb: ' . $e->getMessage()); }
}
/** Keep only the photos whose file is really on the server (an uploads folder can be lost in a redeploy or a restore). */
function gallery_existing(array $rows): array
{
    return array_values(array_filter($rows, fn($r) => gallery_path((string)$r['file']) !== null));
}
function gallery_downloads_on(): bool { return setting('gallery_downloads', '1') === '1'; }

/** Real file on disk for a stored gallery file name (bundled "img/..." or an upload). */
function gallery_path(string $file): ?string
{
    if (str_contains($file, '..')) return null;
    $p = str_starts_with($file, 'img/') ? ROOT . '/assets/' . $file : ROOT . '/uploads/' . $file;
    return is_file($p) ? $p : null;
}
/** The last edition's photos ship with the website; this adds them to the gallery once (admin keeps full control afterwards). */
function gallery_seed_last_edition(): int
{
    gallery_ensure_cols();
    $caps = ['Tables set up before the guests arrive', 'The Keep Walking entrance arch', 'The venue ready under a blue sky', 'Dressed in white at the photo wall', 'Posing with the golden Johnnie Walker statue',
             'Style meets the golden statue', 'Selfie time with friends', 'Friends strike a pose', 'Smiles from the crowd', 'Golden-hour selfie under the lights', 'The Blue Label moment at the photo wall',
             'The DJ stage at dusk', 'Dancing into the night', 'Cool and relaxed', 'Bottle service with the Johnnie Walker ring light', 'Good laughs', 'Good company', 'The DJ stage lit up',
             'The cocktail bar', 'Bottles up for the toast'];
    $albumId = val("SELECT id FROM gallery_albums WHERE slug = 'last-edition'");
    if (!$albumId) { q("INSERT INTO gallery_albums (name, slug) VALUES ('Last edition - 1st Anniversary', 'last-edition')"); $albumId = (int)val('SELECT LAST_INSERT_ID()'); }
    $added = 0;
    foreach ($caps as $i => $cap) {
        $file = sprintf('img/last-edition/le-%02d.jpg', $i + 1);
        if (!is_file(ROOT . '/assets/' . $file) || val('SELECT id FROM gallery_images WHERE file = ?', [$file])) continue;
        $n = $i + 1;
        q('INSERT INTO gallery_images (album_id, file, caption, featured, hero) VALUES (?,?,?,?,?)', [$albumId, $file, $cap, in_array($n, [3, 10, 12, 15, 18, 20], true) ? 1 : 0, in_array($n, [3, 10, 12, 18, 20], true) ? 1 : 0]);
        $added++;
    }
    return $added;
}

/** The seven flyers of the 29 November edition become the "Headline advert" album (once). Afterwards the admin owns it: add, hide, delete or caption flyers under Gallery. */
function advert_seed(): void
{
    if (setting('seed_advert', '') === '1') return;
    try {
        gallery_ensure_cols();
        $items = [
            'adv-01.jpg' => 'Official flyer - Table packages and rates', 'adv-02.webp' => 'MC Kash Owakabi - Your host', 'adv-03.webp' => 'DJ Joelfresh - On the decks',
            'adv-04.webp' => 'DJ Shad - On the decks', 'adv-05.webp' => 'DJ Big Val - On the decks', 'adv-06.webp' => 'Shakira - Red Carpet', 'adv-07.webp' => 'DJ Drey - On the decks',
        ];
        $album = (int)val("SELECT id FROM gallery_albums WHERE slug = 'headline-advert'");
        if (!$album) { q("INSERT INTO gallery_albums (name, slug) VALUES ('Headline advert (homepage)', 'headline-advert')"); $album = (int)val('SELECT LAST_INSERT_ID()'); }
        foreach ($items as $f => $cap) {
            $file = 'img/advert/' . $f;
            if (is_file(ROOT . '/assets/' . $file) && !val('SELECT id FROM gallery_images WHERE file = ?', [$file])) q('INSERT INTO gallery_images (album_id, file, caption) VALUES (?,?,?)', [$album, $file, $cap]);
        }
        save_setting('seed_advert', '1');
    } catch (Throwable $e) { error_log('advert_seed: ' . $e->getMessage()); }
}
/** Flyers for the running headline advert on the homepage, in the order they were added. */
function advert_slides(): array
{
    gallery_ensure_cols();
    $rows = gallery_existing(rows("SELECT i.id, i.file, i.caption FROM gallery_images i JOIN gallery_albums a ON a.id = i.album_id WHERE a.slug = 'headline-advert' AND i.hidden = 0 ORDER BY i.id ASC LIMIT 12"));
    foreach ($rows as &$r) { $p = explode(' - ', (string)$r['caption'], 2); $r['name'] = trim($p[0]) ?: 'Chill & Bite MEATINGS'; $r['sub'] = trim($p[1] ?? ''); }
    return $rows;
}

/** Albums with the number of photos that can really be shown. */
function gallery_album_counts(): array
{
    $count = [];
    foreach (gallery_existing(rows('SELECT album_id, file FROM gallery_images WHERE hidden = 0')) as $r) $count[(int)$r['album_id']] = ($count[(int)$r['album_id']] ?? 0) + 1;
    $albums = rows('SELECT * FROM gallery_albums ORDER BY id DESC');
    foreach ($albums as &$a) $a['n'] = $count[(int)$a['id']] ?? 0;
    return $albums;
}

/* ---------- public ---------- */
function page_gallery(): void
{
    gallery_ensure_cols();
    $album = preg_match('/^[a-z0-9-]+$/', $_GET['album'] ?? '') ? $_GET['album'] : null;
    $sql = 'SELECT i.*, a.name AS album FROM gallery_images i LEFT JOIN gallery_albums a ON a.id = i.album_id WHERE i.hidden = 0';
    $p = [];
    if ($album) { $sql .= ' AND a.slug = ?'; $p[] = $album; }
    render('site/gallery', [
        'title' => 'Photo Gallery | Chill & Bite MEATINGS Events in Gulu',
        'desc' => 'Photos from Chill & Bite MEATINGS and Laugh with Owakabi events in Gulu City, Uganda. Browse every edition and download your favourites.', 'album' => $album,
        'images' => gallery_existing(rows($sql . ' ORDER BY i.featured DESC, i.id ASC', $p)),
        'albums' => gallery_album_counts(),
        'downloads' => gallery_downloads_on(), 'zip' => class_exists('ZipArchive'),
    ]);
}
function gallery_send(string $path, string $name): never
{
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    header('Content-Type: ' . (['png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif'][$ext] ?? 'image/jpeg'));
    header('Content-Length: ' . filesize($path));
    header('Content-Disposition: attachment; filename="' . $name . '.' . $ext . '"');
    header('X-Content-Type-Options: nosniff');
    readfile($path);
    exit;
}
function page_gallery_download(array $a): void
{
    gallery_ensure_cols();
    if (!gallery_downloads_on()) not_found();
    $r = row('SELECT i.*, a.slug AS aslug FROM gallery_images i LEFT JOIN gallery_albums a ON a.id = i.album_id WHERE i.id = ? AND i.hidden = 0', [(int)$a['id']]) ?? not_found();
    $path = gallery_path((string)$r['file']) ?? not_found();
    gallery_send($path, 'chill-and-bite-' . ($r['aslug'] ?: 'photo') . '-' . (int)$r['id']);
}
function page_gallery_zip(): void
{
    gallery_ensure_cols();
    if (!gallery_downloads_on() || !class_exists('ZipArchive')) not_found();
    @set_time_limit(120);
    $album = preg_match('/^[a-z0-9-]+$/', $_GET['album'] ?? '') ? $_GET['album'] : '';
    $sql = 'SELECT i.id, i.file FROM gallery_images i LEFT JOIN gallery_albums a ON a.id = i.album_id WHERE i.hidden = 0' . ($album ? ' AND a.slug = ?' : '') . ' ORDER BY i.id LIMIT 200';
    $list = rows($sql, $album ? [$album] : []);
    if (!$list) not_found();
    $tmp = tempnam(sys_get_temp_dir(), 'cbz');
    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) not_found();
    $n = 0;
    foreach ($list as $r) if ($p = gallery_path((string)$r['file'])) { $zip->addFile($p, sprintf('chill-and-bite-%s-%03d.%s', $album ?: 'photos', ++$n, strtolower(pathinfo($p, PATHINFO_EXTENSION)))); }
    $zip->close();
    header('Content-Type: application/zip');
    header('Content-Length: ' . filesize($tmp));
    header('Content-Disposition: attachment; filename="chill-and-bite-' . ($album ?: 'photos') . '.zip"');
    readfile($tmp); @unlink($tmp);
    exit;
}

/* ---------- admin ---------- */
function admin_gallery(): void
{
    require_can('gallery');
    gallery_ensure_cols();
    $album = (int)($_GET['album'] ?? 0);
    $where = $album > 0 ? 'WHERE i.album_id = ' . $album : ($album === -1 ? 'WHERE i.album_id IS NULL' : '');
    admin_view('gallery', [
        'title' => 'Gallery', 'filter' => $album, 'downloads' => gallery_downloads_on(),
        'images' => array_map(fn($r) => $r + ['missing' => gallery_path((string)$r['file']) === null], rows("SELECT i.*, a.name AS album FROM gallery_images i LEFT JOIN gallery_albums a ON a.id=i.album_id $where ORDER BY i.id DESC LIMIT 500")),
        'albums' => rows('SELECT a.*, (SELECT COUNT(*) FROM gallery_images i WHERE i.album_id = a.id) AS n FROM gallery_albums a ORDER BY a.id'),
        'total' => (int)val('SELECT COUNT(*) FROM gallery_images'),
        'maxmb' => upload_max_mb(), 'ini' => ['upload' => ini_get('upload_max_filesize'), 'post' => ini_get('post_max_size'), 'files' => (int)ini_get('max_file_uploads'), 'gd' => function_exists('imagecreatetruecolor')],
    ]);
}
function admin_gallery_save(): void
{
    require_can('gallery');
    gallery_ensure_cols();
    if (!empty($_POST['one']) && preg_match('/^(feature|hero|hidden|delete):(\d+)$/', (string)$_POST['one'], $m)) { $_POST['action'] = $m[1]; $_POST['id'] = $m[2]; }   // per-photo buttons
    $act = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    $back = 'admin/gallery' . (!empty($_POST['filter']) ? '?album=' . (int)$_POST['filter'] : '');
    if ($act === 'delete') {
        $f = (string)val('SELECT file FROM gallery_images WHERE id = ?', [$id]);
        q('DELETE FROM gallery_images WHERE id = ?', [$id]);
        if ($f && preg_match('#^gallery/[a-f0-9]+\.(jpg|png|webp|gif)$#', $f)) @unlink(ROOT . '/uploads/' . $f);
        audit('gallery.delete', '#' . $id); flash('Photo deleted.');
    } elseif (in_array($act, ['feature', 'hero', 'hidden'], true)) {
        q("UPDATE gallery_images SET $act = 1 - $act WHERE id = ?", [$id]); flash('Updated.');
    } elseif ($act === 'edit') {
        q('UPDATE gallery_images SET caption = ?, album_id = ? WHERE id = ?', [post_str('caption', 190) ?: null, (int)($_POST['album_id'] ?? 0) ?: null, $id]);
        audit('gallery.edit', '#' . $id); flash('Photo saved.');
    } elseif ($act === 'bulk') {
        $ids = array_values(array_filter(array_map('intval', (array)($_POST['ids'] ?? []))));
        $do = $_POST['bulk'] ?? '';
        if (!$ids) flash('Tick at least one photo first.', 'err');
        elseif ($do === 'delete') {
            foreach ($ids as $i) { $f = (string)val('SELECT file FROM gallery_images WHERE id = ?', [$i]); q('DELETE FROM gallery_images WHERE id = ?', [$i]); if ($f && preg_match('#^gallery/[a-f0-9]+\.(jpg|png|webp|gif)$#', $f)) @unlink(ROOT . '/uploads/' . $f); }
            audit('gallery.bulk_delete', count($ids) . ' photos'); flash(count($ids) . ' photo(s) deleted.');
        } elseif (in_array($do, ['hide', 'show'], true)) { foreach ($ids as $i) q('UPDATE gallery_images SET hidden = ? WHERE id = ?', [$do === 'hide' ? 1 : 0, $i]); flash(count($ids) . ' photo(s) updated.'); }
        elseif (str_starts_with((string)$do, 'move:')) { $to = (int)substr($do, 5) ?: null; foreach ($ids as $i) q('UPDATE gallery_images SET album_id = ? WHERE id = ?', [$to, $i]); flash(count($ids) . ' photo(s) moved.'); }
    } elseif ($act === 'purge_missing') {
        $n = 0;
        foreach (rows('SELECT id, file FROM gallery_images') as $r) if (gallery_path((string)$r['file']) === null) { q('DELETE FROM gallery_images WHERE id = ?', [$r['id']]); $n++; }
        audit('gallery.purge_missing', "$n records"); flash($n ? "$n photo record(s) with a missing file were removed." : 'Every photo file is in place.');
    } elseif ($act === 'album_add') {
        $name = post_str('name', 120);
        if ($name === '') flash('Give the album a name.', 'err'); else { q('INSERT INTO gallery_albums (name, slug) VALUES (?,?)', [$name, unique_slug('gallery_albums', $name)]); audit('gallery.album_add', $name); flash('Album created.'); }
    } elseif ($act === 'album_rename') {
        $name = post_str('name', 120);
        if ($name !== '') { q('UPDATE gallery_albums SET name = ? WHERE id = ?', [$name, $id]); flash('Album renamed.'); }
    } elseif ($act === 'album_delete') {
        q('UPDATE gallery_images SET album_id = NULL WHERE album_id = ?', [$id]); q('DELETE FROM gallery_albums WHERE id = ?', [$id]);
        audit('gallery.album_delete', '#' . $id); flash('Album deleted. Its photos are kept without an album.'); $back = 'admin/gallery';
    } elseif ($act === 'settings') {
        save_setting('gallery_downloads', empty($_POST['gallery_downloads']) ? '0' : '1');
        save_setting('upload_max_mb', (string)max(1, min(100, (int)($_POST['upload_max_mb'] ?? 10))));
        flash('Gallery settings saved.');
    } else {
        $n = 0; $errs = [];
        $album = (int)($_POST['album_id'] ?? 0) ?: null;
        $files = $_FILES['photos'] ?? null;
        if ($files && is_array($files['name'])) {
            foreach ($files['name'] as $i => $_) {
                $_FILES['one'] = ['name' => $files['name'][$i], 'type' => $files['type'][$i], 'tmp_name' => $files['tmp_name'][$i], 'error' => $files['error'][$i], 'size' => $files['size'][$i]];
                try { if ($f = save_upload('one', 'gallery')) { q('INSERT INTO gallery_images (album_id, file, caption) VALUES (?,?,?)', [$album, $f, post_str('caption', 190) ?: null]); gallery_make_thumb($f); $n++; } }
                catch (RuntimeException $ex) { $errs[] = $files['name'][$i] . ': ' . $ex->getMessage(); }
            }
        }
        if (!empty($_POST['ajax'])) { header('Content-Type: application/json'); echo json_encode(['ok' => $n, 'errors' => $errs]); exit; }   // the batch uploader
        foreach ($errs as $e) flash($e, 'err');
        if ($n) { audit('gallery.upload', "$n photos"); flash("$n photo(s) uploaded."); }
    }
    redirect($back);
}
