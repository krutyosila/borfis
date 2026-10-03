<?php
declare(strict_types=1);
defined('BORFIS') || exit;

/*
 * Ürün illüstrasyonları: ürün adı ve içindekiler metninden katman katman SVG çizer.
 * Kalın kontur + düz renk, sitenin çizgi diliyle aynı. Fotoğraf yüklenen ürünlerde kullanılmaz.
 */

const ART_VERSION = 1;
const ART_INK = '#1E1716';

function art_lower(string $s): string
{
    // Eşleştirme için ı/i farkını yok say (SPRITE → sprite, KIRMIZI → kirmizi)
    return str_replace('ı', 'i', mb_strtolower(strtr($s, ['İ' => 'i', 'I' => 'ı']), 'UTF-8'));
}

function art_has(string $text, string ...$needles): bool
{
    foreach ($needles as $n) {
        if (str_contains($text, art_lower($n))) {
            return true;
        }
    }
    return false;
}

/** Kartlarda kullanılacak görsel adresi (içerik değişince önbellek yenilenir). */
function art_url(array $p): string
{
    $v = substr(md5($p['name'] . '|' . $p['description'] . '|' . ($p['category_id'] ?? '') . '|' . ART_VERSION), 0, 10);
    return '/art.php?p=' . (int) $p['id'] . '&v=' . $v;
}

function product_art_svg(array $p, string $categoryName = ''): string
{
    $name = art_lower($p['name']);
    $text = art_lower($p['name'] . ' ' . $p['description']);
    $cat = art_lower($categoryName);

    if (art_has($cat, 'içecek') || art_has($name, 'cola', 'sprite', 'fanta', 'fuse tea', 'ayran', 'limonata', 'soda') || $name === 'su') {
        return art_frame(art_drink($name), 300);
    }
    if (art_has($name, 'patates')) {
        return art_frame(art_fries($text), 300);
    }
    if (art_has($name, 'hellim')) {
        return art_frame(art_hellim(), 300);
    }
    if (art_has($name, 'paçanga')) {
        return art_frame(art_pacanga(), 300);
    }
    if (art_has($name, 'tenders')) {
        return art_frame(art_tenders(), 300);
    }
    if (art_has($cat, 'extra') || art_has($cat, 'ekstra')) {
        return art_frame(art_extra($name), 300);
    }
    [$body, $h] = art_burger($text);
    if (art_has($name, 'wrap')) {
        return art_frame(art_wrap_side($text) . '<g transform="translate(-18 0) scale(0.92)">' . $body . '</g>', max($h, 300));
    }
    return art_frame($body, $h);
}

function art_frame(string $body, int $h): string
{
    $h = max($h, 260);
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 ' . $h . '" width="800" height="' . ($h * 2) . '" fill="none" stroke="' . ART_INK
        . '" stroke-width="5" stroke-linejoin="round" stroke-linecap="round">' . $body . '</svg>';
}

/* ---------------- Burger ---------------- */

function art_burger(string $t): array
{
    $sauces = [];
    foreach ([
        'barbekü' => '#6B2E1C', 'peri peri' => '#E2552D', 'acılı mayonez' => '#E8733A',
        "börfi's sos" => '#E9A34B', 'ranch' => '#F6F1E4', 'sarımsaklı mayonez' => '#F6F1E4',
    ] as $k => $c) {
        if (art_has($t, $k) && !in_array($c, $sauces, true)) {
            $sauces[] = $c;
        }
    }
    $protein = art_has($t, 'tavuk') ? 'chicken' : 'patty';

    // Yukarıdan aşağıya katmanlar
    $stack = [];
    if ($sauces) {
        $stack[] = ['sauce', $sauces[0]];
    }
    $map = [
        'marul' => 'lettuce', 'domates' => 'tomato', 'avokado' => 'avocado', 'kırmızı soğan' => 'redonion',
        'turşu' => 'pickle', 'coleslaw' => 'coleslaw', 'mantar' => 'mushroom', 'mütebbel' => 'mutebbel',
        'kapiçyo' => 'kapicyo', 'bacon' => 'bacon', 'çıtır soğan' => 'crispyonion', 'çıtır patates' => 'crispypotato',
        'karamelize' => 'caramel', 'cheddar' => 'cheddar',
    ];
    foreach ($map as $k => $layer) {
        if (art_has($t, $k)) {
            $stack[] = [$layer, null];
        }
    }
    if (count($sauces) > 1) {
        $stack[] = ['sauce', $sauces[1]];
    }
    $stack[] = [$protein, null];

    $y = 14;
    $parts = [];
    $parts[] = art_bun_top($y, art_has($t, 'susam'));
    $y += 92;
    foreach ($stack as [$layer, $arg]) {
        [$svg, $h] = art_layer($layer, $y, $arg);
        $parts[] = $svg;
        $y += $h;
    }
    $parts[] = art_bun_bottom($y);
    $y += 46;
    // Altta olan önce çizilir ki üst katmanlar (peynir damlası vb.) üstüne binsin
    return [implode('', array_reverse($parts)), $y + 10];
}

