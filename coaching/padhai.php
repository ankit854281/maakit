<?php
// ============================================================
// Coaching Saathi — Padhai aur staff (V2–V5), owner + teacher.
//   V2: suchna, homework, timetable, notes
//   V3: test ke marks, quiz
//   V4: mock test (negative marking, rank), doubt box
//   V5: teacher haaziri, salary slip, sabko WhatsApp, risk score
// Teacher: sirf apne batch. Salary, sabko WhatsApp, timetable badalna: sirf owner.
// ============================================================
require __DIR__ . '/inc.php';

$me = cs_me();
$cid = $me['cid']; $uid = $me['uid']; $C = $me['c']; $owner = cs_owner();
$p = preg_replace('/[^a-z]/', '', cs_get('p', 'home')) ?: 'home';
$today = date('Y-m-d');

function pb(string $to, string $m = 'ok'): void { cs_go('padhai.php?' . $to . '&m=' . $m); }
function pb_owner(): void { if (!cs_owner()) pb('p=home', 'band'); }
/** Quiz jo is login ka hai (teacher: apne batch ka) */
function pb_quiz(int $id): ?array {
    $q = cs_row('SELECT * FROM cs_quiz WHERE id=? AND coaching_id=?', [$id, cs_me()['cid']]);
    return $q && batch_ok((int)$q['batch_id']) ? $q : null;
}
function pb_test(int $id): ?array {
    $t = cs_row('SELECT * FROM cs_test WHERE id=? AND coaching_id=?', [$id, cs_me()['cid']]);
    return $t && batch_ok((int)$t['batch_id']) ? $t : null;
}
/** Batch ke active bachche */
function pb_kids(int $bid): array {
    return cs_all("SELECT s.* FROM cs_enroll e JOIN cs_student s ON s.id=e.student_id AND s.coaching_id=e.coaching_id
                   WHERE e.batch_id=? AND e.coaching_id=? AND e.status='active' AND s.status='active' ORDER BY s.naam", [$bid, cs_me()['cid']]);
}
function pb_dt(string $v): ?string {
    $v = str_replace('T', ' ', trim($v));
    return preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $v) && strtotime($v) ? $v . ':00' : null;
}
function pb_time(string $v): string { return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $v) ? $v : ''; }

