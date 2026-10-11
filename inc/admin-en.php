<?php
// ============================================================
// Admin me English — jab EN chuna ho.
//
// Admin ke purane panno me Hindi seedhe likhi hai. Har panna
// dobara likhne ke bajaye, panna banne ke baad uski Hindi
// lines ko (sirf poori line, tukde nahi) is shabdkosh se English
// me badal dete hain. Grahak ka naam ya saaman ka naam tabhi
// badlega jab wo poora ka poora kisi line se milta ho.
//
// Naya panna banaiye to t('English', 'हिंदी') hi likhiye — ye
// shabdkosh sirf purane panno ke liye hai.
// ============================================================

function adm_en_start() {
    if (function_exists('is_hi') && is_hi()) return;
    if (defined('ADM_EN_ON')) return;
    define('ADM_EN_ON', 1);
    ob_start('adm_en_filter');
}

function adm_en_filter($html) {
    foreach (headers_list() as $hd) {
        if (stripos($hd, 'content-type:') === 0 && stripos($hd, 'text/html') === false) return $html;
    }
    $D = adm_en_dict();
    $one = function ($s) use ($D) {
        $k = trim($s);
        if ($k === '') return $s;
        $lead = substr($s, 0, strlen($s) - strlen(ltrim($s)));
        $tail = substr($s, strlen(rtrim($s)));
        if (isset($D[$k])) return $lead . $D[$k] . $tail;
        $k2 = rtrim($k, '।.:'); if ($k2 !== $k && isset($D[$k2])) return $lead . $D[$k2] . $tail;
        return null;
    };
    static $long = null;
    if ($long === null) { $long = []; foreach ($D as $k => $v) if (mb_strlen($k) >= 7) $long[$k] = $v; }
    return preg_replace_callback(
        '/[\x{0900}-\x{097F}](?:[\x{0900}-\x{097F}0-9₹%\s,.:;!?—–\-\/()+“”…·]*[\x{0900}-\x{097F}।?!.”)…])?/u',
        function ($m) use ($one, $long) {
            $r = $one($m[0]);
            if ($r !== null) return $r;
            // poori line na mile to: pehle lambe vakya andar se badlo,
            // phir — · , । ke tukdon me koshish
            $t = strtr($m[0], $long);
            $parts = preg_split('/(\s*[—·|।]\s*|\s*,\s+|\s*\(\s*|\s*\)\s*)/u', $t, -1, PREG_SPLIT_DELIM_CAPTURE);
            if (count($parts) < 2) return $t;
            $out = '';
            foreach ($parts as $i => $p) $out .= ($i % 2) ? (trim($p) === '।' ? '. ' : $p) : (($x = $one($p)) !== null ? $x : $p);
            return $out;
        },
        $html
    ) ?? $html;
}

