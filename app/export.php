<?php
declare(strict_types=1);

/**
 * Report exports in PDF, Word (.docx), Excel (.xlsx) and CSV.
 * Pure PHP (no Composer packages, no ZipArchive): a tiny ZIP writer builds the Office files.
 */

/* ================= helpers ================= */
function x_esc($v): string
{
    $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', (string)$v);
    return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}
function cell_text($v): string
{
    if (is_int($v)) return number_format($v);
    if (is_float($v)) return number_format($v, $v == floor($v) ? 0 : 2);
    return (string)$v;
}
/** Column widths in proportion to content, summing to $total. */
function col_weights(array $headers, array $rows, float $total, float $min = 0.06): array
{
    $n = count($headers);
    $w = [];
    foreach ($headers as $i => $hd) {
        // longest unbreakable word (capped) plus a share of the typical text length, so IDs/phones/headers stay on one line
        $word = 0; foreach (preg_split('/\s+/', (string)$hd) as $wd) $word = max($word, mb_strlen($wd));
        $line = mb_strlen((string)$hd);
        foreach (array_slice($rows, 0, 200) as $r) {
            $t = cell_text($r[$i] ?? '');
            $line = max($line, min(34, mb_strlen($t)));
            foreach (preg_split('/\s+/', $t) as $wd) $word = max($word, min(18, mb_strlen($wd)));
        }
        $w[$i] = max(6, $word + 2, (int)round($line * 0.7));
    }
    $sum = array_sum($w);
    $out = [];
    foreach ($w as $i => $l) $out[$i] = max($min, $l / $sum);
    $norm = array_sum($out);
    foreach ($out as $i => $f) $out[$i] = $f / $norm * $total;
    return $out;
}

/* ================= ZIP (store only) ================= */
function zip_build(array $files): string
{
    $out = ''; $central = ''; $count = 0;
    $dosDate = ((2026 - 1980) << 9) | (1 << 5) | 1;
    foreach ($files as $name => $data) {
        $crc = crc32($data) & 0xFFFFFFFF;
        $size = strlen($data);
        $offset = strlen($out);
        $out .= pack('VvvvvvVVVvv', 0x04034b50, 20, 0, 0, 0, $dosDate, $crc, $size, $size, strlen($name), 0) . $name . $data;
        $central .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0, 0, 0, $dosDate, $crc, $size, $size, strlen($name), 0, 0, 0, 0, 0, $offset) . $name;
        $count++;
    }
    return $out . $central . pack('VvvvvVVv', 0x06054b50, 0, 0, $count, $count, strlen($central), strlen($out), 0);
}

