// Public certificate page (cert.php): everyone's competition certificates, search by name or mobile number,
// photos of the competition, each certificate drawn on demand (cert-draw.js) → share / download / WhatsApp.
'use strict';
const $ = id => document.getElementById(id);
const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
const LINK = location.origin + location.pathname;
const POSN = { 1: 'First', 2: 'Second', 3: 'Third' }, MEDAL = { 1: '🥇', 2: '🥈', 3: '🥉' };
let D = null, ROWS = [], CLS = '', IDS = null; const PH = {}, KID = {}, PNG = {};
// Opening story (same SNMSIS welcome as the Chairman app): logo + SNMSIS in many languages → SNMSIS → the certificates
const LANGS = [['SNMSIS', 'English'], ['एस एन एम एस आई एस', 'हिन्दी'], ['এস এন এম এস আই এস', 'বাংলা'], ['எஸ் என் எம் எஸ் ஐ எஸ்', 'தமிழ்'], ['ఎస్ ఎన్ ఎమ్ ఎస్ ఐ ఎస్', 'తెలుగు'],
  ['એસ એન એમ એસ આઈ એસ', 'ગુજરાતી'], ['ਐਸ ਐਨ ਐਮ ਐਸ ਆਈ ਐਸ', 'ਪੰਜਾਬੀ'], ['ಎಸ್ ಎನ್ ಎಂ ಎಸ್ ಐ ಎಸ್', 'ಕನ್ನಡ'], ['എസ് എൻ എം എസ് ഐ എസ്', 'മലയാളം'], ['ایس این ایم ایس آئی ایس', 'اردو'],
  ['エス・エヌ・エム・エス・アイ・エス', '日本語'], ['ЭС ЭН ЭМ ЭС АЙ ЭС', 'Русский'], ['إس إن إم إس آي إس', 'العربية'], ['SNMSIS', 'English']];
