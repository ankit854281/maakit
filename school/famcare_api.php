<?php
// ============================================================
// Parent connection — maakit.in/school/famcare_api.php  (owner, 28 Nov 2026: "kaise pata chalega parent dekh raha hai ya nahi ·
// bachcha homework nahi karta, jhooth bol deta hai · students ke care se related positive")
//
// Parent / student:
//   POST ?a=seen  {sid, what, date}          the parent opened today's homework ('hw') or the notifications ('notif') — first time kept
//   POST ?a=sign  {sid, date, status, note}  evening sign: done | partial | not  ("I have seen the homework; my child did it")
//   GET  ?a=notes&sid=                       praise / care notes from teachers (last 30 days)
// Teacher of the class / Principal:
//   GET  ?a=class&cls=&date=                 per child: homework seen at, notifications seen at, parent's sign, and
//                                            "parent said done but the teacher found it not done" (next homework check)
//   POST ?a=note  {sid, kind, text}          kind: praise | care — appears in the parent's app and notifications
// Records (kept out of the legacy load by the "fam" prefix): famRead 'date|sid|what', famSign 'date|sid', famNote id.
// ============================================================
declare(strict_types=1);
require __DIR__ . '/inc.php';

try {
    $db = sch_db(); $u = sch_auth(); $a = (string)($_GET['a'] ?? ''); $uid = sch_uid_of($u); $now = time(); $today = date('Y-m-d');
    $in = [];
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') { $in = json_decode(file_get_contents('php://input', false, null, 0, 8000) ?: '', true); if (!is_array($in)) sch_fail('Bad request.'); }
    $date = (string)($in['date'] ?? $_GET['date'] ?? $today); if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $date > $today || $date < date('Y-m-d', $now - 30 * 86400)) $date = $today;

    // ================= TEACHER / PRINCIPAL =================
    if ($a === 'class' || $a === 'note') {
        if (!in_array($u['role'], ['teacher', 'principal'], true)) sch_fail('Teachers only.', 403);
        if ($a === 'note') {
            $s = sch_rec_get('student', (string)($in['sid'] ?? '')); if (!$s) sch_fail('Choose the child.');
            if ($u['role'] !== 'principal' && !sch_in_class((string)$u['tid'], (string)$s['cls'])) sch_fail('Only a teacher of this class can send a note.', 403);
            $kind = (string)($in['kind'] ?? ''); $text = mb_substr(trim(preg_replace('/\s+/u', ' ', (string)($in['text'] ?? ''))), 0, 200);
            if (!in_array($kind, ['praise', 'care'], true) || mb_strlen($text) < 3) sch_fail('Choose praise or care and write the note.');
            $id = 'nt' . substr(hash('sha256', $s['id'] . '|' . $now . '|' . random_int(0, PHP_INT_MAX)), 0, 14);
            sch_rec_put('famNote', $id, ['id' => $id, 'sid' => (string)$s['id'], 'cls' => (string)$s['cls'], 'kind' => $kind, 'text' => $text, 'by' => (string)$u['label'], 'at' => $now], $uid);
            sch_log($u, 'Parent note', ucfirst($kind) . ' note for ' . ($s['name'] ?? '') . " ({$s['cls']})");
            sch_json(['ok' => true]);
        }
        $cls = (string)($_GET['cls'] ?? '');
        if ($cls === '' || ($u['role'] !== 'principal' && !sch_in_class((string)$u['tid'], $cls))) sch_fail('Choose one of your classes.', 403);
        $stu = []; foreach ($db->query("SELECT r, d FROM sch_records WHERE k='student' AND deleted=0") as $row) { $s = json_decode((string)$row['d'], true); if (is_array($s) && ($s['cls'] ?? '') === $cls && empty($s['inactive'])) $stu[(string)$row['r']] = $s; }
        $get = function (string $k, string $like) use ($db) { $q = $db->prepare('SELECT r, d FROM sch_records WHERE k=? AND deleted=0 AND r LIKE ?'); $q->execute([$k, $like]); $o = []; foreach ($q as $row) { $d = json_decode((string)$row['d'], true); if (is_array($d)) $o[(string)$row['r']] = $d; } return $o; };
        $reads = $get('famRead', "$date|%"); $signs = $get('famSign', "$date|%");
        // next homework checks after this date (to catch "parent said done, teacher found not done")
        $chk = []; foreach ($db->query("SELECT d FROM sch_records WHERE k='tchChk' AND deleted=0") as $row) { $d = json_decode((string)$row['d'], true);
            if (is_array($d) && ($d['cls'] ?? '') === $cls && ($d['date'] ?? '') > $date && ($d['date'] ?? '') <= date('Y-m-d', strtotime($date) + 4 * 86400)) foreach ((array)($d['rows'] ?? []) as $sid => $x) if (($x['hw'] ?? '') === 'not') $chk[(string)$sid][] = (string)$d['subj']; }
        $notes = []; foreach ($db->query("SELECT d FROM sch_records WHERE k='famNote' AND deleted=0") as $row) { $d = json_decode((string)$row['d'], true); if (is_array($d) && ($d['cls'] ?? '') === $cls && (int)$d['at'] > $now - 30 * 86400) $notes[(string)$d['sid']] = ($notes[(string)$d['sid']] ?? 0) + 1; }
        $rows = [];
        foreach ($stu as $sid => $s) {
            $hw = $reads["$date|$sid|hw"]['at'] ?? null; $nf = $reads["$date|$sid|notif"]['at'] ?? null; $sg = $signs["$date|$sid"] ?? null;
            $rows[] = ['sid' => $sid, 'name' => (string)$s['name'], 'roll' => (string)($s['rollno'] ?? ''), 'seenHw' => $hw, 'seenNotif' => $nf, 'sign' => $sg ? $sg['status'] : null, 'signAt' => $sg['at'] ?? null, 'signNote' => $sg['note'] ?? '',
                       'mismatch' => $sg && $sg['status'] === 'done' && !empty($chk[$sid]) ? array_values(array_unique($chk[$sid])) : [], 'notes30' => $notes[$sid] ?? 0];
        }
        usort($rows, fn($x, $y) => [(int)$x['roll'] ?: 999, $x['name']] <=> [(int)$y['roll'] ?: 999, $y['name']]);
        $n = count($rows);
        sch_json(['ok' => true, 'cls' => $cls, 'date' => $date, 'rows' => $rows, 'sum' => ['n' => $n, 'seenHw' => count(array_filter($rows, fn($r) => $r['seenHw'])), 'signed' => count(array_filter($rows, fn($r) => $r['sign'])), 'mismatch' => count(array_filter($rows, fn($r) => $r['mismatch']))]]);
    }

    // ================= PARENT / STUDENT =================
    if (!in_array($u['role'], ['parent', 'student'], true)) sch_fail('Not allowed.', 403);
    $sid = $u['role'] === 'student' ? (string)$u['tid'] : (string)($_GET['sid'] ?? $in['sid'] ?? '');
    $kids = $u['role'] === 'student' ? [(string)$u['tid']] : array_map('strval', array_column(sch_children((string)$u['tid']), 'id'));
    if ($sid === '' || !in_array($sid, $kids, true)) sch_fail('Choose your own child.', 403);

    if ($a === 'notes') {
        $o = []; foreach ($db->query("SELECT d FROM sch_records WHERE k='famNote' AND deleted=0") as $row) { $d = json_decode((string)$row['d'], true); if (is_array($d) && ($d['sid'] ?? '') === $sid && (int)$d['at'] > $now - 30 * 86400) $o[] = $d; }
        usort($o, fn($x, $y) => $y['at'] <=> $x['at']);
        $sg = sch_rec_get('famSign', "$today|$sid");
        sch_json(['ok' => true, 'notes' => $o, 'signToday' => $sg]);
    }
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') sch_fail('POST only', 405);
    if ($a === 'seen') {
        $what = (string)($in['what'] ?? ''); if (!in_array($what, ['hw', 'notif'], true)) sch_fail('Unknown item.');
        $db->prepare("INSERT IGNORE INTO sch_records (k,r,d,deleted,dkey,ver,by_uid,updated_at) VALUES ('famRead',?,?,0,?,1,?,NOW())")
           ->execute(["$today|$sid|$what", json_encode(['sid' => $sid, 'what' => $what, 'at' => $now, 'by' => $u['role']]), $today, $uid]);
        sch_json(['ok' => true]);
    }
    if ($a === 'sign') {
        $st = (string)($in['status'] ?? ''); if (!in_array($st, ['done', 'partial', 'not'], true)) sch_fail('Choose done, partly done or not done.');
        if ($u['role'] !== 'parent') sch_fail('Only the parent can sign the homework.', 403);
        sch_rec_put('famSign', "$today|$sid", ['sid' => $sid, 'date' => $today, 'status' => $st, 'note' => mb_substr(trim((string)($in['note'] ?? '')), 0, 200), 'at' => $now], $uid);
        sch_json(['ok' => true]);
    }
    sch_fail('Unknown action.', 404);
} catch (Throwable $e) {
    error_log('school famcare: ' . $e->getMessage());
    sch_fail('Something went wrong on the server. Please try again.', 500);
}