function art_bun_top(int $y, bool $blackSesame): string
{
    $b = $y + 90;
    $s = '<path d="M46 ' . $b . ' C46 ' . ($y - 10) . ' 354 ' . ($y - 10) . ' 354 ' . $b . ' Z" fill="#E08A2C"/>';
    $s .= '<path d="M92 ' . ($y + 40) . ' C112 ' . ($y + 18) . ' 150 ' . ($y + 10) . ' 182 ' . ($y + 12) . '" stroke="#F4B866" stroke-width="10"/>';
    $seed = $blackSesame ? '#2A211F' : '#FFF3D6';
    foreach ([[140, 42, -20], [200, 26, 0], [255, 40, 20], [172, 64, 10], [228, 66, -15], [300, 70, 25], [104, 72, -30]] as [$x, $dy, $r]) {
        $s .= '<ellipse cx="' . $x . '" cy="' . ($y + $dy) . '" rx="8" ry="4.5" transform="rotate(' . $r . ' ' . $x . ' ' . ($y + $dy) . ')" fill="' . $seed . '" stroke-width="2.5"/>';
    }
    return $s;
}

function art_bun_bottom(int $y): string
{
    return '<path d="M50 ' . $y . ' L350 ' . $y . ' C350 ' . ($y + 50) . ' 50 ' . ($y + 50) . ' 50 ' . $y . ' Z" fill="#E08A2C"/>';
}

function art_wave(int $y, int $amp, int $x0 = 36, int $x1 = 364, int $step = 41): string
{
    $d = 'M' . $x0 . ' ' . $y;
    $up = true;
    for ($x = $x0; $x < $x1; $x += $step) {
        $nx = min($x + $step, $x1);
        $d .= ' Q' . ($x + $step / 2) . ' ' . ($up ? $y - $amp : $y + $amp) . ' ' . $nx . ' ' . $y;
        $up = !$up;
    }
    return $d;
}

