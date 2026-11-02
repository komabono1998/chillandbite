<?php
/** Reusable view fragments. Every function echoes escaped HTML. */

function media(?string $file, string $alt, string $class = '', string $fallbackLabel = '', ?string $fallbackSrc = null): void
{
    if ($file || $fallbackSrc) {
        echo '<img class="' . h($class) . '" src="' . h($file ? upload_url($file) : $fallbackSrc) . '" alt="' . h($alt) . '" loading="lazy" decoding="async">';
        return;
    }
    echo '<div class="ph ' . h($class) . '" role="img" aria-label="' . h($alt) . '"><span>' . h($fallbackLabel ?: 'C&amp;B') . '</span></div>';
}

function status_badge(string $s): string
{
    $map = ['confirmed' => 'ok', 'published' => 'ok', 'won' => 'ok', 'pending' => 'warn', 'draft' => 'muted', 'scheduled' => 'warn',
            'new' => 'warn', 'in_progress' => 'warn', 'cancelled' => 'err', 'rejected' => 'err', 'archived' => 'muted', 'closed' => 'muted'];
    return '<span class="badge badge-' . ($map[$s] ?? 'muted') . '">' . h(str_replace('_', ' ', $s)) . '</span>';
}

function event_card(array $e, bool $dark = false): void
{
    $d = strtotime($e['start_at']);
    $cancelled = $e['status'] === 'cancelled';
    ?>
    <article class="card event-card<?= $dark ? ' card-dark' : '' ?>">
      <a class="event-poster" href="<?= url('events/' . h($e['slug'])) ?>" tabindex="-1" aria-hidden="true">
        <?php media($e['poster'], $e['title'], 'poster-img', date('M', $d), photo_from(str_contains(strtolower($e['category'] ?? ''), 'comedy') ? 'people' : 'crowd', (int)$e['id'])); ?>
        <span class="date-chip"><b><?= date('j', $d) ?></b><?= date('M', $d) ?></span>
      </a>
      <div class="card-body">
        <?php if (!empty($e['category'])): ?><span class="eyebrow"><?= h($e['category']) ?></span><?php endif; ?>
        <h3><a href="<?= url('events/' . h($e['slug'])) ?>"><?= h($e['title']) ?></a> <?= $cancelled ? '<span class="badge badge-err">Cancelled</span>' : '' ?></h3>
        <p class="meta"><?= fmt_date($e['start_at']) ?> · <?= fmt_time($e['start_at']) ?> · <?= h($e['venue']) ?></p>
        <p><?= h($e['short_desc']) ?></p>
        <div class="btn-row">
          <a class="btn btn-ghost btn-sm" href="<?= url('events/' . h($e['slug'])) ?>">View event</a>
          <?php if (!$cancelled): ?><a class="btn btn-primary btn-sm" href="<?= url('events/' . h($e['slug']) . '/reserve') ?>">Reserve now</a><?php endif; ?>
        </div>
      </div>
    </article>
    <?php
}

function article_card(array $a): void
{
    ?>
    <article class="card article-card">
      <a class="article-img" href="<?= url('news/' . h($a['slug'])) ?>" tabindex="-1" aria-hidden="true"><?php article_visual($a); ?></a>
      <div class="card-body">
        <p class="meta">
          <?php if (!empty($a['category'])): ?><span class="eyebrow"><?= h($a['category']) ?></span><?php endif; ?>
          <span class="tag tag-<?= h($a['label']) ?>"><?= h(NEWS_LABELS[$a['label']] ?? $a['label']) ?></span>
        </p>
        <h3><a href="<?= url('news/' . h($a['slug'])) ?>"><?= h($a['title']) ?></a></h3>
        <p><?= h(excerpt($a['excerpt'] ?: $a['body'], 120)) ?></p>
        <p class="meta small"><?= fmt_date($a['published_at'], 'j M Y') ?><?= !empty($a['source_name']) ? ' · via ' . h($a['source_name']) : (!empty($a['author']) ? ' · ' . h($a['author']) : '') ?></p>
      </div>
    </article>
    <?php
}

