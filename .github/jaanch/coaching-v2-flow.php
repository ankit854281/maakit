<?php
// ============================================================
//  जाँच — Coaching Saathi V2–V5 (coaching-flow.php ke BAAD chalta hai)
//  Admin panel (chaabi, nayi coaching, band/chalu) → logo → notice,
//  homework, timetable, notes + file (sirf haq walon ko) → test marks →
//  quiz (server ka samay, jawab pehle nahi, der = khaali) → mock (minus,
//  samay khatam = jo save hua) → rank → doubt → teacher ki seema →
//  teacher haaziri + salary slip (jam jati hai) → risk score →
//  DOOSRI COACHING kuch na dekh paye na badle.
// ============================================================
require_once __DIR__ . '/../../config.php';
if (DB_NAME !== 'maakit_jaanch' && getenv('CS_JAANCH_LOCAL') !== '1') throw new RuntimeException('CI database only');
require_once __DIR__ . '/../../coaching/inc.php';

const BASE = 'http://127.0.0.1:8099';
function ck($ok, $m) { if (!$ok) throw new RuntimeException('Coaching V2: ' . $m); }
function web(string $path, ?array $post = null, string $tok = '', bool $multipart = false): array {
    $c = curl_init(BASE . $path);
    $o = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_PROXY => '', CURLOPT_HEADER => true, CURLOPT_COOKIE => $tok !== '' ? 'cs_t=' . $tok : ''];
    if ($post !== null) { $o[CURLOPT_POST] = true; $o[CURLOPT_POSTFIELDS] = $multipart ? $post : http_build_query($post); }
    curl_setopt_array($c, $o);
    $raw = (string)curl_exec($c); $code = curl_getinfo($c, CURLINFO_HTTP_CODE); $hs = curl_getinfo($c, CURLINFO_HEADER_SIZE); curl_close($c);
    $head = substr($raw, 0, $hs); $body = substr($raw, $hs);
    ck(!preg_match('/Fatal error|Warning:|Uncaught|Deprecated|Notice:/', $body), "PHP error at $path: " . substr(strip_tags($body), 0, 400));
    preg_match('/^Location:\s*(\S+)/mi', $head, $m);
    preg_match_all('/^Set-Cookie:\s*cs_t=([a-f0-9]{64})/mi', $head, $t);
    return [$code, $body, $m[1] ?? '', end($t[1]) ?: '', $head];
}
function sess(string $kind, string $mobile, ?int $cid = null, ?int $uid = null): string {
    $t = bin2hex(random_bytes(32));
    cs_q('INSERT INTO cs_session (token_hash,kind,mobile,coaching_id,user_id,expires) VALUES (?,?,?,?,?,NOW()+INTERVAL 1 DAY)', [hash('sha256', $t), $kind, $mobile, $cid, $uid]);
    return $t;
}
function blocked(int $c): bool { return (int)cs_val('SELECT blocked FROM cs_coaching_meta WHERE coaching_id=?', [$c]) === 1; }
function csrf(string $t): string { return substr(hash('sha256', $t . '|cs-form'), 0, 32); }
function pd(string $tok, string $page, array $post, bool $mp = false): array { $post['csrf'] = csrf($tok); return web('/coaching/padhai.php?p=' . $page, $post, $tok, $mp); }

foreach (['cs_coaching_meta','cs_notice','cs_homework','cs_timetable','cs_note','cs_test','cs_mark','cs_quiz','cs_quiz_q','cs_quiz_att','cs_doubt','cs_staff_att','cs_salary_rate','cs_salary_slip',
          'cs_game_round','cs_account','cs_attendance','cs_ledger','cs_receipt','cs_enroll','cs_batch','cs_course','cs_student','cs_enquiry','cs_sub','cs_session','cs_user','cs_coaching'] as $t) cs_q("DELETE FROM $t");
cs_q('DELETE FROM sms_rate_limits');

