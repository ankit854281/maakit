<?php
// Smart Sarathi — privacy policy and account deletion (needed for Play Store listing).
require_once __DIR__ . '/inc/public.php';
$wa = 'https://wa.me/918429393903?text=' . rawurlencode('Smart Sarathi — मेरा खाता और डेटा हटाइए। मेरा मोबाइल नंबर: ');
?><!doctype html>
<html lang="<?= h(sr_lang()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= h(t('Privacy policy — Smart Sarathi', 'प्राइवेसी पॉलिसी — स्मार्ट सारथी')) ?></title>
<style>
body{margin:0;background:#F4F6F9;color:#0B1F3A;font:16px/1.6 'Noto Sans Devanagari',system-ui,sans-serif}
main{max-width:760px;margin:0 auto;padding:24px 18px 48px}
h1{font-size:26px;margin:0 0 4px}h2{font-size:19px;margin:28px 0 6px}
.muted{color:#4B5A6B;font-size:14px}
a.btn{display:inline-block;background:#1FCB97;color:#0B1F3A;font-weight:800;border-radius:12px;padding:12px 16px;text-decoration:none}
.lang a{margin-right:10px}
</style>
</head>
<body>
<main>
<p class="lang"><a href="?lang=hi">हिन्दी</a><a href="?lang=en">English</a></p>
<h1><?= h(t('Privacy policy', 'प्राइवेसी पॉलिसी')) ?></h1>
<p class="muted"><?= h(t('Smart Sarathi, run by Maakit, Bhadohi, Uttar Pradesh. Effective 29 October 2026.', 'स्मार्ट सारथी, Maakit, भदोही, उत्तर प्रदेश द्वारा। 29 अक्टूबर 2026 से लागू।')) ?></p>

<h2><?= h(t('What we collect', 'हम क्या जानकारी लेते हैं')) ?></h2>
<ul>
<li><?= h(t('Mobile number — to sign you in with an SMS code. The code is sent by Google Firebase.', 'मोबाइल नंबर — SMS कोड से लॉगिन के लिए। कोड Google Firebase भेजता है।')) ?></li>
<li><?= h(t('Booking details — pickup and drop places, items, dates, and contact numbers you enter, including a family member\'s name and number when you book for them.', 'बुकिंग की जानकारी — कहाँ से, कहाँ तक, सामान, तारीख़ और आपके दिए नंबर; घर वालों के लिए बुकिंग में उनका नाम और नंबर भी।')) ?></li>
<li><?= h(t('For partners (riders and drivers): name, vehicle type and number, and optionally your photo and driving licence photo. These are stored privately and seen only by our office for verification.', 'पार्टनर (राइडर/ड्राइवर) के लिए: नाम, गाड़ी का प्रकार और नंबर, और चाहें तो फ़ोटो व ड्राइविंग लाइसेंस की फ़ोटो। ये निजी रखी जाती हैं और सिर्फ़ जाँच के लिए दफ़्तर देखता है।')) ?></li>
<li><?= h(t('Location — a partner\'s location only while they turn on sharing during an active job, so the customer can see them coming.', 'लोकेशन — पार्टनर की जगह सिर्फ़ तब, जब वे चालू काम के दौरान ख़ुद साझा करना चालू करें, ताकि ग्राहक देख सके।')) ?></li>
<li><?= h(t('Delivery photos — taken by the partner as proof of delivery.', 'डिलीवरी की फ़ोटो — पहुँचाने के सबूत के लिए पार्टनर लेता है।')) ?></li>
</ul>

<h2><?= h(t('How we use it', 'हम इसका क्या करते हैं')) ?></h2>
<p><?= h(t('Only to run your booking: find a partner, contact you, show tracking, keep accounts and handle complaints. We do not sell your data and do not show ads based on it.', 'सिर्फ़ आपकी बुकिंग चलाने के लिए: पार्टनर ढूँढना, आपसे बात करना, ट्रैकिंग दिखाना, हिसाब रखना और शिकायत सुलझाना। हम आपकी जानकारी बेचते नहीं और उस पर विज्ञापन नहीं दिखाते।')) ?></p>

<h2><?= h(t('Who sees it', 'कौन देखता है')) ?></h2>
<p><?= h(t('The partner on your job sees the details needed for that job. Our office sees bookings and partner documents. Google Firebase handles the SMS login.', 'आपके काम वाला पार्टनर उस काम के लिए ज़रूरी जानकारी देखता है। हमारा दफ़्तर बुकिंग और पार्टनर के काग़ज़ देखता है। SMS लॉगिन Google Firebase से होता है।')) ?></p>

<h2 id="delete"><?= h(t('Delete your account and data', 'खाता और डेटा हटवाइए')) ?></h2>
<p><?= h(t('Send us a WhatsApp message or call from your registered number. We delete your account, photos and personal details within 30 days. Booking and payment records that the law requires us to keep are retained only for that period.', 'अपने रजिस्टर्ड नंबर से WhatsApp या फ़ोन कीजिए। 30 दिन के अंदर आपका खाता, फ़ोटो और निजी जानकारी हटा दी जाएगी। बुकिंग और पैसों का जो हिसाब क़ानून के हिसाब से रखना ज़रूरी है, वह सिर्फ़ उतने समय तक रहेगा।')) ?></p>
<p><a class="btn" href="<?= h($wa) ?>"><?= h(t('Request deletion on WhatsApp', 'WhatsApp पर खाता हटवाइए')) ?></a></p>

<h2><?= h(t('Contact', 'संपर्क')) ?></h2>
<p>Maakit, Bhadohi, U.P. · <a href="tel:+918429393903">+91 84293 93903</a></p>
</main>
</body>
</html>