const SCN = [6000, 2600, 3600]; let SI = -1, ST = [];
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
const longDate = d => new Date(d + 'T00:00:00').toLocaleDateString('en-IN', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
const shortDate = d => new Date(d + 'T00:00:00').toLocaleDateString('en-IN', { weekday: 'short', day: '2-digit', month: 'short' }) + ' ' + d.slice(0, 4);
const get = async u => { const r = await fetch(u, { cache: 'no-store' }); const j = await r.json().catch(() => null); if (!r.ok || !j || !j.ok) throw new Error((j && j.error) || 'Server problem.'); return j; };
const ORDER = ['PG', 'LKG', 'UKG', '1st', '2nd', '3rd', '4th', '5th', '6th', '7th', '8th', '9th', '10th'];
const clsIx = c => { const i = ORDER.indexOf(c); return i < 0 ? 99 : i; };

(async () => {
  try { D = await get('cert_api.php?a=all'); } catch (e) { $('msg').textContent = e.message; return; }
  D.entries.forEach(n => n.sids.forEach(sid => { const k = D.names[sid]; if (k) ROWS.push({ sid, n, name: k.name, cls: k.cls }); }));
  ROWS.sort((a, b) => clsIx(a.cls) - clsIx(b.cls) || a.name.localeCompare(b.name));
  const cl = [...new Set(ROWS.map(r => r.cls))].sort((a, b) => clsIx(a) - clsIx(b));
  $('cls').innerHTML = ['', ...cl].map(c => `<button type="button" data-cls="${esc(c)}" class="${c === CLS ? 'on' : ''}">${c ? esc(c) : 'All'}</button>`).join('');
  gallery(); list();
})();
async function photo(n, w) { const k = n.ev.id + '|' + n.id + '|' + (w ? 1 : 0); if (PH[k] !== undefined) return PH[k]; for (let t = 0; t < 3; t++) { try { return (PH[k] = (await get(`cert_api.php?a=ph&k=${encodeURIComponent(n.ev.id + '|' + n.id)}${w ? '&w=1' : ''}`)).data || ''); } catch (e) { await new Promise(r => setTimeout(r, 700 * (t + 1))); } } return ''; }
// Children's photos: at most 4 requests at a time (a busy server dropped some when ~90 went together) and a failed
// load is retried later instead of being remembered as "no photo" (owner, 6 Nov 2026: photos missing on some certificates).
let KQ = 0; const KW = [];
const kslot = () => new Promise(ok => { if (KQ < 4) { KQ++; ok(); } else KW.push(ok); }), kfree = () => { const n = KW.shift(); if (n) n(); else KQ--; };
async function kid(sid, tries) {
  if (KID[sid]) return KID[sid]; const k = D.names[sid]; if (!k || !k.ph) return '';
  if (KID['p' + sid]) return KID['p' + sid]; let ok = '';
  KID['p' + sid] = (async () => { for (let t = 0; t < (tries || 3) && !ok; t++) { await kslot(); try { ok = (await get('cert_api.php?a=kid&sid=' + encodeURIComponent(sid))).data || ''; } catch (e) {} finally { kfree(); } if (!ok) await new Promise(r => setTimeout(r, 700 * (t + 1))); }
    delete KID['p' + sid]; if (ok) KID[sid] = ok; return ok; })();
  return KID['p' + sid];
}
function gallery() {
  const L = D.entries.filter(n => n.photoW || n.photo); if (!L.length) return; $('gal').hidden = false; $('gal').dataset.ok = '1';
  $('galg').innerHTML = L.map((n, i) => `<figure data-g="${i}"><img alt=""><figcaption>${esc(n.team ? 'Team ' + n.team : D.names[n.sids[0]] ? D.names[n.sids[0]].name : '')}<small>${esc(n.cls)} · ${esc(n.cat)}</small></figcaption></figure>`).join('');
  const io = 'IntersectionObserver' in window ? new IntersectionObserver(es => es.forEach(e => { if (e.isIntersecting) { io.unobserve(e.target); load(e.target); } }), { rootMargin: '200px' }) : null;
  const load = async f => { const n = L[+f.dataset.g], src = await photo(n, n.photoW) || await photo(n, false); if (src) f.querySelector('img').src = src; };
  document.querySelectorAll('#galg figure').forEach(f => { io ? io.observe(f) : load(f); f.onclick = () => { const im = f.querySelector('img'); if (im.src) full(im.src); }; });
}
function list() {
  const q = $('q').value.trim().toLowerCase(); if ($('gal').dataset.ok) $('gal').hidden = !!(q || IDS || CLS);
  const L = ROWS.filter(r => (!CLS || r.cls === CLS) && (IDS ? IDS.includes(r.sid) : (!q || r.name.toLowerCase().includes(q) || (r.n.team || '').toLowerCase().includes(q))));
  $('res').innerHTML = `<p class="cnt">${L.length} certificate${L.length === 1 ? '' : 's'}${IDS ? ' for this mobile number' : ''}</p>` + L.slice(0, 120).map(r => `<div class="card row" data-sid="${esc(r.sid)}" data-id="${esc(r.n.id)}">
      <div class="rh"><span class="av" data-av="${esc(r.sid)}">${esc(certInitials(r.name))}</span><div><b>${esc(r.name)}</b><small>Class ${esc(r.cls)} · ${esc(r.n.cat)}${r.n.team ? ' · Team ' + esc(r.n.team) : ''}</small></div><em>${r.n.pos ? MEDAL[r.n.pos] : '🎖️'}</em></div>
      <div class="pv"></div><button class="open">🎓 View certificate</button></div>`).join('') + (L.length > 120 ? '<p class="cnt">Type a name to see more.</p>' : '') + (!L.length ? '<p class="cnt">No match found. Please check the spelling.</p>' : '');
  document.querySelectorAll('[data-av]').forEach(async el => { const s = await kid(el.dataset.av); if (s) el.innerHTML = `<img src="${s}" alt="">`; });
}
function dataOf(r, kids, ph, pw) {
  const n = r.n;
  return { school: D.school, event: n.ev.title, date: longDate(n.ev.date), cat: n.cat, sec: n.sec, pos: n.pos, cls: n.cls, name: r.name, team: n.team, member: !!n.team, members: '',
    teacher: n.teacher, principal: D.principal, photo: ph, photoW: pw, signed: shortDate(n.ev.date), year: n.ev.date.slice(0, 4), no: certNo(n.ev.date, n.id, r.sid),
    seal: D.school, tSign: D.signs[n.ct] || '', pSign: D.signs.P || '', kids };
}
async function make(r) {
  const key = r.n.id + '|' + r.sid; if (PNG[key]) return PNG[key];
  const kids = await Promise.all(r.n.sids.map(async s => ({ name: (D.names[s] || {}).name || '', src: await kid(s), me: s === r.sid })));
  const png = await certPNG(dataOf(r, kids, r.n.photo ? await photo(r.n, false) : '', r.n.photoW ? await photo(r.n, true) : ''));
  if (kids.every(k => k.src || !(D.names[r.n.sids[kids.indexOf(k)]] || {}).ph)) PNG[key] = png; return png;
}
function rowOf(el) { return ROWS.find(x => x.sid === el.dataset.sid && x.n.id === el.dataset.id); }
function textOf(r) { return `🌟 I achieved this! 🌟
${r.name} (Class ${r.cls}) has received ${r.n.pos ? 'the ' + POSN[r.n.pos] + ' Position 🏆' : 'a Certificate of Participation 🎉'} in ${r.n.cat} at the ${r.n.ev.title}, ${D.school}.

View your child's certificate here 👉 ${LINK}`; }
function copyText(t) { try { if (navigator.clipboard && navigator.clipboard.writeText) { navigator.clipboard.writeText(t).catch(() => fbCopy(t)); return; } } catch (e) {} fbCopy(t); }
function fbCopy(t) { const ta = document.createElement('textarea'); ta.value = t; ta.style.position = 'fixed'; ta.style.opacity = '0'; document.body.appendChild(ta); ta.select(); try { document.execCommand('copy'); } catch (e) {} ta.remove(); }
function say(m) { let t = document.querySelector('.toast'); if (!t) { t = document.createElement('div'); t.className = 'toast'; document.body.appendChild(t); } t.textContent = m; t.classList.add('on'); clearTimeout(say.t); say.t = setTimeout(() => t.classList.remove('on'), 5000); }
function full(src) { const o = document.createElement('div'); o.className = 'full'; o.innerHTML = `<img src="${src}" alt=""><small>Tap to close</small>`; o.onclick = () => o.remove(); document.body.appendChild(o); }

let T0 = 0;
$('q').addEventListener('input', () => { clearTimeout(T0); const v = $('q').value.replace(/\D/g, ''); IDS = null; $('msg').textContent = '';
  if (v.length >= 10 && /^[\d\s+()-]+$/.test($('q').value.trim())) T0 = setTimeout(findMobile, 300); else T0 = setTimeout(list, 120); });
$('f').addEventListener('submit', e => { e.preventDefault(); const v = $('q').value.replace(/\D/g, ''); if (v.length >= 10) findMobile(); else list(); $('q').blur(); setTimeout(() => $('res').scrollIntoView({ behavior: 'smooth', block: 'start' }), 80); });
async function findMobile() {
  const m = $('q').value.replace(/\D/g, '').slice(-10); $('msg').textContent = 'Searching…';
  try { const r = await fetch('cert_api.php?a=find', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ mobile: m }) }), j = await r.json().catch(() => null);
    if (!r.ok || !j || !j.ok) { $('msg').textContent = (j && j.error) || 'Server problem.'; IDS = []; } else { IDS = j.ids; $('msg').textContent = ''; } } catch (e) { $('msg').textContent = 'No internet connection. Please try again.'; IDS = null; }
  list();
}
document.addEventListener('click', async e => {
  const c = e.target.closest('#cls button'); if (c) { CLS = c.dataset.cls; document.querySelectorAll('#cls button').forEach(b => b.classList.toggle('on', b === c)); list(); return; }
  const b = e.target.closest('.row button'); if (!b) return; const card = b.closest('.row'), r = rowOf(card); if (!r) return;
  const fn = ('certificate-' + r.name + '-' + r.n.cat).replace(/[^a-z0-9]+/gi, '-').toLowerCase() + '.png';
  if (b.classList.contains('open')) { b.disabled = true; b.textContent = 'Preparing…'; const png = await make(r);
    card.querySelector('.pv').innerHTML = `<img src="${png}" alt="Certificate">`; b.outerHTML = `<div class="bt"><button class="wa" data-a="share">💬 WhatsApp / Share</button><button data-a="dl">⬇️ Download</button><button data-a="cp">📋 Copy message</button></div><p class="hint">The certificate is shared as an image with a message. If the message does not appear in WhatsApp, long-press the text box and tap <b>Paste</b> — it is already copied.</p>`;
    card.querySelector('.pv img').onclick = () => full(png); return; }
  const a = b.dataset.a, png = await make(r);
  // the image itself + the message as caption (owner: "image share ho, message ke saath — link nahi"); the message is also copied in case an app drops it
  const txt = textOf(r); copyText(txt);
  if (a === 'cp') { say('Message copied. Long-press in WhatsApp and tap Paste.'); return; }
  if (a === 'share') { try { const f = new File([await (await fetch(png)).blob()], fn, { type: 'image/png' });
    if (navigator.canShare && navigator.canShare({ files: [f] })) { await navigator.share({ files: [f], text: txt }); return; } } catch (er) { if (er && er.name === 'AbortError') return; }
    say('This browser cannot share images directly, so the certificate has been downloaded. Share it from your gallery — the message is already copied. Tip: open this link in Chrome to share in one tap.'); }
  const l = document.createElement('a'); l.href = png; l.download = fn; document.body.appendChild(l); l.click(); l.remove();
});