// ---- 1. Admin panel ----
[, $html] = web('/coaching/admin.php');
ck(str_contains($html, 'Admin chaabi'), 'admin login page');
[, $html] = web('/coaching/admin.php', ['a' => 'login', 'key' => 'galat-chaabi-123']);
ck(str_contains($html, 'Chaabi galat'), 'galat chaabi');
[, , $loc, $at] = web('/coaching/admin.php', ['a' => 'login', 'key' => cs_admin_key()]);
ck($at !== '' && str_contains($loc, 'admin.php'), 'admin login');
[, $html] = web('/coaching/admin.php', ['a' => 'new', 'csrf' => csrf($at), 'naam' => 'Verma Academy', 'own' => 'Suresh Verma', 'mobile' => '9811100001', 'ctype' => 'sarkari', 'city' => 'Lucknow'], $at);
ck(preg_match('/Pehla password<\/th><td[^>]*><b>([a-z0-9]+)<\/b>/', $html, $m), 'admin ne coaching banayi + pehla password');
$C1 = cs_row("SELECT * FROM cs_coaching WHERE naam='Verma Academy'"); $cid = (int)$C1['id'];
$O1 = cs_row("SELECT * FROM cs_user WHERE coaching_id=? AND role='owner'", [$cid]);
ck($O1 && $O1['mobile'] === '9811100001' && (int)cs_account('9811100001')['temp'] === 1, 'owner bana, password temp');
[$code] = web('/coaching/admin.php?c=' . $cid, null, $at); ck($code === 200, 'admin coaching page');
// owner/parent admin panel nahi khol sakte
$ot = sess('staff', $O1['mobile'], $cid, (int)$O1['id']);
[, $html] = web('/coaching/admin.php', null, $ot);
ck(str_contains($html, 'Admin chaabi') && !str_contains($html, 'Saari coaching'), 'owner admin nahi');
[, $html] = web('/coaching/admin.php', ['a' => 'block', 'c' => $cid, 'csrf' => csrf($ot)], $ot);
ck(!blocked($cid), 'owner khud ko/doosre ko band nahi kar sakta');

// ---- 2. coaching taiyaar: teacher, 2 batch, 3 bachche ----
cs_q("INSERT INTO cs_user (coaching_id,mobile,naam,role) VALUES (?,?,?,'teacher')", [$cid, '9811100002', 'Sita Mam']); $T1 = (int)cs_db()->lastInsertId();
cs_q("INSERT INTO cs_user (coaching_id,mobile,naam,role) VALUES (?,?,?,'teacher')", [$cid, '9811100003', 'Ravi Sir']); $T2 = (int)cs_db()->lastInsertId();
cs_q("INSERT INTO cs_course (coaching_id,naam,fee_type,amount) VALUES (?,?,?,?)", [$cid, 'SSC GD', 'monthly', 800]); $co = (int)cs_db()->lastInsertId();
cs_q("INSERT INTO cs_batch (coaching_id,course_id,naam,timing,teacher_id) VALUES (?,?,?,?,?)", [$cid, $co, 'GD Subah', '7-9 AM', $T1]); $B1 = (int)cs_db()->lastInsertId();
cs_q("INSERT INTO cs_batch (coaching_id,course_id,naam,timing,teacher_id) VALUES (?,?,?,?,?)", [$cid, $co, 'GD Shaam', '5-7 PM', $T2]); $B2 = (int)cs_db()->lastInsertId();
$kid = function (string $naam, string $mob, int $b) use ($cid) {
    cs_q("INSERT INTO cs_student (coaching_id,naam,mobile,cls,join_date) VALUES (?,?,?,?,CURDATE())", [$cid, $naam, $mob, 'SSC']); $id = (int)cs_db()->lastInsertId();
    cs_q("INSERT INTO cs_enroll (coaching_id,student_id,batch_id,fee,start_date) VALUES (?,?,?,0,CURDATE())", [$cid, $id, $b]);
    return $id;
};
$S1 = $kid('Aman', '9811100011', $B1); $S2 = $kid('Bina', '9811100012', $B1); $S3 = $kid('Chetan', '9811100013', $B2);
$tt1 = sess('staff', '9811100002', $cid, $T1);
$p1 = sess('family', '9811100011'); $p2 = sess('family', '9811100012'); $p3 = sess('family', '9811100013');