/** @return array{0:string,1:int} [svg, yükseklik] */
function art_layer(string $layer, int $y, ?string $arg = null): array
{
    switch ($layer) {
        case 'patty':
            $s = '<rect x="44" y="' . $y . '" width="312" height="40" rx="20" fill="#5A2E1A"/>'
                . '<path d="M84 ' . ($y + 16) . 'H140M178 ' . ($y + 24) . 'H246M282 ' . ($y + 16) . 'H326" stroke="#3A1C10" stroke-width="5"/>';
            return [$s, 36];
        case 'chicken':
            $d = 'M50 ' . ($y + 22);
            for ($x = 50; $x < 350; $x += 25) {
                $d .= ' Q' . ($x + 12) . ' ' . ($y - 4) . ' ' . ($x + 25) . ' ' . ($y + 4);
            }
            $d .= ' Q362 ' . ($y + 22) . ' 350 ' . ($y + 40);
            for ($x = 350; $x > 50; $x -= 25) {
                $d .= ' Q' . ($x - 12) . ' ' . ($y + 50) . ' ' . ($x - 25) . ' ' . ($y + 40);
            }
            $d .= ' Q38 ' . ($y + 30) . ' 50 ' . ($y + 22) . 'Z';
            $s = '<path d="' . $d . '" fill="#D9912E"/>';
            foreach ([96, 150, 206, 258, 310] as $i => $x) {
                $s .= '<circle cx="' . $x . '" cy="' . ($y + 20 + ($i % 2) * 6) . '" r="4" fill="#F2C063" stroke="none"/>';
            }
            return [$s, 40];
        case 'cheddar':
            $s = '<path d="M38 ' . $y . 'H362L344 ' . ($y + 14) . 'Q338 ' . ($y + 34) . ' 330 ' . ($y + 14) . 'L276 ' . ($y + 12) . 'Q268 ' . ($y + 40)
                . ' 258 ' . ($y + 12) . 'L198 ' . ($y + 12) . 'Q190 ' . ($y + 30) . ' 182 ' . ($y + 12) . 'L118 ' . ($y + 12) . 'Q110 ' . ($y + 44) . ' 100 '
                . ($y + 12) . 'L58 ' . ($y + 12) . 'Z" fill="#F7C531"/>';
            return [$s, 10];
        case 'lettuce':
            $top = art_wave($y + 6, 9);
            $s = '<path d="' . $top . ' L360 ' . ($y + 20) . ' L40 ' . ($y + 20) . ' Z" fill="#5CB646"/>';
            return [$s, 16];
        case 'tomato':
            $s = '<rect x="50" y="' . $y . '" width="300" height="20" rx="10" fill="#E8463C"/>';
            foreach ([90, 150, 210, 270, 318] as $x) {
                $s .= '<ellipse cx="' . $x . '" cy="' . ($y + 10) . '" rx="5" ry="3" fill="#F7B1A8" stroke="none"/>';
            }
            return [$s, 18];
        case 'avocado':
            $s = '';
            foreach ([60, 134, 208, 282] as $x) {
                $s .= '<path d="M' . $x . ' ' . ($y + 18) . ' Q' . ($x + 30) . ' ' . ($y - 8) . ' ' . ($x + 62) . ' ' . ($y + 18) . ' Z" fill="#7FB24A"/>'
                    . '<path d="M' . ($x + 12) . ' ' . ($y + 16) . ' Q' . ($x + 30) . ' ' . ($y + 2) . ' ' . ($x + 50) . ' ' . ($y + 16) . '" fill="#D7E58A" stroke-width="2.5"/>';
            }
            return [$s, 16];
        case 'redonion':
            $s = '';
            foreach ([72, 132, 192, 252, 312] as $x) {
                $s .= '<ellipse cx="' . $x . '" cy="' . ($y + 7) . '" rx="26" ry="7" fill="#B45A9E"/><ellipse cx="' . $x . '" cy="' . ($y + 7) . '" rx="14" ry="3" fill="#E7B6D9" stroke-width="2.5"/>';
            }
            return [$s, 12];
        case 'pickle':
            $s = '';
            foreach ([76, 136, 196, 256, 316] as $x) {
                $s .= '<ellipse cx="' . $x . '" cy="' . ($y + 8) . '" rx="24" ry="8" fill="#7DAA3A"/><ellipse cx="' . $x . '" cy="' . ($y + 8) . '" rx="13" ry="4" fill="#C9DE8A" stroke-width="2.5"/>';
            }
            return [$s, 14];
        case 'coleslaw':
            $s = '<path d="' . art_wave($y + 6, 5, 44, 356, 26) . ' L356 ' . ($y + 16) . ' L44 ' . ($y + 16) . ' Z" fill="#F4F1E3"/>';
            foreach ([[90, '#8DC45A'], [140, '#B45A9E'], [190, '#F2A33A'], [240, '#8DC45A'], [290, '#B45A9E']] as [$x, $c]) {
                $s .= '<path d="M' . $x . ' ' . ($y + 9) . 'l18 -4" stroke="' . $c . '" stroke-width="4"/>';
            }
            return [$s, 14];
        case 'mushroom':
            $s = '';
            foreach ([64, 136, 208, 280] as $x) {
                $s .= '<path d="M' . $x . ' ' . ($y + 12) . ' Q' . ($x + 28) . ' ' . ($y - 10) . ' ' . ($x + 56) . ' ' . ($y + 12) . ' L' . ($x + 38) . ' ' . ($y + 12)
                    . ' L' . ($x + 38) . ' ' . ($y + 22) . ' L' . ($x + 18) . ' ' . ($y + 22) . ' L' . ($x + 18) . ' ' . ($y + 12) . ' Z" fill="#B8977A"/>';
            }
            $s .= '<path d="' . art_wave($y + 20, 3, 50, 350, 30) . '" stroke="#F3E9D6" stroke-width="5"/>';
            return [$s, 20];
        case 'mutebbel':
            $s = '<path d="' . art_wave($y + 4, 5, 44, 356, 30) . ' L356 ' . ($y + 16) . ' L44 ' . ($y + 16) . ' Z" fill="#D9C3A2"/>';
            foreach ([90, 150, 205, 262, 316] as $x) {
                $s .= '<circle cx="' . $x . '" cy="' . ($y + 9) . '" r="2.5" fill="#6B4A2B" stroke="none"/>';
            }
            return [$s, 14];
        case 'kapicyo':
            $s = '<path d="' . art_wave($y + 4, 5, 44, 356, 30) . ' L356 ' . ($y + 16) . ' L44 ' . ($y + 16) . ' Z" fill="#F6E4C8"/>';
            foreach ([84, 128, 176, 224, 270, 318] as $x) {
                $s .= '<rect x="' . $x . '" y="' . ($y + 6) . '" width="9" height="5" rx="2" fill="#D9402F" stroke="none"/>';
            }
            return [$s, 14];
        case 'bacon':
            $s = '<path d="M40 ' . $y . ' Q80 ' . ($y - 8) . ' 120 ' . $y . ' T200 ' . $y . ' T280 ' . $y . ' T360 ' . $y . ' L360 ' . ($y + 16)
                . ' Q320 ' . ($y + 24) . ' 280 ' . ($y + 16) . ' T200 ' . ($y + 16) . ' T120 ' . ($y + 16) . ' T40 ' . ($y + 16) . ' Z" fill="#C8475A"/>'
                . '<path d="M50 ' . ($y + 8) . ' Q90 ' . ($y + 2) . ' 130 ' . ($y + 8) . ' T210 ' . ($y + 8) . ' T290 ' . ($y + 8) . ' T350 ' . ($y + 8) . '" stroke="#F4B6B9" stroke-width="4"/>';
            return [$s, 16];
        case 'crispyonion':
            $s = '';
            foreach ([[70, 0], [104, 4], [140, -2], [176, 3], [212, -1], [248, 4], [284, 0], [318, 3]] as [$x, $dy]) {
                $s .= '<path d="M' . $x . ' ' . ($y + 8 + $dy) . 'q12 -10 24 0" stroke="#C98B3C" stroke-width="7"/>';
            }
            return [$s, 12];
        case 'crispypotato':
            $s = '';
            foreach ([[66, -8], [102, 6], [140, -4], [178, 8], [216, -6], [254, 4], [292, -8], [320, 6]] as [$x, $r]) {
                $s .= '<rect x="' . $x . '" y="' . ($y + 2) . '" width="34" height="9" rx="3" transform="rotate(' . $r . ' ' . ($x + 17) . ' ' . ($y + 6) . ')" fill="#F2C14E" stroke-width="3"/>';
            }
            return [$s, 12];
        case 'caramel':
            $s = '';
            foreach ([60, 120, 180, 240, 300] as $x) {
                $s .= '<path d="M' . $x . ' ' . ($y + 6) . ' q10 -8 20 0 t20 0" stroke="#9A5A22" stroke-width="7"/>';
            }
            return [$s, 10];
        case 'sauce':
            $c = $arg ?? '#F6F1E4';
            $s = '<path d="M48 ' . $y . 'H352 Q356 ' . ($y + 6) . ' 344 ' . ($y + 8) . ' Q330 ' . ($y + 10) . ' 326 ' . ($y + 24) . ' Q320 ' . ($y + 10)
                . ' 260 ' . ($y + 9) . ' Q246 ' . ($y + 30) . ' 238 ' . ($y + 9) . ' L150 ' . ($y + 9) . ' Q140 ' . ($y + 22) . ' 132 ' . ($y + 9)
                . ' L56 ' . ($y + 8) . ' Q44 ' . ($y + 6) . ' 48 ' . $y . 'Z" fill="' . $c . '" stroke-width="3.5"/>';
            return [$s, 8];
    }
    return ['', 0];
}

