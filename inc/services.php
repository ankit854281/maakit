<?php
// ============================================================
// Maakit — booking wali sewayein (English + हिंदी)
//
// label 'l'  => ['English', 'हिंदी']
// option     => "English|हिंदी"  (database me poora string jata hai,
//               taaki BPO ko dono dikhein)
//
// Nayi sewa jodni ho to bas yahan ek entry jodiye.
// field type: select | date | time | number | text | area | multi
// ============================================================

/** option ka wo hissa jo abhi ki bhasha me hai */
function opt_label($o) {
    $p = explode('|', $o, 2);
    if (count($p) < 2) return $o;
    return is_hi() ? $p[1] : $p[0];
}
/** field ka label */
function f_label($l) { return is_array($l) ? t($l[0], $l[1]) : $l; }

function services() {
    $today = date('Y-m-d');
    return [

    'gaadi' => [
        'name' => 'गाड़ी बुकिंग', 'en' => 'Vehicle Booking', 'icon' => 'ride',
        'tag'  => t('Bolero, tempo, tractor, ambulance', 'बोलेरो, टेम्पो, ट्रैक्टर, एम्बुलेंस'),
        'lead' => t('Wedding, pilgrimage, hospital or hauling goods — a vehicle from your door.',
                    'शादी, तीरथ, अस्पताल या सामान ढोना — गाड़ी घर से लीजिए।'),
        'cat'  => 'gaadi',
        'fields' => [
            ['k'=>'vehicle','t'=>'select','l'=>['Which vehicle','कौन सी गाड़ी'],'req'=>1,
             'o'=>['Bolero|बोलेरो','Scorpio / XUV|स्कॉर्पियो / XUV','Tata Magic|टाटा मैजिक','Tempo Traveller|टेम्पो ट्रैवलर','Auto|ऑटो','Tractor-trolley|ट्रैक्टर-ट्रॉली','Dala / DCM (goods)|डाला / DCM (सामान)','Bus|बस','Ambulance|एम्बुलेंस','Anything — you decide|कुछ भी — आप बता दीजिए']],
            ['k'=>'trip','t'=>'select','l'=>['Trip type','आना-जाना'],'req'=>1,
             'o'=>['One way|सिर्फ़ जाना (one way)','Round trip|जाना और आना (round trip)','Full day|पूरे दिन के लिए']],
            ['k'=>'from','t'=>'text','l'=>['From','कहाँ से'],'req'=>1,'ph'=>t('village / mohalla','गाँव / मोहल्ला')],
            ['k'=>'to','t'=>'text','l'=>['To','कहाँ तक'],'req'=>1,'ph'=>t('e.g. Varanasi station, Bhadohi hospital','जैसे: वाराणसी स्टेशन, भदोही अस्पताल')],
            ['k'=>'on_date','t'=>'date','l'=>['Which day','किस दिन'],'req'=>1,'min'=>$today],
            ['k'=>'on_time','t'=>'time','l'=>['What time','कितने बजे'],'req'=>1],
            ['k'=>'qty','t'=>'number','l'=>['How many people','कितने लोग'],'req'=>0,'ph'=>'6','min'=>1,'max'=>60],
        ],
    ],

    'maal' => [
        'name' => 'माल / सामान ढुलाई', 'en' => 'Goods Transport', 'icon' => 'truck',
        'tag'  => t('Chhota hathi, dala, truck, tractor-trolley', 'छोटा हाथी, डाला, ट्रक, ट्रैक्टर-ट्रॉली'),
        'lead' => t('Bricks, sand, grain, furniture, shifting house — tell us what and where, we arrange the vehicle.',
                    'ईंट, बालू, अनाज, फ़र्नीचर, घर शिफ़्ट — क्या और कहाँ, बता दीजिए। गाड़ी हम लगवा देंगे।'),
        'cat'  => 'gaadi',
        'fields' => [
            ['k'=>'goods','t'=>'select','l'=>['What goods','कौन सा सामान'],'req'=>1,
             'o'=>['Bricks|ईंट','Sand / gitti / moram|बालू / गिट्टी / मोरंग','Cement|सीमेंट','Grain / fodder|अनाज / भूसा','Fertiliser / seed|खाद / बीज','Furniture|फ़र्नीचर','House shifting|घर शिफ़्ट करना','Shop goods|दुकान का सामान','Machine / pump|मशीन / पंप','Cattle|पशु','Water tanker|पानी का टैंकर','Other|अन्य']],
            ['k'=>'weight','t'=>'select','l'=>['How much (roughly)','कितना (अंदाज़न)'],'req'=>1,
             'o'=>['Up to 1 tonne — Chhota Hathi|1 टन तक — छोटा हाथी','1 – 3 tonnes — Pickup / Dala|1 – 3 टन — पिकअप / डाला','3 – 5 tonnes — Tractor-trolley|3 – 5 टन — ट्रैक्टर-ट्रॉली','5 – 10 tonnes — 6 wheel truck|5 – 10 टन — 6 चक्का ट्रक','More than 10 tonnes — 10 wheel|10 टन से ज़्यादा — 10 चक्का','Don’t know — you decide|पता नहीं — आप बता दीजिए']],
            ['k'=>'from','t'=>'text','l'=>['Pick up from','कहाँ से उठाना है'],'req'=>1,'ph'=>t('village / bhatta / market','गाँव / भट्ठा / बाज़ार')],
            ['k'=>'to','t'=>'text','l'=>['Drop at','कहाँ पहुँचाना है'],'req'=>1,'ph'=>t('full address','पूरा पता')],
            ['k'=>'on_date','t'=>'date','l'=>['Which day','किस दिन'],'req'=>1,'min'=>$today],
            ['k'=>'on_time','t'=>'time','l'=>['What time','कितने बजे'],'req'=>0],
            ['k'=>'qty','t'=>'number','l'=>['How many trips','कितने फेरे'],'req'=>0,'ph'=>'1','min'=>1,'max'=>50],
            ['k'=>'items','t'=>'multi','l'=>['Anything else needed','और क्या चाहिए'],'req'=>0,
             'o'=>['Labour for loading|चढ़ाने वाले मज़दूर','Labour for unloading|उतारने वाले मज़दूर','Tarpaulin / cover|तिरपाल / ढकने का','Weighing (dharmkanta)|तौल (धर्मकांटा)','Return trip too|वापसी का फेरा भी']],
        ],
    ],

    'lawn' => [
        'name' => 'लॉन / मैरिज हॉल', 'en' => 'Venue Booking', 'icon' => 'lawn',
        'tag'  => t('Wedding, tilak, birthday, bhandara', 'शादी, तिलक, जन्मदिन, भंडारा'),
        'lead' => t('Tell us the date — we’ll find which lawns are free and what they cost.',
                    'तारीख़ बता दीजिए — हम खाली लॉन और उनका रेट पता करके बताएँगे।'),
        'cat'  => 'lawn',
        'fields' => [
            ['k'=>'event','t'=>'select','l'=>['Which occasion','कौन सा कार्यक्रम'],'req'=>1,
             'o'=>['Wedding|शादी','Tilak / engagement|तिलक / सगाई','Birthday|जन्मदिन','Mundan|मुंडन','Bhandara / satsang|भंडारा / सत्संग','Janeu|जनेऊ','Other|अन्य']],
            ['k'=>'on_date','t'=>'date','l'=>['Which day','किस दिन'],'req'=>1,'min'=>$today],
            ['k'=>'days','t'=>'number','l'=>['How many days','कितने दिन'],'req'=>0,'ph'=>'1','min'=>1,'max'=>15],
            ['k'=>'qty','t'=>'number','l'=>['Roughly how many guests','कितने मेहमान (अंदाज़न)'],'req'=>1,'ph'=>'300','min'=>10,'max'=>5000],
            ['k'=>'budget','t'=>'select','l'=>['Budget','बजट'],'req'=>0,
             'o'=>['Not sure — tell me the rates|बता नहीं सकते — रेट बताइए','Up to ₹20,000|₹20,000 तक','₹20,000 – ₹50,000|₹20,000 – ₹50,000','₹50,000 – ₹1 lakh|₹50,000 – ₹1 लाख','More than ₹1 lakh|₹1 लाख से ज़्यादा']],
        ],
    ],

    'tent' => [
        'name' => 'टेंट, साउंड, लाइट', 'en' => 'Tent & Sound', 'icon' => 'tent',
        'tag'  => t('Shamiana, DJ, generator, chairs', 'शामियाना, डीजे, जनरेटर, कुर्सी'),
        'lead' => t('Pick what you need — we’ll talk to the tent people for you.',
                    'जो-जो चाहिए चुन लीजिए, बाक़ी हम टेंट वाले से बात कर लेंगे।'),
        'cat'  => 'tent',
        'fields' => [
            ['k'=>'items','t'=>'multi','l'=>['What do you need','क्या-क्या चाहिए'],'req'=>1,
             'o'=>['Tent / shamiana|टेंट / शामियाना','Chairs & tables|कुर्सी-मेज़','DJ / sound|डीजे / साउंड','Lights, jhalar|लाइट, झालर','Generator|जनरेटर','Stage|स्टेज','Band-baja|बैंड-बाजा','Mattress & dari|गद्दा-दरी','Water cooler|वाटर कूलर','Fans|पंखा']],
            ['k'=>'on_date','t'=>'date','l'=>['Which day','किस दिन'],'req'=>1,'min'=>$today],
            ['k'=>'days','t'=>'number','l'=>['How many days','कितने दिन'],'req'=>0,'ph'=>'1','min'=>1,'max'=>15],
            ['k'=>'qty','t'=>'number','l'=>['How many people will come','कितने लोग आएँगे'],'req'=>0,'ph'=>'200','min'=>10,'max'=>5000],
        ],
    ],

    'halwai' => [
        'name' => 'हलवाई / कैटरिंग', 'en' => 'Catering', 'icon' => 'halwai',
        'tag'  => t('Food for weddings, bhandara, parties', 'शादी, भंडारा, पार्टी का खाना'),
        'lead' => t('Tell us how many people are eating — we’ll get you the rate.',
                    'कितने लोगों का खाना है, बता दीजिए — रेट पता करके बताएँगे।'),
        'cat'  => 'halwai',
        'fields' => [
            ['k'=>'event','t'=>'select','l'=>['For what occasion','किस मौक़े पर'],'req'=>1,
             'o'=>['Wedding|शादी','Tilak / engagement|तिलक / सगाई','Bhandara|भंडारा','Birthday|जन्मदिन','Mundan|मुंडन','Small party at home|घर की छोटी पार्टी','Other|अन्य']],
            ['k'=>'on_date','t'=>'date','l'=>['Which day','किस दिन'],'req'=>1,'min'=>$today],
            ['k'=>'qty','t'=>'number','l'=>['Food for how many','कितने लोगों का'],'req'=>1,'ph'=>'250','min'=>10,'max'=>5000],
            ['k'=>'items','t'=>'multi','l'=>['Which meals','कौन सा खाना'],'req'=>1,
             'o'=>['Breakfast|नाश्ता','Lunch|दोपहर का खाना','Dinner|रात का खाना','Sweets only|सिर्फ़ मिठाई','Chaat / stall|चाट / स्टॉल','Pure vegetarian|शुद्ध शाकाहारी','Non-veg also|नॉन-वेज भी']],
            ['k'=>'budget','t'=>'select','l'=>['Budget per person','प्रति व्यक्ति बजट'],'req'=>0,
             'o'=>['Not sure — tell me the rates|बता नहीं सकते — रेट बताइए','Up to ₹100|₹100 तक','₹100 – ₹200|₹100 – ₹200','₹200 – ₹350|₹200 – ₹350','More than ₹350|₹350 से ज़्यादा']],
        ],
    ],

    'pandit' => [
        'name' => 'पंडित जी / पूजा', 'en' => 'Pandit Booking', 'icon' => 'pandit',
        'tag'  => t('Satyanarayan, griha pravesh, vivah', 'सत्यनारायण, गृह प्रवेश, विवाह'),
        'lead' => t('Name the puja and the day — we’ll fix pandit ji’s time.',
                    'पूजा का नाम और दिन बता दीजिए — पंडित जी का समय पक्का करा देंगे।'),
        'cat'  => 'pandit',
        'fields' => [
            ['k'=>'event','t'=>'select','l'=>['Which puja','कौन सी पूजा'],'req'=>1,
             'o'=>['Satyanarayan katha|सत्यनारायण कथा','Griha pravesh|गृह प्रवेश','Vivah (wedding)|विवाह','Tilak|तिलक','Mundan|मुंडन','Janeu|जनेऊ','Namkaran|नामकरण','Rudrabhishek|रुद्राभिषेक','Navagraha shanti|नवग्रह शांति','Shraadh / pind daan|श्राद्ध / पिंडदान','Kundli / horoscope|कुंडली / जन्मपत्री','Other|अन्य']],
            ['k'=>'on_date','t'=>'date','l'=>['Which day','किस दिन'],'req'=>1,'min'=>$today],
            ['k'=>'on_time','t'=>'time','l'=>['What time','कितने बजे'],'req'=>0],
            ['k'=>'items','t'=>'multi','l'=>['Anything else','और क्या चाहिए'],'req'=>0,
             'o'=>['Send the puja samagri too|पूजा की सामग्री भी मँगवा दीजिए','Havan kund|हवन कुंड','Assistants for the purohit|पुरोहित के सहयोगी','Need an auspicious time picked|मुहूर्त निकलवाना है']],
        ],
    ],

    'photo' => [
        'name' => 'फोटो / वीडियो', 'en' => 'Photography', 'icon' => 'photo',
        'tag'  => t('Wedding, tilak, birthday shoots', 'शादी, तिलक, जन्मदिन की शूटिंग'),
        'lead' => t('Tell us the day — we’ll get the photographer’s rate for you.',
                    'कार्यक्रम का दिन बता दीजिए — फोटोग्राफर का रेट पता करके बताएँगे।'),
        'cat'  => 'photo',
        'fields' => [
            ['k'=>'event','t'=>'select','l'=>['Which occasion','कौन सा कार्यक्रम'],'req'=>1,
             'o'=>['Wedding|शादी','Tilak / engagement|तिलक / सगाई','Birthday|जन्मदिन','Mundan|मुंडन','Passport / form photo|पासपोर्ट / फॉर्म की फोटो','Other|अन्य']],
            ['k'=>'on_date','t'=>'date','l'=>['Which day','किस दिन'],'req'=>1,'min'=>$today],
            ['k'=>'days','t'=>'number','l'=>['How many days','कितने दिन'],'req'=>0,'ph'=>'1','min'=>1,'max'=>10],
            ['k'=>'items','t'=>'multi','l'=>['What do you need','क्या-क्या चाहिए'],'req'=>1,
             'o'=>['Photos|फोटो','Video|वीडियो','Drone|ड्रोन','Album|एल्बम','Live screen|लाइव स्क्रीन']],
        ],
    ],

    'mistri' => [
        'name' => 'घर की मरम्मत', 'en' => 'Home Services', 'icon' => 'home',
        'tag'  => t('Plumber, electrician, carpenter, mason', 'नल, बिजली, बढ़ई, राजमिस्त्री'),
        'lead' => t('Tell us what’s broken — we’ll send the right person to your door.',
                    'क्या ख़राब है बता दीजिए — कारीगर को आपके घर भेज देंगे।'),
        'cat'  => 'thekedar',
        'fields' => [
            ['k'=>'event','t'=>'select','l'=>['What needs work','किसका काम है'],'req'=>1,
             'o'=>['Tap / water tank|नल / पानी की टंकी','Electrical / wiring|बिजली / वायरिंग','Fan, cooler, fridge|पंखा, कूलर, फ्रिज','Carpenter (wood)|बढ़ई (लकड़ी)','Mason / plaster|राजमिस्त्री / प्लस्तर','Painting|पुताई / पेंट','Welding / iron|वेल्डिंग / लोहा','Boring / submersible|बोरिंग / सबमर्सिबल','Tiles / marble|टाइल / मार्बल','Mobile / TV|मोबाइल / टीवी','Other|अन्य']],
            ['k'=>'on_date','t'=>'date','l'=>['Which day','किस दिन'],'req'=>1,'min'=>$today],
            ['k'=>'on_time','t'=>'select','l'=>['When should they come','कब आएँ'],'req'=>0,
             'o'=>['Morning (8 – 12)|सुबह (8 – 12)','Afternoon (12 – 4)|दोपहर (12 – 4)','Evening (4 – 8)|शाम (4 – 8)','Whenever they are free|जब भी खाली हों']],
            ['k'=>'urgent','t'=>'select','l'=>['How soon','कितनी जल्दी'],'req'=>0,
             'o'=>['In a day or two|आज-कल में हो जाए','This week|इसी हफ़्ते','No hurry|जल्दी नहीं है','Very urgent — today|बहुत ज़रूरी है — आज ही']],
        ],
    ],

    ];
}