// ---- 3. logo ----
$png = sys_get_temp_dir() . '/cs-logo.png'; $im = imagecreatetruecolor(400, 300); imagefill($im, 0, 0, imagecolorallocate($im, 200, 30, 30)); imagepng($im, $png);
[, , $loc] = web('/coaching/app.php?p=setup', ['csrf' => csrf($ot), 'a' => 'logo', 'logo' => new CURLFile($png, 'image/png', 'logo.png')], $ot, true);
ck(str_contains($loc, 'm=saved') && cs_val('SELECT logo FROM cs_coaching_meta WHERE coaching_id=?', [$cid]), 'logo laga: ' . $loc);
[$code, $img, , , $head] = web('/coaching/files.php?logo=' . $cid);
ck($code === 200 && str_starts_with($img, "\x89PNG") && stripos($head, 'image/png') !== false && getimagesizefromstring($img)[0] === 256, 'logo chhota karke dikhta hai');
$bad = sys_get_temp_dir() . '/cs-bad.png'; file_put_contents($bad, "<?php echo 'x';");
[, , $loc] = web('/coaching/app.php?p=setup', ['csrf' => csrf($ot), 'a' => 'logo', 'logo' => new CURLFile($bad, 'image/png', 'x.php')], $ot, true);
ck(str_contains($loc, 'm=big'), 'nakli photo nahi');
[, $html] = web('/coaching/app.php?p=home', null, $ot); ck(str_contains($html, 'files.php?logo='), 'header me logo');

