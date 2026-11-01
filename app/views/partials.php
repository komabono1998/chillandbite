<?php
/** Reusable view fragments. Every function echoes escaped HTML. */

function media(?string $file, string $alt, string $class = '', string $fallbackLabel = ''): void
{
    if ($file) {
        echo '<img class="' . h($class) . '" src="' . h(upload_url($file)) . '" alt="' . h($alt) . '" loading="lazy">';
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
        <?php media($e['poster'], $e['title'], 'poster-img', date('M', $d)); ?>
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
      <a class="article-img" href="<?= url('news/' . h($a['slug'])) ?>" tabindex="-1" aria-hidden="true"><?php media($a['image'], $a['title'], 'article-img-el', 'Buzz'); ?></a>
      <div class="card-body">
        <p class="meta">
          <?php if (!empty($a['category'])): ?><span class="eyebrow"><?= h($a['category']) ?></span><?php endif; ?>
          <span class="tag tag-<?= h($a['label']) ?>"><?= h(NEWS_LABELS[$a['label']] ?? $a['label']) ?></span>
        </p>
        <h3><a href="<?= url('news/' . h($a['slug'])) ?>"><?= h($a['title']) ?></a></h3>
        <p><?= h(excerpt($a['excerpt'] ?: $a['body'], 120)) ?></p>
        <p class="meta small"><?= fmt_date($a['published_at'], 'j M Y') ?><?= !empty($a['author']) ? ' · ' . h($a['author']) : '' ?></p>
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
