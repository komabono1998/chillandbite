<?php
declare(strict_types=1);

/* ================= Admin: Pictures manager ================= */
/** Every picture slot on the website pages (the image fields of the Pages manager). */
function picture_slots(): array
{
    $out = [];
    foreach (site_pages() as $pid => $page) {
        if (!can_edit_page($pid)) continue;
        foreach ($page['sections'] as $sid => $sec)
            foreach ($sec['fields'] as $fd) {
                if ($fd['type'] !== 'image') continue;
                $key = "$pid.$sid.{$fd['key']}";
                $ov = content_overrides()[$key] ?? '';
                $eff = $ov !== '' ? $ov : (string)$fd['default'];
                $out[] = ['key' => $key, 'page' => $pid, 'page_label' => $page['label'], 'section' => $sec['label'], 'label' => $fd['label'], 'help' => $fd['help'] ?? '',
                          'overridden' => $ov !== '', 'url' => $eff !== '' ? upload_url($eff) : '', 'default' => (string)$fd['default'], 'url_path' => $page['url']];
            }
    }
    return $out;
}
/** Where each library photo is used, for the labels on the Photo library tab. */
function photo_usage(): array
{
    $u = [];
    foreach (PHOTO_SETS as $set => $nums) foreach ($nums as $n) $u[$n][] = ['crowd' => 'Crowd', 'stage' => 'Stage & DJs', 'people' => 'People', 'social' => 'Social', 'food' => 'Food'][$set] ?? $set;
    foreach ([44, 3, 6, 41, 18] as $n) $u[$n][] = 'Home slideshow';
    foreach ([10 => 'About & host', 12 => 'Comedy header', 22 => 'News header', 33 => 'Contact header', 42 => 'Advertise header', 53 => 'Final call to action'] as $n => $l) $u[$n][] = $l;
    foreach ([41 => 'About collage', 11 => 'About collage', 30 => 'About collage', 44 => 'Staff login'] as $n => $l) $u[$n][] = $l;
    return array_map(fn($a) => array_values(array_unique($a)), $u);
}
function admin_pictures(): void
{
    require_can('pages');
    $photos = [];
    foreach (glob(ROOT . '/assets/img/photos/photo-*.jpg') ?: [] as $f)
        if (preg_match('/photo-(\d+)\.jpg$/', $f, $m)) $photos[] = (int)$m[1];
    sort($photos);
    $usage = photo_usage();
    $lib = array_map(fn($n) => ['n' => $n, 'url' => photo($n), 'overridden' => photo_override($n) !== null, 'used' => $usage[$n] ?? []], $photos);
    $pickGallery = array_map(fn($r) => ['id' => 'g:' . $r['id'], 'url' => upload_url($r['file']), 'label' => (string)($r['caption'] ?: 'Gallery photo ' . $r['id'])], rows('SELECT id, file, caption FROM gallery_images ORDER BY id DESC LIMIT 400'));
    $pickLib = array_map(fn($p) => ['id' => 'p:' . $p['n'], 'url' => $p['url'], 'label' => 'Library photo ' . $p['n']], $lib);
    admin_view('pictures', [
        'title' => 'Pictures', 'slots' => picture_slots(), 'lib' => $lib, 'brandOk' => can('settings'), 'pickGallery' => $pickGallery, 'pickLib' => $pickLib,
        'brand' => ['logo' => brand_logo(), 'favicon' => brand_favicon(), 'logo_ov' => (content_overrides()['brand.logo'] ?? '') !== '', 'fav_ov' => (content_overrides()['brand.favicon'] ?? '') !== ''],
        'elsewhere' => [
            ['Event posters & flyers', 'admin/events', 'events', '📅', (int)val("SELECT COUNT(*) FROM events WHERE poster IS NOT NULL AND poster <> ''"), 'posters'],
            ['Gallery photos', 'admin/gallery', 'gallery', '🖼️', (int)val('SELECT COUNT(*) FROM gallery_images'), 'photos'],
            ['Sponsor & partner logos', 'admin/advertising', 'advertising', '📣', (int)val('SELECT COUNT(*) FROM sponsors'), 'logos'],
            ['DJ and host photos', 'admin/djs', 'djs', '🎧', (int)val("SELECT COUNT(*) FROM djs WHERE photo IS NOT NULL AND photo <> ''"), 'photos'],
            ['Mix cover images', 'admin/djs', 'djs', '💿', (int)val("SELECT COUNT(*) FROM dj_mixes WHERE cover IS NOT NULL AND cover <> ''"), 'covers'],
            ['News story pictures', 'admin/news', 'news', '📰', (int)val("SELECT COUNT(*) FROM articles WHERE image IS NOT NULL AND image <> ''"), 'pictures'],
        ],
    ]);
}
/** Stored value for a picture chosen from the gallery or photo library, e.g. "g:12" or "p:44". Uploaded files are copied so deleting the original cannot break the page. */
function picture_pick_value(string $source): string
{
    $copy = function (string $file): string {
        if (str_starts_with($file, 'img/')) return $file;                       // bundled photo: nothing to copy
        $src = ROOT . '/uploads/' . $file;
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (!is_file($src) || !in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) throw new RuntimeException('That picture could not be found.');
        $dir = ROOT . '/uploads/site';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $name = 'site/' . bin2hex(random_bytes(10)) . '.' . ($ext === 'jpeg' ? 'jpg' : $ext);
        if (!copy($src, ROOT . '/uploads/' . $name)) throw new RuntimeException('Could not copy the picture.');
        return $name;
    };
    if (preg_match('/^g:(\d+)$/', $source, $m)) {
        $file = val('SELECT file FROM gallery_images WHERE id = ?', [(int)$m[1]]);
        if (!$file) throw new RuntimeException('That gallery picture no longer exists.');
        return $copy((string)$file);
    }
    if (preg_match('/^p:(\d+)$/', $source, $m)) {
        $n = (int)$m[1];
        if (!is_file(ROOT . sprintf('/assets/img/photos/photo-%02d.jpg', $n))) throw new RuntimeException('That photo does not exist.');
        $ov = content_overrides()['photolib.' . $n] ?? '';
        return $ov !== '' ? $copy($ov) : sprintf('img/photos/photo-%02d.jpg', $n);
    }
    throw new RuntimeException('Choose a picture from the gallery.');
}
/** New picture for a replace action: an uploaded file, or one picked from the gallery. */
function picture_new_value(string $act): string
{
    if (str_ends_with($act, '_pick')) return picture_pick_value((string)($_POST['source'] ?? ''));
    $up = save_upload('image', 'site');
    if (!$up) throw new RuntimeException('Choose a picture to upload, or pick one from the gallery.');
    return $up;
}
function admin_pictures_save(): void
{
    require_can('pages');
    $act = $_POST['action'] ?? '';
    $uid = (int)current_user()['id'];
    $back = 'admin/pictures';
    $put = function (string $key, ?string $file) use ($uid) {
        $old = (string)val('SELECT `value` FROM site_content WHERE `key` = ?', [$key]);
        if ($file === null) q('DELETE FROM site_content WHERE `key` = ?', [$key]);
        else q('INSERT INTO site_content (`key`, `value`, updated_by) VALUES (?,?,?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), updated_by = VALUES(updated_by)', [$key, $file, $uid]);
        if ($old !== '' && $old !== $file && preg_match('#^site/[a-f0-9]+\.(jpg|png|webp|gif)$#', $old) && !val('SELECT COUNT(*) FROM site_content WHERE `value` = ?', [$old])) @unlink(ROOT . '/uploads/' . $old);
    };
    try {
        if (in_array($act, ['slot_replace', 'slot_pick', 'slot_reset'], true)) {
            $key = (string)($_POST['key'] ?? '');
            $fd = content_fields()[$key] ?? null;
            if (!$fd || $fd['type'] !== 'image' || !can_edit_page(explode('.', $key)[0])) not_found();
            $back .= '#slot-' . str_replace('.', '-', $key);
            if ($act === 'slot_reset') { $put($key, null); flash('Picture reset to the original.'); }
            else {
                $up = picture_new_value($act);
                $put($key, $up); flash('Picture updated. It is live on the website now.');
            }
            audit('picture.' . $act, $key);
        } elseif (in_array($act, ['photo_replace', 'photo_pick', 'photo_reset'], true)) {
            $n = (int)($_POST['n'] ?? 0);
            if ($n < 1 || !is_file(ROOT . sprintf('/assets/img/photos/photo-%02d.jpg', $n))) not_found();
            $back .= '#photo-' . $n;
            if ($act === 'photo_reset') { $put("photolib.$n", null); flash("Photo $n reset to the original."); }
            else {
                $up = picture_new_value($act);
                $put("photolib.$n", $up); flash("Photo $n replaced everywhere it appears.");
            }
            audit('picture.' . $act, "photo $n");
        } elseif (in_array($act, ['brand_replace', 'brand_pick', 'brand_reset'], true)) {
            require_can('settings');
            $which = ($_POST['which'] ?? '') === 'favicon' ? 'favicon' : 'logo';
            $back .= '#brand';
            if ($act === 'brand_reset') { $put("brand.$which", null); flash(ucfirst($which) . ' reset to the original.'); }
            else {
                $up = picture_new_value($act);
                $put("brand.$which", $up); flash(ucfirst($which) . ' updated.');
            }
            audit('picture.' . $act, $which);
        } else {
            not_found();
        }
    } catch (RuntimeException $e) {
        flash($e->getMessage(), 'err');
    }
    redirect($back);
}

