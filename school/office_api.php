<?php
// ============================================================
// School office API — maakit.in/school/office_api.php
//
// Kaun kya kar sakta hai:
//   Accountant (Akash Tiwari): fees jama + raseed, bachche ka pichhla bakaya /
//                alag mahina fees / free scheme / chhoot, calls bhi
//   Reception  (Shiva Rai):    bakaya list, phone + WhatsApp, call ka nateeja
//   Principal:  sab kuchh + raseed radd (cancel, wajah ke saath)
//   Chairman:   sirf dekh sakte hain
//   Parent / Student: sirf apne bachche ka bakaya aur raseedein (?a=mine)
//
//   GET  ?a=data                        sab bachche + bakaya + raseedein + calls
//   POST ?a=pay    {sid, amt, mode, ref, date, note, nonce}
//   POST ?a=cancel {no, reason}
//   POST ?a=setstu {sid, opening, custom, free, scheme, concession, note}
//   POST ?a=call   {sid, outcome, promise, note}
//   GET  ?a=mine
//
// Records (aam sync se bahar, "acct" prefix): acctPay (raseed, r = 000001),
// acctSeq (raseed number), acctNonce (do baar dabane par do raseed nahi),
// acctStu (bachche ki fee setting), acctCall (call ka nateeja).
// ============================================================
declare(strict_types=1);
require __DIR__ . '/inc.php';
require __DIR__ . '/office_lib.php';

$a = (string)($_GET['a'] ?? '');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
try { $db = sch_db(); } catch (Throwable $e) { sch_fail('Server is not reachable right now. Please try again in a few minutes.', 503); }
$in = [];
if ($method === 'POST') {
    $in = json_decode(file_get_contents('php://input', false, null, 0, 200000) ?: '', true);
    if (!is_array($in)) sch_fail('Bad request.');
}

function of_rows(string $k): array {
    $q = sch_db()->prepare('SELECT r, d FROM sch_records WHERE k=? AND deleted=0');
    $q->execute([$k]);
    $out = [];
    foreach ($q as $row) { $d = json_decode((string)$row['d'], true); if (is_array($d)) $out[(string)$row['r']] = $d; }
    return $out;
}
function of_students(): array {
    $out = [];
    foreach (of_rows('student') as $s) {
        if (!isset($s['id'])) continue;
        $out[(string)$s['id']] = ['id' => (string)$s['id'], 'name' => (string)($s['name'] ?? ''), 'cls' => (string)($s['cls'] ?? ''), 'adm' => (string)($s['adm'] ?? ''),
                                  'father' => (string)($s['father'] ?? ''), 'phone' => (string)($s['phone'] ?? ''), 'km' => $s['km'] ?? null];
    }
    return $out;
}
function of_text($v, int $max): string { return mb_substr(trim(preg_replace('/[\x00-\x1F\x7F]/u', ' ', (string)$v)), 0, $max); }
function of_date($v, bool $future = false): string {
    $d = (string)$v; $x = DateTimeImmutable::createFromFormat('!Y-m-d', $d);
    if (!$x || $x->format('Y-m-d') !== $d) sch_fail('Choose a valid date.');
    if (!$future && $d > date('Y-m-d')) sch_fail('The date cannot be in the future.');
    if ($d < date('Y-m-d', time() - 400 * 86400) || $d > date('Y-m-d', time() + 120 * 86400)) sch_fail('That date is too far away.');
    return $d;
}
function of_rupees($v, int $min, int $max, string $what): int {
    if (is_string($v)) $v = trim($v);
    if ($v === '' || $v === null) return 0;
    if (!is_numeric($v) || (float)$v != floor((float)$v)) sch_fail("$what must be whole rupees.");
    $n = (int)$v; if ($n < $min || $n > $max) sch_fail("$what must be between ₹$min and ₹" . number_format($max) . '.');
    return $n;
}
function of_rs(int $n): string { return '₹' . number_format($n); }

