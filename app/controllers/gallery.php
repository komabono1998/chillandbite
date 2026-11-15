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
        'images' => rows($sql . ' ORDER BY i.featured DESC, i.id ASC', $p),
        'albums' => rows('SELECT a.*, (SELECT COUNT(*) FROM gallery_images i WHERE i.album_id = a.id AND i.hidden = 0) AS n FROM gallery_albums a ORDER BY a.id DESC'),
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
        'images' => rows("SELECT i.*, a.name AS album FROM gallery_images i LEFT JOIN gallery_albums a ON a.id=i.album_id $where ORDER BY i.id DESC LIMIT 500"),
        'albums' => rows('SELECT a.*, (SELECT COUNT(*) FROM gallery_images i WHERE i.album_id = a.id) AS n FROM gallery_albums a ORDER BY a.id'),
        'total' => (int)val('SELECT COUNT(*) FROM gallery_images'),
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
        save_setting('gallery_downloads', empty($_POST['gallery_downloads']) ? '0' : '1'); flash('Download setting saved.');
    } else {
        $n = 0;
        $album = (int)($_POST['album_id'] ?? 0) ?: null;
        $files = $_FILES['photos'] ?? null;
        if ($files && is_array($files['name'])) {
            foreach ($files['name'] as $i => $_) {
                $_FILES['one'] = ['name' => $files['name'][$i], 'type' => $files['type'][$i], 'tmp_name' => $files['tmp_name'][$i], 'error' => $files['error'][$i], 'size' => $files['size'][$i]];
                try { if ($f = save_upload('one', 'gallery')) { q('INSERT INTO gallery_images (album_id, file, caption) VALUES (?,?,?)', [$album, $f, post_str('caption', 190) ?: null]); $n++; } }
                catch (RuntimeException $ex) { flash($files['name'][$i] . ': ' . $ex->getMessage(), 'err'); }
            }
        }
        if ($n) { audit('gallery.upload', "$n photos"); flash("$n photo(s) uploaded."); }
    }
    redirect($back);
}
