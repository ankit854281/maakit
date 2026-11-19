<?php
// ============================================================
// Staff salary API — maakit.in/school/salary_api.php
//
//   GET  ?a=sheet&ym=2026-10      Manager / Chairman (view) — poore staff ki sheet
//   POST ?a=staff  {id?, name, role, monthly, paidLeave, active}   sirf Manager
//        (teacher ke liye id = "t:<tid>"; naya non-teaching staff: id khaali)
//   POST ?a=adjust {ym, id, advance, bonus, manualAbsent, note}    sirf Manager
//   POST ?a=pay    {ym, id, date, online, cash, ref}               sirf Manager — Online + Cash, poora ya thoda (baaki pending)
//                  (purana {mode} bhi chalta hai = poora baaki ek mode me)
//   POST ?a=paymany {ym, date, items:[{id, online, cash}], ref}    sirf Manager — sab staff ek saath
//   POST ?a=unpay  {ym, id, reason}                                sirf Manager
//   GET  ?a=mine                  Teacher — sirf apni paid salary slips
//   GET  ?a=summary               Manager / Chairman — 6 mahine ka jod
//   GET  ?a=importplan            Manager / Chairman — September 2026 Excel sheet ki salary ka preview
//   POST ?a=import                sirf Manager — sheet ki salary sirf unhe jinki salary set nahi (set wali kabhi nahi badalti)
//
// Owner, 12 Nov 2026: salary sirf MANAGER (login 'manager', Salary app manager.php) aur CHAIRMAN dekhein —
// "bas manager, chairman sir dekh payen, aur koi nahi". Manager sab karta hai (salary set, adjust, pay, reopen);
// Chairman sab dekhta hai (read-only). Principal / Accountant ko ab salary nahi dikhti. Teacher sirf apni paid slip.
// Salary ki baat kisi aur ko nahi dikhni chahiye: records "sal" prefix
// (salStaff, salMonth) aam sync / load me kabhi nahi jaate.
// ============================================================
declare(strict_types=1);
require __DIR__ . '/inc.php';
require __DIR__ . '/salary_lib.php';
require_once __DIR__ . '/seed_lib.php';

$a = (string)($_GET['a'] ?? '');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
try { $db = sch_db(); } catch (Throwable $e) { sch_fail('Server is not reachable right now. Please try again in a few minutes.', 503); }
$in = [];
if ($method === 'POST') { $in = json_decode(file_get_contents('php://input', false, null, 0, 100000) ?: '', true); if (!is_array($in)) sch_fail('Bad request.'); }
function sal_need(array $u, array $roles): void { if (!in_array($u['role'], $roles, true)) sch_fail($roles === ['teacher', 'principal'] ? 'Not allowed.' : 'Salary is open only in the Salary app — for the Manager and the Chairman.', 403); }
function sal_text($v, int $max): string { return mb_substr(trim(preg_replace('/[\x00-\x1F\x7F]/u', ' ', (string)$v)), 0, $max); }
function sal_find(string $id): array { $all = sal_staff_list(); if (!isset($all[$id])) sch_fail('Staff member not found.'); return $all[$id]; }
function sal_ym_in(array $in): string { $ym = (string)($in['ym'] ?? ''); if (!sal_ym_ok($ym) || $ym > date('Y-m', strtotime('+1 month'))) sch_fail('Choose a month.'); return $ym; }

