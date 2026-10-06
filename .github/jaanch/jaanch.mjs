// ============================================================
// Maakit — jaanch karne wala bot
//
// Do tarah se chalta hai:
//
//   node jaanch.mjs nabz    — har 15 minute. Sirf 4 main page.
//                             Khul rahe hain? Dheeme to nahi?
//
//   node jaanch.mjs poori   — subah 7 aur shaam 6. Har page,
//                             har dibba, har link, har photo.
//                             Iske liye asli browser chalta hai.
//
// Ye website me kuchh badalta NAHI hai — sirf dekhta hai.
// Na koi order banata hai, na koi form bhejta hai.
//
// Nateeja jaanch-nateeja.json me likh deta hai, jise workflow
// padhkar aapko khabar karta hai.
// ============================================================
import { writeFileSync } from 'node:fs';
import { PAGES, KHARAB, GALAT_JAGAH } from './pages.mjs';

const GHAR  = process.env.MAAKIT_URL || 'https://maakit.in';
const MODE  = (process.argv[2] || 'nabz').toLowerCase();
const DHEEMA = 6000;        // itne se zyada lage to "dheema" maana jayega

const galtiyan = [];
const add = (naam, baat) => galtiyan.push({ naam, baat });

/** ek page khol kar dekhiye — bina browser ke, sirf text */
async function dekho(p) {
  const pata = GHAR + p.path;
  const shuru = Date.now();
  let r, html;

  try {
    r = await fetch(pata, {
      redirect: 'follow',
      headers: { 'User-Agent': 'Maakit-Jaanch/1.0 (apni hi website dekh raha hai)' },
      signal: AbortSignal.timeout(25000),
    });
    html = await r.text();
  } catch (e) {
    add(p.naam, `खुला ही नहीं — ${String(e.message || e).slice(0, 120)}`);
    return;
  }

  const samay = Date.now() - shuru;

  if (!r.ok) {
    add(p.naam, `पेज नहीं खुला (कोड ${r.status})`);
    return;                                  // aage jaanchne ka matlab nahi
  }
  if (samay > DHEEMA) {
    add(p.naam, `बहुत धीमा — ${(samay / 1000).toFixed(1)} सेकंड लगे`);
  }

  for (const k of KHARAB) {
    if (html.includes(k)) { add(p.naam, `पेज पर गड़बड़ी दिख रही है: "${k}"`); break; }
  }
  for (const c of (p.chahiye || [])) {
    if (!html.includes(c)) add(p.naam, `पेज अधूरा है — "${c}" नहीं मिला`);
  }
  for (const n of (p.nahi || [])) {
    if (html.includes(n)) add(p.naam, `ग़लत पेज खुल रहा है — "${n}" दिख रहा है`);
  }
}

