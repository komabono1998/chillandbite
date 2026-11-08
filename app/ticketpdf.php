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
const TICKET_W_MM = 190, TICKET_H_MM = 88;
function gen_serial(array $cat, int $number): string { return strtoupper((string)$cat['prefix']) . '-' . str_pad((string)$number, 4, '0', STR_PAD_LEFT); }
function gen_verify(string $token): string { return strtoupper(substr(hash_hmac('sha256', $token, app_secret()), 0, 4)); }
/** Everything printed on one ticket. $g is a gen_tickets row; $e the event; $c the category. */
function gen_ticket_data(array $g, array $e, array $c): array
{
    $admits = max(1, (int)$c['admits']);
    return ['brand' => 'CHILL & BITE MEATINGS', 'event' => (string)$e['title'], 'when' => fmt_date($e['start_at'], 'D j M Y') . ' | ' . fmt_time($e['start_at']),
        'venue' => trim($e['venue'] . ', ' . $e['location'], ', '), 'cat' => (string)$c['name'], 'color' => (string)$c['color'] ?: '#D96B27',
        'admits' => $admits === 1 ? 'ADMIT ONE' : 'ADMITS ' . $admits, 'price' => (int)$c['price'] > 0 ? money($c['price']) : '',
        'serial' => gen_serial($c, (int)$g['number']), 'verify' => gen_verify((string)$g['token']), 'qr' => abs_url('admin/checkin/' . $g['token']),
        'terms' => trim((string)$c['terms']) ?: 'Valid for one entry only. Not refundable. Keep this ticket safe: copies are rejected at the gate.'];
}
/** Draw one landscape ticket at (x, y) with width w points (height follows the 190 x 88 mm shape). */
function ticket_pdf_draw(MiniPdf $p, float $x, float $y, float $w, array $t): void
{
    $s = $w / 538.6; $h = $w * TICKET_H_MM / TICKET_W_MM;
    $mw = 0.735 * $w; $sw = $w - $mw; $hb = 52 * $s; $dark = '#171717'; $gold = '#E6B65C'; $orange = '#B9561B';
    $p->rect($x, $y, $w, $h, '#ffffff');
    $p->rect($x, $y, $mw, $hb, $dark);
    $p->rect($x + $mw, $y, $sw, $hb, $t['color']);
    $p->text($x + 16 * $s, $y + $hb * 0.64, $t['brand'], 17 * $s, true, $gold);
    $p->text($x + $mw - 14 * $s, $y + $hb * 0.62, 'GULU CITY - UGANDA', 7.5 * $s, true, '#ffffff', 'r');
    $cs = 12 * $s; $cat = strtoupper($t['cat']); while (pdf_w($cat, $cs, true) > $sw - 14 * $s && $cs > 6) $cs -= 0.5 * $s;
    $p->text($x + $mw + $sw / 2, $y + $hb * 0.62, $cat, $cs, true, '#ffffff', 'c');
    // event, date, venue
    // the event name shrinks until it fits on two lines
    $ts = 22;
    do { $lines = pdf_wrap($t['event'], $mw - 32 * $s, $ts * $s, 3, true); $ok = count($lines) <= 2 && !str_ends_with(end($lines), '...'); } while (!$ok && --$ts >= 13);
    if (count($lines) > 2) $lines = pdf_wrap($t['event'], $mw - 32 * $s, 13 * $s, 2, true);
    $ty = $y + $hb + 30 * $s;
    foreach ($lines as $ln) { $p->text($x + 16 * $s, $ty, $ln, $ts * $s, true, $dark); $ty += ($ts + 3) * $s; }
    $p->text($x + 16 * $s, $ty + 2 * $s, $t['when'], 12.5 * $s, true, $orange);
    $venue = pdf_wrap($t['venue'], $mw - 32 * $s, 10.5 * $s, 1)[0];
    $p->text($x + 16 * $s, $ty + 19 * $s, $venue, 10.5 * $s, false, '#444444');
    // category pill, admits and price
    $py = $y + $h - 88 * $s; $ptxt = strtoupper($t['cat']); $psz = 15 * $s; $pw = pdf_w($ptxt, $psz, true) + 28 * $s;
    if ($pw > $mw * 0.55) { $psz = 11 * $s; $pw = $mw * 0.55; }
    $p->pill($x + 16 * $s, $py, $pw, 32 * $s, 8 * $s, $t['color']);
    $p->text($x + 16 * $s + $pw / 2, $py + 21.5 * $s, $ptxt, $psz, true, '#ffffff', 'c');
    $p->text($x + 16 * $s + $pw + 14 * $s, $py + 21 * $s, $t['admits'], 12 * $s, true, $dark);
    if ($t['price'] !== '') $p->text($x + $mw - 16 * $s, $py + 23 * $s, $t['price'], 19 * $s, true, $orange, 'r');
    // footer
    $p->line($x + 16 * $s, $y + $h - 44 * $s, $x + $mw - 16 * $s, $y + $h - 44 * $s, '#cccccc', 0.6);
    $ty = $y + $h - 32 * $s;
    foreach (pdf_wrap($t['terms'], $mw - 32 * $s, 6.8 * $s, 2) as $ln) { $p->text($x + 16 * $s, $ty, $ln, 6.8 * $s, false, '#666666'); $ty += 9 * $s; }
    // stub
    $p->line($x + $mw, $y, $x + $mw, $y + $h, '#999999', 0.8, '4 3');
    $qs = min($sw - 22 * $s, 118 * $s);
    $p->qr($t['qr'], $x + $mw + ($sw - $qs) / 2, $y + $hb + 12 * $s, $qs);
    $p->text($x + $mw + $sw / 2, $y + $hb + 12 * $s + $qs + 17 * $s, $t['serial'], 15 * $s, true, $dark, 'c');
    $p->text($x + $mw + $sw / 2, $y + $hb + 12 * $s + $qs + 29 * $s, 'Verify ' . $t['verify'], 8 * $s, true, '#555555', 'c');
    $p->frame($x, $y, $w, $h, $dark, 1.2);
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

/* ---------- The same ticket as HTML, for printing from the browser ---------- */
function ticket_html(array $t): string
{
    $e = fn($v) => h((string)$v);
    return '<div class="gt" style="--c:' . $e($t['color']) . '"><div class="gt-main"><div class="gt-band"><b>' . $e($t['brand']) . '</b><span>GULU CITY - UGANDA</span></div>'
        . '<div class="gt-body"><h3 style="font-size:' . (mb_strlen($t['event']) <= 28 ? 22 : (mb_strlen($t['event']) <= 48 ? 18 : 15)) . 'pt">' . $e($t['event']) . '</h3><p class="gt-when">' . $e($t['when']) . '</p><p class="gt-venue">' . $e($t['venue']) . '</p></div>'
        . '<div class="gt-mid"><span class="gt-pill">' . $e(strtoupper($t['cat'])) . '</span><b class="gt-admit">' . $e($t['admits']) . '</b>' . ($t['price'] !== '' ? '<b class="gt-price">' . $e($t['price']) . '</b>' : '') . '</div>'
        . '<div class="gt-terms">' . $e($t['terms']) . '</div></div>'
        . '<div class="gt-stub"><div class="gt-cat">' . $e(strtoupper($t['cat'])) . '</div><div class="gt-qr">' . qr_svg($t['qr'], 110) . '</div><b class="gt-serial">' . $e($t['serial']) . '</b><small>Verify ' . $e($t['verify']) . '</small></div></div>';
}
function ticket_css(): string
{
    return '.gt{display:flex;width:190mm;height:88mm;border:1.2pt solid #171717;background:#fff;color:#171717;font-family:Helvetica,Arial,sans-serif;position:relative;box-sizing:border-box;overflow:hidden;-webkit-print-color-adjust:exact;print-color-adjust:exact}'
        . '.gt-main{width:73.5%;display:flex;flex-direction:column;position:relative}.gt-band{height:18.4mm;background:#171717;display:flex;align-items:center;justify-content:space-between;padding:0 5.6mm}'
        . '.gt-band b{color:#E6B65C;font-size:17pt;letter-spacing:.2px}.gt-band span{color:#fff;font-size:7.5pt;font-weight:700}'
        . '.gt-body{padding:4mm 5.6mm 0}.gt-body h3{margin:0 0 2mm;font-size:22pt;line-height:1.08;font-weight:800;max-height:2.2em;overflow:hidden}'
        . '.gt-when{margin:0;font-size:12.5pt;font-weight:700;color:#B9561B}.gt-venue{margin:1.2mm 0 0;font-size:10.5pt;color:#444}'
        . '.gt-mid{position:absolute;left:5.6mm;right:5.6mm;bottom:15mm;display:flex;align-items:center;gap:4mm}'
        . '.gt-pill{background:var(--c);color:#fff;font-weight:800;font-size:15pt;padding:2.4mm 5mm;border-radius:2.6mm;max-width:55%;white-space:nowrap;overflow:hidden}'
        . '.gt-admit{font-size:12pt}.gt-price{margin-left:auto;font-size:19pt;color:#B9561B}'
        . '.gt-terms{position:absolute;left:5.6mm;right:5.6mm;bottom:3mm;border-top:.6pt solid #ccc;padding-top:1.6mm;font-size:6.8pt;color:#666;line-height:1.3;max-height:2.7em;overflow:hidden}'
        . '.gt-stub{width:26.5%;border-left:.8pt dashed #999;display:flex;flex-direction:column;align-items:center;position:relative}'
        . '.gt-cat{align-self:stretch;height:18.4mm;background:var(--c);color:#fff;font-weight:800;font-size:12pt;display:flex;align-items:center;justify-content:center;text-align:center;padding:0 2mm}'
        . '.gt-qr{margin-top:4mm;line-height:0}.gt-qr svg{width:36mm;height:36mm;display:block}.gt-serial{margin-top:2.5mm;font-size:15pt}.gt-stub small{margin-top:1mm;font-size:8pt;font-weight:700;color:#555}';
}
