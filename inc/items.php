<?php
// ============================================================
// Maakit — saaman ki list, emoji, cart ka hisaab
// ============================================================
require_once __DIR__ . '/groups.php';
require_once __DIR__ . '/icons.php';

/** Sabhi chaalu saaman, group ke hisaab se */
function items_all(PDO $pdo) {
    static $c = null;
    if ($c !== null) return $c;
    try {
        $c = $pdo->query("SELECT id,name,unit,grp,words,popular,photo FROM items WHERE active=1 ORDER BY sort_no, id")->fetchAll();
    } catch (Throwable $e) { $c = []; }
    return $c;
}

/** ek saaman, id se */
function item_one(PDO $pdo, $id) {
    foreach (items_all($pdo) as $i) { if ((int)$i['id'] === (int)$id) return $i; }
    return null;
}

/** saaman ke aage dikhne wala chhota chitr (emoji) */
function item_emoji($name, $grp = '') {
    $map = [
        'आटा'=>'🌾','मैदा'=>'🌾','सूजी'=>'🌾','बेसन'=>'🌾','मक्के'=>'🌽','सत्तू'=>'🌾',
        'चावल'=>'🍚','दाल'=>'🫘','चना'=>'🫘','राजमा'=>'🫘','पोहा'=>'🍚','दलिया'=>'🥣',
        'तेल'=>'🫗','घी'=>'🧈','वनस्पति'=>'🧈','चीनी'=>'🍬','गुड़'=>'🟤','नमक'=>'🧂',
        'चाय'=>'🍵','कॉफ़ी'=>'☕',
        'हल्दी'=>'🟡','मिर्च'=>'🌶️','धनिया'=>'🌿','मसाला'=>'🥄','जीरा'=>'🥄','राई'=>'🥄',
        'अजवाइन'=>'🥄','हींग'=>'🥄','पत्ता'=>'🍃','मेथी'=>'🥄','सौंफ'=>'🥄',
        'दूध'=>'🥛','दही'=>'🥣','पनीर'=>'🧆','मक्खन'=>'🧈','अंडे'=>'🥚','खोया'=>'🍥',
        'ब्रेड'=>'🍞','बिस्कुट'=>'🍪','रस्क'=>'🍞','नमकीन'=>'🥨','मैगी'=>'🍜',
        'कॉर्नफ्लेक्स'=>'🥣','चिप्स'=>'🍟','मूँगफली'=>'🥜',
        'आलू'=>'🥔','प्याज़'=>'🧅','टमाटर'=>'🍅','लहसुन'=>'🧄','अदरक'=>'🫚',
        'भिंडी'=>'🥬','लौकी'=>'🥒','बैंगन'=>'🍆','गोभी'=>'🥦','पालक'=>'🥬','नींबू'=>'🍋',
        'कद्दू'=>'🎃','परवल'=>'🥒','मटर'=>'🫛','गाजर'=>'🥕','शिमला'=>'🫑',
        'केला'=>'🍌','सेब'=>'🍎','संतरा'=>'🍊','पपीता'=>'🥭','अंगूर'=>'🍇','अनार'=>'🍎',
        'अमरूद'=>'🍐','तरबूज़'=>'🍉','नारियल तेल'=>'🧴','नारियल'=>'🥥',
        'पाउडर'=>'🧼','टिकिया'=>'🧼','लिक्विड'=>'🧴','फ़िनाइल'=>'🧴','क्लीनर'=>'🧴',
        'झाड़ू'=>'🧹','माचिस'=>'🔥','अगरबत्ती'=>'🪔','कॉइल'=>'🦟','कचरा'=>'🗑️',
        'साबुन'=>'🧼','शैम्पू'=>'🧴','टूथपेस्ट'=>'🪥','टूथब्रश'=>'🪥','पैड'=>'🩹',
        'डायपर'=>'🍼','शेविंग'=>'🪒','रेज़र'=>'🪒','कंघी'=>'💈','क्रीम'=>'🧴',
        'समोसा'=>'🥟','कचौड़ी'=>'🥟','पूरी'=>'🫓','भटूरे'=>'🫓','पकौड़ी'=>'🧆',
        'चाट'=>'🥗','गोलगप्पे'=>'🥣','पराठा'=>'🫓','थाली'=>'🍽️','रोटी'=>'🫓',
        'बिरयानी'=>'🍛','राइस'=>'🍚','चाउमीन'=>'🍜','मोमोज़'=>'🥟','बर्गर'=>'🍔',
        'पिज़्ज़ा'=>'🍕','चिकन'=>'🍗','मटन'=>'🍖','करी'=>'🍛','वेज'=>'🍛',
        'जलेबी'=>'🍥','रसगुल्ला'=>'🍡','जामुन'=>'🍡','लड्डू'=>'🟠','बर्फ़ी'=>'🍬',
        'पेड़ा'=>'🍬','इमरती'=>'🍥','रसमलाई'=>'🍮','केक'=>'🍰','पेस्ट्री'=>'🧁',
        'पैटीज़'=>'🥐','रोल'=>'🌯',
        'कोल्ड'=>'🥤','पानी'=>'💧','लस्सी'=>'🥛','मट्ठा'=>'🥛','जूस'=>'🧃','शेक'=>'🥤',
        'सिलेंडर'=>'🛢️','मोमबत्ती'=>'🕯️','बैटरी'=>'🔋','बल्ब'=>'💡','कॉपी'=>'📓',
        'पेन'=>'🖊️','रीचार्ज'=>'📱','रस्सी'=>'🪢','तार'=>'🔌','पूजा'=>'🪔','फूल'=>'🌸',
        'चारा'=>'🌾','खाद'=>'🌱',
    ];
    foreach ($map as $k => $e) { if (mb_strpos($name, $k) !== false) return $e; }
    $g = ['anaj'=>'🌾','tel'=>'🫗','masala'=>'🥄','dairy'=>'🥛','nashta'=>'🍪','sabzi'=>'🥬',
          'fal'=>'🍎','safai'=>'🧼','sabun'=>'🧴','khana'=>'🍛','mithai'=>'🍰','peene'=>'🥤','anya'=>'🛍️'];
    return $g[$grp] ?? '🛍️';
}