/** poori jaanch — asli browser se, jaise koi grahak dekhta hai */
async function browserSe() {
  let chromium;
  try { ({ chromium } = await import('playwright')); }
  catch { add('जाँच', 'browser नहीं मिला — पूरी जाँच नहीं हो पाई'); return; }

  const b = await chromium.launch();
  const p = await b.newPage({ viewport: { width: 390, height: 844 }, isMobile: true });

  const jsGalti = [];
  p.on('pageerror', e => jsGalti.push(String(e).slice(0, 160)));

  try {
    await p.goto(GHAR + '/', { waitUntil: 'networkidle', timeout: 45000 });

    // --- har dibba sahi jagah le ja raha hai? ---
    const dibbe = await p.$$eval('.tiles.cat .tile', a => a.map(x => ({
      naam: (x.querySelector('b')?.textContent || '').trim(),
      href: x.getAttribute('href'),
    })));

    if (dibbe.length === 0) {
      add('होम पेज', 'एक भी श्रेणी का डिब्बा नहीं दिखा');
    }

    for (const d of dibbe) {
      if (!d.href) { add('होम पेज', `"${d.naam}" वाले डिब्बे पर कोई लिंक ही नहीं`); continue; }
      try {
        const r = await fetch(new URL(d.href, GHAR).href, {
          redirect: 'follow', signal: AbortSignal.timeout(25000),
        });
        if (!r.ok) add('डिब्बा: ' + d.naam, `कहीं नहीं ले जा रहा (कोड ${r.status})`);
        else {
          const t = await r.text();
          const k = KHARAB.find(x => t.includes(x));
          if (k) add('डिब्बा: ' + d.naam, `जिस पेज पर ले जाता है वहाँ गड़बड़ी है: "${k}"`);
          // Page khul to gaya, par sahi page hai ya nahi — ye alag baat hai.
          // book.php bina id ke 200 hi lautata hai, par "kitaab nahi mili"
          // dikhata hai. Isi tarah ki galti pehle ek baar ho chuki hai.
          const w = GALAT_JAGAH.find(x => t.includes(x));
          if (w) add('डिब्बा: ' + d.naam, `ग़लत पेज पर ले जा रहा है — वहाँ "${w}" लिखा आ रहा है`);
        }
      } catch (e) {
        add('डिब्बा: ' + d.naam, `नहीं खुला — ${String(e.message || e).slice(0, 90)}`);
      }
    }

    // --- tooti hui photo ---
    const tooti = await p.$$eval('img', a => a
      .filter(i => i.complete && i.naturalWidth === 0 && i.getAttribute('src'))
      .map(i => i.getAttribute('src')).slice(0, 8));
    for (const s of tooti) add('होम पेज', `फ़ोटो नहीं खुली: ${s}`);

    // --- order ka page: saaman dikh raha hai? ---
    await p.goto(GHAR + '/order.php', { waitUntil: 'networkidle', timeout: 45000 });
    await p.waitForTimeout(1200);
    const n = await p.$$eval('.it', a => a.length).catch(() => 0);
    if (n < 5) add('ऑर्डर', `सामान की सूची लगभग खाली है — सिर्फ़ ${n} चीज़ें दिखीं`);

    // --- booking ka form bana hua hai? (bhejte nahi, sirf dekhte hain) ---
    await p.goto(GHAR + '/sewa.php?s=gaadi', { waitUntil: 'networkidle', timeout: 45000 });
    const khaane = await p.$$eval('form select, form input', a => a.length).catch(() => 0);
    if (khaane < 5) add('गाड़ी बुकिंग', `फ़ॉर्म अधूरा है — सिर्फ़ ${khaane} ख़ाने मिले`);

    if (jsGalti.length) add('वेबसाइट', `पेज पर जावास्क्रिप्ट की ग़लती: ${jsGalti[0]}`);
  } catch (e) {
    add('पूरी जाँच', `बीच में रुक गई — ${String(e.message || e).slice(0, 140)}`);
  } finally {
    await b.close();
  }
}

// ------------------------------------------------------------
const list = MODE === 'nabz' ? PAGES.filter(x => x.nabz) : PAGES;

// ek saath 4 se zyada nahi — warna shared hosting par asli grahak
// ke liye website dheemi pad jayegi
for (let i = 0; i < list.length; i += 4) {
  await Promise.all(list.slice(i, i + 4).map(dekho));
}

if (MODE === 'poori') await browserSe();

const nateeja = {
  mode: MODE,
  samay: new Date().toISOString(),
  theek: galtiyan.length === 0,
  galtiyan,
  dekhe: list.length,
};
writeFileSync('jaanch-nateeja.json', JSON.stringify(nateeja, null, 2));

if (galtiyan.length === 0) {
  console.log(`सब ठीक है — ${list.length} पेज देखे (${MODE})`);
} else {
  console.log(`${galtiyan.length} गड़बड़ी मिली:`);
  for (const g of galtiyan) console.log(`  • ${g.naam} — ${g.baat}`);
}

// Galti milne par bhi 0 hi lautate hain. Workflow ko report
// banani hai, isliye use "fail" nahi karna — wo khud faisla karega.
process.exit(0);
