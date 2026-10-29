<?php
function sr_companion(): void {
    static $done=false; if($done) return; $done=true;
    $data=['api'=>u('/chat-api.php'),'csrf'=>csrf(),'lang'=>sr_lang(),'L'=>[
        'help'=>t('Help','मदद'),'assistant'=>t('Smart Assistant','स्मार्ट सहायक'),'chat'=>t('Trip chat','सफ़र की चैट'),
        'close'=>t('Close','बंद करें'),'send'=>t('Send','भेजें'),'type'=>t('Write your message','अपना संदेश लिखें'),
        'voice'=>t('Speak','बोलें'),'listen'=>t('Listen','सुनें'),'unavailable'=>t('Voice input is unavailable. Please type.','यहाँ बोलकर लिखना उपलब्ध नहीं है। लिखकर पूछें।'),
        'error'=>t('Could not connect. Try again. Your message has not been cleared.','संपर्क नहीं हुआ। दोबारा कोशिश करें। आपका संदेश यहीं है।'),
        'empty'=>t('No messages yet.','अभी कोई संदेश नहीं।'),
        'quick'=>[t('I am on my way','मैं रास्ते में हूँ'),t('I have arrived','मैं लोकेशन पर पहुँच गया हूँ'),t('Please answer the phone','कृपया फोन उठाएं')],
        'topics'=>[
            [t('Book a ride','सवारी बुक करें'),t('Choose a vehicle on Home, set pickup and drop pins, check the fare and confirm.','होम पर गाड़ी चुनें, पिकअप और ड्रॉप पिन लगाएँ, किराया जाँचें और बुक करें।'),'book|ride|सवारी|बुक|किराया'],
            [t('Partner jobs','पार्टनर के काम'),t('Complete your partner profile. Once approved, open Jobs and go online. Accept an offer to see the customer and trip.','पार्टनर प्रोफ़ाइल पूरी करें। मंज़ूरी मिलने पर काम खोलें और हाज़िरी लगाएँ। ऑफ़र स्वीकार करने पर ग्राहक और सफ़र दिखेंगे।'),'driver|partner|job|काम|सारथी|पार्टनर|हाज़िर'],
            [t('Password and login','पासवर्ड और लॉगिन'),t('Use mobile and password to log in. First verify your number with OTP, then set an 8-character or longer password in Settings. Forgot it? Sign out and use OTP again.','मोबाइल और पासवर्ड से प्रवेश करें। पहले OTP से नंबर जाँचें, फिर सेटिंग्स में कम से कम 8 अक्षर का पासवर्ड बनाएँ। भूल गए? लॉगआउट करके OTP चुनें।'),'password|login|otp|पासवर्ड|लॉगिन|ओटीपी'],
            [t('Change vehicle','गाड़ी बदलें'),t('Open Settings, add your vehicle model and RC number, then choose it. Finish any active trip first and go online again after switching.','सेटिंग्स में गाड़ी का मॉडल और RC नंबर जोड़ें, फिर उसे चुनें। पहले चालू सफ़र पूरा करें; गाड़ी बदलने के बाद फिर हाज़िरी लगाएँ।'),'vehicle|car|bike|गाड़ी|ऑटो|बाइक|रिक्शा'],
            [t('Call or chat','कॉल या चैट'),t('After your trip is accepted, open tracking or the job card. Tap Call or Chat. Chat is available while the trip is active.','सफ़र स्वीकार होने के बाद ट्रैकिंग या काम का कार्ड खोलें। कॉल या चैट दबाएँ। चालू सफ़र के दौरान चैट उपलब्ध है।'),'chat|call|चैट|कॉल|फोन']
        ],'fallback'=>t('I can guide you with bookings, partner jobs, login, vehicles and trip chat. Choose a topic below or call the office for account help.','मैं बुकिंग, पार्टनर के काम, लॉगिन, गाड़ी और सफ़र की चैट समझा सकता हूँ। नीचे विषय चुनें या खाते की मदद के लिए दफ़्तर को फ़ोन करें।')]];
    ?><link rel="stylesheet" href="<?= h(u('/assets/companion.css')) ?>?v=1">
<button type="button" id="sr-help" aria-haspopup="dialog"><?= h(t('Smart Help','स्मार्ट मदद')) ?></button>
<dialog id="sr-companion" aria-labelledby="sr-companion-title"><header><h2 id="sr-companion-title"></h2><button type="button" data-close aria-label="<?= h(t('Close','बंद करें')) ?>">×</button></header><div id="sr-messages" role="log" aria-live="polite"></div><div id="sr-quick"></div><form id="sr-message-form"><label><span class="sr-message-label"><?= h(t('Message','संदेश')) ?></span><input id="sr-message" maxlength="1000" required autocomplete="off"></label><button type="submit"><?= h(t('Send','भेजें')) ?></button></form><button type="button" id="sr-mic"><?= h(t('Speak','बोलें')) ?></button><button type="button" id="sr-listen"><?= h(t('Listen','सुनें')) ?></button><p id="sr-chat-status" role="status"></p></dialog>
<script id="sr-companion-config" type="application/json"><?= json_encode($data,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE) ?></script><script src="<?= h(u('/assets/companion.js')) ?>?v=1" defer></script><?php
}
