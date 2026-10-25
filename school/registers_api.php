<?php
// ============================================================
// School Office registers API (see registers_lib.php for the modules)
//
//   GET  ?a=meta                       modules this login may open + student/teacher pickers
//   GET  ?a=rows&m=<module>            rows of one register
//   GET  ?a=report&k=stock|cashbook    computed reports
//   POST ?a=save   {m, id?, row}       add / edit a row (auto number on add)
//   POST ?a=del    {m, id, reason}     remove a row (tombstone, reason logged)
//   POST ?a=import {m, rows[], apply}  Principal: preview, then apply; never overwrites
//   POST ?a=carry  {session, apply}    Principal: old ERP net due -> Fee Desk old due
// ============================================================
declare(strict_types=1);
require __DIR__ . '/inc.php';
require __DIR__ . '/registers_lib.php';

$a = (string)($_GET['a'] ?? 'meta');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
try { $db = sch_db(); } catch (Throwable $e) { sch_fail('Server is not reachable right now. Please try again in a few minutes.', 503); }
$u = sch_auth();
$role = (string)$u['role'];
$uid = sch_uid_of($u);
$M = reg_modules();
$in = [];
if ($method === 'POST') {
    $raw = file_get_contents('php://input', false, null, 0, 8000001) ?: '';
    if (strlen($raw) > 8000000) sch_fail('This file is too large. Import one class at a time.');
    $in = json_decode($raw, true);
    if (!is_array($in)) sch_fail('Bad request.');
}
function reg_mod(array $M, string $id): array { if (!isset($M[$id])) sch_fail('Unknown register.', 404); return $M[$id]; }
function reg_new_id(): string { return bin2hex(random_bytes(10)); }

