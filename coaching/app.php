<?php
// Coaching Saathi — owner aur teacher ka app (ek hi file, ?p= se page).
// Teacher: sirf apne batch, haaziri. Fees / munafa / doosre teacher kabhi nahi.
require __DIR__ . '/inc.php';

$me = cs_me();
$cid = $me['cid']; $uid = $me['uid']; $C = $me['c']; $owner = cs_owner();
$p = preg_replace('/[^a-z]/', '', cs_get('p', 'home')) ?: 'home';
$today = date('Y-m-d');
$SHOW = null;

cs_post_dues($cid);   // mahine ki fees apne aap (do baar kabhi nahi)

function back(string $to, string $m = 'ok'): void { cs_go('app.php?' . $to . '&m=' . $m); }
function need_owner(): void { if (!cs_owner()) back('p=home', 'band'); }

// ======================= POST =======================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!cs_csrf_ok()) back('p=' . $p, 'csrf');
    $a = cs_post('a');

    if ($a === 'att') {
        $bid = (int)cs_post('batch'); $d = cs_date(cs_post('d'));
        $b = batch_ok($bid);
        $minD = $owner ? '2000-01-01' : date('Y-m-d', strtotime('-7 days'));
        if (!$b || !$d || $d > $today || $d < $minD) back('p=att', 'err');
        $ids = array_column(cs_all("SELECT e.student_id FROM cs_enroll e JOIN cs_student s ON s.id=e.student_id AND s.coaching_id=e.coaching_id
                                    WHERE e.batch_id=? AND e.coaching_id=? AND e.status='active' AND s.status='active'", [$bid, $cid]), 'student_id');
        $st = cs_db()->prepare('INSERT INTO cs_attendance (coaching_id,batch_id,student_id,d,status,by_user,updated_at) VALUES (?,?,?,?,?,?,NOW())
                                ON DUPLICATE KEY UPDATE status=VALUES(status), by_user=VALUES(by_user), updated_at=NOW()');
        foreach ($ids as $sid) {
            $v = $_POST['s'][$sid] ?? '';
            if ($v === 'P' || $v === 'A') $st->execute([$cid, $bid, $sid, $d, $v, $uid]);
        }
        cs_go('app.php?p=att&batch=' . $bid . '&d=' . $d . '&done=1&m=saved');
    }

    // ---- baaki sab sirf owner ----
    need_owner();

    if ($a === 'course') {
        $naam = mb_substr(cs_post('naam'), 0, 80); $ft = array_key_exists(cs_post('fee_type'), CS_FEE_TYPES) ? cs_post('fee_type') : 'monthly';
        if ($naam === '') back('p=courses', 'err');
        cs_q('INSERT INTO cs_course (coaching_id,naam,fee_type,amount,months) VALUES (?,?,?,?,?)',
            [$cid, $naam, $ft, cs_int(cs_post('amount')), min(60, cs_int(cs_post('months')))]);
        back('p=courses', 'saved');
    }
    if ($a === 'batch') {
        $course = (int)cs_val('SELECT id FROM cs_course WHERE id=? AND coaching_id=?', [(int)cs_post('course'), $cid]);
        $t = (int)cs_post('teacher');
        $t = $t ? (int)cs_val('SELECT id FROM cs_user WHERE id=? AND coaching_id=? AND active=1', [$t, $cid]) : 0;
        $naam = mb_substr(cs_post('naam'), 0, 80);
        if (!$course || $naam === '') back('p=courses', 'err');
        cs_q('INSERT INTO cs_batch (coaching_id,course_id,naam,timing,teacher_id,seat_limit) VALUES (?,?,?,?,?,?)',
            [$cid, $course, $naam, mb_substr(cs_post('timing'), 0, 60), $t ?: null, cs_int(cs_post('seats'))]);
        back('p=courses', 'saved');
    }
    if ($a === 'batch_off') {
        cs_q('UPDATE cs_batch SET active=0 WHERE id=? AND coaching_id=?', [(int)cs_post('id'), $cid]);
        back('p=courses', 'ok');
    }
    if ($a === 'teacher') {
        $m = cs_mobile(cs_post('mobile')); $naam = mb_substr(cs_post('naam'), 0, 80);
        if (!$m || $naam === '') back('p=team', 'err');
        cs_q("INSERT INTO cs_user (coaching_id,mobile,naam,role) VALUES (?,?,?,'teacher')
              ON DUPLICATE KEY UPDATE naam=VALUES(naam), active=1", [$cid, $m, $naam]);
        back('p=team', 'saved');
    }
    if ($a === 'teacher_off') {
        cs_q("UPDATE cs_user SET active=1-active WHERE id=? AND coaching_id=? AND role='teacher'", [(int)cs_post('id'), $cid]);
        back('p=team', 'ok');
    }
    if ($a === 'student') {
        $naam = mb_substr(cs_post('naam'), 0, 80); $m = cs_mobile(cs_post('mobile'));
        $join = cs_date(cs_post('join')) ?: $today;
        $b = batch_ok((int)cs_post('batch'));
        if ($naam === '' || !$m || !$b) back('p=add', 'err');
        if ((int)$b['seat_limit'] > 0 && (int)$b['n'] >= (int)$b['seat_limit'] && cs_post('over') !== '1') back('p=add&full=' . (int)$b['id'], 'err');
        cs_q('INSERT INTO cs_student (coaching_id,naam,father,mobile,cls,join_date) VALUES (?,?,?,?,?,?)',
            [$cid, $naam, mb_substr(cs_post('father'), 0, 80), $m, mb_substr(cs_post('cls'), 0, 30), $join]);
        $sid = (int)cs_db()->lastInsertId();
        $fee = cs_post('fee') === '' ? (int)$b['amount'] : cs_int(cs_post('fee'));
        cs_q('INSERT INTO cs_enroll (coaching_id,student_id,batch_id,fee,fee_note,start_date) VALUES (?,?,?,?,?,?)',
            [$cid, $sid, $b['id'], $fee, mb_substr(cs_post('fee_note'), 0, 120), $join]);
        cs_post_dues($cid, $sid);
        cs_go('app.php?p=student&id=' . $sid . '&m=added');
    }
    $sid = (int)cs_post('sid');
    $S = $sid ? cs_row('SELECT * FROM cs_student WHERE id=? AND coaching_id=?', [$sid, $cid]) : null;
    if ($a === 'edit' && $S) {
        $m = cs_mobile(cs_post('mobile'));
        if (!$m || cs_post('naam') === '') back('p=student&id=' . $sid, 'err');
        cs_q('UPDATE cs_student SET naam=?, father=?, mobile=?, cls=? WHERE id=? AND coaching_id=?',
            [mb_substr(cs_post('naam'), 0, 80), mb_substr(cs_post('father'), 0, 80), $m, mb_substr(cs_post('cls'), 0, 30), $sid, $cid]);
        back('p=student&id=' . $sid, 'saved');
    }
    if ($a === 'status' && $S) {
        cs_q('UPDATE cs_student SET status=? WHERE id=? AND coaching_id=?', [$S['status'] === 'active' ? 'archived' : 'active', $sid, $cid]);
        back('p=student&id=' . $sid, 'ok');
    }
    if ($a === 'enroll' && $S) {
        $b = batch_ok((int)cs_post('batch'));
        if (!$b) back('p=student&id=' . $sid, 'err');
        $start = cs_date(cs_post('start')) ?: $today;
        cs_q('INSERT INTO cs_enroll (coaching_id,student_id,batch_id,fee,fee_note,start_date) VALUES (?,?,?,?,?,?)',
            [$cid, $sid, $b['id'], cs_post('fee') === '' ? (int)$b['amount'] : cs_int(cs_post('fee')), mb_substr(cs_post('fee_note'), 0, 120), $start]);
        cs_post_dues($cid, $sid);
        back('p=student&id=' . $sid, 'saved');
    }
    if ($a === 'unenroll' && $S) {
        cs_q("UPDATE cs_enroll SET status='ended' WHERE id=? AND coaching_id=? AND student_id=?", [(int)cs_post('eid'), $cid, $sid]);
        back('p=student&id=' . $sid, 'ok');
    }
    if ($a === 'pay' && $S) {
        $amt = cs_int(cs_post('amount')); $mode = cs_post('mode') === 'upi' ? 'upi' : 'cash';
        $d = cs_date(cs_post('d')) ?: $today;
        $nonce = preg_match('/^[a-f0-9]{16}$/', cs_post('nonce')) ? cs_post('nonce') : '';
        if ($amt < 1 || $amt > 1000000 || $d > $today) back('p=student&id=' . $sid, 'err');
        $rid = cs_take_payment($cid, $sid, $amt, $mode, $d, $uid, $nonce);
        cs_go('app.php?p=student&id=' . $sid . '&r=' . $rid . '&m=paid');
    }
    if ($a === 'waive' && $S) {
        $bal = cs_balance($cid, $sid); $why = mb_substr(cs_post('why'), 0, 160);
        $amt = min($bal, cs_post('amount') === '' ? $bal : cs_int(cs_post('amount')));
        if ($amt < 1 || $why === '') back('p=student&id=' . $sid, 'err');
        cs_q("INSERT INTO cs_ledger (coaching_id,student_id,kind,amount,note,by_user) VALUES (?,?,'waive',?,?,?)", [$cid, $sid, $amt, 'Maaf: ' . $why, $uid]);
        back('p=student&id=' . $sid, 'waived');
    }
    if ($a === 'fix' && $S) {
        $amt = (int)cs_post('amount'); $why = mb_substr(cs_post('why'), 0, 160);
        if ($amt === 0 || abs($amt) > 1000000 || $why === '') back('p=student&id=' . $sid, 'err');
        cs_q("INSERT INTO cs_ledger (coaching_id,student_id,kind,amount,note,by_user) VALUES (?,?,'fix',?,?,?)", [$cid, $sid, $amt, 'Sudhaar: ' . $why, $uid]);
        back('p=student&id=' . $sid, 'saved');
    }
    if ($a === 'cancel_receipt') {
        $r = cs_row('SELECT * FROM cs_receipt WHERE id=? AND coaching_id=? AND cancelled=0', [(int)cs_post('rid'), $cid]);
        $why = mb_substr(cs_post('why'), 0, 160);
        if (!$r || $why === '') back('p=receipts', 'err');
        cs_db()->beginTransaction();
        cs_q('UPDATE cs_receipt SET cancelled=1, cancel_reason=? WHERE id=? AND coaching_id=?', [$why, $r['id'], $cid]);
        cs_q("INSERT INTO cs_ledger (coaching_id,student_id,kind,amount,note,receipt_id,by_user) VALUES (?,?,'fix',?,?,?,?)",
            [$cid, $r['student_id'], $r['amount'], 'Receipt #' . $r['no'] . ' radd: ' . $why, $r['id'], $uid]);
        cs_db()->commit();
        back('p=receipts', 'ok');
    }
    if ($a === 'enq') {
        $m = cs_mobile(cs_post('mobile')); $naam = mb_substr(cs_post('naam'), 0, 80);
        if (!$m || $naam === '') back('p=enq', 'err');
        cs_q('INSERT INTO cs_enquiry (coaching_id,naam,mobile,interest,follow_up,note) VALUES (?,?,?,?,?,?)',
            [$cid, $naam, $m, mb_substr(cs_post('interest'), 0, 120), cs_date(cs_post('follow')) ?: $today, mb_substr(cs_post('note'), 0, 255)]);
        back('p=enq', 'saved');
    }
    if ($a === 'enq_upd') {
        $st = in_array(cs_post('status'), ['open', 'joined', 'closed'], true) ? cs_post('status') : 'open';
        cs_q('UPDATE cs_enquiry SET status=?, follow_up=?, note=? WHERE id=? AND coaching_id=?',
            [$st, cs_date(cs_post('follow')) ?: null, mb_substr(cs_post('note'), 0, 255), (int)cs_post('id'), $cid]);
        back('p=enq', 'saved');
    }
    if ($a === 'setup') {
        $naam = mb_substr(cs_post('naam'), 0, 120); $ph = cs_mobile(cs_post('phone'));
        $slug = strtolower(preg_replace('/[^a-z0-9-]/i', '', cs_post('slug')));
        if ($naam === '' || !$ph || strlen($slug) < 3) back('p=setup', 'err');
        if (cs_val('SELECT 1 FROM cs_coaching WHERE slug=? AND id<>?', [$slug, $cid])) back('p=setup&taken=1', 'err');
        $color = preg_match('/^#[0-9a-fA-F]{6}$/', cs_post('color')) ? cs_post('color') : '#0C831F';
        cs_q('UPDATE cs_coaching SET naam=?, ctype=?, phone=?, city=?, address=?, reg_no=?, color=?, about=?, timing=?, slug=? WHERE id=?',
            [$naam, array_key_exists(cs_post('ctype'), CS_TYPES) ? cs_post('ctype') : 'tuition', $ph, mb_substr(cs_post('city'), 0, 60),
             mb_substr(cs_post('address'), 0, 255), mb_substr(cs_post('reg'), 0, 60), $color, mb_substr(cs_post('about'), 0, 2000),
             mb_substr(cs_post('timing'), 0, 120), substr($slug, 0, 40), $cid]);
        back('p=setup', 'saved');
    }
    if ($a === 'logo') {
        $up = cs_save_upload('logo', 'image');
        if (isset($up['err'])) back('p=setup', $up['err'] === 'none' ? 'err' : 'big');
        $old = cs_meta($cid)['logo'];
        cs_q('INSERT INTO cs_coaching_meta (coaching_id,logo) VALUES (?,?) ON DUPLICATE KEY UPDATE logo=VALUES(logo), updated_at=NOW()', [$cid, $up['name']]);
        if ($old) @unlink(cs_files_dir() . '/' . preg_replace('/[^a-f0-9]/', '', $old) . '.bin');
        back('p=setup', 'saved');
    }
    if ($a === 'login_send') {
        // Teacher / parent ko pehla (ya naya) password — page par ek hi baar dikhta hai, kahin likha nahi jaata
        if (cs_post('who') === 'teacher') {
            $t = cs_row("SELECT * FROM cs_user WHERE id=? AND coaching_id=? AND role='teacher' AND active=1", [(int)cs_post('id'), $cid]);
            $mob = $t['mobile'] ?? ''; $who = $t['naam'] ?? ''; $p = 'team';
        } else {
            $mob = $S['mobile'] ?? ''; $who = $S ? $S['naam'] . ' ke parent' : ''; $p = 'student'; $_GET['id'] = (string)$sid;
        }
        if ($mob === '') back('p=' . $p, 'err');
        if (cs_can_reset($cid, $mob)) {
            $pass = cs_temp_password();
            cs_set_password($mob, $pass, true, $uid);
            $SHOW = ['mobile' => $mob, 'pass' => $pass, 'who' => $who];
        } else {
            $SHOW = ['mobile' => $mob, 'pass' => '', 'who' => $who];
        }
    } else {
        back('p=home', 'err');
    }
}

// ======================= PAGES =======================
$tabs = $owner
    ? ['home' => ['Aaj', 'home'], 'students' => ['Bachche', 'users'], 'dues' => ['Fees', 'rupee'], 'padhai' => ['Padhai', 'book'], 'more' => ['Aur', 'more']]
    : ['home' => ['Aaj', 'home'], 'students' => ['Bachche', 'users'], 'att' => ['Haaziri', 'check'], 'padhai' => ['Padhai', 'book'], 'more' => ['Aur', 'more']];
cs_head($C['naam'] . ' — Coaching Saathi', $C);
echo '<div class="top">', cs_logo_html($C), '<b>', h($C['naam']), '</b>',
     $owner ? '' : '<span class="tag">Teacher</span>', '</div><div class="wrap">', cs_flash();
if (!empty($SHOW)) {
    if ($SHOW['pass'] !== '') {
        $msg = "Namaste, {$C['naam']} ka Coaching Saathi app:\n" . cs_base() . "/\nMobile: {$SHOW['mobile']}\nPehla password: {$SHOW['pass']}\nLogin karte hi apna naya password rakhiye.";
        echo '<div class="card" style="border:2px solid var(--c)"><b>', h($SHOW['who']), ' ka login</b><table><tr><th>Mobile</th><td class="r">', h($SHOW['mobile']), '</td></tr>',
             '<tr><th>Pehla password</th><td class="r" style="font-size:20px;letter-spacing:1px"><b>', h($SHOW['pass']), '</b></td></tr></table>',
             '<p class="note">Ye password sirf abhi dikh raha hai. Bhejiye — pehli login par wo apna naya rakhenge.</p>',
             '<div class="btns"><a class="btn wa" target="_blank" href="', h(cs_wa($SHOW['mobile'], $msg)), '">WhatsApp par bhejiye</a></div></div>';
    } else {
        echo '<div class="flash bad">', h($SHOW['mobile']), ' ka password pehle se bana hai aur ye number doosri coaching me bhi juda hai (ya owner hai). Wahi password chalega. Bhool gaye ho to Maakit se badalwaiye.</div>';
    }
}

$bal = $owner ? cs_balances($cid) : [];
$wa_due = function (array $s, int $b) use ($C) {
    return cs_wa($s['mobile'], "Namaste, {$C['naam']} se: {$s['naam']} ki fees " . rs($b) . " baaki hai. Kripya jald jama karein. Dhanyavaad.");
};

switch ($p) {
// ----------------------------------------------------------------
case 'home':
    $active = (int)cs_val("SELECT COUNT(*) FROM cs_student WHERE coaching_id=? AND status='active'", [$cid]);
    $absToday = (int)cs_val("SELECT COUNT(*) FROM cs_attendance WHERE coaching_id=? AND d=? AND status='A'", [$cid, $today]);
    $risk = count(array_filter(array_keys(cs_risk_scores($cid, $owner)), fn($i) => student_ok((int)$i) !== null));
    echo '<h1>Namaste, ', h($me['user']['naam']), '</h1><div class="grid">';
    echo '<a class="kpi" href="?p=students"><small>Active bachche</small><strong>', $active, '</strong></a>';
    if ($owner) {
        $coll = (int)cs_val("SELECT COALESCE(SUM(amount),0) FROM cs_receipt WHERE coaching_id=? AND cancelled=0 AND paid_on>=?", [$cid, date('Y-m-01')]);
        $due = array_sum(array_filter($bal, fn($b) => $b > 0));
        echo '<a class="kpi" href="?p=receipts"><small>Is mahine mila</small><strong>', rs($coll), '</strong></a>',
             '<a class="kpi red" href="?p=dues"><small>Kul baaki</small><strong>', rs($due), '</strong></a>';
    }
    echo '<a class="kpi', $absToday ? ' red' : '', '" href="?p=att"><small>Aaj absent</small><strong>', $absToday, '</strong></a>',
         '<a class="kpi', $risk ? ' red' : '', '" href="padhai.php?p=risk"><small>Khatre me bachche</small><strong>', $risk, '</strong></a>';
    if ($owner) {
        $fu = (int)cs_val("SELECT COUNT(*) FROM cs_enquiry WHERE coaching_id=? AND status='open' AND follow_up<=?", [$cid, $today]);
        echo '<a class="kpi', $fu ? ' red' : '', '" href="?p=enq"><small>Aaj ki enquiry call</small><strong>', $fu, '</strong></a>';
    }
    echo '</div>';
    $bs = my_batches();
    if ($owner && !$bs) {
        echo '<div class="card"><b>Shuru kaise karein — 3 kadam</b><ol><li><a href="?p=courses">Course aur fees likhiye</a></li>',
             '<li><a href="?p=courses">Batch banaiye</a></li><li><a href="?p=add">Bachche jodiye</a></li></ol></div>';
    }
    echo '<div class="card"><b>Aaj ki haaziri</b>';
    foreach ($bs as $b) {
        $done = (int)cs_val('SELECT COUNT(*) FROM cs_attendance WHERE batch_id=? AND coaching_id=? AND d=?', [$b['id'], $cid, $today]);
        echo '<div class="row"><div class="grow">', h($b['naam']), '<div class="sub">', h($b['timing']), ' · ', (int)$b['n'], ' bachche</div></div>',
             $done ? '<span class="tag green">Lag gayi</span>' : '<a class="btn sm" href="?p=att&batch=' . (int)$b['id'] . '">Haaziri lagaiye</a>', '</div>';
    }
    if (!$bs) echo '<p class="note">', $owner ? 'Abhi koi batch nahi.' : 'Aapko abhi koi batch nahi diya gaya. Owner se kahiye.', '</p>';
    echo '</div>';
    if ($owner) {
        echo '<div class="btns"><a class="btn" href="?p=add">+ Naya bachcha</a><a class="btn ghost" href="?p=enq">+ Enquiry</a>',
             '<a class="btn ghost" href="site.php?c=', h($C['slug']), '" target="_blank">Meri website</a></div>';
    }
    break;

// ----------------------------------------------------------------
case 'students':
    $q = cs_get('q'); $show = cs_get('show') === 'old' && $owner ? 'archived' : 'active';
    $sql = "SELECT DISTINCT s.* FROM cs_student s";
    $par = [];
    if (!$owner) { $sql .= " JOIN cs_enroll e ON e.student_id=s.id AND e.coaching_id=s.coaching_id AND e.status='active'
                             JOIN cs_batch b ON b.id=e.batch_id AND b.coaching_id=s.coaching_id AND b.teacher_id=?"; $par[] = $uid; }
    $sql .= ' WHERE s.coaching_id=? AND s.status=?'; $par[] = $cid; $par[] = $show;
    if ($q !== '') { $sql .= ' AND (s.naam LIKE ? OR s.mobile LIKE ? OR s.father LIKE ?)'; $lk = '%' . $q . '%'; array_push($par, $lk, $lk, $lk); }
    $list = cs_all($sql . ' ORDER BY s.naam LIMIT 500', $par);
    echo '<h1>Bachche</h1>';
    if ($owner && $show === 'active' && count($list) > CS_SOFT_LIMIT)
        echo '<div class="flash bad">', CS_SOFT_LIMIT, ' se zyada bachche ho gaye. Bade plan ke liye Maakit se baat kariye.</div>';
    echo '<form class="search"><input type="hidden" name="p" value="students"><input name="q" value="', h($q), '" placeholder="Naam ya mobile se dhoondhiye"></form>';
    if ($owner) echo '<div class="btns" style="margin:0 0 12px"><a class="btn sm" href="?p=add">+ Naya bachcha</a>',
                     $show === 'active' ? '<a class="btn sm ghost" href="?p=students&show=old">Purane (archive)</a>' : '<a class="btn sm ghost" href="?p=students">Active</a>', '</div>';
    echo '<div class="card">';
    foreach ($list as $s) {
        $b = $bal[(int)$s['id']] ?? 0;
        echo '<a class="row" style="text-decoration:none;color:inherit" href="?p=student&id=', (int)$s['id'], '"><div class="grow"><b>', h($s['naam']), '</b><div class="sub">',
             h($s['cls']), $s['father'] !== '' ? ' · ' . h($s['father']) : '', ' · ', h($s['mobile']), '</div></div>',
             $owner ? ($b > 0 ? '<span class="amt due">' . rs($b) . '</span>' : ($b < 0 ? '<span class="amt adv">+' . rs(-$b) . '</span>' : '<span class="tag green">Clear</span>')) : '', '</a>';
    }
    if (!$list) echo '<p class="note">Koi bachcha nahi mila.</p>';
    echo '</div>';
    break;

// ----------------------------------------------------------------
case 'add':
    need_owner();
    $bs = my_batches();
    echo '<h1>Naya bachcha</h1>';
    if (!$bs) { echo '<div class="card">Pehle <a href="?p=courses">course aur batch</a> banaiye.</div>'; break; }
    if (($fb = (int)cs_get('full')) && ($b = batch_ok($fb))) echo '<div class="flash bad">', h($b['naam']), ' ki seat bhar gayi hai (', (int)$b['seat_limit'], '). Phir bhi jodna hai to neeche "seat se zyada" chuniye.</div>';
    echo '<form method="post" class="card">', cs_csrf_field(), '<input type="hidden" name="a" value="student">',
         '<label>Bachche ka naam</label><input name="naam" required maxlength="80">',
         '<label>Pita / parent ka naam</label><input name="father" maxlength="80">',
         '<label>Parent ka mobile (isi se app login hoga)</label><input name="mobile" inputmode="numeric" required maxlength="13">',
         '<label>Class</label><input name="cls" maxlength="30" placeholder="jaise 10th">',
         '<label>Batch</label><select name="batch" id="bt" required>';
    foreach ($bs as $b) echo '<option value="', (int)$b['id'], '" data-fee="', (int)$b['amount'], '">', h($b['naam']), ' — ', h($b['course']), ' (', rs($b['amount']), ' ', h(CS_FEE_TYPES[$b['fee_type']] ?? ''), ')</option>';
    echo '</select><div class="two"><div><label>Is bachche ki fees (₹)</label><input name="fee" id="fee" inputmode="numeric" value="', (int)$bs[0]['amount'], '"></div>',
         '<div><label>Admission ki tareekh</label><input type="date" name="join" value="', $today, '" max="', $today, '"></div></div>',
         '<label>Discount ka kaaran (agar fees kam ki)</label><input name="fee_note" maxlength="120" placeholder="jaise: bhai bhi padhta hai">',
         '<label style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="over" value="1" style="width:auto"> Seat se zyada ho to bhi jodo</label>',
         '<p class="note">Fees 0 likhenge to is bachche par koi fees nahi judegi.</p>',
         '<button class="btn full">Save kariye</button></form>',
         '<script>document.getElementById("bt").onchange=function(){document.getElementById("fee").value=this.selectedOptions[0].dataset.fee}</script>';
    break;

// ----------------------------------------------------------------
case 'student':
    $sid = (int)cs_get('id');
    $S = student_ok($sid);
    if (!$S) { echo '<div class="card">Ye bachcha nahi mila.</div>'; break; }
    $en = cs_all("SELECT e.*, b.naam batch, b.timing, c.naam course, c.fee_type FROM cs_enroll e
                  JOIN cs_batch b ON b.id=e.batch_id AND b.coaching_id=e.coaching_id JOIN cs_course c ON c.id=b.course_id AND c.coaching_id=e.coaching_id
                  WHERE e.student_id=? AND e.coaching_id=? ORDER BY e.status, e.id DESC", [$sid, $cid]);
    $att = cs_row("SELECT SUM(status='P') p, SUM(status='A') a FROM cs_attendance WHERE student_id=? AND coaching_id=? AND d>=CURDATE()-INTERVAL 30 DAY", [$sid, $cid]);
    echo '<h1>', h($S['naam']), $S['status'] !== 'active' ? ' <span class="tag red">Archive</span>' : '', '</h1>';
    echo '<div class="card"><div class="sub">', h($S['cls']), ' · Pita: ', h($S['father'] ?: '—'), ' · ', h($S['mobile']), ' · Admission: ', h(date('d M Y', strtotime($S['join_date']))), '</div>',
         '<div class="sub">Pichhle 30 din: ', (int)($att['p'] ?? 0), ' din aaya, ', (int)($att['a'] ?? 0), ' din absent</div>',
         '<div class="btns"><a class="btn sm ghost" href="tel:', h($S['mobile']), '">', cs_icon('phone'), ' Call</a></div></div>';
    if (!$owner) {
        echo '<div class="card">';
        foreach ($en as $e) if ($e['status'] === 'active') echo '<div class="row"><div class="grow">', h($e['batch']), '<div class="sub">', h($e['timing']), '</div></div></div>';
        echo '</div>';
        break;
    }
    $b = cs_balance($cid, $sid);
    if (($rid = (int)cs_get('r')) && ($r = cs_row('SELECT * FROM cs_receipt WHERE id=? AND coaching_id=?', [$rid, $cid]))) {
        $link = cs_base() . '/receipt.php?t=' . $r['token'];
        echo '<div class="card"><b>Receipt #', (int)$r['no'], ' ban gayi — ', rs($r['amount']), '</b><div class="btns">',
             '<a class="btn sm" href="receipt.php?t=', h($r['token']), '" target="_blank">Receipt dekhiye / print</a>',
             '<a class="btn sm wa" target="_blank" href="', h(cs_wa($S['mobile'], "{$C['naam']}: {$S['naam']} ki fees " . rs($r['amount']) . " mili. Receipt #{$r['no']}: $link")), '">WhatsApp par bhejiye</a></div></div>';
    }
    echo '<div class="card"><div class="row"><div class="grow"><b>Fees ka khata</b><div class="sub">', $b > 0 ? 'Baaki hai' : ($b < 0 ? 'Advance jama hai' : 'Sab clear'), '</div></div>',
         '<span class="amt ', $b > 0 ? 'due' : 'adv', '" style="font-size:22px">', rs(abs($b)), '</span></div>';
    $nonce = bin2hex(random_bytes(8));
    echo '<form method="post">', cs_csrf_field(), '<input type="hidden" name="a" value="pay"><input type="hidden" name="sid" value="', $sid, '"><input type="hidden" name="nonce" value="', $nonce, '">',
         '<div class="two"><div><label>Paisa mila (₹)</label><input name="amount" inputmode="numeric" required value="', $b > 0 ? $b : '', '"></div>',
         '<div><label>Kaise</label><select name="mode"><option value="cash">Cash</option><option value="upi">UPI</option></select></div></div>',
         '<label>Tareekh</label><input type="date" name="d" value="', $today, '" max="', $today, '">',
         '<button class="btn full">Paisa likhiye + receipt</button></form>';
    echo '<div class="btns">';
    if ($b > 0) echo '<a class="btn sm wa" target="_blank" href="', h($wa_due($S, $b)), '">Fees yaad dilaiye (WhatsApp)</a>';
    echo '</div>';
    if ($b > 0) {
        echo '<details style="margin-top:12px"><summary class="note">Maaf karo (baaki zero)</summary><form method="post">', cs_csrf_field(),
             '<input type="hidden" name="a" value="waive"><input type="hidden" name="sid" value="', $sid, '">',
             '<label>Kitna maaf (khaali = poora ', rs($b), ')</label><input name="amount" inputmode="numeric">',
             '<label>Kaaran (zaroori)</label><input name="why" required maxlength="160" placeholder="jaise: ghar ki majboori">',
             '<button class="btn sm red" style="margin-top:8px">Maaf kariye</button></form></details>';
    }
    echo '<details style="margin-top:8px"><summary class="note">Galti sudhaar (entry jodiye / ghataiye)</summary><form method="post">', cs_csrf_field(),
         '<input type="hidden" name="a" value="fix"><input type="hidden" name="sid" value="', $sid, '">',
         '<label>Rakam (+ baaki badhao, − baaki ghatao)</label><input name="amount" inputmode="numeric" required placeholder="jaise 200 ya -200">',
         '<label>Kaaran (zaroori)</label><input name="why" required maxlength="160"><button class="btn sm" style="margin-top:8px">Save</button></form></details></div>';

    $led = cs_all('SELECT l.*, r.token, r.cancelled FROM cs_ledger l LEFT JOIN cs_receipt r ON r.id=l.receipt_id AND r.coaching_id=l.coaching_id
                   WHERE l.student_id=? AND l.coaching_id=? ORDER BY COALESCE(CONCAT(l.month,"-01"), DATE(l.created_at)), l.id', [$sid, $cid]);
    echo '<div class="card"><b>Hisaab</b><table><tr><th>Kya</th><th class="r">Fees</th><th class="r">Jama/Maaf</th><th class="r">Baaki</th></tr>';
    $run = 0;
    foreach ($led as $l) {
        $plus = in_array($l['kind'], ['due', 'fix'], true);
        $run += $plus ? (int)$l['amount'] : -(int)$l['amount'];
        $note = h($l['note']);
        if ($l['kind'] === 'pay' && $l['token']) $note = '<a href="receipt.php?t=' . h($l['token']) . '" target="_blank">' . $note . '</a>' . ($l['cancelled'] ? ' <span class="tag red">radd</span>' : '');
        echo '<tr><td>', $note, '<div class="sub">', h(date('d M y', strtotime($l['created_at']))), '</div></td>',
             '<td class="r">', $plus ? rs($l['amount']) : '', '</td><td class="r">', $plus ? '' : rs($l['amount']), '</td><td class="r">', rs($run), '</td></tr>';
    }
    echo '</table></div>';

    $acc = cs_access($S);
    echo '<div class="card"><b>Student app</b><div class="sub">';
    echo $acc['why'] === 'paid' ? 'Chalu — ' . h(date('d M Y', strtotime($acc['till']))) . ' tak'
       : ($acc['why'] === 'trial' ? 'Free trial — ' . h(date('d M Y', strtotime($acc['till']))) . ' tak'
       : ($acc['why'] === 'free' ? 'Chalu (payment abhi shuru nahi hua)' : 'Band — ₹' . cs_plans()['6m']['amount'] . ' bharne par khulega'));
    echo '</div>';
    if (cs_pay_ready()) echo '<div class="btns"><a class="btn sm ghost" href="pay.php?sid=', $sid, '">Aap khud bhariye (gift)</a></div>';
    echo '</div>';
    $pacc = cs_account($S['mobile']);
    echo '<div class="card"><b>Parent ka login</b><div class="sub">', h($S['mobile']), ' · ',
         !$pacc ? 'Abhi password nahi diya' : ((int)$pacc['temp'] === 1 ? 'Pehla password diya, abhi badla nahi' : 'Login chalu hai'), '</div>',
         '<form method="post">', cs_csrf_field(), '<input type="hidden" name="a" value="login_send"><input type="hidden" name="who" value="parent"><input type="hidden" name="sid" value="', $sid, '">',
         '<button class="btn sm" style="margin-top:8px"', $pacc && (int)$pacc['temp'] === 0 ? ' onclick="return confirm(\'Naya password banega, purana band ho jayega. Theek?\')"' : '', '>',
         $pacc && (int)$pacc['temp'] === 0 ? 'Naya password bhejiye (bhool gaye ho to)' : 'Login bhejiye (WhatsApp)', '</button></form></div>';

    echo '<div class="card"><b>Batch</b>';
    foreach ($en as $e) {
        echo '<div class="row"><div class="grow">', h($e['batch']), ' — ', h($e['course']), '<div class="sub">Fees ', rs($e['fee']), ' ', h(CS_FEE_TYPES[$e['fee_type']] ?? ''),
             $e['fee_note'] !== '' ? ' · ' . h($e['fee_note']) : '', ' · ', h(date('d M y', strtotime($e['start_date']))), ' se</div></div>';
        if ($e['status'] === 'active') echo '<form method="post">', cs_csrf_field(), '<input type="hidden" name="a" value="unenroll"><input type="hidden" name="sid" value="', $sid, '"><input type="hidden" name="eid" value="', (int)$e['id'],
             '"><button class="btn sm ghost" onclick="return confirm(\'Is batch se hataayein? Aage ki fees nahi judegi.\')">Hataiye</button></form>';
        else echo '<span class="tag">Khatam</span>';
        echo '</div>';
    }
    echo '<details><summary class="note">Doosre batch me bhi jodiye</summary><form method="post">', cs_csrf_field(), '<input type="hidden" name="a" value="enroll"><input type="hidden" name="sid" value="', $sid, '"><label>Batch</label><select name="batch">';
    foreach (my_batches() as $bb) echo '<option value="', (int)$bb['id'], '">', h($bb['naam']), ' (', rs($bb['amount']), ')</option>';
    echo '</select><div class="two"><div><label>Fees (khaali = course ki)</label><input name="fee" inputmode="numeric"></div><div><label>Kab se</label><input type="date" name="start" value="', $today, '"></div></div>',
         '<label>Discount ka kaaran</label><input name="fee_note" maxlength="120"><button class="btn sm" style="margin-top:8px">Jodiye</button></form></details></div>';

    echo '<details class="card"><summary><b>Details badlein</b></summary><form method="post">', cs_csrf_field(), '<input type="hidden" name="a" value="edit"><input type="hidden" name="sid" value="', $sid, '">',
         '<label>Naam</label><input name="naam" value="', h($S['naam']), '" required><label>Pita</label><input name="father" value="', h($S['father']), '">',
         '<label>Mobile</label><input name="mobile" value="', h($S['mobile']), '" required><label>Class</label><input name="cls" value="', h($S['cls']), '">',
         '<button class="btn sm" style="margin-top:8px">Save</button></form></details>';
    echo '<form method="post">', cs_csrf_field(), '<input type="hidden" name="a" value="status"><input type="hidden" name="sid" value="', $sid, '"><button class="btn ghost full">',
         $S['status'] === 'active' ? 'Coaching chhod diya (archive)' : 'Wapas active kariye', '</button></form>',
         '<p class="note">Archive bachche ginti aur fees me nahi aate. Unka hisaab mitta nahi.</p>';
    break;

// ----------------------------------------------------------------
case 'att':
    $bs = my_batches();
    $bid = (int)cs_get('batch'); $d = cs_date(cs_get('d')) ?: $today;
    if (!$bid && count($bs) === 1) $bid = (int)$bs[0]['id'];
    $B = $bid ? batch_ok($bid) : null;
    echo '<h1>Haaziri</h1>';
    if (!$B) {
        echo '<div class="card">';
        foreach ($bs as $b) echo '<a class="row" style="text-decoration:none;color:inherit" href="?p=att&batch=', (int)$b['id'], '"><div class="grow"><b>', h($b['naam']), '</b><div class="sub">', h($b['timing']), ' · ', (int)$b['n'], ' bachche</div></div>›</a>';
        if (!$bs) echo '<p class="note">Koi batch nahi.</p>';
        echo '</div>';
        break;
    }
    $list = cs_all("SELECT s.id, s.naam, s.mobile, a.status FROM cs_enroll e JOIN cs_student s ON s.id=e.student_id AND s.coaching_id=e.coaching_id
                    LEFT JOIN cs_attendance a ON a.batch_id=e.batch_id AND a.student_id=s.id AND a.d=?
                    WHERE e.batch_id=? AND e.coaching_id=? AND e.status='active' AND s.status='active' ORDER BY s.naam", [$d, $bid, $cid]);
    echo '<form class="card" style="display:flex;gap:8px;align-items:end"><input type="hidden" name="p" value="att"><input type="hidden" name="batch" value="', $bid, '">',
         '<div class="grow" style="flex:1"><label style="margin-top:0">', h($B['naam']), '</label><input type="date" name="d" value="', $d, '" max="', $today, '" onchange="this.form.submit()"></div></form>';
    if (cs_get('done')) {
        $abs = array_filter($list, fn($s) => $s['status'] === 'A');
        echo '<div class="card"><b>Absent bachchon ke parent ko bataiye</b>';
        foreach ($abs as $s)
            echo '<div class="row"><div class="grow">', h($s['naam']), '</div><a class="btn sm wa" target="_blank" href="',
                 h(cs_wa($s['mobile'], "Namaste, {$C['naam']} se: {$s['naam']} aaj (" . date('d M', strtotime($d)) . ") {$B['naam']} me nahi aaya/aayi. Kripya dhyan dein.")), '">WhatsApp</a></div>';
        if (!$abs) echo '<p class="note">Aaj sab aaye. Badhiya!</p>';
        echo '<p class="note">Student app me bhi parent ko absent dikh jata hai.</p></div>';
    }
    echo '<form method="post" class="card">', cs_csrf_field(), '<input type="hidden" name="a" value="att"><input type="hidden" name="batch" value="', $bid, '"><input type="hidden" name="d" value="', $d, '">',
         '<div class="btns noprint" style="margin:0 0 6px"><button type="button" class="btn sm ghost" onclick="document.querySelectorAll(\'.pa input[value=P]\').forEach(i=>i.checked=true)">Sab aaye</button></div>';
    foreach ($list as $s) {
        $sid = (int)$s['id'];
        echo '<div class="row"><div class="grow">', h($s['naam']), '</div><div class="pa">',
             '<label><input type="radio" name="s[', $sid, ']" value="P"', $s['status'] === 'P' ? ' checked' : '', '><span class="p">P</span></label>',
             '<label><input type="radio" name="s[', $sid, ']" value="A"', $s['status'] === 'A' ? ' checked' : '', '><span class="a">A</span></label></div></div>';
    }
    if (!$list) echo '<p class="note">Is batch me koi bachcha nahi.</p>';
    else echo '<button class="btn full">Haaziri save kariye</button>';
    echo '</form>';
    break;

// ----------------------------------------------------------------
case 'dues':
    need_owner();
    $rows = cs_all("SELECT * FROM cs_student WHERE coaching_id=? AND status='active' ORDER BY naam", [$cid]);
    $rows = array_values(array_filter($rows, fn($s) => ($bal[(int)$s['id']] ?? 0) > 0));
    usort($rows, fn($x, $y) => $bal[(int)$y['id']] <=> $bal[(int)$x['id']]);
    $tot = array_sum(array_map(fn($s) => $bal[(int)$s['id']], $rows));
    echo '<h1>Baaki fees — ', rs($tot), '</h1><div class="btns" style="margin:0 0 12px"><a class="btn sm ghost" href="?p=receipts">Receipts</a><a class="btn sm ghost" href="?p=report">Saal ki report</a></div><div class="card">';
    foreach ($rows as $s) {
        $b = $bal[(int)$s['id']];
        echo '<div class="row"><a class="grow" style="text-decoration:none;color:inherit" href="?p=student&id=', (int)$s['id'], '"><b>', h($s['naam']), '</b><div class="sub">', h($s['mobile']), '</div></a>',
             '<span class="amt due">', rs($b), '</span><a class="btn sm wa" target="_blank" href="', h($wa_due($s, $b)), '">WA</a></div>';
    }
    if (!$rows) echo '<p class="note">Kisi ki fees baaki nahi. 👍</p>';
    echo '</div>';
    break;

// ----------------------------------------------------------------
case 'risk':
    $abs = cs_absent3($cid); $late = $owner ? cs_fee_late($cid) : [];
    $ids = array_unique(array_merge($abs, array_keys($late)));
    $ids = array_values(array_filter($ids, fn($i) => student_ok((int)$i) !== null));
    echo '<h1>Khatre me bachche</h1><p class="note">3 din lagatar absent', $owner ? ', ya fees 15 din se zyada baaki' : '', '. Inse aaj baat kariye — yahi bachche chhod kar jaate hain.</p><div class="card">';
    foreach ($ids as $i) {
        $s = student_ok((int)$i);
        echo '<div class="row"><a class="grow" style="text-decoration:none;color:inherit" href="?p=student&id=', (int)$i, '"><b>', h($s['naam']), '</b><div class="sub">',
             in_array($i, $abs, true) ? '<span class="tag red">3 din se nahi aaya</span> ' : '', isset($late[$i]) ? '<span class="tag red">Fees ' . rs($late[$i]) . ' 15 din+</span>' : '', '</div></a>',
             '<a class="btn sm ghost" href="tel:', h($s['mobile']), '">Call</a></div>';
    }
    if (!$ids) echo '<p class="note">Abhi koi khatre me nahi.</p>';
    echo '</div>';
    break;

// ----------------------------------------------------------------
case 'enq':
    need_owner();
    $rows = cs_all("SELECT * FROM cs_enquiry WHERE coaching_id=? ORDER BY status='open' DESC, follow_up IS NULL, follow_up, id DESC LIMIT 300", [$cid]);
    echo '<h1>Enquiry</h1><details class="card"', $rows ? '' : ' open', '><summary><b>+ Nayi enquiry</b></summary><form method="post">', cs_csrf_field(), '<input type="hidden" name="a" value="enq">',
         '<label>Naam</label><input name="naam" required maxlength="80"><label>Mobile</label><input name="mobile" inputmode="numeric" required maxlength="13">',
         '<label>Kis course ke liye</label><input name="interest" maxlength="120"><label>Kab call karna hai</label><input type="date" name="follow" value="', date('Y-m-d', strtotime('+1 day')), '">',
         '<label>Note</label><input name="note" maxlength="255"><button class="btn full">Save</button></form></details>';
    foreach ($rows as $r) {
        $dueNow = $r['status'] === 'open' && $r['follow_up'] && $r['follow_up'] <= $today;
        echo '<details class="card"><summary><b>', h($r['naam']), '</b> ', $r['status'] === 'open' ? ($dueNow ? '<span class="tag red">Aaj call</span>' : '<span class="tag">' . h($r['follow_up'] ? date('d M', strtotime($r['follow_up'])) : '') . '</span>') : '<span class="tag green">' . ($r['status'] === 'joined' ? 'Jud gaya' : 'Band') . '</span>',
             $r['source'] === 'web' ? ' <span class="tag">Website</span>' : '', '<div class="sub">', h($r['interest']), ' · ', h($r['mobile']), '</div></summary>',
             '<div class="btns"><a class="btn sm ghost" href="tel:', h($r['mobile']), '">Call</a><a class="btn sm wa" target="_blank" href="', h(cs_wa($r['mobile'], "Namaste {$r['naam']}, {$C['naam']} se baat kar rahe hain. Aapne {$r['interest']} ke baare me poochha tha. Kab aa sakte hain?")), '">WhatsApp</a></div>',
             '<form method="post">', cs_csrf_field(), '<input type="hidden" name="a" value="enq_upd"><input type="hidden" name="id" value="', (int)$r['id'], '">',
             '<div class="two"><div><label>Haal</label><select name="status">';
        foreach (['open' => 'Baat chal rahi', 'joined' => 'Jud gaya', 'closed' => 'Nahi aayega'] as $k => $v) echo '<option value="', $k, '"', $r['status'] === $k ? ' selected' : '', '>', $v, '</option>';
        echo '</select></div><div><label>Agli call</label><input type="date" name="follow" value="', h($r['follow_up']), '"></div></div><label>Note</label><input name="note" value="', h($r['note']), '" maxlength="255">',
             '<button class="btn sm" style="margin-top:8px">Save</button></form></details>';
    }
    break;

// ----------------------------------------------------------------
case 'receipts':
    need_owner();
    $rows = cs_all('SELECT r.*, s.naam FROM cs_receipt r JOIN cs_student s ON s.id=r.student_id AND s.coaching_id=r.coaching_id WHERE r.coaching_id=? ORDER BY r.no DESC LIMIT 200', [$cid]);
    echo '<h1>Receipts</h1><div class="card">';
    foreach ($rows as $r) {
        echo '<details class="row" style="display:block"><summary style="display:flex;gap:10px"><span class="grow" style="flex:1">#', (int)$r['no'], ' ', h($r['naam']), '<div class="sub">', h(date('d M Y', strtotime($r['paid_on']))), ' · ', $r['mode'] === 'upi' ? 'UPI' : 'Cash', '</div></span>',
             '<span class="amt">', rs($r['amount']), '</span>', $r['cancelled'] ? ' <span class="tag red">radd</span>' : '', '</summary>',
             '<div class="btns"><a class="btn sm ghost" href="receipt.php?t=', h($r['token']), '" target="_blank">Dekhiye</a></div>';
        if (!$r['cancelled']) echo '<form method="post">', cs_csrf_field(), '<input type="hidden" name="a" value="cancel_receipt"><input type="hidden" name="rid" value="', (int)$r['id'], '">',
             '<label>Radd karne ka kaaran</label><input name="why" required maxlength="160"><button class="btn sm red" style="margin-top:8px" onclick="return confirm(\'Receipt radd karein? Paisa wapas baaki me judega.\')">Radd kariye</button></form>';
        else echo '<p class="note">Kaaran: ', h($r['cancel_reason']), '</p>';
        echo '</details>';
    }
    if (!$rows) echo '<p class="note">Abhi koi receipt nahi.</p>';
    echo '</div>';
    break;

// ----------------------------------------------------------------
case 'report':
    need_owner();
    $y = (int)(cs_get('y') ?: date('Y'));
    $r = cs_row("SELECT SUM(CASE WHEN kind='due' THEN amount ELSE 0 END) due, SUM(CASE WHEN kind='pay' THEN amount ELSE 0 END) pay,
                        SUM(CASE WHEN kind='waive' THEN amount ELSE 0 END) waive FROM cs_ledger WHERE coaching_id=? AND YEAR(created_at)=?", [$cid, $y]);
    $disc = (int)cs_val("SELECT COALESCE(SUM(c.amount - e.fee),0) FROM cs_enroll e JOIN cs_batch b ON b.id=e.batch_id AND b.coaching_id=e.coaching_id
                         JOIN cs_course c ON c.id=b.course_id AND c.coaching_id=e.coaching_id WHERE e.coaching_id=? AND e.status='active' AND c.amount>e.fee", [$cid]);
    $w = cs_all("SELECT l.amount, l.note, l.created_at, s.naam FROM cs_ledger l JOIN cs_student s ON s.id=l.student_id AND s.coaching_id=l.coaching_id
                 WHERE l.coaching_id=? AND l.kind='waive' AND YEAR(l.created_at)=? ORDER BY l.id DESC", [$cid, $y]);
    echo '<h1>Saal ', $y, ' ki report</h1><div class="grid"><div class="kpi"><small>Fees judi</small><strong>', rs($r['due'] ?? 0), '</strong></div>',
         '<div class="kpi"><small>Paisa mila</small><strong>', rs($r['pay'] ?? 0), '</strong></div><div class="kpi red"><small>Maaf kiya</small><strong>', rs($r['waive'] ?? 0), '</strong></div>',
         '<div class="kpi"><small>Har mahine ka discount (abhi)</small><strong>', rs($disc), '</strong></div></div><div class="card"><b>Maafi ki list</b>';
    foreach ($w as $x) echo '<div class="row"><div class="grow">', h($x['naam']), '<div class="sub">', h($x['note']), ' · ', h(date('d M', strtotime($x['created_at']))), '</div></div><span class="amt">', rs($x['amount']), '</span></div>';
    if (!$w) echo '<p class="note">Kuch maaf nahi kiya.</p>';
    echo '</div><div class="btns"><a class="btn sm ghost" href="?p=report&y=', $y - 1, '">', $y - 1, '</a><button class="btn sm ghost" onclick="print()">Print</button></div>';
    break;

// ----------------------------------------------------------------
case 'courses':
    need_owner();
    $cs = cs_all('SELECT * FROM cs_course WHERE coaching_id=? AND active=1 ORDER BY naam', [$cid]);
    $ts = cs_all("SELECT * FROM cs_user WHERE coaching_id=? AND active=1 ORDER BY role='owner' DESC, naam", [$cid]);
    if (cs_get('new')) echo '<div class="flash">Coaching ban gayi! Ab apne course aur unki fees likhiye.</div>';
    echo '<h1>Course aur batch</h1><div class="card"><b>Course (fees ke saath)</b>';
    foreach ($cs as $c) echo '<div class="row"><div class="grow">', h($c['naam']), '<div class="sub">', h(CS_FEE_TYPES[$c['fee_type']] ?? ''), $c['months'] ? ' · ' . (int)$c['months'] . ' mahine' : '', '</div></div><span class="amt">', rs($c['amount']), '</span></div>';
    echo '<details', $cs ? '' : ' open', '><summary class="note">+ Naya course</summary><form method="post">', cs_csrf_field(), '<input type="hidden" name="a" value="course">',
         '<label>Course ka naam</label><input name="naam" required maxlength="80" placeholder="jaise: Class 10 Maths">',
         '<label>Fees kaise</label><select name="fee_type">';
    foreach (CS_FEE_TYPES as $k => $v) echo '<option value="', $k, '">', h($v), '</option>';
    echo '</select><div class="two"><div><label>Fees (₹)</label><input name="amount" inputmode="numeric" required></div><div><label>Kitne mahine ka (khaali = chalta rahe)</label><input name="months" inputmode="numeric"></div></div>',
         '<button class="btn full">Course save kariye</button></form></details></div>';
    $bs = my_batches();
    echo '<div class="card"><b>Batch</b>';
    foreach ($bs as $b) echo '<div class="row"><div class="grow">', h($b['naam']), '<div class="sub">', h($b['course']), ' · ', h($b['timing']), ' · ', h($b['teacher'] ?: 'teacher nahi'), ' · ', (int)$b['n'], $b['seat_limit'] ? '/' . (int)$b['seat_limit'] : '', ' bachche</div></div>',
         '<form method="post">', cs_csrf_field(), '<input type="hidden" name="a" value="batch_off"><input type="hidden" name="id" value="', (int)$b['id'], '"><button class="btn sm ghost" onclick="return confirm(\'Batch band karein?\')">Band</button></form></div>';
    if ($cs) {
        echo '<details', $bs ? '' : ' open', '><summary class="note">+ Naya batch</summary><form method="post">', cs_csrf_field(), '<input type="hidden" name="a" value="batch">',
             '<label>Batch ka naam</label><input name="naam" required maxlength="80" placeholder="jaise: 10th Shaam 5 baje"><label>Course</label><select name="course">';
        foreach ($cs as $c) echo '<option value="', (int)$c['id'], '">', h($c['naam']), '</option>';
        echo '</select><div class="two"><div><label>Samay</label><input name="timing" maxlength="60" placeholder="5–6 PM"></div><div><label>Seat (khaali = koi limit nahi)</label><input name="seats" inputmode="numeric"></div></div><label>Teacher</label><select name="teacher"><option value="">—</option>';
        foreach ($ts as $t) echo '<option value="', (int)$t['id'], '">', h($t['naam']), $t['role'] === 'owner' ? ' (aap)' : '', '</option>';
        echo '</select><button class="btn full">Batch save kariye</button></form></details>';
    } else echo '<p class="note">Pehle ek course banaiye.</p>';
    echo '</div>';
    break;

// ----------------------------------------------------------------
case 'team':
    need_owner();
    $ts = cs_all("SELECT * FROM cs_user WHERE coaching_id=? AND role='teacher' ORDER BY active DESC, naam", [$cid]);
    echo '<h1>Teachers</h1><div class="card">';
    foreach ($ts as $t) echo '<div class="row"><div class="grow">', h($t['naam']), '<div class="sub">', h($t['mobile']), ' · ', ($ta = cs_account($t['mobile'])) ? ((int)$ta['temp'] ? 'password diya' : 'login chalu') : 'password nahi diya', '</div></div>',
         $t['active'] ? '<form method="post">' . cs_csrf_field() . '<input type="hidden" name="a" value="login_send"><input type="hidden" name="who" value="teacher"><input type="hidden" name="id" value="' . (int)$t['id'] . '"><button class="btn sm">Login bhejiye</button></form>' : '',
         '<form method="post">', cs_csrf_field(), '<input type="hidden" name="a" value="teacher_off"><input type="hidden" name="id" value="', (int)$t['id'], '"><button class="btn sm ', $t['active'] ? 'ghost' : '', '">', $t['active'] ? 'Band kariye' : 'Chalu kariye', '</button></form></div>';
    if (!$ts) echo '<p class="note">Abhi koi teacher nahi.</p>';
    echo '</div><form method="post" class="card">', cs_csrf_field(), '<input type="hidden" name="a" value="teacher"><b>+ Teacher jodiye</b>',
         '<label>Naam</label><input name="naam" required maxlength="80"><label>Mobile (isi se login karega)</label><input name="mobile" inputmode="numeric" required maxlength="13">',
         '<button class="btn full">Jodiye</button><p class="note">Teacher sirf apne batch ki haaziri aur bachche dekhega. Fees aur hisaab nahi.</p></form>';
    break;

// ----------------------------------------------------------------
case 'apps':
    need_owner();
    $rows = cs_all("SELECT * FROM cs_student WHERE coaching_id=? AND status='active' ORDER BY naam", [$cid]);
    $plans = cs_plans();
    echo '<h1>Student app — kaun chalu, kaun band</h1>';
    if (!cs_pay_ready()) echo '<div class="flash">Abhi sabka app free chal raha hai. Payment chalu hone par 7 din trial ke baad ', rs($plans['6m']['amount']), ' / 6 mahine lagega.</div>';
    echo '<div class="card">';
    foreach ($rows as $s) {
        $a = cs_access($s);
        $tag = $a['why'] === 'paid' ? '<span class="tag green">Paid · ' . h(date('d M', strtotime($a['till']))) . '</span>'
             : ($a['why'] === 'trial' ? '<span class="tag">Trial · ' . h(date('d M', strtotime($a['till']))) . '</span>'
             : ($a['open'] ? '<span class="tag green">Chalu</span>' : '<span class="tag red">Band</span>'));
        echo '<div class="row"><div class="grow">', h($s['naam']), '<div class="sub">', $tag, '</div></div>';
        if (!$a['open'] || $a['why'] === 'trial')
            echo '<a class="btn sm wa" target="_blank" href="', h(cs_wa($s['mobile'], "Namaste, {$C['naam']} ka app chalu karne ke liye " . rs($plans['6m']['amount']) . " (6 mahine) bhariye: " . cs_base() . '/')), '">WA</a>';
        echo '</div>';
    }
    echo '</div>';
    break;

// ----------------------------------------------------------------
case 'setup':
    need_owner();
    $link = cs_base() . '/s/' . $C['slug'];
    echo '<h1>Coaching ki details</h1><div class="card"><b>Aapki website</b><p><a href="', h($link), '" target="_blank">', h($link), '</a></p>',
         '<div class="btns"><a class="btn sm wa" target="_blank" href="', h('https://wa.me/?text=' . rawurlencode($C['naam'] . ' — admission ke liye dekhiye: ' . $link)), '">Share kariye</a></div></div>';
    echo '<form method="post" enctype="multipart/form-data" class="card">', cs_csrf_field(), '<input type="hidden" name="a" value="logo"><div class="row"><div>', cs_logo_html($C), '</div><div class="grow"><b>Logo</b><div class="sub">JPG / PNG — app, website aur receipt par dikhega</div></div></div>',
         '<input type="file" name="logo" accept="image/jpeg,image/png" required><button class="btn sm" style="margin-top:8px">Logo lagaiye</button></form>';
    if (cs_get('taken')) echo '<div class="flash bad">Ye website naam pehle se kisi ka hai. Doosra likhiye.</div>';
    echo '<form method="post" class="card">', cs_csrf_field(), '<input type="hidden" name="a" value="setup">',
         '<label>Coaching ka naam</label><input name="naam" required value="', h($C['naam']), '" maxlength="120"><label>Kis tarah ki</label><select name="ctype">';
    foreach (CS_TYPES as $k => $v) echo '<option value="', $k, '"', $C['ctype'] === $k ? ' selected' : '', '>', h($v), '</option>';
    echo '</select><div class="two"><div><label>Phone (website par dikhega)</label><input name="phone" value="', h($C['phone']), '" required></div><div><label>Shehar</label><input name="city" value="', h($C['city']), '"></div></div>',
         '<label>Pata</label><input name="address" value="', h($C['address']), '" maxlength="255">',
         '<label>UP coaching registration no.</label><input name="reg" value="', h($C['reg_no']), '" maxlength="60">',
         '<label>Samay</label><input name="timing" value="', h($C['timing']), '" maxlength="120" placeholder="Subah 7 se shaam 8">',
         '<label>Coaching ke baare me (website par)</label><textarea name="about" maxlength="2000">', h($C['about']), '</textarea>',
         '<div class="two"><div><label>Rang</label><input type="color" name="color" value="', h($C['color']), '" style="height:46px"></div><div><label>Website ka naam</label><input name="slug" value="', h($C['slug']), '" pattern="[a-z0-9-]{3,40}" required></div></div>',
         '<button class="btn full">Save kariye</button></form>';
    break;

// ----------------------------------------------------------------
case 'more':
default:
    echo '<h1>Aur</h1><div class="card menu">';
    if ($owner) {
        foreach (['add' => '+ Naya bachcha', 'att' => 'Haaziri', 'enq' => 'Enquiry / follow-up', 'receipts' => 'Receipts', 'report' => 'Saal ki report (maafi, discount)',
                  'courses' => 'Course aur batch', 'team' => 'Teachers', 'apps' => 'Student app — kaun paid, kaun band', 'setup' => 'Coaching details + website'] as $k => $v)
            echo '<a href="?p=', $k, '">', h($v), '<span>›</span></a>';
        echo '<a href="padhai.php">Padhai: notice, homework, timetable, notes, test, quiz, doubt<span>›</span></a><a href="padhai.php?p=salary">Teacher salary + haaziri<span>›</span></a>',
             '<a href="padhai.php?p=wa">Sabko WhatsApp<span>›</span></a><a href="padhai.php?p=risk">Khatre me bachche (score)<span>›</span></a>',
             '<a href="site.php?c=', h($C['slug']), '" target="_blank">Meri website dekhiye<span>›</span></a>';
    } else {
        echo '<a href="padhai.php">Padhai: homework, notes, test, quiz, doubt<span>›</span></a><a href="padhai.php?p=risk">Khatre me bachche<span>›</span></a><a href="padhai.php?p=mysal">Meri salary slip<span>›</span></a>';
    }
    echo '<a href="index.php?choose=1">Doosri coaching / app<span>›</span></a><a href="pw.php">Password badlein<span>›</span></a></div>',
         '<form method="post" action="index.php"><input type="hidden" name="a" value="out"><button class="btn ghost full">Logout</button></form>';
}

echo '</div><nav class="tabs">';
foreach ($tabs as $k => [$label, $ic]) echo '<a href="', $k === 'padhai' ? 'padhai.php' : '?p=' . $k, '"', $p === $k ? ' class="on"' : '', '>', cs_icon($ic), h($label), '</a>';
echo '</nav>';
cs_foot();
