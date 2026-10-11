<?php
// ============================================================
//  सारथी — मदद
//  Wahi sawaal jo raste me sach me atakte hain. Kitaabi madad
//  nahi, aur dafter ka number sabse upar.
// ============================================================
require_once __DIR__ . '/inc/fn.php';
require_once __DIR__ . '/inc/kaam.php';
require_once __DIR__ . '/inc/ui.php';

$me = rider_me($pdo);
if (!$me) { redirect('/'); }

$ready = duty_until($pdo, (int)$me['id']);
$jobs  = rider_jobs($pdo, (int)$me['id']);

$sawal = [
  [t('The sender cannot provide the pickup code', 'भेजने वाला OTP नहीं बता रहा'),
   t('The company that booked the job provides the code to its customer or shop. If it is missing, call your office. Do not pick up the goods without recording the pickup.', 'जिस कंपनी का काम है उसने ही OTP भेजा है — वो अपने ग्राहक या दुकान को बताती है।
    न मिले तो दफ़्तर को फ़ोन कीजिए। सामान बिना दर्ज किए मत उठाइए।')],

  [t('The customer does not know the delivery code', 'ग्राहक को अपना कोड नहीं पता'),
   t('The booking company provides the customer\'s code by SMS or in its app. If it is missing, call your office before handing over the goods.', 'ग्राहक को वो कोड उसकी कंपनी ने दिया होगा (SMS या ऐप पर)। न मिले तो दफ़्तर को
    फ़ोन कीजिए — सामान देकर बाद में दर्ज करना ठीक नहीं है।')],

  [t('The customer is not at home', 'ग्राहक घर पर नहीं मिला'),
   t('Call the customer first. If they do not answer, use \'Having a problem?\' to report it. Do not leave the goods with a neighbour without instructions.', 'पहले फ़ोन कीजिए। न उठे तो "कुछ दिक्कत आ रही है?" में दर्ज कीजिए। सामान किसी
    पड़ोसी को देकर आगे मत बढ़िए — बाद में "मिला ही नहीं" का झगड़ा आप पर आता है।')],

  [t('Why combine deliveries in one trip?', 'एक चक्कर में कई जगह — क्या फ़ायदा'),
   t('Combining deliveries reduces travel. The first delivery earns your standard rate; later deliveries in the same trip use the extra-delivery rate shown in your earnings.', 'एक बार निकलकर 4-5 जगह निपटाने में आपके चक्कर कम लगते हैं और कमाई बढ़ती है।
    पहली डिलीवरी का पूरा रेट, उसी चक्कर में अगली का अलग रेट — खाते में साफ़ दिखता है।')],

  [t('How will I receive my earnings?', 'कमाई का पैसा कब मिलेगा'),
   t('The office pays your outstanding balance directly into your account. Sarathi does not hold your money; the ledger records the amounts.', 'खाते में "बाकी मिलना है" जो दिख रहा है, वो दफ़्तर अपने खाते से सीधे आपके खाते
    में भेजता है। सारथी आपका पैसा अपने पास नहीं रखता — खाता सिर्फ़ गिनती रखता है।')],

  [t('What if the job says to collect cash?', 'नगद लेना लिखा है तो'),
   t('Collect only the displayed amount and hand it to your office. Your delivery earnings are recorded separately in the ledger.', 'जितना लिखा है उतना ही लीजिए, और वो पैसा दफ़्तर में जमा कीजिए। आपकी अपनी कमाई
    उससे अलग है — वो खाते में अलग दिखती है।')],
];

sr_head($me, '', t('Help', 'मदद'), $ready, ['kaam' => count($jobs)]);
?>

<a class="sr-helpcall" href="tel:<?= h(SR_PHONE) ?>">
  <?= sr_icon('phone', 26) ?>
  <span><b><?= h(t('Call support', 'दफ़्तर को फ़ोन कीजिए')) ?></b><small><?= h(SR_PHONE_SHOW) ?></small></span>
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

<?php sr_foot('', ['kaam' => count($jobs)]); ?>

