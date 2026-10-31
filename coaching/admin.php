<?php
// ============================================================
// Coaching Saathi — Maakit ka Admin Panel (sirf Ankit / Maakit)
// Login: config.php ki CS_ADMIN_KEY (na ho to UPDATE_KEY). 12 ghante.
// Saari coaching, nayi coaching jodna (owner ko pehla password),
// band / chalu, owner ka password, kamai (sirf student app plan).
// Coaching ki fees ka hisaab yahan NAHI dikhta — wo coaching ka apna hai.
// ============================================================
require __DIR__ . '/inc.php';

$err = ''; $SHOW = null;
$key = cs_admin_key();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && cs_post('a') === 'login') {
    if (!cs_take('admin-ip:' . cs_ip(), 10, 3600)) $err = 'Bahut koshish ho gayi. 1 ghante baad.';
    elseif (strlen($key) < 12 || !hash_equals($key, (string)($_POST['key'] ?? ''))) $err = 'Chaabi galat hai.';
    else {
        cs_logout();
        $tok = bin2hex(random_bytes(32));
        cs_q("INSERT INTO cs_session (token_hash,kind,mobile,coaching_id,user_id,expires) VALUES (?,'admin','',NULL,NULL,NOW()+INTERVAL 12 HOUR)", [hash('sha256', $tok)]);
        cs_cookie('cs_t', $tok, 1);
        cs_go('admin.php');
    }
}
if (!cs_is_admin()) {
    cs_head('Maakit Admin');
    echo '<div class="hero"><h1>Coaching Saathi — Admin</h1><p>Sirf Maakit ke liye</p></div><div class="wrap">', $err ? '<div class="flash bad">' . h($err) . '</div>' : '',
         '<form method="post" class="card"><input type="hidden" name="a" value="login"><label>Admin chaabi</label><input type="password" name="key" required autocomplete="off"><button class="btn full">Andar jaiye</button></form></div>';
    cs_foot(); exit;
}

function ad_go(string $q, string $m = 'ok'): void { cs_go('admin.php?' . $q . '&m=' . $m); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!cs_csrf_ok()) ad_go('x=1', 'csrf');
    $a = cs_post('a');
    $c = (int)cs_post('c');
    $C = $c ? cs_row('SELECT * FROM cs_coaching WHERE id=?', [$c]) : null;
    if ($a === 'out') { cs_logout(); cs_go('admin.php'); }
    if ($a === 'block' && $C) {
        $block = (int)cs_meta($c)['blocked'] === 1 ? 0 : 1;
        cs_q('INSERT INTO cs_coaching_meta (coaching_id,blocked,admin_note) VALUES (?,?,?) ON DUPLICATE KEY UPDATE blocked=VALUES(blocked), admin_note=VALUES(admin_note), updated_at=NOW()',
            [$c, $block, mb_substr(cs_post('note'), 0, 255)]);
        if ($block) cs_q('DELETE FROM cs_session WHERE coaching_id=?', [$c]);   // band hote hi staff bahar (parent ko me.php rokta hai)
        ad_go('c=' . $c);
    }
    if ($a === 'opass' && $C) {
        $o = cs_row("SELECT * FROM cs_user WHERE coaching_id=? AND role='owner' ORDER BY id LIMIT 1", [$c]);
        if (!$o) ad_go('c=' . $c, 'err');
        $pass = cs_temp_password();
        cs_set_password($o['mobile'], $pass, true, null);
        $SHOW = ['mobile' => $o['mobile'], 'pass' => $pass, 'who' => $o['naam'] . ' (' . $C['naam'] . ')'];
        $_GET['c'] = (string)$c;
    }
    if ($a === 'new') {
        $m = cs_mobile(cs_post('mobile')); $naam = mb_substr(cs_post('naam'), 0, 120); $own = mb_substr(cs_post('own'), 0, 80); $city = mb_substr(cs_post('city'), 0, 60);
        $type = array_key_exists(cs_post('ctype'), CS_TYPES) ? cs_post('ctype') : 'tuition';
        if (!$m || mb_strlen($naam) < 3 || $own === '' || $city === '') ad_go('p=new', 'err');
        cs_q('INSERT INTO cs_coaching (slug,naam,ctype,phone,city,reg_no) VALUES (?,?,?,?,?,?)', [cs_slug($naam, $city), $naam, $type, $m, $city, mb_substr(cs_post('reg'), 0, 60)]);
        $nc = (int)cs_db()->lastInsertId();
        cs_q("INSERT INTO cs_user (coaching_id,mobile,naam,role) VALUES (?,?,?,'owner')", [$nc, $m, $own]);
        $acc = cs_account($m);
        if (!$acc || (int)$acc['temp'] === 1) {
            $pass = cs_temp_password(); cs_set_password($m, $pass, true, null);
            $SHOW = ['mobile' => $m, 'pass' => $pass, 'who' => $own . ' (' . $naam . ')'];
        } else $SHOW = ['mobile' => $m, 'pass' => '', 'who' => $own . ' (' . $naam . ')'];
        $_GET['c'] = (string)$nc;
    }
}

