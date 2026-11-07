<?php
declare(strict_types=1);

/* ================= Visitor statistics (Admin > Visitors) ================= */
function admin_analytics(): void
{
    require_can('analytics');
    try { db()->query('SELECT 1 FROM page_views LIMIT 1'); } catch (Throwable $e) { analytics_ensure_tables(); }
    [$from, $to, $range] = analytics_range();
    $r = analytics_report($from, $to);
    admin_view('analytics', ['title' => 'Visitors', 'r' => $r, 'range' => $range, 'from' => $from, 'to' => $to]);
}

/** Rows for the exports. Dataset names: visitors (everything in one table), visitor_pages, visitor_countries, visitor_daily. */
function analytics_export_dataset(string $name): ?array
{
    [$from, $to] = analytics_range();
    try { $r = analytics_report($from, $to); } catch (Throwable $e) { return null; }
    $sub = 'Period ' . $from . ' to ' . $to . ' | ' . number_format($r['views']) . ' page views, ' . number_format($r['visitors']) . ' visitors';
    $views = max(1, $r['views']);
    $share = fn(int $n) => round($n / $views * 100, 1);
    switch ($name) {
        case 'visitor_pages':
            $rows = [];
            foreach ($r['pages'] as $p) $rows[] = [$p['label'], $p['path'], (int)$p['views'], (int)$p['visitors'], $share((int)$p['views'])];
            return ['Most visited pages', $sub, ['Page', 'Address', 'Page views', 'Visitors', 'Share of views (%)'], $rows];
        case 'visitor_countries':
            $rows = [];
            foreach ($r['countries'] as $c) $rows[] = [country_name($c['country']), (string)$c['country'], (int)$c['views'], (int)$c['visitors'], $share((int)$c['views'])];
            return ['Visitors by country', $sub, ['Country', 'Code', 'Page views', 'Visitors', 'Share of views (%)'], $rows];
        case 'visitor_daily':
            $rows = [];
            foreach ($r['daily'] as $d => $v) $rows[] = [$d, date('D', strtotime($d)), $v['views'], $v['visitors']];
            return ['Visitors per day', $sub, ['Date', 'Day', 'Page views', 'Visitors'], $rows];
        case 'visitors':
            $rows = [['Summary', 'Page views', $r['views'], $r['visitors'], 100]];
            $rows[] = ['Summary', 'Pages per visitor', $r['visitors'] ? round($r['views'] / $r['visitors'], 2) : 0, '', ''];
            foreach ($r['pages'] as $p) $rows[] = ['Page', $p['label'], (int)$p['views'], (int)$p['visitors'], $share((int)$p['views'])];
            foreach ($r['countries'] as $c) $rows[] = ['Country', country_name($c['country']), (int)$c['views'], (int)$c['visitors'], $share((int)$c['views'])];
            foreach ($r['sources'] as $s) $rows[] = ['Traffic source', ucfirst($s['source']), (int)$s['views'], '', $share((int)$s['views'])];
            foreach ($r['referrers'] as $s) $rows[] = ['Referrer', $s['ref_host'], (int)$s['views'], '', $share((int)$s['views'])];
            foreach ($r['devices'] as $d) $rows[] = ['Device', ucfirst($d['device']), (int)$d['views'], (int)$d['visitors'], $share((int)$d['views'])];
            foreach ($r['browsers'] as $b) $rows[] = ['Browser', (string)$b['browser'], (int)$b['views'], '', $share((int)$b['views'])];
            foreach ($r['daily'] as $d => $v) $rows[] = ['Day', $d, $v['views'], $v['visitors'], $share($v['views'])];
            return ['Visitor statistics report', $sub, ['Section', 'Item', 'Page views', 'Visitors', 'Share of views (%)'], $rows];
    }
    return null;
}

/* ================= Update database (admin) ================= */
/** Applies new tables and columns after an update, using the installer. Safe to run repeatedly. */
function admin_migrate(): void
{
    require_can('settings');
    @set_time_limit(180);
    if (!defined('WEB_SETUP')) define('WEB_SETUP', true);
    $argv = [];
    ob_start();
    try { include ROOT . '/database/install.php'; $out = trim((string)ob_get_clean()); $ok = true; }
    catch (Throwable $e) { $out = trim((string)ob_get_clean() . ' ' . $e->getMessage()); $ok = false; }
    audit('system.migrate', $ok ? 'ok' : 'failed');
    flash($ok ? 'Database updated. All new tables and columns are in place.' : 'The update could not finish: ' . excerpt($out, 500), $ok ? 'ok' : 'err');
    redirect('admin/settings');
}