/* ---------------- Wrap (burger/wrap ürünlerinde yan görsel) ---------------- */

function art_wrap_side(string $t): string
{
    $fill = art_has($t, 'peri peri') ? '#E2552D' : '#5CB646';
    return '<g transform="translate(250 118) rotate(18)">'
        . '<rect x="0" y="0" width="120" height="70" rx="35" fill="#EBC98C"/>'
        . '<ellipse cx="22" cy="35" rx="20" ry="33" fill="#F3DDAE"/>'
        . '<ellipse cx="22" cy="35" rx="13" ry="24" fill="#D9912E" stroke-width="3"/>'
        . '<path d="M14 22 q8 6 16 0 M12 40 q10 6 20 0" stroke="' . $fill . '" stroke-width="5"/>'
        . '<path d="M56 6 q8 30 0 58 M88 6 q8 30 0 58" stroke="#D2A866" stroke-width="3"/>'
        . '</g>';
}

/* ---------------- Yan ürünler ---------------- */

function art_fries(string $t): string
{
    $s = '';
    foreach ([[132, -12], [152, -4], [172, -16], [192, 2], [212, -10], [232, 4], [252, -14], [270, -2]] as $i => [$x, $r]) {
        $s .= '<rect x="' . $x . '" y="' . (40 + ($i % 3) * 8) . '" width="20" height="130" rx="5" transform="rotate(' . $r . ' ' . ($x + 10) . ' 150)" fill="#F7C531"/>';
    }
    if (art_has($t, 'cheddar')) {
        $s .= '<path d="M120 112 Q150 92 182 106 Q210 88 240 104 Q268 92 288 112 L282 128 Q262 150 254 126 Q236 138 222 124 Q200 152 190 124 Q168 136 150 122 Q134 140 124 124 Z" fill="#F29E1F"/>';
    }
    if (art_has($t, 'kapiçyo')) {
        $s .= '<path d="M122 110 Q160 92 200 104 Q240 90 284 110 L280 126 Q240 140 200 128 Q160 140 126 126 Z" fill="#F6E4C8"/>';
        foreach ([144, 176, 208, 240, 266] as $x) {
            $s .= '<rect x="' . $x . '" y="112" width="10" height="6" rx="2" fill="#D9402F" stroke="none"/>';
        }
    }
    if (art_has($t, 'susam')) {
        foreach ([[150, 108], [190, 100], [226, 110], [262, 104], [172, 118]] as [$x, $y]) {
            $s .= '<ellipse cx="' . $x . '" cy="' . $y . '" rx="5" ry="3" fill="#FFF3D6" stroke-width="2"/>';
        }
    }
    $s .= '<path d="M110 128 L290 128 L268 270 L132 270 Z" fill="#CF3631"/>'
        . '<path d="M126 160 H274" stroke="#FFFFFF" stroke-width="6"/>'
        . '<path d="M200 186 l10 20 h22 l-18 13 7 21 -21 -13 -21 13 7 -21 -18 -13 h22 z" fill="#F0B445" stroke-width="3"/>';
    return $s;
}

