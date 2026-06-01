<?php

/**
 * Genereer PWA-icons met GD.
 *
 * Sprint 1 (SP-24): dummy/placeholder icons zodat de manifest geldig is.
 * Sprint 5 (SP-26): vervangen door definitieve, gepolijste icons.
 *
 * Draai met:  php scripts/generate-pwa-icons.php
 */
$outDir = __DIR__.'/../public/icons';
if (! is_dir($outDir)) {
    mkdir($outDir, 0o755, true);
}

$bg = [15, 23, 42];      // #0f172a slate-900
$accent = [16, 185, 129]; // #10b981 emerald-500
$white = [241, 245, 249]; // #f1f5f9

/**
 * Teken een "pulse"-lijn (sparkline-achtig) plus een opgaande pijl als logo.
 */
function drawIcon(int $size, bool $maskable, array $bg, array $accent, array $white): GdImage
{
    $img = imagecreatetruecolor($size, $size);
    imagesavealpha($img, true);

    $bgColor = imagecolorallocate($img, ...$bg);
    $accentColor = imagecolorallocate($img, ...$accent);
    $whiteColor = imagecolorallocate($img, ...$white);

    // Achtergrond (afgeronde look via volledige vlakvulling; maskable = meer safe-zone).
    imagefilledrectangle($img, 0, 0, $size, $size, $bgColor);

    // Bij maskable houden we ~20% safe-zone marge aan voor de glyph.
    $pad = $maskable ? (int) round($size * 0.22) : (int) round($size * 0.14);
    $inner = $size - 2 * $pad;

    imagesetthickness($img, max(2, (int) round($size * 0.045)));

    // Pulse/sparkline-pad binnen de inner box.
    $points = [
        [0.00, 0.62], [0.18, 0.62], [0.30, 0.78],
        [0.48, 0.28], [0.62, 0.52], [0.74, 0.40], [1.00, 0.40],
    ];
    $prev = null;
    foreach ($points as $p) {
        $x = $pad + (int) round($p[0] * $inner);
        $y = $pad + (int) round($p[1] * $inner);
        if ($prev !== null) {
            imageline($img, $prev[0], $prev[1], $x, $y, $accentColor);
        }
        $prev = [$x, $y];
    }

    // Opgaande pijlpunt aan het eind van de lijn.
    $ex = $pad + $inner;
    $ey = $pad + (int) round(0.40 * $inner);
    $a = max(4, (int) round($size * 0.06));
    imagefilledpolygon($img, [
        $ex, $ey - $a,
        $ex, $ey + $a,
        $ex + (int) round($a * 1.4), $ey,
    ], $accentColor);

    // Kleine "SP"-markering linksboven in wit voor herkenbaarheid.
    if (! $maskable && $size >= 192) {
        $fontSize = max(3, (int) round($size / 80));
        imagestring($img, 5, $pad, $pad - 2, 'SP', $whiteColor);
        unset($fontSize);
    }

    return $img;
}

$variants = [
    'icon-192.png' => [192, false],
    'icon-512.png' => [512, false],
    'icon-maskable-512.png' => [512, true],
    'apple-touch-icon.png' => [180, false],
];

foreach ($variants as $file => [$size, $maskable]) {
    $img = drawIcon($size, $maskable, $bg, $accent, $white);
    imagepng($img, $outDir.'/'.$file);
    imagedestroy($img);
    echo "wrote {$file} ({$size}x{$size})\n";
}

// Monochroom badge-icoon (72px) voor notificaties (gebruikt in Sprint 5).
$badge = imagecreatetruecolor(72, 72);
imagesavealpha($badge, true);
$transparent = imagecolorallocatealpha($badge, 0, 0, 0, 127);
imagefill($badge, 0, 0, $transparent);
$wht = imagecolorallocate($badge, 255, 255, 255);
imagesetthickness($badge, 5);
imageline($badge, 12, 46, 28, 46, $wht);
imageline($badge, 28, 46, 36, 56, $wht);
imageline($badge, 36, 56, 46, 18, $wht);
imageline($badge, 46, 18, 54, 34, $wht);
imageline($badge, 54, 34, 60, 28, $wht);
imagepng($badge, $outDir.'/badge-72.png');
imagedestroy($badge);
echo "wrote badge-72.png (72x72)\n";

echo "done\n";