cs_head('Maakit Admin — Coaching Saathi');
echo '<div class="top"><span class="logo">M</span><b>Coaching Saathi — Admin</b><a href="admin.php">Sab</a>&nbsp;&nbsp;<a href="?p=new">+ Coaching</a></div><div class="wrap">', cs_flash();
if ($SHOW) {
    $msg = "Namaste {$SHOW['who']}, aapka Coaching Saathi tayyar hai:\n" . cs_base() . "/\nMobile: {$SHOW['mobile']}\n" .
           ($SHOW['pass'] !== '' ? "Pehla password: {$SHOW['pass']}\nLogin karte hi apna naya password rakhiye." : "Apne purane password se login kariye.");
    echo '<div class="card" style="border:2px solid var(--c)"><b>', h($SHOW['who']), '</b><table><tr><th>Mobile</th><td class="r">', h($SHOW['mobile']), '</td></tr>',
         $SHOW['pass'] !== '' ? '<tr><th>Pehla password</th><td class="r" style="font-size:20px"><b>' . h($SHOW['pass']) . '</b></td></tr>' : '<tr><th>Password</th><td class="r">Pehle se bana hai</td></tr>',
         '</table><p class="note">Ye password sirf abhi dikh raha hai.</p><div class="btns"><a class="btn wa" target="_blank" href="', h(cs_wa($SHOW['mobile'], $msg)), '">WhatsApp par bhejiye</a></div></div>';
}

