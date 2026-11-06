<?php
declare(strict_types=1);

const PARTNER_TYPES = ['Event sponsor', 'Product or service supplier', 'Media partner', 'Community partner', 'Brand activation partner', 'Other'];

/* ================= Public ================= */
function approved_partners(): array
{
    return rows('SELECT * FROM sponsors WHERE active = 1 ORDER BY FIELD(tier,"title","gold","silver","partner"), name');
}
function page_partner(): void
{
    render('site/partner', [
        'title' => 'Become a Partner | Chill & Bite MEATINGS Gulu',
        'desc' => "Apply to partner with Chill & Bite MEATINGS and put your brand in front of Gulu City's professionals, entrepreneurs and creatives.",
        'partners' => approved_partners(),
    ]);
}
function post_partner(): void
{
    $back = 'partner#apply';
    if (!empty($_POST['website_url'])) { flash('Thank you!'); redirect('partner'); } // honeypot
    if (rate_limited('partner', 8, 3600)) { flash('Too many applications from your connection. Please try again later.', 'err'); redirect($back); }
    $d = [];
    foreach ($_POST as $k => $v) if (is_string($v)) $d[$k] = trim($v);
    $err = [];
    if (mb_strlen($d['company'] ?? '') < 2) $err[] = 'Please enter your company or brand name.';
    if (mb_strlen($d['contact_name'] ?? '') < 2) $err[] = 'Please enter the contact person.';
    if (!valid_phone($d['phone'] ?? '')) $err[] = 'Please enter a valid telephone number.';
    if (!filter_var($d['email'] ?? '', FILTER_VALIDATE_EMAIL)) $err[] = 'Please enter a valid email address.';
    $site = $d['site'] ?? '';
    if ($site !== '' && !preg_match('#^https?://[^\s]+$#i', $site)) $err[] = 'Website must start with http:// or https://';
    if (!in_array($d['partnership_type'] ?? '', PARTNER_TYPES, true)) $err[] = 'Please choose a partnership type.';
    if (mb_strlen($d['about'] ?? '') < 20) $err[] = 'Please tell us about your business (at least a sentence or two).';
    if (empty($_POST['consent'])) $err[] = 'Please confirm you agree to be contacted about your application.';
    $logo = null;
    if (!$err) {
        try { $logo = save_upload('logo', 'partners'); }
        catch (RuntimeException $e) { $err[] = $e->getMessage(); }
        if (!$err && !$logo) $err[] = 'Please upload your company logo.';
    }
    if ($err) { keep_old($d); foreach ($err as $x) flash($x, 'err'); redirect($back); }
    q('INSERT INTO partner_applications (company, contact_name, phone, email, website, industry, partnership_type, about, proposal, logo) VALUES (?,?,?,?,?,?,?,?,?,?)', [
        mb_substr($d['company'], 0, 160), mb_substr($d['contact_name'], 0, 140), $d['phone'], $d['email'], $site ?: null,
        mb_substr($d['industry'] ?? '', 0, 120) ?: null, $d['partnership_type'], mb_substr($d['about'], 0, 3000),
        mb_substr($d['proposal'] ?? '', 0, 3000) ?: null, $logo]);
    send_mail($d['email'], 'We received your partnership application',
        "Hello {$d['contact_name']},\n\nThank you for applying to partner with Chill & Bite MEATINGS as {$d['company']}. Our team will review your application and contact you.\n");
    clear_old();
    flash('Application received! Our team will review it and contact you. Once approved, your logo will appear under Our Partners.');
    redirect('partner');
}

/* ================= Admin review ================= */
function admin_partners(): void
{
    require_can('advertising');
    $status = in_array($_GET['status'] ?? '', ['pending', 'approved', 'rejected'], true) ? $_GET['status'] : null;
    $sql = 'SELECT pa.*, s.active AS sponsor_active, s.tier AS sponsor_tier FROM partner_applications pa LEFT JOIN sponsors s ON s.id = pa.sponsor_id';
    $p = [];
    if ($status) { $sql .= ' WHERE pa.status = ?'; $p[] = $status; }
    admin_view('partners', ['title' => 'Partner applications', 'status' => $status,
        'list' => rows($sql . " ORDER BY FIELD(pa.status,'pending','approved','rejected'), pa.id DESC LIMIT 200", $p)]);
}
function admin_partner_action(array $a): void
{
    require_can('advertising');
    $id = (int)$a['id'];
    $app = row('SELECT * FROM partner_applications WHERE id = ?', [$id]) ?? not_found();
    $act = $_POST['action'] ?? '';
    $note = post_str('admin_note') ?: null;
    $uid = current_user()['id'];
    if ($act === 'approve') {
        $tier = in_array($_POST['tier'] ?? '', ['title', 'gold', 'silver', 'partner'], true) ? $_POST['tier'] : 'partner';
        $pdo = db();
        $pdo->beginTransaction();
        try {
            if ($app['sponsor_id'] && val('SELECT id FROM sponsors WHERE id = ?', [$app['sponsor_id']])) {
                q('UPDATE sponsors SET active = 1, tier = ?, name = ?, logo = ?, website = ? WHERE id = ?', [$tier, $app['company'], $app['logo'], $app['website'], $app['sponsor_id']]);
                $sid = (int)$app['sponsor_id'];
            } else {
                q('INSERT INTO sponsors (name, logo, website, tier, active) VALUES (?,?,?,?,1)', [$app['company'], $app['logo'], $app['website'], $tier]);
                $sid = (int)$pdo->lastInsertId();
            }
            q("UPDATE partner_applications SET status='approved', sponsor_id=?, admin_note=?, reviewed_by=?, reviewed_at=NOW() WHERE id=?", [$sid, $note, $uid, $id]);
            $pdo->commit();
        } catch (Throwable $e) { $pdo->rollBack(); throw $e; }
        if ($app['status'] !== 'approved')
            send_mail($app['email'], 'Your partnership application is approved',
                "Hello {$app['contact_name']},\n\nGreat news! {$app['company']} is now an official Chill & Bite MEATINGS partner and your logo appears on our website:\n" . abs_url('advertise') . "\n\nWe will be in touch about next steps.\n");
        audit('partner.approve', "#$id {$app['company']}");
        flash($app['company'] . ' approved. Their logo now appears under Partners.');
    } elseif ($act === 'reject') {
        q("UPDATE partner_applications SET status='rejected', admin_note=?, reviewed_by=?, reviewed_at=NOW() WHERE id=?", [$note, $uid, $id]);
        if ($app['sponsor_id']) q('UPDATE sponsors SET active = 0 WHERE id = ?', [$app['sponsor_id']]); // pull a previously approved logo
        if ($app['status'] === 'pending')
            send_mail($app['email'], 'About your partnership application',
                "Hello {$app['contact_name']},\n\nThank you for your interest in partnering with Chill & Bite MEATINGS. We are unable to proceed with {$app['company']} at this time. You are welcome to contact us to discuss other ways to work together.\n");
        audit('partner.reject', "#$id {$app['company']}");
        flash('Application rejected' . ($app['sponsor_id'] ? ' and the logo was removed from the website.' : '.'));
    } else {
        not_found();
    }
    redirect('admin/partners' . (!empty($_POST['back_status']) ? '?status=' . urlencode((string)$_POST['back_status']) : ''));
}
