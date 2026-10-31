<?php
// Student app ka paid hissa (V2–V4): timetable, homework, quiz/mock, marks, notes, doubt.
// me.php se hi chalta hai — yahan $cid, $sid, $myB, $bin pehle se hain, aur app chalu hai.
if (!isset($myB, $cid, $sid)) exit;
$P = array_merge([$cid], $myB);

// ---- aaj ka timetable + poora hafta ----
$tt = cs_all('SELECT t.*, b.naam bnaam FROM cs_timetable t JOIN cs_batch b ON b.id=t.batch_id AND b.coaching_id=t.coaching_id WHERE t.coaching_id=? AND t.batch_id IN (' . $bin . ') ORDER BY t.day, t.t_from', $P);
if ($tt) {
    $dn = (int)date('N');
    echo '<details class="card"><summary><b>Aaj ka timetable</b> ';
    $todayRows = array_filter($tt, fn($r) => (int)$r['day'] === $dn);
    echo $todayRows ? '' : '<span class="tag">Aaj class nahi</span>';
    foreach ($todayRows as $r) echo '<div class="sub">', h($r['t_from']), '–', h($r['t_to']), ' ', h($r['subject']), '</div>';
    echo '</summary>';
    $last = 0;
    foreach ($tt as $r) {
        if ((int)$r['day'] !== $last) { echo '<div style="margin-top:8px"><b>', CS_DAYS[(int)$r['day']], '</b></div>'; $last = (int)$r['day']; }
        echo '<div class="sub">', h($r['t_from']), '–', h($r['t_to']), ' · ', h($r['subject']), ' · ', h($r['bnaam']), '</div>';
    }
    echo '</details>';
}

// ---- homework (7 din) ----
$hw = cs_all('SELECT * FROM cs_homework WHERE coaching_id=? AND batch_id IN (' . $bin . ') AND d>=CURDATE()-INTERVAL 7 DAY ORDER BY d DESC, id DESC LIMIT 15', $P);
echo '<div class="card"><b>Homework</b>';
foreach ($hw as $r) echo '<div class="row" style="display:block"><b>', h(date('d M', strtotime($r['d']))), $r['subject'] !== '' ? ' · ' . h($r['subject']) : '', '</b><div class="pre">', h($r['body']), '</div></div>';
if (!$hw) echo '<p class="note">Is hafte koi homework nahi.</p>';
echo '</div>';

