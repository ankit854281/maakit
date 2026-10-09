<?php
if (session_status()!==PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode','1');
    session_start();
}
require_once __DIR__ . '/version.php';
require_once __DIR__ . '/db.php';
require_once __DIR__.'/../middleware/legacy.php';
require_once __DIR__ . '/lang.php';
require_once __DIR__ . '/icons.php';
require_once __DIR__ . '/customer.php';
require_once __DIR__ . '/coverage.php';



// ---------- mbstring na ho to kaam chalane wale vikalp ----------
if (!function_exists('mb_strlen')) {
    function mb_strlen($s, $enc = null) { return preg_match_all('/./u', (string)$s); }
    function mb_substr($s, $start, $len = null, $enc = null) {
        preg_match_all('/./u', (string)$s, $m);
        $a = array_slice($m[0], $start, $len);
        return implode('', $a);
    }
    function mb_strtolower($s, $enc = null) { return strtolower((string)$s); }
    function mb_strpos($h, $n, $o = 0, $enc = null) { return strpos((string)$h, (string)$n, $o); }
    function mb_strimwidth($s, $start, $width, $trim = '', $enc = null) {
        $t = mb_substr($s, $start, $width);
        return (mb_strlen($s) > $width) ? $t . $trim : $t;
    }
}

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function redirect($u) { header('Location: ' . $u); exit; }
function post($k, $d = '') { return isset($_POST[$k]) ? trim($_POST[$k]) : $d; }
function get($k, $d = '') { return isset($_GET[$k]) ? trim($_GET[$k]) : $d; }

function user() { return $_SESSION['user'] ?? null; }
function need_role($roles) {
    $u = user();
    if (!$u || !in_array($u['role'], (array)$roles, true)) { redirect('/login.php'); }
    return $u;
}
/**
 * Login ke baad kaun kahan jayega.
 * Naya role jodna ho to bas yahan ek line jodiye — login.php aur
 * baaki jagah apne aap sahi jagah bhej dengi.
 */
function panel_home($role) {
    switch ($role) {
        case 'admin':    return '/admin/';
        case 'bpo':      return '/bpo/';
        case 'designer': return '/admin/banners.php';
        default:         return '/delivery/';
    }
}
function csrf() {
    if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
    return $_SESSION['csrf'];
}
/**
 * Dhyan: pehle ye khali token ko bhi sahi maan leta tha.
 *
 * Bina cookie ke aaye request me $_SESSION['csrf'] hota hi nahi, aur
 * hash_equals('', '') PHP me TRUE deta hai. Yani koi bhi `csrf=` khali
 * bhejkar bina session ke form POST kar sakta tha — aur har aisi request
 * ka apna naya session banta tha, isliye galat password ginne wali hadd
 * bhi ek saath sau baar todi ja sakti thi.
 *
 * Ab dono taraf bhara hua hona zaroori hai.
 */
function csrf_ok() {
    $mera = $_SESSION['csrf'] ?? '';
    $diya = $_POST['csrf'] ?? '';
    if (!is_string($diya) || $mera === '' || $diya === '') return false;
    return hash_equals($mera, $diya);
}

function wa_link($mobile, $text) {
    $m = preg_replace('/\D/', '', $mobile);
    if (strlen($m) === 10) { $m = '91' . $m; }
    return 'https://wa.me/' . $m . '?text=' . rawurlencode($text);
}

function new_order_no(PDO $pdo) {
    return 'MK-' . date('dm') . '-' . strtoupper(bin2hex(random_bytes(5)));
}
function new_code() {
    do {
        $c = random_int(1000, 9999);
        $s = (string)$c;
        $ok = count(array_unique(str_split($s))) > 2;
    } while (!$ok);
    return (string)$c;
}

