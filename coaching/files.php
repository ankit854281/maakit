<?php
// Coaching Saathi — file dena, sirf jise haq hai:
//   ?logo=<coaching id>  — sabke liye (website par dikhta hai)
//   ?n=<note id>         — us coaching ka staff, ya us batch ke bachche ka parent (app chalu ho)
require __DIR__ . '/inc.php';

if (($lc = (int)cs_get('logo')) > 0) {
    $m = cs_meta($lc);
    if (!$m['logo'] || cs_blocked($lc)) { http_response_code(404); exit; }
    header('Cache-Control: public, max-age=604800');
    cs_send_file((string)$m['logo'], 'image/png');
}
$nid = (int)cs_get('n');
$s = cs_session();
if (!$nid || !$s) { http_response_code(403); exit('Login kariye'); }
if ($s['kind'] === 'staff') {
    $me = cs_me();
    $n = cs_row('SELECT * FROM cs_note WHERE id=? AND coaching_id=?', [$nid, $me['cid']]);
    if ($n && $me['role'] !== 'owner' && !cs_val('SELECT 1 FROM cs_batch WHERE id=? AND coaching_id=? AND teacher_id=?', [$n['batch_id'], $me['cid'], $me['uid']])) $n = null;
} elseif ($s['kind'] === 'family') {
    $n = cs_row('SELECT * FROM cs_note WHERE id=?', [$nid]);
    $ok = false;
    if ($n && !cs_blocked((int)$n['coaching_id'])) {
        foreach (cs_all("SELECT * FROM cs_student WHERE mobile=? AND coaching_id=? AND status='active'", [$s['mobile'], $n['coaching_id']]) as $st)
            if (in_array((int)$n['batch_id'], cs_student_batches((int)$n['coaching_id'], (int)$st['id']), true) && cs_access($st)['open']) $ok = true;
    }
    if (!$ok) $n = null;
} else $n = null;
if (!$n || !$n['file']) { http_response_code(404); exit('Nahi mila'); }
header('Cache-Control: private, max-age=3600');
cs_send_file((string)$n['file'], (string)$n['mime'], (string)$n['fname']);
