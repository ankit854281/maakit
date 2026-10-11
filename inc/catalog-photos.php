<?php
// ============================================================
// Saaman ki kai photo (slide) — padhna, aur slider banana.
// Table: catalog_photos (sql/031). Table na ho to sab chupchap
// khali lautata hai, page nahi tootta.
// ============================================================

const CATALOG_PHOTO_MAX = 7;

/** [cat_id => [file, file, ...]] — sort_no ke hisaab se */
function catalog_photos_for(PDO $pdo, array $ids) {
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    if (!$ids) return [];
    try {
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $st = $pdo->prepare("SELECT cat_id, file FROM catalog_photos WHERE cat_id IN ($marks) ORDER BY cat_id, sort_no, id");
        $st->execute($ids);
        $out = [];
        foreach ($st->fetchAll() as $r) $out[(int)$r['cat_id']][] = $r['file'];
        return $out;
    } catch (Throwable $e) {
        return [];
    }
}

/** photo ka naam surakshit hai? (sirf hamari banai file) */
function catalog_photo_ok($f) {
    return is_string($f) && preg_match('/^[a-z]{2,6}-\d{6}-[a-f0-9]{8,12}\.(jpg|png|webp)$/', $f);
}

/**
 * Slider ka HTML. $files = photo ke naam, $alt = saaman ka naam.
 * Ungli se khiskao, ya ‹ › dabao; neeche gol nishaan.
 * JS: /assets/photo-slider.js
 */
function catalog_slider_html(array $files, $alt) {
    $files = array_values(array_filter($files, 'catalog_photo_ok'));
    if (!$files) return '';
    $n = count($files);
    $id = 'ps' . substr(md5(implode('|', $files) . $alt), 0, 8);
    $html = '<div class="photo-slider" id="' . $id . '" data-slider>';
    $html .= '<div class="ps-track" tabindex="0" aria-label="' . h(t('Product photos', 'सामान की फ़ोटो')) . '">';
    foreach ($files as $i => $f) {
        $html .= '<figure class="ps-slide"><img src="/uploads/' . h($f) . '" alt="' . h($alt . ' — ' . ($i + 1) . '/' . $n) . '"'
              . ($i ? ' loading="lazy"' : '') . '></figure>';
    }
    $html .= '</div>';
    if ($n > 1) {
        $html .= '<span class="ps-count" aria-hidden="true"><b>1</b> / ' . $n . '</span>';
        $html .= '<button type="button" class="ps-prev" aria-label="' . h(t('Previous photo', 'पिछली फ़ोटो')) . '">‹</button>';
        $html .= '<button type="button" class="ps-next" aria-label="' . h(t('Next photo', 'अगली फ़ोटो')) . '">›</button>';
        $html .= '<div class="ps-dots" aria-hidden="true">';
        for ($i = 0; $i < $n; $i++) $html .= '<i' . ($i ? '' : ' class="on"') . '></i>';
        $html .= '</div>';
    }
    return $html . '</div>';
}

/**
 * Jis saaman ki koi photo nahi — uske liye saaf, apne aap bani "tile":
 * halka rang (sub-category se tay, har baar wahi), saaman ka icon aur naam.
 * Ye photo nahi hai, bas khaali dabba na dikhe isliye. Asli photo lagte hi hat jaati hai.
 */
function catalog_tile_html(array $item, $href = '', $size = 'md') {
    $hues = [[146, 38], [36, 90], [200, 70], [12, 75], [265, 45], [170, 45], [48, 85], [330, 45]];
    $k = abs(crc32((string)($item['sub_cat'] ?? '') . '|' . (string)($item['shop_type'] ?? ''))) % count($hues);
    [$hue, $sat] = $hues[$k];
    $name = t($item['name_en'] ?? '', ($item['name_hi'] ?? '') ?: ($item['name_en'] ?? ''));
    $icon = function_exists('catalog_product_icon') ? catalog_product_icon($item + ['name_hi' => '', 'name_en' => '', 'shop_type' => ''], $size === 'sm' ? 22 : 40) : '';
    // naam card me neeche likha hi hota hai, isliye tile me sirf icon
    $in = '<span class="ct-ic">' . $icon . '</span>';
    $style = 'style="--ct-h:' . (int)$hue . ';--ct-s:' . (int)$sat . '%"';
    return $href !== ''
        ? '<a class="ct-tile ct-' . h($size) . '" ' . $style . ' href="' . h($href) . '" aria-label="' . h($name) . '">' . $in . '</a>'
        : '<span class="ct-tile ct-' . h($size) . '" ' . $style . ' aria-hidden="true">' . $in . '</span>';
}