// ---- quiz / mock ----
$qz = cs_all('SELECT q.*, a.finished_at, a.score, (SELECT COUNT(*) FROM cs_quiz_q x WHERE x.quiz_id=q.id AND x.coaching_id=q.coaching_id) nq
              FROM cs_quiz q LEFT JOIN cs_quiz_att a ON a.quiz_id=q.id AND a.student_id=? AND a.coaching_id=q.coaching_id
              WHERE q.coaching_id=? AND q.batch_id IN (' . $bin . ') AND q.published=1 ORDER BY q.id DESC LIMIT 15', array_merge([$sid], $P));
if ($qz) {
    echo '<div class="card"><b>Quiz aur Mock test</b>';
    foreach ($qz as $q) {
        $open = cs_quiz_open($q);
        echo '<a class="row" style="text-decoration:none;color:inherit" href="quiz.php?s=', $sid, '&q=', (int)$q['id'], '"><div class="grow"><b>', h($q['title']), '</b> <span class="tag">', $q['kind'] === 'mock' ? 'Mock' : 'Quiz', '</span><div class="sub">',
             (int)$q['nq'], ' sawaal · ', $q['finished_at'] ? 'Score ' . h(rtrim(rtrim(number_format((float)$q['score'], 2), '0'), '.')) . '/' . (int)$q['nq'] : ($open ? '<span class="tag green">Abhi dijiye</span>' : 'Band'), '</div></div>›</a>';
    }
    echo '</div>';
}

// ---- marks + tarakki ----
$mk = cs_all('SELECT t.naam, t.subject, t.d, t.max_marks, m.marks, m.absent,
              (SELECT AVG(x.marks) FROM cs_mark x WHERE x.test_id=t.id AND x.coaching_id=t.coaching_id AND x.absent=0) av
              FROM cs_mark m JOIN cs_test t ON t.id=m.test_id AND t.coaching_id=m.coaching_id WHERE m.student_id=? AND m.coaching_id=? ORDER BY t.d DESC, t.id DESC LIMIT 12', [$sid, $cid]);
if ($mk) {
    echo '<div class="card"><b>Test ke marks</b>';
    foreach ($mk as $m) {
        $pc = !$m['absent'] && $m['marks'] !== null && $m['max_marks'] ? (int)round($m['marks'] * 100 / $m['max_marks']) : 0;
        echo '<div class="row" style="display:block"><div style="display:flex;gap:8px"><span class="grow" style="flex:1">', h($m['naam']), ' <span class="sub">', h(date('d M', strtotime($m['d']))), ' ', h($m['subject']), '</span></span><b>',
             $m['absent'] ? 'Absent' : h(rtrim(rtrim((string)$m['marks'], '0'), '.')) . '/' . (int)$m['max_marks'], '</b></div>',
             $m['absent'] ? '' : '<div class="bar"><i style="width:' . $pc . '%"></i></div><div class="sub">' . $pc . '% · class ka ausat ' . round((float)$m['av'], 1) . '</div>', '</div>';
    }
    echo '</div>';
}

// ---- notes ----
$nt = cs_all('SELECT n.*, b.naam bnaam FROM cs_note n JOIN cs_batch b ON b.id=n.batch_id AND b.coaching_id=n.coaching_id WHERE n.coaching_id=? AND n.batch_id IN (' . $bin . ') ORDER BY n.id DESC LIMIT 20', $P);
if ($nt) {
    echo '<details class="card"><summary><b>Notes (', count($nt), ')</b></summary>';
    foreach ($nt as $n) echo '<div class="row" style="display:block"><b>', h($n['title']), '</b>', $n['body'] ? '<div class="pre">' . h($n['body']) . '</div>' : '',
         $n['link'] !== '' ? '<div><a href="' . h($n['link']) . '" target="_blank" rel="noopener">Link kholiye</a></div>' : '',
         $n['file'] ? '<div><a href="files.php?n=' . (int)$n['id'] . '" target="_blank">' . h($n['fname']) . '</a></div>' : '', '</div>';
    echo '</details>';
}

// ---- doubt box ----
$db = cs_all('SELECT * FROM cs_doubt WHERE student_id=? AND coaching_id=? ORDER BY id DESC LIMIT 10', [$sid, $cid]);
echo '<div class="card" id="doubt"><b>Doubt poochhiye</b><form method="post">', cs_csrf_field(), '<input type="hidden" name="a" value="doubt">';
if (count($myB) > 1) {
    echo '<select name="batch">';
    foreach (cs_all('SELECT id, naam FROM cs_batch WHERE coaching_id=? AND id IN (' . $bin . ')', $P) as $b) echo '<option value="', (int)$b['id'], '">', h($b['naam']), '</option>';
    echo '</select>';
} else echo '<input type="hidden" name="batch" value="', (int)$myB[0], '">';
echo '<textarea name="q" required maxlength="2000" placeholder="Apna sawaal likhiye — teacher yahin jawab denge" style="margin-top:8px"></textarea><button class="btn sm" style="margin-top:8px">Bhejiye</button></form>';
foreach ($db as $d) echo '<div class="row" style="display:block"><div class="pre">', h($d['q']), '</div><div class="sub pre">', $d['answer'] !== null ? 'Jawab: ' . h($d['answer']) : 'Jawab ka intezaar…', '</div></div>';
echo '</div>';
