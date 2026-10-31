<?php
// Coaching Saathi — student / parent app. Ek mobile par jitne bachche, sab yahin.
// Hamesha free: fees ka khata, receipt, aaj absent ki khabar.
// Paid (₹54 / 6 mahine): poori haaziri, batch, aage notes / result (V2+).
require __DIR__ . '/inc.php';

$s = cs_session();
if (!$s || $s['kind'] !== 'family') cs_go('index.php');
$mobile = $s['mobile'];
$kids = cs_all("SELECT st.*, c.naam cnaam, c.color, c.phone cphone, c.slug FROM cs_student st JOIN cs_coaching c ON c.id=st.coaching_id
                WHERE st.mobile=? AND st.status='active' ORDER BY st.naam", [$mobile]);
if (!$kids) { cs_logout(); cs_go('index.php?m=band'); }
$sel = (int)cs_get('s');
$K = $kids[0];
foreach ($kids as $k) if ((int)$k['id'] === $sel) $K = $k;
$cid = (int)$K['coaching_id']; $sid = (int)$K['id'];
cs_post_dues($cid, $sid);
$acc = cs_access($K);
$C = ['color' => $K['color']];

cs_head($K['naam'] . ' — ' . $K['cnaam'], $C);
echo '<div class="top"><span class="logo">', h(mb_strtoupper(mb_substr($K['cnaam'], 0, 1))), '</span><b>', h($K['cnaam']), '</b></div><div class="wrap">', cs_flash();
if (count($kids) > 1) {
    echo '<div class="btns" style="margin:0 0 12px">';
    foreach ($kids as $k) echo '<a class="btn sm', (int)$k['id'] === $sid ? '' : ' ghost', '" href="?s=', (int)$k['id'], '">', h($k['naam']), '</a>';
    echo '</div>';
}
echo '<h1>', h($K['naam']), '</h1>';

// ---- aaj absent: lock ho tab bhi dikhe (parent ka sabse bada fayda) ----
$todayAbs = cs_all("SELECT b.naam FROM cs_attendance a JOIN cs_batch b ON b.id=a.batch_id AND b.coaching_id=a.coaching_id
                    WHERE a.student_id=? AND a.coaching_id=? AND a.d=CURDATE() AND a.status='A'", [$sid, $cid]);
foreach ($todayAbs as $b) echo '<div class="flash bad">Aaj ', h($K['naam']), ' ', h($b['naam']), ' me nahi aaya/aayi.</div>';

// ---- fees khata: hamesha free ----
$bal = cs_balance($cid, $sid);
echo '<div class="card"><div class="row"><div class="grow"><b>Fees</b><div class="sub">', $bal > 0 ? 'Baaki hai — coaching me jama kariye' : ($bal < 0 ? 'Advance jama hai' : 'Sab clear'), '</div></div>',
     '<span class="amt ', $bal > 0 ? 'due' : 'adv', '" style="font-size:22px">', rs(abs($bal)), '</span></div>';
$rec = cs_all('SELECT * FROM cs_receipt WHERE student_id=? AND coaching_id=? ORDER BY no DESC LIMIT 12', [$sid, $cid]);
foreach ($rec as $r) echo '<div class="row"><div class="grow"><a href="receipt.php?t=', h($r['token']), '">Receipt #', (int)$r['no'], '</a><div class="sub">', h(date('d M Y', strtotime($r['paid_on']))), '</div></div><span class="amt">', rs($r['amount']), '</span>',
     $r['cancelled'] ? '<span class="tag red">radd</span>' : '', '</div>';
echo '</div>';

if (!$acc['open']) {
    $plans = cs_plans();
    echo '<div class="card lock"><svg viewBox="0 0 24 24" width="40" height="40" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>',
         '<div class="big">App band hai</div><p>Haaziri, batch aur aage notes / result dekhne ke liye app chalu kariye.</p>',
         '<a class="btn full" href="pay.php?sid=', $sid, '">', rs($plans['6m']['amount']), ' — 6 mahine ke liye chalu kariye</a>',
         '<p class="note">Ya ', rs($plans['12m']['amount']), ' me 12 mahine. Fees ka khata aur receipt hamesha free hain.</p></div>';
} else {
    if ($acc['why'] === 'trial') echo '<div class="flash">Free trial — ', h(date('d M', strtotime($acc['till']))), ' tak. <a href="pay.php?sid=', $sid, '">Abhi chalu kariye</a></div>';
    $en = cs_all("SELECT b.naam, b.timing, c.naam course, u.naam teacher FROM cs_enroll e JOIN cs_batch b ON b.id=e.batch_id AND b.coaching_id=e.coaching_id
                  JOIN cs_course c ON c.id=b.course_id AND c.coaching_id=e.coaching_id LEFT JOIN cs_user u ON u.id=b.teacher_id AND u.coaching_id=e.coaching_id
                  WHERE e.student_id=? AND e.coaching_id=? AND e.status='active'", [$sid, $cid]);
    echo '<div class="card"><b>Batch</b>';
    foreach ($en as $e) echo '<div class="row"><div class="grow">', h($e['naam']), '<div class="sub">', h($e['course']), ' · ', h($e['timing']), $e['teacher'] ? ' · ' . h($e['teacher']) : '', '</div></div></div>';
    echo '</div>';
    $att = cs_all("SELECT d, status FROM cs_attendance WHERE student_id=? AND coaching_id=? AND d>=CURDATE()-INTERVAL 60 DAY ORDER BY d DESC", [$sid, $cid]);
    $p = count(array_filter($att, fn($a) => $a['status'] === 'P'));
    echo '<div class="card"><b>Haaziri (60 din)</b><div class="sub">', $p, ' din aaya, ', count($att) - $p, ' din absent', $att ? ' · ' . round($p * 100 / count($att)) . '%' : '', '</div><div style="display:flex;flex-wrap:wrap;gap:4px;margin-top:10px">';
    foreach ($att as $a) echo '<span class="tag ', $a['status'] === 'P' ? 'green' : 'red', '" title="', h($a['d']), '">', h(date('d/m', strtotime($a['d']))), '</span>';
    if (!$att) echo '<span class="note">Abhi haaziri nahi lagi.</span>';
    echo '</div></div>';
    if ($acc['why'] === 'paid') echo '<p class="note">App ', h(date('d M Y', strtotime($acc['till']))), ' tak chalu hai.</p>';
}
echo '<div class="btns"><a class="btn sm ghost" href="tel:', h($K['cphone']), '">Coaching ko call</a><a class="btn sm ghost" href="s/', h($K['slug']), '">Coaching ki website</a></div>',
     '<form method="post" action="index.php" style="margin-top:20px"><input type="hidden" name="a" value="out"><button class="btn ghost full">Logout</button></form></div>';
cs_foot();