// ---------- Categories (18) ----------
// har category me hindi, english aur buzurgon wale purane shabd, taaki search sab se ho
function categories() {
    return [
        ['slug'=>'pandit','name'=>'पंडित जी / पूजा','icon'=>'pandit','words'=>'pandit purohit guru maharaj pooja puja katha kathavachak jyotish jyotishi panditji brahman havan yagya पंडित पुरोहित गुरु महाराज पूजा कथा ज्योतिषी हवन यज्ञ'],
        ['slug'=>'halwai','name'=>'हलवाई / खानपान','icon'=>'halwai','words'=>'halwai catering rasoiya bhathiyara cook khana bhandara mithai tiffin हलवाई रसोइया भठियारा खाना भंडारा मिठाई कैटरिंग टिफिन'],
        ['slug'=>'tent','name'=>'टेंट, साउंड, लाइट','icon'=>'tent','words'=>'tent sound dj light band baja generator mike shamiyana decoration टेंट साउंड डीजे लाइट बाजा माइक जनरेटर शामियाना सजावट झालर'],
        ['slug'=>'lawn','name'=>'लॉन / मैरिज हॉल','icon'=>'lawn','words'=>'lawn marriage hall guest house dharamshala banquet barat ghar लॉन मैरिज हॉल गेस्ट हाउस धर्मशाला बारात घर'],
        ['slug'=>'photo','name'=>'फोटो / वीडियो','icon'=>'photo','words'=>'photo video camera drone studio photographer फोटो वीडियो कैमरा ड्रोन स्टूडियो फोटोग्राफर'],
        ['slug'=>'bijli','name'=>'बिजली मिस्त्री','icon'=>'bijli','words'=>'electrician bijli wiring inverter motor solar fan light lain बिजली मिस्त्री वायरिंग इन्वर्टर मोटर सोलर पंखा लैन वायर'],
        ['slug'=>'nal','name'=>'नल मिस्त्री','icon'=>'nal','words'=>'plumber nal nalka pani tanki submersible boring ro नल नलका पानी टंकी सबमर्सिबल बोरिंग प्लंबर पानी वाला'],
        ['slug'=>'thekedar','name'=>'राजमिस्त्री / ठेकेदार','icon'=>'thekedar','words'=>'rajmistri mistri thekedar contractor painter badhai carpenter welder shuttering tile marble pop labour राजमिस्त्री मेस्त्री ठेकेदार पुताई पेंटर बढ़ई लोहार वेल्डर टाइल मार्बल मजदूर'],
        ['slug'=>'repair','name'=>'मशीन मरम्मत','icon'=>'repair','words'=>'fridge ac washing machine mobile tv repair cooler ro service मरम्मत फ्रिज एसी वाशिंग मशीन मोबाइल टीवी कूलर सुधारने वाला'],
        ['slug'=>'gaadi','name'=>'गाड़ी / सवारी','icon'=>'gaadi','words'=>'tempo magic tractor jcb trolley taxi auto ambulance bus school van dala driver टेम्पू मैजिक ट्रैक्टर जेसीबी ट्रॉली टैक्सी ऑटो एम्बुलेंस डाला ड्राइवर'],
        ['slug'=>'kheti','name'=>'खेती-किसानी','icon'=>'kheti','words'=>'khaad beej pesticide thresher harvester pump boring nursery dairy pashu doctor bail hal खाद बीज दवा थ्रेशर हार्वेस्टर पंप नर्सरी डेयरी पशु डॉक्टर बैल हल जुताई'],
        ['slug'=>'dukan','name'=>'दुकानें','icon'=>'dukan','words'=>'kirana parchun bania medical dawa hardware kapda stationery bartan general store दुकान परचून बनिया किराना दवा दुकान हार्डवेयर कपड़ा बर्तन स्टेशनरी'],
        ['slug'=>'nai','name'=>'नाई / सैलून','icon'=>'nai','words'=>'nai hajjam salon barber baal katna hair cutting shave नाई नाऊ हज्जाम सैलून बाल कटिंग दाढ़ी'],
        ['slug'=>'parlour','name'=>'ब्यूटी पार्लर / मेहंदी','icon'=>'parlour','words'=>'beauty parlour mehndi makeup bridal facial waxing ब्यूटी पार्लर मेहंदी मेकअप दुल्हन फेशियल'],
        ['slug'=>'silai','name'=>'सिलाई / दर्जी','icon'=>'silai','words'=>'darzi tailor silai boutique kapda sine wala दर्जी सिलाई बुटीक कपड़ा सीने वाला ब्लाउज'],
        ['slug'=>'padhai','name'=>'पढ़ाई / जनसेवा','icon'=>'padhai','words'=>'tuition coaching computer centre csc jan seva kendra xerox typing form cyber cafe ट्यूशन कोचिंग कंप्यूटर जनसेवा केंद्र जेरॉक्स टाइपिंग फॉर्म साइबर'],
        ['slug'=>'ilaj','name'=>'इलाज / दवा','icon'=>'ilaj','words'=>'doctor clinic dawa medical pathology lab dentist vaidya ayurvedic homeopathy ambulance asha anm डॉक्टर बाबू क्लिनिक दवाखाना वैद्य जी पैथोलॉजी दांत आयुर्वेदिक'],
        ['slug'=>'anya','name'=>'दूसरे काम','icon'=>'anya','words'=>'dhobi kabadi raddi gas agency chakki atta chakki puncture garage mechanic courier printing flex banner pest control ro water धोबी कबाड़ी रद्दी गैस एजेंसी आटा चक्की पंचर गैराज मिस्त्री कूरियर प्रिंटिंग फ्लेक्स बैनर'],
    ];
}
function cat_by_slug($slug) {
    foreach (categories() as $c) { if ($c['slug'] === $slug) return $c; }
    return null;
}

