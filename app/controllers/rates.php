<?php
declare(strict_types=1);

/* ================= Ticket rates: every event's prices in one place, with a default for new events ================= */
function rates_type_defaults(string $t): array
{
    return ['min' => $t === 'couple' ? 2 : ($t === 'group' || $t === 'corporate' ? 3 : 1), 'max' => $t === 'individual' ? 1 : ($t === 'couple' ? 2 : 20)];
}
/** Add the admits column (people per table) to a site that was set up before it existed. */
function rates_ensure_cols(): void
{
    static $done = false; if ($done) return; $done = true;
    try {
        if (!(int)val("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'event_packages' AND COLUMN_NAME = 'admits'"))
            db()->exec('ALTER TABLE event_packages ADD COLUMN admits TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER price');
    } catch (PDOException $e) { error_log('rates_ensure_cols: ' . $e->getMessage()); }
}
/** Package lines "Name | price | people it admits" (the last part is optional) as a list of [name, price, admits]. */
function rates_parse_packages(string $text): array
{
    $out = [];
    foreach (preg_split('/\R/', $text) as $line) {
        if (!str_contains($line, '|')) continue;
        $p = array_map('trim', explode('|', $line, 3));
        $n = mb_substr($p[0], 0, 80); $price = (int)preg_replace('/\D+/', '', $p[1] ?? '0'); $ad = max(0, min(50, (int)preg_replace('/\D+/', '', $p[2] ?? '0')));
        if ($n !== '') $out[$n] = [$n, $price, $ad];
    }
    return array_values($out);
}
function rates_packages_text(array $rows): string
{
    return implode("\n", array_map(fn($p) => $p['name'] . ' | ' . (int)$p['price'] . ((int)($p['admits'] ?? 0) > 0 ? ' | ' . (int)$p['admits'] : ''), $rows));
}
/** Saved default rates for new events: ['opt' => [type => [on, price, min, max]], 'packages' => "Name | price" lines] or null when none is saved. */
function rates_template(): ?array
{
    $j = json_decode(setting('rates_template'), true);
    return is_array($j) && isset($j['opt']) ? $j : null;
}
/** The rates of one event in the same shape as the template. */
function rates_of_event(int $eventId): array
{
    $opt = [];
    foreach (event_options($eventId) as $o) $opt[$o['type']] = ['on' => 1, 'price' => (int)$o['price_per_guest'], 'min' => (int)$o['min_guests'], 'max' => (int)$o['max_guests']];
    return ['opt' => $opt, 'packages' => rates_packages_text(event_packages($eventId))];
}
/** Clean a submitted rates form into the common shape. */
function rates_from_post(array $post): array
{
    $opt = [];
    foreach (RES_TYPES as $t => $_) {
        $o = $post['opt'][$t] ?? [];
        if (empty($o['on'])) continue;
        $d = rates_type_defaults($t);
        $min = max(1, (int)($o['min'] ?? $d['min'])); $max = max($min, (int)($o['max'] ?? $d['max']));
        $opt[$t] = ['on' => 1, 'price' => max(0, (int)preg_replace('/\D+/', '', (string)($o['price'] ?? '0'))), 'min' => $min, 'max' => $max];
    }
    $packages = (string)($post['packages'] ?? '');
    if (isset($post['pk']['n']) && is_array($post['pk']['n'])) {          // the package grid: type, price and how many people it admits
        $lines = [];
        foreach ($post['pk']['n'] as $i => $n) {
            $n = mb_substr(trim((string)$n), 0, 80); if ($n === '') continue;
            $p = (int)preg_replace('/\D+/', '', (string)($post['pk']['p'][$i] ?? '0')); $a = max(0, min(50, (int)preg_replace('/\D+/', '', (string)($post['pk']['a'][$i] ?? '0'))));
            $lines[] = str_replace('|', '/', $n) . ' | ' . $p . ($a > 0 ? ' | ' . $a : '');
        }
        $packages = implode("\n", $lines);
    }
    return ['opt' => $opt, 'packages' => $packages];
}
/** Write rates to an event (replaces its options and table packages). */
function rates_apply(int $eventId, array $r): void
{
    q('DELETE FROM event_reservation_options WHERE event_id = ?', [$eventId]);
    foreach ($r['opt'] as $t => $o)
        q('INSERT INTO event_reservation_options (event_id, type, price_per_guest, min_guests, max_guests) VALUES (?,?,?,?,?)', [$eventId, $t, (int)$o['price'], (int)$o['min'], (int)$o['max']]);
    rates_ensure_cols();
    q('DELETE FROM event_packages WHERE event_id = ?', [$eventId]);
    foreach (rates_parse_packages((string)$r['packages']) as $i => [$pn, $pp, $ad])
        q('INSERT IGNORE INTO event_packages (event_id, name, price, admits, sort) VALUES (?,?,?,?,?)', [$eventId, $pn, $pp, $ad, $i]);
}
/** One line describing an event's prices, for the collapsed card. */
function rates_summary(array $r): string
{
    $bits = [];
    foreach (rates_parse_packages((string)$r['packages']) as [$n, $p, $ad]) $bits[] = $n . ' ' . number_format($p) . ($ad ? ' (' . $ad . ' people)' : '');
    foreach ($r['opt'] as $t => $o) if ($t !== 'table' || !$bits) $bits[] = RES_TYPES[$t] . ' ' . ((int)$o['price'] > 0 ? number_format((int)$o['price']) . '/guest' : 'free');
    return $bits ? implode(' · ', array_slice($bits, 0, 7)) : 'No rates set yet';
}

