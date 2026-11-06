<?php
declare(strict_types=1);

const DJ_SOURCES = ['podium' => 'Podium DJs', 'chillbite' => 'Chill & Bite MEATINGS'];
const DJ_EVENT_TYPES = ['Wedding' => 'Wedding', 'Corporate' => 'Corporate event', 'Club' => 'Club / bar night', 'Concert' => 'Concert / festival', 'Private' => 'Private party', 'School' => 'School / campus', 'Other' => 'Other'];

/** Saves an uploaded mix (mp3, m4a, wav or ogg) under uploads/mixes and returns the stored path. */
function save_audio(string $field): ?string
{
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) return null;
    $f = $_FILES[$field];
    if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE) throw new RuntimeException('That audio file is too large. Use a shorter file or paste a SoundCloud, Mixcloud or YouTube link instead.');
    if ($f['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('Audio upload failed. Please try again.');
    if ($f['size'] > 110 * 1024 * 1024) throw new RuntimeException('Audio files must be 110MB or smaller. For longer mixes paste a SoundCloud, Mixcloud or YouTube link.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $ext = ['audio/mpeg' => 'mp3', 'audio/mp3' => 'mp3', 'audio/mp4' => 'm4a', 'audio/x-m4a' => 'm4a', 'audio/wav' => 'wav', 'audio/x-wav' => 'wav', 'audio/vnd.wave' => 'wav', 'audio/ogg' => 'ogg', 'application/ogg' => 'ogg'][$mime] ?? null;
    if (!$ext && in_array($mime, ['video/mp4', 'application/octet-stream'], true) && strtolower(pathinfo($f['name'], PATHINFO_EXTENSION)) === 'm4a') $ext = 'm4a';   // some m4a files are sniffed as mp4
    if (!$ext) throw new RuntimeException('Only MP3, M4A, WAV or OGG audio files are allowed.');
    $dir = ROOT . '/uploads/mixes';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $name = bin2hex(random_bytes(10)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], "$dir/$name")) throw new RuntimeException('Could not save the audio file.');
    return 'mixes/' . $name;
}

/** Friendly download name such as "Afro Friday Mix - DJ Val.mp3". */
function mix_filename(array $m): string
{
    $ext = strtolower(pathinfo((string)$m['audio'], PATHINFO_EXTENSION)) ?: 'mp3';
    $base = trim(preg_replace('#[\\\\/:*?"<>|]+#', ' ', $m['title'] . ' - ' . $m['dj_name']));
    return mb_substr(preg_replace('/\s+/', ' ', $base), 0, 120) . '.' . $ext;
}
/** Embeddable player URL for an external mix link, or null when it can only be opened as a link. */
function mix_embed(string $url): ?array
{
    $host = strtolower((string)parse_url($url, PHP_URL_HOST));
    $host = preg_replace('/^(www|m)\./', '', $host);
    if (($yt = youtube_id($url))) return ['youtube', 'https://www.youtube-nocookie.com/embed/' . $yt . '?autoplay=1&rel=0'];
    if ($host === 'soundcloud.com') return ['soundcloud', 'https://w.soundcloud.com/player/?auto_play=true&visual=false&url=' . rawurlencode($url)];
    if ($host === 'mixcloud.com') return ['mixcloud', 'https://www.mixcloud.com/widget/iframe/?autoplay=1&hide_cover=1&feed=' . rawurlencode((string)parse_url($url, PHP_URL_PATH))];
    return null;
}