// ---------- Category icons (inline SVG) ----------
function cat_icon($icon, $size = 34) {
    $s = 'width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"';
    $p = [
        'pandit'   => '<path d="M12 3l1.6 3.4L17 8l-2.6 2.4L15 14l-3-1.7L9 14l.6-3.6L7 8l3.4-.6L12 3z"/><path d="M5 20h14"/><path d="M8 20v-3h8v3"/>',
        'halwai'   => '<path d="M4 13h16a8 8 0 0 1-16 0z"/><path d="M3 20h18"/><path d="M9 4c0 1.5 1 1.5 1 3M13 4c0 1.5 1 1.5 1 3"/>',
        'tent'     => '<path d="M12 4L3 20h18L12 4z"/><path d="M12 10v10"/><path d="M8 20l4-6 4 6"/>',
        'lawn'     => '<path d="M3 20h18"/><path d="M5 20V9l7-5 7 5v11"/><path d="M10 20v-5h4v5"/>',
        'photo'    => '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7l1.5-3h5L16 7"/><circle cx="12" cy="13.5" r="3.5"/>',
        'bijli'    => '<path d="M13 3L5 13h6l-1 8 8-10h-6l1-8z"/>',
        'nal'      => '<path d="M4 8h7v4H4z"/><path d="M11 10h5a3 3 0 0 1 3 3v1"/><path d="M19 14v3"/><path d="M19 21a2 2 0 0 1-2-2c0-1 2-3 2-3s2 2 2 3a2 2 0 0 1-2 2z"/>',
        'thekedar' => '<path d="M3 20h18"/><path d="M6 20v-8h12v8"/><path d="M6 12l6-8 6 8"/><path d="M10 20v-4h4v4"/>',
        'repair'   => '<path d="M14.7 6.3a4 4 0 0 1 5.3 5.3L9.6 22 2 14.4 12.4 4a4 4 0 0 1 2.3 2.3z"/><path d="M6 12l6 6"/>',
        'gaadi'    => '<path d="M3 16V9h11l4 4h3v3"/><circle cx="7.5" cy="17.5" r="2"/><circle cx="17" cy="17.5" r="2"/><path d="M3 9h11"/>',
        'kheti'    => '<path d="M4 20c0-6 4-10 10-12-1 7-5 11-10 12z"/><path d="M4 20l7-7"/><path d="M14 4l2 2M18 8l2-1"/>',
        'dukan'    => '<path d="M3 9l1.5-5h15L21 9"/><path d="M3 9a2.2 2.2 0 0 0 4.5 0 2.2 2.2 0 0 0 4.5 0 2.2 2.2 0 0 0 4.5 0 2.2 2.2 0 0 0 4.5 0"/><path d="M4.5 11v9h15v-9"/><path d="M10 20v-4.5h4V20"/>',
        'nai'      => '<circle cx="6" cy="6" r="2.5"/><circle cx="6" cy="18" r="2.5"/><path d="M8 7.5L20 18M8 16.5L20 6"/>',
        'parlour'  => '<path d="M12 3a5 5 0 0 1 5 5c0 4-5 5-5 13C12 13 7 12 7 8a5 5 0 0 1 5-5z"/><circle cx="12" cy="8" r="1.6"/>',
        'silai'    => '<path d="M4 20l9-9"/><path d="M13 11l7-7"/><circle cx="6" cy="18" r="2"/><path d="M16 4l4 4"/><path d="M9 15l3 5"/>',
        'padhai'   => '<path d="M3 7l9-4 9 4-9 4-9-4z"/><path d="M7 10v5c0 1.5 2.5 3 5 3s5-1.5 5-3v-5"/><path d="M21 7v7"/>',
        'ilaj'     => '<rect x="4" y="7" width="16" height="13" rx="2"/><path d="M9 7V4h6v3"/><path d="M12 11v6M9 14h6"/>',
        'anya'     => '<circle cx="12" cy="12" r="8"/><path d="M12 8v4l3 2"/>',
    ];
    return '<svg ' . $s . '>' . ($p[$icon] ?? $p['anya']) . '</svg>';
}

