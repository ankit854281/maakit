<?php
// ============================================================
// Coaching Saathi — bachche ka quiz / mock test (V3–V4)
// Quiz: ek-ek sawaal, har sawaal ka samay server ginta hai (refresh se
//       samay dobara shuru nahi hota, der se diya jawab = khaali).
// Mock: poore test ka samay, har jawab turant server par save, samay
//       khatam = jo save hua wahi gina jaata hai, galat par minus.
// Sahi jawab kabhi pehle nahi bheja jaata; review tab dikhta hai jab
// quiz band ho jaye (taaki bachche ek-doosre ko jawab na bata dein).
// ============================================================
require __DIR__ . '/inc.php';

$s = cs_session();
if (!$s || $s['kind'] !== 'family') cs_go('index.php');
$K = cs_row("SELECT * FROM cs_student WHERE id=? AND mobile=? AND status='active'", [(int)cs_get('s'), $s['mobile']]);
if (!$K || cs_blocked((int)$K['coaching_id'])) cs_go('me.php');
$cid = (int)$K['coaching_id']; $sid = (int)$K['id'];
if (!cs_access($K)['open']) cs_go('me.php?s=' . $sid);
$Q = cs_row('SELECT * FROM cs_quiz WHERE id=? AND coaching_id=? AND published=1', [(int)cs_get('q'), $cid]);
if (!$Q || !in_array((int)$Q['batch_id'], cs_student_batches($cid, $sid), true)) cs_go('me.php?s=' . $sid);
$qs = cs_all('SELECT * FROM cs_quiz_q WHERE quiz_id=? AND coaching_id=? ORDER BY n', [$Q['id'], $cid]);
$self = 'quiz.php?s=' . $sid . '&q=' . (int)$Q['id'];
$mock = $Q['kind'] === 'mock';

function qz_att(int $qid, int $sid): ?array { return cs_row('SELECT * FROM cs_quiz_att WHERE quiz_id=? AND student_id=?', [$qid, $sid]); }
function qz_finish(array $Q, array $qs, array $att): void {
    $ans = (array)json_decode((string)cs_val('SELECT answers FROM cs_quiz_att WHERE id=?', [$att['id']]), true);   // hamesha taaza
    $r = cs_quiz_score($Q, $qs, $ans);
    cs_q('UPDATE cs_quiz_att SET finished_at=NOW(), right_n=?, wrong_n=?, score=? WHERE id=? AND finished_at IS NULL', [$r['right'], $r['wrong'], $r['score'], $att['id']]);
}
$att = qz_att((int)$Q['id'], $sid);
// Samay khatam / quiz band ho gaya to jo hai wahi gin lo
if ($att && !$att['finished_at'] && (($mock && cs_mock_left($Q, $att) <= 0) || !cs_quiz_open($Q))) { qz_finish($Q, $qs, $att); $att = qz_att((int)$Q['id'], $sid); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ajax = cs_post('ajax') === '1';
    if (!cs_csrf_ok()) { if ($ajax) { http_response_code(403); exit('csrf'); } cs_go($self . '&m=csrf'); }
    $a = cs_post('a');
    if ($a === 'start' && !$att && cs_quiz_open($Q) && $qs) {
        cs_q('INSERT IGNORE INTO cs_quiz_att (coaching_id,quiz_id,student_id,started_at,answers) VALUES (?,?,?,NOW(),?)', [$cid, $Q['id'], $sid, '{}']);
        cs_go($self);
    }
    if (!$att || $att['finished_at']) { if ($ajax) exit('done'); cs_go($self); }
    $db = cs_db(); $db->beginTransaction();
    $att = cs_row('SELECT * FROM cs_quiz_att WHERE id=? FOR UPDATE', [$att['id']]);
    $ans = (array)json_decode((string)$att['answers'], true);
    $qid = (string)(int)cs_post('qid'); $c = cs_post('c');
    $valid = in_array($c, ['a', 'b', 'c', 'd', ''], true) && in_array((int)$qid, array_map('intval', array_column($qs, 'id')), true);
    if ($a === 'answer' && !$mock && $valid && isset($ans[$qid]) && $ans[$qid]['c'] === null) {
        $late = time() - (int)$ans[$qid]['t'] > (int)$Q['secs_per_q'] + 5;
        $ans[$qid]['c'] = $late ? '' : $c;
        cs_q('UPDATE cs_quiz_att SET answers=? WHERE id=?', [json_encode($ans), $att['id']]);
    }
    if ($a === 'save' && $mock && $valid && cs_mock_left($Q, $att) > 0) {
        $ans[$qid] = ['c' => $c, 't' => time()];
        cs_q('UPDATE cs_quiz_att SET answers=? WHERE id=?', [json_encode($ans), $att['id']]);
    }
    $db->commit();
    if ($a === 'finish' && $mock) { qz_finish($Q, $qs, $att); cs_go($self); }
    if (!$mock && count(array_filter($ans, fn($v) => $v['c'] !== null)) >= count($qs)) qz_finish($Q, $qs, $att);
    if ($ajax) exit('ok');
    cs_go($self);
}