try {
switch ($a) {
case 'meta': {
    $mods = [];
    foreach ($M as $id => $m) if (reg_can($m, $role, 'r')) {
        $mods[] = ['id' => $id, 'title' => $m['title'], 'group' => $m['group'], 'old' => $m['old'], 'fields' => $m['fields'], 'list' => $m['list'], 'sum' => $m['sum'] ?? [],
                   'write' => reg_can($m, $role, 'w'), 'import' => isset($m['keyFrom']) || isset($m['keyed']) ? reg_can($m, $role, 'import') : false,
                   'auto' => $m['auto'][0] ?? '', 'print' => $m['print'] ?? '', 'report' => $m['report'] ?? '', 'due' => $m['due'] ?? '', 'keyed' => $m['keyed'] ?? ''];
    }
    if (!$mods) sch_fail('School Office registers are not open for this login.', 403);
    $teachers = [];
    foreach ($db->query("SELECT r, d FROM sch_records WHERE k='teacher' AND deleted=0") as $row) { $t = json_decode((string)$row['d'], true); $teachers[] = ['id' => $row['r'], 'name' => (string)($t['name'] ?? $row['r'])]; }
    $set = sch_rec_get('settings', 'main') ?? [];
    sch_json(['ok' => true, 'role' => $role, 'modules' => $mods, 'students' => array_values(reg_students()), 'teachers' => $teachers,
              'school' => (string)($set['school'] ?? 'SN Mishra Singapore International School'), 'place' => (string)($set['place'] ?? 'Bhadohi'), 'session' => reg_session(), 'canCarry' => $role === 'principal']);
}
case 'rows': {
    $m = reg_mod($M, (string)($_GET['m'] ?? ''));
    if (!reg_can($m, $role, 'r')) sch_fail('This register is not open for your login.', 403);
    sch_json(['ok' => true, 'rows' => reg_rows($m['id'])]);
}
case 'report': {
    $k = (string)($_GET['k'] ?? '');
    $owner = ['stock' => 'item', 'cashbook' => 'cashbook'][$k] ?? null;
    if (!$owner || !reg_can($M[$owner], $role, 'r')) sch_fail('This report is not open for your login.', 403);
    sch_json(['ok' => true, 'report' => reg_report($k)]);
}
case 'save': {
    if ($method !== 'POST') sch_fail('POST only', 405);
    $m = reg_mod($M, (string)($in['m'] ?? ''));
    if (!reg_can($m, $role, 'w')) sch_fail('Only ' . implode(' / ', array_map('ucfirst', reg_roles($m['w']))) . ' can change ' . $m['title'] . '.', 403);
    if (!is_array($in['row'] ?? null)) sch_fail('Bad request.');
    $students = reg_students();
    $row = reg_clean($m, $in['row'], $students);
    $db->beginTransaction();
    $db->query("SELECT uid FROM sch_users WHERE uid='principal' FOR UPDATE")->fetch();
    $id = (string)($in['id'] ?? '');
    if (($m['keyed'] ?? '') === 'student') $id = substr(hash('sha256', $row['student'] . '|' . ($row['kind'] ?? '')), 0, 20);
    $old = null;
    if ($id !== '') {
        if (!preg_match('/^[a-f0-9]{16,40}$/', $id)) { $db->rollBack(); sch_fail('Refresh the page and try again.'); }
        $old = sch_rec_get('regRow', $m['id'] . '|' . $id);
    } else {
        $id = reg_new_id();
        if (count(reg_rows($m['id'])) >= REG_MAX_ROWS) { $db->rollBack(); sch_fail('This register is full. Ask the developer to archive old rows.'); }
    }
    if (isset($m['auto'])) {
        [$f, $prefix] = $m['auto'];
        if ($old && ($old[$f] ?? '') !== '') $row[$f] = $old[$f];              // a number once given never changes
        elseif (($row[$f] ?? '') === '') {
            $ss = reg_session() ?: date('Y');
            $key = $m['id'] . '|' . $ss;
            $seq = sch_rec_get('regSeq', $key) ?? ['n' => 0];
            $n = (int)$seq['n'] + 1;
            sch_rec_put('regSeq', $key, ['n' => $n], $uid);
            $row[$f] = $prefix . '/' . $ss . '/' . sprintf('%04d', $n);
        }
    }
    $row['_id'] = $id;
    $row['_by'] = (string)$u['label'];
    $row['_at'] = sch_now_ms();
    if ($old) { $row['_created'] = $old['_created'] ?? $old['_at'] ?? null; $row['_createdBy'] = $old['_createdBy'] ?? $old['_by'] ?? ''; }
    else { $row['_created'] = $row['_at']; $row['_createdBy'] = $row['_by']; }
    sch_rec_put('regRow', $m['id'] . '|' . $id, $row, $uid);
    $label = $row[$m['list'][0]] ?? '';
    if ($m['list'][0] === 'student' && isset($students[$label])) $label = $students[$label]['name'];
    sch_log($u, $m['title'], ($old ? 'Edited ' : 'Added ') . mb_substr((string)$label, 0, 80));
    $db->commit();
    sch_json(['ok' => true, 'row' => $row]);
}
case 'del': {
    if ($method !== 'POST') sch_fail('POST only', 405);
    $m = reg_mod($M, (string)($in['m'] ?? ''));
    $canDel = $m['w'] !== '' ? reg_can($m, $role, 'w') : $role === 'principal';
    if (!$canDel) sch_fail('You cannot remove rows from ' . $m['title'] . '.', 403);
    $id = (string)($in['id'] ?? '');
    $reason = trim((string)($in['reason'] ?? ''));
    if ($reason === '') sch_fail('Write why this row is being removed.');
    $old = sch_rec_get('regRow', $m['id'] . '|' . $id);
    if (!$old) sch_fail('This row is already removed.', 404);
    sch_rec_del('regRow', $m['id'] . '|' . $id, $uid);
    sch_log($u, $m['title'], 'Removed a row (' . mb_substr($reason, 0, 200) . ')');
    sch_json(['ok' => true]);
}
case 'bundle': {
    // Principal: one file with several steps from the old ERP — add rows to registers and move records to the right child —
    // run in order inside one transaction. Preview counts first; anything wrong stops everything.
    if ($method !== 'POST') sch_fail('POST only', 405);
    if ($role !== 'principal') sch_fail('Only the Principal can import from the old ERP.', 403);
    $steps = (array)($in['steps'] ?? []); if (!$steps || count($steps) > 20) sch_fail('The file has no steps.');
    $apply = ($in['apply'] ?? false) === true; $report = [];
    $db->beginTransaction();
    $db->query("SELECT uid FROM sch_users WHERE uid='principal' FOR UPDATE")->fetch();
    try {
        foreach ($steps as $n => $step) {
            $step = (array)$step; $kind = (string)($step['kind'] ?? ''); $rows = (array)($step['rows'] ?? []);
            if (!$rows || count($rows) > 5000) throw new InvalidArgumentException('Step ' . ($n + 1) . ' has no rows.');
            $students = reg_students(); $done = 0; $same = 0;
            if ($kind === 'import') {
                $m = $M[(string)($step['m'] ?? '')] ?? null;
                if (!$m || !(isset($m['keyFrom']) || isset($m['keyed'])) || !reg_can($m, $role, 'import')) throw new InvalidArgumentException('Step ' . ($n + 1) . ': unknown register.');
                $now = sch_now_ms();
                foreach ($rows as $i => $r) {
                    try { $c = reg_clean($m, (array)$r, $students, true); } catch (InvalidArgumentException $e) { throw new InvalidArgumentException('Step ' . ($n + 1) . ' row ' . ($i + 1) . ': ' . $e->getMessage()); }
                    $key = isset($m['keyFrom']) ? reg_key_for($m, $c) : substr(hash('sha256', $c['student'] . '|' . ($c['kind'] ?? '')), 0, 20);
                    $old = sch_rec_get('regRow', $m['id'] . '|' . $key);
                    if ($old) { foreach ($c as $k => $v) if (($old[$k] ?? null) !== $v) throw new InvalidArgumentException('Step ' . ($n + 1) . ' row ' . ($i + 1) . ': already here with different values (' . ($c['name'] ?? $key) . ').'); $same++; continue; }
                    sch_rec_put('regRow', $m['id'] . '|' . $key, $c + ['_id' => $key, '_by' => 'Old ERP import', '_at' => $now, '_created' => $now, '_createdBy' => 'Old ERP import (' . $u['label'] . ')'], $uid); $done++;
                }
                $report[] = ['step' => $n + 1, 'what' => 'Add to ' . $m['title'], 'new' => $done, 'already' => $same];
            } elseif ($kind === 'relink') {
                foreach ($rows as $i => $op) {
                    $op = (array)$op; $m = $M[(string)($op['m'] ?? '')] ?? null;
                    if (!$m || !($m['group'] === 'Old ERP Archive' || $m['id'] === 'studinfo')) throw new InvalidArgumentException('Step ' . ($n + 1) . ' row ' . ($i + 1) . ': only Old ERP records can be moved.');
                    $field = $m['id'] === 'oldfeecard' ? 'liveId' : 'student'; $to = (string)($op['to'] ?? ''); $reason = trim((string)($op['reason'] ?? ''));
                    if (mb_strlen($reason) < 4 || ($to !== '' && !isset($students[$to])) || ($to === '' && $field === 'student')) throw new InvalidArgumentException('Step ' . ($n + 1) . ' row ' . ($i + 1) . ': wrong child or no reason.');
                    $key0 = (array)($op['key'] ?? []);
                    $id = isset($m['keyFrom']) ? reg_key_for($m, $key0) : substr(hash('sha256', (string)($key0['student'] ?? '') . '|' . (string)($key0['kind'] ?? '')), 0, 20);
                    $old = sch_rec_get('regRow', $m['id'] . '|' . $id);
                    $fits = function (?array $r) use ($op): bool { if (!$r) return false; foreach ((array)($op['match'] ?? []) as $k => $v) if ((string)($r[$k] ?? '') !== (string)$v) return false; return true; };
                    if (!$fits($old)) {   // the record at this place is another child's (file already run): fine if the moved one is where it should be
                        $tk = isset($m['keyFrom']) ? reg_key_for($m, [$field => $to] + $key0) : substr(hash('sha256', $to . '|' . (string)($key0['kind'] ?? '')), 0, 20);
                        if ($fits(sch_rec_get('regRow', $m['id'] . '|' . $tk))) { $same++; continue; }
                        throw new InvalidArgumentException('Step ' . ($n + 1) . ' row ' . ($i + 1) . ': record not found.');
                    }
                    if ((string)($old[$field] ?? '') === $to) { $same++; continue; }
                    $new = $old; $new[$field] = $to;
                    $key = isset($m['keyFrom']) ? reg_key_for($m, $new) : substr(hash('sha256', $new['student'] . '|' . ($new['kind'] ?? '')), 0, 20);
                    if ($key !== $id && ($there = sch_rec_get('regRow', $m['id'] . '|' . $key))) {
                        $bare = fn(array $r) => array_diff_key($r, array_flip([$field, '_id', '_by', '_at', '_created', '_createdBy', '_relinked', '_ver']));
                        if ($bare($there) == $bare($old)) { $same++; continue; }   // a file run twice: this record was already moved
                        throw new InvalidArgumentException('Step ' . ($n + 1) . ' row ' . ($i + 1) . ': that child already has this record.');
                    }
                    sch_rec_put('regHist', $m['id'] . '|' . $id . '|' . sch_now_ms() . $i, ['row' => $old, 'reason' => $reason, 'by' => $u['label']], $uid);
                    if ($key !== $id) sch_rec_del('regRow', $m['id'] . '|' . $id, $uid);
                    $new['_id'] = $key; $new['_relinked'] = mb_substr($reason, 0, 200);
                    sch_rec_put('regRow', $m['id'] . '|' . $key, $new, $uid); $done++;
                }
                $report[] = ['step' => $n + 1, 'what' => 'Move old records to the right child', 'new' => $done, 'already' => $same];
            } else throw new InvalidArgumentException('Step ' . ($n + 1) . ' is not known.');
        }
    } catch (InvalidArgumentException $e) { $db->rollBack(); sch_fail($e->getMessage() . ' Nothing was changed.'); }
    if ($apply) { sch_log($u, 'Old ERP Import', 'Bundle ' . mb_substr((string)($in['title'] ?? ''), 0, 80) . ': ' . json_encode($report)); $db->commit(); }
    else $db->rollBack();   // preview: everything was tried, then undone
    sch_json(['ok' => true, 'applied' => $apply, 'steps' => $report]);
}
case 'relink': case 'relinkBatch': {
    // Principal: move Old ERP records to the right child (a wrong name match). Only the link changes;
    // the old version is kept in regHist with the reason. relinkBatch takes a file of such moves (preview, then apply).
    if ($method !== 'POST') sch_fail('POST only', 405);
    if ($role !== 'principal') sch_fail('Only the Principal can change which child an old record belongs to.', 403);
    $ops = $a === 'relink' ? [['m' => $in['m'] ?? '', 'id' => $in['id'] ?? '', 'to' => $in['to'] ?? '', 'reason' => $in['reason'] ?? '']] : (array)($in['rows'] ?? []);
    if (!$ops || count($ops) > 3000) sch_fail('Give 1 to 3000 records to move.');
    $apply = $a === 'relink' || ($in['apply'] ?? false) === true;
    $students = reg_students(); $plan = []; $errors = []; $already = 0;
    $db->beginTransaction();
    $db->query("SELECT uid FROM sch_users WHERE uid='principal' FOR UPDATE")->fetch();
    foreach ($ops as $i => $op) {
        try {
            $op = (array)$op; $m = $M[(string)($op['m'] ?? '')] ?? null; if (!$m) throw new InvalidArgumentException('unknown register');
            $field = $m['id'] === 'oldfeecard' ? 'liveId' : 'student';
            if (!($m['group'] === 'Old ERP Archive' || $m['id'] === 'studinfo')) throw new InvalidArgumentException('only Old ERP records can be moved');
            $to = (string)($op['to'] ?? ''); $reason = trim((string)($op['reason'] ?? ''));
            if (mb_strlen($reason) < 4) throw new InvalidArgumentException('write why');
            if ($to !== '' && !isset($students[$to])) throw new InvalidArgumentException('child ' . $to . ' is not in the ERP');
            if ($to === '' && $field === 'student') throw new InvalidArgumentException('choose the child');
            $id = (string)($op['id'] ?? '');
            if ($id === '' && is_array($op['key'] ?? null)) $id = isset($m['keyFrom']) ? reg_key_for($m, (array)$op['key']) : substr(hash('sha256', (string)($op['key']['student'] ?? '') . '|' . (string)($op['key']['kind'] ?? '')), 0, 20);
            $old = sch_rec_get('regRow', $m['id'] . '|' . $id);
            if (!$old) throw new InvalidArgumentException('record not found');
            if ((string)($old[$field] ?? '') === $to) { $already++; continue; }
            $new = $old; $new[$field] = $to;
            $key = isset($m['keyFrom']) ? reg_key_for($m, $new) : substr(hash('sha256', $new['student'] . '|' . ($new['kind'] ?? '')), 0, 20);
            if ($key !== $id && (sch_rec_get('regRow', $m['id'] . '|' . $key) || isset($plan[$m['id'] . '|' . $key]))) throw new InvalidArgumentException('that child already has this record');
            $plan[$m['id'] . '|' . $key] = [$m, $id, $key, $old, $new, $field, $to, $reason];
        } catch (InvalidArgumentException $e) { $errors[] = 'Row ' . ($i + 1) . ': ' . $e->getMessage(); if (count($errors) >= 25) break; }
    }
    if ($errors) { $db->rollBack(); sch_json(['ok' => false, 'error' => 'Nothing was changed. Fix these rows first.', 'rows' => $errors], 400); }
    if ($apply) {
        foreach ($plan as [$m, $id, $key, $old, $new, $field, $to, $reason]) {
            sch_rec_put('regHist', $m['id'] . '|' . $id . '|' . sch_now_ms(), ['row' => $old, 'reason' => $reason, 'by' => $u['label']], $uid);
            if ($key !== $id) sch_rec_del('regRow', $m['id'] . '|' . $id, $uid);
            $new['_id'] = $key; $new['_relinked'] = mb_substr($reason, 0, 200);
            sch_rec_put('regRow', $m['id'] . '|' . $key, $new, $uid);
        }
        sch_log($u, 'Old ERP Import', count($plan) . ' old records moved to the right child; ' . $already . ' already right');
    }
    $db->commit();
    sch_json(['ok' => true, 'applied' => $apply, 'toMove' => count($plan), 'already' => $already, 'total' => count($ops), 'id' => $a === 'relink' && $plan ? array_values($plan)[0][2] : null]);
}
case 'import': {
    if ($method !== 'POST') sch_fail('POST only', 405);
    $m = reg_mod($M, (string)($in['m'] ?? ''));
    if (!(isset($m['keyFrom']) || isset($m['keyed'])) || !reg_can($m, $role, 'import')) sch_fail('Only the Principal can import into ' . $m['title'] . '.', 403);
    $rows = $in['rows'] ?? null;
    if (!is_array($rows) || !array_is_list($rows) || !$rows || count($rows) > 5000) sch_fail('Import must contain 1 to 5000 rows.');
    $students = reg_students();
    $clean = []; $errors = [];
    foreach ($rows as $i => $r) {
        try {
            if (!is_array($r)) throw new InvalidArgumentException('not a row');
            $c = reg_clean($m, $r, $students, true);
            $key = isset($m['keyFrom']) ? reg_key_for($m, $c) : substr(hash('sha256', $c['student'] . '|' . ($c['kind'] ?? '')), 0, 20);
            if (isset($clean[$key])) throw new InvalidArgumentException('repeated in this file');
            $clean[$key] = $c;
        } catch (InvalidArgumentException $e) { $errors[] = 'Row ' . ($i + 1) . ': ' . $e->getMessage(); if (count($errors) >= 25) break; }
    }
    if ($errors) sch_json(['ok' => false, 'error' => 'Nothing was imported. Fix these rows first.', 'rows' => $errors], 400);
    $apply = ($in['apply'] ?? false) === true;
    // Refresh (cut-over day): a newer copy of the old ERP fee cards / receipts replaces the rows that changed there.
    // Only these three, whose key never depends on the matched child; the old version goes to regHist; a child
    // already linked here (incl. a corrected link) keeps that link; nothing is removed; a session whose
    // Fee Receipt is started is never refreshed (from then on the new ERP is the record).
    $refresh = ($in['refresh'] ?? false) === true;
    if ($refresh && ($role !== 'principal' || !in_array($m['id'], REG_REFRESH, true))) sch_fail('Only the Principal can refresh ' . $m['title'] . ' from the old ERP.', 403);
    if ($refresh) {
        require_once __DIR__ . '/feecollect_lib.php';
        $liveSes = array_values(array_unique(array_filter(array_map(fn($c) => ofc_live((string)$c['session']) ? (string)$c['session'] : '', $clean))));
        if ($liveSes) sch_fail('Fee Receipt is already started here for ' . implode(', ', $liveSes) . ', so old ERP copies of that session cannot change any more. Nothing was imported.', 409);
    }
    $link = $m['id'] === 'oldfeecard' ? 'liveId' : 'student';
    $db->beginTransaction();
    $db->query("SELECT uid FROM sch_users WHERE uid='principal' FOR UPDATE")->fetch();
    $new = []; $same = 0; $conflicts = []; $replace = [];
    foreach ($clean as $key => $c) {
        $old = sch_rec_get('regRow', $m['id'] . '|' . $key);
        if (!$old) { $new[$key] = $c; continue; }
        if ($refresh && (string)($old[$link] ?? '') !== '') $c[$link] = $old[$link];
        $equal = true; foreach ($c as $k => $v) if (($old[$k] ?? null) !== $v) { $equal = false; break; }
        if ($equal) { $same++; continue; }
        if ($refresh) $replace[$key] = [$old, $c]; else $conflicts[] = $c[$m['list'][1] ?? $m['list'][0]] ?? $key;
    }
    if ($apply && $conflicts) { $db->rollBack(); sch_fail('Import stopped: ' . count($conflicts) . ' rows already exist with different values. Nothing changed.', 409); }
    if ($apply) {
        $now = sch_now_ms(); $i = 0;
        foreach ($new as $key => $c) sch_rec_put('regRow', $m['id'] . '|' . $key, $c + ['_id' => $key, '_by' => 'Old ERP import', '_at' => $now, '_created' => $now, '_createdBy' => 'Old ERP import (' . $u['label'] . ')'], $uid);
        foreach ($replace as $key => [$old, $c]) {
            sch_rec_put('regHist', $m['id'] . '|' . $key . '|' . $now . ($i++), ['row' => $old, 'reason' => 'Newer copy from the old ERP', 'by' => $u['label']], $uid);
            $meta = array_filter($old, fn($k) => is_string($k) && str_starts_with($k, '_'), ARRAY_FILTER_USE_KEY);
            sch_rec_put('regRow', $m['id'] . '|' . $key, $c + ['_by' => 'Old ERP refresh (' . $u['label'] . ')', '_at' => $now] + $meta, $uid);
        }
        sch_log($u, 'Old ERP Import', count($new) . ' rows imported into ' . $m['title'] . ($refresh ? '; ' . count($replace) . ' replaced by a newer old ERP copy (old kept in history)' : '') . '; ' . $same . ' already present');
    }
    $db->commit();
    sch_json(['ok' => true, 'applied' => $apply, 'refresh' => $refresh, 'total' => count($clean), 'toAdd' => count($new), 'added' => $apply ? count($new) : 0, 'toReplace' => count($replace), 'replaced' => $apply ? count($replace) : 0, 'unchanged' => $same, 'conflicts' => array_slice($conflicts, 0, 50)]);
}
case 'carry': {
    // Old ERP net due of a session becomes the Fee Desk "old due" of each student,
    // only where the Fee Desk has no old due yet. Preview first; nothing is overwritten.
    if ($method !== 'POST') sch_fail('POST only', 405);
    if ($role !== 'principal') sch_fail('Only the Principal can carry old dues.', 403);
    $ss = (string)($in['session'] ?? '');
    $rows = array_values(array_filter(reg_rows('oldfee'), fn($r) => $r['session'] === $ss));
    if (!$rows) sch_fail('No Old ERP Fee Statement rows for session ' . $ss . '. Import them first.');
    $apply = ($in['apply'] ?? false) === true;
    $db->beginTransaction();
    $db->query("SELECT uid FROM sch_users WHERE uid='principal' FOR UPDATE")->fetch();
    $plan = []; $skipped = [];
    foreach ($rows as $r) {
        $sid = $r['student']; $due = (int)($r['due'] ?? 0);
        if (str_starts_with((string)$sid, 'old-')) { $skipped[] = ($r['name'] ?? $sid) . ' (inactive)'; continue; }   // inactive children have no Fee Desk account
        $st = sch_rec_get('acctStu', $sid) ?? [];
        if ((float)($st['opening'] ?? 0) != 0) { $skipped[] = $r['name'] ?? $sid; continue; }
        if ($due === 0) continue;
        $plan[] = ['sid' => $sid, 'name' => $r['name'] ?? '', 'due' => $due];
        if ($apply) { $st['opening'] = $due / 100; $st['note'] = trim(($st['note'] ?? '') . ' Old ERP net due ' . $ss . ' carried on ' . date('Y-m-d') . '.'); sch_rec_put('acctStu', $sid, $st, $uid); }
    }
    if ($apply) sch_log($u, 'Old ERP Import', 'Old dues of ' . $ss . ' carried to Fee Desk for ' . count($plan) . ' students (₹' . number_format(array_sum(array_column($plan, 'due')) / 100) . ')');
    $db->commit();
    sch_json(['ok' => true, 'applied' => $apply, 'students' => count($plan), 'total' => array_sum(array_column($plan, 'due')), 'skipped' => $skipped, 'plan' => $plan]);
}
default: sch_fail('Unknown action.', 404);
}
} catch (InvalidArgumentException $e) {
    if ($db->inTransaction()) $db->rollBack();
    sch_fail($e->getMessage());
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    error_log('registers_api: ' . $e->getMessage());
    sch_fail('Something went wrong. Nothing was saved. Please try again.', 500);
}