// ---------- Delivery charge ----------
function weight_extras() {
    return ['0'=>'5 किलो तक (एक थैला)','10'=>'5 से 15 किलो','20'=>'15 से 30 किलो (बोरी, सिलेंडर)','40'=>'30 से 50 किलो','van'=>'50 किलो से ज़्यादा'];
}
function size_extras() {
    return ['0'=>'सामान्य (थैले/डिब्बे में)','15'=>'नाज़ुक (काँच, अंडे, टीवी)','20'=>'लंबा या बड़ा (पाइप, चटाई, पंखा)','van'=>'बहुत बड़ा (अलमारी, कूलर, साइकिल)'];
}
function markets() { return ['local'=>t('Local shop / area rate','स्थानीय दुकान / इलाके का रेट'),'kapsethi'=>'कपसेठी','chauri'=>'चौरी','kachhawa'=>'कछवा चौराहा']; }

function village_list(PDO $pdo) {
    return array_values(array_filter(coverage_areas($pdo), fn($a) => coverage_enabled($a)));
}
function calc_charge($village, $market, $weight, $size, PDO $pdo, $first = false) {
    $st = $pdo->prepare("SELECT * FROM villages WHERE name=?");
    $st->execute([$village]);
    $v = $st->fetch();
    if (!$v) return ['van'=>false,'total'=>null,'base'=>null,'extra'=>0,'msg'=>t('We will tell you this village’s charge on call', 'इस गाँव का चार्ज कॉल पर बताया जाएगा')];
    if ($weight === 'van' || $size === 'van') {
        return ['van'=>true,'total'=>null,'base'=>null,'extra'=>0,'msg'=>t('This needs a van — from ₹150, confirmed on call', 'यह सामान वैन से जाएगा — चार्ज ₹150 से शुरू, कॉल पर पक्का होगा')];
    }
    $area=coverage_area($pdo,$village);
    $base = $area && $area['base_fee'] !== null ? (int)$area['base_fee'] : (int)($v['rate_' . $market] ?? 0);
    if ($area && $area['first_free'] !== null) $first=$first && (bool)$area['first_free'];
    if ($base <= 0 && (!$area || $area['base_fee'] === null)) {
        return ['van'=>false,'total'=>null,'base'=>0,'extra'=>0,'msg'=>t('We will tell you this village’s charge on call', 'इस गाँव का चार्ज कॉल पर बताया जाएगा')];
    }
    $extra = (int)$weight + (int)$size;
    $total = ($first ? 0 : $base) + $extra;
    return ['van'=>false,'total'=>$total,'base'=>$base,'extra'=>$extra,'msg'=>''];
}

function status_list() {
    return ['Naya'=>t('New','नया'), 'Confirm'=>t('Confirmed','कन्फर्म'), 'Assign'=>t('Assigned','असाइन'),
            'Pickup'=>t('Picked up','दुकान से लिया'), 'Delivered'=>t('Delivered','पहुँचा दिया'),
            'Paisa jama'=>t('Cash settled','पैसा जमा'), 'Cancel'=>t('Cancelled','कैंसिल')];
}
function status_hi($s) { $l = status_list(); return $l[$s] ?? $s; }

function flash($msg = null) {
    if ($msg !== null) { $_SESSION['flash'] = $msg; return; }
    $m = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $m;
}