function admin_rates(): void
{
    require_can('events');
    $past = isset($_GET['past']);
    $evs = rows($past ? "SELECT id, title, start_at, status FROM events WHERE start_at < CURDATE() ORDER BY start_at DESC LIMIT 60"
                      : "SELECT id, title, start_at, status FROM events WHERE start_at >= CURDATE() ORDER BY start_at ASC LIMIT 60");
    foreach ($evs as &$e) $e['rates'] = rates_of_event((int)$e['id']); unset($e);
    $upN = (int)val("SELECT COUNT(*) FROM events WHERE start_at >= CURDATE() AND status <> 'cancelled'");
    $cur = ''; foreach ($evs as $e0) if (trim($e0['rates']['packages']) !== '') { $cur = $e0['rates']['packages']; break; }
    rates_ensure_cols();
    $pkgNames = array_column(rows('SELECT name FROM event_packages UNION SELECT package FROM reservations WHERE package IS NOT NULL ORDER BY 1'), 'name');
    admin_view('rates', ['title' => 'Ticket rates', 'pkg_names' => $pkgNames, 'up_n' => $upN, 'cur_packages' => (rates_template()['packages'] ?? '') !== '' ? rates_template()['packages'] : $cur, 'events' => $evs, 'past' => $past, 'template' => rates_template() ?? ['opt' => [], 'packages' => ''],
        'has_template' => rates_template() !== null, 'others' => rows('SELECT id, title, start_at FROM events ORDER BY start_at DESC LIMIT 80')]);
}
/** Save the rates of one event (id) or the default for new events (id 0). */
function admin_rates_save(array $a): void
{
    require_can('events');
    $id = (int)$a['id'];
    $r = rates_from_post($_POST);
    // the Table Reservation option has no row of its own any more: keep what is saved, and switch it on when packages exist
    $cur = $id ? rates_of_event($id)['opt'] : (rates_template()['opt'] ?? []);
    if (!isset($r['opt']['table'])) {
        if (rates_parse_packages($r['packages'])) $r['opt']['table'] = $cur['table'] ?? ['on' => 1, 'price' => 0, 'min' => 2, 'max' => 15];
        elseif (isset($cur['table'])) $r['opt']['table'] = $cur['table'];
    }
    if ($id === 0) {
        save_setting('rates_template', json_encode($r));
        audit('rates.template', 'default rates for new events');
        flash('Default rates saved. New events start with these.');
    } else {
        $title = val('SELECT title FROM events WHERE id = ?', [$id]) ?? not_found();
        rates_apply($id, $r);
        audit('rates.save', "#$id $title");
        flash('Rates saved for ' . $title . '.');
    }
    redirect('admin/rates' . (isset($_POST['past']) ? '?past=1' : '') . '#e' . $id);
}
/** Copy rates from another event (or the default) onto this one. */
function admin_rates_copy(array $a): void
{
    require_can('events');
    $id = (int)$a['id'];
    $from = (string)($_POST['from'] ?? '');
    $src = $from === 'template' ? rates_template() : ((int)$from ? rates_of_event((int)$from) : null);
    if (!$src || !$src['opt'] && trim($src['packages']) === '') { flash('Nothing to copy from that choice.', 'err'); redirect('admin/rates#e' . $id); }
    if ($id === 0) { save_setting('rates_template', json_encode($src)); flash('Default rates copied.'); }
    else { $title = val('SELECT title FROM events WHERE id = ?', [$id]) ?? not_found(); rates_apply($id, $src); audit('rates.copy', "#$id from $from"); flash('Rates copied to ' . $title . '.'); }
    redirect('admin/rates' . (isset($_POST['past']) ? '?past=1' : '') . '#e' . $id);
}