function art_hellim(): string
{
    $s = '<ellipse cx="200" cy="232" rx="170" ry="34" fill="#FFFFFF"/>';
    foreach ([[70, 150, -8], [140, 136, 6], [210, 148, -4], [280, 134, 8]] as [$x, $y, $r]) {
        $s .= '<g transform="rotate(' . $r . ' ' . ($x + 30) . ' ' . ($y + 40) . ')"><rect x="' . $x . '" y="' . $y . '" width="64" height="88" rx="10" fill="#F6E7C1"/>'
            . '<path d="M' . ($x + 10) . ' ' . ($y + 24) . 'l44 -12M' . ($x + 10) . ' ' . ($y + 50) . 'l44 -12M' . ($x + 10) . ' ' . ($y + 76) . 'l44 -12" stroke="#8A5A2B" stroke-width="7"/></g>';
    }
    foreach ([[96, 130], [168, 120], [238, 128], [306, 118]] as [$x, $y]) {
        $s .= '<ellipse cx="' . $x . '" cy="' . $y . '" rx="5" ry="3" fill="#FFF3D6" stroke-width="2"/>';
    }
    return $s;
}

function art_pacanga(): string
{
    $s = '<ellipse cx="200" cy="236" rx="170" ry="32" fill="#FFFFFF"/>';
    foreach ([[50, 150, -6], [150, 132, 4], [250, 148, -3]] as [$x, $y, $r]) {
        $s .= '<g transform="rotate(' . $r . ' ' . ($x + 50) . ' ' . ($y + 30) . ')"><rect x="' . $x . '" y="' . $y . '" width="110" height="56" rx="26" fill="#E0A04A"/>'
            . '<ellipse cx="' . ($x + 104) . '" cy="' . ($y + 28) . '" rx="16" ry="26" fill="#F3D9A4"/>'
            . '<ellipse cx="' . ($x + 104) . '" cy="' . ($y + 28) . '" rx="9" ry="16" fill="#B5473A" stroke-width="3"/>'
            . '<path d="M' . ($x + 24) . ' ' . ($y + 10) . 'q6 18 0 36M' . ($x + 56) . ' ' . ($y + 10) . 'q6 18 0 36" stroke="#B87A2E" stroke-width="3"/></g>';
    }
    return $s;
}

