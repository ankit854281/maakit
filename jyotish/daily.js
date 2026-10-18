/* ============================================================
   Maakit Jyotish V2 — "Aaj ka Card"
   Teen parat (layer):
     1. Check-in  -> aaj ka haal (state). Phone par hi rehta hai.
     2. Vedic map -> kundli + aaj ka gochar se theme (ghatna nahi).
     3. Delivery  -> ek muft upaya (10 min tak, Rs 0) + mantra ka aavaran.
   Niyam: haal (layer 1) hamesha kundli (layer 2) se upar. Pareshani
   ke shabd mile to koi jyotish nahi — sirf madad ka panna.
   Bhasha: hl = Hinglish, hi = हिंदी, en = English.
   ============================================================ */
var Jd = (function () {
  // ---- Pareshani ke shabd (ai_lib.php ke JY_CRISIS jaisi hi list) ----
  var CRISIS = ['suicide', 'kill myself', 'end my life', 'want to die', 'self harm', 'self-harm', 'no reason to live',
    'khudkushi', 'khud kushi', 'aatmhatya', 'atmahatya', 'jeena nahi', 'jina nahi', 'jeene ka mann nahi', 'marna chahta', 'marna chahti',
    'mar jaun', 'mar jaau', 'mar jana chahta', 'mar jana chahti', 'khatam kar du', 'khatam kar dun', 'zindagi khatam', 'jindagi khatam',
    'आत्महत्या', 'ख़ुदकुशी', 'खुदकुशी', 'जीना नहीं', 'मरना चाहता', 'मरना चाहती', 'मर जाऊं', 'मर जाऊँ', 'ज़िंदगी खत्म', 'जिंदगी खत्म', 'ज़िन्दगी ख़त्म'];
  function crisis(t) { t = String(t || '').toLowerCase(); return CRISIS.some(function (w) { return t.indexOf(w.toLowerCase()) >= 0; }); }

  // ---- Layer 1: check-in -> state ----
  // mood 0 bura .. 3 bahut achha; energy 0 kam .. 2 zyada; concern: tension|gussa|akelapan|neend|theek
  function stateOf(c) {
    if (!c) return null;
    if (c.concern === 'neend') return 'tired';
    if (c.concern === 'gussa') return 'restless';
    if (c.concern === 'akelapan') return 'lonely';
    if (c.concern === 'tension') return 'stressed';
    if (c.mood === 0) return 'low';
    if (c.energy === 0) return c.mood === 1 ? 'low' : 'tired';
    return 'steady';
  }
  var STATE_CATS = { low: ['activate', 'gratitude'], stressed: ['breath', 'seva'], restless: ['move', 'focus'], lonely: ['connect'], tired: ['sleep'] };

  // ---- Upaya library: 9 kinds x 3. Sab muft, 10 minute ya kam. ----
  function P(id, cat, hl, hi, en, whl, whi, wen) { return { id: id, cat: cat, t: { hl: hl, hi: hi, en: en }, why: { hl: whl, hi: whi, en: wen } }; }
  var LIB = [
    P('s1', 'sleep', 'Aaj raat sone se 30 minute pehle phone door rakh dein aur roshni dheemi kar dein.', 'आज रात सोने से 30 मिनट पहले फ़ोन दूर रख दीजिए और रोशनी धीमी कर दीजिए।', 'Tonight, put your phone away 30 minutes before sleep and dim the lights.',
      'Sone se pehle screen kam karne se neend jaldi aur gehri aati hai.', 'सोने से पहले स्क्रीन कम करने से नींद जल्दी और गहरी आती है।', 'Less screen time before bed helps you fall asleep sooner and sleep deeper.'),
    P('s2', 'sleep', 'Dopahar mein 10 minute aankh band karke aaraam karein; sona zaroori nahi.', 'दोपहर में 10 मिनट आँख बंद करके आराम कीजिए; सोना ज़रूरी नहीं।', 'Rest with your eyes closed for 10 minutes this afternoon; you do not need to sleep.',
      'Chhota aaraam thakaan aur chidchidapan kam karta hai.', 'छोटा आराम थकान और चिड़चिड़ापन कम करता है।', 'A short rest lowers tiredness and irritability.'),
    P('s3', 'sleep', 'Aaj roz wale samay par hi soyein aur uthein, chahe chhutti ho.', 'आज रोज़ वाले समय पर ही सोइए और उठिए, चाहे छुट्टी हो।', 'Go to bed and wake up at your usual time today, even on a holiday.',
      'Roz ek hi samay par sona sharir ki ghadi ko theek rakhta hai.', 'रोज़ एक ही समय पर सोना शरीर की घड़ी को ठीक रखता है।', 'A fixed sleep time keeps the body clock steady.'),
    P('b1', 'breath', '4 ginti saans andar, 4 roko, 4 bahar, 4 roko. Aise 5 minute.', '4 गिनती साँस अंदर, 4 रोकिए, 4 बाहर, 4 रोकिए। ऐसे 5 मिनट।', 'Breathe in for 4 counts, hold 4, out 4, hold 4. Repeat for 5 minutes.',
      'Dheemi, ginti wali saans dil ki dhadkan aur ghabrahat ko shaant karti hai.', 'धीमी, गिनती वाली साँस दिल की धड़कन और घबराहट को शांत करती है।', 'Slow, counted breathing calms the heartbeat and nervousness.'),
    P('b2', 'breath', 'Saans chhodna lamba karein: 4 ginti andar, 6 ginti bahar, 5 minute.', 'साँस छोड़ना लंबा कीजिए: 4 गिनती अंदर, 6 गिनती बाहर, 5 मिनट।', 'Make the out-breath longer: in for 4, out for 6, for 5 minutes.',
      'Lambi bahar wali saans sharir ko aaraam ki halat mein laati hai.', 'लंबी बाहर वाली साँस शरीर को आराम की हालत में लाती है।', 'A long out-breath switches the body towards rest.'),
    P('b3', 'breath', 'Jo chinta hai use kaagaz par likhein, aur uske neeche ek chhota agla kadam.', 'जो चिंता है उसे काग़ज़ पर लिखिए, और उसके नीचे एक छोटा अगला कदम।', 'Write down what worries you, and under it one small next step.',
      'Chinta ko likhne se mann halka hota hai aur agla kaam saaf dikhta hai.', 'चिंता को लिखने से मन हल्का होता है और अगला काम साफ़ दिखता है।', 'Writing a worry down makes it lighter and the next step clearer.'),
    P('m1', 'move', 'Aaj 10 minute tez chalein, ho sake to khuli hawa mein.', 'आज 10 मिनट तेज़ चलिए, हो सके तो खुली हवा में।', 'Walk briskly for 10 minutes today, outdoors if you can.',
      'Chalne se gussa aur bechaini kam hoti hai aur mood behtar hota hai.', 'चलने से गुस्सा और बेचैनी कम होती है और मूड बेहतर होता है।', 'Walking eases anger and restlessness and lifts mood.'),
    P('m2', 'move', 'Gussa aaye to jawab dene se pehle 3 lambi saans lein aur 10 tak ginein.', 'गुस्सा आए तो जवाब देने से पहले 3 लंबी साँस लीजिए और 10 तक गिनिए।', 'When angry, take 3 long breaths and count to 10 before you reply.',
      'Ek chhota thehrav gusse mein liye galat faisle rokta hai.', 'एक छोटा ठहराव गुस्से में लिए गलत फ़ैसले रोकता है।', 'A short pause stops decisions made in anger.'),
    P('m3', 'move', 'Subah 5 minute halki stretching ya 3 Surya Namaskar karein.', 'सुबह 5 मिनट हल्की स्ट्रेचिंग या 3 सूर्य नमस्कार कीजिए।', 'Do 5 minutes of gentle stretching or 3 Surya Namaskars in the morning.',
      'Halki kasrat sharir mein urja aur mann mein taazgi laati hai.', 'हल्की कसरत शरीर में ऊर्जा और मन में ताज़गी लाती है।', 'Light exercise brings energy to the body and freshness to the mind.'),
    P('f1', 'focus', 'Ek kaam chunein aur 20 minute phone door rakhkar sirf wahi karein.', 'एक काम चुनिए और 20 मिनट फ़ोन दूर रखकर सिर्फ़ वही कीजिए।', 'Pick one task and do only that for 20 minutes, phone away.',
      'Ek samay par ek kaam karne se kaam jaldi aur achha hota hai.', 'एक समय पर एक काम करने से काम जल्दी और अच्छा होता है।', 'Doing one thing at a time gets work done faster and better.'),
    P('f2', 'focus', 'Aaj ke 3 sabse zaroori kaam likhein; pehla kaam dopahar se pehle.', 'आज के 3 सबसे ज़रूरी काम लिखिए; पहला काम दोपहर से पहले।', "Write today's 3 most important tasks; do the first one before noon.",
      'Chhoti list mann ko bikharne se rokti hai.', 'छोटी लिस्ट मन को बिखरने से रोकती है।', 'A short list keeps a scattered mind on track.'),
    P('f3', 'focus', 'Aaj ek ghanta social media band rakhein.', 'आज एक घंटा सोशल मीडिया बंद रखिए।', 'Keep social media off for one hour today.',
      'Kam scroll karne se dhyan aur neend dono sudharte hain.', 'कम स्क्रॉल करने से ध्यान और नींद दोनों सुधरते हैं।', 'Less scrolling improves both focus and sleep.'),
    P('c1', 'connect', 'Aaj kisi apne ko phone karke sirf haal-chaal poochhein.', 'आज किसी अपने को फ़ोन करके सिर्फ़ हाल-चाल पूछिए।', 'Call someone close today just to ask how they are.',
      'Apno se baat karna akelapan kam karne ka sabse seedha raasta hai.', 'अपनों से बात करना अकेलापन कम करने का सबसे सीधा रास्ता है।', 'Talking to people you care about is the most direct way to ease loneliness.'),
    P('c2', 'connect', 'Ghar mein kisi ke saath 10 minute bina phone ke baithkar baat karein.', 'घर में किसी के साथ 10 मिनट बिना फ़ोन के बैठकर बात कीजिए।', 'Sit and talk with someone at home for 10 minutes, without phones.',
      'Aamne-saamne ki baat rishton ko mazboot karti hai.', 'आमने-सामने की बात रिश्तों को मज़बूत करती है।', 'Face-to-face talk strengthens relationships.'),
    P('c3', 'connect', 'Kisi purane dost ko ek achha sandesh bhejiye.', 'किसी पुराने दोस्त को एक अच्छा संदेश भेजिए।', 'Send a kind message to an old friend.',
      'Chhota sa sampark bhi apnapan badhata hai.', 'छोटा सा संपर्क भी अपनापन बढ़ाता है।', 'Even a small contact builds a sense of belonging.'),
    P('g1', 'gratitude', 'Sone se pehle aaj ki 3 achhi baatein likhein, chahe chhoti hon.', 'सोने से पहले आज की 3 अच्छी बातें लिखिए, चाहे छोटी हों।', 'Before sleep, write 3 good things from today, however small.',
      'Aabhaar likhne ki aadat mood ko dheere-dheere behtar karti hai.', 'आभार लिखने की आदत मूड को धीरे-धीरे बेहतर करती है।', 'A gratitude habit slowly improves mood.'),
    P('g2', 'gratitude', 'Aaj kisi ko dil se dhanyavaad kahein, wajah batakar.', 'आज किसी को दिल से धन्यवाद कहिए, वजह बताकर।', 'Thank someone sincerely today and tell them why.',
      'Dhanyavaad kehna dene aur paane wale dono ka mann achha karta hai.', 'धन्यवाद कहना देने और पाने वाले दोनों का मन अच्छा करता है।', 'Thanking someone lifts the mood of both people.'),
    P('g3', 'gratitude', 'Khaane se pehle ek minute rukkar anna ke liye dhanyavaad kahein.', 'खाने से पहले एक मिनट रुककर अन्न के लिए धन्यवाद कहिए।', 'Before a meal, pause for a minute and give thanks for the food.',
      'Ek pal ka thehrav mann ko abhi ke pal mein laata hai.', 'एक पल का ठहराव मन को अभी के पल में लाता है।', 'A brief pause brings the mind into the present.'),
    P('a1', 'activate', 'Sirf ek chhota kaam 10 minute mein poora karein, jaise ek almaari ya ek bill.', 'सिर्फ़ एक छोटा काम 10 मिनट में पूरा कीजिए, जैसे एक अलमारी या एक बिल।', 'Finish just one small job in 10 minutes, like one shelf or one bill.',
      "Chhota kaam poora karne se mann ko 'ho gaya' ki taakat milti hai.", "छोटा काम पूरा करने से मन को 'हो गया' की ताक़त मिलती है।", "Finishing a small task gives the mind a sense of 'done'."),
    P('a2', 'activate', 'Nahakar saaf kapde pehnein aur 10 minute ghar se bahar ho aayein.', 'नहाकर साफ़ कपड़े पहनिए और 10 मिनट घर से बाहर हो आइए।', 'Bathe, put on clean clothes, and step outside for 10 minutes.',
      'Halki shuruaat bhaari mann ko dheere-dheere uthati hai.', 'हल्की शुरुआत भारी मन को धीरे-धीरे उठाती है।', 'Small starts slowly lift a heavy mood.'),
    P('a3', 'activate', 'Jo kaam taal rahe hain, uska sirf pehla 5 minute ka hissa aaj karein.', 'जो काम टाल रहे हैं, उसका सिर्फ़ पहला 5 मिनट का हिस्सा आज कीजिए।', 'Do just the first 5 minutes of a task you have been putting off.',
      'Shuru karna sabse mushkil hai; 5 minute ka niyam use aasaan banata hai.', 'शुरू करना सबसे मुश्किल है; 5 मिनट का नियम उसे आसान बनाता है।', 'Starting is the hardest part; the 5-minute rule makes it easy.'),
    P('l1', 'learn', 'Aaj 15 minute kuch naya seekhein: ek kitaab, ek hunar, ek video.', 'आज 15 मिनट कुछ नया सीखिए: एक किताब, एक हुनर, एक वीडियो।', 'Spend 15 minutes learning something new: a book, a skill, a video.',
      'Seekhne se aatmavishvas aur khushi dono badhte hain.', 'सीखने से आत्मविश्वास और खुशी दोनों बढ़ते हैं।', 'Learning builds both confidence and happiness.'),
    P('l2', 'learn', 'Kisi bachche ya chhote ko aaj kuch sikhayein.', 'किसी बच्चे या छोटे को आज कुछ सिखाइए।', 'Teach a child or a younger person something today.',
      'Sikhana khud ki samajh pakki karta hai aur santosh deta hai.', 'सिखाना ख़ुद की समझ पक्की करता है और संतोष देता है।', 'Teaching deepens your own understanding and brings satisfaction.'),
    P('l3', 'learn', 'Kisi bade-buzurg se 10 minute unke anubhav sunein.', 'किसी बड़े-बुज़ुर्ग से 10 मिनट उनके अनुभव सुनिए।', "Listen to an elder's experience for 10 minutes.",
      'Buzurgon ki seekh aur saath, dono mann ko sahara dete hain.', 'बुज़ुर्गों की सीख और साथ, दोनों मन को सहारा देते हैं।', "An elder's wisdom and company both give support."),
    P('v1', 'seva', 'Aaj kisi buzurg ki 10 minute madad karein.', 'आज किसी बुज़ुर्ग की 10 मिनट मदद कीजिए।', 'Help an elderly person for 10 minutes today.',
      'Doosron ki madad karne se tanaav kam hota hai aur mann santusht rehta hai.', 'दूसरों की मदद करने से तनाव कम होता है और मन संतुष्ट रहता है।', 'Helping others reduces stress and brings contentment.'),
    P('v2', 'seva', 'Pakshiyon ke liye paani ya daana rakhein.', 'पक्षियों के लिए पानी या दाना रखिए।', 'Put out water or grain for birds.',
      'Chhoti seva roz ke tanaav se dhyan hatati hai.', 'छोटी सेवा रोज़ के तनाव से ध्यान हटाती है।', 'A small act of care takes the mind off daily stress.'),
    P('v3', 'seva', 'Ghar ya mohalle ka ek chhota kaam bina kahe kar dein.', 'घर या मोहल्ले का एक छोटा काम बिना कहे कर दीजिए।', 'Do one small job at home or in the neighbourhood without being asked.',
      'Bina kahe madad karna khushi aur apnapan badhata hai.', 'बिना कहे मदद करना खुशी और अपनापन बढ़ाता है।', 'Helping without being asked increases happiness and belonging.')
  ];

  // ---- Graha ka mantra (aavaran). Sab muft. ----
  var MANTRA = { Su: ['Om Suryaya Namah', 'ॐ सूर्याय नमः'], Mo: ['Om Somaya Namah', 'ॐ सोमाय नमः'], Ma: ['Om Mangalaya Namah', 'ॐ मंगलाय नमः'],
    Me: ['Om Budhaya Namah', 'ॐ बुधाय नमः'], Ju: ['Om Gurave Namah', 'ॐ गुरवे नमः'], Ve: ['Om Shukraya Namah', 'ॐ शुक्राय नमः'],
    Sa: ['Om Shanaishcharaya Namah', 'ॐ शनैश्चराय नमः'], Ra: ['Om Rahave Namah', 'ॐ राहवे नमः'], Ke: ['Om Ketave Namah', 'ॐ केतवे नमः'] };
  var GNAME = { Su: ['Surya', 'सूर्य', 'the Sun'], Mo: ['Chandra', 'चंद्र', 'the Moon'], Ma: ['Mangal', 'मंगल', 'Mars'], Me: ['Budh', 'बुध', 'Mercury'],
    Ju: ['Guru', 'गुरु', 'Jupiter'], Ve: ['Shukra', 'शुक्र', 'Venus'], Sa: ['Shani', 'शनि', 'Saturn'], Ra: ['Rahu', 'राहु', 'Rahu'], Ke: ['Ketu', 'केतु', 'Ketu'] };
  var VAAR = [['Ravivar', 'रविवार', 'Sunday', 'Su'], ['Somvar', 'सोमवार', 'Monday', 'Mo'], ['Mangalvar', 'मंगलवार', 'Tuesday', 'Ma'], ['Budhvar', 'बुधवार', 'Wednesday', 'Me'],
    ['Guruvar', 'गुरुवार', 'Thursday', 'Ju'], ['Shukravar', 'शुक्रवार', 'Friday', 'Ve'], ['Shanivar', 'शनिवार', 'Saturday', 'Sa']];
  var LI = { hl: 0, hi: 1, en: 2 };

  // ---- Layer 2: Chandra gochar ghar -> theme (ghatna nahi) ----
  function H(cat, hl, hi, en) { return { cat: cat, t: { hl: hl, hi: hi, en: en } }; }
  var HOUSE = {
    1: H('breath', 'Aaj mann jaldi bhaavuk ho sakta hai. Apna khayal rakhein aur jaldbaazi ke faisle taalein.', 'आज मन जल्दी भावुक हो सकता है। अपना ख़याल रखिए और जल्दबाज़ी के फ़ैसले टालिए।', 'Feelings may run high today. Look after yourself and avoid hasty decisions.'),
    2: H('gratitude', 'Aaj paisa aur boli, dono sambhal kar. Kharch dekhein aur pyaar se bolein.', 'आज पैसा और बोली, दोनों सँभालकर। ख़र्च देखिए और प्यार से बोलिए।', 'Go carefully with money and words today. Watch spending and speak gently.'),
    3: H('activate', 'Himmat aur urja ka din. Ruka hua kaam aage badhaiye, ek zaroori call kijiye.', 'हिम्मत और ऊर्जा का दिन। रुका हुआ काम आगे बढ़ाइए, एक ज़रूरी फ़ोन कीजिए।', 'A day of courage and energy. Push a stuck task forward and make that important call.'),
    4: H('sleep', 'Ghar aur mann ki shaanti par dhyan dein. Aaraam karein, ghar mein bahas se bachein.', 'घर और मन की शांति पर ध्यान दीजिए। आराम कीजिए, घर में बहस से बचिए।', 'Focus on home and inner peace. Rest, and avoid arguments at home.'),
    5: H('focus', 'Mann idhar-udhar ja sakta hai. Bade daav ki jagah padhai ya rachnatmak kaam karein.', 'मन इधर-उधर जा सकता है। बड़े दाँव की जगह पढ़ाई या रचनात्मक काम कीजिए।', 'The mind may wander. Choose study or creative work over big bets.'),
    6: H('activate', 'Rukawatein paar karne ka din. Pending kaam aur sehat ki dinchariya theek karein.', 'रुकावटें पार करने का दिन। बाक़ी काम और सेहत की दिनचर्या ठीक कीजिए।', 'A day to clear obstacles. Finish pending work and fix your health routine.'),
    7: H('connect', 'Rishton aur saajhedari ka din. Milna-julna aur baat-cheet achhi rahegi.', 'रिश्तों और साझेदारी का दिन। मिलना-जुलना और बातचीत अच्छी रहेगी।', 'A day for relationships and partnerships. Meetings and talks suit today.'),
    8: H('sleep', 'Paramparik roop se dheema din (Chandrashtama). Bade kaam taalein aur aaraam karein.', 'परंपरा में धीमा दिन (चंद्राष्टम)। बड़े काम टालिए और आराम कीजिए।', 'Traditionally a slow day (Chandrashtama). Postpone big starts and rest.'),
    9: H('learn', 'Badon aur guruon se seekhne ka din. Ummeed santulit rakhein.', 'बड़ों और गुरुओं से सीखने का दिन। उम्मीदें संतुलित रखिए।', 'A day to learn from elders and teachers. Keep expectations balanced.'),
    10: H('focus', 'Kaam aur pehchaan ka din. Naukri ya vyapar mein pehal karein.', 'काम और पहचान का दिन। नौकरी या व्यापार में पहल कीजिए।', 'A day for work and reputation. Take initiative at your job or business.'),
    11: H('connect', 'Laabh aur dosti ka din. Logon se judein aur madad maangne mein sankoch na karein.', 'लाभ और दोस्ती का दिन। लोगों से जुड़िए और मदद माँगने में संकोच मत कीजिए।', 'A day for gains and friendships. Reach out, and do not hesitate to ask for help.'),
    12: H('sleep', 'Kharch aur thakaan badh sakti hai. Aaraam, prarthana aur yojana ka din.', 'ख़र्च और थकान बढ़ सकती है। आराम, प्रार्थना और योजना का दिन।', 'Spending and tiredness may rise. A day for rest, prayer and planning.')
  };
  var SADE = { hl: 'Shani ka samay chal raha hai: dheeraj aur lagaataar mehnat ka phal milta hai, jaldbaazi ka nahi.', hi: 'शनि का समय चल रहा है: धैर्य और लगातार मेहनत का फल मिलता है, जल्दबाज़ी का नहीं।', en: "Saturn's period is running: patience and steady effort pay, haste does not." };
  var TARA_WEAK = { hl: 'Aaj ka tara-bal kamzor hai, isliye zaroori kaam soch-samajhkar karein.', hi: 'आज का तारा-बल कमज़ोर है, इसलिए ज़रूरी काम सोच-समझकर कीजिए।', en: "Today's star strength (Tarabala) is weak, so do important things carefully." };
  var ORD = ['', '1st', '2nd', '3rd', '4th', '5th', '6th', '7th', '8th', '9th', '10th', '11th', '12th'];

  // K = Jy.kundli(...), TD = Jy.today(...), date = Date (aaj)
  function themes(K, TD, date) {
    var hm = ((TD.moonSign - K.moonSign + 12) % 12) + 1;
    var tn = ((TD.nak.i - K.nak.i + 27) % 27) % 9, taraWeak = [2, 4, 6].indexOf(tn) >= 0;
    var sh = ((TD.satSign - K.moonSign + 12) % 12) + 1, sade = [12, 1, 2, 4, 8].indexOf(sh) >= 0;
    var v = VAAR[date.getDay()];
    var dasha = K.curAD ? K.curAD.lord : (K.curMD ? K.curMD.lord : null);
    // Aavaran ka graha: Shani ka samay ho to Shani, warna din badal-badal kar vaar ka swami aur antardasha ka swami.
    var useVaar = !dasha || (Math.floor(date.getTime() / 86400000) % 2 === 0);
    var graha = sade ? 'Sa' : (useVaar ? v[3] : dasha);
    return { house: hm, theme: HOUSE[hm], taraWeak: taraWeak, sade: sade, graha: graha,
      grahaWhy: sade ? 'sade' : (useVaar ? 'vaar' : 'dasha'), vaar: v, moonSign: TD.moonSign, natalMoon: K.moonSign };
  }

  // ---- Layer 3: ek upaya chuniye ----
  function hash(s) { var h = 2166136261; for (var i = 0; i < s.length; i++) { h ^= s.charCodeAt(i); h = Math.imul(h, 16777619); } return h >>> 0; }
  // history = [{date:'YYYY-MM-DD', id:'s1'}, ...]; seed = device id
  function pick(state, th, dateKey, history, seed) {
    var cats = (state && STATE_CATS[state]) ? STATE_CATS[state].slice() : [th.theme.cat];
    if (!state || state === 'steady') { cats = [th.theme.cat]; if (th.sade) cats.push('seva'); }
    var recent = {};
    (history || []).forEach(function (h) { if (h && h.date && daysBetween(h.date, dateKey) > 0 && daysBetween(h.date, dateKey) <= 3) recent[h.id] = 1; });
    var pool = LIB.filter(function (p) { return cats.indexOf(p.cat) >= 0 && !recent[p.id]; });
    if (!pool.length) pool = LIB.filter(function (p) { return cats.indexOf(p.cat) >= 0; });
    return pool[hash(dateKey + '|' + (seed || '') + '|' + (state || '')) % pool.length];
  }
  function daysBetween(a, b) { return Math.round((Date.parse(b) - Date.parse(a)) / 86400000); }

  // ---- Card ka text ----
  function card(lang, K, TD, state, dateKey, history, seed, signs) {
    var L = LI[lang] === undefined ? 0 : LI[lang], lk = ['hl', 'hi', 'en'][L];
    var th = themes(K, TD, new Date(Date.parse(dateKey + 'T12:00:00')));
    var p = pick(state, th, dateKey, history, seed);
    var m = MANTRA[th.graha], g = GNAME[th.graha][L];
    var mantraLine = [
      "Sankalp: shuru karne se pehle 3 baar '" + m[0] + "' bolein.",
      "संकल्प: शुरू करने से पहले 3 बार '" + m[1] + "' बोलिए।",
      "Sankalp: before you begin, say '" + m[0] + "' 3 times."][L];
    var S = signs || Jy.SIGN_SHORT;
    var why1 = [
      'Aaj Chandra ' + S[th.moonSign] + ' mein hai, jo aapki Chandra rashi ' + S[th.natalMoon] + ' se ' + th.house + 'va ghar hai.',
      'आज चंद्रमा ' + S[th.moonSign] + ' में है, जो आपकी चंद्र राशि ' + S[th.natalMoon] + ' से ' + th.house + 'वाँ घर है।',
      'Today the Moon is in ' + S[th.moonSign] + ', the ' + ORD[th.house] + ' house from your Moon sign ' + S[th.natalMoon] + '.'][L];
    var why2 = th.grahaWhy === 'sade'
      ? ['Mantra Shani ka hai, kyunki Shani ka samay chal raha hai.', 'मंत्र शनि का है, क्योंकि शनि का समय चल रहा है।', 'The mantra is for Saturn, because a Saturn period is running.'][L]
      : th.grahaWhy === 'vaar'
        ? ['Mantra ' + g + ' ka hai, kyunki aaj ' + th.vaar[0] + ' hai.', 'मंत्र ' + g + ' का है, क्योंकि आज ' + th.vaar[1] + ' है।', 'The mantra is for ' + g + ', because today is ' + th.vaar[2] + '.'][L]
        : ['Mantra ' + g + ' ka hai, kyunki aapki ' + g + ' antardasha chal rahi hai.', 'मंत्र ' + g + ' का है, क्योंकि आपकी ' + g + ' अंतर्दशा चल रही है।', 'The mantra is for ' + g + ', because your ' + g + ' antardasha is running.'][L];
    var why3 = [
      state && state !== 'steady' ? 'Upaya aapke aaj ke haal ke hisaab se chuna gaya hai.' : 'Upaya aaj ke gochar ke hisaab se chuna gaya hai.',
      state && state !== 'steady' ? 'उपाय आपके आज के हाल के हिसाब से चुना गया है।' : 'उपाय आज के गोचर के हिसाब से चुना गया है।',
      state && state !== 'steady' ? "The upaya was chosen for how you said you feel today." : "The upaya was chosen from today's transit."][L];
    var extra = [];
    if (th.sade) extra.push(SADE[lk]);
    if (th.taraWeak) extra.push(TARA_WEAK[lk]);
    return {
      id: p.id, cat: p.cat, house: th.house,
      insight: th.theme.t[lk], extra: extra,
      upaya: p.t[lk], mantra: mantraLine,
      why: [why1, why3, p.why[lk], why2],
      // AI chat ke liye chhota saar, hamesha English me
      ctx: 'Theme: ' + th.theme.t.en + (th.sade ? ' ' + SADE.en : '') + ' Upaya: ' + p.t.en + ' Mantra: ' + MANTRA[th.graha][0] + '.'
    };
  }

  // ---- Diya: 7 din me 4 = poora hafta ----
  function week(done, todayKey) {
    var out = [], set = {};
    (done || []).forEach(function (d) { set[d] = 1; });
    for (var i = 6; i >= 0; i--) {
      var d = new Date(Date.parse(todayKey + 'T12:00:00') - i * 86400000).toISOString().slice(0, 10);
      out.push({ date: d, lit: !!set[d] });
    }
    var lit = out.filter(function (x) { return x.lit; }).length;
    return { days: out, lit: lit, full: lit >= 4 };
  }

  return { crisis: crisis, stateOf: stateOf, themes: themes, pick: pick, card: card, week: week, LIB: LIB, HOUSE: HOUSE, CRISIS: CRISIS, MANTRA: MANTRA };
})();
