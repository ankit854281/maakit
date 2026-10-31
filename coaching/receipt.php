<?php
// Coaching Saathi — fees receipt (print + QR). Link me lamba anjaan token hai;
// QR scan karne par yahi page khulta hai aur batata hai receipt asli hai ya radd.
require __DIR__ . '/inc.php';

$t = preg_match('/^[a-f0-9]{24}$/', cs_get('t')) ? cs_get('t') : '';
$r = $t ? cs_row('SELECT * FROM cs_receipt WHERE token=?', [$t]) : null;
$c = $r ? cs_row('SELECT * FROM cs_coaching WHERE id=?', [$r['coaching_id']]) : null;
$s = $r ? cs_row('SELECT * FROM cs_student WHERE id=? AND coaching_id=?', [$r['student_id'], $r['coaching_id']]) : null;
if (!$r || !$c || !$s) { http_response_code(404); cs_head('Receipt nahi mili'); echo '<div class="wrap"><div class="card">Ye receipt nahi mili. Link poora copy kiya hai?</div></div>'; cs_foot(); exit; }
$waived = (int)cs_val("SELECT COALESCE(SUM(amount),0) FROM cs_ledger WHERE coaching_id=? AND student_id=? AND kind='waive' AND created_at<=?", [$c['id'], $s['id'], $r['created_at']]);
$url = cs_base() . '/receipt.php?t=' . $t;

cs_head('Receipt #' . $r['no'] . ' — ' . $c['naam'], $c);
echo '<div class="wrap" style="max-width:520px">';
if ($r['cancelled']) echo '<div class="flash bad">Ye receipt RADD ho chuki hai. Kaaran: ', h($r['cancel_reason']), '</div>';
else echo '<div class="flash noprint">Asli receipt ✓ — ', h($c['naam']), ' ke record me hai.</div>';
echo '<div class="card"><div class="row">', cs_meta((int)$c['id'])['logo'] ? cs_logo_html($c) : '<span class="logo" style="background:var(--c);color:#fff">' . h(mb_strtoupper(mb_substr($c['naam'], 0, 1))) . '</span>', '<div class="grow"><b style="font-size:18px">', h($c['naam']), '</b>',
     '<div class="sub">', h(trim($c['address'] . ', ' . $c['city'], ', ')), ' · ', h($c['phone']), '</div>',
     $c['reg_no'] !== '' ? '<div class="sub">Regn. No. ' . h($c['reg_no']) . '</div>' : '', '</div></div>',
     '<table><tr><th>Receipt no.</th><td class="r"><b>#', (int)$r['no'], '</b></td></tr>',
     '<tr><th>Tareekh</th><td class="r">', h(date('d M Y', strtotime($r['paid_on']))), '</td></tr>',
     '<tr><th>Bachcha</th><td class="r">', h($s['naam']), $s['cls'] !== '' ? ' (' . h($s['cls']) . ')' : '', '</td></tr>',
     $s['father'] !== '' ? '<tr><th>Pita</th><td class="r">' . h($s['father']) . '</td></tr>' : '',
     '<tr><th>Kaise</th><td class="r">', $r['mode'] === 'upi' ? 'UPI' : 'Cash', '</td></tr>',
     '<tr><th>Rakam</th><td class="r" style="font-size:22px"><b>', rs($r['amount']), '</b></td></tr>',
     $waived ? '<tr><th>Ab tak maaf ki gayi fees</th><td class="r">' . rs($waived) . '</td></tr>' : '',
     '</table><div style="display:flex;align-items:center;gap:12px;margin-top:12px"><div id="qr" style="width:96px;height:96px"></div><div class="note">QR scan karke receipt jaanchiye.<br>Computer se bani receipt — sign ki zaroorat nahi.</div></div></div>',
     '<div class="btns noprint"><button class="btn" onclick="print()">Print / PDF</button>',
     '<a class="btn wa" target="_blank" href="', h('https://wa.me/?text=' . rawurlencode($c['naam'] . ' — Receipt #' . $r['no'] . ' ' . rs($r['amount']) . ': ' . $url)), '">WhatsApp</a></div></div>',
     '<script src="/school/vendor/qrcode.js"></script><script>try{var q=qrcode(0,"M");q.addData(', json_encode($url), ');q.make();document.getElementById("qr").innerHTML=q.createSvgTag({scalable:true,margin:0})}catch(e){}</script>';
cs_foot();