function art_tenders(): string
{
    $s = '<ellipse cx="200" cy="236" rx="170" ry="32" fill="#FFFFFF"/>';
    foreach ([[60, 140, -14], [130, 124, 6], [200, 138, -6], [266, 122, 12]] as [$x, $y, $r]) {
        $s .= '<g transform="rotate(' . $r . ' ' . ($x + 36) . ' ' . ($y + 50) . ')"><path d="M' . $x . ' ' . ($y + 20) . ' Q' . ($x + 6) . ' ' . $y . ' ' . ($x + 30) . ' ' . ($y + 2)
            . ' Q' . ($x + 66) . ' ' . ($y + 4) . ' ' . ($x + 70) . ' ' . ($y + 30) . ' L' . ($x + 74) . ' ' . ($y + 92) . ' Q' . ($x + 60) . ' ' . ($y + 112) . ' ' . ($x + 36) . ' ' . ($y + 108)
            . ' Q' . ($x + 4) . ' ' . ($y + 104) . ' ' . ($x + 4) . ' ' . ($y + 80) . ' Z" fill="#D9912E"/>';
        foreach ([[18, 30], [44, 46], [24, 70], [52, 82]] as [$dx, $dy]) {
            $s .= '<circle cx="' . ($x + $dx) . '" cy="' . ($y + $dy) . '" r="4" fill="#F2C063" stroke="none"/>';
        }
        $s .= '</g>';
    }
    return $s;
}

/* ---------------- Ekstralar ---------------- */

function art_extra(string $name): string
{
    $plate = '<ellipse cx="200" cy="226" rx="160" ry="34" fill="#FFFFFF"/>';
    if (art_has($name, 'köfte')) {
        return $plate . '<rect x="70" y="150" width="260" height="62" rx="31" fill="#5A2E1A"/><path d="M110 176H170M200 188H262M290 176H306" stroke="#3A1C10" stroke-width="6"/>';
    }
    if (art_has($name, 'cheddar')) {
        return $plate . '<path d="M90 110 L310 110 L300 204 L274 210 Q266 236 256 210 L180 212 Q170 244 160 212 L100 206 Z" fill="#F7C531"/>'
            . '<circle cx="150" cy="150" r="9" fill="#E9AE1C" stroke-width="3"/><circle cx="240" cy="168" r="11" fill="#E9AE1C" stroke-width="3"/>';
    }
    if (art_has($name, 'bacon')) {
        $s = $plate;
        foreach ([150, 186] as $y) {
            $s .= '<path d="M60 ' . $y . ' Q100 ' . ($y - 14) . ' 140 ' . $y . ' T220 ' . $y . ' T300 ' . $y . ' T340 ' . ($y - 4) . ' L340 ' . ($y + 22) . ' Q300 ' . ($y + 34)
                . ' 260 ' . ($y + 22) . ' T180 ' . ($y + 22) . ' T100 ' . ($y + 22) . ' T60 ' . ($y + 24) . ' Z" fill="#C8475A"/>'
                . '<path d="M72 ' . ($y + 10) . ' Q110 ' . ($y - 2) . ' 150 ' . ($y + 10) . ' T230 ' . ($y + 10) . ' T320 ' . ($y + 8) . '" stroke="#F4B6B9" stroke-width="5"/>';
        }
        return $s;
    }
    if (art_has($name, 'avokado')) {
        return $plate . '<path d="M200 70 C262 70 300 150 288 196 C276 238 124 238 112 196 C100 150 138 70 200 70 Z" fill="#3E6B2A"/>'
            . '<path d="M200 86 C250 86 280 152 270 190 C260 224 140 224 130 190 C120 152 150 86 200 86 Z" fill="#CFE07A" stroke-width="4"/>'
            . '<circle cx="200" cy="172" r="30" fill="#8A5A2B"/>';
    }
    [$b] = art_burger($name);
    return $b;
}

