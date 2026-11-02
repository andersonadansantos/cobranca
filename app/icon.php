<?php
header('Content-Type: image/png');
header('Cache-Control: public, max-age=604800');

$size = intval($_GET['size'] ?? 192);
if (!in_array($size, [72, 96, 128, 144, 152, 192, 384, 512], true)) {
    $size = 192;
}

// Favicon oficial do sistema (assets/img/favicon.png), servido em todos os
// tamanhos usados pelo manifest do PWA e pelos <link rel="icon">.
$fonte = dirname(__DIR__) . '/assets/img/favicon.png';
$src   = (is_file($fonte) && function_exists('imagecreatefrompng')) ? @imagecreatefrompng($fonte) : false;

if ($src !== false) {
    $sw  = imagesx($src);
    $sh  = imagesy($src);
    $img = imagecreatetruecolor($size, $size);
    imagealphablending($img, false);
    imagesavealpha($img, true);
    $transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
    imagefilledrectangle($img, 0, 0, $size, $size, $transparent);
    imagealphablending($img, true);

    $ratio = min($size / $sw, $size / $sh);
    $nw    = max(1, (int)round($sw * $ratio));
    $nh    = max(1, (int)round($sh * $ratio));
    $dx    = (int)(($size - $nw) / 2);
    $dy    = (int)(($size - $nh) / 2);
    imagecopyresampled($img, $src, $dx, $dy, 0, 0, $nw, $nh, $sw, $sh);

    imagepng($img);
    imagedestroy($img);
    imagedestroy($src);
    exit;
}

// Fallback: ícone genérico (favicon indisponível)
$img = imagecreatetruecolor($size, $size);
$bg  = imagecolorallocate($img, 108, 92, 231);
$fg  = imagecolorallocate($img, 255, 255, 255);
imagefill($img, 0, 0, $bg);
$cx = (int)($size / 2);
$cy = (int)($size / 2);
imagefilledellipse($img, $cx, $cy, (int)($size * 0.55), (int)($size * 0.55), $fg);
imagefilledellipse($img, $cx, $cy, (int)($size * 0.38), (int)($size * 0.38), $bg);
imagesetthickness($img, max(2, (int)($size * 0.04)));
imagearc($img, $cx, $cy - (int)($size * 0.02), (int)($size * 0.40), (int)($size * 0.40), 200, 340, $fg);
imageline($img, $cx - (int)($size * 0.12), $cy + (int)($size * 0.04), $cx + (int)($size * 0.16), $cy + (int)($size * 0.14), $fg);
imagepng($img);
imagedestroy($img);