try {
switch ($a) {

case 'sheet': {
    $u = sch_auth(); sal_need($u, ['manager', 'chairman']);
    sch_seeds();
    $ym = (string)($_GET['ym'] ?? date('Y-m'));
    if (!sal_ym_ok($ym)) sch_fail('Choose a month.');
    sal_seed_oct_register('system');   // October 2026 register: closed school days opened for staff (salary_lib.php)
    sal_seed_joined_nov('system');     // teachers not in the October register: working from November (salary_lib.php)
    sal_seed_kartikey_oct('system');   // Kartikey Sir: in October, 23 Oct absent (salary_lib.php)
    sal_seed_oct_staff('system');      // October register people who left / were missing: in October with Sep salary (salary_lib.php)
    $m = $u['role'] === 'manager';
    sch_json(['ok' => true, 'can' => ['staff' => $m, 'unpay' => $m, 'edit' => $m], 'me' => $u['label'], 'modes' => SAL_MODES, 'today' => date('Y-m-d'),
              'school' => (string)((sch_rec_get('settings', 'main') ?? [])['school'] ?? '')] + sal_sheet($ym));
}

case 'summary': {                      // Admin dashboard: last 6 months, totals only
    $u = sch_auth(); sal_need($u, ['manager', 'chairman']); sal_seed_joined_nov('system');
    $out = [];
    for ($i = 5; $i >= 0; $i--) {
        $ym = date('Y-m', strtotime(date('Y-m-01') . " -$i month"));
        $rows = array_filter(sal_sheet($ym)['rows'], fn($r) => $r['status'] !== 'Not set');
        $paid = array_filter($rows, fn($r) => $r['status'] === 'Paid');
        $out[] = ['ym' => $ym, 'staff' => count($rows), 'gross' => array_sum(array_map(fn($r) => (int)$r['calc']['gross'], $rows)),
                  'net' => array_sum(array_map(fn($r) => (int)$r['calc']['net'], $rows)), 'cut' => array_sum(array_map(fn($r) => (int)$r['calc']['deduction'], $rows)),
                  'paid' => count($paid), 'paidAmt' => array_sum(array_map(fn($r) => (int)($r['paidAmt'] ?? 0), $rows)),   // part payments count too
                  'pending' => array_sum(array_map(fn($r) => max(0, (int)($r['balance'] ?? 0)), $rows))];
    }
    sch_json(['ok' => true, 'months' => $out]);
}

case 'importplan': {
    $u = sch_auth(); sal_need($u, ['manager', 'chairman']);
    sch_json(['ok' => true, 'rows' => sal_import_plan(), 'can' => $u['role'] === 'manager']);
}

case 'import': {                       // Sep 2026 Excel salaries → only staff with NO salary set
    if ($method !== 'POST') sch_fail('POST only', 405);
    $u = sch_auth(); sal_need($u, ['manager']);
    $db->beginTransaction();
    $db->query("SELECT uid FROM sch_users WHERE uid='principal' FOR UPDATE")->fetch();
    $set = sal_rows('salStaff'); $done = [];
    foreach (sal_import_plan() as $r) {
        if ($r['action'] !== 'set') continue;
        $d = $set[$r['id']] ?? [];
        if (array_key_exists('monthly', $d) && $d['monthly'] !== null) continue;
        $d['monthly'] = $r['monthly']; $d['paidLeave'] = (float)($d['paidLeave'] ?? 1); $d['active'] = $d['active'] ?? true;
        if (str_starts_with($r['id'], 'x')) { $d['name'] = $d['name'] ?? $r['erpName']; $d['role'] = $d['role'] ?? 'Staff'; }
        sch_rec_put('salStaff', $r['id'], $d, sch_uid_of($u));
        $done[] = $r['erpName'] . ' ₹' . number_format($r['monthly']);
    }
    if ($done) sch_log($u, 'Salary', 'Salaries filled from the September 2026 salary sheet: ' . implode('; ', $done));
    $db->commit();
    sch_json(['ok' => true, 'count' => count($done)]);
}

case 'staff': {
    if ($method !== 'POST') sch_fail('POST only', 405);
    $u = sch_auth(); sal_need($u, ['manager']);
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
        if (isset($old['until'])) $d['until'] = $old['until']; if (isset($old['from'])) $d['from'] = $old['from'];
        if (array_key_exists('from', $in)) { $f = (string)($in['from'] ?? ''); if ($f !== '' && !preg_match('/^\d{4}-\d{2}$/', $f)) sch_fail('Choose the month they started.'); if ($f === '') unset($d['from']); else $d['from'] = $f . '-01'; } if (isset($old['role']) && $s['teaching']) $d['role'] = $old['role'];   // keep 'worked till' (staffatt_api.php)
        if (!$s['teaching']) { $d['name'] = sal_text($in['name'] ?? $s['name'], 60) ?: $s['name']; $d['role'] = sal_text($in['role'] ?? $s['role'], 40) ?: $s['role']; }
        $what = "{$s['name']}: " . ($monthly === null ? 'salary not set' : 'salary ₹' . number_format($monthly)) . ", paid leave $pl/month" . ($active ? '' : ', inactive');
        if (($d['from'] ?? '') !== ($old['from'] ?? '')) $what .= ', working from ' . (($d['from'] ?? '') !== '' ? substr($d['from'], 0, 7) : 'always');
        if (($old['monthly'] ?? null) === $monthly && (float)($old['paidLeave'] ?? 1) === (float)$pl && ($old['active'] ?? true) === $active && !isset($d['name']) && ($d['from'] ?? '') === ($old['from'] ?? '')) sch_json(['ok' => true, 'id' => $id]);
    }
    sch_rec_put('salStaff', $id, $d, sch_uid_of($u));
    sch_log($u, 'Salary', 'Staff salary setting — ' . $what);
    sch_json(['ok' => true, 'id' => $id]);
}

case 'adjust': {
    if ($method !== 'POST') sch_fail('POST only', 405);
    $u = sch_auth(); sal_need($u, ['manager']);
    $ym = sal_ym_in($in); $id = (string)($in['id'] ?? ''); $s = sal_find($id);
    $key = $ym . '|' . $id; $old = sch_rec_get('salMonth', $key) ?? [];
    if (($old['status'] ?? '') === 'Paid') sch_fail('Already paid. Ask the Principal to reopen it first.');
    try {
        $ma = $in['manualAbsent'] ?? '';
        if ($ma !== '' && $ma !== null && (!is_numeric($ma) || $ma < 0 || $ma > 31 || fmod((float)$ma * 2, 1) != 0)) throw new InvalidArgumentException('Absent days must be 0 to 31 (half days allowed).');
        $d = ['advance' => sal_rupees($in['advance'] ?? 0, 0, 500000, 'Advance'), 'bonus' => sal_rupees($in['bonus'] ?? 0, 0, 500000, 'Bonus'),
              'manualAbsent' => ($ma === '' || $ma === null) ? null : (float)$ma, 'note' => sal_text($in['note'] ?? '', 200), 'status' => 'Draft', 'by' => $u['label'], 'at' => sch_now_ms()];
    } catch (InvalidArgumentException $e) { sch_fail($e->getMessage()); }
    if (sal_payments($old)) { $d['payments'] = sal_payments($old); $d['status'] = 'Part paid'; }   // keep part payments already made
    if (isset($old['reopened'])) $d['reopened'] = $old['reopened'];
    sch_rec_put('salMonth', $key, $d, sch_uid_of($u));
    sch_log($u, 'Salary', "{$s['name']} $ym: advance ₹{$d['advance']}, bonus ₹{$d['bonus']}" . ($d['manualAbsent'] !== null ? ", absent days set to {$d['manualAbsent']}" : ''));
    sch_json(['ok' => true, 'row' => sal_sheet($ym, $id)['rows'][0] ?? null]);
}

case 'pay':                            // one staff member: {ym, id, date, online, cash, ref} — or the old {mode} = whole balance in that mode
case 'paymany': {                      // many at once: {ym, date, items:[{id, online, cash}], ref}
    if ($method !== 'POST') sch_fail('POST only', 405);
    $u = sch_auth(); sal_need($u, ['manager']);
    $ym = sal_ym_in($in); $ref = sal_text($in['ref'] ?? '', 60);
    $date = (string)($in['date'] ?? date('Y-m-d'));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $date > date('Y-m-d') || $date < date('Y-m-d', strtotime('-200 days'))) sch_fail('Choose a valid payment date.');
    $items = $a === 'pay' ? [['id' => (string)($in['id'] ?? ''), 'online' => $in['online'] ?? null, 'cash' => $in['cash'] ?? null, 'mode' => $in['mode'] ?? null]] : (is_array($in['items'] ?? null) ? $in['items'] : []);
    if (!$items || count($items) > 200) sch_fail('Choose who is being paid.');
    $db->beginTransaction();
    $db->query("SELECT uid FROM sch_users WHERE uid='principal' FOR UPDATE")->fetch();
    $out = []; $logs = [];
    foreach ($items as $it) {
        $id = (string)($it['id'] ?? ''); $all = sal_staff_list();
        if (!isset($all[$id])) { $db->rollBack(); sch_fail('Staff member not found.'); }
        $s = $all[$id]; $key = $ym . '|' . $id;
        $old = sch_rec_get('salMonth', $key) ?? [];
        if (($old['status'] ?? '') === 'Paid') { $db->rollBack(); sch_fail("{$s['name']}: this salary is already fully paid."); }
        $row = sal_sheet($ym, $id)['rows'][0] ?? null;
        if (!$row || $s['monthly'] === null) { $db->rollBack(); sch_fail("{$s['name']}: set the monthly salary first."); }
        $c = $row['calc'];
        if ($c['net'] < 0) { $db->rollBack(); sch_fail("{$s['name']}: net salary is below zero. Check the advance."); }
        $pays = sal_payments($old); $paid = array_sum(array_column($pays, 'amt')); $bal = (int)$c['net'] - $paid;
        $legacy = isset($it['mode']) && $it['mode'] !== null && $it['mode'] !== '';
        try {
            if ($legacy) {
                if (!in_array((string)$it['mode'], SAL_MODES, true)) throw new InvalidArgumentException('Choose how the salary was paid.');
                $add = $bal > 0 ? [[sal_pay_mode((string)$it['mode']), $bal, (string)$it['mode']]] : [];
            } else {
                $on = sal_rupees($it['online'] ?? 0, 0, 500000, 'Online amount'); $ca = sal_rupees($it['cash'] ?? 0, 0, 500000, 'Cash amount');
                if ($on + $ca === 0 && $bal > 0) throw new InvalidArgumentException("{$s['name']}: write the amount (Online or Cash).");
                if ($on + $ca > max(0, $bal)) throw new InvalidArgumentException("{$s['name']}: amount is more than pending (₹" . number_format(max(0, $bal)) . ').');
                $add = array_values(array_filter([['Online', $on, 'Online'], ['Cash', $ca, 'Cash']], fn($x) => $x[1] > 0));
            }
        } catch (InvalidArgumentException $e) { $db->rollBack(); sch_fail($e->getMessage()); }
        foreach ($add as [$mode, $amt, $how]) $pays[] = ['amt' => $amt, 'mode' => $mode, 'how' => $how, 'date' => $date, 'ref' => $ref, 'by' => $u['label'], 'at' => sch_now_ms()];
        $paid = array_sum(array_column($pays, 'amt')); $left = (int)$c['net'] - $paid;
        $d = $old + ['advance' => 0, 'bonus' => 0, 'manualAbsent' => null, 'note' => ''];
        $d['payments'] = $pays; $d['name'] = $s['name']; $d['role'] = $s['role'];
        $now = implode(' + ', array_map(fn($x) => $x[0] . ' ₹' . number_format($x[1]), $add)) ?: 'nothing new';
        if ($left <= 0) {                // fully paid → freeze the numbers, exactly as before
            $modes = array_values(array_unique(array_column($pays, 'mode')));
            $d = array_merge($d, ['status' => 'Paid', 'calc' => $c, 'paidOn' => $date, 'mode' => $legacy && count($pays) === 1 ? (string)$it['mode'] : implode(' + ', $modes), 'ref' => $ref, 'by' => $u['label'], 'at' => sch_now_ms()]);
            $logs[] = "PAID {$s['name']} for $ym: $now — full ₹" . number_format($c['net']) . " paid (gross ₹" . number_format($c['gross']) . ", cut ₹{$c['deduction']}, advance ₹{$c['advance']}, bonus ₹{$c['bonus']})";
        } else {
            $d['status'] = 'Part paid';
            $logs[] = "PART PAID {$s['name']} for $ym: $now — paid ₹" . number_format($paid) . " of ₹" . number_format($c['net']) . ", ₹" . number_format($left) . ' pending';
        }
        sch_rec_put('salMonth', $key, $d, sch_uid_of($u));
        $out[] = ['id' => $id, 'name' => $s['name'], 'online' => array_sum(array_map(fn($x) => $x[0] === 'Online' ? $x[1] : 0, $add)), 'cash' => array_sum(array_map(fn($x) => $x[0] === 'Cash' ? $x[1] : 0, $add)), 'pending' => max(0, $left), 'net' => (int)$c['net']];
    }
    foreach ($logs as $l) sch_log($u, 'Salary', $l . ($ref !== '' ? " (ref $ref)" : '') . " on $date");
    $db->commit();
    sch_json(['ok' => true, 'date' => $date, 'paid' => $out, 'row' => $a === 'pay' ? (sal_sheet($ym, $items[0]['id'])['rows'][0] ?? null) : null]);
}

