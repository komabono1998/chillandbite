<?php
chdir(__DIR__ . '/..');
function rep(string $f, string $old, string $new): void {
    $s = file_get_contents($f);
    $p = strpos($s, $old);
    if ($p === false) {
        $o2 = str_replace("\n", "\r\n", $old);
        $p = strpos($s, $o2);
        if ($p === false) die("NO MATCH in $f: " . substr($old, 0, 70) . "\n");
        $old = $o2; $new = str_replace("\n", "\r\n", $new);
    }
    file_put_contents($f, substr_replace($s, $new, $p, strlen($old)));
    echo "ok $f\n";
}
// admin form: tick box (ticked by default for new uploads)
rep('app/views/admin/djs.php',
    "    <p class=\"small muted\">Uploaded audio files always get a <b>Download mix</b> button on the website. Mixes added as links cannot be downloaded.</p>",
    "    <label class=\"chk\"><input type=\"checkbox\" name=\"allow_download\" value=\"1\"<?= \$v('allow_download', 1) ? ' checked' : '' ?>> Make this mix available for download <span class=\"muted small\">(shows a Download mix button on the website; only for uploaded audio files)</span></label>");
// controller: read the tick box again
rep('app/controllers/djs.php', "external_url, allow_download, featured, status=? WHERE id=?', [...\$v, \$id]);", "external_url, allow_download, featured, status=? WHERE id=?', [...\$v, \$id]);");
rep('app/controllers/djs.php', "\$url ?: null,\n                  1, ", "\$url ?: null,\n                  empty(\$_POST['allow_download']) ? 0 : 1, ");
