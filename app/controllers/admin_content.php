<?php
declare(strict_types=1);

/* ================= News ================= */
function news_is_contrib(): bool { return (current_user()['role'] ?? '') === 'contributor'; }
function news_guard(): void { require_can_any(['news', 'my_articles']); }
function news_owned(int $id): array
{
    $art = row('SELECT * FROM articles WHERE id = ?', [$id]) ?? not_found();
    if (news_is_contrib() && (int)$art['author_id'] !== (int)current_user()['id']) not_found();
    return $art;
}
function admin_news(): void
{
    news_guard();
    $mine = news_is_contrib();
    admin_view('news', ['title' => $mine ? 'My stories' : 'News & articles', 'mine' => $mine,
        'list' => rows('SELECT a.*, c.name AS category, u.name AS author, u.organization AS author_org FROM articles a LEFT JOIN article_categories c ON c.id=a.category_id LEFT JOIN users u ON u.id=a.author_id'
            . ($mine ? ' WHERE a.author_id = ?' : '') . " ORDER BY (a.status='pending') DESC, a.id DESC LIMIT 200", $mine ? [current_user()['id']] : [])]);
}
function admin_article_form(array $a = []): void
{
    news_guard();
    $art = isset($a['id']) ? news_owned((int)$a['id']) : null;
    $mine = news_is_contrib();
    if ($mine && $art && !in_array($art['status'], ['draft', 'pending'], true)) { flash('Published stories can only be changed by an editor. Contact the newsroom for corrections.', 'err'); redirect('admin/news'); }
    admin_view('article_form', ['title' => $art ? 'Edit article' : 'New article', 'art' => $art, 'mine' => $mine, 'cats' => rows('SELECT * FROM article_categories ORDER BY id'), 'old' => $_SESSION['old'] ?? []]);
    clear_old();
}
function admin_article_save(): void
{
    news_guard();
    $mine = news_is_contrib();
    $id = (int)($_POST['id'] ?? 0);
    $existing = $id ? news_owned($id) : null;
    $back = $id ? "admin/news/$id" : 'admin/news/new';
    if ($mine && $existing && !in_array($existing['status'], ['draft', 'pending'], true)) { flash('Published stories can only be changed by an editor.', 'err'); redirect('admin/news'); }
    $title = post_str('title', 220); $body = trim((string)($_POST['body'] ?? ''));
    $labels = $mine ? ['news', 'opinion'] : array_keys(NEWS_LABELS);
    $label = in_array($_POST['label'] ?? '', $labels, true) ? $_POST['label'] : 'news';
    if ($mine) $status = !empty($_POST['submit_review']) ? 'pending' : 'draft';
    else $status = in_array($_POST['status'] ?? '', ['draft', 'pending', 'scheduled', 'published'], true) ? $_POST['status'] : 'draft';
    $pub = $mine ? null : dt_in($_POST['published_at'] ?? '');
    $err = [];
    if (mb_strlen($title) < 3) $err[] = 'Headline is required.';
    if (mb_strlen($body) < 10) $err[] = 'Story body is required.';
    if ($mine && $status === 'pending' && mb_strlen($body) < 200) $err[] = 'Please write a little more before sending to the editor (at least a few sentences).';
    if ($status === 'scheduled' && (!$pub || strtotime($pub) <= time())) $err[] = 'Scheduled stories need a future publish date.';
    if (!$mine && $label === 'sponsored' && post_str('sponsor_name') === '') $err[] = 'Sponsored articles must name the sponsor.';
    $srcUrl = $mine ? '' : post_str('source_url', 500);
    if ($srcUrl !== '' && !preg_match('#^https?://[^\s]+$#i', $srcUrl)) $err[] = 'Source link must start with http:// or https://';
    if ($err) { keep_old($_POST); foreach ($err as $x) flash($x, 'err'); redirect($back); }
    try { $img = save_upload('image', 'articles'); } catch (RuntimeException $ex) { keep_old($_POST); flash($ex->getMessage(), 'err'); redirect($back); }
    if ($status === 'published') $pub = $pub && strtotime($pub) <= time() ? $pub : date('Y-m-d H:i:s');
    if (in_array($status, ['draft', 'pending'], true)) $pub = $pub ?: null;
    $cat = (int)($_POST['category_id'] ?? 0) ?: null;
    if ($mine) {
        $v = [$cat, $title, post_str('excerpt', 300), $body, $label, $status];
        if ($id) { q('UPDATE articles SET category_id=?, title=?, excerpt=?, body=?, label=?, status=? WHERE id=?', [...$v, $id]); }
        else {
            q('INSERT INTO articles (category_id, title, excerpt, body, label, status, slug, author_id) VALUES (?,?,?,?,?,?,?,?)', [...$v, unique_slug('articles', $title), current_user()['id']]);
            $id = (int)db()->lastInsertId();
        }
        if ($img) q('UPDATE articles SET image = ? WHERE id = ?', [$img, $id]);
        audit($status === 'pending' ? 'article.submit' : 'article.draft', "#$id $title");
        flash($status === 'pending' ? 'Sent to the editor for review. You will be emailed when it is published.' : 'Draft saved.');
        redirect($status === 'pending' ? 'admin/news' : "admin/news/$id");
    }
    $v = [$cat, $title, post_str('excerpt', 300), $body, $label, post_str('sponsor_name', 140) ?: null,
          $status, $pub, empty($_POST['featured']) ? 0 : 1, post_str('meta_description', 300) ?: null, trim((string)($_POST['correction'] ?? '')) ?: null,
          post_str('source_name', 140) ?: null, $srcUrl ?: null];
    if ($id) {
        q('UPDATE articles SET category_id=?, title=?, excerpt=?, body=?, label=?, sponsor_name=?, status=?, published_at=?, featured=?, meta_description=?, correction=?, source_name=?, source_url=? WHERE id=?', [...$v, $id]);
        if ($img) q('UPDATE articles SET image = ? WHERE id = ?', [$img, $id]);
        audit('article.update', "#$id $title");
        if ($existing['status'] === 'pending' && $status === 'published' && $existing['author_id']) {
            $au = row('SELECT name, email, role FROM users WHERE id = ?', [$existing['author_id']]);
            if ($au && $au['role'] === 'contributor')
                send_mail($au['email'], 'Your story is live on The Gulu Chronicles', "Hello {$au['name']},\n\nYour story \"$title\" has been approved and published:\n" . abs_url('news/' . $existing['slug']) . "\n\nThe Gulu Chronicles\n");
        }
    } else {
        q('INSERT INTO articles (category_id, title, excerpt, body, label, sponsor_name, status, published_at, featured, meta_description, correction, source_name, source_url, slug, author_id, image) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [...$v, unique_slug('articles', $title), current_user()['id'], $img]);
        $id = (int)db()->lastInsertId();
        audit('article.create', "#$id $title");
    }
    flash('Article saved.');
    redirect("admin/news/$id");
}
function admin_article_delete(array $a): void
{
    news_guard();
    $art = news_owned((int)$a['id']);
    if (news_is_contrib() && $art['status'] === 'published') { flash('Published stories can only be removed by an editor.', 'err'); redirect('admin/news'); }
    q('DELETE FROM articles WHERE id = ?', [(int)$a['id']]);
    audit('article.delete', "#{$a['id']}");
    flash('Article deleted.');
    redirect('admin/news');
}


/* ================= Comedy videos ================= */
/** Saves an uploaded MP4 under uploads/videos and returns the stored path (null when no file was chosen). */
function save_video_file(string $field): ?string
{
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) return null;
    $f = $_FILES[$field];
    if (in_array($f['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) throw new RuntimeException('That video is too large for the server. Use a file under 300MB, or paste a YouTube link instead.');
    if ($f['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('Video upload failed. Please try again.');
    if ($f['size'] > 300 * 1024 * 1024) throw new RuntimeException('Videos must be 300MB or smaller. For longer shows paste a YouTube link.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    $okMime = in_array($mime, ['video/mp4', 'video/x-m4v'], true) || ($mime === 'application/octet-stream' && in_array($ext, ['mp4', 'm4v'], true));
    if (!$okMime || !in_array($ext, ['mp4', 'm4v'], true)) throw new RuntimeException('Only MP4 video files are allowed.');
    $dir = ROOT . '/uploads/videos';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $name = bin2hex(random_bytes(10)) . '.mp4';
    if (!move_uploaded_file($f['tmp_name'], "$dir/$name")) throw new RuntimeException('Could not save the video file.');
    return 'videos/' . $name;
}
function admin_comedy(): void
{
    require_can('comedy');
    admin_view('comedy', ['title' => 'Comedy videos', 'videos' => rows('SELECT * FROM videos ORDER BY id DESC')]);
}
function admin_comedy_save(): void
{
    require_can('comedy');
    $act = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    $unlink = function (?string $f) { if ($f && preg_match('#^(videos)/[a-f0-9]+\.(mp4|jpg|png|webp|gif)$#', $f)) @unlink(ROOT . '/uploads/' . $f); };
    try {
        if ($act === 'delete') {
            $v = row('SELECT file, poster FROM videos WHERE id = ?', [$id]);
            q('DELETE FROM videos WHERE id = ?', [$id]);
            if ($v) { $unlink($v['file']); $unlink($v['poster']); }
            audit('video.delete', "#$id"); flash('Video removed.');
        } elseif ($act === 'feature') {
            q('UPDATE videos SET featured = 1 - featured WHERE id = ?', [$id]); flash('Updated.');
        } elseif ($act === 'save') {
            $existing = $id ? (row('SELECT * FROM videos WHERE id = ?', [$id]) ?? not_found()) : null;
            $title = post_str('title', 190);
            $url = post_str('url');
            $kind = in_array($_POST['kind'] ?? '', ['clip', 'full_show', 'behind_scenes', 'interview', 'highlight'], true) ? $_POST['kind'] : 'clip';
            if (mb_strlen($title) < 2) throw new RuntimeException('Please give the video a title.');
            if ($url !== '' && !youtube_id($url)) throw new RuntimeException('The link must be a YouTube link, or upload an MP4 file instead.');
            $file = save_video_file('video');
            $poster = save_upload('poster', 'videos');
            if (!$existing && !$file && $url === '') throw new RuntimeException('Upload an MP4 file or paste a YouTube link.');
            $v = [$title, $kind, post_str('description', 300) ?: null, empty($_POST['featured']) ? 0 : 1];
            if ($existing) {
                q('UPDATE videos SET title=?, kind=?, description=?, featured=? WHERE id=?', [...$v, $id]);
                if ($url !== '') q('UPDATE videos SET url = ? WHERE id = ?', [$url, $id]);
                if ($file) { $unlink($existing['file']); q('UPDATE videos SET file = ? WHERE id = ?', [$file, $id]); }
                if ($poster) { $unlink($existing['poster']); q('UPDATE videos SET poster = ? WHERE id = ?', [$poster, $id]); }
                audit('video.update', "#$id $title"); flash('Video updated.');
            } else {
                q('INSERT INTO videos (title, kind, description, featured, url, file, poster) VALUES (?,?,?,?,?,?,?)', [...$v, $url ?: null, $file, $poster]);
                audit('video.create', $title); flash($file ? 'Video uploaded. It is live on the website now.' : 'Video added.');
            }
        } else {
            not_found();
        }
    } catch (RuntimeException $e) {
        flash($e->getMessage(), 'err');
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
    $type = in_array($_GET['type'] ?? '', ['contact', 'performance', 'dj', 'advertising'], true) ? $_GET['type'] : null;
    // Role scoping: marketing sees advertising only, editors see contact/performance, event managers see contact.
    $role = current_user()['role'];
    $allowed = inquiry_scope($role);
    $types = $type && in_array($type, $allowed, true) ? [$type] : $allowed;
    $in = implode(',', array_fill(0, count($types), '?'));
    admin_view('inquiries', ['title' => 'Inquiries', 'types' => $allowed, 'type' => $type, 'list' => rows("SELECT * FROM inquiries WHERE type IN ($in) ORDER BY id DESC LIMIT 200", $types)]);
}
function admin_inquiry_update(array $a): void
{
    require_can('inquiries');
    $i = row('SELECT * FROM inquiries WHERE id = ?', [(int)$a['id']]) ?? not_found();
    $role = current_user()['role'];
    $scope = inquiry_scope($role);
    if (!in_array($i['type'], $scope, true)) { http_response_code(403); exit('Forbidden'); }
    $st = in_array($_POST['status'] ?? '', ['new', 'in_progress', 'won', 'closed'], true) ? $_POST['status'] : $i['status'];
    q('UPDATE inquiries SET status = ?, follow_up = ?, agreed_package = ? WHERE id = ?', [$st, post_str('follow_up') ?: null, post_str('agreed_package') ?: null, $i['id']]);
    audit('inquiry.update', "#{$i['id']} -> $st");
    flash('Inquiry updated.');
    redirect('admin/inquiries' . ($_POST['back_type'] ?? '' ? '?type=' . urlencode($_POST['back_type']) : ''));
}