/* ================= XLSX ================= */
function xlsx_col(int $i): string
{
    $s = '';
    for ($i++; $i > 0; $i = intdiv($i - 1, 26)) $s = chr(65 + ($i - 1) % 26) . $s;
    return $s;
}
function xlsx_build(string $title, string $subtitle, array $headers, array $rows): string
{
    $ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    $sheetRows = '';
    $r = 1;
    $cellStr = fn(int $c, int $row, string $v, int $style = 0) =>
        '<c r="' . xlsx_col($c) . $row . '" t="inlineStr"' . ($style ? ' s="' . $style . '"' : '') . '><is><t xml:space="preserve">' . x_esc($v) . '</t></is></c>';
    $sheetRows .= '<row r="1">' . $cellStr(0, 1, $title, 2) . '</row>'; $r = 2;
    $sheetRows .= '<row r="2">' . $cellStr(0, 2, $subtitle, 0) . '</row>'; $r = 3;
    $sheetRows .= '<row r="3">' . $cellStr(0, 3, 'Generated ' . date('j M Y, H:i') . ' · Chill & Bite MEATINGS', 0) . '</row>'; $r = 4;
    $line = '';
    foreach ($headers as $c => $hd) $line .= $cellStr($c, $r, (string)$hd, 1);
    $sheetRows .= '<row r="' . $r . '">' . $line . '</row>';
    $headerRow = $r;
    foreach ($rows as $row) {
        $r++; $line = '';
        foreach ($headers as $c => $_) {
            $v = $row[$c] ?? '';
            if (is_int($v) || is_float($v)) $line .= '<c r="' . xlsx_col($c) . $r . '"><v>' . $v . '</v></c>';
            else $line .= $cellStr($c, $r, (string)$v);
        }
        $sheetRows .= '<row r="' . $r . '">' . $line . '</row>';
    }
    $cols = '';
    foreach (col_weights($headers, $rows, 150, 0.04) as $i => $w) $cols .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . round(max(9, min(48, $w)), 1) . '" customWidth="1"/>';
    $lastCol = xlsx_col(max(0, count($headers) - 1));
    $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="' . $ns . '"><sheetViews><sheetView workbookViewId="0">' .
        '<pane ySplit="' . $headerRow . '" topLeftCell="A' . ($headerRow + 1) . '" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>' .
        '<cols>' . $cols . '</cols><sheetData>' . $sheetRows . '</sheetData><autoFilter ref="A' . $headerRow . ':' . $lastCol . max($headerRow, $r) . '"/></worksheet>';
    $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="' . $ns . '">' .
        '<fonts count="4"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font>' .
        '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font><font><b/><sz val="15"/><name val="Calibri"/></font></fonts>' .
        '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>' .
        '<fill><patternFill patternType="solid"><fgColor rgb="FFD96B27"/><bgColor indexed="64"/></patternFill></fill></fills>' .
        '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>' .
        '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>' .
        '<cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>' .
        '<xf numFmtId="0" fontId="2" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>' .
        '<xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs></styleSheet>';
    return zip_build([
        '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' .
            '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>' .
            '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>' .
            '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>' .
            '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>',
        '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
        'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="' . $ns . '" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' .
            '<sheets><sheet name="Report" sheetId="1" r:id="rId1"/></sheets></workbook>',
        'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>' .
            '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
        'xl/styles.xml' => $styles,
        'xl/worksheets/sheet1.xml' => $sheet,
    ]);
}

/* ================= DOCX ================= */
function docx_build(string $title, string $subtitle, array $headers, array $rows): string
{
    $w = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    $para = fn(string $t, string $rpr = '', string $ppr = '') =>
        '<w:p>' . ($ppr ? '<w:pPr>' . $ppr . '</w:pPr>' : '') . '<w:r>' . ($rpr ? '<w:rPr>' . $rpr . '</w:rPr>' : '') . '<w:t xml:space="preserve">' . x_esc($t) . '</w:t></w:r></w:p>';
    $widths = array_map(fn($f) => (int)round($f), col_weights($headers, $rows, 15398));
    $grid = '';
    foreach ($widths as $wd) $grid .= '<w:gridCol w:w="' . $wd . '"/>';
    $cell = function (string $text, int $wd, bool $head, bool $zebra) use ($para) {
        $shd = $head ? '<w:shd w:val="clear" w:color="auto" w:fill="D96B27"/>' : ($zebra ? '<w:shd w:val="clear" w:color="auto" w:fill="F7F2EA"/>' : '');
        $rpr = $head ? '<w:b/><w:color w:val="FFFFFF"/><w:sz w:val="17"/>' : '<w:sz w:val="16"/>';
        return '<w:tc><w:tcPr><w:tcW w:w="' . $wd . '" w:type="dxa"/>' . $shd . '</w:tcPr>' . $para($text, $rpr, '<w:spacing w:before="20" w:after="20"/>') . '</w:tc>';
    };
    $tbl = '<w:tbl><w:tblPr><w:tblW w:w="' . array_sum($widths) . '" w:type="dxa"/><w:tblBorders>';
    foreach (['top', 'left', 'bottom', 'right', 'insideH', 'insideV'] as $b) $tbl .= '<w:' . $b . ' w:val="single" w:sz="4" w:space="0" w:color="C9C0B2"/>';
    $tbl .= '</w:tblBorders><w:tblLayout w:type="fixed"/><w:tblCellMar><w:left w:w="70" w:type="dxa"/><w:right w:w="70" w:type="dxa"/></w:tblCellMar></w:tblPr><w:tblGrid>' . $grid . '</w:tblGrid>';
    $tbl .= '<w:tr><w:trPr><w:tblHeader/></w:trPr>';
    foreach ($headers as $i => $hd) $tbl .= $cell((string)$hd, $widths[$i], true, false);
    $tbl .= '</w:tr>';
    foreach ($rows as $ri => $row) {
        $tbl .= '<w:tr><w:trPr><w:cantSplit/></w:trPr>';
        foreach ($headers as $i => $_) $tbl .= $cell(cell_text($row[$i] ?? ''), $widths[$i], false, $ri % 2 === 1);
        $tbl .= '</w:tr>';
    }
    $tbl .= '</w:tbl>';
    $body = $para($title, '<w:b/><w:color w:val="D96B27"/><w:sz w:val="34"/>', '<w:spacing w:after="40"/>') .
        $para($subtitle, '<w:color w:val="555555"/><w:sz w:val="18"/>') .
        $para('Generated ' . date('j M Y, H:i') . ' · Chill & Bite MEATINGS · ' . count($rows) . ' record(s)', '<w:i/><w:color w:val="888888"/><w:sz w:val="16"/>', '<w:spacing w:after="160"/>') .
        $tbl . '<w:p/>' .
        '<w:sectPr><w:pgSz w:w="16838" w:h="11906" w:orient="landscape"/><w:pgMar w:top="720" w:right="720" w:bottom="720" w:left="720" w:header="360" w:footer="360" w:gutter="0"/></w:sectPr>';
    return zip_build([
        '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">' .
            '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>' .
            '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>',
        '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' .
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>',
        'word/document.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document xmlns:w="' . $w . '"><w:body>' . $body . '</w:body></w:document>',
    ]);
}

