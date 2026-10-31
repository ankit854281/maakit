<?php
// ============================================================================
//  Old ERP Reports — every list/report page copied from the old ERP (Oct 2026)
//  Owner, 25 Oct 2026: "jo bhi incomplete ho complete kar dijiye" — nothing of
//  the old ERP may be left behind. Each exported page (staff, accounts, cash
//  book, bank, enquiry, transport, store, masters, …) is kept as the old ERP
//  showed it: its columns and rows, gzip+base64, one record per session+page.
//  Read-only. Only the Principal imports; a page already here is never changed.
//
//   GET  ?a=index                          pages this login may open (no data)
//   GET  ?a=page&ses=&page=                one page (rows)
//   POST ?a=import {pages:[…], apply}      Principal; preview, then apply
//
//  Kind regPage 'ses|page' (reg prefix: kept out of legacy sync).
// ============================================================================
declare(strict_types=1);
require __DIR__ . '/inc.php';

$a = (string)($_GET['a'] ?? 'index');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
try { $db = sch_db(); } catch (Throwable $e) { sch_fail('Server is not reachable right now. Please try again in a few minutes.', 503); }
$u = sch_auth(); $role = (string)$u['role'];
// Which export groups each office login may read (the Principal and Chairman read everything).
const OLDR_GROUPS = ['accountant' => ['03', '04', '05', '11', '12'], 'reception' => ['09', '10', '13']];
$can = function (string $group) use ($role): bool {
    if (in_array($role, ['principal', 'chairman'], true)) return true;
    return in_array(substr($group, 0, 2), OLDR_GROUPS[$role] ?? [], true);
};
if (!in_array($role, ['principal', 'chairman', 'accountant', 'reception'], true)) sch_fail('Old ERP reports are not open for this login.', 403);
$in = [];
if ($method === 'POST') { $raw = file_get_contents('php://input', false, null, 0, 9000001) ?: ''; $in = json_decode($raw, true); if (!is_array($in)) sch_fail('Bad request.'); }

try {
switch ($a) {
case 'index': {
    $out = [];
    foreach ($db->query("SELECT r, d FROM sch_records WHERE k='regPage' AND deleted=0") as $row) {
        $d = json_decode((string)$row['d'], true); if (!is_array($d) || !$can((string)$d['group'])) continue;
        unset($d['h']); $out[] = $d;
    }
    usort($out, fn($x, $y) => [$x['group'], $x['title'], $y['session']] <=> [$y['group'], $y['title'], $x['session']]);
    sch_json(['ok' => true, 'pages' => $out, 'isPrincipal' => $role === 'principal']);
}
case 'page': {
    $ses = (string)($_GET['ses'] ?? ''); $page = (string)($_GET['page'] ?? '');
    if (!preg_match('/^20\d\d-\d\d$/', $ses) || !preg_match('/^[a-z0-9_]{1,80}\.php$/', $page)) sch_fail('Choose a report.');
    $d = sch_rec_get('regPage', "$ses|$page"); if (!$d || !$can((string)$d['group'])) sch_fail('This report is not here or not open for your login.', 404);
    sch_json(['ok' => true, 'page' => $d]);
}
case 'import': {
    if ($method !== 'POST') sch_fail('POST only', 405);
    if ($role !== 'principal') sch_fail('Only the Principal imports from the old ERP.', 403);
    $pages = (array)($in['pages'] ?? []); if (!$pages || count($pages) > 700) throw new InvalidArgumentException('The file must have 1 to 700 pages.');
    $apply = ($in['apply'] ?? false) === true; $plan = []; $same = 0; $conflict = [];
    foreach ($pages as $p) {
        $p = (array)$p; $ses = (string)($p['session'] ?? ''); $page = (string)($p['page'] ?? ''); $group = (string)($p['group'] ?? '');
        if (!preg_match('/^20\d\d-\d\d$/', $ses) || !preg_match('/^[a-z0-9_]{1,80}\.php$/', $page) || !preg_match('/^\d\d [\w &,\/-]{1,60}$/', $group)) throw new InvalidArgumentException('A page in the file has a wrong name.');
        $h = (string)($p['h'] ?? ''); $bin = base64_decode($h, true); $json = $bin === false ? false : @gzdecode($bin); $rows = $json === false ? null : json_decode($json, true);
        if (!is_array($rows) || !array_is_list($rows) || count($rows) > 30000) throw new InvalidArgumentException('Page ' . $page . ' is damaged.');
        $d = ['session' => $ses, 'page' => $page, 'title' => mb_substr(trim((string)($p['title'] ?? $page)), 0, 120), 'group' => $group, 'rows' => count($rows) - 1, 'cols' => count((array)($rows[0] ?? [])), 'h' => $h];
        $old = sch_rec_get('regPage', "$ses|$page");
        if ($old) { if ($old['h'] === $h) $same++; else $conflict[] = "$ses $page"; continue; }
        $plan["$ses|$page"] = $d;
    }
    if ($conflict) sch_json(['ok' => false, 'error' => count($conflict) . ' pages already here are different (' . implode(', ', array_slice($conflict, 0, 4)) . '). Nothing was imported.'], 409);
    if ($apply) {
        $db->beginTransaction();
        foreach ($plan as $k => $d) sch_rec_put('regPage', $k, $d, sch_uid_of($u));
        sch_log($u, 'Old ERP Import', count($plan) . ' old ERP report pages imported; ' . $same . ' already present');
        $db->commit();
    }
    sch_json(['ok' => true, 'applied' => $apply, 'toAdd' => count($plan), 'unchanged' => $same, 'total' => count($pages)]);
}
default: sch_fail('Unknown action.', 404);
}
} catch (InvalidArgumentException $e) {
    if ($db->inTransaction()) $db->rollBack();
    sch_fail($e->getMessage());
} catch (Throwable $e) {
    if ($db->inTransaction()) $db->rollBack();
    error_log('oldreports_api: ' . $e->getMessage());
    sch_fail('Could not complete this. Nothing was saved.', 500);
}