case 'unpay': {
    if ($method !== 'POST') sch_fail('POST only', 405);
    $u = sch_auth(); sal_need($u, ['manager']);
    $ym = sal_ym_in($in); $id = (string)($in['id'] ?? ''); $key = $ym . '|' . $id;
    $reason = sal_text($in['reason'] ?? '', 200); if ($reason === '') sch_fail('Write why it is being reopened.');
    $old = sch_rec_get('salMonth', $key);
    if (!$old || !sal_payments($old)) sch_fail('Nothing has been paid for this month.');
    // Reopen = the payments of this month are cancelled (kept under `reopened.was` with the reason) and the month is worked out again.
    $was = array_intersect_key($old, array_flip(['calc', 'paidOn', 'mode', 'ref', 'by', 'status'])); $was['payments'] = sal_payments($old);
    $d = ['advance' => $old['advance'] ?? 0, 'bonus' => $old['bonus'] ?? 0, 'manualAbsent' => $old['manualAbsent'] ?? null, 'note' => $old['note'] ?? '',
          'status' => 'Draft', 'reopened' => ['reason' => $reason, 'by' => $u['label'], 'at' => sch_now_ms(), 'was' => $was]];
    sch_rec_put('salMonth', $key, $d, sch_uid_of($u));
    $pdSum = array_sum(array_column($was['payments'], 'amt'));
    sch_log($u, 'Salary', 'REOPENED ' . ($old['name'] ?? $id) . " $ym (was ₹" . number_format(($old['status'] ?? '') === 'Paid' ? (int)($old['calc']['net'] ?? 0) : $pdSum) . (($old['status'] ?? '') === 'Paid' ? " paid on " . ($old['paidOn'] ?? '') : ' part paid') . "): $reason");
    sch_json(['ok' => true]);
}

case 'managername': {                  // POST {name} — Chairman names the Manager (shown on the Salary app login)
    if ($method !== 'POST') sch_fail('POST only', 405);
    $u = sch_auth(); sal_need($u, ['chairman']);
    $name = sal_text($in['name'] ?? '', 40);
    $set = sch_rec_get('settings', 'main') ?? []; $set['managerName'] = $name; sch_rec_put('settings', 'main', $set, 'chairman');
    sch_log($u, 'Salary', 'Manager name set to ' . ($name !== '' ? $name : 'Manager'));
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