/* ================= PDF ================= */
/** Helvetica glyph widths (1/1000 em) for ASCII 32..126. */
const PDF_W = [278, 278, 355, 556, 556, 889, 667, 191, 333, 333, 389, 584, 278, 333, 278, 278, 556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 278, 278, 584, 584, 584, 556,
    1015, 667, 667, 722, 722, 667, 611, 778, 722, 278, 500, 667, 556, 833, 722, 778, 667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 278, 278, 278, 469, 556,
    333, 556, 556, 500, 556, 556, 278, 556, 556, 222, 222, 500, 222, 833, 556, 556, 556, 556, 333, 500, 278, 556, 500, 722, 500, 500, 500, 334, 260, 334, 584];
function pdf_cp(string $s): string
{
    $s = str_replace(["\r", "\n", "\t"], ' ', $s);
    $o = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $s);
    if ($o === false) $o = preg_replace('/[^\x20-\x7E]/', '?', $s);
    return preg_replace('/[\x00-\x1F]/', '', $o);
}
function pdf_w(string $s, float $size, bool $bold = false): float
{
    $t = 0;
    for ($i = 0, $n = strlen($s); $i < $n; $i++) { $c = ord($s[$i]); $t += ($c >= 32 && $c <= 126) ? PDF_W[$c - 32] : 556; }
    return $t / 1000 * $size * ($bold ? 1.07 : 1);
}
function pdf_wrap(string $s, float $maxW, float $size, int $maxLines = 4, bool $bold = false): array
{
    $words = preg_split('/\s+/', trim($s)) ?: [''];
    $lines = []; $cur = '';
    foreach ($words as $wd) {
        while (pdf_w($wd, $size, $bold) > $maxW && strlen($wd) > 1) {          // break very long words
            $cut = strlen($wd);
            while ($cut > 1 && pdf_w(substr($wd, 0, $cut), $size, $bold) > $maxW) $cut--;
            if ($cur !== '') { $lines[] = $cur; $cur = ''; }
            $lines[] = substr($wd, 0, $cut); $wd = substr($wd, $cut);
        }
        $try = $cur === '' ? $wd : $cur . ' ' . $wd;
        if (pdf_w($try, $size, $bold) <= $maxW) $cur = $try; else { $lines[] = $cur; $cur = $wd; }
    }
    if ($cur !== '' || !$lines) $lines[] = $cur;
    if (count($lines) > $maxLines) { $lines = array_slice($lines, 0, $maxLines); $lines[$maxLines - 1] = rtrim(substr($lines[$maxLines - 1], 0, max(0, strlen($lines[$maxLines - 1]) - 3))) . '...'; }
    return $lines;
}
function pdf_esc(string $s): string { return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $s); }

/**
 * Column widths in points. Every column is at least as wide as its longest unbreakable word (so headers and
 * references/phone numbers never break), then the remaining width is shared out by how much text each column holds.
 */
