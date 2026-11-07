<?php
declare(strict_types=1);

/* ================= Ticket rates: every event's prices in one place, with a default for new events ================= */
function rates_type_defaults(string $t): array
{
    return ['min' => $t === 'couple' ? 2 : ($t === 'group' || $t === 'corporate' ? 3 : 1), 'max' => $t === 'individual' ? 1 : ($t === 'couple' ? 2 : 20)];
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
    return ['opt' => $opt, 'packages' => implode("\n", array_map(fn($p) => $p['name'] . ' | ' . (int)$p['price'], event_packages($eventId)))];
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
    return ['opt' => $opt, 'packages' => (string)($post['packages'] ?? '')];
}
/** Write rates to an event (replaces its options and table packages). */
function rates_apply(int $eventId, array $r): void
{
    q('DELETE FROM event_reservation_options WHERE event_id = ?', [$eventId]);
    foreach ($r['opt'] as $t => $o)
        q('INSERT INTO event_reservation_options (event_id, type, price_per_guest, min_guests, max_guests) VALUES (?,?,?,?,?)', [$eventId, $t, (int)$o['price'], (int)$o['min'], (int)$o['max']]);
    q('DELETE FROM event_packages WHERE event_id = ?', [$eventId]);
    $sort = 0;
    foreach (preg_split('/\R/', (string)$r['packages']) as $line) {
        if (!str_contains($line, '|')) continue;
        [$pn, $pp] = array_map('trim', explode('|', $line, 2));
        $pn = mb_substr($pn, 0, 80); $pp = (int)preg_replace('/\D+/', '', $pp);
        if ($pn === '' || $pp < 0) continue;
        q('INSERT IGNORE INTO event_packages (event_id, name, price, sort) VALUES (?,?,?,?)', [$eventId, $pn, $pp, $sort++]);
    }
}
/** One line describing an event's prices, for the collapsed card. */
function rates_summary(array $r): string
{
    $bits = [];
    foreach (preg_split('/\R/', (string)$r['packages']) as $l) if (str_contains($l, '|')) { [$n, $p] = array_map('trim', explode('|', $l, 2)); $bits[] = $n . ' ' . number_format((int)preg_replace('/\D+/', '', $p)); }
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
    admin_view('rates', ['title' => 'Ticket rates', 'events' => $evs, 'past' => $past, 'template' => rates_template() ?? ['opt' => [], 'packages' => ''],
        'has_template' => rates_template() !== null, 'others' => rows('SELECT id, title, start_at FROM events ORDER BY start_at DESC LIMIT 80')]);
}
/** Save the rates of one event (id) or the default for new events (id 0). */
function admin_rates_save(array $a): void
{
    require_can('events');
    $id = (int)$a['id'];
    $r = rates_from_post($_POST);
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