function video_embed(array $v, bool $big = false): void
{
    $id = youtube_id($v['url']);
    if (!$id) { echo '<a class="btn btn-ghost" href="' . h($v['url']) . '" target="_blank" rel="noopener">Watch: ' . h($v['title']) . '</a>'; return; }
    ?>
    <figure class="video<?= $big ? ' video-big' : '' ?>">
      <button type="button" class="video-play" data-yt="<?= h($id) ?>" aria-label="Play video: <?= h($v['title']) ?>">
        <img src="https://i.ytimg.com/vi/<?= h($id) ?>/hqdefault.jpg" alt="" loading="lazy">
        <span class="play-icon" aria-hidden="true">▶</span>
      </button>
      <figcaption><?= h($v['title']) ?></figcaption>
    </figure>
    <?php
}

function share_buttons(string $title, string $path): void
{
    $link = abs_url($path);
    ?>
    <div class="share" aria-label="Share">
      <span>Share:</span>
      <a class="btn btn-ghost btn-sm" target="_blank" rel="noopener" href="https://wa.me/?text=<?= rawurlencode($title . ' ' . $link) ?>">WhatsApp</a>
      <a class="btn btn-ghost btn-sm" target="_blank" rel="noopener" href="https://www.facebook.com/sharer/sharer.php?u=<?= rawurlencode($link) ?>">Facebook</a>
      <a class="btn btn-ghost btn-sm" target="_blank" rel="noopener" href="https://twitter.com/intent/tweet?text=<?= rawurlencode($title) ?>&amp;url=<?= rawurlencode($link) ?>">X</a>
    </div>
    <?php
}

function field(string $name, string $label, array $o = []): void
{
    $type = $o['type'] ?? 'text';
    $val = $o['value'] ?? old($name, '');
    $req = !empty($o['required']) ? ' required' : '';
    $extra = $o['attrs'] ?? '';
    echo '<div class="field' . (!empty($o['class']) ? ' ' . $o['class'] : '') . '"><label for="f_' . h($name) . '">' . h($label) . (!empty($o['required']) ? ' <span aria-hidden="true">*</span>' : '') . '</label>';
    if ($type === 'textarea') {
        echo '<textarea id="f_' . h($name) . '" name="' . h($name) . '" rows="' . ($o['rows'] ?? 4) . '"' . $req . ' ' . $extra . '>' . h($val) . '</textarea>';
    } elseif ($type === 'select') {
        echo '<select id="f_' . h($name) . '" name="' . h($name) . '"' . $req . ' ' . $extra . '>';
        foreach ($o['options'] as $k => $t) echo '<option value="' . h($k) . '"' . ((string)$k === (string)$val ? ' selected' : '') . '>' . h($t) . '</option>';
        echo '</select>';
    } else {
        echo '<input id="f_' . h($name) . '" type="' . h($type) . '" name="' . h($name) . '" value="' . h($val) . '"' . $req . ' ' . $extra . '>';
    }
    if (!empty($o['help'])) echo '<small>' . h($o['help']) . '</small>';
    echo '</div>';
}
function honeypot(): void
{
    echo '<div class="hp" aria-hidden="true"><label>Leave this empty<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>';
}

/* ---------- Event photography ---------- */
const PHOTO_SETS = [
    'crowd'  => [1, 3, 4, 5, 6, 14, 17, 18, 37, 39, 40, 45, 48, 51, 52],
    'stage'  => [41, 44, 53, 27, 32, 47, 42, 43, 20],
    'people' => [7, 8, 9, 10, 12, 13, 15, 16, 19, 21, 49, 36],
    'social' => [11, 22, 31, 33, 35, 38, 46, 50, 34],
    'food'   => [2, 23, 25, 26, 30],
];
function photo(int $n): string { return asset(sprintf('img/photos/photo-%02d.jpg', $n)); }
function photo_from(string $set, int $seed): string
{
    $p = PHOTO_SETS[$set] ?? PHOTO_SETS['crowd'];
    return photo($p[abs($seed) % count($p)]);
}
/** Left-to-right auto-scrolling photo strip: pauses on hover, photos lift and zoom. */
function photo_strip(array $nums, string $label = 'Moments from Chill & Bite', string $class = ''): void
{
    $items = '';
    foreach ($nums as $n) {
        $items .= '<a class="strip-item" href="' . h(photo($n)) . '" data-lightbox><img src="' . h(photo($n)) . '" alt="Chill &amp; Bite MEATINGS event moment" loading="lazy" decoding="async"></a>';
    }
    echo '<section class="strip ' . h($class) . '" aria-label="' . h($label) . '"><div class="strip-track">' . $items . '<span class="strip-dup" aria-hidden="true">' . str_replace(' data-lightbox', ' tabindex="-1"', $items) . '</span></div></section>';
}
/** Shuffle a photo set with a stable seed so strips differ per page but do not change on reload. */
function strip_nums(array $sets, int $seed, int $max = 14): array
{
    $n = [];
    foreach ($sets as $s) $n = array_merge($n, PHOTO_SETS[$s]);
    $n = array_values(array_unique($n));
    mt_srand($seed); shuffle($n); mt_srand();
    return array_slice($n, 0, $max);
}