cs_head($Q['title'], ['color' => cs_val('SELECT color FROM cs_coaching WHERE id=?', [$cid])]);
echo '<div class="top"><a href="me.php?s=', $sid, '">‹ Wapas</a><b>', h($Q['title']), '</b></div><div class="wrap">', cs_flash();

if (!$att) {
    echo '<div class="card lock"><div class="big">', h($Q['title']), '</div><p>', count($qs), ' sawaal · ',
         $mock ? (int)$Q['total_min'] . ' minute · galat jawab par −' . (float)$Q['neg'] : 'har sawaal ' . (int)$Q['secs_per_q'] . ' second', '</p>',
         '<p class="note">Ek baar shuru kiya to samay chalta rahega. Sirf ek baar de sakte hain.</p>';
    if (cs_quiz_open($Q) && $qs) echo '<form method="post">', cs_csrf_field(), '<input type="hidden" name="a" value="start"><button class="btn full">Shuru kariye</button></form>';
    else echo '<p><b>', $Q['opens_at'] && $Q['opens_at'] > date('Y-m-d H:i:s') ? 'Ye ' . h(date('d M, h:i A', strtotime($Q['opens_at']))) . ' ko khulega.' : 'Ye quiz band ho gaya.', '</b></p>';
    echo '</div></div>'; cs_foot(); exit;
}

$ans = (array)json_decode((string)$att['answers'], true);
if ($att['finished_at']) {
    $nq = count($qs); $ranks = cs_quiz_ranks($cid, (int)$Q['id']); $me = $ranks[$sid] ?? null;
    echo '<div class="card lock"><div class="big">', h(rtrim(rtrim(number_format((float)$att['score'], 2), '0'), '.')), ' / ', $nq, '</div><p>', (int)$att['right_n'], ' sahi · ', (int)$att['wrong_n'], ' galat · ', $nq - (int)$att['right_n'] - (int)$att['wrong_n'], ' chhode</p>',
         $me ? '<p><b>Rank ' . $me['rank'] . '</b> / ' . count($ranks) . '</p>' : '', '</div>';
    if (!cs_quiz_open($Q)) {
        echo '<div class="card"><b>Sahi jawab</b>';
        foreach ($qs as $x) {
            $mine = $ans[(string)$x['id']]['c'] ?? '';
            echo '<div class="row" style="display:block"><b>', (int)$x['n'], '.</b> <span class="pre">', h($x['q']), '</span><div class="sub">Sahi: <b style="color:var(--good)">', strtoupper($x['ans']), ') ', h($x[$x['ans']]), '</b>',
                 $mine === '' ? ' · aapne chhoda' : ($mine === $x['ans'] ? ' · aapka sahi ✓' : ' · aapka: <span style="color:var(--bad)">' . strtoupper($mine) . ') ' . h($x[$mine] ?? '') . '</span>'), '</div></div>';
        }
        echo '</div>';
    } else echo '<p class="note">Sahi jawab quiz band hone ke baad yahin dikhenge.</p>';
    echo '</div>'; cs_foot(); exit;
}

