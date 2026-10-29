<?php
// ============================================================
//  सारथी पार्टनर — नियम और शर्तें (version: SR_TERMS_VER)
//  Joining ke aakhiri step par "मैं मानता हूँ" zaroori hai.
//  Badalne par inc/identity.php me SR_TERMS_VER badliye —
//  sabse dobara manwaya jayega.
//  NOTE for owner: ise ek baar kisi vakeel se padhwa lijiye.
// ============================================================
require_once __DIR__ . '/inc/public.php';
require_once __DIR__ . '/inc/identity.php';
header('Cache-Control: public, max-age=600');
sr_customer_head(t('Sarathi partner rules', 'सारथी पार्टनर के नियम'), t('Version ', 'संस्करण ') . SR_TERMS_VER);
$rules = [
  [t('You are an independent partner', 'आप स्वतंत्र पार्टनर हैं'),
   t('You work with Smart Sarathi as an independent partner, not as an employee. You choose when to go online and when to stop.', 'आप स्मार्ट सारथी के साथ स्वतंत्र पार्टनर के रूप में काम करते हैं, कर्मचारी के रूप में नहीं। कब हाज़िर होना है और कब छुट्टी लेनी है, यह आप तय करते हैं।')],
  [t('Correct information', 'सही जानकारी'),
   t('Your name, Aadhaar, PAN, vehicle and number must be correct. Wrong information can close your account.', 'आपका नाम, आधार, पैन, गाड़ी और नंबर सही होना चाहिए। ग़लत जानकारी पर खाता बंद हो सकता है।')],
  [t('Your vehicle and papers are your responsibility', 'गाड़ी और काग़ज़ आपकी ज़िम्मेदारी'),
   t('Whether the vehicle is yours or rented, a valid driving licence, RC, insurance, permit and fitness are your responsibility. Follow traffic rules and wear a helmet / seat belt.', 'गाड़ी आपकी हो या किराए की — वैध ड्राइविंग लाइसेंस, RC, बीमा, परमिट और फ़िटनेस की ज़िम्मेदारी आपकी है। ट्रैफ़िक नियम मानिए, हेलमेट / सीट बेल्ट लगाइए।')],
  [t('Fare as shown in the app', 'किराया ऐप जितना ही'),
   t('Take only the fare shown in the app. Do not ask the customer for extra money.', 'ग्राहक से सिर्फ़ ऐप में दिखा किराया लीजिए। ज़्यादा पैसे मत माँगिए।')],
  [t('OTP at pickup and drop', 'पिकअप और ड्रॉप पर OTP'),
   t('Start a ride or pick up a parcel only after the customer’s OTP, and finish only with the drop OTP.', 'ग्राहक का OTP लेकर ही सवारी शुरू कीजिए या पार्सल उठाइए, और ड्रॉप OTP से ही काम पूरा कीजिए।')],
  [t('Good behaviour and safety', 'अच्छा व्यवहार और सुरक्षा'),
   t('Be polite. Never work after drinking or taking drugs. Do not carry anything illegal. Misbehaviour or a safety complaint can close your account.', 'ग्राहक से इज़्ज़त से बात कीजिए। शराब या नशा करके कभी काम मत कीजिए। कोई ग़ैरक़ानूनी सामान मत ले जाइए। बुरे व्यवहार या सुरक्षा की शिकायत पर खाता बंद हो सकता है।')],
  [t('Customer privacy', 'ग्राहक की निजता'),
   t('Use the customer’s number and address only for that job. Do not save, share or call later.', 'ग्राहक का नंबर और पता सिर्फ़ उसी काम के लिए है। बाद में सेव, शेयर या फ़ोन मत कीजिए।')],
  [t('Your area and orders', 'आपका इलाका और ऑर्डर'),
   t('You get orders from the area and distance you chose. Accept work only when you can reach on time.', 'आपने जो इलाका और दूरी चुनी है, वहीं के ऑर्डर आपको मिलेंगे। काम तभी लीजिए जब समय पर पहुँच सकें।')],
  [t('Location', 'लोकेशन'),
   t('While you are on a job, the app shares your location with the customer and the office for tracking and safety.', 'जब आप काम पर हैं, तब ट्रैकिंग और सुरक्षा के लिए आपकी लोकेशन ग्राहक और दफ़्तर को दिखती है।')],
  [t('Earnings and payments', 'कमाई और भुगतान'),
   t('Your earnings and any service charge are set by the office and shown in the app. Keep your account of every job in the app.', 'आपकी कमाई और सेवा शुल्क दफ़्तर तय करता है और ऐप में दिखता है। हर काम का हिसाब ऐप में रखिए।')],
  [t('Your data', 'आपकी जानकारी'),
   t('We keep your photos privately and store only the last 4 digits of Aadhaar. We do not sell your data. See the privacy policy to delete your account.', 'हम आपकी फ़ोटो सुरक्षित रखते हैं और आधार के सिर्फ़ आख़िरी 4 अंक रखते हैं। आपकी जानकारी किसी को बेचते नहीं। खाता हटाने के लिए प्राइवेसी पॉलिसी देखिए।')],
  [t('Changes', 'बदलाव'),
   t('If these rules change, the app will ask you to read and accept them again.', 'नियम बदलने पर ऐप आपसे दोबारा पढ़कर मानने को कहेगा।')],
];
?>
<section class="sx-card sx-form" style="display:grid;gap:12px">
<ol style="margin:0;padding-left:22px;display:grid;gap:12px">
<?php foreach ($rules as [$h, $b]): ?>
  <li><b style="display:block;color:#0B1F3A"><?= h($h) ?></b><span style="color:#41505F;line-height:1.55"><?= h($b) ?></span></li>
<?php endforeach; ?>
</ol>
<p style="margin:0"><a class="sx-pill" href="<?= h(u('/privacy.php')) ?>"><?= h(t('Privacy policy', 'प्राइवेसी पॉलिसी')) ?></a></p>
<p style="margin:0;color:#5A6878"><?= h(t('Questions? Call the office: ', 'सवाल हो तो दफ़्तर को फ़ोन कीजिए: ')) ?><a href="tel:<?= h(SR_PHONE) ?>"><?= h(SR_PHONE_SHOW) ?></a></p>
</section>
<?php sr_customer_foot(); ?>