function pdf_col_widths(array $hdr, array $data, float $avail, float $size, float $hsize, float $pad): array
{
    $n = count($hdr); $min = []; $want = [];
    $sample = array_slice($data, 0, 400);
    for ($i = 0; $i < $n; $i++) {
        $hw = 0.0; foreach (preg_split('/\s+/', $hdr[$i]) as $wd) $hw = max($hw, pdf_w($wd, $hsize, true));
        $tok = 0.0; $line = pdf_w($hdr[$i], $hsize, true);
        foreach ($sample as $r) {
            $t = (string)($r[$i] ?? '');
            $line = max($line, pdf_w($t, $size));
            foreach (preg_split('/\s+/', $t) as $wd) $tok = max($tok, pdf_w($wd, $size));
        }
        $min[$i] = min(max($hw, $tok), 120.0) + 2 * $pad + 1;
        $want[$i] = max($min[$i], min($line, 190.0) + 2 * $pad + 1);
    }
    $sumMin = array_sum($min);
    if ($sumMin >= $avail) { $f = $avail / $sumMin; return array_map(fn($m) => $m * $f, $min); }
    $w = $min; $spare = $avail - $sumMin; $need = array_sum($want) - $sumMin;
    if ($need > 0) {
        $give = min($spare, $need);
        foreach ($w as $i => $_) $w[$i] += ($want[$i] - $min[$i]) / $need * $give;
        $spare -= $give;
    }
    if ($spare > 0.5) { $s = array_sum($w); foreach ($w as $i => $_) $w[$i] += $w[$i] / $s * $spare; }
    return $w;
}