/* ---------------- İçecekler (logosuz, sadece renk) ---------------- */

function art_drink(string $n): string
{
    $glass = static fn(string $fill, string $extra = '') => '<path d="M128 70 L272 70 L254 262 L146 262 Z" fill="#E9F4F6"/>'
        . '<path d="M134 110 L266 110 L252 256 L148 256 Z" fill="' . $fill . '" stroke-width="4"/>' . $extra
        . '<path d="M150 90 L146 132" stroke="#FFFFFF" stroke-width="8"/>';
    $can = static fn(string $c, string $band) => '<rect x="132" y="56" width="136" height="214" rx="22" fill="' . $c . '"/>'
        . '<path d="M132 92 H268 M132 236 H268" stroke-width="5"/>'
        . '<path d="M132 168 Q166 136 200 160 T268 150 L268 196 Q234 214 200 194 T132 206 Z" fill="' . $band . '" stroke-width="4"/>'
        . '<rect x="150" y="96" width="14" height="128" rx="7" fill="#FFFFFF" stroke="none" opacity=".35"/>';
    $bottle = static fn(string $c, string $cap) => '<path d="M176 40 H224 V84 Q262 104 262 150 V258 Q262 272 248 272 H152 Q138 272 138 258 V150 Q138 104 176 84 Z" fill="' . $c . '"/>'
        . '<rect x="172" y="26" width="56" height="24" rx="6" fill="' . $cap . '"/>'
        . '<rect x="150" y="160" width="100" height="60" rx="8" fill="#FFFFFF"/>'
        . '<path d="M156 110 V240" stroke="#FFFFFF" stroke-width="8" opacity=".5"/>';

    if (art_has($n, 'zero')) {
        return $can('#1E1716', '#CF3631');
    }
    if (art_has($n, 'cola')) {
        return $can('#C8102E', '#FFFFFF');
    }
    if (art_has($n, 'sprite')) {
        return $can('#2E9E4F', '#F2E94E');
    }
    if (art_has($n, 'fanta')) {
        return $can('#F28C28', '#2B5FB4');
    }
    if (art_has($n, 'fuse')) {
        return $bottle('#E39A3B', '#F2C14E') . '<circle cx="182" cy="190" r="14" fill="#F4A261" stroke-width="3"/><circle cx="214" cy="192" r="12" fill="#F7E04B" stroke-width="3"/>';
    }
    if (art_has($n, 'ayran')) {
        return $glass('#FFFFFF', '<path d="M134 110 Q166 96 200 110 T266 110" fill="#FFFFFF" stroke-width="4"/><circle cx="182" cy="104" r="6" fill="#FFFFFF" stroke-width="3"/><circle cx="222" cy="100" r="5" fill="#FFFFFF" stroke-width="3"/>');
    }
    if (art_has($n, 'limonata')) {
        return $glass('#F7E04B', '<circle cx="262" cy="84" r="34" fill="#F7E04B"/><circle cx="262" cy="84" r="24" fill="#FFF6B0" stroke-width="3"/><path d="M262 60 V108 M238 84 H286 M245 67 L279 101 M279 67 L245 101" stroke-width="2.5"/>'
            . '<path d="M174 76 q-24 -34 6 -46 q18 22 -6 46" fill="#5CB646" stroke-width="4"/>');
    }
    if (art_has($n, 'soda')) {
        return $bottle('#B9E3C6', '#CF3631') . '<circle cx="188" cy="126" r="5" fill="#FFFFFF" stroke-width="2"/><circle cx="212" cy="112" r="4" fill="#FFFFFF" stroke-width="2"/><circle cx="198" cy="244" r="5" fill="#FFFFFF" stroke-width="2"/>';
    }
    if ($n === 'su' || art_has($n, 'su ')) {
        return $bottle('#CFE8F7', '#2B5FB4') . '<path d="M162 190 q12 -10 24 0 t24 0 t24 0" stroke="#2B5FB4" stroke-width="4"/>';
    }
    return $glass('#E9A34B');
}