/** unit se andaaz ka wazan (kilo) — cart se wazan khud chun jaaye */
function unit_kg($unit) {
    $u = trim($unit);
    if (preg_match('/([\d.]+)\s*(किलो|लीटर)/u', $u, $m)) return (float)$m[1];
    if (preg_match('/([\d.]+)\s*(ग्राम)/u', $u, $m)) return (float)$m[1] / 1000;
    if (preg_match('/([\d.]+)\s*मि\.?ली/u', $u, $m)) return (float)$m[1] / 1000;
    if (mb_strpos($u, 'सिलेंडर') !== false) return 16;
    if (mb_strpos($u, 'बोरी') !== false) return 40;
    if (mb_strpos($u, 'दर्जन') !== false) return 1.2;
    if (mb_strpos($u, 'पैकेट') !== false) return 0.4;
    if (mb_strpos($u, 'थाली') !== false || mb_strpos($u, 'प्लेट') !== false) return 0.6;
    if (mb_strpos($u, 'गिलास') !== false || mb_strpos($u, 'कप') !== false) return 0.3;
    if (mb_strpos($u, 'गुच्छा') !== false) return 0.15;
    return 0.4;
}

/**
 * Cart ka JSON padhkar साफ़ list banao.
 * Aaya hua data bharosa layak nahi — har id DB se milaayi jaati hai.
 * Lautata hai: ['lines'=>[['name','unit','q','emoji']], 'kg'=>float, 'count'=>int, 'text'=>string]
 */
