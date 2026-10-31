# Coaching Saathi — V1 (maakit.in/coaching)

Ek hi software par bahut saari chhoti coaching. Owner ke liye free, student app ₹54 / 6 mahine.

## Kaun kya kar sakta hai

| Login | Kya | Kya nahi |
| --- | --- | --- |
| Owner | Sab: bachche, course/batch, fees, receipt, maafi, teacher, enquiry, website, kaun paid | — |
| Teacher | Apne batch ki haaziri (aaj se 7 din pichhe tak), apne batch ke bachche, "3 din se nahi aaya" | Fees, hisaab, doosre teacher |
| Parent / student | Apne bachchon ki fees, receipt, aaj absent (hamesha free); batch + haaziri (plan ya trial me) | Doosre bachche, coaching ka hisaab |

Sab mobile + OTP se. Ek number par kai coaching / kai bachche ho to login ke baad chunne ka page aata hai. Session 90 din (baar-baar OTP ka kharcha nahi).

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

## Live karne ke kadam

1. `maakit.in/update.php?key=...` kholiye — naya code aur `sql/045-coaching-saathi.sql` apne aap chal jayegi.
2. OTP: Smart Sarathi wala Fast2SMS / WhatsApp setup hi kaam aata hai. Wo chalu hai to kuch nahi karna.
3. Payment (jab lock chalu karna ho): `config.php` me `CS_RAZORPAY_KEY_ID`, `CS_RAZORPAY_KEY_SECRET`, `CS_RAZORPAY_WEBHOOK_SECRET`. Razorpay dashboard me webhook `https://maakit.in/coaching/webhook.php`, event `payment.captured`. Jab tak ye nahi bhare, kisi ka app band nahi hota.
4. Testing ke liye (live par nahi): `define('CS_DEMO_OTP', true);` — OTP screen par dikhta hai.

## Abhi khule faisle (owner)

- Play Store par app ke andar payment: abhi app website (PWA) hai, isliye Google billing ka sawaal nahi. Play Store par dalne se pehle policy padhni hai.
- Free owner ki soft limit: abhi 100 bachche par suchna dikhti hai (`CS_SOFT_LIMIT`), roka nahi jaata.
- Family pack ka rate tay nahi — abhi har bachche ka alag plan.
- Push notification (FCM) abhi nahi: absent ki khabar WhatsApp button + parent app me dikhti hai.
- Bachche ki photo, logo upload: V1 me nahi (bachchon ke data me kam se kam). Logo ki jagah naam ka pehla akshar + coaching ka rang.
