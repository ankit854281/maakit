<?php
// ============================================================================
// Public certificate lookup (owner, 6 Nov 2026: "certificate bachchon tak nahi pahuncha — ek link do, group mein
// share karun; search karke apna certificate download / share kar paayein").
//   POST cert_api.php?a=find {mobile}  → certificates of the children whose student record has this mobile number.
// Only the parent who knows the registered number sees their own children. Positions show only after the
// Principal has submitted that result (ev.res). Teammates appear by name only (no photos of other children).
// A small per-IP limit stops number guessing.
// ============================================================================
declare(strict_types=1);
require __DIR__ . '/inc.php';

function cert_limit(): void {
    $f = sys_get_temp_dir() . '/sncert_' . md5(sch_ip()); $now = time(); $L = [];
    if (is_file($f)) { $L = array_values(array_filter((array)json_decode((string)@file_get_contents($f), true), fn($t) => is_int($t) && $t > $now - 3600)); }
    if (count($L) >= 25) sch_fail('Bahut baar koshish ho gayi. Thodi der baad try karein.', 429);
    $L[] = $now; @file_put_contents($f, json_encode($L), LOCK_EX);
}
function cert_thumb(string $sid): string {
    $p = sch_rec_get('phoStu', $sid); if (!$p || empty($p['img'])) return '';
    $th = sch_rec_get('phoThumb', 'v2|' . (string)$p['img']); return (string)($th['data'] ?? '');
}

try {
    if (($_GET['a'] ?? '') !== 'find' || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') sch_fail('Bad request.', 400);
    $in = json_decode((string)file_get_contents('php://input', false, null, 0, 2000), true);
    $mob = sch_phone10((string)($in['mobile'] ?? '')); if ($mob === '') sch_fail('10 ank ka mobile number likhiye.');
    cert_limit();
    $kids = sch_children($mob); if (!$kids) sch_fail('Is number se koi bachcha nahi mila. Wahi number likhiye jo school mein diya hai, ya class teacher se sampark karein.', 404);
    $db = sch_db(); $ev = []; $en = [];
    foreach ($db->query("SELECT r, d FROM sch_records WHERE k='tchComp' AND deleted=0 AND (r LIKE 'ev|%' OR r LIKE 'en|%')") as $row) {
        $d = json_decode((string)$row['d'], true); if (!is_array($d)) continue;
        if (str_starts_with((string)$row['r'], 'ev|')) $ev[(string)$d['id']] = $d; else $en[] = $d;
    }
    $set = (array)sch_rec_get('settings', 'main'); $school = (string)($set['school'] ?? 'SN Mishra Singapore International School');
    $teachers = []; foreach ($db->query("SELECT r, d FROM sch_records WHERE k='teacher' AND deleted=0") as $row) { $d = json_decode((string)$row['d'], true); $teachers[(string)$row['r']] = (string)($d['name'] ?? ''); }
    $ctOf = []; foreach ((array)sch_rec_get('classes', 'main') as $c) $ctOf[(string)($c['c'] ?? '')] = (string)($c['ct'] ?? '');
    $sign = function (string $t) { $s = $t !== '' ? sch_rec_get('tchComp', "sg|$t") : null; return (string)($s['data'] ?? ''); };
    $names = []; foreach ($db->query("SELECT r, d FROM sch_records WHERE k='student' AND deleted=0") as $row) { $d = json_decode((string)$row['d'], true); $names[(string)$row['r']] = (string)($d['name'] ?? ''); }
    $out = []; $photoCache = [];
    foreach ($kids as $k) {
        $sid = (string)($k['id'] ?? ''); $mine = [];
        foreach ($en as $n) {
            if (!in_array($sid, (array)($n['sids'] ?? []), true)) continue; $E = $ev[(string)($n['ev'] ?? '')] ?? null; if (!$E) continue;
            $locked = !empty(((array)($E['res'] ?? []))[($n['cat'] ?? '') . '|' . ($n['sec'] ?? '')]);
            $pk = $E['id'] . '|' . $n['id'];
            if (!isset($photoCache[$pk])) $photoCache[$pk] = [
                'photo' => !empty($n['photo']) ? (string)(sch_rec_get('tchComp', "ph|$pk")['data'] ?? '') : '',
                'photoW' => !empty($n['photoW']) ? (string)(sch_rec_get('tchComp', "pr|$pk")['data'] ?? '') : ''];
            $ct = $ctOf[(string)$n['cls']] ?? '';
            $mine[] = ['id' => $n['id'], 'ev' => ['id' => $E['id'], 'title' => $E['title'], 'date' => $E['date']], 'cat' => $n['cat'], 'sec' => $n['sec'], 'cls' => $n['cls'],
                'team' => ($n['type'] ?? '') === 'group' ? (string)$n['name'] : '', 'pos' => $locked ? (string)($n['pos'] ?? '') : '',
                'kids' => array_map(fn($x) => ['name' => $names[$x] ?? '', 'me' => $x === $sid], (array)$n['sids']),
                'teacher' => $teachers[$ct] ?? '', 'tSign' => $sign($ct), 'photo' => $photoCache[$pk]['photo'], 'photoW' => $photoCache[$pk]['photoW']];
        }
        usort($mine, fn($a, $b) => strcmp($b['ev']['date'], $a['ev']['date']));
        $out[] = ['id' => $sid, 'name' => (string)($k['name'] ?? ''), 'cls' => (string)($k['cls'] ?? ''), 'src' => cert_thumb($sid), 'certs' => $mine];
    }
    sch_json(['ok' => true, 'school' => $school, 'principal' => $teachers['P'] ?? '', 'pSign' => $sign('P'), 'children' => $out]);
} catch (Throwable $e) {
    error_log('cert_api: ' . $e->getMessage()); sch_fail('Server problem. Thodi der baad try karein.', 500);
}