// ======================= POST =======================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!cs_csrf_ok()) pb('p=' . $p, 'csrf');
    $a = cs_post('a');
    $bid = (int)cs_post('batch');
    $B = $bid ? batch_ok($bid) : null;

    if ($a === 'notice') {
        $t = mb_substr(cs_post('title'), 0, 120);
        if ($t === '' || (!$B && !$owner)) pb('p=notice', 'err');          // poori coaching ko sirf owner
        cs_q('INSERT INTO cs_notice (coaching_id,batch_id,title,body,by_user) VALUES (?,?,?,?,?)', [$cid, $B ? $bid : null, $t, mb_substr(cs_post('body'), 0, 3000), $uid]);
        pb('p=notice', 'saved');
    }
    if ($a === 'notice_del') {
        cs_q('DELETE FROM cs_notice WHERE id=? AND coaching_id=?' . ($owner ? '' : ' AND by_user=?'), $owner ? [(int)cs_post('id'), $cid] : [(int)cs_post('id'), $cid, $uid]);
        pb('p=notice', 'deleted');
    }
    if ($a === 'hw') {
        $d = cs_date(cs_post('d')) ?: $today; $body = mb_substr(cs_post('body'), 0, 3000);
        if (!$B || $body === '') pb('p=hw', 'err');
        cs_q('INSERT INTO cs_homework (coaching_id,batch_id,d,subject,body,by_user) VALUES (?,?,?,?,?,?)', [$cid, $bid, $d, mb_substr(cs_post('subject'), 0, 60), $body, $uid]);
        pb('p=hw&batch=' . $bid, 'saved');
    }
    if ($a === 'hw_del') {
        $h = cs_row('SELECT * FROM cs_homework WHERE id=? AND coaching_id=?', [(int)cs_post('id'), $cid]);
        if ($h && batch_ok((int)$h['batch_id'])) cs_q('DELETE FROM cs_homework WHERE id=? AND coaching_id=?', [$h['id'], $cid]);
        pb('p=hw&batch=' . (int)($h['batch_id'] ?? 0), 'deleted');
    }
    if ($a === 'tt') {
        pb_owner();
        $day = (int)cs_post('day'); $f = pb_time(cs_post('from')); $t = pb_time(cs_post('to')); $sub = mb_substr(cs_post('subject'), 0, 60);
        $tid = (int)cs_post('teacher'); $tid = $tid ? (int)cs_val('SELECT id FROM cs_user WHERE id=? AND coaching_id=? AND active=1', [$tid, $cid]) : 0;
        if (!$B || !isset(CS_DAYS[$day]) || !$f || !$t || $t <= $f || $sub === '') pb('p=tt&batch=' . $bid, 'err');
        $days = cs_post('all') === '1' ? [1, 2, 3, 4, 5, 6] : [$day];
        foreach ($days as $dd) cs_q('INSERT INTO cs_timetable (coaching_id,batch_id,day,t_from,t_to,subject,teacher_id) VALUES (?,?,?,?,?,?,?)', [$cid, $bid, $dd, $f, $t, $sub, $tid ?: null]);
        pb('p=tt&batch=' . $bid, 'saved');
    }
    if ($a === 'tt_del') {
        pb_owner();
        cs_q('DELETE FROM cs_timetable WHERE id=? AND coaching_id=?', [(int)cs_post('id'), $cid]);
        pb('p=tt&batch=' . $bid, 'deleted');
    }
    if ($a === 'note') {
        $t = mb_substr(cs_post('title'), 0, 120); $link = cs_post('link');
        if ($link !== '' && !preg_match('#^https?://[^\s<>"]+$#i', $link)) $link = '';
        if (!$B || $t === '') pb('p=notes', 'err');
        $up = cs_save_upload('file', 'note');
        if (isset($up['err']) && $up['err'] !== 'none') pb('p=notes&batch=' . $bid, 'big');
        $body = mb_substr(cs_post('body'), 0, 20000);
        if ($body === '' && $link === '' && !isset($up['name'])) pb('p=notes&batch=' . $bid, 'err');
        cs_q('INSERT INTO cs_note (coaching_id,batch_id,title,body,link,file,fname,mime,by_user) VALUES (?,?,?,?,?,?,?,?,?)',
            [$cid, $bid, $t, $body, mb_substr($link, 0, 500), $up['name'] ?? null, $up['orig'] ?? '', $up['mime'] ?? '', $uid]);
        pb('p=notes&batch=' . $bid, 'saved');
    }
    if ($a === 'note_del') {
        $n = cs_row('SELECT * FROM cs_note WHERE id=? AND coaching_id=?', [(int)cs_post('id'), $cid]);
        if ($n && batch_ok((int)$n['batch_id'])) {
            cs_q('DELETE FROM cs_note WHERE id=? AND coaching_id=?', [$n['id'], $cid]);
            if ($n['file']) @unlink(cs_files_dir() . '/' . preg_replace('/[^a-f0-9]/', '', $n['file']) . '.bin');
        }
        pb('p=notes&batch=' . (int)($n['batch_id'] ?? 0), 'deleted');
    }
    if ($a === 'test') {
        $naam = mb_substr(cs_post('naam'), 0, 120); $max = min(10000, cs_int(cs_post('max')));
        if (!$B || $naam === '' || $max < 1) pb('p=tests', 'err');
        cs_q('INSERT INTO cs_test (coaching_id,batch_id,naam,subject,d,max_marks,by_user) VALUES (?,?,?,?,?,?,?)',
            [$cid, $bid, $naam, mb_substr(cs_post('subject'), 0, 60), cs_date(cs_post('d')) ?: $today, $max, $uid]);
        pb('p=test&id=' . (int)cs_db()->lastInsertId(), 'saved');
    }
    if ($a === 'marks') {
        $T = pb_test((int)cs_post('id'));
        if (!$T) pb('p=tests', 'err');
        $st = cs_db()->prepare('INSERT INTO cs_mark (test_id,student_id,coaching_id,marks,absent) VALUES (?,?,?,?,?)
                                ON DUPLICATE KEY UPDATE marks=VALUES(marks), absent=VALUES(absent)');
        foreach (pb_kids((int)$T['batch_id']) as $s) {
            $sid = (int)$s['id']; $ab = isset($_POST['ab'][$sid]) ? 1 : 0;
            $v = trim((string)($_POST['mk'][$sid] ?? ''));
            if ($v === '' && !$ab) continue;
            $m = $ab ? null : max(0, min((float)$T['max_marks'], (float)str_replace(',', '.', $v)));
            $st->execute([$T['id'], $sid, $cid, $m, $ab]);
        }
        pb('p=test&id=' . (int)$T['id'], 'saved');
    }
    if ($a === 'quiz') {
        $kind = cs_post('kind') === 'mock' ? 'mock' : 'quiz'; $title = mb_substr(cs_post('title'), 0, 120);
        if (!$B || $title === '') pb('p=quiz', 'err');
        cs_q('INSERT INTO cs_quiz (coaching_id,batch_id,kind,title,secs_per_q,total_min,neg,opens_at,closes_at,by_user) VALUES (?,?,?,?,?,?,?,?,?,?)',
            [$cid, $bid, $kind, $title, max(10, min(300, cs_int(cs_post('secs')) ?: 30)), $kind === 'mock' ? max(5, min(300, cs_int(cs_post('mins')) ?: 60)) : 0,
             $kind === 'mock' ? max(0, min(1, (float)cs_post('neg'))) : 0, pb_dt(cs_post('opens')), pb_dt(cs_post('closes')), $uid]);
        pb('p=quizedit&id=' . (int)cs_db()->lastInsertId(), 'saved');
    }
    if (in_array($a, ['qbulk', 'qdel', 'qpub'], true)) {
        $Q = pb_quiz((int)cs_post('id'));
        if (!$Q) pb('p=quiz', 'err');
        $started = (bool)cs_val('SELECT 1 FROM cs_quiz_att WHERE quiz_id=? AND coaching_id=?', [$Q['id'], $cid]);
        if ($a === 'qbulk' && !$started) {
            $r = cs_parse_questions(cs_post('text'));
            $n = (int)cs_val('SELECT COALESCE(MAX(n),0) FROM cs_quiz_q WHERE quiz_id=? AND coaching_id=?', [$Q['id'], $cid]);
            foreach (array_slice($r['qs'], 0, 200) as $x)
                cs_q('INSERT INTO cs_quiz_q (coaching_id,quiz_id,n,section,q,a,b,c,d,ans) VALUES (?,?,?,?,?,?,?,?,?,?)',
                    [$cid, $Q['id'], ++$n, $x['section'], $x['q'], $x['a'], $x['b'], $x['c'], $x['d'], $x['ans']]);
            cs_go('padhai.php?p=quizedit&id=' . (int)$Q['id'] . '&added=' . count($r['qs']) . '&bad=' . $r['bad']);
        }
        if ($a === 'qdel' && !$started) cs_q('DELETE FROM cs_quiz_q WHERE id=? AND quiz_id=? AND coaching_id=?', [(int)cs_post('q'), $Q['id'], $cid]);
        if ($a === 'qpub') {
            $has = (int)cs_val('SELECT COUNT(*) FROM cs_quiz_q WHERE quiz_id=? AND coaching_id=?', [$Q['id'], $cid]);
            if (!$has && !(int)$Q['published']) pb('p=quizedit&id=' . (int)$Q['id'], 'err');
            cs_q('UPDATE cs_quiz SET published=1-published WHERE id=? AND coaching_id=?', [$Q['id'], $cid]);
        }
        pb('p=quizedit&id=' . (int)$Q['id'], $started && $a !== 'qpub' ? 'err' : 'ok');
    }
    if ($a === 'doubt') {
        $d = cs_row('SELECT * FROM cs_doubt WHERE id=? AND coaching_id=?', [(int)cs_post('id'), $cid]);
        $ans = mb_substr(cs_post('answer'), 0, 3000);
        if (!$d || !batch_ok((int)$d['batch_id']) || $ans === '') pb('p=doubts', 'err');
        cs_q('UPDATE cs_doubt SET answer=?, answered_by=?, answered_at=NOW() WHERE id=? AND coaching_id=?', [$ans, $uid, $d['id'], $cid]);
        pb('p=doubts', 'saved');
    }

    // ---- V5: sirf owner ----
    pb_owner();
    if ($a === 'satt') {
        $d = cs_date(cs_post('d'));
        if (!$d || $d > $today) pb('p=satt', 'err');
        $st = cs_db()->prepare('INSERT INTO cs_staff_att (coaching_id,user_id,d,status) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE status=VALUES(status)');
        foreach (cs_all("SELECT id FROM cs_user WHERE coaching_id=? AND role='teacher' AND active=1", [$cid]) as $t) {
            $v = $_POST['s'][$t['id']] ?? '';
            if (in_array($v, ['P', 'A', 'H'], true) && !cs_val('SELECT 1 FROM cs_salary_slip WHERE coaching_id=? AND user_id=? AND month=?', [$cid, $t['id'], substr($d, 0, 7)]))
                $st->execute([$cid, $t['id'], $d, $v]);
        }
        pb('p=satt&d=' . $d, 'saved');
    }
    if ($a === 'srate') {
        $t = (int)cs_val("SELECT id FROM cs_user WHERE id=? AND coaching_id=? AND role='teacher'", [(int)cs_post('uid'), $cid]);
        if (!$t) pb('p=salary', 'err');
        cs_q('INSERT INTO cs_salary_rate (coaching_id,user_id,monthly,paid_leave) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE monthly=VALUES(monthly), paid_leave=VALUES(paid_leave)',
            [$cid, $t, min(1000000, cs_int(cs_post('monthly'))), max(0, min(10, (float)cs_post('leave')))]);
        pb('p=salary', 'saved');
    }
    if ($a === 'spay') {
        $t = (int)cs_val("SELECT id FROM cs_user WHERE id=? AND coaching_id=? AND role='teacher'", [(int)cs_post('uid'), $cid]);
        $mo = preg_match('/^\d{4}-\d{2}$/', cs_post('month')) ? cs_post('month') : '';
        if (!$t || !$mo || $mo > date('Y-m')) pb('p=salary', 'err');
        $c = cs_salary_calc($cid, $t, $mo);
        $adv = min(1000000, cs_int(cs_post('advance'))); $bonus = min(1000000, cs_int(cs_post('bonus')));
        cs_q('INSERT IGNORE INTO cs_salary_slip (coaching_id,user_id,month,monthly,work_days,absent,cut,advance,bonus,net,paid_on,by_user) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
            [$cid, $t, $mo, $c['monthly'], $c['work'], $c['absent'], $c['cut'], $adv, $bonus, max(0, $c['net'] - $adv + $bonus), $today, $uid]);
        pb('p=salary&month=' . $mo, 'saved');
    }
    pb('p=home', 'err');
}

