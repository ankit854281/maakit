// ============================================================
//  जाँच — Maakit Jyotish ki ginti (jyotish/engine.js)
//  Grah ki jagah jaani-maani tareekh se milaai jaati hai, aur
//  gun milan ke ank 0–36 ke beech, aath koot ke saath.
// ============================================================
const fs = require('fs'), vm = require('vm'), path = require('path');
const root = path.join(__dirname, '..', '..', 'jyotish');
const ctx = {}; vm.createContext(ctx);
vm.runInContext(fs.readFileSync(path.join(root, 'astronomy.min.js'), 'utf8'), ctx);
vm.runInContext(fs.readFileSync(path.join(root, 'engine.js'), 'utf8') + ';this.Jy=Jy;this.D=Date;', ctx);
vm.runInContext(fs.readFileSync(path.join(root, 'daily.js'), 'utf8') + ';this.Jd=Jd;', ctx);
const Jy = ctx.Jy;
// Dates must come from the same context, or the library's 'instanceof Date' check fails.
const U = (...a) => new ctx.D(ctx.D.UTC(...a));
function check(ok, m) { if (!ok) { console.error('Jyotish engine: ' + m); process.exit(1); } }
function near(a, b, tol) { let d = Math.abs(a - b) % 360; return Math.min(d, 360 - d) <= tol; }

// 1 Jan 2000 12:00 UT — Lahiri ayanamsa ~23.85, sidereal Sun ~256.5, Moon ~199.5
const j2k = U(2000, 0, 1, 12);
check(Math.abs(Jy.ayanamsa(j2k) - 23.853) < 0.01, 'ayanamsa ' + Jy.ayanamsa(j2k));
const p = Jy.siderealPositions(j2k);
check(near(p.Su.lon, 256.5, 0.2), 'Sun ' + p.Su.lon);
check(near(p.Mo.lon, 199.5, 0.5), 'Moon ' + p.Mo.lon);
check(near(p.Ke.lon, p.Ra.lon + 180, 0.001), 'Rahu/Ketu opposite');
// Sunrise in Delhi on the equinox: ascendant ≈ Sun's tropical longitude (~0°)
check(near(Jy.ascendant(U(2024, 2, 20, 0, 54), 28.61, 77.21), 0, 2.5), 'sunrise ascendant');

const k = Jy.kundli({ date: U(2001, 0, 15, 3, 0), lat: 25.3953, lon: 82.5683 });
check(k.planets.length === 9 && k.planets.every(x => x.house >= 1 && x.house <= 12), 'nine planets in houses');
check(k.dashas.length >= 9 && Math.abs((k.dashas[1].start - k.dashas[0].end)) < 1000, 'dasha chain');
const yrs = k.dashas.slice(0, 9).reduce((s, d) => s + d.years, 0);
check(yrs === 120, 'Vimshottari totals 120 years, got ' + yrs);

for (let i = 0; i < 300; i++) {
  const m = Jy.milan({ sign: i % 12, nak: (i * 7) % 27 }, { sign: (i * 5) % 12, nak: (i * 11) % 27 });
  check(m.rows.length === 8 && m.total >= 0 && m.total <= 36, 'milan range ' + m.total);
  check(m.rows.reduce((s, r) => s + r.max, 0) === 36, 'milan maximum 36');
}
const same = Jy.milan({ sign: 3, nak: 7 }, { sign: 3, nak: 7 });
check(same.rows[7].got === 0, 'same nakshatra = Nadi dosha');

