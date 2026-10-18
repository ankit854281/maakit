<?php
// ============================================================
// Profiles API — maakit.in/school/profile_api.php  (Principal; Chairman can only read)
//
// Header search → teacher / student profile window. The profile itself is
// built in app.js from data the Principal already has; this API adds the
// three things the normal load does NOT carry:
//   GET  ?a=att&from=YYYY-MM-DD&to=YYYY-MM-DD   staff + student attendance and behaviour for a
//                                              longer range (max 400 days) — "last 5 months"
//   GET  ?a=teacher&tid=K                       salary history (salStaff + paid/draft months)
//   GET  ?a=notes&who=t:K | s:<student id>      private notes: complaint / appreciation / note
//   POST ?a=note    {who, type, text}           Principal only
//   POST ?a=delnote {id}                        Principal only
//
// Notes are private (a complaint about a teacher must never reach teachers'
// phones), so they live in "profNote" records — the "prof" prefix is kept out
// of the legacy load / sync (inc.php).
// ============================================================
declare(strict_types=1);
require __DIR__ . '/inc.php';

$a = (string)($_GET['a'] ?? '');
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
try { $db = sch_db(); } catch (Throwable $e) { sch_fail('Server is not reachable right now. Please try again in a few minutes.', 503); }
$in = [];
if ($method === 'POST') { $in = json_decode(file_get_contents('php://input', false, null, 0, 50000) ?: '', true); if (!is_array($in)) sch_fail('Bad request.'); }

const PF_TYPES = ['Complaint', 'Appreciation', 'Note', 'Parent meeting', 'Warning'];
function pf_rows(string $k, ?string $like = null): array {
    $q = sch_db()->prepare('SELECT r, d FROM sch_records WHERE k=? AND deleted=0' . ($like !== null ? ' AND r LIKE ?' : ''));
    $q->execute($like !== null ? [$k, $like] : [$k]);
    $out = [];
    foreach ($q as $row) { $d = json_decode((string)$row['d'], true); if (is_array($d)) $out[(string)$row['r']] = $d; }
    return $out;
}
function pf_who(string $who): string {
    if (preg_match('/^t:([A-Za-z0-9_]{1,20})$/', $who, $m) && sch_rec_get('teacher', $m[1])) return $who;
    if (preg_match('/^s:(.{1,180})$/', $who, $m) && sch_rec_get('student', $m[1])) return $who;
    sch_fail('Teacher or student not found.');
    return '';
}
function pf_date(string $d): string { if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) || !strtotime($d)) sch_fail('Choose a valid date.'); return $d; }

try {
$u = sch_auth();
if (!in_array($u['role'], ['principal', 'chairman'], true)) sch_fail('Only the Principal can open profiles.', 403);
switch ($a) {

case 'att': {
    $from = pf_date((string)($_GET['from'] ?? '')); $to = pf_date((string)($_GET['to'] ?? date('Y-m-d')));
    if ($from > $to || (strtotime($to) - strtotime($from)) > 400 * 86400) sch_fail('Choose up to 400 days.');
    $st = $db->prepare("SELECT k, r, d, ver, deleted FROM sch_records WHERE deleted=0 AND k IN ('studAtt','staffAtt','behav') AND dkey BETWEEN ? AND ?");
    $st->execute([$from, $to]);
    sch_json(['ok' => true, 'from' => $from, 'to' => $to, 'records' => sch_rows_to_records($st->fetchAll())]);
}

case 'teacher': {
    $tid = (string)($_GET['tid'] ?? ''); pf_who('t:' . $tid);
    $id = 't:' . $tid; $months = [];
    foreach (pf_rows('salMonth', '%|' . $id) as $r => $m) {
        $ym = explode('|', $r)[0];
        $months[] = ['ym' => $ym, 'status' => $m['status'] ?? 'Draft', 'net' => $m['calc']['net'] ?? null, 'gross' => $m['calc']['gross'] ?? null,
                     'absent' => $m['calc']['absent'] ?? null, 'paidOn' => $m['paidOn'] ?? null, 'mode' => $m['mode'] ?? null, 'advance' => $m['advance'] ?? 0, 'bonus' => $m['bonus'] ?? 0];
    }
    usort($months, fn($x, $y) => strcmp($y['ym'], $x['ym']));
    $set = sch_rec_get('salStaff', $id) ?? [];
    sch_json(['ok' => true, 'salary' => ['monthly' => $set['monthly'] ?? null, 'paidLeave' => $set['paidLeave'] ?? 1, 'months' => array_slice($months, 0, 12)]]);
}

case 'notes': {
    $who = pf_who((string)($_GET['who'] ?? ''));
    $notes = array_values(array_filter(pf_rows('profNote'), fn($n) => ($n['who'] ?? '') === $who));
    usort($notes, fn($x, $y) => ($y['at'] ?? 0) <=> ($x['at'] ?? 0));
    sch_json(['ok' => true, 'notes' => $notes, 'types' => PF_TYPES]);
}

case 'note': {
    if ($method !== 'POST') sch_fail('POST only', 405);
    if ($u['role'] !== 'principal') sch_fail('Only the Principal can add notes.', 403);
    $who = pf_who((string)($in['who'] ?? ''));
    $type = (string)($in['type'] ?? ''); if (!in_array($type, PF_TYPES, true)) sch_fail('Choose the kind of note.');
    $text = mb_substr(trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', ' ', (string)($in['text'] ?? ''))), 0, 1000);
    if (mb_strlen($text) < 3) sch_fail('Write the note.');
    $date = (string)($in['date'] ?? date('Y-m-d')); pf_date($date); if ($date > date('Y-m-d')) sch_fail('The date cannot be in the future.');
    $id = bin2hex(random_bytes(8));
    $d = ['id' => $id, 'who' => $who, 'type' => $type, 'text' => $text, 'date' => $date, 'by' => $u['label'], 'at' => sch_now_ms()];
    sch_rec_put('profNote', $id, $d, sch_uid_of($u));
    $name = str_starts_with($who, 't:') ? sch_teacher_name(substr($who, 2)) : (string)((sch_rec_get('student', substr($who, 2)) ?? [])['name'] ?? '');
    sch_log($u, 'Profile', "$type added for $name");
    sch_json(['ok' => true, 'note' => $d]);
}

case 'delnote': {
    if ($method !== 'POST') sch_fail('POST only', 405);
    if ($u['role'] !== 'principal') sch_fail('Only the Principal can remove notes.', 403);
    $id = (string)($in['id'] ?? '');
    if (!preg_match('/^[a-f0-9]{16}$/', $id) || !($n = sch_rec_get('profNote', $id))) sch_fail('Note not found.');
    sch_rec_del('profNote', $id, sch_uid_of($u));
    sch_log($u, 'Profile', 'Removed a ' . strtolower((string)$n['type']) . ' note');
    sch_json(['ok' => true]);
}

default: sch_fail('Unknown action.', 404);
}
} catch (Throwable $e) {
    error_log('profile_api: ' . $e->getMessage());
    sch_fail('Something went wrong. Please try again.', 500);
}