// ---- 4. notice, homework, timetable, notes ----
pd($ot, 'notice', ['a' => 'notice', 'batch' => 0, 'title' => 'Kal chhutti', 'body' => 'Diwali ki chhutti']);
pd($tt1, 'notice', ['a' => 'notice', 'batch' => 0, 'title' => 'Teacher sabko']);
ck(!cs_val("SELECT 1 FROM cs_notice WHERE title='Teacher sabko'"), 'teacher poori coaching ko notice nahi');
pd($tt1, 'notice', ['a' => 'notice', 'batch' => $B2, 'title' => 'Doosre batch ko']);
ck(!cs_val("SELECT 1 FROM cs_notice WHERE title='Doosre batch ko'"), 'teacher doosre batch ko notice nahi');
pd($tt1, 'notice', ['a' => 'notice', 'batch' => $B1, 'title' => 'Subah wale test']);
pd($tt1, 'hw', ['a' => 'hw', 'batch' => $B1, 'd' => date('Y-m-d'), 'subject' => 'Maths', 'body' => 'Percentage ke 20 sawaal']);
pd($tt1, 'hw', ['a' => 'hw', 'batch' => $B2, 'body' => 'galat batch']);
ck((int)cs_val('SELECT COUNT(*) FROM cs_homework') === 1, 'homework sirf apne batch');
pd($tt1, 'tt', ['a' => 'tt', 'batch' => $B1, 'day' => 1, 'from' => '07:00', 'to' => '08:00', 'subject' => 'Maths']);
ck(!cs_val('SELECT 1 FROM cs_timetable'), 'teacher timetable nahi badal sakta');
pd($ot, 'tt', ['a' => 'tt', 'batch' => $B1, 'day' => 1, 'all' => '1', 'from' => '07:00', 'to' => '08:00', 'subject' => 'Maths', 'teacher' => $T1]);
pd($ot, 'tt', ['a' => 'tt', 'batch' => $B1, 'day' => 2, 'from' => '09:00', 'to' => '08:00', 'subject' => 'Ulta']);
ck((int)cs_val('SELECT COUNT(*) FROM cs_timetable') === 6, 'roz ka period 6 din, ulta samay nahi');
$pdf = sys_get_temp_dir() . '/cs-note.pdf'; file_put_contents($pdf, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
pd($tt1, 'notes', ['a' => 'note', 'batch' => $B1, 'title' => 'Formula sheet', 'file' => new CURLFile($pdf, 'application/pdf', 'formula.pdf')], true);
$N = cs_row("SELECT * FROM cs_note WHERE title='Formula sheet'");
ck($N && $N['file'] && $N['mime'] === 'application/pdf' && !is_file(__DIR__ . '/../../coaching/files/' . $N['file'] . '.bin') || is_file(__DIR__ . '/../../coaching/files/.htaccess'), 'pdf note bahar ya band folder me');
pd($tt1, 'notes', ['a' => 'note', 'batch' => $B1, 'title' => 'Bura link', 'link' => 'javascript:alert(1)']);
ck(!cs_val("SELECT 1 FROM cs_note WHERE title='Bura link'"), 'javascript link nahi (aur khaali note nahi)');
[$code, $body] = web('/coaching/files.php?n=' . $N['id'], null, $p1); ck($code === 200 && str_starts_with($body, '%PDF'), 'apne batch ka parent note khole');
[$code] = web('/coaching/files.php?n=' . $N['id'], null, $p3); ck($code === 404, 'doosre batch ka parent note nahi');
[$code] = web('/coaching/files.php?n=' . $N['id']); ck($code === 403, 'bina login note nahi');
[$code] = web('/coaching/files.php?n=' . $N['id'], null, sess('staff', '9811100003', $cid, $T2)); ck($code === 404, 'doosre batch ka teacher note nahi');

// ---- 5. test marks ----
pd($tt1, 'tests', ['a' => 'test', 'batch' => $B1, 'naam' => 'Weekly 1', 'subject' => 'Maths', 'd' => date('Y-m-d', strtotime('-14 days')), 'max' => 50]);
$TS = (int)cs_val("SELECT id FROM cs_test WHERE naam='Weekly 1'");
pd($tt1, 'test', ['a' => 'marks', 'id' => $TS, 'mk' => [$S1 => '45', $S2 => '80', $S3 => '50'], 'ab' => []]);
ck((float)cs_val('SELECT marks FROM cs_mark WHERE test_id=? AND student_id=?', [$TS, $S2]) === 50.0, 'poore number se zyada nahi');
ck(!cs_val('SELECT 1 FROM cs_mark WHERE student_id=?', [$S3]), 'doosre batch ke bachche ke marks nahi');
pd($tt1, 'tests', ['a' => 'test', 'batch' => $B1, 'naam' => 'Weekly 2', 'd' => date('Y-m-d'), 'max' => 50]);
$TS2 = (int)cs_val("SELECT id FROM cs_test WHERE naam='Weekly 2'");
pd($tt1, 'test', ['a' => 'marks', 'id' => $TS2, 'mk' => [$S1 => '20'], 'ab' => [$S2 => '1']]);
ck((int)cs_val('SELECT absent FROM cs_mark WHERE test_id=? AND student_id=?', [$TS2, $S2]) === 1, 'absent');

// ---- 6. quiz (har sawaal ka samay) ----
pd($tt1, 'quiz', ['a' => 'quiz', 'batch' => $B1, 'kind' => 'quiz', 'title' => 'GK Quiz', 'secs' => 20]);
$QZ = cs_row("SELECT * FROM cs_quiz WHERE title='GK Quiz'");
pd($tt1, 'quizedit', ['a' => 'qpub', 'id' => $QZ['id']]);
ck(!(int)cs_val('SELECT published FROM cs_quiz WHERE id=?', [$QZ['id']]), 'bina sawaal publish nahi');
[, , $loc] = pd($tt1, 'quizedit', ['a' => 'qbulk', 'id' => $QZ['id'], 'text' => "Section: GK\n1. Bharat ki rajdhani?\nA) Mumbai\nB) Delhi\nAns: B\n\n2+2?\nA) 4\nB) 5\nC) 6\nUttar: A\n\n3+3?\n(a) 5\n(b) 6\nAns: b\n\nbina jawab\nA) x\nB) y"]);
ck(str_contains($loc, 'added=3') && str_contains($loc, 'bad=1'), 'sawaal chipkana: ' . $loc);
pd($tt1, 'quizedit', ['a' => 'qpub', 'id' => $QZ['id']]);
$qq = cs_all('SELECT * FROM cs_quiz_q WHERE quiz_id=? ORDER BY n', [$QZ['id']]);
[, $html] = web('/coaching/me.php?s=' . $S1, null, $p1);
ck(str_contains($html, 'GK Quiz') && str_contains($html, 'Kal chhutti') && str_contains($html, 'Subah wale test') && str_contains($html, 'Percentage') && str_contains($html, 'Formula sheet') && str_contains($html, 'Weekly 1'), 'student app me sab');
[, $html] = web('/coaching/me.php?s=' . $S3, null, $p3);
ck(!str_contains($html, 'GK Quiz') && !str_contains($html, 'Subah wale test') && str_contains($html, 'Kal chhutti'), 'doosre batch ko B1 ka nahi, coaching ka notice haan');
[, , $loc] = web('/coaching/quiz.php?s=' . $S3 . '&q=' . $QZ['id'], null, $p3); ck(str_contains($loc, 'me.php'), 'doosre batch ka bachcha quiz nahi');
[, , $loc] = web('/coaching/quiz.php?s=' . $S1 . '&q=' . $QZ['id'], null, $p3); ck(str_contains($loc, 'me.php'), 'doosre parent ka bachcha nahi');
$qp = function (string $tok, int $sid, array $post) use ($QZ) { $post['csrf'] = csrf($tok); return web('/coaching/quiz.php?s=' . $sid . '&q=' . $QZ['id'], $post, $tok); };
$qp($p1, $S1, ['a' => 'start']);
[, $html] = web('/coaching/quiz.php?s=' . $S1 . '&q=' . $QZ['id'], null, $p1);
ck(str_contains($html, 'Bharat ki rajdhani') && !preg_match('/"ans"|Ans:|✓/', $html) && !str_contains($html, '2+2'), 'ek sawaal, jawab nahi bheja');
$qp($p1, $S1, ['a' => 'answer', 'qid' => $qq[1]['id'], 'c' => 'a']);   // jo dikha nahi uska jawab nahi
ck(!isset(json_decode(cs_val('SELECT answers FROM cs_quiz_att WHERE student_id=?', [$S1]), true)[$qq[1]['id']]), 'bina dikhe sawaal ka jawab nahi');
$qp($p1, $S1, ['a' => 'answer', 'qid' => $qq[0]['id'], 'c' => 'b']);
web('/coaching/quiz.php?s=' . $S1 . '&q=' . $QZ['id'], null, $p1);   // doosra sawaal dikha
$A = json_decode(cs_val('SELECT answers FROM cs_quiz_att WHERE student_id=?', [$S1]), true);
$A[$qq[1]['id']]['t'] = time() - 60; cs_q('UPDATE cs_quiz_att SET answers=? WHERE student_id=?', [json_encode($A), $S1]);
$qp($p1, $S1, ['a' => 'answer', 'qid' => $qq[1]['id'], 'c' => 'a']);  // der se = khaali
web('/coaching/quiz.php?s=' . $S1 . '&q=' . $QZ['id'], null, $p1);
$qp($p1, $S1, ['a' => 'answer', 'qid' => $qq[2]['id'], 'c' => 'b']);
$at1 = cs_row('SELECT * FROM cs_quiz_att WHERE student_id=?', [$S1]);
ck($at1['finished_at'] && (int)$at1['right_n'] === 2 && (int)$at1['wrong_n'] === 0 && (float)$at1['score'] === 2.0, 'quiz score: sahi 2, der wala khaali');
$qp($p1, $S1, ['a' => 'start']);
ck((int)cs_val('SELECT COUNT(*) FROM cs_quiz_att WHERE student_id=?', [$S1]) === 1, 'ek hi baar');
[, $html] = web('/coaching/quiz.php?s=' . $S1 . '&q=' . $QZ['id'], null, $p1);
ck(str_contains($html, 'Rank 1') && !str_contains($html, 'Sahi:'), 'rank dikhe, khula hai to jawab nahi');
// bachche ke shuru karne ke baad sawaal nahi badlenge
pd($tt1, 'quizedit', ['a' => 'qdel', 'id' => $QZ['id'], 'q' => $qq[0]['id']]);
ck((int)cs_val('SELECT COUNT(*) FROM cs_quiz_q WHERE quiz_id=?', [$QZ['id']]) === 3, 'shuru ke baad sawaal nahi hate');
pd($tt1, 'quizedit', ['a' => 'qpub', 'id' => $QZ['id']]);   // band
cs_q('UPDATE cs_quiz SET published=1, closes_at=NOW()-INTERVAL 1 MINUTE WHERE id=?', [$QZ['id']]);
[, $html] = web('/coaching/quiz.php?s=' . $S1 . '&q=' . $QZ['id'], null, $p1);
ck(str_contains($html, 'Sahi:') && str_contains($html, 'Delhi'), 'band hone ke baad sahi jawab');

// ---- 7. mock test: minus marking, samay khatam ----
pd($tt1, 'quiz', ['a' => 'quiz', 'batch' => $B1, 'kind' => 'mock', 'title' => 'SSC Mock 1', 'mins' => 10, 'neg' => '0.5']);
$MK = cs_row("SELECT * FROM cs_quiz WHERE title='SSC Mock 1'");
ck((float)$MK['neg'] === 0.5 && (int)$MK['total_min'] === 10, 'mock settings');
pd($tt1, 'quizedit', ['a' => 'qbulk', 'id' => $MK['id'], 'text' => "Section: Maths\n1+1?\nA) 2\nB) 3\nAns: A\n\n2+1?\nA) 2\nB) 3\nAns: B\n\nSection: GK\nSuraj?\nA) Tara\nB) Grah\nAns: A"]);
pd($tt1, 'quizedit', ['a' => 'qpub', 'id' => $MK['id']]);
$mq = cs_all('SELECT * FROM cs_quiz_q WHERE quiz_id=? ORDER BY n', [$MK['id']]);
$mp = function (string $tok, int $sid, array $post) use ($MK) { $post['csrf'] = csrf($tok); return web('/coaching/quiz.php?s=' . $sid . '&q=' . $MK['id'], $post, $tok); };
$mp($p1, $S1, ['a' => 'start']);
[, $html] = web('/coaching/quiz.php?s=' . $S1 . '&q=' . $MK['id'], null, $p1);
ck(str_contains($html, 'Suraj?') && str_contains($html, 'GK') && !preg_match('/"ans"|Ans:/', $html), 'mock: sab sawaal, jawab nahi');
[, $r] = $mp($p1, $S1, ['a' => 'save', 'ajax' => '1', 'qid' => $mq[0]['id'], 'c' => 'a']); ck($r === 'ok', 'save');
$mp($p1, $S1, ['a' => 'save', 'ajax' => '1', 'qid' => $mq[1]['id'], 'c' => 'a']);   // galat
$mp($p1, $S1, ['a' => 'finish']);
$m1 = cs_row('SELECT * FROM cs_quiz_att WHERE student_id=? AND quiz_id=?', [$S1, $MK['id']]);
ck((float)$m1['score'] === 0.5 && (int)$m1['wrong_n'] === 1, 'minus marking: 1 − 0.5 = 0.5');
$mp($p2, $S2, ['a' => 'start']);
$mp($p2, $S2, ['a' => 'save', 'ajax' => '1', 'qid' => $mq[0]['id'], 'c' => 'a']);
$mp($p2, $S2, ['a' => 'save', 'ajax' => '1', 'qid' => $mq[2]['id'], 'c' => 'a']);
cs_q('UPDATE cs_quiz_att SET started_at=NOW()-INTERVAL 11 MINUTE WHERE student_id=? AND quiz_id=?', [$S2, $MK['id']]);
[, $r] = $mp($p2, $S2, ['a' => 'save', 'ajax' => '1', 'qid' => $mq[1]['id'], 'c' => 'b']);
$m2 = cs_row('SELECT * FROM cs_quiz_att WHERE student_id=? AND quiz_id=?', [$S2, $MK['id']]);
ck($r === 'done' && $m2['finished_at'] && (float)$m2['score'] === 2.0, 'samay khatam: baad wala jawab nahi gina, pehle wale 2');
$rk = cs_quiz_ranks($cid, (int)$MK['id']);
ck($rk[$S2]['rank'] === 1 && $rk[$S1]['rank'] === 2, 'rank list');
[$code, $html] = web('/coaching/padhai.php?p=quizres&id=' . $MK['id'], null, $tt1); ck($code === 200 && str_contains($html, 'Bina'), 'teacher ko rank list');

// ---- 8. doubt ----
web('/coaching/me.php?s=' . $S1, ['csrf' => csrf($p1), 'a' => 'doubt', 'batch' => $B1, 'q' => 'Percentage ka shortcut?'], $p1);
web('/coaching/me.php?s=' . $S1, ['csrf' => csrf($p1), 'a' => 'doubt', 'batch' => $B2, 'q' => 'galat batch'], $p1);
ck((int)cs_val('SELECT COUNT(*) FROM cs_doubt') === 1, 'doubt sirf apne batch me');
$D = (int)cs_val('SELECT id FROM cs_doubt');
pd(sess('staff', '9811100003', $cid, $T2), 'doubts', ['a' => 'doubt', 'id' => $D, 'answer' => 'galat teacher']);
ck(cs_val('SELECT answer FROM cs_doubt WHERE id=?', [$D]) === null, 'doosre batch ka teacher jawab nahi');
pd($tt1, 'doubts', ['a' => 'doubt', 'id' => $D, 'answer' => '10% = /10']);
[, $html] = web('/coaching/me.php?s=' . $S1, null, $p1); ck(str_contains($html, '10% = /10'), 'jawab parent ko dikha');

// ---- 9. teacher haaziri + salary ----
pd($tt1, 'salary', ['a' => 'srate', 'uid' => $T1, 'monthly' => 9999]);
ck(!cs_val('SELECT 1 FROM cs_salary_rate'), 'teacher salary nahi badal sakta');
[, $html] = web('/coaching/padhai.php?p=salary', null, $tt1); ck(!str_contains($html, 'Ravi Sir'), 'teacher ko salary page nahi');
$mo = date('Y-m', strtotime('first day of last month'));
pd($ot, 'salary', ['a' => 'srate', 'uid' => $T1, 'monthly' => 13000, 'leave' => 1]);
pd($ot, 'satt', ['a' => 'satt', 'd' => $mo . '-05', 's' => [$T1 => 'A']]);
pd($ot, 'satt', ['a' => 'satt', 'd' => $mo . '-06', 's' => [$T1 => 'A']]);
pd($ot, 'satt', ['a' => 'satt', 'd' => $mo . '-07', 's' => [$T1 => 'H']]);
$c = cs_salary_calc($cid, $T1, $mo);
ck($c['absent'] === 2.5 && $c['cut'] === (int)round(13000 / $c['work'] * 1.5), 'katauti: (2.5 − 1) din: ' . json_encode($c));
pd($ot, 'salary', ['a' => 'spay', 'uid' => $T1, 'month' => $mo, 'advance' => 1000, 'bonus' => 500]);
$sl = cs_row('SELECT * FROM cs_salary_slip WHERE user_id=?', [$T1]);
ck($sl && (int)$sl['net'] === $c['net'] - 500, 'slip: net − advance + bonus');
pd($ot, 'salary', ['a' => 'srate', 'uid' => $T1, 'monthly' => 50000, 'leave' => 1]);
pd($ot, 'satt', ['a' => 'satt', 'd' => $mo . '-08', 's' => [$T1 => 'A']]);
ck((int)cs_val('SELECT net FROM cs_salary_slip WHERE id=?', [$sl['id']]) === (int)$sl['net'] && !cs_val('SELECT 1 FROM cs_staff_att WHERE d=?', [$mo . '-08']), 'diya hua mahina jam gaya');
[$code, $html] = web('/coaching/padhai.php?p=slip&id=' . $sl['id'], null, $tt1); ck(str_contains($html, rs($sl['net'])), 'teacher apni slip dekhe');
[, $html] = web('/coaching/padhai.php?p=slip&id=' . $sl['id'], null, sess('staff', '9811100003', $cid, $T2)); ck(str_contains($html, 'nahi mili'), 'doosre teacher ki slip nahi');

// ---- 10. risk score ----
foreach ([3, 2, 1] as $i) cs_q("INSERT INTO cs_attendance (coaching_id,batch_id,student_id,d,status,by_user) VALUES (?,?,?,CURDATE()-INTERVAL ? DAY,'A',?)", [$cid, $B1, $S1, $i, $T1]);
$rs = cs_risk_scores($cid, true);
ck(($rs[$S1]['score'] ?? 0) >= 60 && in_array('3 din se nahi aaya', $rs[$S1]['why'], true) && preg_grep('/Marks gire/', $rs[$S1]['why']), 'risk: absent + marks gire: ' . json_encode($rs[$S1] ?? null));
[, $html] = web('/coaching/padhai.php?p=risk', null, $ot); ck(str_contains($html, 'Aman') && str_contains($html, 'Marks gire'), 'risk page');

// ---- 11. DOOSRI COACHING: kuch na dekhe, na badle ----
cs_q("INSERT INTO cs_coaching (slug,naam,phone,city) VALUES ('khan-lko','Khan Classes','9822200001','Lucknow')"); $cid2 = (int)cs_db()->lastInsertId();
cs_q("INSERT INTO cs_user (coaching_id,mobile,naam,role) VALUES (?,?,?,'owner')", [$cid2, '9822200001', 'Khan']); $o2 = sess('staff', '9822200001', $cid2, (int)cs_db()->lastInsertId());
$before = md5(json_encode([cs_all('SELECT * FROM cs_quiz'), cs_all('SELECT * FROM cs_quiz_q'), cs_all('SELECT * FROM cs_mark'), cs_all('SELECT * FROM cs_note'), cs_all('SELECT * FROM cs_doubt'), cs_all('SELECT * FROM cs_timetable'), cs_all('SELECT * FROM cs_salary_rate'), cs_all('SELECT * FROM cs_notice')]));
pd($o2, 'quizedit', ['a' => 'qpub', 'id' => $MK['id']]);
pd($o2, 'quizedit', ['a' => 'qbulk', 'id' => $MK['id'], 'text' => "x?\nA) 1\nB) 2\nAns: A"]);
pd($o2, 'test', ['a' => 'marks', 'id' => $TS, 'mk' => [$S1 => '0']]);
pd($o2, 'notes', ['a' => 'note_del', 'id' => $N['id']]);
pd($o2, 'doubts', ['a' => 'doubt', 'id' => $D, 'answer' => 'hack']);
pd($o2, 'tt', ['a' => 'tt_del', 'id' => cs_val('SELECT id FROM cs_timetable LIMIT 1')]);
pd($o2, 'salary', ['a' => 'srate', 'uid' => $T1, 'monthly' => 1]);
pd($o2, 'notice', ['a' => 'notice_del', 'id' => cs_val('SELECT id FROM cs_notice LIMIT 1')]);
pd($o2, 'hw', ['a' => 'hw', 'batch' => $B1, 'body' => 'hack']);
$after = md5(json_encode([cs_all('SELECT * FROM cs_quiz'), cs_all('SELECT * FROM cs_quiz_q'), cs_all('SELECT * FROM cs_mark'), cs_all('SELECT * FROM cs_note'), cs_all('SELECT * FROM cs_doubt'), cs_all('SELECT * FROM cs_timetable'), cs_all('SELECT * FROM cs_salary_rate'), cs_all('SELECT * FROM cs_notice')]));
ck($before === $after && (int)cs_val('SELECT COUNT(*) FROM cs_homework') === 1, 'doosri coaching ne kuch nahi badla');
foreach (['quizedit&id=' . $MK['id'], 'quizres&id=' . $MK['id'], 'test&id=' . $TS, 'slip&id=' . $sl['id']] as $pg) {
    [, $html] = web('/coaching/padhai.php?p=' . $pg, null, $o2);
    ck(!str_contains($html, 'Aman') && !str_contains($html, 'SSC Mock') && !str_contains($html, 'Sita'), "doosri coaching ko $pg nahi dikha");
}
[$code] = web('/coaching/files.php?n=' . $N['id'], null, $o2); ck($code === 404, 'doosri coaching ko note file nahi');

// ---- 12. saare panne chalein ----
foreach (['home','notice','hw','tt','notes','tests','quiz','doubts','risk','satt','salary','wa','wa&k=absent','wa&k=batch&batch=' . $B1 . '&msg=Kal+test','mysal'] as $pg) {
    [$code, $html] = web('/coaching/padhai.php?p=' . $pg, null, $ot); ck($code === 200, "owner padhai $pg");
}
foreach (['home','notice','hw','tt','notes','tests','quiz','doubts','risk','mysal'] as $pg) { [$code] = web('/coaching/padhai.php?p=' . $pg, null, $tt1); ck($code === 200, "teacher padhai $pg"); }
[, $html] = web('/coaching/padhai.php?p=wa&k=batch&batch=' . $B1 . '&msg=Kal+test', null, $ot); ck(substr_count($html, 'wa.me/91') === 2, 'batch ke 2 parent ko WhatsApp list');

// ---- 13. band coaching: sab bahar, website band ----
web('/coaching/admin.php', ['a' => 'block', 'c' => $cid, 'csrf' => csrf($at), 'note' => 'test'], $at);
ck(blocked($cid), 'admin ne band kiya');
[, , $loc] = web('/coaching/app.php', null, sess('staff', $O1['mobile'], $cid, (int)$O1['id'])); ck(str_contains($loc, 'index.php'), 'band coaching ka owner bahar');
[, , $loc] = web('/coaching/me.php', null, $p1); ck(str_contains($loc, 'index.php'), 'band coaching ka parent bahar');
[$code] = web('/coaching/site.php?c=' . $C1['slug']); ck($code === 404, 'band coaching ki website band');
[$code] = web('/coaching/files.php?n=' . $N['id'], null, $p2); ck($code === 404, 'band coaching ki file band');
web('/coaching/admin.php', ['a' => 'block', 'c' => $cid, 'csrf' => csrf($at)], $at);
ck(!blocked($cid), 'admin ne wapas chalu kiya');
[$code] = web('/coaching/site.php?c=' . $C1['slug']); ck($code === 200, 'website wapas');
[, $html] = web('/coaching/admin.php', ['a' => 'opass', 'c' => $cid, 'csrf' => csrf($at)], $at);
ck(preg_match('/Pehla password<\/th><td[^>]*><b>([a-z0-9]+)<\/b>/', $html, $m) && cs_check_login($O1['mobile'], $m[1]) === 'ok', 'admin se owner ka naya password');
[, $html] = web('/coaching/admin.php?q=Verma', null, $at); ck(str_contains($html, 'Verma Academy') && !str_contains($html, 'Khan Classes'), 'admin search');

echo "Coaching Saathi V2-V5: sab theek\n";
