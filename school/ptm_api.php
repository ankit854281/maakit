<?php
// ============================================================================
//  PTM Desk — owner, 21 Nov 2026: "parents aa rahe hain, unki entry, feedback, complaint application me kaise note hoga …
//  Pawan Bhalla Sir naam note kar rahe hain, slip class me teachers dikha rahe hain — naam, feedback, fee Pawan Bhalla Sir
//  dekh lein, complaint ka suggestion ke saath education ke according baat karein aur fill karein".
//
//  One desk for the parent who walks in: pick the class and the child → the parent's name and mobile, brothers/sisters,
//  Half-Yearly result (%, rank, weak and strong subjects), attendance and the fee due till this month are on one screen,
//  with talking points; one save notes "came", slip seen, fee paid / promise date, mood, topics, feedback and a complaint.
//
//   GET  ?a=cls&cls=&date=          the class: children with result, attendance, fee due, siblings, today's visit
//   POST ?a=visit {date,sid,…}       save / update the visit of one child (also marks siblings as came, if asked)
//   GET  ?a=report&date=             the day: every visit, counts per class, who did not come; complaints still open (any day)
//   POST ?a=resolve {key,status,action}   close a complaint (Principal, Chairman, the desk)
//   POST ?a=sent {date,sid,how}      the result card / thank-you was sent to the parent on WhatsApp
//
//  Who: Principal, Reception, Chairman (report + resolve) and the desk teacher — Pawan Bhalla Sir (tid PW, or a teacher whose
//  name has "Pawan"), or the teachers listed in settings.ptmDesk. Records: ptmVisit 'date|sid' (kept out of the legacy sync).
// ============================================================================
declare(strict_types=1);
require __DIR__ . '/inc.php';
require __DIR__ . '/registers_lib.php';
require __DIR__ . '/feecollect_lib.php';
require __DIR__ . '/feereports_lib.php';

$a = (string)($_GET['a'] ?? 'cls');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
try { $db = sch_db(); } catch (Throwable $e) { sch_fail('Server is not reachable right now. Please try again in a few minutes.', 503); }
$u = sch_auth(); $role = (string)$u['role']; $tid = (string)($u['tid'] ?? ''); $uid = sch_uid_of($u);
function ptm_desk_teacher(string $tid): bool {
    if ($tid === '') return false;
    $L = (sch_rec_get('settings', 'main') ?? [])['ptmDesk'] ?? null;
    if (is_array($L)) return in_array($tid, $L, true);
    return $tid === 'PW' || (bool)preg_match('/\bpawan\b/i', sch_teacher_name($tid));
}
$desk = in_array($role, ['principal', 'reception'], true) || ($role === 'teacher' && ptm_desk_teacher($tid));
$see = $desk || $role === 'chairman';
if (!$see) sch_fail('PTM Desk is open for the Principal, Chairman, Reception and the PTM desk teacher.', 403);
$in = [];
if ($method === 'POST') { $in = json_decode((string)file_get_contents('php://input', false, null, 0, 200000), true); if (!is_array($in)) sch_fail('Bad request.'); }
function ptm_date($v): string { $v = (string)$v; return preg_match('/^20\d\d-\d\d-\d\d$/', $v) ? $v : date('Y-m-d'); }
function ptm_txt($v, int $n = 300): string { return mb_substr(trim(preg_replace('/[\x00-\x1f\x7f]/u', ' ', (string)$v)), 0, $n); }
function ptm_ses(string $date): string { $y = (int)substr($date, 0, 4); if ((int)substr($date, 5, 2) < 4) $y--; return $y . '-' . substr((string)($y + 1), 2); }
const PTM_TAGS = ['Academics', 'Teacher', 'Homework', 'Behaviour', 'Fee', 'Transport', 'Cleanliness', 'Meals', 'Other'];
const PTM_OLD = ['Padhai' => 'Academics', 'Bus' => 'Transport', 'Safai' => 'Cleanliness', 'Khana' => 'Meals'];   // first-day Hinglish topics
$visits = function (string $prefix) use ($db): array {
    $q = $db->prepare("SELECT r, d FROM sch_records WHERE k='ptmVisit' AND deleted=0 AND r LIKE ?"); $q->execute([str_replace(['%', '_'], ['\\%', '\\_'], $prefix) . '%']); $o = [];
    foreach ($q as $x) { $d = json_decode((string)$x['d'], true); if (is_array($d)) $o[(string)$x['r']] = $d; } return $o;
};

