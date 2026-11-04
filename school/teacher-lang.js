/* =====================================================================
   SNMSIS Teacher App — three languages: English · Hinglish · हिंदी (owner, 5 Nov 2026)
   teacher.js keeps writing its screens in English. This file translates what appears inside #phone and the toast,
   just after it is drawn, from one word list (WORDS) and a few patterns for sentences with numbers/names (PATTERNS).
   - The choice is remembered on the phone (localStorage `ta_lang`): en | hg | hi.
   - Never translated: what a teacher types (inputs/textarea), student and teacher names, server messages it does not
     know. Anything not in the list simply stays in English — nothing breaks.
   - Data, records and permissions are not touched by this file.
   To add a sentence: put the exact English text as the key and [Hinglish, हिंदी] as the value. An optional third
   value replaces the English wording itself (used for the tab names).
   ===================================================================== */
'use strict';
(function () {
  const get = () => { try { return localStorage.getItem('ta_lang') || 'en'; } catch (e) { return 'en'; } };
  const LANGS = [['en', 'English'], ['hg', 'Hinglish'], ['hi', 'हिंदी']];
  const W = {
    // tabs and shell
    'Aaj': ['Aaj', 'आज', 'Today'], 'Haziri': ['Haziri', 'हाज़िरी', 'Attendance'], 'Diary': ['Diary', 'डायरी'], 'Plan': ['Plan', 'योजना'],
    'Notes': ['Notes', 'नोट्स'], 'Result': ['Result', 'परिणाम'], 'Saved': ['Save ho gaya', 'सेव हो गया'], 'Saving…': ['Save ho raha hai…', 'सेव हो रहा है…'],
    'Offline': ['Internet nahi', 'इंटरनेट नहीं'], 'My account': ['Mera account', 'मेरा खाता'], 'Close': ['Band karein', 'बंद करें'], 'Close profile': ['Profile band karein', 'प्रोफ़ाइल बंद करें'],
    'Opening your classes…': ['Aapki classes khul rahi hain…', 'आपकी कक्षाएँ खुल रही हैं…'], 'Opening…': ['Khul raha hai…', 'खुल रहा है…'], 'Try again': ['Dobara koshish karein', 'फिर से कोशिश करें'],
    'Language': ['Bhasha', 'भाषा'],
    'Today': ['Aaj', 'आज'], 'Classroom': ['Classroom', 'कक्षा कार्य'], 'Planner': ['Planner', 'योजना'], 'Results': ['Result', 'परिणाम'],
    // login
    'SNMSIS Teacher': ['SNMSIS Teacher', 'SNMSIS शिक्षक'], 'Namaste!': ['Namaste!', 'नमस्ते!'],
    'Choose your name and enter your PIN. First time? Your PIN is 1234.': ['Apna naam chunein aur PIN daalein. Pehli baar? Aapka PIN 1234 hai.', 'अपना नाम चुनें और PIN डालें। पहली बार? आपका PIN 1234 है।'],
    'Teacher': ['Teacher', 'शिक्षक'], 'Principal': ['Principal', 'प्रधानाचार्य'], 'Open': ['Kholein', 'खोलें'], '— Your name —': ['— Apna naam —', '— अपना नाम —'],
    'Choose your own PIN': ['Apna PIN chunein', 'अपना PIN चुनें'], 'First login: choose a new PIN (4–8 digits, not 1234). Keep it secret.': ['Pehla login: naya PIN chunein (4–8 ank, 1234 nahi). Ise kisi ko na batayein.', 'पहला लॉगिन: नया PIN चुनें (4–8 अंक, 1234 नहीं)। इसे किसी को न बताएँ।'],
    'Current PIN (1234)': ['Abhi ka PIN (1234)', 'मौजूदा PIN (1234)'], 'New PIN': ['Naya PIN', 'नया PIN'], 'Save PIN': ['PIN save karein', 'PIN सेव करें'],
    'Put this app on your home screen': ['Is app ko home screen par lagayein', 'इस ऐप को होम स्क्रीन पर लगाएँ'],
    'In Chrome tap ⋮ → Install app (or Add to Home screen).': ['Chrome mein ⋮ → Install app (ya Add to Home screen) dabayein.', 'Chrome में ⋮ → Install app (या Add to Home screen) दबाएँ।'],
    'In Safari tap Share → Add to Home Screen.': ['Safari mein Share → Add to Home Screen dabayein.', 'Safari में Share → Add to Home Screen दबाएँ।'],
    'Install the Teacher app on this phone': ['Teacher app is phone par install karein', 'शिक्षक ऐप इस फ़ोन पर इंस्टॉल करें'],
    'Powered by Maakit': ['Powered by Maakit', 'Powered by Maakit'], 'Choose your name.': ['Apna naam chunein.', 'अपना नाम चुनें।'], 'Please log in again.': ['Kripya dobara login karein.', 'कृपया फिर से लॉगिन करें।'],
    'This app is for teachers. Please log in with a teacher PIN.': ['Ye app teachers ke liye hai. Teacher PIN se login karein.', 'यह ऐप शिक्षकों के लिए है। शिक्षक PIN से लॉगिन करें।'],
    // welcome / home
    'SNMSIS · TEACHER': ['SNMSIS · TEACHER', 'SNMSIS · शिक्षक'], 'Good morning': ['Suprabhat', 'सुप्रभात'], 'Good afternoon': ['Namaskar', 'नमस्कार'], 'Good evening': ['Shubh sandhya', 'शुभ संध्या'],
    'Start my day →': ['Din shuru karein →', 'दिन शुरू करें →'], 'Skip animation': ['Animation chhodein', 'एनिमेशन छोड़ें'], 'Welcome to SNMSIS Teacher': ['SNMSIS Teacher mein swagat hai', 'SNMSIS शिक्षक में स्वागत है'],
    'Daily teacher motivation': ['Aaj ki prerna', 'आज की प्रेरणा'], '✦ TODAY’S TEACHER SPARK': ['✦ AAJ KI PRERNA', '✦ आज की प्रेरणा'],
    'Every child can learn. Your patience helps them believe it.': ['Har bachcha seekh sakta hai. Aapka dhairya unhe yakeen dilata hai.', 'हर बच्चा सीख सकता है। आपका धैर्य उन्हें विश्वास दिलाता है।'],
    'A small lesson today can become a big dream tomorrow.': ['Aaj ka chhota sabak kal ka bada sapna ban sakta hai.', 'आज का छोटा पाठ कल का बड़ा सपना बन सकता है।'],
    'Teach with care. Progress comes one step at a time.': ['Pyaar se padhaiye. Tarakki ek-ek kadam se aati hai.', 'स्नेह से पढ़ाइए। प्रगति एक-एक कदम से आती है।'],
    'Your kindness is part of what students remember.': ['Bachche aapki daya ko hamesha yaad rakhte hain.', 'बच्चे आपकी दया को हमेशा याद रखते हैं।'],
    'One encouraging word can change a student’s day.': ['Ek hausla badhane wala shabd bachche ka din badal sakta hai.', 'एक हौसला बढ़ाने वाला शब्द बच्चे का दिन बदल सकता है।'],
    'Great teaching makes room for questions and curiosity.': ['Achchhi padhai sawaalon aur jigyasa ko jagah deti hai.', 'अच्छी पढ़ाई सवालों और जिज्ञासा को जगह देती है।'],
    'Each new day is another chance to make learning joyful.': ['Har naya din padhai ko anandmay banane ka mauka hai.', 'हर नया दिन पढ़ाई को आनंदमय बनाने का मौका है।'],
    'Har bachche ke andar ek nayi shuruaat hai.': ['Har bachche ke andar ek nayi shuruaat hai.', 'हर बच्चे के अंदर एक नई शुरुआत है।'],
    'Aaj ka chhota kadam, kal ka bada sapna.': ['Aaj ka chhota kadam, kal ka bada sapna.', 'आज का छोटा कदम, कल का बड़ा सपना।'],
    'Har din ek bachche ka hausla badhaiye.': ['Har din ek bachche ka hausla badhaiye.', 'हर दिन एक बच्चे का हौसला बढ़ाइए।'],
    'Padhai ke saath apnapan bhi sikhaiye.': ['Padhai ke saath apnapan bhi sikhaiye.', 'पढ़ाई के साथ अपनापन भी सिखाइए।'],
    'Aaj kisi bachche ki mehnat ko sarahiye.': ['Aaj kisi bachche ki mehnat ko sarahiye.', 'आज किसी बच्चे की मेहनत को सराहिए।'],
    'Sawal puchhne ki himmat badhaiye.': ['Sawal puchhne ki himmat badhaiye.', 'सवाल पूछने की हिम्मत बढ़ाइए।'],
    'Aaj ki class ko yaadgaar banaiye.': ['Aaj ki class ko yaadgaar banaiye.', 'आज की कक्षा को यादगार बनाइए।'],
    'My bells today': ['Aaj ki meri bells', 'आज के मेरे पीरियड'], 'Mark': ['Lagayein', 'लगाएँ'], 'Not marked yet': ['Abhi nahi lagi', 'अभी नहीं लगी'],
    'No school': ['School band', 'स्कूल बंद'], 'Chapter notes': ['Chapter notes', 'पाठ के नोट्स'], 'Make notes from a book photo': ['Kitaab ki photo se notes banayein', 'किताब की फ़ोटो से नोट्स बनाएँ'],
    'Update them together': ['Sab ek saath bharein', 'सब एक साथ भरें'], 'Diary not filled:': ['Diary nahi bhari:', 'डायरी नहीं भरी:'], 'My bells': ['Meri bells', 'मेरे पीरियड'],
    'Upcoming': ['Aane wali', 'आने वाला'], 'Diary not filled': ['Diary nahi bhari', 'डायरी नहीं भरी'], 'Done': ['Ho gaya', 'हो गया'], 'Fill': ['Bharein', 'भरें'], 'none': ['koi nahi', 'कोई नहीं'],
    'Sunday — no classes.': ['Ravivaar — koi class nahi.', 'रविवार — कोई कक्षा नहीं।'], 'Holiday — no classes.': ['Chhutti — koi class nahi.', 'छुट्टी — कोई कक्षा नहीं।'],
    'No bells for you today.': ['Aaj aapki koi bell nahi.', 'आज आपका कोई पीरियड नहीं।'], 'The timetable is not set yet. Use Diary → "Add a period".': ['Timetable abhi bana nahi. Diary → "Add a period" use karein.', 'समय-सारणी अभी नहीं बनी। डायरी → "पीरियड जोड़ें" इस्तेमाल करें।'],
    'Notices': ['Soochnaayein', 'सूचनाएँ'], 'Coming holidays': ['Aane wali chhuttiyan', 'आने वाली छुट्टियाँ'], 'Holiday': ['Chhutti', 'छुट्टी'], 'My leave': ['Meri chhutti', 'मेरी छुट्टी'], 'Apply': ['Apply karein', 'आवेदन करें'],
    'Sunday — no school today.': ['Aaj Ravivaar hai — school band.', 'आज रविवार है — स्कूल बंद।'], 'Holiday:': ['Chhutti:', 'छुट्टी:'],
    'You are on leave / marked absent today.': ['Aaj aap chhutti par / absent hain.', 'आज आप छुट्टी पर / अनुपस्थित हैं।'],
    'Pending': ['Baaki', 'बाकी'], 'Approved': ['Manzoor', 'स्वीकृत'], 'Rejected': ['Na-manzoor', 'अस्वीकृत'], 'Submitted': ['Bheja gaya', 'भेजा गया'], 'Revise': ['Sudhaarein', 'सुधारें'],
    'Arrangement': ['Arrangement', 'व्यवस्था'], 'Added period': ['Joda gaya period', 'जोड़ा गया पीरियड'],
    // attendance
    'Day': ['Din', 'दिन'], 'Register': ['Register', 'रजिस्टर'], 'Not marked': ['Nahi lagi', 'नहीं लगी'], 'Class': ['Class', 'कक्षा'],
    'Attendance': ['Haziri', 'हाज़िरी'], 'Only the class teacher marks attendance. You are not the class teacher of any class.': ['Haziri sirf class teacher lagate hain. Aap kisi class ke class teacher nahi hain.', 'हाज़िरी केवल कक्षा अध्यापक लगाते हैं। आप किसी कक्षा के कक्षा अध्यापक नहीं हैं।'],
    'Sunday — no attendance.': ['Ravivaar — haziri nahi.', 'रविवार — हाज़िरी नहीं।'], 'School holiday:': ['School ki chhutti:', 'विद्यालय अवकाश:'], 'No attendance is taken on a holiday.': ['Chhutti ke din haziri nahi lagti.', 'छुट्टी के दिन हाज़िरी नहीं लगती।'],
    'This day is not counted as present or absent.': ['Ye din present ya absent mein nahi gina jayega.', 'यह दिन उपस्थित या अनुपस्थित में नहीं गिना जाएगा।'], 'Remove holiday — take attendance': ['Chhutti hatayein — haziri lein', 'छुट्टी हटाएँ — हाज़िरी लें'],
    'Not saved yet': ['Abhi save nahi', 'अभी सेव नहीं'], 'Mark absent': ['Absent lagayein', 'अनुपस्थित करें'], 'Absent roll no. e.g. 3, 7, 12': ['Absent roll no. jaise 3, 7, 12', 'अनुपस्थित रोल नं. जैसे 3, 7, 12'],
    'All': ['Sab', 'सभी'], 'Absent': ['Absent', 'अनुपस्थित'], 'Leave': ['Chhutti', 'अवकाश'], 'Present': ['Present', 'उपस्थित'],
    'Tap a name: Present → Absent → Leave.': ['Naam par tap karein: Present → Absent → Chhutti.', 'नाम पर टैप करें: उपस्थित → अनुपस्थित → अवकाश।'], 'Nobody here.': ['Yahan koi nahi.', 'यहाँ कोई नहीं।'],
    'No students in this class yet.': ['Is class mein abhi koi bachcha nahi.', 'इस कक्षा में अभी कोई छात्र नहीं।'], 'All present': ['Sab present', 'सभी उपस्थित'], 'Mark holiday': ['Chhutti lagayein', 'छुट्टी लगाएँ'],
    'Save attendance': ['Haziri save karein', 'हाज़िरी सेव करें'], 'Update attendance': ['Haziri update karein', 'हाज़िरी अपडेट करें'], 'Tell the parents': ['Parents ko batayein', 'अभिभावकों को बताएँ'], 'Send': ['Bhejein', 'भेजें'],
    'Previous month': ['Pichhla mahina', 'पिछला महीना'], 'Next month': ['Agla mahina', 'अगला महीना'], 'Sunday and saved holidays are automatic. Tap a date to mark attendance.': ['Ravivaar aur chhuttiyan apne aap lagti hain. Haziri ke liye date par tap karein.', 'रविवार और छुट्टियाँ अपने-आप लगती हैं। हाज़िरी के लिए तारीख़ पर टैप करें।'],
    'Sun': ['Ravi', 'रवि'], 'Mon': ['Som', 'सोम'], 'Tue': ['Mangal', 'मंगल'], 'Wed': ['Budh', 'बुध'], 'Thu': ['Guru', 'गुरु'], 'Fri': ['Shukra', 'शुक्र'], 'Sat': ['Shani', 'शनि'],
    'Open': ['Kholein', 'खोलें'], 'Already a holiday': ['Pehle se chhutti', 'पहले से छुट्टी'],
    'Fill them all as Present': ['Sabko Present bharein', 'सबको उपस्थित भरें'], 'then tap the absent children and Save.': ['fir absent bachchon par tap karke Save karein.', 'फिर अनुपस्थित बच्चों पर टैप करके सेव करें।'],
    'Student': ['Bachcha', 'छात्र'], 'P present': ['P present', 'P उपस्थित'], 'A absent': ['A absent', 'A अनुपस्थित'], 'L leave': ['L chhutti', 'L अवकाश'], 'H holiday': ['H chhutti (holiday)', 'H छुट्टी'], '· not marked': ['· nahi lagi', '· नहीं लगी'],
    'Cancel changes': ['Badlav radd karein', 'बदलाव रद्द करें'], 'with no attendance for Class': ['jin par haziri nahi lagi', 'जिन पर हाज़िरी नहीं लगी'],
    'Check from the start of the session': ['Session ki shuruaat se dekhein', 'सत्र की शुरुआत से देखें'], 'Every school day is marked. Well done!': ['Har school din ki haziri lag chuki hai. Shabaash!', 'हर स्कूल दिवस की हाज़िरी लग चुकी है। शाबाश!'],
    'Open them in the register to mark absentees': ['Absent lagane ke liye register mein kholein', 'अनुपस्थित लगाने के लिए रजिस्टर में खोलें'],
    'Reason': ['Wajah', 'कारण'], 'Local holiday': ['Sthaniya chhutti', 'स्थानीय अवकाश'], 'Exam day off': ['Pariksha ki chhutti', 'परीक्षा का अवकाश'], 'Class trip': ['Class trip', 'कक्षा भ्रमण'], 'Rain / weather': ['Baarish / mausam', 'बारिश / मौसम'],
    'e.g. Class picnic, local holiday, exam day off': ['jaise class picnic, sthaniya chhutti, pariksha', 'जैसे कक्षा पिकनिक, स्थानीय अवकाश, परीक्षा'], 'e.g. Local holiday': ['jaise sthaniya chhutti', 'जैसे स्थानीय अवकाश'],
    'Everyone present this day': ['Is din sab present', 'इस दिन सभी उपस्थित'], 'Open this day (with names)': ['Ye din kholein (naamon ke saath)', 'यह दिन खोलें (नामों के साथ)'],
    'Mark as holiday for this class': ['Is class ki chhutti lagayein', 'इस कक्षा की छुट्टी लगाएँ'], 'Remove class holiday': ['Class ki chhutti hatayein', 'कक्षा की छुट्टी हटाएँ'], 'Undo my changes for this day': ['Is din ke badlav hatayein', 'इस दिन के बदलाव हटाएँ'],
    'Attendance is saved for this day.': ['Is din ki haziri save hai.', 'इस दिन की हाज़िरी सेव है।'], 'Not marked yet.': ['Abhi nahi lagi.', 'अभी नहीं लगी।'],
    'Absent 3 school days in a row:': ['Lagatar 3 school din absent:', 'लगातार 3 स्कूल दिवस अनुपस्थित:'], ' · no phone saved': [' · phone number nahi', ' · फ़ोन नंबर नहीं'],
    'Calendar': ['Calendar', 'कैलेंडर'], 'Attendance report': ['Haziri report', 'हाज़िरी रिपोर्ट'], 'This month': ['Is mahine', 'इस महीने'], 'Session': ['Session', 'सत्र'],
    'Below 75%:': ['75% se kam:', '75% से कम:'], 'Photos': ['Photo', 'फ़ोटो'], 'Tap a child: Present → Absent → Leave.': ['Bachche par tap karein: Present → Absent → Chhutti.', 'बच्चे पर टैप करें: उपस्थित → अनुपस्थित → अवकाश।'],
    'Photo mode: tap a child to take or choose the photo. Tap “Photos” again when done.': ['Photo mode: photo lene ke liye bachche par tap karein. Kaam ho jaye to “Photo” dobara dabayein.', 'फ़ोटो मोड: फ़ोटो लेने के लिए बच्चे पर टैप करें। काम हो जाए तो “फ़ोटो” फिर दबाएँ।'],
    'Attendance submitted': ['Haziri submit ho gayi', 'हाज़िरी जमा हो गई'], 'Undo': ['Wapas lein', 'वापस लें'], 'Undone.': ['Wapas le liya.', 'वापस ले लिया।'], 'Previous day': ['Pichhla din', 'पिछला दिन'], 'Next day': ['Agla din', 'अगला दिन'],
    'Select days for a holiday': ['Chhutti ke din chunein', 'छुट्टी के दिन चुनें'], 'Cancel': ['Radd karein', 'रद्द करें'], 'Festival': ['Tyohaar', 'त्योहार'], 'Function in school': ['School mein karyakram', 'विद्यालय में कार्यक्रम'],
    'Tap the days to close for this class, then choose the reason.': ['Jin dino class band rahegi un par tap karein, fir wajah chunein.', 'जिन दिनों कक्षा बंद रहेगी उन पर टैप करें, फिर कारण चुनें।'],
    'Tap a day to take attendance or mark a holiday. Sundays and school holidays are automatic.': ['Haziri ya chhutti ke liye din par tap karein. Ravivaar aur school ki chhuttiyan apne aap lagti hain.', 'हाज़िरी या छुट्टी के लिए दिन पर टैप करें। रविवार और विद्यालय अवकाश अपने-आप लगते हैं।'],
    'Take attendance': ['Haziri lein', 'हाज़िरी लें'], 'Edit attendance': ['Haziri badlein', 'हाज़िरी बदलें'], 'Mark holiday for this class': ['Is class ki chhutti lagayein', 'इस कक्षा की छुट्टी लगाएँ'],
    'The attendance saved for this day will be removed.': ['Is din ki save haziri hat jayegi.', 'इस दिन की सेव हाज़िरी हट जाएगी।'], 'Saving the photo…': ['Photo save ho rahi hai…', 'फ़ोटो सेव हो रही है…'],
    'Some of these days already have attendance. It will be removed. Continue?': ['Kuch dino ki haziri pehle se lagi hai. Wo hat jayegi. Aage badhein?', 'कुछ दिनों की हाज़िरी पहले से लगी है। वह हट जाएगी। आगे बढ़ें?'],
    'Submit attendance': ['Haziri submit karein', 'हाज़िरी जमा करें'],
    // diary
    '✨ Auto fill from syllabus': ['✨ Syllabus se apne aap bharein', '✨ पाठ्यक्रम से अपने-आप भरें'], 'Auto fill from syllabus': ['Syllabus se apne aap bharein', 'पाठ्यक्रम से अपने-आप भरें'],
    'Topic taught *': ['Padhaya gaya topic *', 'पढ़ाया गया विषय *'], 'What did you teach?': ['Aapne kya padhaya?', 'आपने क्या पढ़ाया?'], 'Class work': ['Class work', 'कक्षा कार्य'], 'Homework': ['Homework', 'गृहकार्य'],
    'Homework time (minutes, whole class ≤ 45)': ['Homework ka samay (minute, poori class ≤ 45)', 'गृहकार्य का समय (मिनट, पूरी कक्षा ≤ 45)'], 'Homework for': ['Homework kis din ke liye', 'गृहकार्य किस दिन के लिए'],
    'Next school day': ['Agla school din', 'अगला स्कूल दिवस'], 'Same day': ['Usi din', 'उसी दिन'], 'More · books to bring, revision': ['Aur · laane wali kitaabein, revision', 'और · लाने वाली किताबें, दोहराई'],
    'Books / copies to bring': ['Laane wali kitaabein / copy', 'लाने वाली किताबें / कॉपी'], 'Revision': ['Revision', 'दोहराई'], 'I really taught this and checked the homework.': ['Maine sach mein ye padhaya aur homework check kiya.', 'मैंने सच में यह पढ़ाया और गृहकार्य जाँचा।'],
    'Save diary': ['Diary save karein', 'डायरी सेव करें'], '+ Add a period (substitution / not in timetable)': ['+ Period jodein (substitution / timetable mein nahi)', '+ पीरियड जोड़ें (प्रतिस्थापन / समय-सारणी में नहीं)'],
    'e.g. Q1–Q5 done in class copy': ['jaise Q1–Q5 class copy mein kiye', 'जैसे Q1–Q5 कक्षा कॉपी में किए'], 'e.g. Ex 4.1 Q6–Q10': ['jaise Ex 4.1 Q6–Q10', 'जैसे Ex 4.1 Q6–Q10'], 'min': ['minute', 'मिनट'],
    'Syllabus suggestions': ['Syllabus ke sujhaav', 'पाठ्यक्रम के सुझाव'], 'From your syllabus · tap to use': ['Aapke syllabus se · use karne ke liye tap karein', 'आपके पाठ्यक्रम से · इस्तेमाल के लिए टैप करें'],
    'No matching syllabus topic. You can keep typing.': ['Syllabus mein aisa topic nahi. Aap likhte rahiye.', 'पाठ्यक्रम में ऐसा विषय नहीं। आप लिखते रहिए।'],
    'The Principal has signed this diary. Ask the Principal before changing it.': ['Principal ne ye diary sign kar di hai. Badalne se pehle Principal se poochhein.', 'प्रधानाचार्य ने यह डायरी हस्ताक्षरित कर दी है। बदलने से पहले उनसे पूछें।'],
    'No bells for you on this day.': ['Is din aapki koi bell nahi.', 'इस दिन आपका कोई पीरियड नहीं।'],
    'For a substitution or a period that is not in your timetable.': ['Substitution ya timetable ke bahar ke period ke liye.', 'प्रतिस्थापन या समय-सारणी से बाहर के पीरियड के लिए।'],
    'Class & subject': ['Class aur vishay', 'कक्षा और विषय'], 'Bell': ['Bell', 'पीरियड'], "This is an arrangement (I took someone else's class)": ['Ye arrangement hai (maine kisi aur ki class li)', 'यह व्यवस्था है (मैंने किसी और की कक्षा ली)'], 'Continue': ['Aage badhein', 'आगे बढ़ें'],
    "Send to the class parents' group": ['Class parents group mein bhejein', 'कक्षा के अभिभावक समूह में भेजें'],
    // plan + syllabus
    'Lesson plan': ['Lesson plan', 'पाठ योजना'], 'Syllabus': ['Syllabus', 'पाठ्यक्रम'], 'Week': ['Hafta', 'सप्ताह'], 'From a photo': ['Photo se', 'फ़ोटो से'],
    'Topics — one line = one period · next chapter:': ['Topics — ek line = ek period · agla chapter:', 'विषय — एक पंक्ति = एक पीरियड · अगला पाठ:'], 'Topics — one line = one period': ['Topics — ek line = ek period', 'विषय — एक पंक्ति = एक पीरियड'],
    'Aim / activity / teaching aid / homework': ['Uddeshya / activity / teaching aid / homework', 'उद्देश्य / गतिविधि / शिक्षण सामग्री / गृहकार्य'], 'Send to Principal': ['Principal ko bhejein', 'प्रधानाचार्य को भेजें'],
    'Update and send again': ['Update karke dobara bhejein', 'अपडेट करके फिर भेजें'], 'Approved by the Principal — locked.': ['Principal ne manzoor kiya — locked.', 'प्रधानाचार्य द्वारा स्वीकृत — लॉक है।'],
    'This week has a daily teaching plan. Open the web ERP → Daily Teaching to change it.': ['Is hafte ka daily teaching plan hai. Badalne ke liye web ERP → Daily Teaching kholein.', 'इस सप्ताह की दैनिक शिक्षण योजना है। बदलने के लिए वेब ERP → Daily Teaching खोलें।'],
    'My plans this week': ['Is hafte ke mere plan', 'इस सप्ताह की मेरी योजनाएँ'], 'Status:': ['Sthiti:', 'स्थिति:'],
    'No subjects are given to you yet. The Principal sets them in the timetable.': ['Aapko abhi koi vishay nahi mila. Principal timetable mein dete hain.', 'आपको अभी कोई विषय नहीं मिला। प्रधानाचार्य समय-सारणी में देते हैं।'],
    'Chapters': ['Chapters', 'पाठ'], 'Add': ['Jodein', 'जोड़ें'], 'Add a chapter by name': ['Naam se chapter jodein', 'नाम से पाठ जोड़ें'], 'From the contents page': ['Vishay-soochi page se', 'विषय-सूची पृष्ठ से'],
    'Photo of the book’s Contents / विषय-सूची page — the chapters are read for you. Check them, then save.': ['Kitaab ke Contents / विषय-सूची page ki photo — chapters apne aap padh liye jaate hain. Check karke save karein.', 'किताब के Contents / विषय-सूची पृष्ठ की फ़ोटो — पाठ अपने-आप पढ़ लिए जाते हैं। जाँचकर सेव करें।'],
    'Take / choose photo': ['Photo lein / chunein', 'फ़ोटो लें / चुनें'], 'No chapters yet. Take a photo of the book’s contents page.': ['Abhi koi chapter nahi. Kitaab ke contents page ki photo lein.', 'अभी कोई पाठ नहीं। किताब के विषय-सूची पृष्ठ की फ़ोटो लें।'],
    'Save chapters': ['Chapters save karein', 'पाठ सेव करें'], 'Replace my chapter list (ticks of chapters with the same name are kept)': ['Meri chapter list badlein (same naam wale chapters ke tick bane rahenge)', 'मेरी पाठ सूची बदलें (समान नाम वाले पाठों के टिक बने रहेंगे)'],
    // notes
    'New notes from book photos': ['Kitaab ki photo se naye notes', 'किताब की फ़ोटो से नए नोट्स'], 'Draft': ['Draft', 'ड्राफ़्ट'], 'Shared': ['Share kiya', 'साझा किया'], 'Chapters without notes': ['Bina notes wale chapters', 'बिना नोट्स वाले पाठ'],
    'Make': ['Banayein', 'बनाएँ'], 'Every chapter has notes.': ['Har chapter ke notes hain.', 'हर पाठ के नोट्स हैं।'], 'No notes yet for this class and subject.': ['Is class aur vishay ke abhi notes nahi.', 'इस कक्षा और विषय के अभी नोट्स नहीं।'],
    'Back': ['Peeche', 'वापस'], '← Back': ['← Peeche', '← वापस'], 'All notes': ['Saare notes', 'सभी नोट्स'], 'New notes': ['Naye notes', 'नए नोट्स'], 'Edit notes': ['Notes badlein', 'नोट्स बदलें'], 'Chapter': ['Chapter', 'पाठ'], 'Chapter name': ['Chapter ka naam', 'पाठ का नाम'],
    'Take photos of the chapter pages (up to 4)': ['Chapter ke pages ki photo lein (4 tak)', 'पाठ के पृष्ठों की फ़ोटो लें (4 तक)'], 'Add more pages': ['Aur pages jodein', 'और पृष्ठ जोड़ें'], 'Clear photos': ['Photo hatayein', 'फ़ोटो हटाएँ'],
    'Summary / notes': ['Saar / notes', 'सारांश / नोट्स'], 'Key points — one per line': ['Mukhya baatein — har line mein ek', 'मुख्य बातें — हर पंक्ति में एक'], 'Words — one per line: word = meaning': ['Shabd — har line mein ek: shabd = arth', 'शब्द — हर पंक्ति में एक: शब्द = अर्थ'],
    'Questions & answers — “Q:” line, then “A:” line': ['Prashn-uttar — “Q:” line, fir “A:” line', 'प्रश्न-उत्तर — “Q:” पंक्ति, फिर “A:” पंक्ति'], 'Share with students and parents (their app → Homework page)': ['Bachchon aur parents ke saath share karein (unke app → Homework page)', 'छात्रों और अभिभावकों के साथ साझा करें (उनका ऐप → गृहकार्य पृष्ठ)'],
    'Save notes': ['Notes save karein', 'नोट्स सेव करें'], 'Key points': ['Mukhya baatein', 'मुख्य बातें'], 'Words & meanings': ['Shabd aur arth', 'शब्द और अर्थ'], 'Questions & answers': ['Prashn aur uttar', 'प्रश्न और उत्तर'],
    'WhatsApp': ['WhatsApp', 'WhatsApp'], 'Print': ['Print', 'प्रिंट'], 'Edit': ['Badlein', 'बदलें'], 'Delete': ['Hatayein', 'हटाएँ'],
    'Made by AI from the photos — read it once and correct anything before sharing.': ['Photo se AI ne banaya — share karne se pehle ek baar padhkar sudhaar lein.', 'फ़ोटो से AI ने बनाया — साझा करने से पहले एक बार पढ़कर सुधार लें।'],
    'The phone read the text of the pages — tidy it into notes before sharing.': ['Phone ne pages ka text padha — share se pehle notes ki tarah saja lein.', 'फ़ोन ने पृष्ठों का पाठ पढ़ा — साझा करने से पहले नोट्स के रूप में सजा लें।'],
    'Students and parents can see this in their app (Homework page).': ['Bachche aur parents ise apne app mein dekh sakte hain (Homework page).', 'छात्र और अभिभावक इसे अपने ऐप में देख सकते हैं (गृहकार्य पृष्ठ)।'], 'Draft — not shared yet.': ['Draft — abhi share nahi.', 'ड्राफ़्ट — अभी साझा नहीं।'],
    // result
    'Exam': ['Pariksha', 'परीक्षा'], 'Marks entry': ['Ank bharein', 'अंक प्रविष्टि'], 'Final (T1+T2)': ['Final (T1+T2)', 'अंतिम (T1+T2)'], 'Subject:': ['Vishay:', 'विषय:'], 'Subject': ['Vishay', 'विषय'],
    'Not saved yet.': ['Abhi save nahi.', 'अभी सेव नहीं।'], 'Write AB for absent. Total and grade come by themselves. What you type stays on this phone until you save.': ['Absent ke liye AB likhein. Total aur grade apne aap aate hain. Save karne tak likha hua is phone mein surakshit rehta hai.', 'अनुपस्थित के लिए AB लिखें। कुल और ग्रेड अपने-आप आते हैं। सेव करने तक लिखा हुआ इस फ़ोन में सुरक्षित रहता है।'],
    'Pre-Test': ['Pre-Test', 'प्री-टेस्ट'], 'Notebook': ['Notebook', 'कॉपी'], 'SEA': ['SEA', 'SEA'], 'Term': ['Term', 'सत्र परीक्षा'],
    'Printed report cards (web ERP)': ['Print report cards (web ERP)', 'मुद्रित रिपोर्ट कार्ड (वेब ERP)'], 'Class average': ['Class ka ausat', 'कक्षा औसत'], 'Passed (33%+)': ['Paas (33%+)', 'उत्तीर्ण (33%+)'],
    'Total': ['Kul', 'कुल'], 'Grade': ['Grade', 'ग्रेड'], 'Print result sheet': ['Result sheet print karein', 'परिणाम पत्र प्रिंट करें'], 'Report cards': ['Report cards', 'रिपोर्ट कार्ड'],
    'Attendance on the report card': ['Report card par haziri', 'रिपोर्ट कार्ड पर हाज़िरी'], 'Counted from the attendance register (holidays and Sundays left out). Remarks and co-scholastic grades already entered are kept.': ['Haziri register se gini jaati hai (chhuttiyan aur Ravivaar chhodkar). Pehle bhare remarks aur co-scholastic grade bane rehte hain.', 'हाज़िरी रजिस्टर से गिनी जाती है (छुट्टियाँ और रविवार छोड़कर)। पहले भरे गए रिमार्क और सह-शैक्षिक ग्रेड बने रहते हैं।'],
    'From': ['Se', 'से'], 'To': ['Tak', 'तक'], 'Fill attendance for every child': ['Har bachche ki haziri bharein', 'हर बच्चे की हाज़िरी भरें'], 'Choose Term-2 to see the final result.': ['Final result ke liye Term-2 chunein.', 'अंतिम परिणाम के लिए Term-2 चुनें।'],
    'No students in this class.': ['Is class mein koi bachcha nahi.', 'इस कक्षा में कोई छात्र नहीं।'], 'No classes are given to you yet.': ['Aapko abhi koi class nahi mili.', 'आपको अभी कोई कक्षा नहीं मिली।'],
    'The session is not set in the ERP settings yet.': ['ERP settings mein session abhi set nahi.', 'ERP सेटिंग में सत्र अभी तय नहीं।'], 'No subjects are set for this class.': ['Is class ke vishay set nahi.', 'इस कक्षा के विषय तय नहीं।'],
    // account
    'MY TEACHER PROFILE': ['MERI TEACHER PROFILE', 'मेरी शिक्षक प्रोफ़ाइल'], 'Class teacher': ['Class teacher', 'कक्षा अध्यापक'], 'Subjects': ['Vishay', 'विषय'], 'Weekly periods': ['Hafte ke period', 'साप्ताहिक पीरियड'],
    'My class': ['Meri class', 'मेरी कक्षा'], 'My classes': ['Meri classes', 'मेरी कक्षाएँ'], 'My subjects': ['Mere vishay', 'मेरे विषय'], 'No class-teacher assignment': ['Class teacher nahi', 'कक्षा अध्यापक नहीं'], 'No subject assignment yet': ['Abhi koi vishay nahi', 'अभी कोई विषय नहीं'],
    'School-record contact': ['School record ka number', 'विद्यालय रिकॉर्ड का नंबर'], 'Change photo': ['Photo badlein', 'फ़ोटो बदलें'], 'Remove photo': ['Photo hatayein', 'फ़ोटो हटाएँ'], 'Replay welcome': ['Welcome dobara dekhein', 'स्वागत फिर देखें'],
    'Your profile photo stays on this phone. Official staff information is managed by the school office.': ['Aapki photo sirf is phone mein rehti hai. Staff ki official jaankari school office rakhta hai.', 'आपकी फ़ोटो केवल इस फ़ोन में रहती है। स्टाफ़ की आधिकारिक जानकारी विद्यालय कार्यालय रखता है।'],
    'Apply for leave': ['Chhutti ke liye apply karein', 'अवकाश के लिए आवेदन'], 'e.g. Family function': ['jaise parivaar ka function', 'जैसे पारिवारिक कार्यक्रम'], 'School holidays ahead': ['Aane wali school chhuttiyan', 'आने वाले विद्यालय अवकाश'], 'None in the calendar.': ['Calendar mein koi nahi.', 'कैलेंडर में कोई नहीं।'],
    'The Basic Shiksha Parishad / BSA Bhadohi holiday list is added to the school calendar by itself. The Principal can remove any day in the web ERP → Calendar.': ['Basic Shiksha Parishad / BSA Bhadohi ki chhutti list school calendar mein apne aap judti hai. Principal web ERP → Calendar mein koi bhi din hata sakte hain.', 'बेसिक शिक्षा परिषद / BSA भदोही की अवकाश सूची विद्यालय कैलेंडर में अपने-आप जुड़ती है। प्रधानाचार्य वेब ERP → कैलेंडर में कोई भी दिन हटा सकते हैं।'],
    'Change my PIN': ['Mera PIN badlein', 'मेरा PIN बदलें'], 'Change PIN': ['PIN badlein', 'PIN बदलें'], 'Old': ['Purana', 'पुराना'], 'New': ['Naya', 'नया'], 'Log out': ['Log out', 'लॉग आउट'],
    'SNMSIS Teacher · Powered by Maakit': ['SNMSIS Teacher · Powered by Maakit', 'SNMSIS शिक्षक · Powered by Maakit'], 'Teacher · Session': ['Teacher · Session', 'शिक्षक · सत्र'],
    'You can mark attendance and write the diary. Everything is sent when the internet is back.': ['Aap haziri aur diary bhar sakte hain. Internet aate hi sab chala jayega.', 'आप हाज़िरी और डायरी भर सकते हैं। इंटरनेट आते ही सब भेज दिया जाएगा।'],
    // toasts and questions
    'Attendance saved.': ['Haziri save ho gayi.', 'हाज़िरी सेव हो गई।'], 'Diary saved.': ['Diary save ho gayi.', 'डायरी सेव हो गई।'], 'Chapters saved.': ['Chapters save ho gaye.', 'पाठ सेव हो गए।'], 'Notes saved.': ['Notes save ho gaye.', 'नोट्स सेव हो गए।'],
    'Deleted.': ['Hata diya.', 'हटा दिया।'], 'Sent to the Principal.': ['Principal ko bhej diya.', 'प्रधानाचार्य को भेज दिया।'], 'Leave request sent to the Principal.': ['Chhutti ki arzi Principal ko bhej di.', 'अवकाश आवेदन प्रधानाचार्य को भेज दिया।'],
    'PIN changed.': ['PIN badal gaya.', 'PIN बदल गया।'], 'PIN saved.': ['PIN save ho gaya.', 'PIN सेव हो गया।'], 'Check the dates.': ['Dates check karein.', 'तारीख़ें जाँचें।'], 'Write a reason.': ['Wajah likhein.', 'कारण लिखें।'],
    'Write the topic taught.': ['Padhaya gaya topic likhein.', 'पढ़ाया गया विषय लिखें।'], 'Tick “I really taught this” first.': ['Pehle “Maine sach mein padhaya” par tick karein.', 'पहले “मैंने सच में पढ़ाया” पर टिक करें।'],
    'Write how many minutes the homework takes (1 to 45).': ['Homework kitne minute ka hai likhein (1 se 45).', 'गृहकार्य कितने मिनट का है लिखें (1 से 45)।'], 'Write the chapter name.': ['Chapter ka naam likhein.', 'पाठ का नाम लिखें।'],
    'Write at least one topic.': ['Kam se kam ek topic likhein.', 'कम से कम एक विषय लिखें।'], 'This plan is locked.': ['Ye plan locked hai.', 'यह योजना लॉक है।'], 'No chapter to save.': ['Save karne ko koi chapter nahi.', 'सेव करने को कोई पाठ नहीं।'],
    'Some marks are more than the maximum. Check the red boxes.': ['Kuch ank poorne ank se zyada hain. Laal box check karein.', 'कुछ अंक अधिकतम से ज़्यादा हैं। लाल बॉक्स जाँचें।'],
    'New PIN must be 4 to 8 digits.': ['Naya PIN 4 se 8 ank ka ho.', 'नया PIN 4 से 8 अंकों का हो।'], 'Choose a PIN other than 1234.': ['1234 ke alawa koi PIN chunein.', '1234 के अलावा कोई PIN चुनें।'],
    'Type the roll numbers of the absent children, e.g. 3, 7, 12.': ['Absent bachchon ke roll number likhein, jaise 3, 7, 12.', 'अनुपस्थित बच्चों के रोल नंबर लिखें, जैसे 3, 7, 12।'],
    'Filled as Present. Now tap the absent children, then Save.': ['Present bhar diya. Ab absent bachchon par tap karke Save karein.', 'उपस्थित भर दिया। अब अनुपस्थित बच्चों पर टैप करके सेव करें।'],
    'Draft filled. Review the work and enter homework minutes before saving.': ['Draft bhar gaya. Save se pehle check karein aur homework ke minute likhein.', 'ड्राफ़्ट भर गया। सेव से पहले जाँचें और गृहकार्य के मिनट लिखें।'],
    'Your writing is already filled. Use the syllabus suggestions to edit.': ['Aapka likha pehle se bhara hai. Badalne ke liye syllabus ke sujhaav use karein.', 'आपका लिखा पहले से भरा है। बदलने के लिए पाठ्यक्रम के सुझाव इस्तेमाल करें।'],
    'Add your syllabus chapters first, or type the topic.': ['Pehle syllabus ke chapters jodein, ya topic likhein.', 'पहले पाठ्यक्रम के पाठ जोड़ें, या विषय लिखें।'],
    'Add the chapters first: Plan → Syllabus (a photo of the contents page works).': ['Pehle chapters jodein: Plan → Syllabus (contents page ki photo bhi chalegi).', 'पहले पाठ जोड़ें: योजना → पाठ्यक्रम (विषय-सूची की फ़ोटो भी चलेगी)।'],
    'Counting school days…': ['School din gine ja rahe hain…', 'स्कूल दिवस गिने जा रहे हैं…'], 'Attendance filled on the report cards.': ['Report card par haziri bhar di.', 'रिपोर्ट कार्ड पर हाज़िरी भर दी।'],
    'No attendance is saved between these dates.': ['In dates ke beech koi haziri save nahi.', 'इन तारीख़ों के बीच कोई हाज़िरी सेव नहीं।'], 'Choose the bell.': ['Bell chunein.', 'पीरियड चुनें।'],
    'No subjects are given to you yet.': ['Aapko abhi koi vishay nahi mila.', 'आपको अभी कोई विषय नहीं मिला।'], 'Delete these notes?': ['Ye notes hata dein?', 'ये नोट्स हटा दें?'],
    'You have changes that are not saved. Leave them?': ['Kuch badlav save nahi hue. Chhod dein?', 'कुछ बदलाव सेव नहीं हुए। छोड़ दें?'], 'Leave attendance changes without saving?': ['Haziri ke badlav bina save kiye chhod dein?', 'हाज़िरी के बदलाव बिना सेव किए छोड़ दें?'],
    'Replace these topics with a syllabus draft?': ['In topics ki jagah syllabus ka draft daal dein?', 'इन विषयों की जगह पाठ्यक्रम का ड्राफ़्ट डाल दें?'],
    'No internet. Please check the connection and try again.': ['Internet nahi hai. Connection check karke dobara koshish karein.', 'इंटरनेट नहीं है। कनेक्शन जाँचकर फिर कोशिश करें।'],
    'Server problem. Please try again.': ['Server mein dikkat. Dobara koshish karein.', 'सर्वर में समस्या। फिर कोशिश करें।'],
    'Profile photo saved on this device.': ['Photo is phone mein save ho gayi.', 'फ़ोटो इस फ़ोन में सेव हो गई।'], 'Choose an image below 8 MB.': ['8 MB se chhoti photo chunein.', '8 MB से छोटी फ़ोटो चुनें।'],
    'Draft ready. Read it once, correct it, then Save.': ['Draft taiyaar. Ek baar padhkar sudhaarein, fir Save karein.', 'ड्राफ़्ट तैयार। एक बार पढ़कर सुधारें, फिर सेव करें।'], 'Read from the photo. Check it, then send.': ['Photo se padh liya. Check karke bhejein.', 'फ़ोटो से पढ़ लिया। जाँचकर भेजें।'],
    'Nothing readable was found. Try a clearer photo.': ['Kuch padha nahi gaya. Saaf photo lein.', 'कुछ पढ़ा नहीं गया। साफ़ फ़ोटो लें।'], 'Nothing readable was found. Try a clearer, straighter photo in good light.': ['Kuch padha nahi gaya. Achchhi roshni mein seedhi, saaf photo lein.', 'कुछ पढ़ा नहीं गया। अच्छी रोशनी में सीधी, साफ़ फ़ोटो लें।'],
    'Nothing left to plan — all chapters are done.': ['Plan karne ko kuch nahi bacha — sab chapters ho gaye.', 'योजना को कुछ नहीं बचा — सभी पाठ हो गए।'], 'No teaching periods this week. Sundays and saved holidays are skipped.': ['Is hafte koi period nahi. Ravivaar aur chhuttiyan chhodi gayi.', 'इस सप्ताह कोई पीरियड नहीं। रविवार और छुट्टियाँ छोड़ी गईं।'],
    'No school day found. Check the holiday calendar.': ['Koi school din nahi mila. Chhutti calendar check karein.', 'कोई स्कूल दिवस नहीं मिला। अवकाश कैलेंडर जाँचें।']
  };
  // Sentences with numbers, dates or names. Each: [regex, (m) => Hinglish, (m) => Hindi]
  const P = [
    [/^(Good morning|Good afternoon|Good evening), (.+)$/, m => (W[m[1]][0]) + ', ' + m[2], m => (W[m[1]][1]) + ', ' + m[2]],
    [/^Welcome, (.+)$/, m => 'Swagat hai, ' + m[1], m => 'स्वागत है, ' + m[1]],
    [/^(\d+) diary filled$/, m => m[1] + ' diary bhari', m => m[1] + ' डायरी भरी'],
    [/^Class (.+) attendance$/, m => 'Class ' + m[1] + ' haziri', m => 'कक्षा ' + m[1] + ' हाज़िरी'],
    [/^Saved (.+)$/, m => 'Save hua ' + m[1], m => 'सेव हुआ ' + m[1]],
    [/^(\d+) of (\d+) filled$/, m => m[2] + ' mein se ' + m[1] + ' bhari', m => m[2] + ' में से ' + m[1] + ' भरी'],
    [/^(\d+) school day\(s\) without attendance$/, m => m[1] + ' school din bina haziri ke', m => m[1] + ' स्कूल दिवस बिना हाज़िरी के'],
    [/^in the last 30 days \((.+)\)\.$/, m => 'pichhle 30 din mein (' + m[1] + ').', m => 'पिछले 30 दिनों में (' + m[1] + ')।'],
    [/^Topic: (.+)$/, m => 'Topic: ' + m[1], m => 'विषय: ' + m[1]],
    [/^Arrangement for (.+?) · (.*)$/, m => 'Arrangement (' + m[1] + ' ki jagah) · ' + tr(m[2]), m => 'व्यवस्था (' + m[1] + ' के स्थान पर) · ' + tr(m[2])],
    [/^Arrangement for (.+)$/, m => 'Arrangement (' + m[1] + ' ki jagah)', m => 'व्यवस्था (' + m[1] + ' के स्थान पर)'],
    [/^Half day today — you leave before the (.+) bell\.$/, m => 'Aaj half day — aap ' + m[1] + ' bell se pehle jayenge.', m => 'आज आधा दिन — आप ' + m[1] + ' पीरियड से पहले जाएँगे।'],
    [/^(\d+) absent$/, m => m[1] + ' absent', m => m[1] + ' अनुपस्थित'],
    [/^(\d+) P$/, m => m[1] + ' P', m => m[1] + ' उप.'], [/^(\d+) A$/, m => m[1] + ' A', m => m[1] + ' अनु.'], [/^(\d+) L$/, m => m[1] + ' L', m => m[1] + ' अव.'],
    [/^(.+): (Present|Absent|Leave)$/, m => m[1] + ': ' + W[m[2]][0], m => m[1] + ': ' + W[m[2]][1]],
    [/^(.+) — please call the parents\.$/, m => m[1] + ' — parents ko call karein.', m => m[1] + ' — अभिभावकों को फ़ोन करें।'],
    [/^Marked by (.*)\. This day is not counted as present or absent\.$/, m => m[1] + ' ne lagayi. Ye din present ya absent mein nahi gina jayega.', m => m[1] + ' ने लगाई। यह दिन उपस्थित या अनुपस्थित में नहीं गिना जाएगा।'],
    [/^Class (.+) holiday:$/, m => 'Class ' + m[1] + ' ki chhutti:', m => 'कक्षा ' + m[1] + ' की छुट्टी:'],
    [/^(\d+) day\(s\) not marked$/, m => m[1] + ' din ki haziri nahi lagi', m => m[1] + ' दिन की हाज़िरी नहीं लगी'],
    [/^in (.+)\.$/, m => m[1] + ' mein.', m => m[1] + ' में।'],
    [/^(\d+) school day\(s\) marked\. Tap a box to change P → A → L\. Tap a date for the whole day \(all present \/ holiday\)\.$/, m => m[1] + ' school din ki haziri lagi. Box par tap karke P → A → L badlein. Poore din ke liye date par tap karein.', m => m[1] + ' स्कूल दिवस की हाज़िरी लगी। बॉक्स पर टैप करके P → A → L बदलें। पूरे दिन के लिए तारीख़ पर टैप करें।'],
    [/^Save (\d+) changed day\(s\)$/, m => m[1] + ' badle din save karein', m => m[1] + ' बदले दिन सेव करें'],
    [/^School days from$/, () => 'School din, is taareekh se:', () => 'इस तारीख़ से स्कूल दिवस:'],
    [/^with no attendance for Class (.+)\. Sundays and holidays are left out\.$/, m => '— Class ' + m[1] + ' ki haziri nahi lagi. Ravivaar aur chhuttiyan chhodi gayi hain.', m => '— कक्षा ' + m[1] + ' की हाज़िरी नहीं लगी। रविवार और छुट्टियाँ छोड़ी गई हैं।'],
    [/^Select all (\d+) day\(s\)$/, m => 'Saare ' + m[1] + ' din chunein', m => 'सभी ' + m[1] + ' दिन चुनें'],
    [/^Mark (\d+) day\(s\) — everyone present$/, m => m[1] + ' din — sab present', m => m[1] + ' दिन — सभी उपस्थित'],
    [/^Mark (\d+) day\(s\) as holiday$/, m => m[1] + ' din ki chhutti lagayein', m => m[1] + ' दिन की छुट्टी लगाएँ'],
    [/^(\d+) day\(s\) as holiday · Class (.+)$/, m => m[1] + ' din chhutti · Class ' + m[2], m => m[1] + ' दिन छुट्टी · कक्षा ' + m[2]],
    [/^Holiday for Class (.+)$/, m => 'Class ' + m[1] + ' ki chhutti', m => 'कक्षा ' + m[1] + ' की छुट्टी'],
    [/^(.+) — only this class\. The day will not count as present or absent\.$/, m => m[1] + ' — sirf is class ke liye. Ye din present ya absent mein nahi gina jayega.', m => m[1] + ' — केवल इस कक्षा के लिए। यह दिन उपस्थित या अनुपस्थित में नहीं गिना जाएगा।'],
    [/^The attendance saved for this day will be removed\.$/, () => 'Is din ki save haziri hat jayegi.', () => 'इस दिन की सेव हाज़िरी हट जाएगी।'],
    [/^(\d+)(st|nd|rd|th) bell$/, m => m[1] + m[2] + ' bell', m => m[1] + 'वाँ पीरियड'],
    [/^Next chapter: (.+)$/, m => 'Agla chapter: ' + m[1], m => 'अगला पाठ: ' + m[1]],
    [/^Use: (.+)$/, m => 'Ye lein: ' + m[1], m => 'यह लें: ' + m[1]],
    [/^Chapter “(.+)” finished today \(ticks it in the syllabus\)$/, m => 'Chapter “' + m[1] + '” aaj poora hua (syllabus mein tick ho jayega)', m => 'पाठ “' + m[1] + '” आज पूरा हुआ (पाठ्यक्रम में टिक हो जाएगा)'],
    [/^Class (.+) homework today$/, m => 'Class ' + m[1] + ' ka aaj ka homework', m => 'कक्षा ' + m[1] + ' का आज का गृहकार्य'],
    [/^(\d+) subject\(s\)$/, m => m[1] + ' vishay', m => m[1] + ' विषय'],
    [/^Add a period · (.+)$/, m => 'Period jodein · ' + m[1], m => 'पीरियड जोड़ें · ' + m[1]],
    [/^(.+) — already used$/, m => m[1] + ' — pehle se bhara', m => m[1] + ' — पहले से भरा'],
    [/^Week of (.+)$/, m => m[1] + ' wala hafta', m => m[1] + ' वाला सप्ताह'],
    [/^(\d+)\/(\d+) done$/, m => m[1] + '/' + m[2] + ' poore', m => m[1] + '/' + m[2] + ' पूरे'],
    [/^Planned for (.+?)( · (\d+) topics)?$/, m => m[1] + ' ke liye plan' + (m[3] ? ' · ' + m[3] + ' topics' : ''), m => m[1] + ' के लिए नियोजित' + (m[3] ? ' · ' + m[3] + ' विषय' : '')],
    [/^Finished (.+?)( · (\d+) topics)?$/, m => m[1] + ' ko poora' + (m[3] ? ' · ' + m[3] + ' topics' : ''), m => m[1] + ' को पूरा' + (m[3] ? ' · ' + m[3] + ' विषय' : '')],
    [/^(\d+) topic\(s\)(.*)$/, m => m[1] + ' topic' + m[2], m => m[1] + ' विषय' + m[2]],
    [/^(\d+) chapter\(s\)$/, m => m[1] + ' chapter', m => m[1] + ' पाठ'],
    [/^(\d+) points · (\d+) Q&A · (.+)$/, m => m[1] + ' baatein · ' + m[2] + ' prashn · ' + m[3], m => m[1] + ' बातें · ' + m[2] + ' प्रश्न · ' + m[3]],
    [/^Chapters found \((read by AI|read by the phone — check carefully)\) — one per line$/, m => 'Mile chapters (' + (m[1] === 'read by AI' ? 'AI ne padhe' : 'phone ne padhe — dhyaan se check karein') + ') — har line mein ek', m => 'मिले पाठ (' + (m[1] === 'read by AI' ? 'AI ने पढ़े' : 'फ़ोन ने पढ़े — ध्यान से जाँचें') + ') — हर पंक्ति में एक'],
    [/^Make notes from (\d+) photo\(s\)$/, m => m[1] + ' photo se notes banayein', m => m[1] + ' फ़ोटो से नोट्स बनाएँ'],
    [/^(\d+) of (\d+) students filled$/, m => m[2] + ' mein se ' + m[1] + ' bachchon ke ank bhare', m => m[2] + ' में से ' + m[1] + ' छात्रों के अंक भरे'],
    [/^Save (.+) marks$/, m => m[1] + ' ke ank save karein', m => m[1] + ' के अंक सेव करें'],
    [/^Marks entered for (\d+) of (\d+) subjects\. Waiting: (.+)\.$/, m => m[2] + ' mein se ' + m[1] + ' vishay ke ank bhare. Baaki: ' + m[3] + '.', m => m[2] + ' में से ' + m[1] + ' विषयों के अंक भरे। बाकी: ' + m[3] + '।'],
    [/^(\d+) student\(s\) have marks missing in some subject\. Their %, grade and rank show only when every subject is filled\.$/, m => m[1] + ' bachchon ke kisi vishay ke ank baaki hain. Unka %, grade aur rank tabhi dikhega jab har vishay bhara ho.', m => m[1] + ' छात्रों के किसी विषय के अंक बाकी हैं। उनका %, ग्रेड और रैंक तभी दिखेगा जब हर विषय भरा हो।'],
    [/^(\d+) complete result\(s\)$/, m => m[1] + ' poore result', m => m[1] + ' पूर्ण परिणाम'],
    [/^Topper: (.+)$/, m => 'Topper: ' + m[1], m => 'प्रथम: ' + m[1]],
    [/^You do not teach a subject in Class (.+)\. Open “Result” to see the class result\.$/, m => 'Aap Class ' + m[1] + ' mein koi vishay nahi padhate. Class ka result “Result” mein dekhein.', m => 'आप कक्षा ' + m[1] + ' में कोई विषय नहीं पढ़ाते। कक्षा का परिणाम “परिणाम” में देखें।'],
    [/^Teacher · Session (.*)$/, m => 'Teacher · Session ' + m[1], m => 'शिक्षक · सत्र ' + m[1]],
    [/^Principal · Session (.*)$/, m => 'Principal · Session ' + m[1], m => 'प्रधानाचार्य · सत्र ' + m[1]],
    [/^Class teacher of (.+)$/, m => 'Class teacher: ' + m[1], m => 'कक्षा अध्यापक: ' + m[1]],
    [/^(.+) · Class (.+)$/, m => m[1] + ' · Class ' + m[2], m => m[1] + ' · कक्षा ' + m[2]],
    [/^No internet — showing the copy saved on (.+)\.$/, m => 'Internet nahi — ' + m[1] + ' ki save copy dikh rahi hai.', m => 'इंटरनेट नहीं — ' + m[1] + ' की सेव प्रति दिख रही है।'],
    [/^(\d+) waiting · offline$/, m => m[1] + ' bhejna baaki · internet nahi', m => m[1] + ' भेजना बाकी · इंटरनेट नहीं'],
    [/^(\d+) marked absent\.(?: Not found: (.+)\.)? Check, then Save\.$/, m => m[1] + ' absent lag gaye.' + (m[2] ? ' Nahi mile: ' + m[2] + '.' : '') + ' Check karke Save karein.', m => m[1] + ' अनुपस्थित लग गए।' + (m[2] ? ' नहीं मिले: ' + m[2] + '।' : '') + ' जाँचकर सेव करें।'],
    [/^(\d+) day\(s\) saved\.$/, m => m[1] + ' din save ho gaye.', m => m[1] + ' दिन सेव हो गए।'],
    [/^(\d+) day\(s\) marked as holiday\.$/, m => m[1] + ' din ki chhutti lag gayi.', m => m[1] + ' दिन की छुट्टी लग गई।'],
    [/^Holiday marked for Class (.+)\.$/, m => 'Class ' + m[1] + ' ki chhutti lag gayi.', m => 'कक्षा ' + m[1] + ' की छुट्टी लग गई।'],
    [/^Holiday removed\.$/, () => 'Chhutti hata di.', () => 'छुट्टी हटा दी।'],
    [/^Homework date: (.+)\. Sundays and saved holidays skipped\.$/, m => 'Homework date: ' + m[1] + '. Ravivaar aur chhuttiyan chhodi gayi.', m => 'गृहकार्य तारीख़: ' + m[1] + '। रविवार और छुट्टियाँ छोड़ी गईं।'],
    [/^(\d+) period\(s\) filled\. Sundays and saved holidays skipped\. Review, then send\.$/, m => m[1] + ' period bhar diye. Check karke bhejein.', m => m[1] + ' पीरियड भर दिए। जाँचकर भेजें।'],
    [/^(.+) marks saved\. The result is updated\.$/, m => m[1] + ' ke ank save ho gaye. Result update ho gaya.', m => m[1] + ' के अंक सेव हो गए। परिणाम अपडेट हो गया।'],
    [/^Opened (.+)\. Tap the absent children, then Save\.$/, m => m[1] + ' khula. Absent bachchon par tap karke Save karein.', m => m[1] + ' खुला। अनुपस्थित बच्चों पर टैप करके सेव करें।'],
    [/^Save (\d+) day\(s\) with every child of Class (.+) present\?$/, m => m[1] + ' din Class ' + m[2] + ' ke sab bachche present save karein?', m => m[1] + ' दिन कक्षा ' + m[2] + ' के सभी बच्चे उपस्थित सेव करें?'],
    [/^(\d+) school days with attendance\. Put days and presents on the (.+) report card of all (\d+) children\?$/, m => m[1] + ' school din ki haziri mili. Sabhi ' + m[3] + ' bachchon ke ' + m[2] + ' report card par daal dein?', m => m[1] + ' स्कूल दिवसों की हाज़िरी मिली। सभी ' + m[3] + ' बच्चों के ' + m[2] + ' रिपोर्ट कार्ड पर डाल दें?'],
    [/^(\d+) change\(s\) are not sent yet \(no internet\)\. Log out anyway\? They will be lost\.$/, m => m[1] + ' badlav abhi bheje nahi gaye (internet nahi). Fir bhi log out karein? Ye mit jayenge.', m => m[1] + ' बदलाव अभी भेजे नहीं गए (इंटरनेट नहीं)। फिर भी लॉग आउट करें? ये मिट जाएँगे।'],
    [/^Attendance calendar · (.+)$/, m => 'Haziri calendar · ' + m[1], m => 'हाज़िरी कैलेंडर · ' + m[1]],
    [/^(Submit|Update) attendance · (\d+) P · (\d+) A( · (\d+) L)?$/, m => (m[1] === 'Submit' ? 'Haziri submit karein' : 'Haziri update karein') + ' · ' + m[2] + ' P · ' + m[3] + ' A' + (m[5] ? ' · ' + m[5] + ' L' : ''), m => (m[1] === 'Submit' ? 'हाज़िरी जमा करें' : 'हाज़िरी अपडेट करें') + ' · ' + m[2] + ' उप. · ' + m[3] + ' अनु.' + (m[5] ? ' · ' + m[5] + ' अव.' : '')],
    [/^(\d+) present · (\d+) absent · (\d+) leave · (.+)$/, m => m[1] + ' present · ' + m[2] + ' absent · ' + m[3] + ' chhutti · ' + m[4], m => m[1] + ' उपस्थित · ' + m[2] + ' अनुपस्थित · ' + m[3] + ' अवकाश · ' + m[4]],
    [/^Class (.+) · (\d+) students$/, m => 'Class ' + m[1] + ' · ' + m[2] + ' bachche', m => 'कक्षा ' + m[1] + ' · ' + m[2] + ' छात्र'],
    [/^(\d+) school day\(s\)$/, m => m[1] + ' school din', m => m[1] + ' स्कूल दिवस'],
    [/^Roll (\S+)( · )?$/, m => 'Roll ' + m[1] + (m[2] || ''), m => 'रोल ' + m[1] + (m[2] || '')],
    [/^(\d+) saved$/, m => m[1] + ' save', m => m[1] + ' सेव'], [/^(\d+) to mark$/, m => m[1] + ' baaki', m => m[1] + ' बाकी'], [/^(\d+) holiday$/, m => m[1] + ' chhutti', m => m[1] + ' छुट्टी'],
    [/^Holiday for (\d+) day\(s\)$/, m => m[1] + ' din ki chhutti', m => m[1] + ' दिन की छुट्टी'],
    [/^Class (.+) · School holiday: (.+)$/, m => 'Class ' + m[1] + ' · School ki chhutti: ' + m[2], m => 'कक्षा ' + m[1] + ' · विद्यालय अवकाश: ' + m[2]],
    [/^Class (.+) · Class holiday: (.+)$/, m => 'Class ' + m[1] + ' · Class ki chhutti: ' + m[2], m => 'कक्षा ' + m[1] + ' · कक्षा अवकाश: ' + m[2]],
    [/^Class (.+) · Attendance saved — (\d+)\/(\d+) present$/, m => 'Class ' + m[1] + ' · Haziri save — ' + m[2] + '/' + m[3] + ' present', m => 'कक्षा ' + m[1] + ' · हाज़िरी सेव — ' + m[2] + '/' + m[3] + ' उपस्थित'],
    [/^Class (.+) · Upcoming day$/, m => 'Class ' + m[1] + ' · Aane wala din', m => 'कक्षा ' + m[1] + ' · आने वाला दिन'],
    [/^Class (.+) · Attendance not marked yet$/, m => 'Class ' + m[1] + ' · Haziri abhi nahi lagi', m => 'कक्षा ' + m[1] + ' · हाज़िरी अभी नहीं लगी'],
    [/^Photo saved for (.+)\.$/, m => m[1] + ' ki photo save ho gayi.', m => m[1] + ' की फ़ोटो सेव हो गई।'],
    [/^Loading (.+)…$/, m => m[1] + ' khul raha hai…', m => m[1] + ' खुल रहा है…'],
    [/^Pending$/, () => 'Baaki', () => 'बाकी']
  ];
  const hgIdx = { hg: 0, hi: 1 };
  function tr1(s) {
    const lang = get();
    const e = W[s];
    if (lang === 'en') return e && e[2] ? e[2] : s;
    if (e) return e[hgIdx[lang]];
    for (const [re, a, b] of P) { const m = s.match(re); if (m) return (lang === 'hg' ? a : b)(m); }
    return s;
  }
  function tr(s) {
    if (typeof s !== 'string') return s;
    const m = s.match(/^(\s*)([\s\S]*?)(\s*)$/); if (!m[2]) return s;
    return m[1] + tr1(m[2]) + m[3];
  }
  window.taTr = tr;
  // ---- apply to the page ----
  const ORIG = new WeakMap(), SET = new WeakMap();
  const SKIP = /^(SCRIPT|STYLE|TEXTAREA|INPUT|SELECT)$/;
  function doText(n) {
    const p = n.parentElement; if (!p || SKIP.test(p.tagName) || p.closest('[data-notr]')) return;
    if (SET.get(n) !== n.data) ORIG.set(n, n.data);         // new or redrawn text: remember the English
    const en = ORIG.get(n), out = tr(en);
    if (n.data !== out) n.data = out;
    SET.set(n, n.data);
  }
  function doAttrs(el) {
    ['placeholder', 'aria-label', 'title'].forEach(a => {
      if (!el.hasAttribute(a)) return;
      const key = 'data-en-' + a, cur = el.getAttribute(a);
      if (!el.hasAttribute(key) || el.getAttribute('data-tr-' + a) !== cur) el.setAttribute(key, cur);
      const out = tr(el.getAttribute(key)); if (cur !== out) el.setAttribute(a, out); el.setAttribute('data-tr-' + a, out);
    });
  }
  let busy = false;
  function apply(root) {
    if (!root || busy) return; busy = true;
    try {
      if (root.nodeType === 3) doText(root);
      else if (root.nodeType === 1) {
        if (root.closest && root.closest('[data-notr]')) return;
        const w = document.createTreeWalker(root, NodeFilter.SHOW_TEXT); let n; while ((n = w.nextNode())) doText(n);
        if (root.matches && root.matches('[placeholder],[aria-label],[title]')) doAttrs(root);
        root.querySelectorAll && root.querySelectorAll('[placeholder],[aria-label],[title]').forEach(doAttrs);
      }
    } finally { busy = false; }
  }
  function applyAll() { apply(document.getElementById('phone')); apply(document.getElementById('toast')); }
  const obs = new MutationObserver(list => {
    if (busy) return;
    list.forEach(m => { if (m.type === 'characterData') apply(m.target); else m.addedNodes.forEach(apply); if (m.type === 'attributes') doAttrs(m.target); });
  });
  function start() {
    document.documentElement.lang = get() === 'hi' ? 'hi' : 'en';
    ['phone', 'toast'].forEach(id => { const el = document.getElementById(id); if (el) obs.observe(el, { childList: true, subtree: true, characterData: true, attributes: true, attributeFilter: ['placeholder', 'aria-label', 'title'] }); });
    applyAll();
  }
  // Pop-up questions are translated too.
  const oc = window.confirm.bind(window); window.confirm = msg => oc(tr(String(msg)));
  // Language choice: three chips, used on the login screen, the header button and the account sheet.
  window.taLang = get;
  window.taLocale = () => get() === 'hi' ? 'hi-IN' : 'en-IN';
  window.setLang = function (l) {
    try { localStorage.setItem('ta_lang', l); } catch (e) {}
    document.documentElement.lang = l === 'hi' ? 'hi' : 'en';
    if (typeof render === 'function') { try { if (typeof SHEET !== 'undefined' && SHEET && typeof openMe === 'function' && S) openMe(); else render(); } catch (e) {} }
    applyAll();
  };
  window.langCycle = function () { const i = LANGS.findIndex(x => x[0] === get()); setLang(LANGS[(i + 1) % LANGS.length][0]); };
  window.langShort = () => ({ en: 'EN', hg: 'HG', hi: 'हि' })[get()] || 'EN';
  window.langPicker = function () {
    return `<div class="langpick" data-notr><span>Language · भाषा</span><div class="chips">${LANGS.map(([k, l]) => `<button type="button" class="chip ${get() === k ? 'on' : ''}" onclick="setLang('${k}')">${l}</button>`).join('')}</div></div>`;
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();
})();
