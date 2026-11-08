<?php
declare(strict_types=1);

/*
 * Small QR code encoder (byte mode, error correction M, versions 1 to 10: up to 213 characters).
 * Used to draw QR codes into printable tickets and PDFs without any outside library.
 */
const QR_BLOCKS = [   // version => [error-correction codewords per block, [[blocks, data codewords per block], ...]]
    1 => [10, [[1, 16]]], 2 => [16, [[1, 28]]], 3 => [26, [[1, 44]]], 4 => [18, [[2, 32]]], 5 => [24, [[2, 43]]],
    6 => [16, [[4, 27]]], 7 => [18, [[4, 31]]], 8 => [22, [[2, 38], [2, 39]]], 9 => [22, [[3, 36], [2, 37]]], 10 => [26, [[4, 43], [1, 44]]],
];
const QR_ALIGN = [1 => [], 2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30], 6 => [6, 34], 7 => [6, 22, 38], 8 => [6, 24, 42], 9 => [6, 26, 46], 10 => [6, 28, 50]];

function qr_gf_tables(): array
{
    static $t = null;
    if ($t) return $t;
    $exp = []; $log = array_fill(0, 256, 0); $x = 1;
    for ($i = 0; $i < 255; $i++) { $exp[$i] = $x; $log[$x] = $i; $x <<= 1; if ($x & 0x100) $x ^= 0x11D; }
    for ($i = 255; $i < 512; $i++) $exp[$i] = $exp[$i - 255];
    return $t = [$exp, $log];
}
function qr_gf_mul(int $a, int $b): int
{
    if ($a === 0 || $b === 0) return 0;
    [$exp, $log] = qr_gf_tables();
    return $exp[$log[$a] + $log[$b]];
}
function qr_rs_divisor(int $degree): array
{
    $r = array_fill(0, $degree, 0); $r[$degree - 1] = 1; $root = 1;
    for ($i = 0; $i < $degree; $i++) {
        for ($j = 0; $j < $degree; $j++) {
            $r[$j] = qr_gf_mul($r[$j], $root);
            if ($j + 1 < $degree) $r[$j] ^= $r[$j + 1];
        }
        $root = qr_gf_mul($root, 2);
    }
    return $r;
}
function qr_rs_remainder(array $data, array $divisor): array
{
    $res = array_fill(0, count($divisor), 0);
    foreach ($data as $b) {
        $factor = $b ^ array_shift($res);
        $res[] = 0;
        foreach ($divisor as $i => $c) $res[$i] ^= qr_gf_mul($c, $factor);
    }
    return $res;
}
function qr_bit(int $v, int $i): bool { return (($v >> $i) & 1) === 1; }

