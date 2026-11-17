<?php
$src = __DIR__ . '/images/icons/icon-512.png';
$dst = __DIR__ . '/images/icons/icon-192.png';
$fromAssets = dirname(__DIR__, 2) . '/Users'; // noop
// Prefer cursor assets if local 512 missing
$candidates = [
    $src,
    'C:/Users/Jay Soni/.cursor/projects/c-xampp-htdocs-chandrakala-jewellers-CJ/assets/pwa-icon-1024.png',
];
$in = null;
foreach ($candidates as $c) {
    if (is_file($c)) { $in = $c; break; }
}
if (!$in) {
    fwrite(STDERR, "no source icon\n");
    exit(1);
}
@mkdir(dirname($src), 0777, true);
if ($in !== $src) {
    copy($in, $src);
}
if (!extension_loaded('gd')) {
    copy($src, $dst);
    echo "copied-without-gd\n";
    exit(0);
}
$i = imagecreatefrompng($in);
$a = imagecreatetruecolor(192, 192);
imagealphablending($a, false);
imagesavealpha($a, true);
imagecopyresampled($a, $i, 0, 0, 0, 0, 192, 192, imagesx($i), imagesy($i));
imagepng($a, $dst);
$b = imagecreatetruecolor(512, 512);
imagealphablending($b, false);
imagesavealpha($b, true);
imagecopyresampled($b, $i, 0, 0, 0, 0, 512, 512, imagesx($i), imagesy($i));
imagepng($b, $src);
echo "ok\n";
