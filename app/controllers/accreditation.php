<?php
declare(strict_types=1);

const ACC_TYPES = [
    'blogger' => 'Blogger',
    'journalist' => 'Independent journalist',
    'media_house' => 'Media house',
    'vlogger' => 'Vlogger / content creator',
];

/* ================= Admin: review applications ================= */
function temp_password(): string { return strtr(base64_encode(random_bytes(9)), '+/', 'Xy'); }

function admin_accreditation(): void
{
    require_can('accreditation');
    $status = in_array($_GET['status'] ?? '', ['pending', 'approved', 'rejected'], true) ? $_GET['status'] : null;
    $sql = 'SELECT a.*, u.active AS user_active, u.last_login FROM accreditation_applications a LEFT JOIN users u ON u.id = a.user_id';
    $p = [];
    if ($status) { $sql .= ' WHERE a.status = ?'; $p[] = $status; }
    admin_view('accreditation', [
        'title' => 'Accreditation', 'status' => $status,
        'counts' => array_column(rows('SELECT status, COUNT(*) n FROM accreditation_applications GROUP BY status'), 'n', 'status'),
        'list' => rows($sql . " ORDER BY FIELD(a.status,'pending','approved','rejected'), a.id DESC LIMIT 200", $p),
    ]);
}
function admin_accreditation_action(array $a): void
{
    require_can('accreditation');
    $id = (int)$a['id'];
    $app = row('SELECT * FROM accreditation_applications WHERE id = ?', [$id]) ?? not_found();
    $act = $_POST['action'] ?? '';
    $note = post_str('admin_note') ?: null;
    $me = (int)current_user()['id'];
    $back = 'admin/accreditation' . (!empty($_POST['back_status']) ? '?status=' . urlencode((string)$_POST['back_status']) : '');

    if ($act === 'approve') {
        $email = strtolower($app['email']);
        $existing = row('SELECT id FROM users WHERE email = ?', [$email]);
        if ($existing && (int)$existing['id'] !== (int)$app['user_id']) {
            flash("A user with the email $email already exists. Edit that account under Users, or ask the applicant to use another email.", 'err');
            redirect($back);
        }
        $org = $app['organization'] ?: $app['full_name'];
        $password = null;
        if ($app['user_id'] && val('SELECT id FROM users WHERE id = ?', [$app['user_id']])) {                       // re-approval: restore the account
            q("UPDATE users SET active = 1, role = 'contributor', organization = ? WHERE id = ?", [mb_substr($org, 0, 160), $app['user_id']]);
            $uid = (int)$app['user_id'];
        } else {
            $password = temp_password();
            q("INSERT INTO users (name, email, password_hash, role, organization) VALUES (?,?,?,'contributor',?)", [mb_substr($app['full_name'], 0, 120), $email, password_hash($password, PASSWORD_DEFAULT), mb_substr($org, 0, 160)]);
            $uid = (int)db()->lastInsertId();
        }
        q("UPDATE accreditation_applications SET status='approved', user_id=?, admin_note=?, reviewed_by=?, reviewed_at=NOW() WHERE id=?", [$uid, $note, $me, $id]);
        send_mail($email, 'You are accredited on The Gulu Chronicles',
            "Hello {$app['full_name']},\n\nCongratulations! You are now an accredited contributor on The Gulu Chronicles.\n\nSign in: " . abs_url('admin/login') . "\nEmail: $email\n" .
            ($password ? "Temporary password: $password\nPlease change it after signing in (Account page).\n" : "Your existing login has been restored.\n") .
            "\nWrite a story, save drafts, and submit it for review. Approved stories are published with your byline.\n");
        audit('accreditation.approve', "#$id {$app['full_name']}");
        flash($password
            ? "Approved. A contributor login was created for {$app['full_name']}: email $email, temporary password $password. This password is shown only once, so share it securely (it was also emailed)."
            : "Approved. {$app['full_name']}'s contributor account is active again.");
    } elseif ($act === 'reject') {
        q("UPDATE accreditation_applications SET status='rejected', admin_note=?, reviewed_by=?, reviewed_at=NOW() WHERE id=?", [$note, $me, $id]);
        if ($app['user_id']) q('UPDATE users SET active = 0 WHERE id = ?', [$app['user_id']]);                 // revoking an approved contributor
        if ($app['status'] === 'pending')
            send_mail($app['email'], 'About your accreditation application', "Hello {$app['full_name']},\n\nThank you for applying to The Gulu Chronicles. We are not able to accredit you at this time. You are welcome to apply again with more examples of your work.\n");
        audit('accreditation.reject', "#$id {$app['full_name']}");
        flash($app['user_id'] ? 'Accreditation revoked and the contributor login was switched off.' : 'Application declined.');
    } else {
        not_found();
    }
    redirect($back);
}

/* ================= Admin: my account (name and password) ================= */
function admin_account(): void
{
    if (!current_user()) redirect('admin/login');
    admin_view('account', ['title' => 'My account', 'u' => row('SELECT id, name, email, role, organization, last_login, must_change FROM users WHERE id = ?', [current_user()['id']])]);
}
function admin_account_save(): void
{
    if (!current_user()) redirect('admin/login');
    $me = row('SELECT * FROM users WHERE id = ?', [current_user()['id']]);
    $act = $_POST['action'] ?? '';
    if ($act === 'profile') {
        $name = post_str('name', 120);
        if (mb_strlen($name) < 2) flash('Please enter your name.', 'err');
        else { q('UPDATE users SET name = ? WHERE id = ?', [$name, $me['id']]); flash('Your name was updated.'); }
    } elseif ($act === 'password') {
        $cur = (string)($_POST['current'] ?? ''); $new = (string)($_POST['password'] ?? ''); $conf = (string)($_POST['confirm'] ?? '');
        if (rate_limited('pwchange', 8, 900)) flash('Too many attempts. Please wait a few minutes.', 'err');
        elseif (!password_verify($cur, $me['password_hash'])) flash('Your current password is not correct.', 'err');
        elseif (strlen($new) < 10) flash('The new password must be at least 10 characters.', 'err');
        elseif ($new !== $conf) flash('The new passwords do not match.', 'err');
        elseif ($new === $cur) flash('Choose a password that is different from the current one.', 'err');
        else {
            q('UPDATE users SET password_hash = ?, must_change = 0 WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $me['id']]);
            session_regenerate_id(true);
            audit('account.password', 'own password changed');
            flash('Password changed.');
        }
    }
    redirect('admin/account');
}
