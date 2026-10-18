<?php
// ============================================================
// Staff salary API — maakit.in/school/salary_api.php
//
//   GET  ?a=sheet&ym=2026-10      Principal / Accountant — poore staff ki sheet
//   POST ?a=staff  {id?, name, role, monthly, paidLeave, active}   sirf Principal
//        (teacher ke liye id = "t:<tid>"; naya non-teaching staff: id khaali)
//   POST ?a=adjust {ym, id, advance, bonus, manualAbsent, note}    Principal / Accountant
//   POST ?a=pay    {ym, id, mode, ref, date}                       Principal / Accountant
//   POST ?a=unpay  {ym, id, reason}                                sirf Principal
//   GET  ?a=mine                  Teacher — sirf apni paid salary slips
//
// Salary ki baat kisi aur ko nahi dikhni chahiye: records "sal" prefix
// (salStaff, salMonth) aam sync / load me kabhi nahi jaate.
// ============================================================
declare(strict_types=1);
require __DIR__ . '/inc.php';
require __DIR__ . '/salary_lib.php';

$a = (string)($_GET['a'] ?? '');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
try { $db = sch_db(); } catch (Throwable $e) { sch_fail('Server is not reachable right now. Please try again in a few minutes.', 503); }
$in = [];
if ($method === 'POST') { $in = json_decode(file_get_contents('php://input', false, null, 0, 100000) ?: '', true); if (!is_array($in)) sch_fail('Bad request.'); }
function sal_need(array $u, array $roles): void { if (!in_array($u['role'], $roles, true)) sch_fail('Not allowed.', 403); }
function sal_text($v, int $max): string { return mb_substr(trim(preg_replace('/[\x00-\x1F\x7F]/u', ' ', (string)$v)), 0, $max); }
function sal_find(string $id): array { $all = sal_staff_list(); if (!isset($all[$id])) sch_fail('Staff member not found.'); return $all[$id]; }
function sal_ym_in(array $in): string { $ym = (string)($in['ym'] ?? ''); if (!sal_ym_ok($ym) || $ym > date('Y-m', strtotime('+1 month'))) sch_fail('Choose a month.'); return $ym; }