try {
switch ($a) {
case 'cls': {
    $cls = (string)($_GET['cls'] ?? ''); $date = ptm_date($_GET['date'] ?? ''); $ses = ptm_ses($date); $y0 = (int)substr($ses, 0, 4);
    $all = reg_students(); $kids = array_values(array_filter($all, fn($s) => $s['cls'] === $cls && empty($s['inactive'])));
    if (!$kids) sch_json(['ok' => true, 'kids' => [], 'tags' => PTM_TAGS]);
    usort($kids, fn($x, $y) => strcmp($x['name'], $y['name']));
    // brothers / sisters: same parent mobile
    $byPhone = []; foreach ($all as $s) if (empty($s['inactive']) && ($p = sch_phone10($s['phone'])) !== '') $byPhone[$p][] = $s;
    // Half-Yearly result — same subjects as the marks slip (a combined "Hindi/Sanskrit" is left out when its parts have marks)
    $q = $db->prepare("SELECT d FROM sch_records WHERE k='prcMarks' AND deleted=0 AND r LIKE ?"); $q->execute([$ses . '|Term-1|' . str_replace(['%', '_'], ['\\%', '\\_'], $cls) . '|%']);
    $M = []; foreach ($q as $x) { $d = json_decode((string)$x['d'], true); if (is_array($d) && ($d['subject'] ?? '') !== '') $M[(string)$d['subject']] = (array)($d['rows'] ?? []); }
    $filled = array_keys(array_filter($M, function ($rows) { foreach ($rows as $r) foreach (['pt', 'nb', 'sea', 'te'] as $k) if ((((array)$r)[$k] ?? '') !== '') return true; return false; }));
    $M = array_filter($M, function ($rows, $nm) use ($filled) { if (!in_array($nm, $filled, true)) return false; $pp = array_values(array_filter(array_map('trim', explode('/', (string)$nm)))); return !(count($pp) > 1 && !array_diff($pp, $filled)); }, ARRAY_FILTER_USE_BOTH);
    $res = [];
    foreach ($kids as $s) { $subs = []; $tot = 0.0; $n = 0; $nbLow = 0;
        foreach ($M as $nm => $rows) { $r = (array)($rows[$s['id']] ?? []); if (!$r) continue; $has = false; $t = 0.0;
            foreach (['pt', 'nb', 'sea', 'te'] as $k) { $v = $r[$k] ?? ''; if ($v !== '' && $v !== null) $has = true; if (is_int($v) || is_float($v)) $t += $v; }
            if (!$has) continue; $ab = ($r['te'] ?? '') === 'AB'; if (is_numeric($r['nb'] ?? '') && (float)$r['nb'] < 4) $nbLow++;
            $subs[] = ['s' => preg_match('/^gk\s*\/\s*drawing$/i', $nm) ? 'G.K.' : $nm, 't' => round($t, 2), 'ab' => $ab]; $tot += $t; $n++; }
        $res[$s['id']] = ['subs' => $subs, 'pct' => $n ? round($tot / $n, 1) : null, 'n' => $n, 'nbLow' => $nbLow]; }
    $full = $res ? max(array_column($res, 'n')) : 0; $rk = [];
    foreach ($res as $sid => $r) if ($full && $r['n'] === $full) $rk[$sid] = $r['pct'];
    arsort($rk); $i = 0; $last = null; $rank = 0; foreach ($rk as $sid => $p) { $i++; if ($last === null || $p < $last) { $rank = $i; $last = $p; } $res[$sid]['rank'] = $rank; $res[$sid]['of'] = count($rk); }
    // attendance this session (daily class attendance)
    $aq = $db->prepare("SELECT r, d FROM sch_records WHERE k='studAtt' AND deleted=0 AND dkey >= ? AND dkey <= ? AND r LIKE ?"); $aq->execute([$y0 . '-04-01', $date, '%|' . str_replace(['%', '_'], ['\\%', '\\_'], $cls)]);
    $days = 0; $abs = []; foreach ($aq as $row) { [, $c] = array_pad(explode('|', (string)$row['r'], 2), 2, ''); if ($c !== $cls) continue; $d = json_decode((string)$row['d'], true); if (!is_array($d)) continue; $days++; foreach ((array)($d['absent'] ?? []) as $x) $abs[(string)$x] = ($abs[(string)$x] ?? 0) + 1; }
    // fee due till this month (same figures as the Chairman app: card heads up to this month + old due)
    $fee = []; $mi = array_search((int)substr($date, 5, 2), [4, 5, 6, 7, 8, 9, 10, 11, 12, 1, 2, 3], true);
    try { foreach (fr_dues($db, $ses)['rows'] as $r) { if ($r['cls'] !== $cls) continue; $due = max(0, (float)$r['oldDue'] - (float)($r['oldPaid'] ?? 0)); $months = [];
        foreach ($r['heads'] as $h) { $pos = array_search((int)$h['m'], [4, 5, 6, 7, 8, 9, 10, 11, 12, 1, 2, 3], true); if ($pos === false || $pos > $mi) continue; $o = max(0, (float)$h['amt'] - (float)$h['paid']); if ($o > 0) { $due += $o; $months[(int)$h['m']] = 1; } }
        $fee[$r['sid']] = ['due' => round($due), 'months' => array_keys($months), 'last' => $r['lastDate'] ? ['date' => $r['lastDate'], 'amt' => $r['lastAmt']] : null]; } } catch (Throwable $e) {}
    $V = $visits($date . '|'); $out = [];
    foreach ($kids as $s) { $p = sch_phone10($s['phone']);
        $sib = array_values(array_map(fn($x) => ['id' => $x['id'], 'name' => $x['name'], 'cls' => $x['cls']], array_filter($byPhone[$p] ?? [], fn($x) => $x['id'] !== $s['id'])));
        $out[] = ['id' => $s['id'], 'name' => $s['name'], 'section' => $s['section'], 'adm' => $s['adm'], 'father' => $s['father'], 'mother' => $s['mother'], 'phone' => $p, 'sib' => $sib,
            'res' => $res[$s['id']] ?? null, 'att' => $days ? ['days' => $days, 'present' => max(0, $days - ($abs[$s['id']] ?? 0))] : null, 'fee' => $fee[$s['id']] ?? null, 'visit' => $V[$date . '|' . $s['id']] ?? null]; }
    sch_json(['ok' => true, 'date' => $date, 'kids' => $out, 'tags' => PTM_TAGS, 'desk' => $desk]);
}
case 'visit': {
    if (!$desk) sch_fail('Only the PTM desk can save visits.', 403);
    $date = ptm_date($in['date'] ?? ''); $sid = (string)($in['sid'] ?? ''); $all = reg_students();
    if (!isset($all[$sid]) || !empty($all[$sid]['inactive'])) throw new InvalidArgumentException('Choose a child of the school.');
    $key = $date . '|' . $sid; $old = sch_rec_get('ptmVisit', $key) ?? [];
    $tags = array_values(array_intersect(PTM_TAGS, array_map(fn($t) => PTM_OLD[(string)$t] ?? (string)$t, (array)($in['tags'] ?? []))));
    $promise = (string)($in['promise'] ?? ''); if ($promise !== '' && !preg_match('/^20\d\d-\d\d-\d\d$/', $promise)) $promise = '';
    $mood = (int)($in['mood'] ?? 0); if ($mood < 0 || $mood > 4) $mood = 0;
    $paid = (float)preg_replace('/[^\d.]/', '', (string)($in['paid'] ?? ''));
    $cText = ptm_txt($in['complaint'] ?? '', 800);
    $cmp = $old['cmp'] ?? null;
    if ($cText !== '') $cmp = ['text' => $cText, 'cat' => in_array(PTM_OLD[(string)($in['cat'] ?? '')] ?? (string)($in['cat'] ?? ''), PTM_TAGS, true) ? (PTM_OLD[(string)$in['cat']] ?? (string)$in['cat']) : ($tags[0] ?? 'Other'), 'status' => ($cmp['status'] ?? '') === 'Resolved' && ($cmp['text'] ?? '') === $cText ? 'Resolved' : 'Open',
        'action' => (string)($cmp['action'] ?? ''), 'due' => preg_match('/^20\d\d-\d\d-\d\d$/', (string)($in['cdue'] ?? '')) ? (string)$in['cdue'] : (string)($cmp['due'] ?? ''), 'at' => $cmp['at'] ?? date('Y-m-d H:i')];
    elseif (!empty($in['clearComplaint'])) $cmp = null;
    $d = ['sid' => $sid, 'name' => $all[$sid]['name'], 'cls' => $all[$sid]['cls'], 'date' => $date, 'at' => $old['at'] ?? date('H:i'), 'by' => (string)$u['label'],
        'parent' => ptm_txt($in['parent'] ?? '', 80), 'phone' => sch_phone10((string)($in['phone'] ?? '')), 'slip' => !empty($in['slip']), 'paid' => $paid > 0 ? round($paid) : 0, 'promise' => $promise,
        'pamt' => (int)preg_replace('/\D/', '', (string)($in['pamt'] ?? '')), 'mood' => $mood, 'tags' => $tags, 'note' => ptm_txt($in['note'] ?? '', 800), 'cmp' => $cmp, 'with' => $old['with'] ?? '', 'sent' => $old['sent'] ?? null];
    sch_rec_put('ptmVisit', $key, $d, $uid);
    $also = [];
    if (!empty($in['siblings'])) { $p = sch_phone10($all[$sid]['phone']);
        foreach ($all as $x) if (empty($x['inactive']) && $x['id'] !== $sid && $p !== '' && sch_phone10($x['phone']) === $p) { $k2 = $date . '|' . $x['id'];
            if (!sch_rec_get('ptmVisit', $k2)) { sch_rec_put('ptmVisit', $k2, ['sid' => $x['id'], 'name' => $x['name'], 'cls' => $x['cls'], 'date' => $date, 'at' => date('H:i'), 'by' => (string)$u['label'], 'parent' => $d['parent'], 'phone' => $d['phone'],
                'slip' => false, 'paid' => 0, 'promise' => '', 'pamt' => 0, 'mood' => 0, 'tags' => [], 'note' => '', 'cmp' => null, 'with' => $all[$sid]['name']], $uid); $also[] = $x['name']; } } }
    sch_log($u, 'ptmVisit', 'PTM visit saved: ' . $all[$sid]['name'] . ' (' . $all[$sid]['cls'] . ')' . ($cmp && $cText !== '' ? ' with a complaint' : ''));
    sch_json(['ok' => true, 'visit' => $d, 'also' => $also]);
}
case 'sent': {
    // the result card / thank-you message was sent to the parent on WhatsApp from the desk (owner, 21 Nov 2026)
    if (!$desk) sch_fail('Only the PTM desk can do this.', 403);
    $key = ptm_date($in['date'] ?? '') . '|' . (string)($in['sid'] ?? ''); $v = sch_rec_get('ptmVisit', $key);
    if (!$v) throw new InvalidArgumentException('Save the visit first.');
    $v['sent'] = ['at' => date('H:i'), 'how' => in_array($in['how'] ?? '', ['msg', 'card'], true) ? $in['how'] : 'msg', 'by' => (string)$u['label']];
    sch_rec_put('ptmVisit', $key, $v, $uid); sch_json(['ok' => true, 'visit' => $v]);
}
case 'report': {
    $date = ptm_date($_GET['date'] ?? ''); $V = $visits($date . '|'); $all = reg_students(); $per = [];
    foreach ($all as $s) if (empty($s['inactive']) && $s['cls'] !== '') { $per[$s['cls']]['n'] = ($per[$s['cls']]['n'] ?? 0) + 1; if (!isset($V[$date . '|' . $s['id']])) $per[$s['cls']]['not'][] = ['id' => $s['id'], 'name' => $s['name'], 'father' => $s['father'], 'phone' => sch_phone10($s['phone'])]; }
    foreach ($V as $v) $per[$v['cls']]['came'] = ($per[$v['cls']]['came'] ?? 0) + 1;
    $open = array_values(array_filter($visits(''), fn($v) => !empty($v['cmp']) && (($v['cmp']['status'] ?? '') !== 'Resolved' || ($v['date'] ?? '') === $date)));
    usort($open, fn($x, $y) => strcmp((string)($y['date'] ?? ''), (string)($x['date'] ?? '')));
    sch_json(['ok' => true, 'date' => $date, 'visits' => array_values($V), 'classes' => $per, 'complaints' => $open, 'desk' => $desk, 'canResolve' => $desk || $role === 'chairman']);
}
case 'resolve': {
    if (!$desk && $role !== 'chairman') sch_fail('Not open for this login.', 403);
    $key = (string)($in['key'] ?? ''); if (!preg_match('/^20\d\d-\d\d-\d\d\|[^|]{1,80}$/', $key)) throw new InvalidArgumentException('Choose a complaint.');
    $v = sch_rec_get('ptmVisit', $key); if (!$v || empty($v['cmp'])) throw new InvalidArgumentException('This complaint is not there any more.');
    $v['cmp']['status'] = ($in['status'] ?? '') === 'Open' ? 'Open' : 'Resolved'; $v['cmp']['action'] = ptm_txt($in['action'] ?? '', 800); $v['cmp']['doneBy'] = (string)$u['label']; $v['cmp']['doneAt'] = date('Y-m-d H:i');
    sch_rec_put('ptmVisit', $key, $v, $uid); sch_log($u, 'ptmVisit', 'PTM complaint ' . strtolower($v['cmp']['status']) . ': ' . ($v['name'] ?? ''));
    sch_json(['ok' => true, 'visit' => $v]);
}
default: sch_fail('Unknown action.', 404);
}
} catch (InvalidArgumentException $e) { sch_fail($e->getMessage()); }
catch (Throwable $e) { error_log('ptm_api: ' . $e->getMessage()); sch_fail('Server problem. Please try again.', 500); }