function cart_parse(PDO $pdo, $json) {
    $out = ['lines' => [], 'kg' => 0.0, 'count' => 0, 'text' => ''];
    $d = json_decode((string)$json, true);
    if (!is_array($d)) return $out;
    $by = [];
    foreach (items_all($pdo) as $i) { $by[(int)$i['id']] = $i; }
    $n = 0;
    foreach ($d as $k => $row) {
        if ($n++ >= 60) break;                       // hadd
        $id = (int)(is_array($row) ? ($row['id'] ?? $k) : $k);
        $q  = (int)(is_array($row) ? ($row['q'] ?? 0) : $row);
        if ($q < 1 || $q > 99 || !isset($by[$id])) continue;
        $it = $by[$id];
        $out['lines'][] = [
            'id' => $id, 'name' => $it['name'], 'unit' => $it['unit'], 'q' => $q,
            'ic' => prod_icon_key($it['name'], $it['grp']),
        ];
        $out['kg']    += unit_kg($it['unit']) * $q;
        $out['count'] += $q;
    }
    $t = [];
    foreach ($out['lines'] as $l) {
        $t[] = $l['name'] . ' — ' . ($l['q'] > 1 ? $l['q'] . ' × ' : '') . $l['unit'];
    }
    $out['text'] = implode("\n", $t);
    return $out;
}

/** kg se wazan ka band (weight_extras ki key) */
function kg_band($kg) {
    if ($kg > 50) return 'van';
    if ($kg > 30) return '40';
    if ($kg > 15) return '20';
    if ($kg > 5)  return '10';
    return '0';
}

/** order ka safar — customer ko dikhane wale kadam */
function order_steps($status, $o = null) {
    $flow = [
        ['Naya',      t('Order received','ऑर्डर मिल गया'),          t('Our team is looking at it','हमारी टीम देख रही है')],
        ['Confirm',   t('Confirmed','पक्का हो गया'),                t('Price and items agreed','दाम और सामान कन्फ़र्म')],
        ['Assign',    t('Delivery partner assigned','डिलीवरी पार्टनर लगा'), t('Heading to the shop','दुकान की ओर निकल रहे हैं')],
        ['Pickup',    t('Picked up from the shop','दुकान से सामान लिया'),  t('On the way to your home','आपके घर की ओर')],
        ['Delivered', t('Delivered','पहुँचा दिया'),                 t('Goods are with you','सामान आपके पास')],
    ];
    $order = ['Naya'=>0,'Confirm'=>1,'Assign'=>2,'Pickup'=>3,'Delivered'=>4,'Paisa jama'=>4];
    $cols  = ['created_at','confirmed_at','assigned_at','picked_at','delivered_at'];
    $at = $order[$status] ?? 0;
    $out = [];
    foreach ($flow as $i => list($k, $t, $s)) {
        $when = ($o && !empty($o[$cols[$i]])) ? ago($o[$cols[$i]]) : '';
        $out[] = ['title'=>$t, 'sub'=>$s, 'when'=>$when,
                  'state'=>($i < $at ? 'done' : ($i === $at ? 'now' : 'pend'))];
    }
    return $out;
}
function status_pill($s) {
    if ($s === 'Cancel') return ['pill-bad', t('Cancelled', 'कैंसिल')];
    if ($s === 'Delivered' || $s === 'Paisa jama') return ['pill-done', t('Delivered', 'पहुँच गया')];
    if ($s === 'Naya') return ['pill-new', t('New', 'नया')];
    return ['pill-run', status_hi($s)];
}

/**
 * Saaman ke aage kya dikhe:
 * photo lagi ho to photo, warna uska icon.
 * Isse aap dheere-dheere photo jodte rahiye, website kahin nahi rukegi.
 */
function item_thumb($it, $size = 30) {
    if (!empty($it['photo'])) {
        return '<img src="/uploads/' . h($it['photo']) . '" alt="' . h($it['name']) . '" loading="lazy" class="pimg">';
    }
    return prod_icon($it['name'], $it['grp'] ?? '', $size);
}

/** sewa (service) ki tile par photo — uploads/svc-<naam>.jpg ho to wahi */
function svc_photo($key) {
    foreach (['jpg', 'png', 'webp'] as $e) {
        $f = 'svc-' . $key . '.' . $e;
        if (is_file(__DIR__ . '/../uploads/' . $f)) return $f;
    }
    return null;
}

/** id se photo (purane order me photo baad me lagi ho to bhi dikhe) */
function item_photo_of(PDO $pdo, $id) {
    if (!$id) return null;
    foreach (items_all($pdo) as $i) { if ((int)$i['id'] === (int)$id) return $i['photo'] ?: null; }
    return null;
}