function service_get($slug) {
    $s = services();
    return $s[$slug] ?? null;
}
function svc_name($svc) { return is_hi() ? $svc['name'] : $svc['en']; }

/** booking number — BK-DDMM-NN */
function new_booking_no(PDO $pdo) {
    return 'BK-' . date('dm') . '-' . strtoupper(bin2hex(random_bytes(5)));
}

/** booking ke jawaab ko padhne layak line banao */
function booking_lines($svc, $ans, $both = false) {
    $out = [];
    if (!$svc) return $out;
    foreach ($svc['fields'] as $f) {
        $v = $ans[$f['k']] ?? '';
        if (is_array($v)) {
            $v = implode(', ', array_map($both ? fn($x) => str_replace('|', ' / ', $x) : 'opt_label', $v));
        } elseif ($f['t'] === 'select' && $v !== '') {
            $v = $both ? str_replace('|', ' / ', $v) : opt_label($v);
        }
        if ($v === '' || $v === null) continue;
        if ($f['t'] === 'date')  $v = date('d/m/Y', strtotime($v));
        if ($f['t'] === 'time' && preg_match('/^\d\d:\d\d$/', $v)) $v = date('h:i A', strtotime($v));
        $out[] = [f_label($f['l']), $v];
    }
    return $out;
}

