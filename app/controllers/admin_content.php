<?php
declare(strict_types=1);

/* ================= News ================= */
function admin_news(): void
{
    require_can('news');
    admin_view('news', ['title' => 'News & articles', 'list' => rows('SELECT a.*, c.name AS category, u.name AS author FROM articles a LEFT JOIN article_categories c ON c.id=a.category_id LEFT JOIN users u ON u.id=a.author_id ORDER BY a.id DESC LIMIT 200')]);
}
function admin_article_form(array $a = []): void
{
    require_can('news');
    $art = isset($a['id']) ? (row('SELECT * FROM articles WHERE id = ?', [(int)$a['id']]) ?? not_found()) : null;
    admin_view('article_form', ['title' => $art ? 'Edit article' : 'New article', 'art' => $art, 'cats' => rows('SELECT * FROM article_categories ORDER BY id'), 'old' => $_SESSION['old'] ?? []]);
    clear_old();
}
function admin_article_save(): void
{
    require_can('news');
    $id = (int)($_POST['id'] ?? 0);
    $back = $id ? "admin/news/$id" : 'admin/news/new';
    $title = post_str('title', 220); $body = trim((string)($_POST['body'] ?? ''));
    $label = array_key_exists($_POST['label'] ?? '', NEWS_LABELS) ? $_POST['label'] : 'news';
    $status = in_array($_POST['status'] ?? '', ['draft', 'scheduled', 'published'], true) ? $_POST['status'] : 'draft';
    $pub = dt_in($_POST['published_at'] ?? '');
    $err = [];
    if (mb_strlen($title) < 3) $err[] = 'Headline is required.';
    if (mb_strlen($body) < 10) $err[] = 'Story body is required.';
    if ($status === 'scheduled' && (!$pub || strtotime($pub) <= time())) $err[] = 'Scheduled stories need a future publish date.';
    if ($label === 'sponsored' && post_str('sponsor_name') === '') $err[] = 'Sponsored articles must name the sponsor.';
    if ($err) { keep_old($_POST); foreach ($err as $x) flash($x, 'err'); redirect($back); }
    try { $img = save_upload('image', 'articles'); } catch (RuntimeException $ex) { keep_old($_POST); flash($ex->getMessage(), 'err'); redirect($back); }
    if ($status === 'published') $pub = $pub && strtotime($pub) <= time() ? $pub : date('Y-m-d H:i:s');
    if ($status === 'draft') $pub = $pub ?: null;
    $v = [(int)($_POST['category_id'] ?? 0) ?: null, $title, post_str('excerpt', 300), $body, $label, post_str('sponsor_name', 140) ?: null,
          $status, $pub, empty($_POST['featured']) ? 0 : 1, post_str('meta_description', 300) ?: null, trim((string)($_POST['correction'] ?? '')) ?: null];
    if ($id) {
        q('UPDATE articles SET category_id=?, title=?, excerpt=?, body=?, label=?, sponsor_name=?, status=?, published_at=?, featured=?, meta_description=?, correction=? WHERE id=?', [...$v, $id]);
        if ($img) q('UPDATE articles SET image = ? WHERE id = ?', [$img, $id]);
        audit('article.update', "#$id $title");
    } else {
        q('INSERT INTO articles (category_id, title, excerpt, body, label, sponsor_name, status, published_at, featured, meta_description, correction, slug, author_id, image) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [...$v, unique_slug('articles', $title), current_user()['id'], $img]);
        $id = (int)db()->lastInsertId();
        audit('article.create', "#$id $title");
    }
    flash('Article saved.');
    redirect("admin/news/$id");
}
function admin_article_delete(array $a): void
{
    require_can('news');
    q('DELETE FROM articles WHERE id = ?', [(int)$a['id']]);
    audit('article.delete', "#{$a['id']}");
    flash('Article deleted.');
    redirect('admin/news');
}

/* ================= Comedy videos ================= */
function admin_comedy(): void
{
    require_can('comedy');
    admin_view('comedy', ['title' => 'Comedy videos', 'videos' => rows('SELECT * FROM videos ORDER BY id DESC')]);
}
function admin_comedy_save(): void
{
    require_can('comedy');
    if (($_POST['action'] ?? '') === 'delete') {
        q('DELETE FROM videos WHERE id = ?', [(int)$_POST['id']]); audit('video.delete', '#' . (int)$_POST['id']); flash('Video removed.');
    } else {
        $url = post_str('url');
        $kind = in_array($_POST['kind'] ?? '', ['clip', 'full_show', 'behind_scenes', 'interview', 'highlight'], true) ? $_POST['kind'] : 'clip';
        if (post_str('title') === '' || !youtube_id($url)) flash('A title and a valid YouTube link are required.', 'err');
        else {
            q('INSERT INTO videos (title, url, kind, description, featured) VALUES (?,?,?,?,?)', [post_str('title', 190), $url, $kind, post_str('description', 300) ?: null, empty($_POST['featured']) ? 0 : 1]);
            audit('video.create', post_str('title')); flash('Video added.');
        }
    }
    redirect('admin/comedy');
}

/* ================= Gallery ================= */
function admin_gallery(): void
{
    require_can('gallery');
    admin_view('gallery', ['title' => 'Gallery', 'images' => rows('SELECT i.*, a.name AS album FROM gallery_images i LEFT JOIN gallery_albums a ON a.id=i.album_id ORDER BY i.id DESC LIMIT 300'), 'albums' => rows('SELECT * FROM gallery_albums ORDER BY id')]);
}
function admin_gallery_save(): void
{
    require_can('gallery');
    $act = $_POST['action'] ?? '';
    if ($act === 'delete') {
        $f = val('SELECT file FROM gallery_images WHERE id = ?', [(int)$_POST['id']]);
        q('DELETE FROM gallery_images WHERE id = ?', [(int)$_POST['id']]);
        if ($f && preg_match('#^gallery/[a-f0-9]+\.(jpg|png|webp|gif)$#', $f)) @unlink(ROOT . '/uploads/' . $f);
        audit('gallery.delete', '#' . (int)$_POST['id']); flash('Photo deleted.');
    } elseif ($act === 'feature') {
        q('UPDATE gallery_images SET featured = 1 - featured WHERE id = ?', [(int)$_POST['id']]); flash('Updated.');
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
    redirect('admin/gallery');
}

/* ================= Advertising / sponsors ================= */
function admin_advertising(): void
{
    require_can('advertising');
    admin_view('advertising', ['title' => 'Sponsors & advertising', 'sponsors' => rows('SELECT * FROM sponsors ORDER BY active DESC, FIELD(tier,"title","gold","silver","partner"), name'),
        'inq' => rows("SELECT * FROM inquiries WHERE type='advertising' ORDER BY id DESC LIMIT 100")]);
}
function admin_advertising_save(): void
{
    require_can('advertising');
    $act = $_POST['action'] ?? '';
    if ($act === 'delete') { q('DELETE FROM sponsors WHERE id = ?', [(int)$_POST['id']]); audit('sponsor.delete', '#' . (int)$_POST['id']); flash('Sponsor removed.'); }
    elseif ($act === 'toggle') { q('UPDATE sponsors SET active = 1 - active WHERE id = ?', [(int)$_POST['id']]); flash('Updated.'); }
    else {
        try { $logo = save_upload('logo', 'sponsors'); } catch (RuntimeException $ex) { flash($ex->getMessage(), 'err'); redirect('admin/advertising'); }
        $site = post_str('website');
        if (post_str('name') === '') flash('Sponsor name is required.', 'err');
        elseif ($site !== '' && !preg_match('#^https?://#i', $site)) flash('Website must start with http:// or https://', 'err');
        else {
            $tier = in_array($_POST['tier'] ?? '', ['title', 'gold', 'silver', 'partner'], true) ? $_POST['tier'] : 'partner';
            q('INSERT INTO sponsors (name, logo, website, tier) VALUES (?,?,?,?)', [post_str('name', 140), $logo, $site ?: null, $tier]);
            audit('sponsor.create', post_str('name')); flash('Sponsor added.');
        }
    }
    redirect('admin/advertising');
}

/* ================= Inquiries ================= */
function admin_inquiries(): void
{
    require_can('inquiries');
    $type = in_array($_GET['type'] ?? '', ['contact', 'performance', 'advertising'], true) ? $_GET['type'] : null;
    // Role scoping: marketing sees advertising only, editors see contact/performance, event managers see contact.
    $role = current_user()['role'];
    $allowed = ['admin' => ['contact', 'performance', 'advertising'], 'marketing' => ['advertising'], 'editor' => ['contact', 'performance'], 'event_manager' => ['contact', 'performance']][$role];
    $types = $type && in_array($type, $allowed, true) ? [$type] : $allowed;
    $in = implode(',', array_fill(0, count($types), '?'));
    admin_view('inquiries', ['title' => 'Inquiries', 'types' => $allowed, 'type' => $type, 'list' => rows("SELECT * FROM inquiries WHERE type IN ($in) ORDER BY id DESC LIMIT 200", $types)]);
}
function admin_inquiry_update(array $a): void
{
    require_can('inquiries');
    $i = row('SELECT * FROM inquiries WHERE id = ?', [(int)$a['id']]) ?? not_found();
    $role = current_user()['role'];
    $scope = ['admin' => ['contact', 'performance', 'advertising'], 'marketing' => ['advertising'], 'editor' => ['contact', 'performance'], 'event_manager' => ['contact', 'performance']][$role];
    if (!in_array($i['type'], $scope, true)) { http_response_code(403); exit('Forbidden'); }
    $st = in_array($_POST['status'] ?? '', ['new', 'in_progress', 'won', 'closed'], true) ? $_POST['status'] : $i['status'];
    q('UPDATE inquiries SET status = ?, follow_up = ?, agreed_package = ? WHERE id = ?', [$st, post_str('follow_up') ?: null, post_str('agreed_package') ?: null, $i['id']]);
    audit('inquiry.update', "#{$i['id']} -> $st");
    flash('Inquiry updated.');
    redirect('admin/inquiries' . ($_POST['back_type'] ?? '' ? '?type=' . urlencode($_POST['back_type']) : ''));
}
