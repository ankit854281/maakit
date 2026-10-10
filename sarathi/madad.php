<?php
// ============================================================
//  सारथी — मदद
//
//  Jo sawaal rider sach me poochhta hai, unhi ke jawab.
//  Kitaabi madad nahi — wahi teen-chaar cheezein jo raste me
//  atakti hain, aur Maakit ka number sabse upar.
// ============================================================
require_once __DIR__ . '/../inc/fn.php';
require_once __DIR__ . '/../inc/sarathi.php';
require_once __DIR__ . '/../inc/sarathi-ui.php';

$me = sarathi_me();
if (!$me) { redirect('/sarathi/'); }

$duty_tak = sarathi_duty_tak($pdo, (int)$me['id']);
$baaki    = sarathi_kaam($pdo, (int)$me['id']);

$sawal = [
  ['दुकानदार OTP नहीं बता रहा',
   'उससे कहिए कि अपने Maakit पैनल में ऑर्डर खोले — वहाँ नीचे बड़े अंकों में OTP लिखा है।
    फिर भी न मिले तो Maakit को फ़ोन कीजिए, सामान बिना दर्ज किए मत उठाइए।'],

  ['ग्राहक को अपना कोड नहीं पता',
   'ग्राहक के पास ऑर्डर वाले पन्ने पर 4 अंक का कोड है। उसे वो पन्ना खोलने को कहिए।
    न मिले तो Maakit को फ़ोन कीजिए — सामान देकर बाद में दर्ज करना ठीक नहीं है।'],

  ['ग्राहक घर पर नहीं मिला',
   'पहले फ़ोन कीजिए। न उठे तो Maakit को बताइए। सामान किसी पड़ोसी को देकर आगे मत बढ़िए —
    बाद में "मिला ही नहीं" का झगड़ा आप पर आता है।'],

  ['"चार्ज तय नहीं" लिखा आ रहा है',
   'इसका मतलब उस ऑर्डर का दाम अभी पक्का नहीं हुआ। ऐसे ऑर्डर पर सामान मत उठाइए —
    पहले Maakit से पक्का करवा लीजिए।'],

  ['एक चक्कर में कई जगह — क्या फ़ायदा',
   'एक बार निकलकर 4-5 जगह निपटाने में आपके चक्कर कम लगते हैं और कमाई बढ़ती है।
    पहली डिलीवरी का पूरा रेट, उसी चक्कर में अगली का अलग रेट — खाते में साफ़ दिखता है।'],

  ['कमाई का पैसा कब मिलेगा',
   'खाते में "बाकी मिलना है" जो दिख रहा है, वो Maakit अपने खाते से सीधे आपके खाते में भेजता है।
    Maakit आपका पैसा अपने पास नहीं रखता — खाता सिर्फ़ गिनती रखता है।'],
];

sr_shell_head($me, '', 'मदद', $duty_tak, ['kaam' => count($baaki)]);
?>

<a class="sr-helpcall" href="tel:<?= h(MAAKIT_PHONE) ?>">
  <?= sr_icon('phone', 26) ?>
  <span><b>Maakit को फ़ोन कीजिए</b><small><?= h(MAAKIT_NUMBER_SHOW) ?></small></span>
</a>

<?php foreach ($sawal as [$q, $a]): ?>
  <details class="sr-ord">
    <summary class="sr-head">
      <span class="sr-dot"></span>
      <span class="sr-head-mid"><b><?= h($q) ?></b></span>
    </summary>
    <div class="sr-body"><p class="sr-ans"><?= h($a) ?></p></div>
  </details>
<?php endforeach; ?>

<?php sr_shell_foot('', ['kaam' => count($baaki)]); ?>