function pdf_build(string $title, string $subtitle, array $headers, array $rows): string
{
    $PW = 842.0; $PH = 595.0; $M = 28.0; $bottom = 40.0;
    $size = 7.4; $hsize = 7.8; $lead = 9.2; $pad = 3.2;
    $title = pdf_cp($title); $subtitle = pdf_cp($subtitle);
    $hdr = array_map(fn($h) => pdf_cp((string)$h), $headers);
    $data = [];
    foreach ($rows as $r) { $line = []; foreach ($headers as $i => $_) $line[] = pdf_cp(cell_text($r[$i] ?? '')); $data[] = $line; }
    $weights = pdf_col_widths($hdr, $data, $PW - 2 * $M, $size, $hsize, $pad);

    // Layout pass: assign rows to pages
    $pages = []; $page = []; $y = 0.0; $first = true;
    $hLines = []; $hMax = 1;
    foreach ($hdr as $i => $txt) { $hLines[$i] = pdf_wrap($txt, $weights[$i] - 2 * $pad, $hsize, 3, true); $hMax = max($hMax, count($hLines[$i])); }
    $headRowH = $hMax * ($hsize + 1.8) + 2 * $pad;
    $startY = fn(bool $f) => $f ? $PH - 92 : $PH - 44;
    $y = $startY($first) - $headRowH;
    foreach ($data as $ri => $cells) {
        $wrapped = []; $maxL = 1;
        foreach ($cells as $i => $txt) { $wrapped[$i] = pdf_wrap($txt, $weights[$i] - 2 * $pad, $size); $maxL = max($maxL, count($wrapped[$i])); }
        $rh = $maxL * $lead + 2 * $pad - 1;
        if ($y - $rh < $bottom) { $pages[] = $page; $page = []; $first = false; $y = $startY(false) - $headRowH; }
        $page[] = ['y' => $y, 'h' => $rh, 'w' => $wrapped, 'z' => $ri % 2 === 1];
        $y -= $rh;
    }
    $pages[] = $page;
    $total = count($pages);

    $streams = [];
    foreach ($pages as $pi => $prow) {
        $s = '';
        $topY = $startY($pi === 0);
        if ($pi === 0) {
            $s .= "0.85 0.42 0.15 rg 0 " . ($PH - 30) . " $PW 30 re f\n";
            $s .= "BT /F2 15 Tf 1 1 1 rg $M " . ($PH - 21) . " Td (" . pdf_esc($title) . ") Tj ET\n";
            $s .= "BT /F1 8.5 Tf 0.25 0.25 0.25 rg $M " . ($PH - 47) . " Td (" . pdf_esc($subtitle) . ") Tj ET\n";
            $s .= "BT /F1 7.5 Tf 0.5 0.5 0.5 rg $M " . ($PH - 60) . " Td (Generated " . date('j M Y, H:i') . ' - ' . count($rows) . " record(s)) Tj ET\n";
        } else {
            $s .= "BT /F2 9 Tf 0.85 0.42 0.15 rg $M " . ($PH - 28) . " Td (" . pdf_esc($title) . " (continued)) Tj ET\n";
        }
        // header row
        $hy = $topY - $headRowH;
        $s .= "0.17 0.17 0.17 rg $M $hy " . ($PW - 2 * $M) . " $headRowH re f\n";
        $x = $M;
        foreach ($hdr as $i => $txt) {
            foreach ($hLines[$i] as $li => $ln)
                $s .= "BT /F2 $hsize Tf 1 1 1 rg " . round($x + $pad, 2) . ' ' . round($hy + $headRowH - $pad - $hsize + 1 - $li * ($hsize + 1.8), 2) . " Td (" . pdf_esc($ln) . ") Tj ET\n";
            $x += $weights[$i];
        }
        foreach ($prow as $row) {
            $ry = $row['y'] - $row['h'];
            if ($row['z']) $s .= "0.975 0.955 0.92 rg $M " . round($ry, 2) . ' ' . ($PW - 2 * $M) . ' ' . round($row['h'], 2) . " re f\n";
            $s .= "0.86 0.84 0.8 RG 0.4 w $M " . round($ry, 2) . ' m ' . ($PW - $M) . ' ' . round($ry, 2) . " l S\n";
            $x = $M;
            foreach ($row['w'] as $i => $lines) {
                foreach ($lines as $li => $ln) {
                    $s .= "BT /F1 $size Tf 0.1 0.1 0.1 rg " . round($x + $pad, 2) . ' ' . round($row['y'] - $pad - $size - $li * $lead + 1.2, 2) . " Td (" . pdf_esc($ln) . ") Tj ET\n";
                }
                $x += $weights[$i];
            }
        }
        $foot = 'Chill & Bite MEATINGS - Page ' . ($pi + 1) . ' of ' . $total;
        $s .= "BT /F1 7 Tf 0.5 0.5 0.5 rg $M 18 Td (" . pdf_esc($foot) . ") Tj ET\n";
        $streams[] = $s;
    }

    // Assemble objects
    $objs = [];
    $objs[1] = '<< /Type /Catalog /Pages 2 0 R >>';
    $kids = ''; for ($i = 0; $i < $total; $i++) $kids .= (6 + 2 * $i) . ' 0 R ';
    $objs[2] = '<< /Type /Pages /Kids [' . trim($kids) . '] /Count ' . $total . ' >>';
    $objs[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
    $objs[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
    foreach ($streams as $i => $s) {
        $z = gzcompress($s, 6);
        $objs[5 + 2 * $i] = "<< /Length " . strlen($z) . " /Filter /FlateDecode >>\nstream\n" . $z . "\nendstream";
        $objs[6 + 2 * $i] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 $PW $PH] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents " . (5 + 2 * $i) . " 0 R >>";
    }
    $out = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n"; $offsets = [];
    foreach ($objs as $n => $body) { $offsets[$n] = strlen($out); $out .= "$n 0 obj\n$body\nendobj\n"; }
    $xref = strlen($out);
    $out .= "xref\n0 " . (count($objs) + 1) . "\n0000000000 65535 f \n";
    foreach ($offsets as $off) $out .= sprintf("%010d 00000 n \n", $off);
    $out .= "trailer\n<< /Size " . (count($objs) + 1) . " /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";
    return $out;
}

/* ================= output ================= */
function export_send(string $format, string $title, string $subtitle, array $headers, array $rows, string $basename): never
{
    $format = strtolower($format);
    $name = preg_replace('/[^a-z0-9_-]+/i', '-', $basename) . '-' . date('Ymd-His');
    switch ($format) {
        case 'csv':
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $name . '.csv"');
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            $safe = fn($v) => is_string($v) && $v !== '' && strpbrk($v[0], "=+-@\t\r") ? "'" . $v : $v; // spreadsheet formula-injection guard
            fputcsv($out, $headers);
            foreach ($rows as $r) fputcsv($out, array_map($safe, $r));
            exit;
        case 'xlsx':  $data = xlsx_build($title, $subtitle, $headers, $rows); $type = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'; break;
        case 'docx':  $data = docx_build($title, $subtitle, $headers, $rows); $type = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'; break;
        case 'pdf':   $data = pdf_build($title, $subtitle, $headers, $rows);  $type = 'application/pdf'; break;
        default: http_response_code(400); exit('Unknown export format.');
    }
    header('Content-Type: ' . $type);
    header('Content-Disposition: attachment; filename="' . $name . '.' . $format . '"');
    header('Content-Length: ' . strlen($data));
    header('Cache-Control: private, no-store');
    echo $data;
    exit;
}