// ================= V2: daily card (jyotish/daily.js) =================
const Jd = ctx.Jd;
// crisis words match the server list exactly
const php = fs.readFileSync(path.join(root, 'ai_lib.php'), 'utf8');
const serverList = [...php.match(/const JY_CRISIS = \[([\s\S]*?)\];/)[1].matchAll(/'([^']+)'/g)].map(m => m[1]);
check(JSON.stringify(serverList) === JSON.stringify([...Jd.CRISIS]), 'crisis words differ between daily.js and ai_lib.php');
['I want to die', 'आत्महत्या', 'zindagi khatam kar dunga'].forEach(w => check(Jd.crisis(w), 'crisis miss ' + w));
check(!Jd.crisis('Shani ki dasha kab khatam'), 'crisis false alarm');
// check-in -> state
check(Jd.stateOf({ mood: 1, energy: 0, concern: 'neend' }) === 'tired', 'neend -> tired');
check(Jd.stateOf({ mood: 2, energy: 1, concern: 'gussa' }) === 'restless', 'gussa -> restless');
check(Jd.stateOf({ mood: 0, energy: 1, concern: null }) === 'low', 'bura -> low');
check(Jd.stateOf({ mood: 3, energy: 2, concern: 'theek' }) === 'steady', 'good -> steady');
check(Jd.stateOf(null) === null, 'no check-in -> null');
// library: every entry in 3 languages, free, nothing to buy
const banned = /ratna|gem|stone|yantra|buy|kharid|₹|rupee|paisa de|pandit|fast\b|vrat|upvas|रत्न|यंत्र|ख़रीद|खरीद|व्रत|उपवास/i;
Jd.LIB.forEach(p => { ['hl', 'hi', 'en'].forEach(l => { check(p.t[l] && p.why[l], 'missing text ' + p.id + ' ' + l); check(!banned.test(p.t[l]), 'paid or fasting upaya ' + p.id + ' ' + l); }); });
check(new Set(Jd.LIB.map(p => p.id)).size === Jd.LIB.length, 'duplicate upaya id');
for (let h = 1; h <= 12; h++) ['hl', 'hi', 'en'].forEach(l => check(Jd.HOUSE[h] && Jd.HOUSE[h].t[l], 'house theme ' + h + ' ' + l));
// card: every language, every state, no fatalistic words
const TD = Jy.today(U(2026, 9, 18, 6, 0));
const fatal = /death|maut|मृत्यु|accident|durghatna|दुर्घटना|barbaad|बर्बाद|definitely|guarantee|100%/i;
['hl', 'hi', 'en'].forEach(l => [null, 'low', 'stressed', 'restless', 'lonely', 'tired', 'steady'].forEach(st => {
  const c = Jd.card(l, k, TD, st, '2026-10-18', [], 'abcdef0123456789', Jy.SIGN_SHORT);
  check(c.insight && c.upaya && c.mantra && c.why.length === 4 && c.why.every(Boolean), 'card incomplete ' + l + ' ' + st);
  check(!fatal.test([c.insight, c.upaya, c.mantra].concat(c.extra, c.why).join(' ')), 'fatalistic words in card ' + l + ' ' + st);
  check(c.ctx.length <= 340, 'ctx too long ' + c.ctx.length);
}));
// how you feel wins over the chart
check(Jd.card('en', k, TD, 'tired', '2026-10-18', [], 's', Jy.SIGN_SHORT).cat === 'sleep', 'tired must get a sleep upaya');
check(Jd.card('en', k, TD, 'lonely', '2026-10-18', [], 's', Jy.SIGN_SHORT).cat === 'connect', 'lonely must get connect');
// no repeat within 3 days
const first = Jd.card('en', k, TD, 'tired', '2026-10-18', [], 'seed', Jy.SIGN_SHORT);
for (let i = 0; i < 40; i++) {
  const nxt = Jd.card('en', k, TD, 'tired', '2026-10-19', [{ date: '2026-10-18', id: first.id }], 'seed' + i, Jy.SIGN_SHORT);
  check(nxt.id !== first.id, 'upaya repeated the next day');
}
// same day, same phone = same card
check(Jd.card('en', k, TD, 'stressed', '2026-10-18', [], 'x', Jy.SIGN_SHORT).id === Jd.card('en', k, TD, 'stressed', '2026-10-18', [], 'x', Jy.SIGN_SHORT).id, 'card not stable');
// diya week
const wk = Jd.week(['2026-10-12', '2026-10-14', '2026-10-15', '2026-10-18', '2026-10-01'], '2026-10-18');
check(wk.days.length === 7 && wk.lit === 4 && wk.full && wk.days[6].date === '2026-10-18', 'diya week ' + JSON.stringify(wk));
check(!Jd.week(['2026-10-18'], '2026-10-18').full, 'one day is not a full week');
console.log('Jyotish engine: theek');