function booking_status_list() {
    return ['Naya'      => t('New', 'नया'),
            'Dekh rahe' => t('Checking', 'देख रहे हैं'),
            'Confirm'   => t('Confirmed', 'पक्का हो गया'),
            'Done'      => t('Done', 'हो गया'),
            'Cancel'    => t('Cancelled', 'कैंसिल')];
}
function booking_pill($s) {
    if ($s === 'Cancel')  return ['pill-bad',  t('Cancelled', 'कैंसिल')];
    if ($s === 'Done')    return ['pill-done', t('Done', 'हो गया')];
    if ($s === 'Confirm') return ['pill-done', t('Confirmed', 'पक्का हो गया')];
    if ($s === 'Naya')    return ['pill-new',  t('New', 'नया')];
    return ['pill-run', t('Checking', 'देख रहे हैं')];
}
function booking_steps($status) {
    $flow = [
        ['Naya',      t('Booking received', 'बुकिंग मिल गई'),   t('Our team is looking at it', 'हमारी टीम देख रही है')],
        ['Dekh rahe', t('Checking the rate', 'रेट पता कर रहे हैं'), t('We will call you with the price', 'आपको कॉल करके बताएँगे')],
        ['Confirm',   t('Booking confirmed', 'बुकिंग पक्की'),   t('Date and price fixed', 'तारीख़ और रेट तय')],
        ['Done',      t('Work completed', 'काम पूरा हुआ'),      t('Thank you', 'धन्यवाद')],
    ];
    $at = ['Naya'=>0,'Dekh rahe'=>1,'Confirm'=>2,'Done'=>3][$status] ?? 0;
    $out = [];
    foreach ($flow as $i => list($k, $ti, $su)) {
        $out[] = ['title'=>$ti,'sub'=>$su,'state'=>($i < $at ? 'done' : ($i === $at ? 'now' : 'pend'))];
    }
    return $out;
}
