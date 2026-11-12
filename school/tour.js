// Children's Day Prayagraj Tour (tour.php): opening story like cert.php, countdown to the trip, WhatsApp share.
'use strict';
const $ = id => document.getElementById(id);
const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
const LANGS = [['SNMSIS', 'English'], ['एस एन एम एस आई एस', 'हिन्दी'], ['এস এন এম এস আই এস', 'বাংলা'], ['எஸ் என் எம் எஸ் ஐ எஸ்', 'தமிழ்'], ['ఎస్ ఎన్ ఎమ్ ఎస్ ఐ ఎస్', 'తెలుగు'],
  ['એસ એન એમ એસ આઈ એસ', 'ગુજરાતી'], ['ਐਸ ਐਨ ਐਮ ਐਸ ਆਈ ਐਸ', 'ਪੰਜਾਬੀ'], ['ಎಸ್ ಎನ್ ಎಂ ಎಸ್ ಐ ಎಸ್', 'ಕನ್ನಡ'], ['എസ് എൻ എം എസ് ഐ എസ്', 'മലയാളം'], ['ایس این ایم ایس آئی ایس', 'اردو'],
  ['エス・エヌ・エム・エス・アイ・エス', '日本語'], ['ЭС ЭН ЭМ ЭС АЙ ЭС', 'Русский'], ['إس إن إم إس آي إس', 'العربية'], ['SNMSIS', 'English']];
const SCN = [5200, 3200, 4200]; let SI = -1, ST = [];
function scene() {
  const ad = $('intro'); if (!ad) return; SI++; if (SI >= SCN.length) return endIntro();
  const bars = ad.querySelectorAll('.ad-bars i'), sc = ad.querySelectorAll('.ad-s');
  bars.forEach((b, j) => { b.className = j < SI ? 'done' : ''; }); bars[SI].style.setProperty('--ms', SCN[SI] + 'ms'); void bars[SI].offsetWidth; bars[SI].className = 'run';
  sc.forEach((s, j) => s.classList.toggle('on', j === SI));
  if (SI === 0) { let n = 0; const el = $('lang'); const step = () => { if (SI !== 0) return; const [w, l] = LANGS[n % LANGS.length]; el.innerHTML = `<b dir="auto">${esc(w)}</b><small>${esc(l)}</small>`; n++; if (n < LANGS.length) ST.push(setTimeout(step, (SCN[0] - 600) / LANGS.length)); }; ST.push(setTimeout(step, 600)); }
  ST.push(setTimeout(scene, SCN[SI]));
}
function endIntro() { ST.forEach(clearTimeout); ST = []; SI = 99; const i = $('intro'); if (i) { i.classList.add('out'); setTimeout(() => i.remove(), 600); } }
$('adSkip').onclick = endIntro; $('adGo').onclick = endIntro; scene();
// countdown to Saturday 14 Nov 2026, 8 a.m. India time
const TRIP = new Date('2026-11-14T08:00:00+05:30').getTime();
function tick() {
  const el = $('count'); if (!el) return; const ms = TRIP - Date.now();
  if (ms <= 0) { el.innerHTML = Date.now() < TRIP + 12 * 3600e3 ? '<div class="big"><b>🚌 Tour day!</b><small>आज यात्रा है</small></div>' : '<div class="big"><b>Thank you!</b><small>यादगार यात्रा</small></div>'; return; }
  const d = Math.floor(ms / 864e5), h = Math.floor(ms / 36e5) % 24, m = Math.floor(ms / 6e4) % 60, s = Math.floor(ms / 1e3) % 60;
  el.innerHTML = [[d, 'days'], [h, 'hours'], [m, 'min'], [s, 'sec']].map(([v, l]) => `<div><b>${String(v).padStart(2, '0')}</b><small>${l}</small></div>`).join('');
  setTimeout(tick, 1000);
}
tick();
const LINK = 'https://maakit.in/school/tour.php';
const MSG = `🚌 *Children's Day Prayagraj Educational Tour*\n🎉 बाल दिवस विशेष — SN Mishra Singapore International School\n\n📅 Saturday, 14 Nov 2026\n🎓 Classes 4–10\n📍 Allahabad Museum · Hathi Park · Khusro Bagh · All Saints Cathedral\n\n🚨 Limited seats — register with your Class Teacher.\nपूरी जानकारी: ${LINK}`;
$('wa').href = 'https://wa.me/?text=' + encodeURIComponent(MSG);
$('copy').onclick = async () => { try { if (navigator.share) { await navigator.share({ title: "Children's Day Prayagraj Tour", text: MSG.replace(LINK, '').trim(), url: LINK }); return; } await navigator.clipboard.writeText(MSG); $('copy').textContent = '✅ Copied'; } catch (e) {} };