/** The QR matrix for a text: array of rows, each an array of bool (true = dark). */
function qr_matrix(string $text, ?int $forceMask = null): array
{
    $bytes = array_values(unpack('C*', $text) ?: []);
    $n = count($bytes);
    $version = 0;
    foreach (QR_BLOCKS as $v => [$ec, $groups]) {
        $cap = 0; foreach ($groups as [$cnt, $dcw]) $cap += $cnt * $dcw;
        $need = 4 + ($v < 10 ? 8 : 16) + 8 * $n;
        if ($need <= $cap * 8) { $version = $v; break; }
    }
    if (!$version) throw new InvalidArgumentException('QR text too long');
    [$ecPer, $groups] = QR_BLOCKS[$version];
    $dataCw = 0; foreach ($groups as [$cnt, $dcw]) $dataCw += $cnt * $dcw;

    // data bits: mode 0100 (bytes), length, data, terminator, padding
    $bits = '0100' . str_pad(decbin($n), $version < 10 ? 8 : 16, '0', STR_PAD_LEFT);
    foreach ($bytes as $b) $bits .= str_pad(decbin($b), 8, '0', STR_PAD_LEFT);
    $bits .= str_repeat('0', min(4, $dataCw * 8 - strlen($bits)));
    $bits .= str_repeat('0', (8 - strlen($bits) % 8) % 8);
    $cw = [];
    for ($i = 0; $i < strlen($bits); $i += 8) $cw[] = bindec(substr($bits, $i, 8));
    for ($pad = 0xEC; count($cw) < $dataCw; $pad ^= 0xEC ^ 0x11) $cw[] = $pad;

    // split into blocks, add error correction, interleave
    $blocks = []; $pos = 0; $div = qr_rs_divisor($ecPer);
    foreach ($groups as [$cnt, $dcw]) for ($k = 0; $k < $cnt; $k++) { $d = array_slice($cw, $pos, $dcw); $pos += $dcw; $blocks[] = ['d' => $d, 'e' => qr_rs_remainder($d, $div)]; }
    $maxD = max(array_map(fn($b) => count($b['d']), $blocks));
    $final = [];
    for ($i = 0; $i < $maxD; $i++) foreach ($blocks as $b) if ($i < count($b['d'])) $final[] = $b['d'][$i];
    for ($i = 0; $i < $ecPer; $i++) foreach ($blocks as $b) $final[] = $b['e'][$i];

    // function patterns
    $size = 17 + 4 * $version;
    $m = array_fill(0, $size, array_fill(0, $size, false));
    $fn = array_fill(0, $size, array_fill(0, $size, false));
    $set = function (int $x, int $y, bool $dark) use (&$m, &$fn, $size) { if ($x >= 0 && $x < $size && $y >= 0 && $y < $size) { $m[$y][$x] = $dark; $fn[$y][$x] = true; } };
    for ($i = 0; $i < $size; $i++) { $set(6, $i, $i % 2 === 0); $set($i, 6, $i % 2 === 0); }
    foreach ([[3, 3], [$size - 4, 3], [3, $size - 4]] as [$cx, $cy])
        for ($dy = -4; $dy <= 4; $dy++) for ($dx = -4; $dx <= 4; $dx++) { $dist = max(abs($dx), abs($dy)); $set($cx + $dx, $cy + $dy, $dist !== 2 && $dist !== 4); }
    $ap = QR_ALIGN[$version]; $na = count($ap);
    for ($i = 0; $i < $na; $i++) for ($j = 0; $j < $na; $j++) {
        if (($i === 0 && $j === 0) || ($i === 0 && $j === $na - 1) || ($i === $na - 1 && $j === 0)) continue;
        for ($dy = -2; $dy <= 2; $dy++) for ($dx = -2; $dx <= 2; $dx++) $set($ap[$i] + $dx, $ap[$j] + $dy, max(abs($dx), abs($dy)) !== 1);
    }
    $drawFormat = function (int $mask) use ($set, $size) {
        $data = (0 << 3) | $mask; $rem = $data;                                  // error correction M = 0
        for ($i = 0; $i < 10; $i++) $rem = ($rem << 1) ^ (($rem >> 9) * 0x537);
        $bits = (($data << 10) | $rem) ^ 0x5412;
        for ($i = 0; $i <= 5; $i++) $set(8, $i, qr_bit($bits, $i));
        $set(8, 7, qr_bit($bits, 6)); $set(8, 8, qr_bit($bits, 7)); $set(7, 8, qr_bit($bits, 8));
        for ($i = 9; $i < 15; $i++) $set(14 - $i, 8, qr_bit($bits, $i));
        for ($i = 0; $i < 8; $i++) $set($size - 1 - $i, 8, qr_bit($bits, $i));
        for ($i = 8; $i < 15; $i++) $set(8, $size - 15 + $i, qr_bit($bits, $i));
        $set(8, $size - 8, true);
    };
    $drawFormat(0);                                                              // reserves the format areas
    if ($version >= 7) {
        $rem = $version;
        for ($i = 0; $i < 12; $i++) $rem = ($rem << 1) ^ (($rem >> 11) * 0x1F25);
        $bits = ($version << 12) | $rem;
        for ($i = 0; $i < 18; $i++) { $a = $size - 11 + $i % 3; $b = intdiv($i, 3); $set($a, $b, qr_bit($bits, $i)); $set($b, $a, qr_bit($bits, $i)); }
    }

    // data in the zigzag order
    $i = 0; $total = count($final) * 8;
    for ($right = $size - 1; $right >= 1; $right -= 2) {
        if ($right === 6) $right = 5;
        for ($vert = 0; $vert < $size; $vert++) for ($j = 0; $j < 2; $j++) {
            $x = $right - $j; $y = ((($right + 1) & 2) === 0) ? $size - 1 - $vert : $vert;
            if (!$fn[$y][$x] && $i < $total) { $m[$y][$x] = qr_bit($final[$i >> 3], 7 - ($i & 7)); $i++; }
        }
    }

    // choose the mask with the lowest penalty
    $best = null; $bestScore = PHP_INT_MAX; $bestMask = 0;
    for ($mask = 0; $mask < 8; $mask++) {
        if ($forceMask !== null && $mask !== $forceMask) continue;
        $t = qr_apply_mask($m, $fn, $size, $mask);
        $score = qr_penalty($t, $size);
        if ($score < $bestScore) { $bestScore = $score; $best = $t; $bestMask = $mask; }
    }
    $m = $best;
    $drawFormat($bestMask);
    return $m;
}
function qr_apply_mask(array $m, array $fn, int $size, int $mask): array
{
    for ($y = 0; $y < $size; $y++) for ($x = 0; $x < $size; $x++) {
        if ($fn[$y][$x]) continue;
        $inv = match ($mask) {
            0 => ($x + $y) % 2 === 0, 1 => $y % 2 === 0, 2 => $x % 3 === 0, 3 => ($x + $y) % 3 === 0,
            4 => (intdiv($x, 3) + intdiv($y, 2)) % 2 === 0, 5 => $x * $y % 2 + $x * $y % 3 === 0,
            6 => ($x * $y % 2 + $x * $y % 3) % 2 === 0, default => (($x + $y) % 2 + $x * $y % 3) % 2 === 0,
        };
        if ($inv) $m[$y][$x] = !$m[$y][$x];
    }
    return $m;
}
function qr_penalty(array $m, int $size): int
{
    $score = 0;
    for ($pass = 0; $pass < 2; $pass++) {                                         // rows, then columns
        for ($a = 0; $a < $size; $a++) {
            $run = 1; $prev = null; $line = '';
            for ($b = 0; $b < $size; $b++) {
                $v = $pass === 0 ? $m[$a][$b] : $m[$b][$a];
                $line .= $v ? '1' : '0';
                if ($v === $prev) { $run++; if ($run === 5) $score += 3; elseif ($run > 5) $score++; } else { $run = 1; }
                $prev = $v;
            }
            $score += 40 * (substr_count($line, '10111010000') + substr_count($line, '00001011101'));
        }
    }
    for ($y = 0; $y < $size - 1; $y++) for ($x = 0; $x < $size - 1; $x++) if ($m[$y][$x] === $m[$y][$x + 1] && $m[$y][$x] === $m[$y + 1][$x] && $m[$y][$x] === $m[$y + 1][$x + 1]) $score += 3;
    $dark = 0; foreach ($m as $row) foreach ($row as $v) if ($v) $dark++;
    return $score + 10 * (int)floor(abs($dark * 20 - $size * $size * 10) / ($size * $size));
}
/** Inline SVG of a QR code (dark modules as one path), with a quiet zone. */
function qr_svg(string $text, int $px = 120, string $fg = '#000', string $bg = '#fff', int $quiet = 2): string
{
    $m = qr_matrix($text); $n = count($m); $d = '';
    foreach ($m as $y => $row) { $x = 0; while ($x < $n) { if ($row[$x]) { $s = $x; while ($x < $n && $row[$x]) $x++; $d .= 'M' . ($s + $quiet) . ',' . ($y + $quiet) . 'h' . ($x - $s) . 'v1h-' . ($x - $s) . 'z'; } else $x++; } }
    $t = $n + 2 * $quiet;
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $t . ' ' . $t . '" width="' . $px . '" height="' . $px . '" shape-rendering="crispEdges" role="img" aria-label="QR code"><rect width="' . $t . '" height="' . $t . '" fill="' . $bg . '"/><path d="' . $d . '" fill="' . $fg . '"/></svg>';
}
