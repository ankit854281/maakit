<?php
// Coaching Saathi — har coaching ki apni website: maakit.in/coaching/s/<naam>
// Sirf wahi dikhta hai jo owner ne "Coaching details" me likha — bachchon ka koi data nahi.
require __DIR__ . '/inc.php';

$slug = strtolower(preg_replace('/[^a-z0-9-]/i', '', cs_get('c')));
$c = $slug !== '' ? cs_row('SELECT * FROM cs_coaching WHERE slug=?', [$slug]) : null;
if ($c && cs_blocked((int)$c['id'])) $c = null;
if (!$c) { http_response_code(404); cs_head('Coaching nahi mili'); echo '<div class="wrap"><div class="card">Ye coaching nahi mili.</div></div>'; cs_foot(); exit; }
$cid = (int)$c['id'];
$sent = false; $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $m = cs_mobile(cs_post('mobile')); $naam = mb_substr(cs_post('naam'), 0, 80);
    $today = (int)cs_val("SELECT COUNT(*) FROM cs_enquiry WHERE coaching_id=? AND source='web' AND created_at>=CURDATE()", [$cid]);
    if (cs_post('website') !== '') { $sent = true; }                       // bot (chhupa khaana bhara)
    elseif (!$m || $naam === '') $err = 'Naam aur sahi mobile number likhiye.';
    elseif ($today >= 30) $err = 'Aaj bahut enquiry aa gayi. Seedha call kariye.';
    else {
        if (!cs_val("SELECT 1 FROM cs_enquiry WHERE coaching_id=? AND mobile=? AND status='open'", [$cid, $m]))
            cs_q("INSERT INTO cs_enquiry (coaching_id,naam,mobile,interest,follow_up,source) VALUES (?,?,?,?,CURDATE(),'web')",
                [$cid, $naam, $m, mb_substr(cs_post('interest'), 0, 120)]);
        $sent = true;
    }
}

$courses = cs_all('SELECT * FROM cs_course WHERE coaching_id=? AND active=1 ORDER BY naam', [$cid]);
$batches = cs_all('SELECT b.naam, b.timing, b.seat_limit, c.naam course,
          (SELECT COUNT(*) FROM cs_enroll e WHERE e.batch_id=b.id AND e.coaching_id=b.coaching_id AND e.status=\'active\') n
          FROM cs_batch b JOIN cs_course c ON c.id=b.course_id AND c.coaching_id=b.coaching_id WHERE b.coaching_id=? AND b.active=1 ORDER BY b.naam', [$cid]);
$type = CS_TYPES[$c['ctype']] ?? '';
$desc = $c['naam'] . ' — ' . $type . ' coaching, ' . $c['city'] . '. Admission, fees aur batch ki jaankari.';
$meta = '<meta name="description" content="' . h($desc) . '"><meta property="og:title" content="' . h($c['naam']) . '"><meta property="og:description" content="' . h($desc) . '">'
      . '<link rel="canonical" href="' . h(cs_base() . '/s/' . $c['slug']) . '">'
      . '<script type="application/ld+json">' . json_encode(['@context' => 'https://schema.org', '@type' => 'EducationalOrganization', 'name' => $c['naam'],
          'telephone' => '+91' . $c['phone'], 'address' => ['@type' => 'PostalAddress', 'streetAddress' => $c['address'], 'addressLocality' => $c['city'], 'addressCountry' => 'IN']],
          JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . '</script>';

cs_head($c['naam'] . ' — ' . $c['city'], $c, '', $meta);
$lg = cs_meta($cid)['logo'];
echo '<div class="hero">', $lg ? '<img class="logo" src="' . h(cs_path_url('files.php?logo=' . $cid . '&v=' . substr((string)$lg, 0, 8))) . '" alt="" style="margin:auto;width:72px;height:72px">' : '<div class="logo" style="margin:auto;width:56px;height:56px;font-size:26px">' . h(mb_strtoupper(mb_substr($c['naam'], 0, 1))) . '</div>',
     '<h1>', h($c['naam']), '</h1><p>', h($type), ' · ', h($c['city']), '</p>',
     $c['reg_no'] !== '' ? '<p class="note" style="color:#fff">Regn. No. ' . h($c['reg_no']) . '</p>' : '',
     '<div class="btns" style="justify-content:center"><a class="btn" style="background:#fff;color:var(--c)" href="tel:+91', h($c['phone']), '">Call kariye</a>',
     '<a class="btn wa" href="', h(cs_wa($c['phone'], 'Namaste, mujhe admission ki jaankari chahiye.')), '">WhatsApp</a></div></div><div class="wrap">';
if ($c['about']) echo '<div class="card"><h2 style="margin-top:0">Hamare baare me</h2><p style="white-space:pre-line;margin:0">', h($c['about']), '</p></div>';
if ($courses) {
    echo '<div class="card"><h2 style="margin-top:0">Course aur fees</h2>';
    foreach ($courses as $x) echo '<div class="row"><div class="grow"><b>', h($x['naam']), '</b><div class="sub">', h(CS_FEE_TYPES[$x['fee_type']] ?? ''), $x['months'] ? ' · ' . (int)$x['months'] . ' mahine' : '', '</div></div>',
         $x['amount'] ? '<span class="amt">' . rs($x['amount']) . ($x['fee_type'] === 'monthly' ? '/mahina' : '') . '</span>' : '', '</div>';
    echo '</div>';
}
if ($batches) {
    echo '<div class="card"><h2 style="margin-top:0">Batch ka samay</h2>';
    foreach ($batches as $b) {
        $left = $b['seat_limit'] ? max(0, (int)$b['seat_limit'] - (int)$b['n']) : null;
        echo '<div class="row"><div class="grow">', h($b['naam']), '<div class="sub">', h($b['course']), ' · ', h($b['timing']), '</div></div>',
             $left === null ? '' : ($left > 0 ? '<span class="tag green">' . $left . ' seat baaki</span>' : '<span class="tag red">Seat full</span>'), '</div>';
    }
    echo '</div>';
}
echo '<div class="card" id="enq"><h2 style="margin-top:0">Admission ke liye poochhiye</h2>';
if ($sent) echo '<div class="flash">Dhanyavaad! Coaching aapko jald call karegi.</div>';
else {
    if ($err) echo '<div class="flash bad">', h($err), '</div>';
    echo '<form method="post" action="#enq"><label>Naam</label><input name="naam" required maxlength="80"><label>Mobile</label><input name="mobile" inputmode="numeric" required maxlength="13">',
         '<label>Kis class / course ke liye</label><input name="interest" maxlength="120"><input name="website" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px" aria-hidden="true">',
         '<button class="btn full">Bhejiye</button></form>';
}
echo '</div><div class="card"><h2 style="margin-top:0">Pata</h2><p style="margin:0">', h(trim($c['address'] . ', ' . $c['city'], ', ')), '</p>',
     $c['timing'] ? '<p class="note">Samay: ' . h($c['timing']) . '</p>' : '', '<p><a href="tel:+91', h($c['phone']), '">+91 ', h($c['phone']), '</a></p>',
     '<p class="note"><a href="/coaching/">Student / parent login</a></p></div></div>';
cs_foot();
