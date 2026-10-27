<?php
// ============================================================
// Photo → text API — maakit.in/school/scan_api.php
//
//   GET  ?a=status                     {ai: true/false} — AI na ho to phone khud padhega
//   POST ?a=read {mode, cls, subj, images:[dataURL]}
//        mode = chapters (kitaab ka Contents panna) | lplan (lesson plan / kitaab ka panna)
//
// Sirf teacher (apne class-subject ke liye) aur Principal. Photo save nahi
// hoti; nateeja teacher ko dikhaya jaata hai, wahi badal kar save karta hai
// (syllabus / lesson plan aam sync se). Ginti: scanUse (uid|date), "scan"
// prefix aam load se bahar.
// ============================================================
declare(strict_types=1);
require __DIR__ . '/inc.php';
require_once __DIR__ . '/learning_lib.php';
require __DIR__ . '/scan_lib.php';

$a = (string)($_GET['a'] ?? '');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
try { $db = sch_db(); } catch (Throwable $e) { sch_fail('Server is not reachable right now. Please try again in a few minutes.', 503); }

try {
switch ($a) {

case 'status': {
    $u = sch_auth();
    if (!in_array($u['role'], ['teacher', 'principal'], true)) sch_fail('Teachers only.', 403);
    sch_json(['ok' => true, 'ai' => sc_ai_ready()]);
}

case 'read': {
    if ($method !== 'POST') sch_fail('POST only', 405);
    $u = sch_auth();
    if (!in_array($u['role'], ['teacher', 'principal'], true)) sch_fail('Teachers only.', 403);
    $in = json_decode(file_get_contents('php://input', false, null, 0, 14000000) ?: '', true);
    if (!is_array($in)) sch_fail('Bad request.');
    $mode = (string)($in['mode'] ?? ''); $cls = (string)($in['cls'] ?? ''); $subj = (string)($in['subj'] ?? '');
    if (!in_array($mode, ['chapters', 'lplan', 'page'], true)) sch_fail('Choose what the photo is.');
    if ($u['role'] === 'teacher' && !sch_teaches((string)$u['tid'], $cls, $subj)) sch_fail('You can scan only for your own class and subject.', 403);
    $imgs = $in['images'] ?? null;
    if (!is_array($imgs) || !$imgs || count($imgs) > SC_MAX_IMAGES) sch_fail('Send 1 to ' . SC_MAX_IMAGES . ' photos.');
    try { $images = array_map(fn($x) => sc_image((string)$x), array_values($imgs)); }
    catch (InvalidArgumentException $e) { sch_fail($e->getMessage()); }
    if (!sc_ai_ready()) sch_json(['ok' => false, 'noAi' => true, 'error' => 'AI reading is not switched on. Your phone will read the photo instead.'], 409);

    $uid = sch_uid_of($u); $key = $uid . '|' . date('Y-m-d');
    $db->beginTransaction();
    $db->prepare("INSERT IGNORE INTO sch_records (k,r,d,deleted,dkey,ver,by_uid,updated_at) VALUES ('scanUse',?,'{\"n\":0}',0,NULL,?,?,NOW())")->execute([$key, sch_now_ms(), $uid]);
    $st = $db->prepare("SELECT d FROM sch_records WHERE k='scanUse' AND r=? FOR UPDATE"); $st->execute([$key]);
    $n = (int)(json_decode((string)$st->fetchColumn(), true)['n'] ?? 0);
    if ($n >= SC_PER_DAY) { $db->rollBack(); sch_fail('Today\'s photo limit is used up. Please type the rest, or try tomorrow.', 429); }
    sch_rec_put('scanUse', $key, ['n' => $n + 1], $uid);
    $db->commit();

    $ctx = [];
    if($mode==='page'){$ctx['days']=max(1,min(6,(int)($in['days']??3)));$ctx['stage']=preg_match('/^(PG|Nursery|LKG|UKG)$/i',$cls)?'foundation':((int)$cls<=5?'primary':'secondary');}
    if ($mode === 'lplan') {
        $syl = sch_rec_get('syl', $cls . '|' . $subj);
        $next = array_values(array_filter($syl['chapters'] ?? [], fn($c) => empty($c['done'])));
        if ($next) $ctx['nextChapter'] = (string)($next[0]['n'] ?? '');
    }
    try { $res = sc_ai_read($mode, $cls, $subj, $images, $ctx); }
    catch (Throwable $e) { error_log('scan_api: ' . $e->getMessage()); sch_json(['ok' => false, 'noAi' => true, 'error' => 'AI could not read it just now. Your phone will read the photo instead.'], 502); }
    sch_log($u, $mode === 'chapters' ? 'Syllabus' : 'Lesson Plan', 'Photo read for Class ' . $cls . ' ' . $subj . ': '
        . ($mode === 'chapters' ? count($res['chapters']) . ' chapter(s) found' : ($mode==='page'?count($res['days']).' day drafts':count($res['topics']).' topic(s) found')) . ' (to check and save)');
    sch_json(['ok' => true, 'via' => 'ai'] + $res);
}

default: sch_fail('Unknown action.', 404);
}
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('scan_api: ' . $e->getMessage());
    sch_fail('Something went wrong. Please try again.', 500);
}