// ======================= PAGES =======================
cs_head('Padhai — ' . $C['naam'], $C);
echo '<div class="top">', cs_logo_html($C), '<b>', h($C['naam']), '</b>', $owner ? '' : '<span class="tag">Teacher</span>', '</div><div class="wrap">', cs_flash();
$bs = my_batches();
$bsel = function (string $page, int $cur, bool $all = false) use ($bs) {
    echo '<form class="card" style="padding:10px 14px"><input type="hidden" name="p" value="', h($page), '"><label style="margin-top:0">Batch</label><select name="batch" onchange="this.form.submit()">';
    if ($all) echo '<option value="0">Sab batch</option>';
    foreach ($bs as $b) echo '<option value="', (int)$b['id'], '"', (int)$b['id'] === $cur ? ' selected' : '', '>', h($b['naam']), '</option>';
    echo '</select></form>';
};
$bopts = function (int $cur = 0) use ($bs): string {
    $o = '';
    foreach ($bs as $b) $o .= '<option value="' . (int)$b['id'] . '"' . ((int)$b['id'] === $cur ? ' selected' : '') . '>' . h($b['naam']) . '</option>';
    return $o;
};
$bid = (int)cs_get('batch');
if (!$bid && $bs) $bid = (int)$bs[0]['id'];
$B = $bid ? batch_ok($bid) : null;
$del = function (string $a, int $id, string $extra = '') {
    return '<form method="post" style="display:inline">' . cs_csrf_field() . '<input type="hidden" name="a" value="' . $a . '"><input type="hidden" name="id" value="' . $id . '">' . $extra .
           '<button class="btn sm ghost" onclick="return confirm(\'Hataayein?\')">Hataiye</button></form>';
};