/* ================= Quick-edit bar for signed-in staff on the public site ================= */
/** Admin page that edits the page being viewed, or null. */
function quick_edit_target(): array
{
    $path = trim(substr(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), strlen(BASE)), '/');
    $seg = explode('/', $path);
    $pages = ['' => 'home', 'about' => 'about', 'events' => 'events', 'laugh' => 'laugh', 'djs' => 'djs', 'news' => 'news', 'advertise' => 'advertise', 'partner' => 'partner',
              'accreditation' => 'accreditation', 'track' => 'track', 'gallery' => 'gallery', 'contact' => 'contact'];
    $t = ['page' => null, 'record' => null, 'record_label' => ''];
    if (count($seg) === 1 && isset($pages[$seg[0]])) $t['page'] = $pages[$seg[0]];
    elseif ($seg[0] === 'events' && !empty($seg[1]) && can('events')) {
        $id = val('SELECT id FROM events WHERE slug = ?', [$seg[1]]);
        if ($id) { $t['record'] = url('admin/events/' . $id); $t['record_label'] = 'Edit this event'; }
        $t['page'] = 'events';
    } elseif ($seg[0] === 'news' && !empty($seg[1]) && can_any(['news'])) {
        $id = val('SELECT id FROM articles WHERE slug = ?', [$seg[1]]);
        if ($id) { $t['record'] = url('admin/news/' . $id); $t['record_label'] = 'Edit this story'; }
    }
    if ($t['page'] && !can_edit_page($t['page'])) $t['page'] = null;
    return $t;
}
/** Pictures on the current page that staff can edit: [site-relative path => [label, admin URL]]. */
function quick_edit_map(?string $currentPage = null): array
{
    $m = [];
    $add = function (?string $url, string $label, string $href, bool $weak = false) use (&$m) {
        if (!$url) return;
        $p = parse_url($url, PHP_URL_PATH);
        if ($weak && isset($m[$p])) return;       // a library photo or page slot keeps its own link
        $m[$p] = [$label, $href];
    };
    // Gallery items first: the library link below wins on every page except the Gallery itself
    if (can('gallery') && $currentPage !== 'gallery') { /* added after the library so it does not hide it */ }
    foreach (picture_slots() as $s) {
        if ($s['page'] === $currentPage) continue;      // this page's own slots are added last so they win
        $add($s['url'], 'Edit ' . strtolower($s['label']), url('admin/pictures#slot-' . str_replace('.', '-', $s['key'])));
    }
    foreach (glob(ROOT . '/assets/img/photos/photo-*.jpg') ?: [] as $f)
        if (preg_match('/photo-(\d+)\.jpg$/', $f, $x)) { $n = (int)$x[1]; $add(photo($n), 'Replace photo ' . $n, url('admin/pictures#photo-' . $n)); }
    if (can('settings')) { $add(brand_logo(), 'Replace the logo', url('admin/pictures#brand')); $add(brand_favicon(), 'Replace the icon', url('admin/pictures#brand')); }
    if (can('events')) foreach (rows("SELECT id, poster FROM events WHERE poster IS NOT NULL AND poster <> ''") as $r) $add(upload_url($r['poster']), 'Edit event poster', url('admin/events/' . $r['id']), true);
    if (can('gallery')) foreach (rows('SELECT file FROM gallery_images') as $r) {
        $p = parse_url(upload_url($r['file']), PHP_URL_PATH);
        if ($currentPage === 'gallery' || !isset($m[$p])) $add(upload_url($r['file']), 'Edit gallery photos', url('admin/gallery'));
    }
    if (can('djs')) {
        foreach (rows("SELECT photo FROM djs WHERE photo IS NOT NULL AND photo <> ''") as $r) $add(upload_url($r['photo']), 'Edit this DJ', url('admin/djs'), true);
        foreach (rows("SELECT cover FROM dj_mixes WHERE cover IS NOT NULL AND cover <> ''") as $r) $add(upload_url($r['cover']), 'Edit this mix', url('admin/djs'), true);
    }
    if (can('comedy')) foreach (rows("SELECT poster FROM videos WHERE poster IS NOT NULL AND poster <> ''") as $r) $add(upload_url($r['poster']), 'Edit this video', url('admin/comedy'), true);
    if (can('news')) foreach (rows("SELECT id, image FROM articles WHERE image IS NOT NULL AND image <> ''") as $r) $add(upload_url($r['image']), 'Edit this story', url('admin/news/' . $r['id']), true);
    if (can('advertising')) foreach (rows("SELECT logo FROM sponsors WHERE logo IS NOT NULL AND logo <> ''") as $r) $add(upload_url($r['logo']), 'Edit sponsor logo', url('admin/advertising'), true);
    foreach (picture_slots() as $s)
        if ($s['page'] === $currentPage) $add($s['url'], 'Edit ' . strtolower($s['label']), url('admin/pictures#slot-' . str_replace('.', '-', $s['key'])));
    return $m;
}
/** Top bar with quick links, plus the pencil overlay script. Returns '' for visitors. */
function quick_edit_bar(): string
{
    $u = current_user();
    if (!$u || !can_any(['pages', 'events', 'news', 'gallery', 'djs', 'advertising'])) return '';
    $t = quick_edit_target();
    $links = [['🏠', 'Dashboard', url('admin')]];
    if ($t['page']) $links[] = ['✏️', 'Edit this page', url('admin/pages/' . $t['page'])];
    if ($t['record']) $links[] = ['📝', $t['record_label'], $t['record']];
    if (can('pages')) $links[] = ['🖼️', 'Pictures', url('admin/pictures' . ($t['page'] ? '#page-' . $t['page'] : ''))];
    $html = '<div class="qe-bar" id="qeBar" role="region" aria-label="Quick edit"><span class="qe-tag">Admin</span><nav>';
    foreach ($links as [$ic, $l, $h]) $html .= '<a href="' . h($h) . '"><span aria-hidden="true">' . $ic . '</span> ' . h($l) . '</a>';
    $html .= '</nav><button type="button" class="qe-toggle" id="qeToggle" aria-pressed="false" title="Show an edit pencil on every picture you can change">✏️ Show picture pencils</button>'
        . '<form class="qe-out" method="post" action="' . h(url('admin/logout')) . '">' . csrf_field() . '<button>Log out</button></form></div>'
        . '<script>window.QE_MAP=' . json_encode(quick_edit_map($t['page']), JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) . ';document.body.classList.add("has-qe");</script><script src="' . h(asset_v('js/quickedit.js')) . '" defer></script>';
    return $html;
}