try {
switch ($a) {

case 'sheet': {
    $u = sch_auth(); sal_need($u, ['principal', 'accountant']);
    $ym = (string)($_GET['ym'] ?? date('Y-m'));
    if (!sal_ym_ok($ym)) sch_fail('Choose a month.');
    sch_json(['ok' => true, 'can' => ['staff' => $u['role'] === 'principal', 'unpay' => $u['role'] === 'principal'], 'modes' => SAL_MODES, 'today' => date('Y-m-d')] + sal_sheet($ym));
}

case 'staff': {
    if ($method !== 'POST') sch_fail('POST only', 405);
    $u = sch_auth(); sal_need($u, ['principal']);
    $id = (string)($in['id'] ?? '');
    try {
        $monthly = ($in['monthly'] ?? '') === '' || ($in['monthly'] ?? null) === null ? null : sal_rupees($in['monthly'], 0, 500000, 'Monthly salary');
        $pl = $in['paidLeave'] ?? 1;
        if (!is_numeric($pl) || $pl < 0 || $pl > 10 || fmod((float)$pl * 2, 1) != 0) throw new InvalidArgumentException('Paid leave must be 0 to 10 days (half days allowed).');
    } catch (InvalidArgumentException $e) { sch_fail($e->getMessage()); }
    $active = ($in['active'] ?? true) !== false;
    if ($id === '') {                                   // naya non-teaching staff
        $name = sal_text($in['name'] ?? '', 60); $role = sal_text($in['role'] ?? '', 40);
        if (mb_strlen($name) < 2) sch_fail('Write the name.');
        $id = 'x' . bin2hex(random_bytes(6));
        $d = ['name' => $name, 'role' => $role !== '' ? $role : 'Staff', 'monthly' => $monthly, 'paidLeave' => (float)$pl, 'active' => $active];
        $what = "added $name (" . $d['role'] . ')';
    } else {
        $s = sal_find($id); $old = sal_rows('salStaff')[$id] ?? [];
        $d = ['monthly' => $monthly, 'paidLeave' => (float)$pl, 'active' => $active];
        if (!$s['teaching']) { $d['name'] = sal_text($in['name'] ?? $s['name'], 60) ?: $s['name']; $d['role'] = sal_text($in['role'] ?? $s['role'], 40) ?: $s['role']; }
        $what = "{$s['name']}: " . ($monthly === null ? 'salary not set' : 'salary ₹' . number_format($monthly)) . ", paid leave $pl/month" . ($active ? '' : ', inactive');
        if (($old['monthly'] ?? null) === $monthly && (float)($old['paidLeave'] ?? 1) === (float)$pl && ($old['active'] ?? true) === $active && !isset($d['name'])) sch_json(['ok' => true, 'id' => $id]);
    }
    sch_rec_put('salStaff', $id, $d, sch_uid_of($u));
    sch_log($u, 'Salary', 'Staff salary setting — ' . $what);
    sch_json(['ok' => true, 'id' => $id]);
}

case 'adjust': {
    if ($method !== 'POST') sch_fail('POST only', 405);
    $u = sch_auth(); sal_need($u, ['principal', 'accountant']);
    $ym = sal_ym_in($in); $id = (string)($in['id'] ?? ''); $s = sal_find($id);
    $key = $ym . '|' . $id; $old = sch_rec_get('salMonth', $key) ?? [];
    if (($old['status'] ?? '') === 'Paid') sch_fail('Already paid. Ask the Principal to reopen it first.');
    try {
        $ma = $in['manualAbsent'] ?? '';
        if ($ma !== '' && $ma !== null && (!is_numeric($ma) || $ma < 0 || $ma > 31 || fmod((float)$ma * 2, 1) != 0)) throw new InvalidArgumentException('Absent days must be 0 to 31 (half days allowed).');
        $d = ['advance' => sal_rupees($in['advance'] ?? 0, 0, 500000, 'Advance'), 'bonus' => sal_rupees($in['bonus'] ?? 0, 0, 500000, 'Bonus'),
              'manualAbsent' => ($ma === '' || $ma === null) ? null : (float)$ma, 'note' => sal_text($in['note'] ?? '', 200), 'status' => 'Draft', 'by' => $u['label'], 'at' => sch_now_ms()];
    } catch (InvalidArgumentException $e) { sch_fail($e->getMessage()); }
    sch_rec_put('salMonth', $key, $d, sch_uid_of($u));
    sch_log($u, 'Salary', "{$s['name']} $ym: advance ₹{$d['advance']}, bonus ₹{$d['bonus']}" . ($d['manualAbsent'] !== null ? ", absent days set to {$d['manualAbsent']}" : ''));
    sch_json(['ok' => true, 'row' => sal_sheet($ym, $id)['rows'][0] ?? null]);
}

case 'pay': {
    if ($method !== 'POST') sch_fail('POST only', 405);
    $u = sch_auth(); sal_need($u, ['principal', 'accountant']);
    $ym = sal_ym_in($in); $id = (string)($in['id'] ?? ''); $s = sal_find($id);
    $mode = (string)($in['mode'] ?? ''); if (!in_array($mode, SAL_MODES, true)) sch_fail('Choose how the salary was paid.');
    $ref = sal_text($in['ref'] ?? '', 60);
    $date = (string)($in['date'] ?? date('Y-m-d'));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $date > date('Y-m-d') || $date < date('Y-m-d', strtotime('-200 days'))) sch_fail('Choose a valid payment date.');
    $key = $ym . '|' . $id;
    $db->beginTransaction();
    $db->query("SELECT uid FROM sch_users WHERE uid='principal' FOR UPDATE")->fetch();
    $old = sch_rec_get('salMonth', $key) ?? [];
    if (($old['status'] ?? '') === 'Paid') { $db->rollBack(); sch_fail('This salary is already marked paid.'); }
    $row = sal_sheet($ym, $id)['rows'][0] ?? null;
    if (!$row || $s['monthly'] === null) { $db->rollBack(); sch_fail('Set the monthly salary first.'); }
    $c = $row['calc'];
    if ($c['net'] < 0) { $db->rollBack(); sch_fail('Net salary is below zero. Check the advance.'); }
    $d = $old + ['advance' => 0, 'bonus' => 0, 'manualAbsent' => null, 'note' => ''];
    $d = array_merge($d, ['status' => 'Paid', 'calc' => $c, 'paidOn' => $date, 'mode' => $mode, 'ref' => $ref, 'by' => $u['label'], 'at' => sch_now_ms(),
                          'name' => $s['name'], 'role' => $s['role']]);
    sch_rec_put('salMonth', $key, $d, sch_uid_of($u));
    sch_log($u, 'Salary', "PAID {$s['name']} for $ym: ₹" . number_format($c['net']) . " by $mode" . ($ref !== '' ? " ($ref)" : '') . " (gross ₹" . number_format($c['gross']) . ", cut ₹{$c['deduction']}, advance ₹{$c['advance']}, bonus ₹{$c['bonus']})");
    $db->commit();
    sch_json(['ok' => true, 'row' => sal_sheet($ym, $id)['rows'][0]]);
}

case 'unpay': {
    if ($method !== 'POST') sch_fail('POST only', 405);
    $u = sch_auth(); sal_need($u, ['principal']);
    $ym = sal_ym_in($in); $id = (string)($in['id'] ?? ''); $key = $ym . '|' . $id;
    $reason = sal_text($in['reason'] ?? '', 200); if ($reason === '') sch_fail('Write why it is being reopened.');
    $old = sch_rec_get('salMonth', $key);
    if (!$old || ($old['status'] ?? '') !== 'Paid') sch_fail('This salary is not marked paid.');
    $d = ['advance' => $old['advance'] ?? 0, 'bonus' => $old['bonus'] ?? 0, 'manualAbsent' => $old['manualAbsent'] ?? null, 'note' => $old['note'] ?? '',
          'status' => 'Draft', 'reopened' => ['reason' => $reason, 'by' => $u['label'], 'at' => sch_now_ms(), 'was' => array_intersect_key($old, array_flip(['calc', 'paidOn', 'mode', 'ref', 'by']))]];
    sch_rec_put('salMonth', $key, $d, sch_uid_of($u));
    sch_log($u, 'Salary', 'REOPENED ' . ($old['name'] ?? $id) . " $ym (was ₹" . number_format((int)($old['calc']['net'] ?? 0)) . " paid on " . ($old['paidOn'] ?? '') . "): $reason");
    sch_json(['ok' => true]);
}

case 'mine': {
    $u = sch_auth(); sal_need($u, ['teacher', 'principal']);
    $id = 't:' . $u['tid'];
    $slips = [];
    foreach (sal_rows('salMonth') as $k => $m) if (str_ends_with($k, '|' . $id) && ($m['status'] ?? '') === 'Paid')
        $slips[] = ['ym' => explode('|', $k)[0], 'calc' => $m['calc'], 'paidOn' => $m['paidOn'], 'mode' => $m['mode'], 'name' => $m['name'] ?? '', 'role' => $m['role'] ?? ''];
    usort($slips, fn($x, $y) => strcmp($y['ym'], $x['ym']));
    sch_json(['ok' => true, 'slips' => array_slice($slips, 0, 24)]);
}

default: sch_fail('Unknown action.', 404);
}
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('salary_api: ' . $e->getMessage());
    sch_fail('Something went wrong. Please try again.', 500);
}