/** Set the same table packages on every upcoming event at once (and keep them as the default for new events). */
function rates_packages_clean(string $text): array
{
    $out = [];
    foreach (rates_parse_packages($text) as [$n, $p, $ad]) $out[$n] = [$p, $ad];
    return $out;
}
function admin_rates_apply_all(): void
{
    require_can('events');
    $pk = rates_packages_clean((string)($_POST['packages'] ?? ''));
    if (!$pk) { flash('Enter at least one package, one per line, like: Silver | 200000', 'err'); redirect('admin/rates'); }
    rates_ensure_cols();
    $text = implode("\n", array_map(fn($n, $v) => $n . ' | ' . $v[0] . ($v[1] ? ' | ' . $v[1] : ''), array_keys($pk), $pk));
    $ids = array_column(rows("SELECT id FROM events WHERE start_at >= CURDATE() AND status <> 'cancelled'"), 'id');
    foreach ($ids as $id) {
        $id = (int)$id;
        q('DELETE FROM event_packages WHERE event_id = ?', [$id]);
        $sort = 0;
        foreach ($pk as $n => $v) q('INSERT IGNORE INTO event_packages (event_id, name, price, admits, sort) VALUES (?,?,?,?,?)', [$id, (string)$n, $v[0], $v[1], $sort++]);
        // packages only show when the Table Reservation type is on
        if (!val("SELECT id FROM event_reservation_options WHERE event_id = ? AND type = 'table'", [$id]))
            q("INSERT INTO event_reservation_options (event_id, type, price_per_guest, min_guests, max_guests) VALUES (?, 'table', 0, 2, 15)", [$id]);
    }
    $t = rates_template() ?? ['opt' => [], 'packages' => ''];
    $t['packages'] = $text;
    if (!isset($t['opt']['table'])) $t['opt']['table'] = ['on' => 1, 'price' => 0, 'min' => 2, 'max' => 15];
    save_setting('rates_template', json_encode($t));
    audit('rates.apply_all', count($pk) . ' packages to ' . count($ids) . ' upcoming events');
    flash('Applied ' . count($pk) . ' table packages to ' . count($ids) . ' upcoming event' . (count($ids) === 1 ? '' : 's') . ' and saved them as the default for new events.');
    redirect('admin/rates');
}

/**
 * Set how many people a table package admits, and (if asked) update the tickets that were already booked on that package.
 * scope: 'package' = only the package (new bookings), 'approved' = also approved tickets, 'all' = also bookings still awaiting approval.
 */
function admin_rates_package_size(): void
{
    require_can('events');
    rates_ensure_cols();
    $name = mb_substr(trim((string)($_POST['package'] ?? '')), 0, 80);
    $admits = (int)($_POST['admits'] ?? 0);
    $scope = in_array($_POST['scope'] ?? '', ['package', 'approved', 'all'], true) ? $_POST['scope'] : 'package';
    $which = (string)($_POST['events'] ?? 'all');
    if ($name === '' || $admits < 1 || $admits > 50) { flash('Choose a package and enter how many people it admits (1 to 50).', 'err'); redirect('admin/rates#size'); }

    $where = ''; $args = [];
    if ($which === 'upcoming') $where = ' AND event_id IN (SELECT id FROM events WHERE start_at >= CURDATE())';
    elseif (ctype_digit($which) && (int)$which > 0) { $where = ' AND event_id = ?'; $args[] = (int)$which; }
    $pk = q('UPDATE event_packages SET admits = ? WHERE name = ?' . $where, [$admits, $name, ...$args])->rowCount();
    $evCount = (int)val('SELECT COUNT(*) FROM event_packages WHERE name = ?' . $where, [$name, ...$args]);

    $msg = "$name now admits $admits people at $evCount event" . ($evCount === 1 ? '' : 's') . '.';
    $tickets = 0; $pending = 0;
    if ($scope !== 'package') {
        $st = $scope === 'all' ? "('confirmed','pending')" : "('confirmed')";
        $resWhere = 'package = ?' . $where . " AND status IN $st AND guests <> ?";
        $tickets = (int)val("SELECT COUNT(*) FROM reservations WHERE status = 'confirmed' AND $resWhere", [$name, ...$args, $admits]);
        $pending = $scope === 'all' ? (int)val("SELECT COUNT(*) FROM reservations WHERE status = 'pending' AND $resWhere", [$name, ...$args, $admits]) : 0;
        q("UPDATE reservations SET guests = ? WHERE $resWhere", [$admits, $name, ...$args, $admits]);
        $msg .= " Updated $tickets approved ticket" . ($tickets === 1 ? '' : 's') . ($scope === 'all' ? " and $pending booking" . ($pending === 1 ? '' : 's') . ' awaiting approval' : '') . '. Guests see the new number on their ticket straight away.';
        // a bigger table can push an event over its capacity: say so instead of hiding it
        $over = [];
        foreach (rows("SELECT e.id, e.title, e.capacity, (SELECT COALESCE(SUM(guests),0) FROM reservations r WHERE r.event_id = e.id AND r.status IN ('pending','confirmed')) AS booked
                       FROM events e WHERE e.capacity IS NOT NULL AND e.id IN (SELECT DISTINCT event_id FROM reservations WHERE package = ?)", [$name]) as $e)
            if ((int)$e['booked'] > (int)$e['capacity']) $over[] = $e['title'] . ' (' . (int)$e['booked'] . ' booked, capacity ' . (int)$e['capacity'] . ')';
        if ($over) $msg .= ' Careful: over capacity now: ' . implode('; ', $over) . '.';
    }
    audit('rates.package_size', "$name admits $admits, scope $scope, events $which: $evCount events, $tickets approved, $pending pending");
    flash($msg, strpos($msg, 'Careful') !== false ? 'err' : 'ok');
    redirect('admin/rates#size');
}