if (!$mock) {
    // Agla sawaal: jo dikh chuka par jawab nahi diya, warna naya (samay abhi se)
    $cur = null;
    foreach ($qs as $x) { $k = (string)$x['id']; if (isset($ans[$k]) && $ans[$k]['c'] === null) { $cur = $x; break; } }
    if (!$cur) foreach ($qs as $x) if (!isset($ans[(string)$x['id']])) { $cur = $x; break; }
    if (!$cur) { qz_finish($Q, $qs, $att); cs_go($self); }
    $k = (string)$cur['id'];
    if (!isset($ans[$k])) {
        cs_db()->beginTransaction();
        $lock = cs_row('SELECT answers FROM cs_quiz_att WHERE id=? FOR UPDATE', [$att['id']]);
        $ans = (array)json_decode((string)$lock['answers'], true);
        if (!isset($ans[$k])) { $ans[$k] = ['c' => null, 't' => time()]; cs_q('UPDATE cs_quiz_att SET answers=? WHERE id=?', [json_encode($ans), $att['id']]); }
        cs_db()->commit();
    }
    $left = max(0, (int)$Q['secs_per_q'] - (time() - (int)$ans[$k]['t']));
    $done = count(array_filter($ans, fn($v) => $v['c'] !== null));
    echo '<div class="row"><div class="grow">Sawaal ', $done + 1, ' / ', count($qs), '</div><span class="timer" id="tm">', $left, 's</span></div>',
         '<form method="post" class="card" id="f">', cs_csrf_field(), '<input type="hidden" name="a" value="answer"><input type="hidden" name="qid" value="', (int)$cur['id'], '"><input type="hidden" name="c" id="c" value="">',
         '<div class="pre" style="font-size:17px;font-weight:600">', h($cur['q']), '</div>';
    foreach (['a', 'b', 'c', 'd'] as $o) if ($cur[$o] !== '') echo '<button type="button" class="qopt" onclick="pick(\'', $o, '\')">', strtoupper($o), ') ', h($cur[$o]), '</button>';
    echo '<button type="button" class="btn ghost full" onclick="pick(\'\')">Chhod do</button></form>',
         '<script>var t=', $left, ',el=document.getElementById("tm");function pick(o){document.getElementById("c").value=o;document.getElementById("f").submit()}',
         'setInterval(function(){t--;el.textContent=Math.max(0,t)+"s";if(t<=0){pick("")}},1000)</script>';
} else {
    $left = cs_mock_left($Q, $att) - 15;
    echo '<div class="row" style="position:sticky;top:58px;background:var(--bg);z-index:4"><div class="grow"><span id="sv">', count(array_filter($ans, fn($v) => ($v['c'] ?? '') !== '')), '</span> / ', count($qs), ' jawab save</div><span class="timer" id="tm"></span></div>';
    $sec = null;
    foreach ($qs as $x) {
        if ($x['section'] !== $sec && $x['section'] !== '') echo '<h2>', h($x['section']), '</h2>';
        $sec = $x['section']; $mine = $ans[(string)$x['id']]['c'] ?? '';
        echo '<div class="card"><b>', (int)$x['n'], '.</b> <span class="pre">', h($x['q']), '</span>';
        foreach (['a', 'b', 'c', 'd'] as $o) if ($x[$o] !== '')
            echo '<label class="qopt', $mine === $o ? ' on' : '', '"><input type="radio" name="q', (int)$x['id'], '" value="', $o, '" data-q="', (int)$x['id'], '"', $mine === $o ? ' checked' : '', ' style="width:auto;margin-right:8px">', strtoupper($o), ') ', h($x[$o]), '</label>';
        echo '<button type="button" class="btn sm ghost" data-clear="', (int)$x['id'], '">Jawab hataiye</button></div>';
    }
    echo '<form method="post" id="fin">', cs_csrf_field(), '<input type="hidden" name="a" value="finish"><button class="btn full" onclick="return confirm(\'Test jama karein? Phir badal nahi sakte.\')">Test jama kariye</button></form>',
         '<script>(function(){var t=', $left, ',el=document.getElementById("tm"),cs=', json_encode(cs_csrf()), ';',
         'function fmt(s){s=Math.max(0,s);return Math.floor(s/60)+":"+("0"+s%60).slice(-2)}el.textContent=fmt(t);',
         'setInterval(function(){t--;el.textContent=fmt(t);if(t<=0){location.reload()}},1000);',
         'function save(q,c){var b=new URLSearchParams({a:"save",ajax:"1",csrf:cs,qid:q,c:c});return fetch(location.href,{method:"POST",body:b,credentials:"same-origin"}).then(function(r){return r.text()}).then(function(x){if(x!=="ok"){location.reload()}count()})}',
         'function count(){document.getElementById("sv").textContent=document.querySelectorAll("input[type=radio]:checked").length}',
         'document.querySelectorAll("input[type=radio]").forEach(function(i){i.onchange=function(){i.closest(".card").querySelectorAll(".qopt").forEach(function(l){l.classList.remove("on")});i.parentNode.classList.add("on");save(i.dataset.q,i.value)}});',
         'document.querySelectorAll("[data-clear]").forEach(function(b){b.onclick=function(){var c=b.closest(".card");c.querySelectorAll("input").forEach(function(i){i.checked=false});c.querySelectorAll(".qopt").forEach(function(l){l.classList.remove("on")});save(b.dataset.clear,"")}})})()</script>';
}
echo '</div>';
cs_foot();
