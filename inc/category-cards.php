<?php
// ============================================================
// Home page ke bade category card (bada naam + saaman ki tasveer).
//
// Tasveer kahan se aati hai:
//   1) us category ke saaman ki asli photo — jo Admin > स्लाइड फ़ोटो
//      se lagi hai, ya dukandar ne apne saaman par lagayi hai
//   2) jab tak photo nahi hai, hamari apni banai saadi tasveer (SVG)
// Kisi brand ka logo ya kisi doosri website ki photo yahan nahi hai.
// ============================================================

function maakit_category_cards() {
    return [
        ['key' => 'veg', 'en' => 'Fruits & Vegetables', 'hi' => 'फल और सब्ज़ी', 'type' => 'Fruit & Vegetable Shop', 'subs' => [], 'wide' => true],
        ['key' => 'atta', 'en' => 'Atta, Rice & Dal', 'hi' => 'आटा, चावल और दाल', 'type' => 'Grocery / Kirana Store', 'subs' => ['Atta & Flour', 'Rice', 'Pulses']],
        ['key' => 'oil', 'en' => 'Oil, Ghee & Masala', 'hi' => 'तेल, घी और मसाले', 'type' => 'Grocery / Kirana Store', 'subs' => ['Edible Oil', 'Spices']],
        ['key' => 'dairy', 'en' => 'Dairy, Bread & Eggs', 'hi' => 'दूध, ब्रेड और अंडे', 'type' => 'Dairy Shop', 'subs' => [], 'also' => ['Bakery']],
        ['key' => 'snacks', 'en' => 'Snacks & Drinks', 'hi' => 'नमकीन और पेय', 'type' => 'Grocery / Kirana Store', 'subs' => ['Snacks & Packaged Food', 'Beverages']],
        ['key' => 'sweets', 'en' => 'Sweets', 'hi' => 'मिठाई', 'type' => 'Sweet Shop', 'subs' => []],
        ['key' => 'puja', 'en' => 'Puja Samagri', 'hi' => 'पूजा सामग्री', 'type' => 'Pooja Samagri Shop', 'subs' => []],
        ['key' => 'medicine', 'en' => 'Medicines', 'hi' => 'दवाइयाँ', 'type' => 'Medical / Pharmacy', 'subs' => []],
        ['key' => 'clean', 'en' => 'Cleaning & Home', 'hi' => 'सफ़ाई और घर', 'type' => 'Cleaning Products Store', 'subs' => []],
    ];
}