// Sabka hisaab ek saath (family = same mobile number, teesre bachche ki chhoot ke liye)
function of_all(array $students, ?array $only = null): array {
    $F = sch_rec_get('fees', 'main'); $ss = of_session(); $today = date('Y-m-d');
    $sets = of_rows('acctStu'); $pays = array_values(of_rows('acctPay'));
    $fam = [];
    foreach ($students as $s) $fam[of_phone_key($s)][] = $s;
    $out = [];
    foreach ($students as $id => $s) {
        if ($only !== null && !in_array($id, $only, true)) continue;
        $out[$id] = $s + ['fam' => of_phone_key($s), 'dues' => of_dues($s, $fam[of_phone_key($s)], $F, $sets[$id] ?? [], $pays, $ss['start'], $today), 'set' => $sets[$id] ?? new stdClass()];
    }
    return $out;
}

try {
switch ($a) {

case 'data': {
    $u = sch_auth();
    if (!in_array($u['role'], ['principal', 'accountant', 'reception', 'chairman'], true)) sch_fail('Not allowed.', 403);
    $ss = of_session(); $st = of_students();
    $pays = array_values(array_filter(of_rows('acctPay'), fn($p) => (string)($p['date'] ?? '') >= date('Y-m-d', strtotime($ss['start']) - 31 * 86400)));
    usort($pays, fn($x, $y) => strcmp((string)$y['no'], (string)$x['no']));
    $from = date('Y-m-d', time() - 120 * 86400);
    $calls = array_values(array_filter(of_rows('acctCall'), fn($c) => (string)($c['date'] ?? '') >= $from));
    usort($calls, fn($x, $y) => ($y['at'] ?? 0) <=> ($x['at'] ?? 0));
    sch_json(['ok' => true, 'me' => ['role' => $u['role'], 'tid' => $u['tid'], 'label' => $u['label']], 'school' => $ss['school'], 'place' => $ss['place'], 'session' => $ss['session'],
              'sessionStart' => $ss['start'], 'today' => date('Y-m-d'), 'names' => of_names(), 'fees' => sch_rec_get('fees', 'main'),
              'students' => array_values(of_all($st)), 'payments' => $pays, 'calls' => $calls, 'modes' => OF_MODES, 'outcomes' => OF_OUTCOMES,
              'can' => ['pay' => in_array($u['role'], ['principal', 'accountant'], true), 'set' => in_array($u['role'], ['principal', 'accountant'], true),
                        'call' => in_array($u['role'], ['principal', 'accountant', 'reception'], true), 'cancel' => $u['role'] === 'principal']]);
}

case 'pay': {
    if ($method !== 'POST') sch_fail('POST only', 405);
    $u = sch_auth();
    if (!in_array($u['role'], ['principal', 'accountant'], true)) sch_fail('Only the Accountant or the Principal can take fees.', 403);
    $sid = (string)($in['sid'] ?? ''); $st = of_students();
    if (!isset($st[$sid])) sch_fail('Student not found.');
    $amt = of_rupees($in['amt'] ?? '', 1, 500000, 'Amount');
    if ($amt < 1) sch_fail('Enter the amount.');
    $mode = (string)($in['mode'] ?? '');
    if (!in_array($mode, OF_MODES, true)) sch_fail('Choose how the fee was paid.');
    $ref = of_text($in['ref'] ?? '', 60);
    if (in_array($mode, ['Cheque', 'DD'], true) && $ref === '') sch_fail("Enter the $mode number.");
    $date = of_date($in['date'] ?? date('Y-m-d'));
    $note = of_text($in['note'] ?? '', 200);
    $nonce = preg_replace('/[^a-z0-9]/', '', strtolower((string)($in['nonce'] ?? '')));
    if (strlen($nonce) < 12 || strlen($nonce) > 40) sch_fail('Please refresh the page and try again.');
    $uid = sch_uid_of($u);
    // Counter row is made once, outside the lock; then every desk waits its turn on that one row.
    $db->prepare("INSERT IGNORE INTO sch_records (k,r,d,deleted,dkey,ver,by_uid,updated_at) VALUES ('acctSeq','main','{\"n\":0}',0,NULL,?,?,NOW())")->execute([sch_now_ms(), $uid]);
    $db->beginTransaction();
    $seq = json_decode((string)$db->query("SELECT d FROM sch_records WHERE k='acctSeq' AND r='main' FOR UPDATE")->fetchColumn(), true);
    $old = sch_rec_get('acctNonce', $nonce);
    if ($old) { $db->commit(); sch_json(['ok' => true, 'receipt' => sch_rec_get('acctPay', (string)$old['r']), 'again' => true]); }
    $n = (int)($seq['n'] ?? 0) + 1; $r = sprintf('%06d', $n);
    $s = $st[$sid];
    $p = ['no' => $n, 'sid' => $sid, 'name' => $s['name'], 'cls' => $s['cls'], 'adm' => $s['adm'], 'father' => $s['father'], 'amt' => $amt, 'words' => of_words($amt) . ' Rupees Only',
          'mode' => $mode, 'ref' => $ref, 'date' => $date, 'note' => $note, 'by' => $u['label'], 'at' => sch_now_ms(), 'session' => of_session()['session']];
    sch_rec_put('acctPay', $r, $p, $uid);
    sch_rec_put('acctSeq', 'main', ['n' => $n], $uid);
    sch_rec_put('acctNonce', $nonce, ['r' => $r], $uid);
    sch_log($u, 'Fee', "Receipt #$n: " . of_rs($amt) . " from {$s['name']} (Class {$s['cls']}) by $mode" . ($ref !== '' ? " ($ref)" : '') . ($date !== date('Y-m-d') ? ", dated $date" : ''));
    $db->commit();
    $after = of_all($st, [$sid])[$sid]['dues'];
    sch_json(['ok' => true, 'receipt' => $p, 'due' => $after['due']]);
}

case 'cancel': {
    if ($method !== 'POST') sch_fail('POST only', 405);
    $u = sch_auth();
    if ($u['role'] !== 'principal') sch_fail('Only the Principal can cancel a receipt.', 403);
    $r = sprintf('%06d', (int)($in['no'] ?? 0)); $reason = of_text($in['reason'] ?? '', 200);
    if ($reason === '') sch_fail('Write why the receipt is cancelled.');
    $db->beginTransaction();
    $db->query("SELECT uid FROM sch_users WHERE uid='principal' FOR UPDATE")->fetch();
    $p = sch_rec_get('acctPay', $r);
    if (!$p) { $db->rollBack(); sch_fail('Receipt not found.'); }
    if (!empty($p['cancelled'])) { $db->rollBack(); sch_fail('This receipt is already cancelled.'); }
    $p['cancelled'] = true; $p['cancelReason'] = $reason; $p['cancelBy'] = $u['label']; $p['cancelAt'] = sch_now_ms();
    sch_rec_put('acctPay', $r, $p, sch_uid_of($u));
    sch_log($u, 'Fee', "Receipt #{$p['no']} (" . of_rs((int)$p['amt']) . ", {$p['name']}) CANCELLED: $reason");
    $db->commit();
    sch_json(['ok' => true]);
}

case 'setstu': {
    if ($method !== 'POST') sch_fail('POST only', 405);
    $u = sch_auth();
    if (!in_array($u['role'], ['principal', 'accountant'], true)) sch_fail('Only the Accountant or the Principal can change this.', 403);
    $sid = (string)($in['sid'] ?? ''); $st = of_students();
    if (!isset($st[$sid])) sch_fail('Student not found.');
    $cust = $in['custom'] ?? '';
    $d = ['opening' => of_rupees($in['opening'] ?? 0, -500000, 500000, 'Old due'),
          'custom' => ($cust === '' || $cust === null) ? null : of_rupees($cust, 0, 100000, 'Monthly fee'),
          'free' => ($in['free'] ?? false) === true, 'scheme' => of_text($in['scheme'] ?? '', 60),
          'concession' => of_rupees($in['concession'] ?? 0, 0, 500000, 'Concession'), 'note' => of_text($in['note'] ?? '', 200),
          'by' => $u['label'], 'at' => sch_now_ms()];
    $old = sch_rec_get('acctStu', $sid) ?? [];
    $ch = [];
    foreach (['opening' => 'old due', 'custom' => 'own monthly fee', 'concession' => 'concession'] as $k => $w)
        if (($old[$k] ?? ($k === 'custom' ? null : 0)) !== $d[$k]) $ch[] = "$w " . ($d[$k] === null ? 'removed' : of_rs((int)$d[$k]));
    if (!empty($old['free']) !== $d['free']) $ch[] = $d['free'] ? 'free scheme ON' . ($d['scheme'] !== '' ? " ({$d['scheme']})" : '') : 'free scheme OFF';
    elseif (($old['scheme'] ?? '') !== $d['scheme']) $ch[] = 'scheme "' . $d['scheme'] . '"';
    if (($old['note'] ?? '') !== $d['note']) $ch[] = 'note changed';
    sch_rec_put('acctStu', $sid, $d, sch_uid_of($u));
    if ($ch) sch_log($u, 'Fee', "{$st[$sid]['name']} (Class {$st[$sid]['cls']}): " . implode(', ', $ch));
    sch_json(['ok' => true, 'dues' => of_all($st, [$sid])[$sid]['dues']]);
}

case 'call': {
    if ($method !== 'POST') sch_fail('POST only', 405);
    $u = sch_auth();
    if (!in_array($u['role'], ['principal', 'accountant', 'reception'], true)) sch_fail('Not allowed.', 403);
    $sid = (string)($in['sid'] ?? ''); $st = of_students();
    if (!isset($st[$sid])) sch_fail('Student not found.');
    $out = (string)($in['outcome'] ?? '');
    if (!in_array($out, OF_OUTCOMES, true)) sch_fail('Choose what happened on the call.');
    $promise = (string)($in['promise'] ?? '');
    if ($promise !== '') $promise = of_date($promise, true);
    if (in_array($out, ['Promised to pay', 'Call back later', 'Will visit school'], true) && $promise === '') sch_fail('Choose the date they gave.');
    $key = of_phone_key($st[$sid]);
    $kids = array_values(array_map(fn($s) => $s['id'], array_filter($st, fn($s) => of_phone_key($s) === $key)));
    $id = bin2hex(random_bytes(8));
    $c = ['id' => $id, 'fam' => $key, 'sids' => $kids, 'outcome' => $out, 'promise' => $promise, 'note' => of_text($in['note'] ?? '', 200),
          'by' => $u['label'], 'at' => sch_now_ms(), 'date' => date('Y-m-d')];
    sch_rec_put('acctCall', $id, $c, sch_uid_of($u));
    sch_log($u, 'Fee Call', "Called family of {$st[$sid]['name']} (Class {$st[$sid]['cls']}): $out" . ($promise !== '' ? " — date $promise" : ''));
    sch_json(['ok' => true, 'call' => $c]);
}

case 'mine': {
    $u = sch_auth();
    if (!in_array($u['role'], ['parent', 'student'], true)) sch_fail('Not allowed.', 403);
    $st = of_students();
    $ids = $u['role'] === 'student' ? [(string)$u['tid']] : array_values(array_map(fn($s) => (string)$s['id'], sch_children((string)$u['tid'])));
    $ids = array_values(array_filter($ids, fn($i) => isset($st[$i])));
    $all = of_all($st, $ids); $ss = of_session();
    $pays = array_values(array_filter(of_rows('acctPay'), fn($p) => in_array($p['sid'] ?? '', $ids, true) && empty($p['cancelled']) && (string)$p['date'] >= $ss['start']));
    usort($pays, fn($x, $y) => strcmp((string)$y['date'], (string)$x['date']));
    $kids = array_map(fn($i) => ['id' => $i, 'name' => $all[$i]['name'], 'cls' => $all[$i]['cls'], 'dues' => array_intersect_key($all[$i]['dues'], array_flip(['monthly', 'months', 'yearly', 'opening', 'concession', 'paid', 'due', 'free', 'missing']))], $ids);
    $clean = array_map(fn($p) => array_intersect_key($p, array_flip(['no', 'sid', 'name', 'cls', 'amt', 'words', 'mode', 'date'])), $pays);
    sch_json(['ok' => true, 'kids' => $kids, 'receipts' => $clean, 'sessionStart' => $ss['start'], 'school' => $ss['school']]);
}

default: sch_fail('Unknown action.', 404);
}
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    if ($e instanceof PDOException) error_log('office_api: ' . $e->getMessage());
    sch_fail('Something went wrong. Please try again.', 500);
}
