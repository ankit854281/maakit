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
console.log('Jyotish engine: theek');