$cview = (int)cs_get('c');
if (cs_get('p') === 'new') {
    echo '<h1>Nayi coaching jodiye</h1><form method="post" class="card">', cs_csrf_field(), '<input type="hidden" name="a" value="new">',
         '<label>Coaching ka naam</label><input name="naam" required maxlength="120"><label>Owner ka naam</label><input name="own" required maxlength="80">',
         '<label>Owner ka mobile (isi se login)</label><input name="mobile" inputmode="numeric" required maxlength="13"><label>Kis tarah ki</label><select name="ctype">';
    foreach (CS_TYPES as $k => $v) echo '<option value="', $k, '">', h($v), '</option>';
    echo '</select><label>Shehar</label><input name="city" required value="Lucknow" maxlength="60"><label>UP registration no. (agar hai)</label><input name="reg" maxlength="60">',
         '<button class="btn full">Coaching banaiye + owner ko password</button></form>';
} elseif ($cview && ($C = cs_row('SELECT * FROM cs_coaching WHERE id=?', [$cview]))) {
    $m = cs_meta($cview);
    $own = cs_row("SELECT * FROM cs_user WHERE coaching_id=? AND role='owner' ORDER BY id LIMIT 1", [$cview]);
    $st = cs_row("SELECT COUNT(*) n, SUM(status='active') a FROM cs_student WHERE coaching_id=?", [$cview]);
    $paid = (int)cs_val("SELECT COUNT(DISTINCT student_id) FROM cs_sub WHERE coaching_id=? AND status='paid' AND valid_till>=CURDATE()", [$cview]);
    $earn = (int)cs_val("SELECT COALESCE(SUM(amount),0) FROM cs_sub WHERE coaching_id=? AND status='paid'", [$cview]);
    $last = cs_val('SELECT MAX(d) FROM cs_attendance WHERE coaching_id=?', [$cview]);
    echo '<h1>', h($C['naam']), ' ', $m['blocked'] ? '<span class="tag red">Band</span>' : '<span class="tag green">Chalu</span>', '</h1>',
         '<div class="grid"><div class="kpi"><small>Active bachche</small><strong>', (int)$st['a'], '</strong></div><div class="kpi"><small>Paid app</small><strong>', $paid, '</strong></div>',
         '<div class="kpi"><small>Kul kamai (app)</small><strong>', rs($earn), '</strong></div><div class="kpi"><small>Aakhri haaziri</small><strong style="font-size:16px">', $last ? h(date('d M', strtotime($last))) : '—', '</strong></div></div>',
         '<div class="card"><div class="sub">', h(CS_TYPES[$C['ctype']] ?? ''), ' · ', h($C['city']), ' · ', h($C['address']), '<br>Owner: ', h($own['naam'] ?? '—'), ' · ', h($own['mobile'] ?? ''), ' · Juda: ', h(date('d M Y', strtotime($C['created_at']))), '</div>',
         '<div class="btns"><a class="btn sm ghost" href="tel:', h($own['mobile'] ?? ''), '">Call owner</a><a class="btn sm ghost" target="_blank" href="site.php?c=', h($C['slug']), '">Website</a></div></div>',
         '<form method="post" class="card">', cs_csrf_field(), '<input type="hidden" name="a" value="opass"><input type="hidden" name="c" value="', $cview, '"><b>Owner password bhool gaya</b>',
         '<p class="note">Naya pehla password banega, purana band. Owner pehli login par apna rakhega.</p><button class="btn sm" onclick="return confirm(\'Naya password banayein?\')">Naya password</button></form>',
         '<form method="post" class="card">', cs_csrf_field(), '<input type="hidden" name="a" value="block"><input type="hidden" name="c" value="', $cview, '"><b>', $m['blocked'] ? 'Wapas chalu kariye' : 'Coaching band kariye', '</b>',
         '<p class="note">Band hone par owner, teacher, parent sab bahar; website bhi band. Data mitta nahi.</p><label>Kaaran / note</label><input name="note" maxlength="255" value="', h($m['admin_note']), '">',
         '<button class="btn sm ', $m['blocked'] ? '' : 'red', '" style="margin-top:8px" onclick="return confirm(\'Pakka?\')">', $m['blocked'] ? 'Chalu kariye' : 'Band kariye', '</button></form>';
} else {
    $mo = date('Y-m-01');
    $k = cs_row("SELECT (SELECT COUNT(*) FROM cs_coaching) c, (SELECT COUNT(*) FROM cs_student WHERE status='active') s,
                 (SELECT COUNT(DISTINCT student_id) FROM cs_sub WHERE status='paid' AND valid_till>=CURDATE()) p,
                 (SELECT COALESCE(SUM(amount),0) FROM cs_sub WHERE status='paid' AND paid_on>=?) m,
                 (SELECT COALESCE(SUM(amount),0) FROM cs_sub WHERE status='paid') t", [$mo]);
    echo '<h1>Saari coaching</h1><div class="grid"><div class="kpi"><small>Coaching</small><strong>', (int)$k['c'], '</strong></div><div class="kpi"><small>Active bachche</small><strong>', (int)$k['s'], '</strong></div>',
         '<div class="kpi"><small>Paid app (abhi)</small><strong>', (int)$k['p'], '</strong></div><div class="kpi"><small>Is mahine kamai</small><strong>', rs($k['m']), '</strong></div></div>',
         '<p class="note">Kul kamai ab tak: <b>', rs($k['t']), '</b> (sirf student app plan — coaching ki fees Maakit ki nahi).</p>';
    $q = cs_get('q');
    echo '<form class="search"><input name="q" value="', h($q), '" placeholder="Naam, shehar ya owner ka mobile"></form><div class="card">';
    $sql = "SELECT c.*, u.naam onaam, u.mobile omob, COALESCE(m.blocked,0) blocked,
            (SELECT COUNT(*) FROM cs_student s WHERE s.coaching_id=c.id AND s.status='active') n,
            (SELECT COUNT(DISTINCT x.student_id) FROM cs_sub x WHERE x.coaching_id=c.id AND x.status='paid' AND x.valid_till>=CURDATE()) pd
            FROM cs_coaching c LEFT JOIN cs_user u ON u.coaching_id=c.id AND u.role='owner' LEFT JOIN cs_coaching_meta m ON m.coaching_id=c.id";
    $par = [];
    if ($q !== '') { $sql .= ' WHERE c.naam LIKE ? OR c.city LIKE ? OR u.mobile LIKE ?'; $lk = '%' . $q . '%'; $par = [$lk, $lk, $lk]; }
    $rows = cs_all($sql . ' GROUP BY c.id ORDER BY c.id DESC LIMIT 300', $par);
    foreach ($rows as $r)
        echo '<a class="row" style="text-decoration:none;color:inherit" href="?c=', (int)$r['id'], '"><div class="grow"><b>', h($r['naam']), '</b> ', $r['blocked'] ? '<span class="tag red">Band</span>' : '', '<div class="sub">', h($r['city']), ' · ', h($r['onaam']), ' ', h($r['omob']), ' · ',
             h(date('d M y', strtotime($r['created_at']))), '</div></div><div style="text-align:right"><b>', (int)$r['n'], '</b> bachche<div class="sub">', (int)$r['pd'], ' paid</div></div></a>';
    if (!$rows) echo '<p class="note">Koi coaching nahi.</p>';
    echo '</div>';
}
echo '<form method="post">', cs_csrf_field(), '<input type="hidden" name="a" value="out"><button class="btn ghost full">Logout</button></form></div>';
cs_foot();