function adm_en_dict() {
    static $D = null;
    if ($D !== null) return $D;
    return $D = [
// ---- areas
'अब चालू है। लोगों को फ़ोन कर दीजिए।' => 'Now live. Call the people who asked.',
'अभी किसी नए गाँव से माँग नहीं आई।' => 'No requests from new villages yet.',
'अभी नहीं' => 'Not now', 'आख़िरी माँग' => 'Last request', 'कब' => 'When', 'का स्टेटस बदल दिया गया।' => 'status changed.',
'क्या कहा' => 'Message', 'गाँव चालू कीजिए' => 'Activate village',
'चालू करते ही यह गाँव ऑर्डर फ़ॉर्म और ऊपर की पट्टी में दिखने लगेगा।' => 'Once active, this village appears in the order form and the top strip.',
'चालू है' => 'Active', 'जिन गाँवों से लोग माँग रहे हैं वो यहाँ हैं। सबसे ऊपर वाला गाँव सबसे ज़्यादा फ़ायदे का है' => 'Villages where people are asking for Maakit. The top one is the most promising',
'नंबर' => 'Number', 'नए गाँव की माँग' => 'New area requests', 'नमस्ते! Maakit अब' => 'Hello! Maakit is now', 'नया' => 'New', 'नाम' => 'Name',
'पास का बाज़ार' => 'Nearby market', 'माँगने वाला पेज देखिए' => 'View request page', 'में भी शुरू हो रहा है। आपने माँगा था — अब ऑर्डर कर सकते हैं। maakit.' => 'is starting there too. You asked — you can order now. maakit.',
'यहाँ शुरू कीजिए' => 'Start here', 'योजना में' => 'Planned', 'रेट सेट है' => 'Rate set', 'लोग माँग रहे हैं' => 'people asking', 'लोगों के नंबर देखिए' => 'See their numbers',
'वहाँ ग्राहक पहले से तैयार बैठे हैं।' => 'Customers there are already waiting.', 'सेव' => 'Save', 'स्टेटस' => 'Status',
// ---- banners
'अभी क्या दिख रहा है' => 'Currently showing', 'आज और कल के लिए' => 'For today and tomorrow', 'ऑफ़र और ऐड' => 'Offers & banners', 'ऑर्डर पेज' => 'Order page',
'कब तक' => 'Until', 'कब से' => 'From', 'कम से कम एक भाषा में हेडिंग लिखिए।' => 'Write a heading in at least one language.', 'काला' => 'Black', 'किताबें' => 'Books', 'क्रम' => 'Order',
'गहरा लाल' => 'Dark red', 'गाड़ी' => 'Vehicle', 'गाड़ी बुकिंग' => 'Vehicle booking', 'छोटी लाइन (हिंदी)' => 'Short line (Hindi)', 'टेंट बुकिंग' => 'Tent booking',
'डिलीवरी फ़्री' => 'Free delivery', 'त्योहार' => 'Festival', 'त्योहार का ऑफ़र, नई सेवा, या कोई ज़रूरी बात — यहाँ से लगाइए, तुरंत दिख जाएगी।' => 'Festival offer, new service or an important notice — add it here and it shows immediately.',
'दबाने पर कहाँ जाए' => 'Link when tapped', 'दवाई' => 'Medicine', 'दिख रहा है' => 'Showing', 'दिखाइए' => 'Show', 'देखिए' => 'View', 'नया ऑफ़र जुड़ गया।' => 'New offer added.',
'नया गाँव' => 'New village', 'पीछे की फ़ोटो' => 'Background photo', 'पुरानी किताबें' => 'Old books', 'पूजा' => 'Puja', 'पूजा का सामान' => 'Puja items', 'फ़ोटो हटा दी।' => 'Photo removed.',
'बंद' => 'Off', 'बार दबाया गया' => 'clicks', 'रंग' => 'Colour', 'लॉन बुकिंग' => 'Lawn booking', 'शादी-ब्याह' => 'Weddings', 'सारी बुकिंग' => 'All bookings', 'सुनहरा' => 'Gold',
'सेव हो गया।' => 'Saved.', 'हटा दिया गया।' => 'Removed.', 'हल्का' => 'Light', 'हेडिंग (हिंदी)' => 'Heading (Hindi)',
'होम पेज पर “क्या चल रहा है” वाली पट्टी आप ख़ुद चलाते हैं।' => 'You control the “What’s on” strip on the home page.',
// ---- books
'अभी देख लीजिए' => 'Check now', 'अभी लगी हैं' => 'Live now', 'असली चार्ज तो आप कॉल पर गाँव देखकर बताएँगे।' => 'You confirm the real charge on the call, based on the village.',
'आज की नई किताबें' => 'New books today', 'आज डाली गईं' => 'Added today', 'इतने ऑर्डर आए' => 'Orders received', 'काम हो गया' => 'Done', 'किताब की डिलीवरी' => 'Book delivery',
'किताब की डिलीवरी का शुरुआती चार्ज' => 'Starting charge for book delivery', 'किताब की शिकायत आई है' => 'Book complaints', 'किताब पूरी तरह मिटा दी गई।' => 'Book permanently deleted.',
'किताब वापस लगा दी गई।' => 'Book restored.', 'किताब वाले पेज पर लोगों को यही दिखता है — “पहुँचाने का चार्ज' => 'This is what people see on the book page — “Delivery charge',
'किताब हटा दी गई।' => 'Book removed.', 'किताब, नाम, नंबर या गाँव से ढूंढिए' => 'Search by book, name, number or village', 'कुल माँगी गईं' => 'Total requested',
'के बारे में बात करनी थी।' => '— we wanted to talk about it.', 'कोड' => 'Code', 'खोलिए' => 'Open', 'जी, Maakit से। आपकी किताब' => 'ji, this is Maakit. Your book', 'ठीक है' => 'OK',
'ढूंढिए' => 'Search', 'नमस्ते' => 'Hello', 'ने देखी' => 'views', 'ने माँगी' => 'requests', 'बदल दीजिए' => 'Change', 'बिक गई' => 'Sold', 'बिक गईं' => 'Sold', 'बिक गई” कर दिया।' => 'Sold”.',
'मिटाइए' => 'Delete', 'यह किताब हमेशा के लिए मिटा दें? वापस नहीं आएगी।' => 'Delete this book permanently? It cannot be undone.', 'यहाँ कोई किताब नहीं है।' => 'No books here.',
'लगी हुई' => 'Live', 'लोग अपनी किताब खुद डालते हैं और वह तुरंत दिख जाती है। यहाँ से आप कोई भी किताब हटा सकते हैं।' => 'People list their own books and they appear immediately. You can remove any book from here.',
'लोगों को दिख रही हैं' => 'Visible to people', 'वापस लगाइए' => 'Restore', 'शिकायत' => 'Complaints', 'शिकायत हटा दी गई — किताब ठीक है।' => 'Complaint cleared — the book is fine.',
'साफ़' => 'Clear', 'सारी' => 'All', 'से शुरू' => 'onwards', 'से शुरू — बदल दिया गया।' => 'onwards — updated.', 'से शुरू”।' => 'onwards”.', 'हटाइए' => 'Remove', 'हटाई गईं' => 'Removed', 'हटाई हुई' => 'Removed',
// ---- businesses
'का हिस्सा' => 'share', 'कोड WhatsApp पर भेजिए' => 'Send code on WhatsApp', 'चालू कीजिए' => 'Activate', 'छुपाइए' => 'Hide', 'छुपाए' => 'Hidden', 'जाँच बाकी' => 'Pending review',
'जाँच में रखिए' => 'Put on hold', 'दिख रहे' => 'Visible', 'दुकान / कारीगर' => 'Shops & workers', 'दुकानदार को यह भेजिए — लिंक' => 'Send this to the shopkeeper — link',
'नया कोड' => 'New code', 'नया कोड बन गया, पुराना लॉगिन बंद हो गया।' => 'New code created; the old login no longer works.', 'नया कोड बनाने पर पुराना लॉगिन बंद हो जाएगा।' => 'Creating a new code stops the old login.',
'पक्का हटाना है?' => 'Remove for sure?', 'बंद कीजिए' => 'Turn off', 'बदल दिया गया।' => 'Updated.', 'मोबाइल' => 'Mobile', 'यहाँ कुछ नहीं है।' => 'Nothing here.',
'रखिए तो कुछ नहीं कटेगा। दुकानदार को उसकी बही में अलग लाइन दिखती है — छिपाकर कुछ नहीं।' => 'and nothing is deducted. The shopkeeper sees it as a separate line in their ledger — nothing hidden.',
'वहाँ आप ये कर सकते हैं' => 'There you can', 'सब' => 'All', 'सीट बुकिंग' => 'Seat booking', 'हिस्सा सेव हो गया। दुकानदार को उसकी बही में अलग लाइन में दिखेगा।' => 'Share saved. The shopkeeper will see it as a separate line in their ledger.',
'एक बार खोलने के बाद 90 दिन तक इसी फ़ोन पर सीधा खुलेगा। कोड किसी को न बताइए।' => 'After opening once, it stays signed in on this phone for 90 days. Do not share the code.',
// ---- coverage
'इलाका चालू' => 'Area active', 'डिलीवरी अनुरोध लें' => 'Accept delivery requests', 'पहली डिलीवरी का मूल चार्ज माफ करें' => 'Waive base charge on first delivery',
'बुकिंग अनुरोध लें' => 'Accept booking requests', 'राज्य' => 'State', 'शहर / जिला' => 'City / district', 'स्थानीय डिलीवरी चार्ज ₹ (खाली = पुराने बाज़ार के रेट)' => 'Local delivery charge ₹ (blank = old market rates)',
// ---- daam
'अब तक की सारी ख़रीद' => 'All purchases so far', 'अभी कोई दाम दर्ज नहीं हुआ।' => 'No prices recorded yet.', 'अभी दिख रहा' => 'Showing now', 'आज के बिलों से' => 'From today’s bills',
'आज लिखे गए' => 'Written today', 'आटा, तेल, चाय, साबुन। आलू-प्याज़ में ब्रांड का कोई मतलब नहीं।' => 'Atta, oil, tea, soap. Brands don’t matter for potatoes and onions.',
'इस सामान के ब्रांड सेट हो गए।' => 'Brands set for this product.', 'एक ही ब्रांड को दो तरह से मत लिखिए — “आशीर्वाद” और “Aashirvaad” अलग गिने जाएँगे' => 'Don’t write the same brand two ways — “आशीर्वाद” and “Aashirvaad” are counted separately',
'और दाम का हिसाब बँट जाएगा।' => 'and the price data gets split.', 'कंपनी' => 'Company', 'कंपनी का' => 'Branded', 'कब · किसने' => 'When · by', 'कितने सामान में' => 'In products',
'किस तरह का' => 'Type', 'किस सामान में कौन से ब्रांड मिलते हैं' => 'Which brands are sold for each product', 'कुल दर्ज' => 'Total recorded', 'ख़रीद से' => 'From purchases', 'ग़लत है' => 'Wrong',
'ग्राहक को “कोई भी ब्रांड” पहले से चुना हुआ मिलेगा — उसे कुछ चुनना ज़रूरी नहीं।' => 'Customers get “any brand” pre-selected — they don’t have to choose.',
'चालू ब्रांड' => 'Active brands', 'चुनिए' => 'Choose', 'जिनका दाम नहीं पता' => 'Price unknown', 'जुड़ गया।' => 'Added.', 'जैसे: आशीर्वाद' => 'e.g. Aashirvaad', 'जो मिलते हैं उन पर टिक कीजिए' => 'Tick the ones available',
'जोड़िए' => 'Add', 'तरह' => 'types', 'दबाइए और बिल के दाम भर दीजिए।' => 'press and enter the bill prices.', 'दाम' => 'Price', 'दाम और ब्रांड' => 'Prices & brands', 'दाम तय नहीं करता' => 'does not set prices',
'दाम पता है' => 'Price known', 'दाम लिखिए' => 'Enter prices', 'दो ख़रीद के बाद वह सामान ग्राहकों को दिखने लगेगा।' => 'After two purchases, the product becomes visible to customers.',
'दो बार के बाद अपने आप दिखने लगेगा।' => 'Shows automatically after two entries.', 'नया ब्रांड जोड़िए' => 'Add a new brand', 'नाम (English)' => 'Name (English)', 'नाम (हिंदी)' => 'Name (Hindi)',
'पैनल में किसी ऑर्डर पर' => 'in the panel on any order', 'बाक़ी का दाम अभी नहीं पता' => 'price not known yet for the rest', 'ब्रांड' => 'Brand', 'ब्रांड की लिस्ट' => 'Brand list', 'ब्रांड बदल दिया गया।' => 'Brand updated.',
'यह दाम ग़लत मानकर हटा दें?' => 'Remove this price as wrong?', 'ये सामान ग्राहक को बिना दाम के दिखते हैं। जब इनका ऑर्डर आए, BPO से दाम लिखवा दीजिए' => 'These products show without a price. When an order comes, have BPO enter the price',
'लोकल' => 'Local', 'लोकल / खुला' => 'Local / loose', 'वह दाम हटा दिया गया और हिसाब दोबारा लगा दिया गया।' => 'Price removed and averages recalculated.',
'वही यहाँ जमा होता जाता है और ग्राहक को अंदाज़े के तौर पर दिखता है।' => 'it collects here and is shown to customers as an estimate.', 'सबसे ज़्यादा बिकने वाले पहले हैं।' => 'Best sellers first.',
'सामान' => 'Products', 'सामान चुनिए' => 'Choose product', 'सामान में ब्रांड जोड़िए' => 'Add brands to products', 'सिर्फ़ उन्हीं सामान में ब्रांड जोड़िए जिनमें दाम का फ़र्क़ पड़ता है' => 'Add brands only to products where the brand changes the price',
'सीखे हुए दाम' => 'Learned prices', 'सेव कीजिए' => 'Save', 'हर बिल से सीखता है' => 'learns from every bill', '। BPO जब ऑर्डर का दाम लिखता है' => '. When BPO enters an order’s prices',
// ---- feedback
'जाँच के बाद ही राय वेबसाइट पर दिखती है, ताकि कोई झूठी राय न डाल सके।' => 'Reviews appear on the website only after checking, so no one can post fake reviews.',
'दिख रही है' => 'Visible', 'राय दिखने लगी।' => 'Review is now visible.', 'लोगों की राय' => 'Customer reviews', 'हटा दी गई।' => 'Removed.',
// ---- items
'अपनी खींची हुई ही लगाइए' => 'use only photos you took', 'इससे लोग हिंदी और अंग्रेज़ी दोनों से ढूंढ पाएँगे।' => 'so people can search in both Hindi and English.',
'और वहाँ दबाते ही फ़ोटो चढ़ जाती है।' => 'and the photo uploads as soon as you tap there.', 'कौन सा हिस्सा' => 'Section', 'खोजने के शब्द (चाहें तो)' => 'Search words (optional)',
'जिनकी फ़ोटो नहीं है' => 'Without photo', 'जिनकी फ़ोटो लग चुकी' => 'With photo', 'जैसे: 1 किलो' => 'e.g. 1 kg', 'जैसे: सरसों का साग' => 'e.g. mustard greens',
'जोड़ने के बाद ऊपर ढूंढकर फ़ोटो लगा सकते हैं।' => 'After adding, search above to add a photo.', 'दिन की रोशनी में — ट्यूबलाइट में रंग पीला आता है' => 'In daylight — tube lights make colours yellow',
'दुकान पर जाइए, फ़ोन से खींचिए, यहीं से लगा दीजिए।' => 'Go to the shop, take a phone photo, and upload it here.', 'नया सामान' => 'New product', 'नया सामान जुड़ गया।' => 'New product added.',
'नया सामान जोड़िए' => 'Add a new product', 'नाम / मात्रा बदलिए' => 'Edit name / quantity', 'नाम और मात्रा दोनों लिखिए।' => 'Enter both name and quantity.', 'नाम से ढूंढिए' => 'Search by name',
'पूरा सामान फ़्रेम में आए, हाथ या दुकान का सामान पीछे न दिखे' => 'Whole product in frame, no hands or shop clutter behind', 'पेज पर चला गया है — वहाँ सारी फ़ोटो एक ही जगह हैं' => 'page — all photos are in one place there',
'फ़ोटो' => 'Photos', 'फ़ोटो एक साथ लगाइए' => 'Bulk upload photos', 'फ़ोटो कैसी लीजिए' => 'How to take photos', 'फ़ोटो नहीं लग पाई।' => 'Photo could not be added.',
'फ़ोटो नहीं लग पाई। साफ़ तस्वीर चुनिए (8 MB तक)।' => 'Photo could not be added. Choose a clear image (up to 8 MB).', 'फ़ोटो लग गई।' => 'Photo added.', 'फ़ोटो लग चुकी' => 'Has photo', 'फ़ोटो लगाइए' => 'Add photo',
'फ़ोटो लगाना ज़रूरी नहीं — जिसकी फ़ोटो नहीं होगी उसका साफ़ icon दिखेगा।' => 'Photos are optional — products without one show a clean icon.', 'फ़ोटो हटा दी — अब icon दिखेगा।' => 'Photo removed — the icon will show now.',
'बदलिए' => 'Change', 'बाक़ी पर icon दिख रहा है' => 'the rest show an icon', 'मात्रा' => 'Quantity', 'यह सामान इसी मात्रा में पहले से है।' => 'This product already exists with this quantity.', 'यह हिस्सा अब' => 'This section is now on the',
'या किसी और की फ़ोटो मत लगाइए' => 'or anyone else’s photos', 'रोज़ 5-10 फ़ोटो भी लगाएँगे तो हफ़्ते भर में पूरी लिस्ट रंगीन हो जाएगी।' => 'Even 5–10 photos a day will cover the whole list in a week.',
'रोज़ का' => 'Daily', 'रोज़ का सामान' => 'Daily essentials', 'रोज़ के सामान में दिखाइए' => 'Show in daily essentials', 'वेबसाइट पर दिखाइए' => 'Show on website', 'सबसे पहले “रोज़ का सामान” वाले' => 'Start with the “Daily essentials”',
'साग — अंग्रेज़ी में भी लिखिए' => 'greens — also write it in English', 'सामान और फ़ोटो' => 'Products & photos', 'सामान का नाम (हिंदी में)' => 'Product name (Hindi)', 'सामान की फ़ोटो लगाइए — वही सबसे ज़्यादा दिखते हैं।' => 'Add product photos — they are seen the most.',
'सामान को सफ़ेद या सादे कागज़ पर रखकर ऊपर से खींचिए' => 'Place the product on white or plain paper and shoot from above', 'सामान दिख रहे हैं' => 'products visible',
'सीधी खींचिए — website ख़ुद चौकोर काटकर छोटी कर देती है' => 'Shoot straight — the website crops it square and resizes it', 'सेवा की फ़ोटो लग गई।' => 'Service photo added.', 'सेवाओं की फ़ोटो लगाइए' => 'Add service photos',
'हिस्सा' => 'Section', 'होम पेज की सेवाओं की फ़ोटो' => 'Home page service photos',
// ---- photos (old bulk)
'अगर सारी फ़ोटो कंप्यूटर में रखी हैं, तो उन्हें सामान के' => 'If all photos are on a computer, name them after the product’s',
'अभी इनमें चित्र (icon) है। असली फ़ोटो लगाते ही होम पेज बदल जाएगा।' => 'These show an icon now. The home page updates as soon as you add a real photo.',
'इनका नाम बदलकर नंबर रख दीजिए, फिर दोबारा भेजिए' => 'rename these to the number and upload again', 'इस हिस्से का सारा सामान हो चुका।' => 'Everything in this section is done.',
'इससे ज़्यादा चुनेंगे तो बाक़ी छूट जाएँगी' => 'if you choose more, the rest are skipped', 'एक अच्छी फ़ोटो — जैसे अपनी बोलेरो की, या किसी लॉन की जहाँ आपने डिलीवरी की थी।' => 'One good photo — like your own Bolero, or a lawn you served.',
'एक के बाद एक करते जाइए।' => 'Go one by one.', 'एक फ़ोटो' => 'One photo', 'एक साथ — कंप्यूटर से कई फ़ोटो' => 'Bulk — many photos from a computer', 'करके भेजिए।' => 'and upload.',
'कुछ बिगड़ेगा नहीं।' => 'Nothing will break.', 'के नाम से रख दीजिए' => 'as the file name', 'चढ़ रही है' => 'Uploading', 'चुनी हुई फ़ोटो चढ़ाइए' => 'Upload selected photos', 'छोटी की जा रही है' => 'Resizing',
'जिनकी फ़ोटो नहीं है, उनकी जगह अपने आप चित्र (icon) दिखता है — इसलिए कोई जल्दी नहीं।' => 'Products without a photo show an icon automatically — so no rush.', 'जैसे' => 'e.g.', 'तक' => 'up to', 'तक लेता है' => 'at most',
'तेज़ तरीक़ा — दबाइए और फ़ोटो चुनिए' => 'Fast way — tap and choose a photo', 'दबाते ही फ़ोन का कैमरा या गैलरी खुलेगी।' => 'Tapping opens the phone camera or gallery.', 'दुकानें' => 'Shops', 'नंबर की सूची' => 'Number list',
'नहीं चढ़ी। दोबारा कीजिए।' => 'did not upload. Try again.', 'नीचे हर सामान का अपना डिब्बा है।' => 'Each product has its own box below.', 'नेट बीच में कट गया। दोबारा कीजिए।' => 'Network dropped. Try again.',
'पर दिखते हैं — गाड़ी, लॉन, टेंट, किताबें, दुकानें।' => '— vehicles, lawns, tents, books, shops.', 'पहले वही सामान कीजिए जो सबसे ज़्यादा बिकता है।' => 'Do the best-selling products first.', 'फ़ोटो का नाम इनमें से नंबर रख दीजिए।' => 'Name each photo with one of these numbers.',
'फ़ोटो का सामान नहीं मिला' => 'No product found for the photo', 'फ़ोटो चढ़ नहीं पाई' => 'Photo did not upload', 'फ़ोटो चुनते ही वह अपने आप चढ़ जाएगी — पेज दोबारा नहीं खुलेगा, कुछ सेव नहीं करना।' => 'The photo uploads as soon as you choose it — no reload, nothing to save.',
'फ़ोटो लग गईं' => 'Photos added', 'फिर नीचे से सारी फ़ोटो एक बार में चुन लीजिए।' => 'Then select all photos at once below.', 'बहुत बड़ी है, या तस्वीर नहीं है)' => 'too large, or not an image)',
'में से' => 'of', 'यह सर्वर एक बार में' => 'This server accepts at most', 'यहाँ सिर्फ़ वही हैं जिनकी फ़ोटो बाक़ी है।' => 'Only products still missing a photo are shown here.', 'या' => 'or',
'ये वही डिब्बे हैं जो' => 'These are the boxes shown on the', 'सब तरह का' => 'All kinds', 'सर्वर ने अजीब जवाब दिया' => 'Unexpected server response', 'सामान का नाम भी चलेगा — जैसे' => 'The product name also works — e.g.',
'सामान की फ़ोटो लग चुकी' => 'products have photos', 'सिर्फ़ बची हुई' => 'Only missing', 'से उठाई हुई फ़ोटो मत लगाइए।' => '— do not use photos taken from there.', 'सेवा और हिस्सों की फ़ोटो' => 'Service & section photos',
'होम पेज' => 'Home page', 'होम पेज का डिब्बा' => 'Home page box', '। दोबारा कीजिए।' => '. Try again.', '। नंबर नीचे की सूची में लिखे हैं (हर डिब्बे के ऊपर बाएँ भी दिखते हैं)।' => '. Numbers are in the list below (also at the top-left of each box).',
'। नाम न मिला तो वह फ़ोटो छोड़ दी जाएगी' => '. If no name matches, that photo is skipped',
// ---- rates
'गाँव' => 'Village', 'गाँव और डिलीवरी रेट' => 'Villages & delivery rates', 'गाँव और रेट' => 'Villages & rates', 'गाँव का नाम' => 'Village name', 'गाँव जोड़ दिया गया।' => 'Village added.', 'गाँव हटा दिया गया।' => 'Village removed.',
'नया गाँव जोड़िए' => 'Add a village', 'यहाँ बदलेंगे तो वेबसाइट और ऑर्डर फ़ॉर्म, दोनों में अपने आप लग जाएगा। 0 का मतलब “कॉल करके पूछिए”।' => 'Changes here apply to both the website and the order form. 0 means “call to ask”.',
'रेट सेव हो गए।' => 'Rates saved.', 'सब सेव कीजिए' => 'Save all',
// ---- sarathi
'अभी कोई डिलीवरी वाला नहीं है' => 'No delivery riders yet', 'अभी कोई दिक्कत नहीं' => 'No issues right now', 'उसी चक्कर में अगली-अगली डिलीवरी पर यह रेट। इसी वजह से एक ऑर्डर का ख़र्च गिरता है' => 'This rate applies to each further delivery on the same trip. That is why the cost per order drops',
'एक डिलीवरी का' => 'Per delivery', 'एक ही चक्कर में अगली डिलीवरी का रेट' => 'Rate for each extra delivery on the same trip', 'एक ही चक्कर वाला रेट' => 'Same-trip rate', 'एक ही चक्कर वाला रेट सेव हो गया।' => 'Same-trip rate saved.',
'एडवांस' => 'Advance', 'और आप दुकान को सस्ता दे पाते हैं।' => 'and you can offer shops a lower price.', 'किसी का पैसा अपने पास नहीं रखता। यह पन्ना सिर्फ़ गिनती रखता है' => 'does not hold anyone’s money. This page only keeps count',
'खाते में दर्ज हो गया।' => 'Recorded in the account.', 'खुली दिक्कतें' => 'Open issues', 'चक्कर की पहली डिलीवरी पर सारथी का पूरा रेट लगता है।' => 'The rider’s full rate applies to the first delivery of a trip.', 'चालू' => 'Active',
'टीम में नया लॉगिन बनाइए (role: delivery), या' => 'Create a new team login (role: delivery), or', 'डिलीवरी वाला रास्ते से जो बताएगा, वो यहाँ आएगा।' => 'Whatever the rider reports on the way appears here.', 'दर्ज कीजिए' => 'Record', 'दिया' => 'Paid',
'नगद' => 'Cash', 'निपट गया' => 'Resolved', 'निपटा हुआ मान लिया गया।' => 'Marked as resolved.', 'पार्टनर आवेदन मंज़ूर कीजिए।' => 'approve partner applications.', 'पैसा आप अपने खाते से सीधे सारथी के खाते में भेजते हैं।' => 'You pay riders directly from your account to theirs.',
'पैसा दिया' => 'Payment made', 'बना' => 'Earned', 'बाकी' => 'Due', 'बैंक' => 'Bank', 'याद रखिए' => 'Remember', 'रकम सही नहीं है।' => 'Amount is not valid.', 'रास्ते से आई दिक्कतें' => 'Issues reported on the way',
'रेट 0 से 5000 के बीच रखिए।' => 'Keep the rate between 0 and 5000.', 'रेट तय नहीं है — इसीलिए इनके खाते में ₹0 दिख रहा है।' => 'No rate set — that is why their account shows ₹0.', 'रेट सेव हो गया।' => 'Rate saved.', 'सारथी' => 'Rider', 'सारथी और उनका खाता' => 'Riders & their accounts', 'हर सारथी' => 'Each rider',
// ---- settings
'अभी ऑर्डर भेज दीजिए, सुबह सबसे पहले जाएगा”।' => 'Send your order now, it goes out first thing in the morning”.', 'अभी कोई छुट्टी नहीं डाली गई।' => 'No holidays added yet.', 'अभी कोई त्योहार नहीं' => 'No festival right now',
'आगे की छुट्टियाँ' => 'Upcoming holidays', 'आज के लिए बंद कर दिया — वेबसाइट पर दिख रहा है।' => 'Closed for today — shown on the website.', 'आज बंद कर दीजिए' => 'Close for today', 'आज बंद करना है?' => 'Closing today?',
'उस दिन वेबसाइट अपने आप “आज बंद है” दिखा देगी, आपको याद रखने की ज़रूरत नहीं।' => 'On that day the website shows “Closed today” automatically.', 'कब तक डिलीवरी' => 'Delivery until', 'कब से खुलते हैं' => 'Opens at', 'खुले हैं' => 'Open',
'ग्राहक को पहले ही पता चल जाता है, तो बेकार के फ़ोन नहीं आते।' => 'Customers know in advance, so there are no wasted calls.', 'छुट्टी का दिन' => 'Holiday date', 'छुट्टी जोड़ दी गई — उस दिन वेबसाइट पर “आज बंद है” दिखेगा।' => 'Holiday added — the website will show “Closed today” that day.', 'छुट्टी हटा दी गई।' => 'Holiday removed.',
'जैसे: होली की वजह से आज बंद है, कल सुबह 8 बजे से खुलेंगे' => 'e.g. Closed today for Holi, open tomorrow from 8 AM', 'त्योहार की पट्टी' => 'Festival banner', 'त्योहार की पट्टी चालू कर दी।' => 'Festival banner turned on.', 'त्योहार की पट्टी बंद कर दी।' => 'Festival banner turned off.',
'नवरात्रि, दशहरा, करवा चौथ, दीवाली, छठ — अपने आप दिखती है। बंद करनी हो तो यहाँ दबाइए।' => 'Navratri, Dussehra, Karwa Chauth, Diwali, Chhath — shows automatically. Press here to turn it off.',
'पट्टी चालू कीजिए' => 'Turn banner on', 'पट्टी बंद कीजिए' => 'Turn banner off', 'पर दिख रहा है' => 'is showing on', 'फिर से खोल दिया।' => 'Reopened.', 'फिर से खोलिए' => 'Reopen', 'बंद है' => 'Closed',
'बंद होने के बाद भी ऑर्डर आता रहेगा — वेबसाइट लिख देती है' => 'Orders still come in after closing — the website says', 'रोज़ का समय' => 'Daily hours', 'वजह — हिंदी में (ग्राहक को दिखेगी)' => 'Reason — in Hindi (shown to customers)',
'वेबसाइट पर देखिए' => 'View on website', 'वेबसाइट पर सबसे ऊपर “अभी खुले हैं / आज बंद है” यहीं से तय होता है।' => 'This controls the “Open now / Closed today” label at the top of the website.', 'समय सेव हो गया।' => 'Hours saved.',
'समय, छुट्टी और त्योहार' => 'Hours, holidays & festival', 'हटाने के लिए तारीख़ पर दबाइए।' => 'Tap a date to remove it.', 'होली, दीवाली, शादी — पहले से डाल दीजिए।' => 'Holi, Diwali, weddings — add them in advance.',
'रोज़ सुबह 8 से रात 8 बजे तक' => 'Daily 8 AM – 8 PM', 'होम पेज पर “आज बंद” दिखेगा' => 'Home page will show “Closed today”',
// ---- summary / support
'ऑर्डर' => 'Orders', 'सबसे ज़्यादा ऑर्डर करने वाले ग्राहक' => 'Top customers', 'सबसे ज़्यादा ऑर्डर वाले गाँव' => 'Top villages', 'हिसाब' => 'Accounts',
'अस्वीकृत' => 'Rejected', 'जाँच में' => 'Under review', 'तय करना बाकी' => 'Decision pending', 'तय हुआ' => 'Decided', 'दुकान को वापस मिला' => 'Returned to shop', 'निर्णय बाकी' => 'Decision pending',
'मिल गया' => 'Received', 'वापसी pickup नहीं' => 'No return pickup', 'समाधान हुआ' => 'Resolved', 'सामान ले लिया गया' => 'Item collected', 'स्वीकृत' => 'Approved',
// ---- transport
'अभी कोई गाड़ी दर्ज नहीं है।' => 'No vehicles registered yet.', 'असली कागज़ अपनी आँख से देखिए' => 'Check the original papers yourself', 'आज खाली' => 'Free today', 'आज बताया नहीं' => 'Not updated today', 'आज व्यस्त' => 'Busy today',
'आप कागज़ देखकर मंज़ूरी देते हैं — उसके बाद उनकी गाड़ी बुकिंग पेज पर दिखने लगती है।' => 'You approve after checking papers — then their vehicle appears on the booking page.', 'इन गाड़ियों के कागज़ जल्दी खत्म हो रहे हैं' => 'Papers expiring soon for these vehicles',
'इसलिए' => 'So', 'एक ट्रिप जोड़ दी गई।' => 'One trip added.', 'कागज़ (गाड़ी वाले ने जो बताया)' => 'Papers (as stated by owner)', 'कागज़ की फ़ोटो WhatsApp पर मँगाइए, अपनी आँख से देखिए, तभी “कागज़ देख लिए” दबाइए।' => 'Ask for photos of the papers on WhatsApp, check them yourself, then press “Papers checked”.',
'कागज़ खत्म हो रहे' => 'Papers expiring', 'कागज़ देख लिए' => 'Papers checked', 'कागज़ देखकर मंज़ूर कीजिए' => 'Approve after checking papers', 'कागज़ देखे गए' => 'Papers checked', 'कागज़ देखे गए — दर्ज कर दिया।' => 'Papers checked — recorded.', 'कागज़ माँगिए' => 'Ask for papers',
'किराए पर सवारी ले जाने के लिए कमर्शियल परमिट, चालू बीमा, फिटनेस और सही लाइसेंस ज़रूरी है।' => 'Carrying paying passengers needs a commercial permit, valid insurance, fitness and the right licence.',
'कॉल' => 'Call', 'कोड भेजिए' => 'Send code', 'खत्म कागज़ वाली गाड़ी को ट्रिप मत भेजिए — दुर्घटना में ज़िम्मेदारी आप पर आ सकती है।' => 'Do not send trips to vehicles with expired papers — you could be liable in an accident.', 'खत्म हो चुका' => 'Expired',
'गाड़ियाँ' => 'Vehicles', 'गाड़ी छिपा दी गई।' => 'Vehicle hidden.', 'गाड़ी मंज़ूर हो गई। अब बुकिंग पेज पर दिखेगी।' => 'Vehicle approved. It now shows on the booking page.', 'गाड़ी वाले को भेज दीजिए।' => 'Send it to the vehicle owner.', 'गाड़ी वाले ख़ुद' => 'Vehicle owners themselves',
'चालू गाड़ियाँ' => 'Active vehicles', 'छिपाइए' => 'Hide', 'छिपी' => 'Hidden', 'जिसके कागज़ नहीं हैं उसे मंज़ूरी मत दीजिए — भले वो जान-पहचान का हो।' => 'Do not approve anyone without papers — even someone you know.', 'ट्रिप' => 'Trips',
'दिन के अंदर' => 'days', 'ध्यान रखने वाली बात' => 'Important', 'पर जाकर रोज़ बता दीजिए कि गाड़ी खाली है या नहीं।' => 'and update daily whether the vehicle is free.', 'परमिट' => 'Permit', 'पुष्टि हटा दी गई।' => 'Verification removed.', 'पुष्टि हटाइए' => 'Remove verification',
'फिटनेस' => 'Fitness', 'फिर नीचे “कागज़ देख लिए” दबाइए। बिना देखे मंज़ूरी मत दीजिए।' => 'then press “Papers checked” below. Never approve without checking.', 'बाहर भी' => 'Outstation', 'बीमा' => 'Insurance', 'मंज़ूर कीजिए' => 'Approve', 'मंज़ूरी बाकी' => 'Pending approval',
'यह सिर्फ़ इनका कहा हुआ है।' => 'This is only what they stated.', 'रात में भी' => 'Night too', 'रेट' => 'Rate', 'लाइसेंस' => 'Licence', 'सब निपट गए' => 'All done', 'सीट' => 'Seats', 'से जुड़ते हैं।' => 'register here.',
// ---- users
'काम' => 'Role', 'टीम' => 'Team', 'टीम के लॉगिन' => 'Team logins', 'डिज़ाइनर' => 'Designer', 'डिज़ाइनर (ऑफ़र और फ़ोटो)' => 'Designer (offers & photos)', 'डिलीवरी' => 'Delivery', 'डिलीवरी पार्टनर' => 'Delivery partner',
'नया पासवर्ड' => 'New password', 'नया लॉगिन बन गया।' => 'New login created.', 'नया लॉगिन बनाइए' => 'Create a new login', 'नाम, यूज़रनेम (कम से कम 3 अक्षर) और पासवर्ड (कम से कम 6 अक्षर) ज़रूरी हैं।' => 'Name, username (min 3 characters) and password (min 6) are required.',
'पासवर्ड' => 'Password', 'पासवर्ड कम से कम 6 अक्षर का रखिए।' => 'Password must be at least 6 characters.', 'पासवर्ड बदल दिया गया।' => 'Password changed.', 'बनाइए' => 'Create', 'मालिक' => 'Owner', 'यह यूज़रनेम पहले से है।' => 'This username already exists.', 'यूज़रनेम' => 'Username',
// ---- bpo
'अभी कोई बुकिंग नहीं है।' => 'No bookings yet.', 'कमीशन' => 'Commission', 'कर पाएँगे?' => 'can you do it?', 'ग्राहक' => 'Customer', 'ग्राहक ने चुना' => 'Customer chose', 'चुनी हुई गाड़ी' => 'Chosen vehicle', 'जी, Maakit से — एक बुकिंग है।' => 'ji, Maakit here — there is a booking.',
'जैसे: 2 लॉन खाली हैं' => 'e.g. 2 lawns free', 'जो तय हुआ' => 'Agreed', 'नई बुकिंग देखना बाकी है — रेट पता करके ग्राहक को कॉल कीजिए।' => 'new bookings to check — find the rate and call the customer.', 'नोट' => 'Note', 'नोट जोड़िए' => 'Add note',
'पक्की हो गई — भेजिए' => 'Confirmed — send', 'पूरा ब्योरा भेजिए' => 'Send full details', 'बुकिंग' => 'Bookings', 'बुकिंग कोड' => 'Booking code', 'बुकिंग मिल गई — भेजिए' => 'Booking received — send', 'बुकिंग सेव हो गई।' => 'Booking saved.', 'भेजिए' => 'Send', 'रेट भेजिए' => 'Send rate', 'सेवा' => 'Service', 'हमारा हिस्सा' => 'Our share',
'आज के ऑर्डर' => 'Today’s orders', 'इस ऑर्डर में लिस्ट वाला सामान नहीं है।' => 'This order has no listed products.', 'एक सामान के दो दाम आने पर Maakit बीच वाला लेता है, इसलिए एक-आध ग़लती से कुछ नहीं बिगड़ता।' => 'With two prices for one product Maakit takes the middle, so one mistake does no harm.',
'कम' => 'Low', 'कोई दाम नहीं लिखा गया।' => 'No price entered.', 'कोई भी ब्रांड' => 'Any brand', 'खाली छोड़ दीजिए' => 'leave blank', 'खुद सीखता जाएगा।' => 'keeps learning by itself.', 'ग्राहक ने बोलकर, लिखकर या फ़ोटो से मँगाया था — इसलिए यहाँ दाम नहीं लिखे जा सकते।' => 'The customer ordered by voice, text or photo — so prices can’t be entered here.',
'ज़रूरी नहीं है कि सब भरें।' => 'You don’t have to fill everything.', 'ज़्यादा' => 'High', 'जो याद न हो उसे' => 'Anything you don’t remember,', 'जोड़' => 'Total', 'दाम सेव कीजिए' => 'Save prices', 'दुकान' => 'Shop', 'बिल' => 'Bill', 'बिल का कुल' => 'Bill total', 'बिल की फ़ोटो — खोलकर देखिए' => 'Bill photo — open to view',
'बिल में जो दाम लगा, वही लिख दीजिए। इससे अगली बार ग्राहक को अंदाज़ा दिखेगा' => 'Enter the prices on the bill. Next time customers will see an estimate', 'बिल से' => 'From bill', 'बिल से मिल गया' => 'Matches bill', 'यह ऑर्डर नहीं मिला।' => 'Order not found.', 'वापस' => 'Back',
'सामान के दाम लिख दिए गए। अब ग्राहकों को अंदाज़ा दिखेगा।' => 'Prices saved. Customers will now see estimates.', 'से मिलाइए' => 'match with',
'इस दिन का कोई ऑर्डर नहीं है।' => 'No orders on this day.', 'कन्फ़र्म + कोड भेजिए' => 'Confirm + send code', 'कॉल से' => 'By call', 'ग्राहक की फ़ोटो' => 'Customer photo', 'चार्ज' => 'Charge', 'डाकिया: हर हाल का अपना सन्देश' => 'Messenger: a message for every status', 'डाकिया: बुकिंग के हर हाल का अपना सन्देश' => 'Messenger: a message for every booking status',
'दीजिए' => 'Give', 'दुकान: हमारी पसंद' => 'Shop: our choice', 'देर हो रही है' => 'Running late', 'नक्शा' => 'Map', 'नया ऑर्डर' => 'New order', 'नया ऑर्डर देखना बाकी है — पहले इन्हें कॉल करके कन्फ़र्म कीजिए।' => 'new orders to check — call and confirm these first.',
'निकल चुका है — भेजिए' => 'Out for delivery — send', 'पहला ऑर्डर' => 'First order', 'पहुँचा दिया — भेजिए' => 'Delivered — send', 'पार्टनर' => 'Partner', 'पेमेंट' => 'Payment', 'बिल के दाम लिख दीजिए — ग्राहकों को अंदाज़ा दिखेगा' => 'Enter bill prices — customers will see estimates',
'वेबसाइट से' => 'From website', 'से' => 'from', 'हर मिनट ख़ुद देखे' => 'auto-refresh every minute',
'अन्य' => 'Other', 'आकार' => 'Size', 'ऊपर ढूंढकर दबाइए — नीचे अपने आप लिख जाएगा। हाथ से भी लिख सकते हैं।' => 'Search above and tap — it fills in below. You can also type by hand.', 'एक और ऑर्डर' => 'Another order',
'ऑर्डर कहाँ से आया' => 'Order source', 'ऑर्डर नंबर' => 'Order number', 'ऑर्डर बन गया' => 'Order created', 'ऑर्डर बनाइए' => 'Create order', 'कन्फर्म मैसेज WhatsApp पर भेजिए' => 'Send confirmation on WhatsApp', 'किराना' => 'Grocery',
'कॉल पर तय करना बाकी' => 'To be decided on call', 'कॉल या WhatsApp पर आया ऑर्डर यहाँ लिखिए। ऑर्डर नंबर और कोड अपने आप बनेंगे।' => 'Enter orders received by call or WhatsApp here. Order number and code are generated automatically.',
'खाना/नाश्ता' => 'Food / snacks', 'ग्राहक का नाम' => 'Customer name', 'ग्राहक की बताई दुकान, या खाली' => 'Shop named by customer, or blank', 'घर की पहचान' => 'Landmark', 'डिलीवरी कोड' => 'Delivery code', 'डिलीवरी चार्ज' => 'Delivery charge',
'डिलीवरी चार्ज ₹ (खाली छोड़ेंगे तो अपने आप लगेगा)' => 'Delivery charge ₹ (blank = automatic)', 'नया ऑर्डर लिखिए' => 'Enter a new order', 'नाम, 10 अंकों का मोबाइल, गाँव और सामान — चारों ज़रूरी हैं।' => 'Name, 10-digit mobile, village and items — all four are required.',
'बाज़ार' => 'Market', 'मोबाइल (10 अंक)' => 'Mobile (10 digits)', 'लिस्ट से ढूंढिए — आटा, तेल, दाल' => 'Search the list — atta, oil, dal', 'वज़न' => 'Weight', 'सब्ज़ी-फल' => 'Fruits & vegetables', 'सामान (मात्रा के साथ)' => 'Items (with quantity)',
'सामान लेते समय यह कोड डिलीवरी पार्टनर को बताइए। किसी और को न बताएँ।' => 'Tell this code to the delivery partner when receiving items. Don’t share it with anyone else.', 'सेक्टर' => 'Sector',
'आज का हिसाब' => 'Today’s accounts', 'कुल ऑर्डर' => 'Total orders', 'कैंसिल' => 'Cancelled', 'कैश' => 'Cash', 'कैश जो जमा होना है' => 'Cash to be deposited', 'गाँव के हिसाब से' => 'By village', 'डिलीवरी से कमाई' => 'Delivery income', 'पहुँचाए गए' => 'Delivered',
'यह उन ऑर्डर का जोड़ है जिनमें डिलीवरी पर कैश लिया गया (सामान + डिलीवरी चार्ज)।' => 'Total of orders paid in cash on delivery (items + delivery charge).',
// ---- nakal
'अपने फ़ोन में उतार लीजिए' => 'Download to your phone', 'अभी नक़ल उतारिए' => 'Create backup now', 'आख़िरी' => 'Latest', 'इसलिए एक नक़ल अपने फ़ोन या लैपटॉप में भी रखिए।' => 'So keep a copy on your phone or laptop too.', 'उतारिए' => 'Download',
'ऊपर वाला बटन दबाइए, फिर' => 'Press the button above, then', 'ऑर्डर, ग्राहक, सामान, दाम, किताबें — सब यहीं था।' => 'Orders, customers, products, prices, books — everything was here.', 'टेबल' => 'Tables', 'डेटाबेस कहीं नहीं रखा था' => 'The database was not stored anywhere',
'डेटाबेस की नक़ल' => 'Database backup', 'नक़ल' => 'Backup', 'नक़ल नहीं बन पाई' => 'Backup failed', 'नक़ल बन गई' => 'Backup created', 'नक़ल वेबसाइट से' => 'Backups are kept outside the website',
'नक़ल सर्वर पर रहने से डेटाबेस बिगड़ने पर बचाव होता है — पर अगर पूरी' => 'A server backup protects you if the database breaks — but if the whole', 'नक़लें सँभाल कर रखी जाती हैं।' => 'backups are kept.', 'पंक्तियाँ' => 'Rows', 'पर' => 'on',
'पहले की नक़लें' => 'Earlier backups', 'बाहर' => 'outside', 'महीने में एक बार यह ज़रूर कीजिए' => 'Do this at least once a month', 'यह पन्ना उसी की नक़ल उतारता है।' => 'This page backs it up.', 'रखी जाती है, जहाँ कोई पहुँच नहीं सकता।' => 'where no one can reach it.',
'वापस डालना हो तो: cPanel → phpMyAdmin → Import → यही फ़ाइल चुन दीजिए।' => 'To restore: cPanel → phpMyAdmin → Import → choose this file.', 'वेबसाइट की फ़ाइलें GitHub पर रखी हैं — वो कभी भी वापस आ जाती हैं।' => 'Website files are on GitHub — they can always be restored.',
'होस्टिंग ही चली जाए, तो सर्वर वाली नक़ल भी चली जाएगी।' => 'hosting is lost, the server backup goes with it.',
// ---- update
'अब देख लीजिए' => 'Check now', 'अभी चल रहा है' => 'Running now', 'और' => 'and', 'किसी को मत दीजिए — इससे वेबसाइट बदली जा सकती है।' => 'Never share it — it can change the website.', 'कीजिए, फिर यही पन्ना दोबारा खोलिए।' => 'then open this page again.',
'कुछ भी गड़बड़ हो तो' => 'If anything looks wrong', 'को हाथ नहीं लगाया जाएगा।' => 'will not be touched.', 'खुल रहा है?' => 'opening?', 'चाबी कोई भी लंबी अनजान लाइन हो सकती है।' => 'The key can be any long random line.', 'चाबी नहीं मिली' => 'Key not found',
'छूने से पहले डेटाबेस की नक़ल रख ली थी' => 'A database backup was taken first', 'ठीक दिख रही है?' => 'looks fine?', 'डेटाबेस भी तैयार कर दिया' => 'Database updated too',
'ध्यान दीजिए: डेटाबेस की नक़ल नहीं बन पाई। Admin → नक़ल से एक बार ख़ुद उतार लीजिए।' => 'Note: the database backup failed. Take one yourself from Admin → Backups.', 'नई फ़ाइलें' => 'New files', 'नया कोड लगा दें?' => 'Install the new code?',
'नीचे footer में version बदला हुआ दिखना चाहिए' => 'the version in the footer should have changed', 'पर दायाँ क्लिक' => 'right-click on', 'पहले' => 'Before', 'पहले से ठीक' => 'Already up to date', 'पिछली बार' => 'Last time', 'फ़ोल्डर में पड़ा है' => 'is in the folder', 'बदली गईं' => 'Changed',
'में एक लाइन चाहिए' => 'needs one line in', 'यह updater चलने के लिए' => 'For this updater to run', 'यह सूची Claude को भेज दीजिए।' => 'Send this list to Claude.', 'ये नहीं हो पाईं' => 'These failed', 'रुक गया — कुछ नहीं बदला' => 'Stopped — nothing changed',
'वह public_html से बाहर है, कोई देख नहीं सकता।' => 'It is outside public_html, no one can see it.', 'वेबसाइट खोलिए' => 'Open website', 'वेबसाइट जैसी थी वैसी ही चल रही है। यह संदेश Claude को भेज दीजिए।' => 'The website is still running as before. Send this message to Claude.',
'सब कुछ पहले से नया था — कुछ बदलने की ज़रूरत नहीं पड़ी।' => 'Everything was already up to date — nothing needed changing.', 'सबसे आसान' => 'Easiest', 'सबसे नीचे यह लाइन जोड़कर' => 'adding this line at the bottom',
'से नया कोड उठाकर लग जाएगा। पुरानी फ़ाइलों का बैकअप पहले रख लिया जाएगा।' => 'and install the new code. Old files are backed up first.', 'हाँ, अभी लगा दीजिए' => 'Yes, install now', 'हो गया' => 'Done',
// ---- common UI words used across old pages
'सेव हो गया' => 'Saved', 'हटाइए?' => 'Remove?', 'खोजिए' => 'Search', 'लॉगआउट' => 'Log out', 'वेबसाइट देखिए' => 'View website', 'कुल' => 'Total', 'आज' => 'Today', 'कल' => 'Yesterday',
'मुनीम' => 'Ledger',
'कागज़ की फ़ोटो' => 'Ask for photos of the papers on',
'पर मँगाइए, अपनी आँख से देखिए, तभी “कागज़ देख लिए” दबाइए' => ', check them yourself, then press “Papers checked”',
'गाड़ी नहीं चलाता — आप सिर्फ़ ग्राहक और गाड़ी वाले को मिलाते हैं। फिर भी अगर आपने' => 'does not run vehicles — you only connect customers and owners. Still, if you',
'बिना कागज़ वाली गाड़ी को ट्रिप भेजी और कुछ हो गया, तो बात आप तक आ सकती है।' => 'send a trip to a vehicle without papers and something happens, it can come back to you.',
'आप कागज़ देखकर मंज़ूरी देते हैं — उसके बाद उनकी गाड़ी बुकिंग पेज पर दिखने लगती है।' => 'You approve after checking papers — then the vehicle appears on the booking page.',
'कागज़ की फ़ोटो WhatsApp पर मँगाइए, अपनी आँख से देखिए, तभी “कागज़ देख लिए” दबाइए।' => 'Ask for photos of the papers on WhatsApp, check them yourself, then press “Papers checked”.', 'पार्टनर आवेदन' => 'Partner applications', 'स्टेटस बदलिए' => 'Change status', 'हाँ' => 'Yes', 'नहीं' => 'No',
    ];
}