/** One partner logo tile; links to the partner's website when they gave one. */
function sponsor_item(array $s): void
{
    $inner = $s['logo']
        ? '<img src="' . h(upload_url($s['logo'])) . '" alt="' . h($s['name']) . '" loading="lazy" decoding="async">'
        : '<span>' . h($s['name']) . '</span>';
    if (!empty($s['website']) && preg_match('#^https?://#i', $s['website']))
        $inner = '<a href="' . h($s['website']) . '" target="_blank" rel="noopener sponsored nofollow" title="' . h($s['name']) . '">' . $inner . '</a>';
    echo '<li>' . $inner . '</li>';
}

/**
 * Photo wall: rows of photos drifting in alternating directions (left to right, then right to left).
 * Photos are dealt round-robin into rows so neighbours differ; each row repeats its photos for a seamless loop.
 */
function photo_wall(array $images, int $rows = 3): void
{
    if (!$images) return;
    $buckets = array_fill(0, $rows, []);
    foreach (array_values($images) as $i => $img) $buckets[$i % $rows][] = $img;
    $widths = [300, 220, 270, 200, 285, 240];            // tile widths give the rows a rhythm
    $durations = [110, 140, 95];                           // seconds per loop: each row moves at its own pace
    echo '<div class="wall" data-stagger aria-label="Photo wall of event moments">';
    foreach ($buckets as $r => $items) {
        if (!$items) continue;
        while (count($items) < 8) $items = array_merge($items, $items);
        $tiles = ''; $dup = '';
        foreach ($items as $k => $img) {
            $w = $widths[($k + $r * 2) % count($widths)];
            $src = h(upload_url($img['file']));
            $alt = h($img['caption'] ?: 'Chill & Bite MEATINGS event photo');
            $tiles .= '<a class="wall-item" style="--w:' . $w . 'px" href="' . $src . '" data-lightbox><img src="' . $src . '" alt="' . $alt . '" loading="lazy" decoding="async"></a>';
            $dup .= '<a class="wall-item" style="--w:' . $w . 'px" href="' . $src . '" tabindex="-1" aria-hidden="true"><img src="' . $src . '" alt="" loading="lazy" decoding="async"></a>';
        }
        echo '<div class="wall-row wall-' . ($r % 2 === 0 ? 'ltr' : 'rtl') . '" style="--r:' . $r . ';--dur:' . $durations[$r % count($durations)] . 's"><div class="wall-track">'
            . $tiles . '<span class="wall-dup" aria-hidden="true">' . $dup . '</span></div></div>';
    }
    echo '</div>';
}

/** Article image, or a clean category tile when the story has no photo (keeps news from being illustrated with unrelated party photos). */
const NEWS_ICONS = ['uganda-news' => '📰', 'world' => '🌍', 'business' => '💼', 'sport' => '⚽', 'entertainment' => '🎭', 'gossip' => '💬', 'music' => '🎵',
    'lifestyle' => '✨', 'events' => '🎉', 'community' => '🤝', 'trending' => '🔥'];
function article_visual(array $a, string $class = 'article-img-el'): void
{
    if (!empty($a['image'])) { echo '<img class="' . h($class) . '" src="' . h(upload_url($a['image'])) . '" alt="' . h($a['title']) . '" loading="lazy" decoding="async">'; return; }
    $slug = $a['category_slug'] ?? slugify((string)($a['category'] ?? 'news'));
    echo '<span class="news-tile news-' . h($slug) . ' ' . h($class) . '" role="img" aria-label="' . h($a['category'] ?? 'News') . '"><b aria-hidden="true">' . (NEWS_ICONS[$slug] ?? '📰') . '</b><span>' . h($a['category'] ?? 'The Gulu Chronicles') . '</span></span>';
}