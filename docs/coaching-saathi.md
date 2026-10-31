# Coaching Saathi — V1 se V5 (maakit.in/coaching)

Ek hi software par bahut saari chhoti coaching. Owner ke liye free, student app ₹54 / 6 mahine.

## Kaun kya kar sakta hai

| Login | Kya | Kya nahi |
| --- | --- | --- |
| Owner | Sab: bachche, course/batch, fees, receipt, maafi, teacher, enquiry, website, kaun paid | — |
| Teacher | Apne batch ki haaziri (aaj se 7 din pichhe tak), apne batch ke bachche, "3 din se nahi aaya" | Fees, hisaab, doosre teacher |
| Parent / student | Apne bachchon ki fees, receipt, aaj absent (hamesha free); batch + haaziri (plan ya trial me) | Doosre bachche, coaching ka hisaab |

Sab **mobile + password** se (OTP / SMS nahi). Ek number par kai coaching / kai bachche ho to login ke baad chunne ka page aata hai. Login 90 din tak yaad rehta hai.

- **Owner:** login page par "Apni coaching free me jodiye" — apna number aur password khud rakhta hai.
- **Teacher / parent:** owner app me "Login bhejiye" dabata hai → pehla password ek baar screen par dikhta hai + WhatsApp button. Pehli login par naya password rakhna zaroori.
- **Bhool gaye:** teacher/parent ko unka owner naya password de deta hai. Owner bhoole to Maakit `maakit.in/coaching/index.php?reset=1` par website ki UPDATE_KEY se badalta hai.
- 5 galat password = 15 minute band. Password badalte hi baaki phone se login band.
- Jo number do coaching me juda hai (ya kahin owner hai), uska password koi ek coaching nahi badal sakti.

## V1 me kya bana

- Coaching banana (naam, type, shehar, UP registration no.), website ka naam apne aap
- Course: har mahine / poora course (kishton me) / ek baar. Batch: samay, teacher, seat limit
- Bachcha jodna: har bachche ki apni fees + discount ka kaaran
- Fees khata: mahine ki fees apne aap judti hai (cron nahi chahiye), baaki aage judta hai, advance katta hai
- Owner ki sirf 3 entry: paisa mila (receipt + QR + WhatsApp), maaf karo (kaaran zaroori), galti sudhaar
- Receipt radd: sirf owner, kaaran ke saath; receipt mitti nahi, QR par "RADD" dikhta hai
- Haaziri ek click P/A, phir absent bachchon ke parent ko WhatsApp button
- "Khatre me bachche": 3 din lagatar absent, ya fees 15 din se zyada baaki
- Enquiry + follow-up date; website ka form bhi yahin aata hai
- Har coaching ki website: `maakit.in/coaching/s/<naam>` (course, fees, batch, seat, call/WhatsApp, enquiry)
- Saal ki report: fees judi, mili, maaf ki list, discount
- Student app plan: ₹54 / ₹99, 7 din trial, Razorpay + webhook, owner gift me bhar sakta hai
- **Game Zone** (school ke student app wala engine): roz 4 round × 5 sawaal (maths class ke hisaab se + English shabd), 20 second, XP, level, streak, chest, galat sawaal 1/3/7 din baad wapas, coaching ki weekly league, Aaj ka shabd. Teacher ko kuch nahi likhna.

## V2–V5 me kya bana (31 Oct 2026)

