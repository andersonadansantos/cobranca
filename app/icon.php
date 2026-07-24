<?php
header('Content-Type: image/png');
header('Cache-Control: public, max-age=604800');

$size = intval($_GET['size'] ?? 192);
if (!in_array($size, [72, 96, 128, 144, 152, 192, 384, 512])) $size = 192;

$img = imagecreatetruecolor($size, $size);

$bg = imagecolorallocate($img, 108, 92, 231);
$fg = imagecolorallocate($img, 255, 255, 255);
imagefill($img, 0, 0, $bg);

$r = intval($size * 0.22);
imagesetruecolor($img, true);
imagefilledellipse($img, $size/2, $size/2, $size*0.55, $size*0.55, $fg);
imagefilledellipse($img, $size/2, $size/2, $size*0.38, $size*0.38, $bg);

$lineW = max(2, intval($size * 0.04));
imagesetthickness($img, $lineW);
$cx = $size / 2;
$cy = $size / 2;
$ir = $size * 0.12;
$or = $size * 0.2;
imagearc($img, $cx, $cy - $size*0.02, $or*2, $or*2, 200, 340, $fg);
imageline($img, $cx - $ir, $cy + $size*0.04, $cx + $or*0.8, $cy + $size*0.14, $fg);

imagepng($img);
imagedestroy($img);