/** har card ke liye 3 tak asli photo (pehle slide photo, phir dukan ki photo) */
function maakit_category_card_photos(PDO $pdo, array $card, $limit = 3) {
    $types = array_merge([$card['type']], $card['also'] ?? []);
    $tm = implode(',', array_fill(0, count($types), '?'));
    $sub = $card['subs'] ? ' AND c.sub_cat IN (' . implode(',', array_fill(0, count($card['subs']), '?')) . ')' : '';
    $args = array_merge($types, $card['subs']);
    $out = [];
    try {
        $st = $pdo->prepare("SELECT p.file FROM catalog_photos p JOIN catalog_items c ON c.id=p.cat_id
            WHERE c.shop_type IN ($tm) $sub AND p.sort_no=(SELECT MIN(p2.sort_no) FROM catalog_photos p2 WHERE p2.cat_id=p.cat_id)
            ORDER BY c.sort_no, c.id LIMIT " . (int)$limit);
        $st->execute($args);
        foreach ($st->fetchAll() as $r) $out[] = $r['file'];
    } catch (Throwable $e) {}
    if (count($out) < $limit) {
        try {
            $st = $pdo->prepare("SELECT s.photo FROM shop_items s JOIN catalog_items c ON c.id=s.cat_id
                WHERE c.shop_type IN ($tm) $sub AND s.active=1 AND s.photo IS NOT NULL AND s.photo<>''
                ORDER BY s.id DESC LIMIT " . (int)$limit);
            $st->execute($args);
            foreach ($st->fetchAll() as $r) if (!in_array($r['photo'], $out, true)) $out[] = $r['photo'];
        } catch (Throwable $e) {}
    }
    $ok = function_exists('catalog_photo_ok') ? 'catalog_photo_ok' : fn($f) => is_string($f) && preg_match('/^[\w.-]+\.(jpg|png|webp)$/', $f);
    return array_slice(array_values(array_filter($out, $ok)), 0, $limit);
}

/** photo na ho tab ki saadi tasveer — bina brand ke */
function maakit_category_art($key) {
    $A = [
        'veg' => '<ellipse cx="70" cy="92" rx="56" ry="6" fill="#000" opacity=".06"/>'
            . '<path d="M18 52c10 26 40 34 62 22-24 2-44-6-56-26z" fill="#F6C443"/><path d="M24 46c12 24 40 30 60 18-22 0-42-6-54-22z" fill="#FAD45E"/>'
            . '<path d="M92 30c18-12 32 2 26 18-10-2-18-8-26-18z" fill="#4CAF50"/><path d="M84 36c10-18 30-14 30 2-12 4-20 2-30-2z" fill="#66BB6A"/>'
            . '<circle cx="86" cy="64" r="22" fill="#E53935"/><path d="M80 44c4 2 8 2 12 0-2 4-10 4-12 0z" fill="#2E7D32"/><circle cx="79" cy="57" r="5" fill="#fff" opacity=".25"/>'
            . '<circle cx="52" cy="80" r="12" fill="#7CB342"/><circle cx="52" cy="80" r="9" fill="#C5E1A5"/><circle cx="72" cy="84" r="11" fill="#7CB342"/><circle cx="72" cy="84" r="8" fill="#DCEDC8"/>',
        'atta' => '<ellipse cx="60" cy="94" rx="48" ry="5" fill="#000" opacity=".06"/>'
            . '<path d="M14 30h48l4 62H10z" fill="#E8C27A"/><path d="M14 30h48l2 10H12z" fill="#D4A55A"/><rect x="20" y="50" width="36" height="22" rx="4" fill="#fff" opacity=".85"/><circle cx="38" cy="61" r="7" fill="#E0A04A"/>'
            . '<path d="M58 44h44l4 50H54z" fill="#F5F1E6"/><path d="M58 44h44l2 8H56z" fill="#C9B88E"/><rect x="64" y="60" width="32" height="18" rx="3" fill="#E9A23B"/><ellipse cx="80" cy="86" rx="14" ry="4" fill="#fff"/>',
        'oil' => '<ellipse cx="60" cy="94" rx="48" ry="5" fill="#000" opacity=".06"/>'
            . '<path d="M22 26h26l6 10v56H16V36z" fill="#F9C80E"/><rect x="26" y="16" width="16" height="10" rx="2" fill="#E53935"/><path d="M48 40c10 0 12 18 2 20" fill="none" stroke="#F9C80E" stroke-width="6"/><rect x="20" y="54" width="30" height="20" rx="3" fill="#fff" opacity=".8"/>'
            . '<path d="M66 40h36v54H66z" fill="#C62828"/><path d="M66 40h36v10H66z" fill="#8E1B1B"/><circle cx="84" cy="70" r="11" fill="#FF7043"/><circle cx="84" cy="70" r="6" fill="#FFAB91"/>',
        'dairy' => '<ellipse cx="60" cy="94" rx="50" ry="5" fill="#000" opacity=".06"/>'
            . '<path d="M14 34l12-10h18l-4 10v58H14z" fill="#E3F2FD"/><path d="M14 34h26v58H14z" fill="#BBDEFB"/><path d="M16 56h22v14H16z" fill="#1E88E5"/>'
            . '<path d="M48 50c0-14 46-14 46 0v42H48z" fill="#D7A86E"/><path d="M52 50c0-10 38-10 38 0" fill="none" stroke="#B5814A" stroke-width="3"/>'
            . '<ellipse cx="100" cy="84" rx="9" ry="11" fill="#FFF8E1" stroke="#E0C9A6" stroke-width="2"/>',
        'snacks' => '<ellipse cx="60" cy="94" rx="50" ry="5" fill="#000" opacity=".06"/>'
            . '<path d="M14 24l40 0-4 70H18z" fill="#FF7043"/><path d="M14 24h40l-2 8H16z" fill="#D84315"/><circle cx="34" cy="58" r="12" fill="#FFCC80"/>'
            . '<rect x="62" y="30" width="22" height="64" rx="8" fill="#43A047"/><rect x="66" y="22" width="14" height="10" rx="3" fill="#2E7D32"/><rect x="62" y="50" width="22" height="16" fill="#fff" opacity=".8"/>'
            . '<rect x="88" y="54" width="22" height="40" rx="6" fill="#FFB300"/>',
        'sweets' => '<ellipse cx="60" cy="94" rx="50" ry="5" fill="#000" opacity=".06"/>'
            . '<path d="M12 62h96l-8 30H20z" fill="#E91E63"/><path d="M12 62h96" stroke="#AD1457" stroke-width="3"/>'
            . '<circle cx="36" cy="54" r="14" fill="#FFB300"/><circle cx="32" cy="50" r="3" fill="#FFE082"/><circle cx="62" cy="52" r="14" fill="#FFA000"/><circle cx="58" cy="48" r="3" fill="#FFE082"/>'
            . '<path d="M78 44l24 10-24 10z" fill="#ECEFF1"/><path d="M78 44l24 10" stroke="#B0BEC5" stroke-width="2"/>',
        'puja' => '<ellipse cx="60" cy="94" rx="50" ry="5" fill="#000" opacity=".06"/>'
            . '<ellipse cx="60" cy="80" rx="44" ry="12" fill="#D4A017"/><ellipse cx="60" cy="76" rx="44" ry="12" fill="#F2C94C"/>'
            . '<path d="M42 72q8 10 16 0z" fill="#B7791F"/><path d="M50 70c-4-8 0-14 0-20 0 6 4 12 0 20z" fill="#FF7043"/>'
            . '<circle cx="76" cy="70" r="6" fill="#FB8C00"/><circle cx="86" cy="72" r="6" fill="#FB8C00"/><circle cx="81" cy="64" r="6" fill="#FFA726"/>'
            . '<path d="M30 74v-30" stroke="#8D6E63" stroke-width="2"/><path d="M30 44c-3-6 3-6 0-12" fill="none" stroke="#BDBDBD" stroke-width="2"/>',
        'medicine' => '<ellipse cx="60" cy="94" rx="50" ry="5" fill="#000" opacity=".06"/>'
            . '<rect x="18" y="34" width="34" height="58" rx="6" fill="#ECEFF1"/><rect x="22" y="24" width="26" height="12" rx="3" fill="#1E88E5"/><rect x="22" y="52" width="26" height="22" fill="#fff"/><path d="M35 57v12M29 63h12" stroke="#E53935" stroke-width="3"/>'
            . '<rect x="58" y="50" width="52" height="30" rx="5" fill="#B3E5FC" transform="rotate(-8 84 65)"/><g fill="#fff"><circle cx="70" cy="64" r="5"/><circle cx="84" cy="62" r="5"/><circle cx="98" cy="60" r="5"/></g>',
        'clean' => '<ellipse cx="60" cy="94" rx="50" ry="5" fill="#000" opacity=".06"/>'
            . '<path d="M22 36h24l4 56H18z" fill="#29B6F6"/><rect x="28" y="22" width="12" height="14" rx="2" fill="#0277BD"/><path d="M40 26h10" stroke="#0277BD" stroke-width="4"/><rect x="22" y="56" width="24" height="16" fill="#fff" opacity=".8"/>'
            . '<rect x="60" y="54" width="44" height="38" rx="6" fill="#AB47BC"/><rect x="66" y="62" width="32" height="12" rx="3" fill="#fff" opacity=".8"/><circle cx="96" cy="46" r="5" fill="#E1F5FE"/><circle cx="104" cy="38" r="3" fill="#E1F5FE"/>',
    ];
    return '<svg class="mk-cc-art" viewBox="0 0 120 100" aria-hidden="true" focusable="false">' . ($A[$key] ?? '') . '</svg>';
}
