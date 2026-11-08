<?php
declare(strict_types=1);

/* ================= Printable general tickets: categories, numbered batches, PDF and print ================= */
const GEN_MAX_BATCH = 2000, GEN_MAX_PDF = 3000;

/** Create the tables on a site that was updated without running the installer. */
function gentickets_ensure(): void
{
    db()->exec("CREATE TABLE IF NOT EXISTS ticket_categories (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, name VARCHAR(80) NOT NULL, prefix VARCHAR(8) NOT NULL, admits TINYINT UNSIGNED NOT NULL DEFAULT 1,
        price INT UNSIGNED NOT NULL DEFAULT 0, color CHAR(7) NOT NULL DEFAULT '#D96B27', terms VARCHAR(255) NOT NULL DEFAULT '', active TINYINT(1) NOT NULL DEFAULT 1, sort SMALLINT NOT NULL DEFAULT 0,
        UNIQUE KEY uq_tc_prefix (prefix)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    foreach ([['General Admission', 'GA', '#D96B27', 1], ['VIP', 'VIP', '#7B4FC4', 2], ['VVIP', 'VVIP', '#B8860B', 3], ['Complimentary', 'COMP', '#2E9D57', 4]] as $c)
        q('INSERT IGNORE INTO ticket_categories (name, prefix, admits, color, sort) VALUES (?,?,1,?,?)', [$c[0], $c[1], $c[2], $c[3]]);
    db()->exec("CREATE TABLE IF NOT EXISTS gen_batches (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, event_id INT UNSIGNED NOT NULL, category_id INT UNSIGNED NOT NULL, from_no INT UNSIGNED NOT NULL, to_no INT UNSIGNED NOT NULL,
        note VARCHAR(190) NULL, created_by INT UNSIGNED NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, KEY idx_gb_event (event_id),
        CONSTRAINT fk_gb_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    db()->exec("CREATE TABLE IF NOT EXISTS gen_tickets (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, batch_id INT UNSIGNED NOT NULL, event_id INT UNSIGNED NOT NULL, category_id INT UNSIGNED NOT NULL, number INT UNSIGNED NOT NULL,
        token CHAR(24) NOT NULL UNIQUE, status ENUM('valid','used','void') NOT NULL DEFAULT 'valid', used_at DATETIME NULL, used_by INT UNSIGNED NULL,
        UNIQUE KEY uq_gt_number (event_id, category_id, number), KEY idx_gt_batch (batch_id), CONSTRAINT fk_gt_batch FOREIGN KEY (batch_id) REFERENCES gen_batches(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
function gen_q(string $sql, array $p = []): array
{
    try { return rows($sql, $p); }
    catch (PDOException $e) { if ($e->getCode() !== '42S02') throw $e; gentickets_ensure(); return rows($sql, $p); }
}

function admin_gentickets(): void
{
    require_can('tickets');
    $cats = gen_q('SELECT * FROM ticket_categories ORDER BY sort, id');
    $events = rows("SELECT id, title, start_at FROM events WHERE status <> 'draft' AND start_at >= (NOW() - INTERVAL 30 DAY) ORDER BY start_at ASC LIMIT 60");
    $batches = rows("SELECT b.*, e.title AS event_title, e.start_at, c.name AS cat_name, c.prefix, c.color,
            (SELECT COUNT(*) FROM gen_tickets t WHERE t.batch_id = b.id AND t.status = 'valid') AS n_valid,
            (SELECT COUNT(*) FROM gen_tickets t WHERE t.batch_id = b.id AND t.status = 'used') AS n_used,
            (SELECT COUNT(*) FROM gen_tickets t WHERE t.batch_id = b.id AND t.status = 'void') AS n_void
        FROM gen_batches b JOIN events e ON e.id = b.event_id LEFT JOIN ticket_categories c ON c.id = b.category_id ORDER BY b.id DESC LIMIT 100");
    $next = [];
    foreach (rows('SELECT event_id, category_id, MAX(number) mx FROM gen_tickets GROUP BY event_id, category_id') as $x) $next[$x['event_id'] . '-' . $x['category_id']] = (int)$x['mx'] + 1;
    admin_view('gentickets', ['title' => 'Printable tickets', 'cats' => $cats, 'events' => $events, 'batches' => $batches, 'next' => $next, 'can_cats' => can('events')]);
}

/* ---------- categories (set by the admin) ---------- */
function admin_gen_categories_save(): void
{
    require_can('events');
    gentickets_ensure();
    $errors = [];
    $clean = function (array $c, ?int $id) use (&$errors): ?array {
        $name = mb_substr(trim((string)($c['name'] ?? '')), 0, 80);
        $prefix = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)($c['prefix'] ?? '')));
        $prefix = substr($prefix, 0, 8);
        if ($name === '' || $prefix === '') return null;
        if (val('SELECT id FROM ticket_categories WHERE prefix = ? AND id <> ?', [$prefix, $id ?? 0])) { $errors[] = "The short code $prefix is already used by another category."; return null; }
        $color = preg_match('/^#[0-9a-fA-F]{6}$/', (string)($c['color'] ?? '')) ? strtoupper((string)$c['color']) : '#D96B27';
        return [$name, $prefix, max(1, min(50, (int)($c['admits'] ?? 1))), max(0, (int)preg_replace('/\D+/', '', (string)($c['price'] ?? '0'))), $color, mb_substr(trim((string)($c['terms'] ?? '')), 0, 255), !empty($c['active']) ? 1 : 0];
    };
    foreach ((array)($_POST['cat'] ?? []) as $id => $c) {
        $id = (int)$id; if (!$id) continue;
        if ($d = $clean((array)$c, $id)) q('UPDATE ticket_categories SET name=?, prefix=?, admits=?, price=?, color=?, terms=?, active=? WHERE id=?', [...$d, $id]);
    }
    $new = (array)($_POST['new'] ?? []);
    if (trim((string)($new['name'] ?? '')) !== '') {
        $new['active'] = 1;
        if ($d = $clean($new, null)) { q('INSERT INTO ticket_categories (name, prefix, admits, price, color, terms, active, sort) VALUES (?,?,?,?,?,?,?,?)', [...$d, (int)val('SELECT COALESCE(MAX(sort),0)+1 FROM ticket_categories')]); }
    }
    audit('gentickets.categories', 'ticket categories updated');
    flash($errors ? implode(' ', $errors) : 'Ticket categories saved.', $errors ? 'err' : 'ok');
    redirect('admin/gen-tickets#cats');
}
function admin_gen_category_delete(array $a): void
{
    require_can('events');
    $id = (int)$a['id'];
    if ((int)val('SELECT COUNT(*) FROM gen_tickets WHERE category_id = ?', [$id]) > 0) { q('UPDATE ticket_categories SET active = 0 WHERE id = ?', [$id]); flash('This category already has tickets, so it was switched off instead of deleted.'); }
    else { q('DELETE FROM ticket_categories WHERE id = ?', [$id]); flash('Category deleted.'); }
    redirect('admin/gen-tickets#cats');
}

/* ---------- batches ---------- */
function admin_gen_create(): void
{
    require_can('tickets');
    gentickets_ensure();
    $fail = function (string $m): never { flash($m, 'err'); redirect('admin/gen-tickets#new'); };
    $e = row('SELECT * FROM events WHERE id = ?', [(int)($_POST['event_id'] ?? 0)]) ?? $fail('Choose an event.');
    $c = row('SELECT * FROM ticket_categories WHERE id = ? AND active = 1', [(int)($_POST['category_id'] ?? 0)]) ?? $fail('Choose a ticket category.');
    $from = (int)preg_replace('/\D+/', '', (string)($_POST['from_no'] ?? '1')); $to = (int)preg_replace('/\D+/', '', (string)($_POST['to_no'] ?? '0'));
    if ($from < 1 || $to < $from) $fail('Enter a start number of 1 or more and an end number that is not smaller.');
    $qty = $to - $from + 1;
    if ($qty > GEN_MAX_BATCH) $fail('One batch can hold up to ' . number_format(GEN_MAX_BATCH) . ' tickets. Make several batches for more.');
    $clash = val('SELECT MAX(number) FROM gen_tickets WHERE event_id = ? AND category_id = ? AND number BETWEEN ? AND ?', [$e['id'], $c['id'], $from, $to]);
    if ($clash !== null) {
        $mx = (int)val('SELECT MAX(number) FROM gen_tickets WHERE event_id = ? AND category_id = ?', [$e['id'], $c['id']]);
        $fail('Some of those numbers already exist for ' . $c['name'] . ' at this event (the highest is ' . gen_serial($c, $mx) . '). Start from ' . ($mx + 1) . ' instead.');
    }
    $pdo = db(); $pdo->beginTransaction();
    try {
        q('INSERT INTO gen_batches (event_id, category_id, from_no, to_no, note, created_by) VALUES (?,?,?,?,?,?)', [$e['id'], $c['id'], $from, $to, mb_substr(trim((string)($_POST['note'] ?? '')), 0, 190) ?: null, current_user()['id']]);
        $bid = (int)$pdo->lastInsertId();
        foreach (array_chunk(range($from, $to), 250) as $chunk) {
            $ph = []; $args = [];
            foreach ($chunk as $n) { $ph[] = '(?,?,?,?,?)'; array_push($args, $bid, $e['id'], $c['id'], $n, bin2hex(random_bytes(12))); }
            q('INSERT INTO gen_tickets (batch_id, event_id, category_id, number, token) VALUES ' . implode(',', $ph), $args);
        }
        $pdo->commit();
    } catch (Throwable $ex) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $ex; }
    audit('gentickets.create', $c['name'] . ' ' . gen_serial($c, $from) . ' to ' . gen_serial($c, $to) . ' for ' . $e['title']);
    flash('Created ' . number_format($qty) . ' ' . $c['name'] . ' tickets: ' . gen_serial($c, $from) . ' to ' . gen_serial($c, $to) . '. Download or print them below.');
    redirect('admin/gen-tickets/batch/' . $bid);
}
function admin_gen_batch(array $a): void
{
    require_can('tickets');
    $b = row('SELECT b.*, e.title AS event_title, e.start_at, c.name AS cat_name, c.prefix, c.color FROM gen_batches b JOIN events e ON e.id = b.event_id LEFT JOIN ticket_categories c ON c.id = b.category_id WHERE b.id = ?', [(int)$a['id']]) ?? not_found();
    $status = in_array($_GET['status'] ?? '', ['valid', 'used', 'void'], true) ? $_GET['status'] : '';
    $page = max(1, (int)($_GET['page'] ?? 1)); $per = 100;
    $w = 't.batch_id = ?'; $p = [$b['id']]; if ($status) { $w .= ' AND t.status = ?'; $p[] = $status; }
    $total = (int)val("SELECT COUNT(*) FROM gen_tickets t WHERE $w", $p);
    $tickets = rows("SELECT t.* FROM gen_tickets t WHERE $w ORDER BY t.number LIMIT $per OFFSET " . (($page - 1) * $per), $p);
    $counts = rows('SELECT status, COUNT(*) n FROM gen_tickets WHERE batch_id = ? GROUP BY status', [$b['id']]);
    $c = row('SELECT * FROM ticket_categories WHERE id = ?', [$b['category_id']]) ?? ['name' => '?', 'prefix' => 'TK', 'admits' => 1, 'price' => 0, 'color' => '#D96B27', 'terms' => ''];
    $e = row('SELECT * FROM events WHERE id = ?', [$b['event_id']]);
    $sample = $tickets ? gen_ticket_data($tickets[0], $e, $c) : null;
    admin_view('gentickets_batch', ['title' => 'Ticket batch', 'b' => $b, 'c' => $c, 'tickets' => $tickets, 'total' => $total, 'page' => $page, 'per' => $per, 'status' => $status,
        'counts' => array_column($counts, 'n', 'status'), 'sample' => $sample]);
}
/** Void, restore, check in or undo one ticket. */
function admin_gen_ticket_action(array $a): void
{
    $act = $a['action'];
    if (in_array($act, ['checkin', 'undo'], true)) require_can_any(['checkin', 'tickets']); else require_can('tickets');
    $t = row('SELECT * FROM gen_tickets WHERE id = ?', [(int)$a['id']]) ?? not_found();
    $uid = (int)current_user()['id'];
    switch ($act) {
        case 'void': q("UPDATE gen_tickets SET status='void' WHERE id = ? AND status <> 'used'", [$t['id']]); $m = 'Ticket voided. It will be rejected at the gate.'; break;
        case 'restore': q("UPDATE gen_tickets SET status='valid' WHERE id = ? AND status = 'void'", [$t['id']]); $m = 'Ticket restored.'; break;
        case 'checkin': q("UPDATE gen_tickets SET status='used', used_at=NOW(), used_by=? WHERE id = ? AND status='valid'", [$uid, $t['id']]); $m = 'Ticket checked in.'; break;
        case 'undo': q("UPDATE gen_tickets SET status='valid', used_at=NULL, used_by=NULL WHERE id = ? AND status='used'", [$t['id']]); $m = 'Check-in undone.'; break;
        default: not_found();
    }
    audit('gentickets.' . $act, '#' . $t['id'] . ' number ' . $t['number']);
    flash($m);
    $back = (string)($_POST['back'] ?? '');
    redirect(preg_match('#^admin/(gen-tickets/batch/\d+(\?[A-Za-z0-9=&_%.-]*)?|checkin/[a-f0-9]{24})$#', $back) ? $back : 'admin/gen-tickets/batch/' . (int)$t['batch_id']);
}

/* ---------- download and print ---------- */
/** Ticket data for the selected batches (and optional number range), in print order. */
function gen_collect(array $batchIds, ?int $from, ?int $to, bool $onlyValid): array
{
    $ids = array_values(array_filter(array_map('intval', $batchIds)));
    if (!$ids) return [];
    $in = implode(',', array_fill(0, count($ids), '?')); $w = "t.batch_id IN ($in)"; $p = $ids;
    if ($from !== null) { $w .= ' AND t.number >= ?'; $p[] = $from; }
    if ($to !== null) { $w .= ' AND t.number <= ?'; $p[] = $to; }
    $w .= $onlyValid ? " AND t.status = 'valid'" : " AND t.status <> 'void'";
    $list = rows("SELECT t.* FROM gen_tickets t WHERE $w ORDER BY t.event_id, t.category_id, t.number LIMIT " . (GEN_MAX_PDF + 1), $p);
    $evs = []; $cts = []; $out = [];
    foreach ($list as $g) {
        $evs[$g['event_id']] ??= row('SELECT * FROM events WHERE id = ?', [$g['event_id']]);
        $cts[$g['category_id']] ??= row('SELECT * FROM ticket_categories WHERE id = ?', [$g['category_id']]) ?? ['name' => 'Ticket', 'prefix' => 'TK', 'admits' => 1, 'price' => 0, 'color' => '#D96B27', 'terms' => ''];
        $out[] = gen_ticket_data($g, $evs[$g['event_id']], $cts[$g['category_id']]);
    }
    return $out;
}
function admin_gen_download(): void
{
    require_can('tickets');
    @set_time_limit(300);
    $batch = $_GET['batch'] ?? [];
    $batch = is_array($batch) ? $batch : [$batch];
    $from = ($_GET['from'] ?? '') !== '' ? (int)$_GET['from'] : null; $to = ($_GET['to'] ?? '') !== '' ? (int)$_GET['to'] : null;
    $layout = ($_GET['layout'] ?? 'a4') === 'a5' ? 'a5' : 'a4';
    $items = gen_collect($batch, $from, $to, !empty($_GET['only_valid']));
    if (!$items) { flash('No tickets match that selection.', 'err'); redirect('admin/gen-tickets'); }
    if (count($items) > GEN_MAX_PDF) { flash('That is more than ' . number_format(GEN_MAX_PDF) . ' tickets at once. Use the From and To boxes to download in parts.', 'err'); redirect('admin/gen-tickets'); }
    audit('gentickets.' . (($_GET['mode'] ?? 'pdf') === 'print' ? 'print' : 'pdf'), count($items) . ' tickets');
    if (($_GET['mode'] ?? 'pdf') === 'print') { gen_print_page($items, $layout); exit; }
    $data = tickets_pdf($items, $layout, 'Chill & Bite tickets');
    $name = 'tickets-' . preg_replace('/[^A-Za-z0-9]+/', '-', strtolower($items[0]['serial'])) . ($layout === 'a5' ? '-a5' : '-a4') . '.pdf';
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('Content-Length: ' . strlen($data));
    header('Cache-Control: private, no-store');
    echo $data;
    exit;
}
/** Print-ready page: A4 sheets with three tickets each (or one per A5 page); the browser's Print dialog can also save a PDF. */
function gen_print_page(array $items, string $layout): void
{
    header('Content-Type: text/html; charset=utf-8'); header('Cache-Control: no-store');
    $per = $layout === 'a5' ? 1 : 3;
    $page = $layout === 'a5' ? 'A5 landscape' : 'A4 portrait';
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex"><title>Print tickets</title><style>'
        . '@page{size:' . $page . ';margin:0}*{box-sizing:border-box}body{margin:0;background:#e9e4da;font-family:Helvetica,Arial,sans-serif}'
        . '.bar{position:sticky;top:0;z-index:5;background:#171717;color:#fff;padding:12px 18px;display:flex;gap:14px;align-items:center;flex-wrap:wrap}.bar b{font-size:1rem}.bar button,.bar a{background:#D96B27;color:#fff;border:0;border-radius:8px;padding:10px 18px;font:700 .95rem inherit;cursor:pointer;text-decoration:none}.bar a.alt{background:#444}'
        . '.sheet{width:' . ($layout === 'a5' ? '210mm;height:148mm' : '210mm;height:297mm') . ';margin:14px auto;background:#fff;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:' . ($layout === 'a5' ? '0' : '9mm') . ';page-break-after:always;break-after:page;position:relative;box-shadow:0 4px 18px rgba(0,0,0,.2)}'
        . ($layout === 'a5' ? '.sheet .gt{transform:scale(1.05)}' : '') . '.cut{position:absolute;left:0;right:0;border-top:.4pt dashed #bbb}'
        . ticket_css() . '@media print{body{background:#fff}.bar{display:none}.sheet{margin:0;box-shadow:none}}</style></head><body>'
        . '<div class="bar"><b>' . count($items) . ' ticket' . (count($items) === 1 ? '' : 's') . ' &middot; ' . ($layout === 'a5' ? 'one per A5 landscape page' : 'three per A4 page') . '</b><button onclick="window.print()">Print / Save as PDF</button><a class="alt" href="javascript:history.back()">Back</a></div>';
    foreach (array_chunk($items, $per) as $chunk) {
        echo '<div class="sheet">';
        foreach ($chunk as $i => $t) echo ticket_html($t);
        if ($per === 3) { echo '<span class="cut" style="top:' . (((297 - 3 * 88 - 18) / 2 + 88 + 4.5)) . 'mm"></span><span class="cut" style="top:' . (((297 - 3 * 88 - 18) / 2 + 2 * 88 + 9 + 4.5)) . 'mm"></span>'; }
        echo '</div>';
    }
    echo '</body></html>';
}
