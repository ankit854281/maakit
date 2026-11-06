<?php
// Accountant app: shared teacher/staff roster, with add-only office permissions.
declare(strict_types=1);
require __DIR__ . '/inc.php';
require __DIR__ . '/salary_lib.php';
try {
    $db = sch_db();
    $u = sch_auth();
    if (!in_array($u['role'], ['accountant', 'principal'], true)) sch_fail('Accountant or Principal login required.', 403);
    $action = (string)($_GET['a'] ?? 'staff');
    if ($action === 'staff' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        $rows = [];
        foreach (sal_staff_list() as $s) $rows[] = array_intersect_key($s, array_flip(['id', 'name', 'role', 'active', 'teaching']));
        sch_json(['ok' => true, 'me' => ['role' => $u['role'], 'label' => $u['label']], 'staff' => $rows]);
    }
    if ($action !== 'add') sch_fail('Unknown action.', 404);
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') sch_fail('POST only', 405);
    $in = json_decode((string)file_get_contents('php://input', false, null, 0, 10000), true);
    if (!is_array($in)) sch_fail('Bad request.');
    $name = sal_text($in['name'] ?? '', 60);
    $phone = preg_replace('/[\s+()-]/', '', (string)($in['phone'] ?? ''));
    if (str_starts_with($phone, '91') && strlen($phone) === 12) $phone = substr($phone, 2);
    $kind = (string)($in['kind'] ?? '');
    $nonce = (string)($in['nonce'] ?? '');
    if (mb_strlen($name) < 2) sch_fail('Enter the full name.');
    if ($phone !== '' && !preg_match('/^[6-9][0-9]{9}$/', $phone)) sch_fail('Enter a valid 10-digit mobile number.');
    if (!in_array($kind, ['teacher', 'staff'], true) || !preg_match('/^[a-f0-9]{24}$/', $nonce)) sch_fail('Please reopen the add form.');
    $designation = $kind === 'teacher' ? 'Teacher' : sal_text($in['designation'] ?? 'Staff', 40);
    if ($designation === '') sch_fail('Enter the staff designation.');
    $key = ($kind === 'teacher' ? 'T' : 'x') . substr(hash('sha256', $u['role'] . '|' . $nonce), 0, 16);
    $recordKind = $kind === 'teacher' ? 'teacher' : 'salStaff';
    $db->beginTransaction();
    $db->query("SELECT uid FROM sch_users WHERE uid='principal' FOR UPDATE")->fetch();
    $existing = sch_rec_get($recordKind, $key);
    if ($existing) {
        if ($existing['name'] !== $name || ($existing['phone'] ?? '') !== $phone || $existing['role'] !== $designation) {
            $db->rollBack(); sch_fail('This form was already saved. Open a new form.', 409);
        }
        $db->commit(); sch_json(['ok' => true, 'id' => $key, 'again' => true]);
    }
    foreach (sal_staff_list() as $s) {
        if (mb_strtolower(trim($s['name'])) === mb_strtolower($name)) {
            $db->rollBack(); sch_fail('This name is already in the staff list. Check the existing entry or use the complete name.', 409);
        }
    }
    $data = ['name' => $name, 'role' => $designation, 'phone' => $phone];
    if ($kind === 'staff') $data += ['monthly' => null, 'paidLeave' => 1, 'active' => true];
    sch_rec_put($recordKind, $key, $data, sch_uid_of($u));
    sch_log($u, 'Staff', 'Accountant app added ' . $designation . ': ' . $name . ' (' . $key . ')');
    $db->commit();
    sch_json(['ok' => true, 'id' => $key]);
} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) $db->rollBack();
    error_log('accountant_api: ' . $e->getMessage());
    sch_fail('Could not complete this. Please retry.', 500);
}