/** Row of export buttons for a dataset, carrying the current filters in the query string. */
function export_buttons(string $dataset, array $query = []): string
{
    $out = '<div class="export-bar" role="group" aria-label="Export"><span>Export</span>';
    foreach (['pdf' => 'PDF', 'docx' => 'Word', 'xlsx' => 'Excel', 'csv' => 'CSV'] as $fmt => $label)
        $out .= '<a class="btn btn-ghost btn-sm exp-' . $fmt . '" href="' . h(url('admin/export/' . $dataset . '?' . http_build_query(array_filter($query + ['format' => $fmt], fn($v) => $v !== '' && $v !== null)))) . '">' . $label . '</a>';
    return $out . '</div>';
}

/* ================= datasets ================= */
/** Inquiry types a role may see (shared with the Inquiries screen). */
function inquiry_scope(string $role): array
{
    return ['admin' => ['contact', 'performance', 'dj', 'advertising'], 'marketing' => ['advertising', 'dj'], 'editor' => ['contact', 'performance', 'dj'], 'event_manager' => ['contact', 'performance', 'dj'], 'dj_manager' => ['dj']][$role] ?? [];
}
function dataset_meta(): array
{
    return [
        'reservations' => ['perm' => ['reservations', 'reports'], 'label' => 'Reservations'],
        'payments'     => ['perm' => ['payments', 'reports'], 'label' => 'Payments ledger'],
        'guestlist'    => ['perm' => ['reservations', 'checkin', 'reports'], 'label' => 'Guest list'],
        'events'       => ['perm' => ['events', 'reports'], 'label' => 'Event summary'],
        'inquiries'    => ['perm' => ['inquiries'], 'label' => 'Inquiries'],
        'partners'     => ['perm' => ['advertising'], 'label' => 'Partner applications'],
        'collections'       => ['perm' => ['collections'], 'label' => 'Collections by channel'],
        'visitors'          => ['perm' => ['analytics'], 'label' => 'Visitor statistics report'],
        'visitor_pages'     => ['perm' => ['analytics'], 'label' => 'Most visited pages'],
        'visitor_countries' => ['perm' => ['analytics'], 'label' => 'Visitors by country'],
        'visitor_daily'     => ['perm' => ['analytics'], 'label' => 'Visitors per day'],
    ];
}
function report_range(): array
{
    $from = !empty($_GET['from']) && strtotime((string)$_GET['from']) ? date('Y-m-d', strtotime((string)$_GET['from'])) : null;
    $to = !empty($_GET['to']) && strtotime((string)$_GET['to']) ? date('Y-m-d', strtotime((string)$_GET['to'])) : null;
    return [$from, $to];
}
/** Build [title, subtitle, headers, rows] for a dataset from the current request filters. */
function export_dataset(string $name): ?array
{
    if (str_starts_with($name, 'visitor')) return analytics_export_dataset($name);
    if ($name === 'collections') return collections_export_dataset();
    switch ($name) {
        case 'reservations': {
            [$w, $p] = reservation_filters();
            [$from, $to] = report_range();
            if ($from) { $w .= ' AND DATE(r.created_at) >= ?'; $p[] = $from; }
            if ($to) { $w .= ' AND DATE(r.created_at) <= ?'; $p[] = $to; }
            $sub = [];
            if (!empty($_GET['event'])) $sub[] = 'Event: ' . (val('SELECT title FROM events WHERE id = ?', [(int)$_GET['event']]) ?? '?');
            if (!empty($_GET['status'])) $sub[] = 'Status: ' . $_GET['status'];
            if (!empty($_GET['package'])) $sub[] = 'Package: ' . ($_GET['package'] === '__none' ? 'none' : $_GET['package']);
            if ($from || $to) $sub[] = 'Booked ' . ($from ?: '...') . ' to ' . ($to ?: '...');
            if (!empty($_GET['q'])) $sub[] = 'Search: ' . $_GET['q'];
            $rows = [];
            foreach (rows(RES_SELECT . " WHERE $w ORDER BY e.start_at, r.full_name", $p) as $r)
                $rows[] = [$r['reference'], $r['event_title'], $r['full_name'], $r['phone'], (string)$r['email'], (string)$r['company'], RES_TYPES[$r['type']], (string)$r['package'], (int)$r['guests'],
                    ucfirst($r['status']), (int)$r['amount_due'], (int)$r['paid'], max(0, (int)$r['amount_due'] - (int)$r['paid']), $r['checked_in_at'] ? 'Yes' : 'No', $r['created_at']];
            return ['Reservations', $sub ? implode(' | ', $sub) : 'All reservations',
                ['Reference', 'Event', 'Guest', 'Phone', 'Email', 'Company', 'Type', 'Package', 'Guests', 'Status', 'Amount due (UGX)', 'Paid (UGX)', 'Balance (UGX)', 'Checked in', 'Booked at'], $rows];
        }
        case 'payments': {
            [$from, $to] = report_range();
            $w = '1=1'; $p = []; $sub = [];
            if (!empty($_GET['event'])) { $w .= ' AND r.event_id = ?'; $p[] = (int)$_GET['event']; $sub[] = 'Event: ' . (val('SELECT title FROM events WHERE id = ?', [(int)$_GET['event']]) ?? '?'); }
            if ($from) { $w .= ' AND DATE(p.created_at) >= ?'; $p[] = $from; }
            if ($to) { $w .= ' AND DATE(p.created_at) <= ?'; $p[] = $to; }
            if ($from || $to) $sub[] = 'Paid ' . ($from ?: '...') . ' to ' . ($to ?: '...');
            $rows = [];
            foreach (rows("SELECT p.*, r.reference, r.full_name, e.title, u.name AS by_name FROM payments p JOIN reservations r ON r.id = p.reservation_id JOIN events e ON e.id = r.event_id
                           LEFT JOIN users u ON u.id = p.recorded_by WHERE $w ORDER BY p.created_at DESC", $p) as $r)
                $rows[] = [$r['created_at'], $r['reference'], $r['full_name'], $r['title'], str_replace('_', ' ', $r['method']), (int)$r['amount'], (string)$r['by_name'], (string)$r['note']];
            return ['Payments ledger', $sub ? implode(' | ', $sub) : 'All recorded payments and refunds (refunds are negative)',
                ['Date', 'Reference', 'Guest', 'Event', 'Method', 'Amount (UGX)', 'Recorded by', 'Note'], $rows];
        }
        case 'guestlist': {
            $eid = (int)($_GET['event'] ?? 0) ?: (int)val("SELECT id FROM events WHERE status='published' AND COALESCE(end_at,start_at) >= NOW() ORDER BY start_at LIMIT 1");
            $ev = row('SELECT * FROM events WHERE id = ?', [$eid]);
            if (!$ev) return ['Guest list', 'No event selected', ['Name'], []];
            $contact = can('reservations') || can('reports');
            $rows = [];
            foreach (rows(RES_SELECT . " WHERE r.event_id = ? AND r.status = 'confirmed' ORDER BY r.full_name", [$eid]) as $r) {
                $line = [$r['full_name'], RES_TYPES[$r['type']], (string)$r['package'], (int)$r['guests'], $r['reference']];
                if ($contact) { $line[] = $r['phone']; $line[] = (string)$r['company']; }
                $line[] = max(0, (int)$r['amount_due'] - (int)$r['paid']);
                $line[] = $r['checked_in_at'] ? 'Yes' : 'No';
                $rows[] = $line;
            }
            $heads = ['Name', 'Type', 'Package', 'Guests', 'Reference'];
            if ($contact) { $heads[] = 'Phone'; $heads[] = 'Company'; }
            $heads[] = 'Balance (UGX)'; $heads[] = 'Checked in';
            return ['Guest list: ' . $ev['title'], fmt_date($ev['start_at'], 'l, j F Y') . ' - confirmed bookings', $heads, $rows];
        }
        case 'events': {
            $rows = [];
            foreach (rows("SELECT e.*, c.name AS cat,
                    (SELECT COALESCE(SUM(guests),0) FROM reservations r WHERE r.event_id=e.id AND r.status IN ('pending','confirmed')) AS booked,
                    (SELECT COALESCE(SUM(guests),0) FROM reservations r WHERE r.event_id=e.id AND r.status='confirmed') AS confirmed,
                    (SELECT COALESCE(SUM(guests),0) FROM reservations r WHERE r.event_id=e.id AND r.status='pending') AS pending,
                    (SELECT COALESCE(SUM(guests),0) FROM reservations r WHERE r.event_id=e.id AND r.status='confirmed' AND r.checked_in_at IS NOT NULL) AS checked,
                    (SELECT COALESCE(SUM(p.amount),0) FROM payments p JOIN reservations r ON r.id=p.reservation_id WHERE r.event_id=e.id) AS revenue,
                    (SELECT COALESCE(SUM(GREATEST(r.amount_due - COALESCE((SELECT SUM(amount) FROM payments p WHERE p.reservation_id=r.id),0),0)),0) FROM reservations r WHERE r.event_id=e.id AND r.status IN ('pending','confirmed')) AS outstanding
                FROM events e LEFT JOIN event_categories c ON c.id = e.category_id ORDER BY e.start_at DESC") as $e)
                $rows[] = [$e['title'], (string)$e['cat'], fmt_date($e['start_at'], 'j M Y'), ucfirst($e['status']), $e['capacity'] === null ? 'Unlimited' : (int)$e['capacity'],
                    (int)$e['booked'], (int)$e['confirmed'], (int)$e['pending'], (int)$e['checked'], (int)$e['revenue'], (int)$e['outstanding']];
            return ['Event summary', 'Capacity, bookings, attendance and revenue per event',
                ['Event', 'Category', 'Date', 'Status', 'Capacity', 'Guests booked', 'Confirmed', 'Pending', 'Checked in', 'Revenue (UGX)', 'Outstanding (UGX)'], $rows];
        }
        case 'inquiries': {
            $scope = inquiry_scope(current_user()['role'] ?? '');
            $type = in_array($_GET['type'] ?? '', $scope, true) ? $_GET['type'] : null;
            $types = $type ? [$type] : $scope;
            if (!$types) return null;
            $in = implode(',', array_fill(0, count($types), '?'));
            $rows = [];
            foreach (rows("SELECT * FROM inquiries WHERE type IN ($in) ORDER BY id DESC", $types) as $i) {
                $d = json_decode((string)$i['details'], true) ?: [];
                $det = implode('; ', array_map(fn($k, $v) => str_replace('_', ' ', $k) . ': ' . $v, array_keys($d), $d));
                $rows[] = [$i['created_at'], ucfirst($i['type']), $i['name'], (string)$i['organization'], (string)$i['phone'], (string)$i['email'], (string)$i['subject'], (string)$i['message'], $det, str_replace('_', ' ', $i['status']), (string)$i['agreed_package']];
            }
            return ['Inquiries', $type ? ucfirst($type) . ' inquiries' : 'All inquiries you can access',
                ['Received', 'Type', 'Name', 'Organization', 'Phone', 'Email', 'Subject', 'Message', 'Details', 'Status', 'Agreed package'], $rows];
        }
        case 'partners': {
            $rows = [];
            foreach (rows('SELECT * FROM partner_applications ORDER BY id DESC') as $p)
                $rows[] = [$p['created_at'], $p['company'], $p['contact_name'], $p['phone'], $p['email'], (string)$p['website'], $p['partnership_type'], (string)$p['industry'], ucfirst($p['status']), (string)$p['admin_note']];
            return ['Partner applications', 'All partnership applications',
                ['Received', 'Company', 'Contact', 'Phone', 'Email', 'Website', 'Partnership type', 'Industry', 'Status', 'Admin note'], $rows];
        }
    }
    return null;
}