switch ($p) {
// ----------------------------------------------------------------
case 'notice':
    $rows = cs_all('SELECT n.*, b.naam bnaam, u.naam unaam FROM cs_notice n LEFT JOIN cs_batch b ON b.id=n.batch_id AND b.coaching_id=n.coaching_id
                    LEFT JOIN cs_user u ON u.id=n.by_user AND u.coaching_id=n.coaching_id WHERE n.coaching_id=? ORDER BY n.id DESC LIMIT 60', [$cid]);
    echo '<h1>Suchna (Notice)</h1><form method="post" class="card">', cs_csrf_field(), '<input type="hidden" name="a" value="notice"><b>+ Nayi suchna</b>',
         '<label>Kiske liye</label><select name="batch">', $owner ? '<option value="0">Poori coaching</option>' : '', $bopts(), '</select>',
         '<label>Sheershak</label><input name="title" required maxlength="120" placeholder="jaise: Kal chhutti hai"><label>Poori baat</label><textarea name="body" maxlength="3000"></textarea>',
         '<button class="btn full">Bhejiye (app me dikhegi)</button></form>';
    foreach ($rows as $r) {
        if ($r['batch_id'] && !$owner && !batch_ok((int)$r['batch_id'])) continue;
        echo '<div class="card"><b>', h($r['title']), '</b> <span class="tag">', h($r['bnaam'] ?: 'Sabke liye'), '</span><div class="pre">', h($r['body']), '</div>',
             '<div class="sub">', h($r['unaam']), ' · ', h(date('d M, h:i A', strtotime($r['created_at']))), '</div>',
             ($owner || (int)$r['by_user'] === $uid) ? '<div class="btns">' . $del('notice_del', (int)$r['id']) . '</div>' : '', '</div>';
    }
    break;

// ----------------------------------------------------------------
case 'hw':
    echo '<h1>Homework</h1>';
    if (!$B) { echo '<div class="card">Koi batch nahi.</div>'; break; }
    $bsel('hw', $bid);
    echo '<form method="post" class="card">', cs_csrf_field(), '<input type="hidden" name="a" value="hw"><input type="hidden" name="batch" value="', $bid, '"><b>+ Aaj ka homework</b>',
         '<div class="two"><div><label>Tareekh</label><input type="date" name="d" value="', $today, '"></div><div><label>Vishay</label><input name="subject" maxlength="60"></div></div>',
         '<label>Kya karna hai</label><textarea name="body" required maxlength="3000" placeholder="jaise: Ex 5.2 ke sawaal 1 se 10"></textarea><button class="btn full">Save (bachchon ke app me)</button></form>';
    echo '<div class="card"><b>Pichhla homework</b>';
    foreach (cs_all('SELECT * FROM cs_homework WHERE coaching_id=? AND batch_id=? ORDER BY d DESC, id DESC LIMIT 40', [$cid, $bid]) as $r)
        echo '<div class="row"><div class="grow"><b>', h(date('d M', strtotime($r['d']))), $r['subject'] !== '' ? ' · ' . h($r['subject']) : '', '</b><div class="pre">', h($r['body']), '</div></div>', $del('hw_del', (int)$r['id']), '</div>';
    echo '</div>';
    break;

// ----------------------------------------------------------------
case 'tt':
    echo '<h1>Timetable</h1>';
    if (!$B) { echo '<div class="card">Koi batch nahi.</div>'; break; }
    $bsel('tt', $bid);
    $rows = cs_all('SELECT t.*, u.naam unaam FROM cs_timetable t LEFT JOIN cs_user u ON u.id=t.teacher_id AND u.coaching_id=t.coaching_id
                    WHERE t.coaching_id=? AND t.batch_id=? ORDER BY t.day, t.t_from', [$cid, $bid]);
    echo '<div class="card">';
    $last = 0;
    foreach ($rows as $r) {
        if ((int)$r['day'] !== $last) { echo '<h2 style="margin:10px 0 4px">', CS_DAYS[(int)$r['day']], '</h2>'; $last = (int)$r['day']; }
        echo '<div class="row"><div class="grow"><b>', h($r['t_from']), '–', h($r['t_to']), '</b> ', h($r['subject']), '<div class="sub">', h($r['unaam'] ?? ''), '</div></div>',
             $owner ? $del('tt_del', (int)$r['id'], '<input type="hidden" name="batch" value="' . $bid . '">') : '', '</div>';
    }
    if (!$rows) echo '<p class="note">Abhi timetable nahi bana.</p>';
    echo '</div>';
    if ($owner) {
        echo '<form method="post" class="card">', cs_csrf_field(), '<input type="hidden" name="a" value="tt"><input type="hidden" name="batch" value="', $bid, '"><b>+ Period jodiye</b>',
             '<label>Din</label><select name="day">';
        foreach (CS_DAYS as $k => $v) echo '<option value="', $k, '">', $v, '</option>';
        echo '</select><label style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="all" value="1" style="width:auto"> Somvar se Shanivar, roz yahi</label>',
             '<div class="two"><div><label>Kab se</label><input type="time" name="from" required></div><div><label>Kab tak</label><input type="time" name="to" required></div></div>',
             '<label>Vishay</label><input name="subject" required maxlength="60"><label>Teacher</label><select name="teacher"><option value="">—</option>';
        foreach (cs_all('SELECT * FROM cs_user WHERE coaching_id=? AND active=1 ORDER BY naam', [$cid]) as $t) echo '<option value="', (int)$t['id'], '">', h($t['naam']), '</option>';
        echo '</select><button class="btn full">Jodiye</button></form>';
    }
    break;

// ----------------------------------------------------------------
case 'notes':
    echo '<h1>Notes / padhai ka saamaan</h1>';
    if (!$B) { echo '<div class="card">Koi batch nahi.</div>'; break; }
    $bsel('notes', $bid);
    echo '<form method="post" enctype="multipart/form-data" class="card">', cs_csrf_field(), '<input type="hidden" name="a" value="note"><input type="hidden" name="batch" value="', $bid, '"><b>+ Naya note</b>',
         '<label>Sheershak</label><input name="title" required maxlength="120" placeholder="jaise: Trigonometry ke formule">',
         '<label>Likh dijiye (chahein to)</label><textarea name="body" maxlength="20000"></textarea>',
         '<label>Ya link (YouTube / Drive)</label><input name="link" type="url" maxlength="500" placeholder="https://">',
         '<label>Ya file — PDF ya photo, 5 MB tak</label><input type="file" name="file" accept="application/pdf,image/jpeg,image/png">',
         '<button class="btn full">Save (bachchon ke app me)</button></form><div class="card">';
    foreach (cs_all('SELECT * FROM cs_note WHERE coaching_id=? AND batch_id=? ORDER BY id DESC LIMIT 60', [$cid, $bid]) as $r) {
        echo '<div class="row"><div class="grow"><b>', h($r['title']), '</b>', $r['body'] ? '<div class="sub pre">' . h(mb_substr($r['body'], 0, 200)) . '</div>' : '',
             $r['link'] !== '' ? '<div><a href="' . h($r['link']) . '" target="_blank" rel="noopener">Link kholiye</a></div>' : '',
             $r['file'] ? '<div><a href="files.php?n=' . (int)$r['id'] . '" target="_blank">' . h($r['fname']) . '</a></div>' : '', '</div>', $del('note_del', (int)$r['id']), '</div>';
    }
    echo '</div>';
    break;

// ----------------------------------------------------------------
case 'tests':
    echo '<h1>Test aur marks</h1>';
    if (!$B) { echo '<div class="card">Koi batch nahi.</div>'; break; }
    $bsel('tests', $bid);
    echo '<details class="card"><summary><b>+ Naya test (kagaz wala)</b></summary><form method="post">', cs_csrf_field(), '<input type="hidden" name="a" value="test"><input type="hidden" name="batch" value="', $bid, '">',
         '<label>Test ka naam</label><input name="naam" required maxlength="120" placeholder="jaise: Weekly Test 3"><div class="two"><div><label>Vishay</label><input name="subject" maxlength="60"></div>',
         '<div><label>Poore number</label><input name="max" inputmode="numeric" required value="50"></div></div><label>Tareekh</label><input type="date" name="d" value="', $today, '">',
         '<button class="btn full">Banaiye, phir marks bhariye</button></form></details><div class="card">';
    foreach (cs_all('SELECT t.*, (SELECT COUNT(*) FROM cs_mark m WHERE m.test_id=t.id AND m.coaching_id=t.coaching_id) n,
                     (SELECT AVG(m.marks) FROM cs_mark m WHERE m.test_id=t.id AND m.coaching_id=t.coaching_id AND m.absent=0) av
                     FROM cs_test t WHERE t.coaching_id=? AND t.batch_id=? ORDER BY t.d DESC, t.id DESC LIMIT 60', [$cid, $bid]) as $t)
        echo '<a class="row" style="text-decoration:none;color:inherit" href="?p=test&id=', (int)$t['id'], '"><div class="grow"><b>', h($t['naam']), '</b><div class="sub">', h(date('d M', strtotime($t['d']))), ' · ',
             h($t['subject']), ' · ', (int)$t['n'], ' ke marks', $t['av'] !== null ? ' · ausat ' . round((float)$t['av'], 1) . '/' . (int)$t['max_marks'] : '', '</div></div>›</a>';
    echo '</div>';
    break;

case 'test':
    $T = pb_test((int)cs_get('id'));
    if (!$T) { echo '<div class="card">Test nahi mila.</div>'; break; }
    $mk = [];
    foreach (cs_all('SELECT * FROM cs_mark WHERE test_id=? AND coaching_id=?', [$T['id'], $cid]) as $m) $mk[(int)$m['student_id']] = $m;
    $kids = pb_kids((int)$T['batch_id']);
    $got = array_filter($mk, fn($m) => !$m['absent'] && $m['marks'] !== null);
    $vals = array_map(fn($m) => (float)$m['marks'], $got); arsort($vals);
    echo '<h1>', h($T['naam']), '</h1><div class="sub" style="margin-bottom:10px">', h(date('d M Y', strtotime($T['d']))), ' · ', h($T['subject']), ' · Poore number ', (int)$T['max_marks'], '</div>';
    if ($vals) {
        $top = array_key_first($vals); $topS = cs_row('SELECT naam FROM cs_student WHERE id=? AND coaching_id=?', [$top, $cid]);
        echo '<div class="grid"><div class="kpi"><small>Ausat</small><strong>', round(array_sum($vals) / count($vals), 1), '</strong></div><div class="kpi"><small>Sabse zyada</small><strong>', h(rtrim(rtrim(number_format(reset($vals), 2), '0'), '.')), '</strong><small>', h($topS['naam'] ?? ''), '</small></div>',
             '<div class="kpi"><small>Diye</small><strong>', count($vals), '/', count($kids), '</strong></div></div>';
    }
    echo '<form method="post" class="card">', cs_csrf_field(), '<input type="hidden" name="a" value="marks"><input type="hidden" name="id" value="', (int)$T['id'], '"><table><tr><th>Bachcha</th><th class="r">Marks</th><th class="r">Absent</th></tr>';
    foreach ($kids as $s) {
        $m = $mk[(int)$s['id']] ?? null;
        echo '<tr><td>', h($s['naam']), '</td><td class="r"><input name="mk[', (int)$s['id'], ']" inputmode="decimal" style="width:90px;text-align:right" value="', $m && $m['marks'] !== null ? h(rtrim(rtrim((string)$m['marks'], '0'), '.')) : '', '"></td>',
             '<td class="r"><input type="checkbox" name="ab[', (int)$s['id'], ']" value="1" style="width:auto"', $m && $m['absent'] ? ' checked' : '', '></td></tr>';
    }
    echo '</table><button class="btn full">Marks save kariye</button></form>';
    if ($vals && $owner) {
        echo '<div class="card"><b>Parent ko result bhejiye</b>';
        foreach ($kids as $s) if (isset($got[(int)$s['id']]))
            echo '<div class="row"><div class="grow">', h($s['naam']), ' — ', h(rtrim(rtrim((string)$got[(int)$s['id']]['marks'], '0'), '.')), '/', (int)$T['max_marks'], '</div><a class="btn sm wa" target="_blank" href="',
                 h(cs_wa($s['mobile'], "{$C['naam']}: {$s['naam']} ko {$T['naam']} me " . rtrim(rtrim((string)$got[(int)$s['id']]['marks'], '0'), '.') . "/{$T['max_marks']} mile. Class ka ausat " . round(array_sum($vals) / count($vals), 1) . '.')), '">WA</a></div>';
        echo '</div>';
    }
    break;

// ----------------------------------------------------------------
case 'quiz':
    echo '<h1>Quiz aur Mock test</h1>';
    if (!$B) { echo '<div class="card">Koi batch nahi.</div>'; break; }
    $bsel('quiz', $bid);
    echo '<details class="card"><summary><b>+ Naya quiz / mock test</b></summary><form method="post">', cs_csrf_field(), '<input type="hidden" name="a" value="quiz"><input type="hidden" name="batch" value="', $bid, '">',
         '<label>Kis tarah ka</label><select name="kind" onchange="document.getElementById(\'mk\').style.display=this.value==\'mock\'?\'block\':\'none\'"><option value="quiz">Quiz — har sawaal ka apna samay</option><option value="mock">Mock test — poore test ka samay, minus marking</option></select>',
         '<label>Naam</label><input name="title" required maxlength="120" placeholder="jaise: Chapter 3 quiz"><label>Har sawaal ke second (quiz)</label><input name="secs" inputmode="numeric" value="30">',
         '<div id="mk" style="display:none"><div class="two"><div><label>Kul samay (minute)</label><input name="mins" inputmode="numeric" value="60"></div><div><label>Galat par minus</label><select name="neg"><option value="0">0</option><option value="0.25">0.25</option><option value="0.33">1/3</option><option value="0.5">0.5</option><option value="1">1</option></select></div></div></div>',
         '<div class="two"><div><label>Kab khulega (khaali = abhi)</label><input type="datetime-local" name="opens"></div><div><label>Kab band (khaali = hamesha)</label><input type="datetime-local" name="closes"></div></div>',
         '<button class="btn full">Banaiye, phir sawaal daaliye</button></form></details><div class="card">';
    foreach (cs_all('SELECT q.*, (SELECT COUNT(*) FROM cs_quiz_q x WHERE x.quiz_id=q.id AND x.coaching_id=q.coaching_id) nq,
                     (SELECT COUNT(*) FROM cs_quiz_att a WHERE a.quiz_id=q.id AND a.coaching_id=q.coaching_id AND a.finished_at IS NOT NULL) na
                     FROM cs_quiz q WHERE q.coaching_id=? AND q.batch_id=? ORDER BY q.id DESC LIMIT 60', [$cid, $bid]) as $q)
        echo '<a class="row" style="text-decoration:none;color:inherit" href="?p=quizedit&id=', (int)$q['id'], '"><div class="grow"><b>', h($q['title']), '</b> <span class="tag">', $q['kind'] === 'mock' ? 'Mock' : 'Quiz', '</span><div class="sub">',
             (int)$q['nq'], ' sawaal · ', (int)$q['na'], ' ne diya · ', (int)$q['published'] ? (cs_quiz_open($q) ? '<span class="tag green">Khula</span>' : '<span class="tag">Band / baad me</span>') : '<span class="tag red">Draft</span>', '</div></div>›</a>';
    echo '</div>';
    break;

case 'quizedit':
    $Q = pb_quiz((int)cs_get('id'));
    if (!$Q) { echo '<div class="card">Quiz nahi mila.</div>'; break; }
    $qs = cs_all('SELECT * FROM cs_quiz_q WHERE quiz_id=? AND coaching_id=? ORDER BY n', [$Q['id'], $cid]);
    $started = (bool)cs_val('SELECT 1 FROM cs_quiz_att WHERE quiz_id=? AND coaching_id=?', [$Q['id'], $cid]);
    echo '<h1>', h($Q['title']), '</h1><div class="sub" style="margin-bottom:10px">', $Q['kind'] === 'mock' ? 'Mock · ' . (int)$Q['total_min'] . ' minute · galat par −' . (float)$Q['neg'] : 'Quiz · har sawaal ' . (int)$Q['secs_per_q'] . ' second',
         $Q['opens_at'] ? ' · khulega ' . h(date('d M h:i A', strtotime($Q['opens_at']))) : '', $Q['closes_at'] ? ' · band ' . h(date('d M h:i A', strtotime($Q['closes_at']))) : '', '</div>';
    if (cs_get('added') !== '') echo '<div class="flash', (int)cs_get('bad') ? ' bad' : '', '">', (int)cs_get('added'), ' sawaal jud gaye', (int)cs_get('bad') ? ' · ' . (int)cs_get('bad') . ' samajh nahi aaye (jawab ya A/B vikalp nahi tha)' : '', '</div>';
    echo '<div class="btns" style="margin:0 0 12px"><form method="post">', cs_csrf_field(), '<input type="hidden" name="a" value="qpub"><input type="hidden" name="id" value="', (int)$Q['id'], '"><button class="btn sm', (int)$Q['published'] ? ' ghost' : '', '">',
         (int)$Q['published'] ? 'Bachchon se chhupaiye' : 'Bachchon ko bhejiye (publish)', '</button></form><a class="btn sm ghost" href="?p=quizres&id=', (int)$Q['id'], '">Result / rank</a></div>';
    if ($started) echo '<p class="note">Bachche shuru kar chuke hain, isliye ab sawaal badal nahi sakte (sabke liye barabar rahe).</p>';
    else echo '<form method="post" class="card">', cs_csrf_field(), '<input type="hidden" name="a" value="qbulk"><input type="hidden" name="id" value="', (int)$Q['id'], '"><b>+ Sawaal chipkaiye</b>',
              '<p class="note">Har sawaal ke beech ek khaali line. Word / WhatsApp se copy karke chipka sakte hain.</p>',
              '<textarea name="text" style="min-height:220px" placeholder="Section: Maths&#10;2 + 2 kitna hota hai?&#10;A) 3&#10;B) 4&#10;C) 5&#10;D) 6&#10;Ans: B&#10;&#10;Bharat ki rajdhani?&#10;A) Mumbai&#10;B) Delhi&#10;Ans: B"></textarea>',
              '<button class="btn full">Sawaal jodiye</button></form>';
    echo '<div class="card"><b>', count($qs), ' sawaal</b>';
    foreach ($qs as $x) {
        echo '<div class="row" style="align-items:flex-start"><div class="grow"><b>', (int)$x['n'], '.</b> ', $x['section'] !== '' ? '<span class="tag">' . h($x['section']) . '</span> ' : '', '<span class="pre">', h($x['q']), '</span><div class="sub">';
        foreach (['a', 'b', 'c', 'd'] as $o) if ($x[$o] !== '') echo $o === $x['ans'] ? '<b style="color:var(--good)">' : '', strtoupper($o), ') ', h($x[$o]), $o === $x['ans'] ? ' ✓</b>' : '', '<br>';
        echo '</div></div>', $started ? '' : '<form method="post">' . cs_csrf_field() . '<input type="hidden" name="a" value="qdel"><input type="hidden" name="id" value="' . (int)$Q['id'] . '"><input type="hidden" name="q" value="' . (int)$x['id'] . '"><button class="btn sm ghost">×</button></form>', '</div>';
    }
    echo '</div>';
    break;

case 'quizres':
    $Q = pb_quiz((int)cs_get('id'));
    if (!$Q) { echo '<div class="card">Quiz nahi mila.</div>'; break; }
    $ranks = cs_quiz_ranks($cid, (int)$Q['id']);
    $nq = (int)cs_val('SELECT COUNT(*) FROM cs_quiz_q WHERE quiz_id=? AND coaching_id=?', [$Q['id'], $cid]);
    echo '<h1>', h($Q['title']), ' — rank list</h1><div class="card"><table><tr><th>Rank</th><th>Naam</th><th class="r">Sahi</th><th class="r">Galat</th><th class="r">Score</th></tr>';
    foreach ($ranks as $r) echo '<tr><td>', $r['rank'], '</td><td>', h($r['naam']), '</td><td class="r">', $r['right'], '</td><td class="r">', $r['wrong'], '</td><td class="r"><b>', $r['score'], '</b>/', $nq, '</td></tr>';
    echo '</table>', $ranks ? '' : '<p class="note">Abhi kisi ne nahi diya.</p>', '</div>';
    $done = array_keys($ranks);
    $left = array_filter(pb_kids((int)$Q['batch_id']), fn($s) => !in_array((int)$s['id'], $done, true));
    if ($left) {
        echo '<div class="card"><b>Abhi nahi diya (', count($left), ')</b>';
        foreach ($left as $s) echo '<div class="row"><div class="grow">', h($s['naam']), '</div><a class="btn sm wa" target="_blank" href="', h(cs_wa($s['mobile'], "{$C['naam']}: {$s['naam']} ka \"{$Q['title']}\" app me khula hai. Aaj hi dijiye: " . cs_base() . '/')), '">WA</a></div>';
        echo '</div>';
    }
    // Sabse kathin sawaal
    $hard = [];
    foreach (cs_all('SELECT answers FROM cs_quiz_att WHERE quiz_id=? AND coaching_id=? AND finished_at IS NOT NULL', [$Q['id'], $cid]) as $at)
        foreach ((array)json_decode((string)$at['answers'], true) as $qid => $v) { $hard[$qid]['n'] = ($hard[$qid]['n'] ?? 0) + 1; $hard[$qid]['c'] = ($hard[$qid]['c'] ?? 0); $hard[$qid]['v'][] = $v['c'] ?? ''; }
    if ($hard) {
        echo '<div class="card"><b>Kaun sa sawaal kitno ne sahi kiya</b>';
        foreach (cs_all('SELECT * FROM cs_quiz_q WHERE quiz_id=? AND coaching_id=? ORDER BY n', [$Q['id'], $cid]) as $x) {
            $v = $hard[(string)$x['id']]['v'] ?? []; $ok = count(array_filter($v, fn($c) => $c === $x['ans'])); $n = count($ranks);
            $pc = $n ? (int)round($ok * 100 / $n) : 0;
            echo '<div class="row"><div class="grow">', (int)$x['n'], '. ', h(mb_substr($x['q'], 0, 90)), '<div class="bar"><i style="width:', $pc, '%"></i></div></div><b>', $pc, '%</b></div>';
        }
        echo '</div>';
    }
    break;

// ----------------------------------------------------------------
case 'doubts':
    $ids = array_map('intval', array_column($bs, 'id'));
    $rows = $ids ? cs_all('SELECT d.*, s.naam, b.naam bnaam FROM cs_doubt d JOIN cs_student s ON s.id=d.student_id AND s.coaching_id=d.coaching_id
                           JOIN cs_batch b ON b.id=d.batch_id AND b.coaching_id=d.coaching_id
                           WHERE d.coaching_id=? AND d.batch_id IN (' . cs_in($ids) . ') ORDER BY d.answered_at IS NOT NULL, d.id DESC LIMIT 80', array_merge([$cid], $ids)) : [];
    echo '<h1>Doubt box</h1>';
    foreach ($rows as $r) {
        echo '<div class="card"><b>', h($r['naam']), '</b> <span class="tag">', h($r['bnaam']), '</span> ', $r['answer'] === null ? '<span class="tag red">Jawab baaki</span>' : '', '<div class="pre" style="margin:6px 0">', h($r['q']), '</div>';
        if ($r['answer'] !== null) echo '<div class="sub pre">Jawab: ', h($r['answer']), '</div>';
        else echo '<form method="post">', cs_csrf_field(), '<input type="hidden" name="a" value="doubt"><input type="hidden" name="id" value="', (int)$r['id'], '"><textarea name="answer" required maxlength="3000" placeholder="Jawab likhiye"></textarea><button class="btn sm" style="margin-top:8px">Jawab bhejiye</button></form>';
        echo '</div>';
    }
    if (!$rows) echo '<div class="card"><p class="note">Abhi koi doubt nahi aaya.</p></div>';
    break;

// ----------------------------------------------------------------
case 'risk':
    $sc = cs_risk_scores($cid, $owner);
    echo '<h1>Khatre me bachche</h1><p class="note">Score: 3 din absent +40, fees 15 din+ +30, haaziri 75% se kam +15/25, marks gire +20. Zyada score = pehle baat kariye.</p><div class="card">';
    $n = 0;
    foreach ($sc as $sid => $r) {
        $s = student_ok((int)$sid);
        if (!$s) continue;
        $n++;
        echo '<div class="row"><span class="risk', $r['score'] >= 50 ? ' hi' : '', '">', $r['score'], '</span><a class="grow" style="text-decoration:none;color:inherit" href="app.php?p=student&id=', (int)$sid, '"><b>', h($s['naam']), '</b><div class="sub">', h(implode(' · ', $r['why'])), '</div></a>',
             '<a class="btn sm ghost" href="tel:', h($s['mobile']), '">Call</a></div>';
    }
    if (!$n) echo '<p class="note">Abhi koi khatre me nahi.</p>';
    echo '</div>';
    break;

// ----------------------------------------------------------------
case 'satt':
    pb_owner();
    $d = cs_date(cs_get('d')) ?: $today;
    $ts = cs_all("SELECT * FROM cs_user WHERE coaching_id=? AND role='teacher' AND active=1 ORDER BY naam", [$cid]);
    $have = [];
    foreach (cs_all('SELECT * FROM cs_staff_att WHERE coaching_id=? AND d=?', [$cid, $d]) as $r) $have[(int)$r['user_id']] = $r['status'];
    echo '<h1>Teacher ki haaziri</h1><form class="card" style="padding:10px 14px"><input type="hidden" name="p" value="satt"><input type="date" name="d" value="', $d, '" max="', $today, '" onchange="this.form.submit()"></form>',
         '<form method="post" class="card">', cs_csrf_field(), '<input type="hidden" name="a" value="satt"><input type="hidden" name="d" value="', $d, '">';
    foreach ($ts as $t) {
        $v = $have[(int)$t['id']] ?? '';
        echo '<div class="row"><div class="grow">', h($t['naam']), '</div><div class="pa">';
        foreach (['P' => 'p', 'H' => 'p', 'A' => 'a'] as $k => $cl) echo '<label><input type="radio" name="s[', (int)$t['id'], ']" value="', $k, '"', $v === $k ? ' checked' : '', '><span class="', $cl, '">', $k === 'H' ? '½' : $k, '</span></label>';
        echo '</div></div>';
    }
    echo $ts ? '<button class="btn full">Save</button>' : '<p class="note">Koi teacher nahi.</p>', '<p class="note">½ = aadha din. Jis mahine ki salary de di, uski haaziri ab nahi badlegi.</p></form>';
    break;

case 'salary':
    pb_owner();
    $mo = preg_match('/^\d{4}-\d{2}$/', cs_get('month')) ? cs_get('month') : date('Y-m');
    $ts = cs_all("SELECT u.*, r.monthly, r.paid_leave FROM cs_user u LEFT JOIN cs_salary_rate r ON r.user_id=u.id AND r.coaching_id=u.coaching_id
                  WHERE u.coaching_id=? AND u.role='teacher' ORDER BY u.active DESC, u.naam", [$cid]);
    echo '<h1>Salary — ', h(date('M Y', strtotime($mo . '-01'))), '</h1><form class="card" style="padding:10px 14px"><input type="hidden" name="p" value="salary"><input type="month" name="month" value="', $mo, '" max="', date('Y-m'), '" onchange="this.form.submit()"></form>';
    $tot = 0;
    foreach ($ts as $t) {
        $slip = cs_row('SELECT * FROM cs_salary_slip WHERE coaching_id=? AND user_id=? AND month=?', [$cid, $t['id'], $mo]);
        $c = cs_salary_calc($cid, (int)$t['id'], $mo);
        echo '<div class="card"><div class="row"><div class="grow"><b>', h($t['naam']), '</b><div class="sub">Salary ', rs($c['monthly']), ' · kaam ke din ', $c['work'], ' · absent ', $c['absent'], ' (', $c['paid_leave'], ' chhutti maaf)</div></div>';
        if ($slip) { $tot += (int)$slip['net']; echo '<span class="tag green">Diya ', rs($slip['net']), '</span></div><div class="btns"><a class="btn sm ghost" target="_blank" href="?p=slip&id=', (int)$slip['id'], '">Slip</a></div>'; }
        else {
            echo '<span class="amt">', rs($c['net']), '</span></div>';
            if ($c['monthly'] > 0) echo '<details><summary class="note">Salary di (slip banaiye)</summary><form method="post">', cs_csrf_field(), '<input type="hidden" name="a" value="spay"><input type="hidden" name="uid" value="', (int)$t['id'], '"><input type="hidden" name="month" value="', $mo, '">',
                 '<div class="two"><div><label>Advance kaata (₹)</label><input name="advance" inputmode="numeric" value="0"></div><div><label>Bonus (₹)</label><input name="bonus" inputmode="numeric" value="0"></div></div>',
                 '<button class="btn sm" style="margin-top:8px" onclick="return confirm(\'Slip banne ke baad number nahi badlenge. Theek?\')">Salary di — slip</button></form></details>';
        }
        echo '<details><summary class="note">Salary badlein</summary><form method="post">', cs_csrf_field(), '<input type="hidden" name="a" value="srate"><input type="hidden" name="uid" value="', (int)$t['id'], '">',
             '<div class="two"><div><label>Har mahine (₹)</label><input name="monthly" inputmode="numeric" value="', (int)$t['monthly'], '"></div><div><label>Mahine me maaf chhutti</label><input name="leave" inputmode="decimal" value="', h($t['paid_leave'] ?? '1'), '"></div></div>',
             '<button class="btn sm" style="margin-top:8px">Save</button></form></details></div>';
    }
    if (!$ts) echo '<div class="card"><p class="note">Pehle <a href="app.php?p=team">teacher jodiye</a>.</p></div>';
    else echo '<p class="note">Is mahine diya: <b>', rs($tot), '</b>. Katauti = salary ÷ kaam ke din × (absent − maaf chhutti). Ravivar chhutti.</p>';
    break;

case 'slip':
    $sl = cs_row('SELECT s.*, u.naam FROM cs_salary_slip s JOIN cs_user u ON u.id=s.user_id AND u.coaching_id=s.coaching_id WHERE s.id=? AND s.coaching_id=?', [(int)cs_get('id'), $cid]);
    if (!$sl || (!$owner && (int)$sl['user_id'] !== $uid)) { echo '<div class="card">Slip nahi mili.</div>'; break; }
    echo '<div class="card"><h1>Salary slip — ', h(date('F Y', strtotime($sl['month'] . '-01'))), '</h1><b>', h($C['naam']), '</b><div class="sub">', h($C['address']), '</div><table>',
         '<tr><th>Naam</th><td class="r">', h($sl['naam']), '</td></tr><tr><th>Salary</th><td class="r">', rs($sl['monthly']), '</td></tr><tr><th>Kaam ke din</th><td class="r">', (int)$sl['work_days'], '</td></tr>',
         '<tr><th>Absent</th><td class="r">', (float)$sl['absent'], '</td></tr><tr><th>Katauti</th><td class="r">−', rs($sl['cut']), '</td></tr><tr><th>Advance</th><td class="r">−', rs($sl['advance']), '</td></tr>',
         '<tr><th>Bonus</th><td class="r">+', rs($sl['bonus']), '</td></tr><tr><th>Diya</th><td class="r" style="font-size:20px"><b>', rs($sl['net']), '</b></td></tr><tr><th>Tareekh</th><td class="r">', h(date('d M Y', strtotime($sl['paid_on']))), '</td></tr></table>',
         '<p class="note" style="margin-top:30px">Hastakshar ____________</p></div><div class="btns"><button class="btn sm ghost" onclick="print()">Print</button></div>';
    break;

case 'mysal':
    echo '<h1>Meri salary</h1><div class="card">';
    $rows = cs_all('SELECT * FROM cs_salary_slip WHERE coaching_id=? AND user_id=? ORDER BY month DESC LIMIT 24', [$cid, $uid]);
    foreach ($rows as $r) echo '<a class="row" style="text-decoration:none;color:inherit" href="?p=slip&id=', (int)$r['id'], '"><div class="grow">', h(date('M Y', strtotime($r['month'] . '-01'))), '</div><span class="amt">', rs($r['net']), '</span></a>';
    if (!$rows) echo '<p class="note">Abhi koi slip nahi.</p>';
    echo '</div>';
    break;

// ----------------------------------------------------------------
case 'wa':
    pb_owner();
    $kind = in_array(cs_get('k'), ['dues', 'absent', 'batch'], true) ? cs_get('k') : 'dues';
    echo '<h1>Sabko WhatsApp</h1><div class="btns" style="margin:0 0 12px">';
    foreach (['dues' => 'Fees baaki', 'absent' => 'Aaj absent', 'batch' => 'Batch ko message'] as $k => $v) echo '<a class="btn sm', $kind === $k ? '' : ' ghost', '" href="?p=wa&k=', $k, '">', $v, '</a>';
    echo '</div>';
    $list = [];
    if ($kind === 'dues') {
        $bal = cs_balances($cid);
        foreach (cs_all("SELECT * FROM cs_student WHERE coaching_id=? AND status='active' ORDER BY naam", [$cid]) as $s)
            if (($bal[(int)$s['id']] ?? 0) > 0) $list[] = [$s, "Namaste, {$C['naam']} se: {$s['naam']} ki fees " . rs($bal[(int)$s['id']]) . " baaki hai. Kripya jald jama karein. Dhanyavaad."];
    } elseif ($kind === 'absent') {
        foreach (cs_all("SELECT DISTINCT s.*, b.naam bnaam FROM cs_attendance a JOIN cs_student s ON s.id=a.student_id AND s.coaching_id=a.coaching_id
                         JOIN cs_batch b ON b.id=a.batch_id AND b.coaching_id=a.coaching_id WHERE a.coaching_id=? AND a.d=? AND a.status='A' ORDER BY s.naam", [$cid, $today]) as $s)
            $list[] = [$s, "Namaste, {$C['naam']} se: {$s['naam']} aaj {$s['bnaam']} me nahi aaya/aayi. Kripya dhyan dein."];
    } else {
        $msg = mb_substr(cs_get('msg'), 0, 600);
        echo '<form class="card"><input type="hidden" name="p" value="wa"><input type="hidden" name="k" value="batch"><label style="margin-top:0">Batch</label><select name="batch">', $bopts($bid), '</select>',
             '<label>Message</label><textarea name="msg" maxlength="600" required>', h($msg), '</textarea><button class="btn full">List banaiye</button></form>';
        if ($msg !== '' && $B) foreach (pb_kids($bid) as $s) $list[] = [$s, $C['naam'] . ': ' . $msg];
    }
    if ($list) {
        echo '<div class="card"><b>', count($list), ' parent</b><p class="note">Ek-ek karke dabaiye — WhatsApp khulega, "Send" dabaiye, wapas aaiye. Dabaya hua button halka ho jata hai.</p>';
        foreach ($list as [$s, $m]) echo '<div class="row"><div class="grow">', h($s['naam']), '<div class="sub">', h($s['mobile']), '</div></div><a class="btn sm wa" target="_blank" href="', h(cs_wa($s['mobile'], $m)), '" onclick="this.style.opacity=.4">Bhejiye</a></div>';
        echo '</div>';
    } elseif ($kind !== 'batch') echo '<div class="card"><p class="note">Abhi kisi ko bhejne ki zaroorat nahi.</p></div>';
    echo '<p class="note">Bina dabaye apne aap message (WhatsApp Business API) ke liye Meta ka template aur paid number chahiye — wo chalu hone par yahin judega.</p>';
    break;

// ----------------------------------------------------------------
case 'home':
default:
    $open = $bs ? (int)cs_val('SELECT COUNT(*) FROM cs_doubt WHERE coaching_id=? AND answered_at IS NULL AND batch_id IN (' . cs_in(array_map('intval', array_column($bs, 'id'))) . ')', array_merge([$cid], array_map('intval', array_column($bs, 'id')))) : 0;
    echo '<h1>Padhai</h1><div class="card menu">';
    $items = ['notice' => 'Suchna (notice) bhejiye', 'hw' => 'Homework', 'tt' => 'Timetable', 'notes' => 'Notes / PDF / video link', 'tests' => 'Test aur marks',
              'quiz' => 'Quiz aur Mock test (rank ke saath)', 'doubts' => 'Doubt box' . ($open ? ' (' . $open . ' naye)' : ''), 'risk' => 'Khatre me bachche (score)'];
    foreach ($items as $k => $v) echo '<a href="?p=', $k, '">', h($v), '<span>›</span></a>';
    echo '</div>';
    if ($owner) {
        echo '<h2>Staff aur sandesh</h2><div class="card menu">';
        foreach (['satt' => 'Teacher ki haaziri', 'salary' => 'Teacher salary + slip', 'wa' => 'Sabko WhatsApp (fees, absent, batch)'] as $k => $v) echo '<a href="?p=', $k, '">', h($v), '<span>›</span></a>';
        echo '</div>';
    } else echo '<div class="card menu"><a href="?p=mysal">Meri salary slip<span>›</span></a></div>';
}

echo '</div><nav class="tabs">';
$tabs = $owner
    ? ['app.php?p=home' => ['Aaj', 'home'], 'app.php?p=students' => ['Bachche', 'users'], 'app.php?p=dues' => ['Fees', 'rupee'], 'padhai.php' => ['Padhai', 'book'], 'app.php?p=more' => ['Aur', 'more']]
    : ['app.php?p=home' => ['Aaj', 'home'], 'app.php?p=students' => ['Bachche', 'users'], 'app.php?p=att' => ['Haaziri', 'check'], 'padhai.php' => ['Padhai', 'book'], 'app.php?p=more' => ['Aur', 'more']];
foreach ($tabs as $k => [$label, $ic]) echo '<a href="', h($k), '"', $k === 'padhai.php' ? ' class="on"' : '', '>', cs_icon($ic), h($label), '</a>';
echo '</nav>';
cs_foot();
