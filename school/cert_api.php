<?php
// ============================================================================
// Public certificate page API (owner, 6 Nov 2026: "ek link do, group mein share karun — search box do, baaki images
// aur certificate dikhao, naam / mobile number se search"). Read only — nobody can change anything here.
//   GET  ?a=all                 → every competition entry (no images): event, competition, group, class, team,
//                                 position (only after the Principal submitted that result), children names, signs
//   GET  ?a=ph&k=<ev>|<id>[&w=1] → team photo (or rangoli / diya photo with w=1)
//   GET  ?a=kid&sid=<id>        → small attendance photo of a child who is in a competition entry
//   POST ?a=find {mobile}       → ids of the children whose student record has this number (limited per IP)
// ============================================================================
declare(strict_types=1);
require __DIR__ . '/inc.php';

function cert_limit(): void {
    $f = sys_get_temp_dir() . '/sncert_' . md5(sch_ip()); $now = time(); $L = [];
    if (is_file($f)) { $L = array_values(array_filter((array)json_decode((string)@file_get_contents($f), true), fn($t) => is_int($t) && $t > $now - 3600)); }
    if (count($L) >= 25) sch_fail('Too many attempts. Please try again later.', 429);
    $L[] = $now; @file_put_contents($f, json_encode($L), LOCK_EX);
}
function cert_rows(PDO $db): array {
    $ev = []; $en = [];
    foreach ($db->query("SELECT r, d FROM sch_records WHERE k='tchComp' AND deleted=0 AND (r LIKE 'ev|%' OR r LIKE 'en|%')") as $row) {
        $d = json_decode((string)$row['d'], true); if (!is_array($d)) continue;
        if (str_starts_with((string)$row['r'], 'ev|')) $ev[(string)$d['id']] = $d; else $en[] = $d;
    }
    return [$ev, $en];
}

try {
    $db = sch_db(); $a = (string)($_GET['a'] ?? ''); $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($a === 'all') {
        [$ev, $en] = cert_rows($db);
        $set = (array)sch_rec_get('settings', 'main');
        $teachers = []; foreach ($db->query("SELECT r, d FROM sch_records WHERE k='teacher' AND deleted=0") as $row) { $d = json_decode((string)$row['d'], true); $teachers[(string)$row['r']] = (string)($d['name'] ?? ''); }
        $ctOf = []; foreach ((array)sch_rec_get('classes', 'main') as $c) $ctOf[(string)($c['c'] ?? '')] = (string)($c['ct'] ?? '');
        $need = []; foreach ($en as $n) foreach ((array)($n['sids'] ?? []) as $s) $need[(string)$s] = 1;
        $names = []; foreach ($db->query("SELECT r, d FROM sch_records WHERE k='student' AND deleted=0") as $row) { $r = (string)$row['r']; if (!isset($need[$r])) continue; $d = json_decode((string)$row['d'], true); $names[$r] = ['name' => (string)($d['name'] ?? ''), 'cls' => (string)($d['cls'] ?? ''), 'ph' => (bool)sch_rec_get('phoStu', $r)]; }
        $signs = []; $out = [];
        foreach ($en as $n) {
            $E = $ev[(string)($n['ev'] ?? '')] ?? null; if (!$E) continue;
            $ct = $ctOf[(string)$n['cls']] ?? ''; if ($ct !== '' && !isset($signs[$ct])) $signs[$ct] = (string)(sch_rec_get('tchComp', "sg|$ct")['data'] ?? '');
            $locked = !empty(((array)($E['res'] ?? []))[($n['cat'] ?? '') . '|' . ($n['sec'] ?? '')]);
            $out[] = ['id' => $n['id'], 'ev' => ['id' => $E['id'], 'title' => $E['title'], 'date' => $E['date']], 'cat' => $n['cat'], 'sec' => $n['sec'], 'cls' => $n['cls'],
                'team' => ($n['type'] ?? '') === 'group' ? (string)$n['name'] : '', 'pos' => $locked ? (string)($n['pos'] ?? '') : '', 'sids' => array_values((array)$n['sids']),
                'photo' => !empty($n['photo']), 'photoW' => !empty($n['photoW']), 'ct' => $ct, 'teacher' => $teachers[$ct] ?? ''];
        }
        usort($out, fn($x, $y) => strcmp($y['ev']['date'], $x['ev']['date']) ?: strcmp($x['cls'], $y['cls']));
        $signs['P'] = (string)(sch_rec_get('tchComp', 'sg|P')['data'] ?? '');
        sch_json(['ok' => true, 'school' => (string)($set['school'] ?? 'SN Mishra Singapore International School'), 'principal' => $teachers['P'] ?? '', 'signs' => (object)$signs, 'names' => (object)$names, 'entries' => $out]);
    }
    if ($a === 'ph') {
        $k = (string)($_GET['k'] ?? ''); if (!preg_match('/^e[a-f0-9]{6,20}\|n[a-f0-9]{8,20}$/', $k)) sch_fail('Bad request.');
        $p = sch_rec_get('tchComp', (($_GET['w'] ?? '') === '1' ? 'pr|' : 'ph|') . $k); sch_json(['ok' => true, 'data' => (string)($p['data'] ?? '')]);
    }
    if ($a === 'kid') {
        $sid = (string)($_GET['sid'] ?? ''); if ($sid === '' || strlen($sid) > 40) sch_fail('Bad request.');
        [, $en] = cert_rows($db); $in = false; foreach ($en as $n) if (in_array($sid, (array)($n['sids'] ?? []), true)) { $in = true; break; }
        if (!$in) sch_fail('Not found.', 404);
        $p = sch_rec_get('phoStu', $sid); $th = $p && !empty($p['img']) ? sch_rec_get('phoThumb', 'v2|' . (string)$p['img']) : null;
        sch_json(['ok' => true, 'data' => (string)($th['data'] ?? '')]);
    }
    if ($a === 'find' && $method === 'POST') {
        $in = json_decode((string)file_get_contents('php://input', false, null, 0, 2000), true);
        $mob = sch_phone10((string)($in['mobile'] ?? '')); if ($mob === '') sch_fail('Please enter a 10-digit mobile number.');
        cert_limit();
        $kids = sch_children($mob); if (!$kids) sch_fail('No student is registered with this mobile number. Please search by name or contact the class teacher.', 404);
        sch_json(['ok' => true, 'ids' => array_values(array_map(fn($k) => (string)($k['id'] ?? ''), $kids))]);
    }
    sch_fail('Bad request.');
} catch (Throwable $e) {
    error_log('cert_api: ' . $e->getMessage()); sch_fail('Server error. Please try again later.', 500);
}