/* ================= Public: Podium DJs ================= */
function page_djs(): void
{
    render('site/djs', [
        'title' => 'Podium DJs | Book a DJ & Listen to Mixes | Chill & Bite MEATINGS',
        'desc' => 'Book Podium DJs for weddings, corporate events, club nights and festivals, and listen to mixes from Podium DJs and Chill & Bite MEATINGS.',
        'djs' => rows("SELECT * FROM djs WHERE active = 1 AND role = 'dj' ORDER BY sort_order, id"),
        'hosts' => rows("SELECT * FROM djs WHERE active = 1 AND role = 'host' ORDER BY sort_order, id"),
        'mixes' => rows("SELECT * FROM dj_mixes WHERE status = 'published' ORDER BY featured DESC, id DESC LIMIT 60"),
    ]);
}
function post_dj_booking(): void
{
    $d = form_guard('dj_booking', 'djs#book');
    $err = [];
    if (mb_strlen($d['name'] ?? '') < 2) $err[] = 'Please enter your name.';
    if (!valid_phone($d['phone'] ?? '')) $err[] = 'Please enter a valid phone number.';
    if (($d['email'] ?? '') !== '' && !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $err[] = 'Email address looks invalid.';
    if (empty($d['event_date']) || !strtotime($d['event_date'])) $err[] = 'Please choose the event date.';
    elseif (strtotime($d['event_date']) < strtotime('today')) $err[] = 'The event date cannot be in the past.';
    if (mb_strlen($d['location'] ?? '') < 2) $err[] = 'Please tell us the event location.';
    if (!isset(DJ_EVENT_TYPES[$d['event_type'] ?? ''])) $err[] = 'Please choose the type of event.';
    if ($err) form_fail($err, $d, 'djs#book');
    $djName = '';
    if (!empty($d['dj_id']) && ctype_digit((string)$d['dj_id'])) $djName = (string)val("SELECT name FROM djs WHERE id = ? AND active = 1 AND role = 'dj'", [(int)$d['dj_id']]);
    $details = json_encode([
        'preferred_dj' => $djName ?: 'Any DJ (match me)', 'event_date' => $d['event_date'], 'event_type' => DJ_EVENT_TYPES[$d['event_type']],
        'location' => $d['location'], 'start_time' => $d['start_time'] ?? '', 'hours' => $d['hours'] ?? '', 'audience' => $d['audience'] ?? '',
        'sound_system' => ['yes' => 'Needs sound system and lights', 'no' => 'Venue has its own sound'][$d['sound'] ?? ''] ?? '',
    ], JSON_UNESCAPED_UNICODE);
    q('INSERT INTO inquiries (type, name, organization, phone, email, subject, message, details) VALUES ("dj",?,?,?,?,?,?,?)',
        [$d['name'], $d['organization'] ?? null, $d['phone'], ($d['email'] ?? '') ?: null, 'DJ booking: ' . DJ_EVENT_TYPES[$d['event_type']], mb_substr($d['message'] ?? '', 0, 5000), $details]);
    if (($d['email'] ?? '') !== '')
        send_mail($d['email'], 'We received your DJ booking request', "Hello {$d['name']},\n\nThank you for your DJ booking request for {$d['event_date']}. Our team will contact you shortly to confirm availability and details.\n\nPodium DJs\nChill & Bite MEATINGS\n");
    clear_old();
    flash('Thank you! Your DJ request was sent. Our team will contact you shortly.');
    redirect('djs');
}

/* ================= Admin: DJs and mixes ================= */
function admin_djs(): void
{
    require_can('djs');
    admin_view('djs', ['title' => 'DJs & mixes',
        'djs' => rows('SELECT * FROM djs ORDER BY sort_order, id'),
        'mixes' => rows('SELECT m.*, d.name AS dj_profile FROM dj_mixes m LEFT JOIN djs d ON d.id = m.dj_id ORDER BY m.id DESC LIMIT 300')]);
}
function admin_djs_save(): void
{
    require_can('djs');
    $act = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    $unlink = function (?string $f) { if ($f && preg_match('#^(mixes|djs)/[a-f0-9]+\.(mp3|m4a|wav|ogg|jpg|png|webp|gif)$#', $f)) @unlink(ROOT . '/uploads/' . $f); };
    try {
        if ($act === 'mix_delete') {
            $m = row('SELECT audio, cover FROM dj_mixes WHERE id = ?', [$id]);
            q('DELETE FROM dj_mixes WHERE id = ?', [$id]);
            if ($m) { $unlink($m['audio']); $unlink($m['cover']); }
            audit('mix.delete', "#$id"); flash('Mix removed.');
        } elseif ($act === 'mix_toggle') {
            q("UPDATE dj_mixes SET status = IF(status='published','draft','published') WHERE id = ?", [$id]); flash('Mix visibility updated.');
        } elseif ($act === 'mix_download') {
            q('UPDATE dj_mixes SET allow_download = 1 - allow_download WHERE id = ?', [$id]); flash('Download setting updated.');
        } elseif ($act === 'mix_feature') {
            q('UPDATE dj_mixes SET featured = 1 - featured WHERE id = ?', [$id]); flash('Updated.');
        } elseif ($act === 'mix_save') {
            $existing = $id ? (row('SELECT * FROM dj_mixes WHERE id = ?', [$id]) ?? not_found()) : null;
            $title = post_str('title', 190);
            $url = post_str('external_url', 500);
            $djId = (int)($_POST['dj_id'] ?? 0) ?: null;
            $djName = post_str('dj_name', 120);
            if ($djId) { $n = val('SELECT name FROM djs WHERE id = ?', [$djId]); if ($n) $djName = $djName !== '' ? $djName : (string)$n; else $djId = null; }
            $source = array_key_exists($_POST['source'] ?? '', DJ_SOURCES) ? $_POST['source'] : 'podium';
            if (mb_strlen($title) < 2) throw new RuntimeException('Please give the mix a title.');
            if ($djName === '') throw new RuntimeException('Please choose a DJ or type the DJ name.');
            if ($url !== '' && !preg_match('#^https?://[^\s]+$#i', $url)) throw new RuntimeException('The link must start with http:// or https://');
            $audio = save_audio('audio');
            $cover = save_upload('cover', 'mixes');
            if (!$existing && !$audio && $url === '') throw new RuntimeException('Upload an audio file or paste a SoundCloud, Mixcloud or YouTube link.');
            $v = [$djId, $djName, $source, $title, post_str('genre', 80) ?: null, post_str('description', 300) ?: null, $url ?: null,
                  empty($_POST['allow_download']) ? 0 : 1, empty($_POST['featured']) ? 0 : 1, ($_POST['status'] ?? '') === 'draft' ? 'draft' : 'published'];
            if ($existing) {
                q('UPDATE dj_mixes SET dj_id=?, dj_name=?, source=?, title=?, genre=?, description=?, external_url=?, allow_download=?, featured=?, status=? WHERE id=?', [...$v, $id]);
                if ($audio) { $unlink($existing['audio']); q('UPDATE dj_mixes SET audio = ? WHERE id = ?', [$audio, $id]); }
                if ($cover) { $unlink($existing['cover']); q('UPDATE dj_mixes SET cover = ? WHERE id = ?', [$cover, $id]); }
                audit('mix.update', "#$id $title"); flash('Mix updated.');
            } else {
                q('INSERT INTO dj_mixes (dj_id, dj_name, source, title, genre, description, external_url, allow_download, featured, status, audio, cover) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)', [...$v, $audio, $cover]);
                audit('mix.create', $title); flash('Mix published.');
            }
        } elseif ($act === 'dj_delete') {
            $p = val('SELECT photo FROM djs WHERE id = ?', [$id]);
            q('DELETE FROM djs WHERE id = ?', [$id]);
            $unlink($p ?: null);
            audit('dj.delete', "#$id"); flash('DJ removed. Their mixes stay listed under the DJ name.');
        } elseif ($act === 'dj_toggle') {
            q('UPDATE djs SET active = 1 - active WHERE id = ?', [$id]); flash('DJ visibility updated.');
        } elseif ($act === 'dj_save') {
            $existing = $id ? (row('SELECT * FROM djs WHERE id = ?', [$id]) ?? not_found()) : null;
            $name = post_str('name', 120);
            if (mb_strlen($name) < 2) throw new RuntimeException('Please enter the DJ name.');
            $ig = post_str('instagram', 255);
            if ($ig !== '' && !preg_match('#^https?://[^\s]+$#i', $ig)) throw new RuntimeException('The Instagram link must start with http:// or https://');
            $photo = save_upload('photo', 'djs');
            $role = ($_POST['role'] ?? '') === 'host' ? 'host' : 'dj';
            $v = [$name, $role, post_str('tagline', 190) ?: null, post_str('genres', 190) ?: null, trim((string)($_POST['bio'] ?? '')) ?: null, $ig ?: null, (int)($_POST['sort_order'] ?? 0), max(0, min(100, (int)($_POST['photo_pos'] ?? 15)))];
            if ($existing) {
                q('UPDATE djs SET name=?, role=?, tagline=?, genres=?, bio=?, instagram=?, sort_order=?, photo_pos=? WHERE id=?', [...$v, $id]);
                if ($photo) { $unlink($existing['photo']); q('UPDATE djs SET photo = ? WHERE id = ?', [$photo, $id]); }
                audit('dj.update', "#$id $name"); flash('DJ updated.');
            } else {
                q('INSERT INTO djs (name, role, tagline, genres, bio, instagram, sort_order, photo_pos, photo) VALUES (?,?,?,?,?,?,?,?,?)', [...$v, $photo]);
                audit('dj.create', $name); flash('DJ added to the roster.');
            }
        } else {
            not_found();
        }
    } catch (RuntimeException $e) {
        flash($e->getMessage(), 'err');
    }
    redirect('admin/djs');
}
