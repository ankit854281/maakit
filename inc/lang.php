<?php
// ============================================================
// Maakit — do bhasha (English + हिंदी)
//
// Kaise chalta hai:
//   t('Order now', 'अभी ऑर्डर कीजिए')
// English pehle, Hindi baad me. Jo bhasha chuni hui hai
// wahi dikhti hai. Naya text likhna ho to bas dono likh dijiye.
//
// Bhasha yaad rehti hai (1 saal ka cookie), isliye ek baar
// chunne ke baad har page usi me khulega.
// ============================================================

function lang() {
    static $l = null;
    if ($l !== null) return $l;

    // 1. URL se badlo (?lang=hi) — aur yaad rakh lo
    if (isset($_GET['lang']) && in_array($_GET['lang'], ['en', 'hi'], true)) {
        $l = $_GET['lang'];
        setcookie('mk_lang', $l, [
            'expires'  => time() + 31536000,
            'path'     => '/',
            'samesite' => 'Lax',
        ]);
        $_COOKIE['mk_lang'] = $l;
        return $l;
    }
    // 2. pehle chuni hui
    if (isset($_COOKIE['mk_lang']) && in_array($_COOKIE['mk_lang'], ['en', 'hi'], true)) {
        return $l = $_COOKIE['mk_lang'];
    }
    // 3. phone ki bhasha Hindi ho to Hindi, warna English
    $al = strtolower($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '');
    if (strpos($al, 'hi') === 0 || strpos($al, ',hi') !== false) return $l = 'hi';
    return $l = 'en';
}

function is_hi() { return lang() === 'hi'; }

/** English ya Hindi — jo chuni ho */
function t($en, $hi = null) {
    if ($hi === null) return $en;
    return is_hi() ? $hi : $en;
}

/** Dono ek saath: bada English, neeche chhota Hindi (ya ulta) */
function t2($en, $hi) {
    return is_hi()
        ? '<span class="l1">' . h($hi) . '</span><span class="l2">' . h($en) . '</span>'
        : '<span class="l1">' . h($en) . '</span><span class="l2">' . h($hi) . '</span>';
}

/** dusri bhasha ka link (abhi ke page par hi) */
function lang_switch_url() {
    $other = is_hi() ? 'en' : 'hi';
    $q = $_GET; $q['lang'] = $other;
    return strtok($_SERVER['REQUEST_URI'], '?') . '?' . http_build_query($q);
}
function lang_other_label() { return is_hi() ? 'English' : 'हिंदी'; }

/** page ka lang attribute */
function html_lang() { return is_hi() ? 'hi' : 'en-IN'; }

/** ginti — Hindi me Devanagari ank */
function num($n) {
    if (!is_hi()) return (string)$n;
    return strtr((string)$n, ['0'=>'०','1'=>'१','2'=>'२','3'=>'३','4'=>'४','5'=>'५','6'=>'६','7'=>'७','8'=>'८','9'=>'९']);
}

/** tareekh */
function dt_fmt($ts, $withTime = false) {
    $d = is_int($ts) ? $ts : strtotime($ts);
    return date($withTime ? 'd M Y, h:i A' : 'd M Y', $d);
}

/** "5 minute pehle" jaisa */
function ago($ts) {
    $d = is_int($ts) ? $ts : strtotime($ts);
    $s = time() - $d;
    if ($s < 60)    return t('just now', 'अभी-अभी');
    if ($s < 3600)  { $m = (int)($s / 60);   return num($m) . t(' min ago', ' मिनट पहले'); }
    if ($s < 86400) { $hh = (int)($s / 3600); return num($hh) . t($hh == 1 ? ' hour ago' : ' hours ago', ' घंटे पहले'); }
    $dd = (int)($s / 86400);
    return num($dd) . t($dd == 1 ? ' day ago' : ' days ago', ' दिन पहले');
}

/** gaon ka naam — English me khula ho to English naam (ho to) */
function vname($row) {
    if (is_array($row)) {
        return (!is_hi() && !empty($row['name_en'])) ? $row['name_en'] : ($row['name'] ?? '');
    }
    return (string)$row;
}

/** ---------- setting padhna / likhna ---------- */
function setg(PDO $pdo, $k, $d = null) {
    static $all = null;
    if ($all === null) {
        $all = [];
        try { foreach ($pdo->query("SELECT k, v FROM settings") as $r) { $all[$r['k']] = $r['v']; } }
        catch (Throwable $e) {}
    }
    return array_key_exists($k, $all) ? $all[$k] : $d;
}

/**
 * Abhi dukaan khuli hai ya nahi.
 * Lautata hai: [khula?, chhoti baat, badi baat]
 */
function open_now(PDO $pdo) {
    $o = setg($pdo, 'open_time', '08:00');
    $c = setg($pdo, 'close_time', '20:00');
    $shut = setg($pdo, 'closed_today', '0') === '1';
    $offd = array_filter(array_map('trim', explode(',', (string)setg($pdo, 'off_days', ''))));

    $fmt = function ($hm) {
        $ts = strtotime($hm ?: '08:00');
        return is_hi() ? (date('g', $ts) . ':' . date('i', $ts) . ' ' . (date('a', $ts) === 'am' ? 'बजे' : 'बजे'))
                       : date('g:i A', $ts);
    };
    $note = is_hi() ? setg($pdo, 'closed_note_hi', '') : setg($pdo, 'closed_note_en', '');

    if ($shut || in_array(date('Y-m-d'), $offd, true)) {
        return [false, t('Closed today', 'आज बंद है'),
                $note ?: t('We open tomorrow at ' . $fmt($o) . '. You can still send an order.',
                           'कल ' . $fmt($o) . ' से खुलेंगे। ऑर्डर अभी भी भेज सकते हैं।')];
    }
    $now = (int)date('Hi');
    $oi  = (int)str_replace(':', '', $o ?: '08:00');
    $ci  = (int)str_replace(':', '', $c ?: '20:00');
    if ($now >= $oi && $now < $ci) {
        return [true, t('Open now', 'अभी खुले हैं'),
                t('Delivering till ' . $fmt($c), $fmt($c) . ' तक डिलीवरी')];
    }
    if ($now < $oi) {
        return [false, t('Opens at ' . $fmt($o), $fmt($o) . ' से खुलेंगे'),
                t('Send your order now — it goes out first thing.', 'अभी ऑर्डर भेज दीजिए — सुबह सबसे पहले जाएगा।')];
    }
    return [false, t('Closed for today', 'आज के लिए बंद'),
            t('Opens tomorrow at ' . $fmt($o), 'कल ' . $fmt($o) . ' से खुलेंगे')];
}
