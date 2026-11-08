<?php
declare(strict_types=1);

/*
 * Printable landscape tickets: the same design as a PDF (A4 sheets with three tickets, or one per A5 page) and as HTML for browser printing.
 * Pure PHP, no libraries. QR codes come from app/qr.php.
 */
final class MiniPdf
{
    private array $pages = [];
    private int $i = -1;
    public function __construct(private string $title) {}
    public function page(float $w, float $h): void { $this->pages[] = ['w' => $w, 'h' => $h, 'c' => '']; $this->i = count($this->pages) - 1; }
    private function add(string $op): void { $this->pages[$this->i]['c'] .= $op . "\n"; }
    private static function f(float $n): string { return rtrim(rtrim(number_format($n, 3, '.', ''), '0'), '.') ?: '0'; }
    private static function rgb(string $hex): string
    {
        $hex = ltrim($hex, '#'); if (strlen($hex) !== 6) $hex = '000000';
        return self::f(hexdec(substr($hex, 0, 2)) / 255) . ' ' . self::f(hexdec(substr($hex, 2, 2)) / 255) . ' ' . self::f(hexdec(substr($hex, 4, 2)) / 255);
    }
    /** Filled rectangle; y is measured from the top of the page. */
    public function rect(float $x, float $y, float $w, float $h, string $fill): void
    {
        $H = $this->pages[$this->i]['h'];
        $this->add('q ' . self::rgb($fill) . ' rg ' . self::f($x) . ' ' . self::f($H - $y - $h) . ' ' . self::f($w) . ' ' . self::f($h) . ' re f Q');
    }
    public function frame(float $x, float $y, float $w, float $h, string $stroke, float $lw): void
    {
        $H = $this->pages[$this->i]['h'];
        $this->add('q ' . self::rgb($stroke) . ' RG ' . self::f($lw) . ' w ' . self::f($x) . ' ' . self::f($H - $y - $h) . ' ' . self::f($w) . ' ' . self::f($h) . ' re S Q');
    }
    /** Rounded filled rectangle. */
    public function pill(float $x, float $y, float $w, float $h, float $r, string $fill): void
    {
        $H = $this->pages[$this->i]['h']; $y = $H - $y - $h; $k = 0.5523 * $r;
        $p = self::f($x + $r) . ' ' . self::f($y) . ' m ' . self::f($x + $w - $r) . ' ' . self::f($y) . ' l '
            . self::f($x + $w - $r + $k) . ' ' . self::f($y) . ' ' . self::f($x + $w) . ' ' . self::f($y + $r - $k) . ' ' . self::f($x + $w) . ' ' . self::f($y + $r) . ' c '
            . self::f($x + $w) . ' ' . self::f($y + $h - $r) . ' l '
            . self::f($x + $w) . ' ' . self::f($y + $h - $r + $k) . ' ' . self::f($x + $w - $r + $k) . ' ' . self::f($y + $h) . ' ' . self::f($x + $w - $r) . ' ' . self::f($y + $h) . ' c '
            . self::f($x + $r) . ' ' . self::f($y + $h) . ' l '
            . self::f($x + $r - $k) . ' ' . self::f($y + $h) . ' ' . self::f($x) . ' ' . self::f($y + $h - $r + $k) . ' ' . self::f($x) . ' ' . self::f($y + $h - $r) . ' c '
            . self::f($x) . ' ' . self::f($y + $r) . ' l '
            . self::f($x) . ' ' . self::f($y + $r - $k) . ' ' . self::f($x + $r - $k) . ' ' . self::f($y) . ' ' . self::f($x + $r) . ' ' . self::f($y) . ' c h';
        $this->add('q ' . self::rgb($fill) . ' rg ' . $p . ' f Q');
    }
    public function line(float $x1, float $y1, float $x2, float $y2, string $stroke, float $lw, ?string $dash = null): void
    {
        $H = $this->pages[$this->i]['h'];
        $this->add('q ' . self::rgb($stroke) . ' RG ' . self::f($lw) . ' w ' . ($dash ? '[' . $dash . '] 0 d ' : '') . self::f($x1) . ' ' . self::f($H - $y1) . ' m ' . self::f($x2) . ' ' . self::f($H - $y2) . ' l S Q');
    }
    /** Text turned 90 degrees (reads upwards), centred on (x, y) measured from the top. */
    public function vtext(float $x, float $y, string $s, float $size, bool $bold, string $color): void
    {
        $s = pdf_cp($s); $w = pdf_w($s, $size, $bold); $H = $this->pages[$this->i]['h'];
        $this->add('BT /' . ($bold ? 'F2' : 'F1') . ' ' . self::f($size) . ' Tf ' . self::rgb($color) . ' rg 0 1 -1 0 ' . self::f($x + 0.36 * $size) . ' ' . self::f(($H - $y) - $w / 2) . ' Tm (' . pdf_esc($s) . ') Tj ET');
    }
    /** A line through several points (used for check marks). */
    public function poly(array $pts, string $stroke, float $lw): void
    {
        $H = $this->pages[$this->i]['h']; $path = '';
        foreach ($pts as $k => $pt) $path .= self::f($pt[0]) . ' ' . self::f($H - $pt[1]) . ($k === 0 ? ' m ' : ' l ');
        $this->add('q ' . self::rgb($stroke) . ' RG ' . self::f($lw) . ' w 1 J 1 j ' . $path . 'S Q');
    }
    /** Text with its baseline at y (from the top). align: l, c or r (x is then the left, centre or right edge). */
    public function text(float $x, float $y, string $s, float $size, bool $bold = false, string $color = '#000000', string $align = 'l'): void
    {
        $s = pdf_cp($s); $w = pdf_w($s, $size, $bold);
        if ($align === 'c') $x -= $w / 2; elseif ($align === 'r') $x -= $w;
        $H = $this->pages[$this->i]['h'];
        $this->add('BT /' . ($bold ? 'F2' : 'F1') . ' ' . self::f($size) . ' Tf ' . self::rgb($color) . ' rg ' . self::f($x) . ' ' . self::f($H - $y) . ' Td (' . pdf_esc($s) . ') Tj ET');
    }
    /** A QR code with its top-left corner at (x, y), size in points, white quiet zone included. */
    public function qr(string $text, float $x, float $y, float $size, int $quiet = 2): void
    {
        $m = qr_matrix($text); $n = count($m); $mod = $size / ($n + 2 * $quiet); $H = $this->pages[$this->i]['h'];
        $this->rect($x, $y, $size, $size, '#ffffff');
        $ops = '';
        foreach ($m as $r => $row) {
            $c = 0;
            while ($c < $n) {
                if ($row[$c]) { $s = $c; while ($c < $n && $row[$c]) $c++; $ops .= self::f($x + ($s + $quiet) * $mod) . ' ' . self::f($H - ($y + ($r + $quiet) * $mod) - $mod) . ' ' . self::f(($c - $s) * $mod) . ' ' . self::f($mod) . " re\n"; }
                else $c++;
            }
        }
        $this->add("q 0 0 0 rg\n" . $ops . "f Q");
    }
    public function output(): string
    {
        $objs = []; $n = 5 + count($this->pages) * 2; $kids = [];
        $objs[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        for ($p = 0; $p < count($this->pages); $p++) $kids[] = (6 + $p * 2 + 1) . ' 0 R';
        $objs[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . count($this->pages) . ' >>';
        $objs[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objs[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
        $objs[5] = '<< /Title (' . pdf_esc(pdf_cp($this->title)) . ') /Producer (Chill & Bite MEATINGS) /CreationDate (D:' . date('YmdHis') . ') >>';
        foreach ($this->pages as $p => $pg) {
            $c = gzcompress($pg['c'], 6);
            $objs[6 + $p * 2] = '<< /Length ' . strlen($c) . ' /Filter /FlateDecode >>' . "\nstream\n" . $c . "\nendstream";
            $objs[7 + $p * 2] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . self::f($pg['w']) . ' ' . self::f($pg['h']) . '] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents ' . (6 + $p * 2) . ' 0 R >>';
        }
        $out = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n"; $off = [];
        ksort($objs);
        foreach ($objs as $i => $body) { $off[$i] = strlen($out); $out .= $i . " 0 obj\n" . $body . "\nendobj\n"; }
        $xref = strlen($out);
        $out .= "xref\n0 " . ($n + 1) . "\n0000000000 65535 f \n";
        for ($i = 1; $i <= $n; $i++) $out .= sprintf("%010d 00000 n \n", $off[$i]);
        return $out . "trailer\n<< /Size " . ($n + 1) . " /Root 1 0 R /Info 5 0 R >>\nstartxref\n" . $xref . "\n%%EOF";
    }
}

/* ---------- Ticket content ---------- */
const TICKET_W_MM = 190, TICKET_H_MM = 88, TICKET_W = 538.6, TICKET_H = 249.4;       // the design is drawn on a 538.6 x 249.4 pt canvas (190 x 88 mm)
function gen_serial(array $cat, int $number): string { return strtoupper((string)$cat['prefix']) . '-' . str_pad((string)$number, 4, '0', STR_PAD_LEFT); }
function gen_verify(string $token): string { return strtoupper(substr(hash_hmac('sha256', $token, app_secret()), 0, 4)); }
/** The "what this ticket comes with" lines the admin wrote for a category (one per line, up to six). */
function gen_perks(string $text): array
{
    $out = [];
    foreach (preg_split('/\R/', $text) as $l) { $l = trim($l); if ($l !== '') $out[] = mb_substr($l, 0, 60); if (count($out) === 6) break; }
    return $out;
}
/** Everything printed on one ticket. $g is a gen_tickets row; $e the event; $c the category. */
function gen_ticket_data(array $g, array $e, array $c): array
{
    $admits = max(1, (int)$c['admits']); $ts = strtotime((string)$e['start_at']);
    return ['brand' => 'CHILL & BITE MEATINGS', 'event' => (string)$e['title'], 'day' => date('j', $ts), 'mon' => strtoupper(date('M Y', $ts)), 'dow' => strtoupper(date('D', $ts)), 'time' => fmt_time($e['start_at']),
        'venue' => trim($e['venue'] . ', ' . $e['location'], ', '), 'cat' => (string)$c['name'], 'color' => (string)$c['color'] ?: '#D96B27',
        'admits' => $admits === 1 ? 'ADMIT ONE' : 'ADMITS ' . $admits, 'price' => (int)$c['price'] > 0 ? money($c['price']) : '', 'perks' => gen_perks((string)($c['perks'] ?? '')),
        'serial' => gen_serial($c, (int)$g['number']), 'verify' => gen_verify((string)$g['token']), 'qr' => abs_url('admin/checkin/' . $g['token']),
        'terms' => trim((string)$c['terms']) ?: 'Valid for one entry only. Not refundable. Keep this ticket safe: copies are rejected at the gate.'];
}

/**
 * The ticket design as a list of drawing steps on the 538.6 x 249.4 pt canvas. The PDF and the on-screen/print version both draw from this list,
 * so they always look the same. Steps: rect, frame, pill, line, text, vtext (upright text turned 90 degrees), poly, qr.
 * Every element has its own band of the ticket, so long names or many perks can never run into each other.
 */
function ticket_ops(array $t): array
{
    $W = TICKET_W; $H = TICKET_H; $mw = 0.725 * $W; $sw = $W - $mw; $strip = 26; $hb = 42;
    $dark = '#171717'; $gold = '#E6B65C'; $orange = '#B9561B'; $c = $t['color']; $cat = strtoupper($t['cat']);
    $x0 = $strip + 14; $right = $mw - 16; $cw = $right - $x0;                 // content area of the main part
    $o = [['rect', 0, 0, $W, $H, '#ffffff']];
    // left colour strip with the category and admits written upwards
    $o[] = ['rect', 0, 0, $strip, $H, $c];
    $vt = $cat . '  |  ' . $t['admits']; $vs = 10;
    while (pdf_w($vt, $vs, true) > $H - 34 && $vs > 8) $vs -= 0.5;
    if (pdf_w($vt, $vs, true) > $H - 34) { $vt = $cat; $vs = 10; while (pdf_w($vt, $vs, true) > $H - 34 && $vs > 6) $vs -= 0.5; }      // very long name: write only the name
    $o[] = ['vtext', $strip / 2, $H / 2, $vt, $vs, true, '#ffffff'];
    // header: brand band and gold rule; stub header in the category colour
    $o[] = ['rect', $strip, 0, $mw - $strip, $hb, $dark]; $o[] = ['rect', $strip, $hb, $mw - $strip, 2.4, $gold]; $o[] = ['rect', $mw, 0, $sw, $hb + 2.4, $c];
    $o[] = ['text', $x0, $hb * 0.66, $t['brand'], 15.5, true, $gold, 'l'];
    $o[] = ['text', $right, $hb * 0.64, 'GULU CITY - UGANDA', 7.5, true, '#ffffff', 'r'];
    $ss = 11; $sl = pdf_wrap($cat, $sw - 14, $ss, 3, true);
    while ((count($sl) > 2 || str_ends_with((string)end($sl), '...')) && $ss > 8) { $ss -= 0.5; $sl = pdf_wrap($cat, $sw - 14, $ss, 3, true); }       // the category name shrinks to fit the stub header
    $n = count($sl); $lh = $ss + 2; $top = ($hb + 2.4) / 2 - ($n - 1) * $lh / 2 + $ss * 0.34;
    foreach ($sl as $i => $ln) $o[] = ['text', $mw + $sw / 2, $top + $i * $lh, $ln, $ss, true, '#ffffff', 'c'];
    // date block
    $by = 54; $bw = 60; $bh = 56;
    $o[] = ['rect', $x0, $by, $bw, 15, $dark]; $o[] = ['text', $x0 + $bw / 2, $by + 11, $t['mon'], 8.5, true, $gold, 'c'];
    $o[] = ['frame', $x0, $by, $bw, $bh, $dark, 1.2];
    $o[] = ['text', $x0 + $bw / 2, $by + 15 + 27, $t['day'], 26, true, $dark, 'c'];
    $o[] = ['text', $x0 + $bw / 2, $by + $bh - 5, trim($t['dow'] . ' ' . $t['time']), 7, true, '#555555', 'c'];
    // event name (shrinks to fit two lines) and venue
    $tx = $x0 + $bw + 14; $tw = $right - $tx; $ts = 18;
    do { $lines = pdf_wrap($t['event'], $tw, $ts, 3, true); $ok = count($lines) <= 2 && !str_ends_with((string)end($lines), '...'); } while (!$ok && --$ts >= 12);
    if (count($lines) > 2) $lines = pdf_wrap($t['event'], $tw, 12, 2, true);
    $last = $by + $ts * 0.95;
    foreach ($lines as $i => $ln) { $last = $by + $ts * 0.95 + $i * ($ts + 3); $o[] = ['text', $tx, $last, $ln, $ts, true, $dark, 'l']; }
    $o[] = ['text', $tx, $last + 15, pdf_wrap($t['venue'], $tw, 9.5, 1)[0], 9.5, false, '#444444', 'l'];
    // admits chip on the left, rate on the right (their own row, so they never touch anything else)
    $bare = !$t['perks'];
    $ry = $bare ? 134 : 122; $rh = $bare ? 30 : 24; $rb = $ry + $rh * 0.68; $asz = $bare ? 13 : 11.5;
    $aw = pdf_w($t['admits'], $asz, true) + 24;
    $o[] = ['pill', $x0, $ry, $aw, $rh, 6, $dark]; $o[] = ['text', $x0 + $aw / 2, $rb, $t['admits'], $asz, true, '#ffffff', 'c'];
    if ($t['price'] !== '') { $o[] = ['text', $right, $rb + 1, $t['price'], $bare ? 22 : 20, true, $orange, 'r']; $o[] = ['text', $right, $ry + $rh + 7, 'per ticket', 6.5, false, '#777777', 'r']; }
    // what the ticket comes with
    if (!$bare) {
        $o[] = ['text', $x0, 166, 'THIS TICKET COMES WITH', 7, true, $orange, 'l']; $o[] = ['line', $x0 + 108, 163.5, $right, 163.5, '#e3d9c6', 0.6, null];
        $colw = $cw / 2;
        foreach ($t['perks'] as $i => $pk) {
            $px = $x0 + ($i % 2) * $colw; $pb = 178 + intdiv($i, 2) * 12.5;
            $o[] = ['poly', [[$px, $pb - 3.2], [$px + 2.6, $pb - 0.6], [$px + 7, $pb - 6.4]], '#2E9D57', 1.3];
            $o[] = ['text', $px + 11, $pb, pdf_wrap($pk, $colw - 16, 8.5, 1)[0], 8.5, false, '#222222', 'l'];
        }
    } else {
        $o[] = ['text', $x0, $ry + $rh + 26, 'Present this ticket at the gate with the QR code facing up. One entry per ticket.', 8.5, false, '#555555', 'l'];
    }
    // small print
    $o[] = ['line', $x0, $H - 38, $right, $H - 38, '#cccccc', 0.6, null];
    foreach (pdf_wrap($t['terms'], $cw, 6.6, 2) as $i => $ln) $o[] = ['text', $x0, $H - 28 + $i * 8.6, $ln, 6.6, false, '#666666', 'l'];
    // stub with QR code
    $o[] = ['line', $mw, 0, $mw, $H, '#999999', 0.8, '4 3'];
    $qs = min($sw - 24, 112); $qy = $hb + 14;
    $o[] = ['qr', $t['qr'], $mw + ($sw - $qs) / 2, $qy, $qs];
    $o[] = ['text', $mw + $sw / 2, $qy + $qs + 18, $t['serial'], 15, true, $dark, 'c'];
    $o[] = ['text', $mw + $sw / 2, $qy + $qs + 30, 'Verify ' . $t['verify'], 8, true, '#555555', 'c'];
    $o[] = ['text', $mw + $sw / 2, $H - 14, 'SCAN AT THE GATE', 6.5, true, '#888888', 'c'];
    $o[] = ['rect', $mw, $H - 6, $sw, 6, $c];
    // frames and punched notches
    $o[] = ['frame', $strip + 3.5, 3.5, $W - $strip - 7, $H - 7, $gold, 0.5]; $o[] = ['frame', 0, 0, $W, $H, $dark, 1.4];
    $o[] = ['pill', $mw - 6, -6, 12, 12, 6, '#ffffff']; $o[] = ['pill', $mw - 6, $H - 6, 12, 12, 6, '#ffffff'];
    return $o;
}
/** Draw one landscape ticket into a PDF at (x, y), w points wide. */
function ticket_pdf_draw(MiniPdf $p, float $x, float $y, float $w, array $t): void
{
    $s = $w / TICKET_W;
    foreach (ticket_ops($t) as $op) {
        switch ($op[0]) {
            case 'rect': $p->rect($x + $op[1] * $s, $y + $op[2] * $s, $op[3] * $s, $op[4] * $s, $op[5]); break;
            case 'frame': $p->frame($x + $op[1] * $s, $y + $op[2] * $s, $op[3] * $s, $op[4] * $s, $op[5], $op[6] * $s); break;
            case 'pill': $p->pill($x + $op[1] * $s, $y + $op[2] * $s, $op[3] * $s, $op[4] * $s, $op[5] * $s, $op[6]); break;
            case 'line': $p->line($x + $op[1] * $s, $y + $op[2] * $s, $x + $op[3] * $s, $y + $op[4] * $s, $op[5], $op[6] * $s, $op[7]); break;
            case 'text': $p->text($x + $op[1] * $s, $y + $op[2] * $s, $op[3], $op[4] * $s, $op[5], $op[6], $op[7]); break;
            case 'vtext': $p->vtext($x + $op[1] * $s, $y + $op[2] * $s, $op[3], $op[4] * $s, $op[5], $op[6]); break;
            case 'poly': $p->poly(array_map(fn($pt) => [$x + $pt[0] * $s, $y + $pt[1] * $s], $op[1]), $op[2], $op[3] * $s); break;
            case 'qr': $p->qr($op[1], $x + $op[2] * $s, $y + $op[3] * $s, $op[4] * $s); break;
        }
    }
}
/**
 * A PDF of many tickets. layout 'a4' = A4 portrait sheets with three landscape tickets and cut lines; 'a5' = one ticket per A5 landscape page.
 * @param array $items list of ticket data arrays (see gen_ticket_data)
 */
function tickets_pdf(array $items, string $layout, string $title): string
{
    $mm = 72 / 25.4; $pdf = new MiniPdf($title);
    if ($layout === 'a5') {
        $pw = 210 * $mm; $ph = 148 * $mm; $w = 196 * $mm; $h = $w * TICKET_H_MM / TICKET_W_MM;
        foreach ($items as $t) { $pdf->page($pw, $ph); ticket_pdf_draw($pdf, ($pw - $w) / 2, ($ph - $h) / 2, $w, $t); }
        return $pdf->output();
    }
    $pw = 210 * $mm; $ph = 297 * $mm; $w = 190 * $mm; $h = TICKET_H_MM * $mm; $gap = 9 * $mm; $top = (297 - 3 * TICKET_H_MM - 2 * 9) / 2 * $mm;
    foreach (array_chunk($items, 3) as $chunk) {
        $pdf->page($pw, $ph);
        foreach ($chunk as $i => $t) {
            $y = $top + $i * ($h + $gap);
            ticket_pdf_draw($pdf, ($pw - $w) / 2, $y, $w, $t);
            if ($i < 2) $pdf->line(0, $y + $h + $gap / 2, $pw, $y + $h + $gap / 2, '#bbbbbb', 0.4, '5 4');      // cut line
        }
    }
    return $pdf->output();
}

/* ---------- The same ticket as vector graphics for the screen and the browser's print dialog ---------- */
function ticket_svg(array $t): string
{
    $e = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $n = fn($v) => rtrim(rtrim(number_format((float)$v, 2, '.', ''), '0'), '.') ?: '0';
    $s = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . TICKET_W . ' ' . TICKET_H . '" width="' . TICKET_W_MM . 'mm" height="' . TICKET_H_MM . 'mm" font-family="Helvetica,Arial,sans-serif" role="img" aria-label="Ticket ' . $e($t['serial']) . '">';
    foreach (ticket_ops($t) as $op) {
        switch ($op[0]) {
            case 'rect': $s .= '<rect x="' . $n($op[1]) . '" y="' . $n($op[2]) . '" width="' . $n($op[3]) . '" height="' . $n($op[4]) . '" fill="' . $e($op[5]) . '"/>'; break;
            case 'frame': $s .= '<rect x="' . $n($op[1]) . '" y="' . $n($op[2]) . '" width="' . $n($op[3]) . '" height="' . $n($op[4]) . '" fill="none" stroke="' . $e($op[5]) . '" stroke-width="' . $n($op[6]) . '"/>'; break;
            case 'pill': $s .= '<rect x="' . $n($op[1]) . '" y="' . $n($op[2]) . '" width="' . $n($op[3]) . '" height="' . $n($op[4]) . '" rx="' . $n($op[5]) . '" fill="' . $e($op[6]) . '"/>'; break;
            case 'line': $s .= '<line x1="' . $n($op[1]) . '" y1="' . $n($op[2]) . '" x2="' . $n($op[3]) . '" y2="' . $n($op[4]) . '" stroke="' . $e($op[5]) . '" stroke-width="' . $n($op[6]) . '"' . ($op[7] ? ' stroke-dasharray="' . $e($op[7]) . '"' : '') . '/>'; break;
            case 'text': $s .= '<text x="' . $n($op[1]) . '" y="' . $n($op[2]) . '" font-size="' . $n($op[4]) . '" font-weight="' . ($op[5] ? 700 : 400) . '" fill="' . $e($op[6]) . '" text-anchor="' . ['l' => 'start', 'c' => 'middle', 'r' => 'end'][$op[7]] . '">' . $e($op[3]) . '</text>'; break;
            case 'vtext': $s .= '<text transform="translate(' . $n($op[1]) . ' ' . $n($op[2]) . ') rotate(-90)" dy="0.35em" font-size="' . $n($op[4]) . '" font-weight="' . ($op[5] ? 700 : 400) . '" fill="' . $e($op[6]) . '" text-anchor="middle">' . $e($op[3]) . '</text>'; break;
            case 'poly': $s .= '<polyline points="' . implode(' ', array_map(fn($pt) => $n($pt[0]) . ',' . $n($pt[1]), $op[1])) . '" fill="none" stroke="' . $e($op[2]) . '" stroke-width="' . $n($op[3]) . '" stroke-linecap="round" stroke-linejoin="round"/>'; break;
            case 'qr':
                $m = qr_matrix($op[1]); $cnt = count($m); $d = '';
                foreach ($m as $r => $row) { $c = 0; while ($c < $cnt) { if ($row[$c]) { $a = $c; while ($c < $cnt && $row[$c]) $c++; $d .= 'M' . ($a + 2) . ',' . ($r + 2) . 'h' . ($c - $a) . 'v1h-' . ($c - $a) . 'z'; } else $c++; } }
                $k = $op[4] / ($cnt + 4);
                $s .= '<g transform="translate(' . $n($op[2]) . ' ' . $n($op[3]) . ') scale(' . round($k, 5) . ')" shape-rendering="crispEdges"><rect width="' . ($cnt + 4) . '" height="' . ($cnt + 4) . '" fill="#fff"/><path d="' . $d . '" fill="#000"/></g>';
                break;
        }
    }
    return $s . '</svg>';
}
function ticket_html(array $t): string { return '<div class="gt">' . ticket_svg($t) . '</div>'; }
function ticket_css(): string
{
    return '.gt{width:190mm;height:88mm;display:block;line-height:0;overflow:hidden;-webkit-print-color-adjust:exact;print-color-adjust:exact}.gt svg{display:block;width:100%;height:100%}';
}