| Version | Kya | Kahan |
| --- | --- | --- |
| V2 | **Maakit Admin Panel**: saari coaching, nayi coaching + owner ko pehla password, band / chalu, owner ka naya password, kamai | `maakit.in/coaching/admin.php` |
| V2 | Logo (app, website, receipt par) | Aur › Coaching details |
| V2 | Suchna (poori coaching ya ek batch) — parent ko hamesha free | Padhai › Suchna |
| V2 | Homework, Timetable (owner banata hai), Notes (likha, link, PDF/photo 5 MB) | Padhai |
| V3 | Kagaz wale test ke marks, ausat, topper, parent ko WhatsApp | Padhai › Test aur marks |
| V3 | Quiz: sawaal Word/WhatsApp se chipkaiye, har sawaal ka samay server ginta hai | Padhai › Quiz |
| V4 | Mock test: poore test ka samay, galat par minus, section, rank list, kaun sa sawaal kitno ne sahi kiya | Padhai › Quiz |
| V4 | Doubt box: bachcha poochhe (din me 5), apne batch ka teacher jawab de | Padhai › Doubt box |
| V5 | Teacher ki haaziri (P / ½ / A) + salary slip (Ravivar chhutti, maaf chhutti, advance, bonus) — slip ke baad mahina jam jaata hai | Padhai › Teacher salary |
| V5 | Risk score 0–100: 3 din absent +40, fees 15 din+ +30, haaziri 75% se kam +15/25, marks gire +20 | Padhai › Khatre me bachche |
| V5 | Sabko WhatsApp: fees baaki, aaj absent, poore batch ko message — ek-ek button | Padhai › Sabko WhatsApp |

Student app (paid hissa) me ab: aaj ka timetable, homework, quiz / mock, marks + class ausat, notes, doubt box. Suchna lock me bhi dikhti hai.

**Quiz ke niyam:** sahi jawab kabhi pehle browser me nahi jaata. Quiz me refresh se samay dobara shuru nahi hota, der (samay + 5 sec) se diya jawab khaali ginta hai. Mock me har jawab turant save hota hai; samay khatam = jo save hua wahi gina jaata hai. Ek bachcha ek hi baar. Bachchon ke shuru karne ke baad sawaal nahi badal sakte. Sahi jawab tab dikhte hain jab quiz band ho jaye.

**Files:** notes ki PDF / photo website ke bahar `/home/<user>/cs-files` me rakhi jaati hai (na ho sake to `coaching/files/`, jo `.htaccess` se band hai). Photo dobara bana kar rakhi jaati hai. Sirf us batch ke parent (app chalu ho) aur us batch ka teacher / owner khol sakta hai.

**Abhi nahi (paise / chaabi chahiye):** apne aap WhatsApp (Meta ka paid API + template), phone par push notification (Firebase), AI se sawaal (AI ki chaabi), Play Store app.

## Live karne ke kadam

1. `maakit.in/update.php?key=...` kholiye — naya code aur `sql/045` se `sql/048` tak apne aap chal jayengi.
   Admin panel: `maakit.in/coaching/admin.php` — website ki UPDATE_KEY se (ya `config.php` me alag `CS_ADMIN_KEY`).
2. `maakit.in/coaching` kholkar "Apni coaching free me jodiye" se pehli coaching banaiye.
3. Payment (jab lock chalu karna ho): `config.php` me `CS_RAZORPAY_KEY_ID`, `CS_RAZORPAY_KEY_SECRET`, `CS_RAZORPAY_WEBHOOK_SECRET`. Razorpay dashboard me webhook `https://maakit.in/coaching/webhook.php`, event `payment.captured`. Jab tak ye nahi bhare, kisi ka app band nahi hota.

## Abhi khule faisle (owner)

- Play Store par app ke andar payment: abhi app website (PWA) hai, isliye Google billing ka sawaal nahi. Play Store par dalne se pehle policy padhni hai.
- Free owner ki soft limit: abhi 100 bachche par suchna dikhti hai (`CS_SOFT_LIMIT`), roka nahi jaata.
- Family pack ka rate tay nahi — abhi har bachche ka alag plan.
- Push notification (FCM) abhi nahi: absent ki khabar WhatsApp button + parent app me dikhti hai.
- Bachche ki photo, logo upload: V1 me nahi (bachchon ke data me kam se kam). Logo ki jagah naam ka pehla akshar + coaching ka rang.
