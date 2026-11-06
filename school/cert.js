// Public certificate page (cert.php): everyone's competition certificates, search by name or mobile number,
// photos of the competition, each certificate drawn on demand (cert-draw.js) → share / download / WhatsApp.
'use strict';
const $ = id => document.getElementById(id);
const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
const LINK = location.origin + location.pathname;
const POSN = { 1: 'First', 2: 'Second', 3: 'Third' }, MEDAL = { 1: '🥇', 2: '🥈', 3: '🥉' };
let D = null, ROWS = [], CLS = '', IDS = null; const PH = {}, KID = {}, PNG = {};
setTimeout(() => { const i = $('intro'); if (i) { i.classList.add('out'); setTimeout(() => i.remove(), 700); } }, 2600);
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
  $('cls').innerHTML = ['', ...cl].map(c => `<button type="button" data-cls="${esc(c)}" class="${c === CLS ? 'on' : ''}">${c ? esc(c) : 'Sab'}</button>`).join('');
  gallery(); list();
})();
async function photo(n, w) { const k = n.ev.id + '|' + n.id + '|' + (w ? 1 : 0); if (PH[k] !== undefined) return PH[k]; try { PH[k] = (await get(`cert_api.php?a=ph&k=${encodeURIComponent(n.ev.id + '|' + n.id)}${w ? '&w=1' : ''}`)).data || ''; } catch (e) { PH[k] = ''; } return PH[k]; }
async function kid(sid) { if (KID[sid] !== undefined) return KID[sid]; const k = D.names[sid]; if (!k || !k.ph) return (KID[sid] = ''); try { KID[sid] = (await get('cert_api.php?a=kid&sid=' + encodeURIComponent(sid))).data || ''; } catch (e) { KID[sid] = ''; } return KID[sid]; }
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
  $('res').innerHTML = `<p class="cnt">${L.length} certificate${IDS ? ' — is number ke bachche' : ''}</p>` + L.slice(0, 120).map(r => `<div class="card row" data-sid="${esc(r.sid)}" data-id="${esc(r.n.id)}">
      <div class="rh"><span class="av" data-av="${esc(r.sid)}">${esc(certInitials(r.name))}</span><div><b>${esc(r.name)}</b><small>Class ${esc(r.cls)} · ${esc(r.n.cat)}${r.n.team ? ' · Team ' + esc(r.n.team) : ''}</small></div><em>${r.n.pos ? MEDAL[r.n.pos] : '🎖️'}</em></div>
      <div class="pv"></div><button class="open">🎓 Certificate dekho</button></div>`).join('') + (L.length > 120 ? '<p class="cnt">Aur dekhne ke liye naam likhiye.</p>' : '') + (!L.length ? '<p class="cnt">Koi nahi mila. Naam ki spelling badal kar dekhiye.</p>' : '');
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
  return (PNG[key] = await certPNG(dataOf(r, kids, r.n.photo ? await photo(r.n, false) : '', r.n.photoW ? await photo(r.n, true) : '')));
}
function rowOf(el) { return ROWS.find(x => x.sid === el.dataset.sid && x.n.id === el.dataset.id); }
function textOf(r) { return `🌟 I achieved this! 🌟\n${r.name} (Class ${r.cls}) ko ${r.n.ev.title} ki ${r.n.cat} pratiyogita mein ${r.n.pos ? POSN[r.n.pos] + ' Position 🏆' : 'participation certificate 🎉'} mila!\n${D.school}\n\nApna certificate yahan dekhiye 👉 ${LINK}`; }
function copyText(t) { try { if (navigator.clipboard && navigator.clipboard.writeText) { navigator.clipboard.writeText(t).catch(() => fbCopy(t)); return; } } catch (e) {} fbCopy(t); }
function fbCopy(t) { const ta = document.createElement('textarea'); ta.value = t; ta.style.position = 'fixed'; ta.style.opacity = '0'; document.body.appendChild(ta); ta.select(); try { document.execCommand('copy'); } catch (e) {} ta.remove(); }
function say(m) { let t = document.querySelector('.toast'); if (!t) { t = document.createElement('div'); t.className = 'toast'; document.body.appendChild(t); } t.textContent = m; t.classList.add('on'); clearTimeout(say.t); say.t = setTimeout(() => t.classList.remove('on'), 5000); }
function full(src) { const o = document.createElement('div'); o.className = 'full'; o.innerHTML = `<img src="${src}" alt=""><small>Band karne ke liye tap karein</small>`; o.onclick = () => o.remove(); document.body.appendChild(o); }

let T0 = 0;
$('q').addEventListener('input', () => { clearTimeout(T0); const v = $('q').value.replace(/\D/g, ''); IDS = null; $('msg').textContent = '';
  if (v.length >= 10 && /^[\d\s+()-]+$/.test($('q').value.trim())) T0 = setTimeout(findMobile, 300); else T0 = setTimeout(list, 120); });
$('f').addEventListener('submit', e => { e.preventDefault(); const v = $('q').value.replace(/\D/g, ''); if (v.length >= 10) findMobile(); else list(); $('q').blur(); setTimeout(() => $('res').scrollIntoView({ behavior: 'smooth', block: 'start' }), 80); });
async function findMobile() {
  const m = $('q').value.replace(/\D/g, '').slice(-10); $('msg').textContent = 'Dhoondh rahe hain…';
  try { const r = await fetch('cert_api.php?a=find', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ mobile: m }) }), j = await r.json().catch(() => null);
    if (!r.ok || !j || !j.ok) { $('msg').textContent = (j && j.error) || 'Server problem.'; IDS = []; } else { IDS = j.ids; $('msg').textContent = ''; } } catch (e) { $('msg').textContent = 'Internet nahi hai.'; IDS = null; }
  list();
}
document.addEventListener('click', async e => {
  const c = e.target.closest('#cls button'); if (c) { CLS = c.dataset.cls; document.querySelectorAll('#cls button').forEach(b => b.classList.toggle('on', b === c)); list(); return; }
  const b = e.target.closest('.row button'); if (!b) return; const card = b.closest('.row'), r = rowOf(card); if (!r) return;
  const fn = ('certificate-' + r.name + '-' + r.n.cat).replace(/[^a-z0-9]+/gi, '-').toLowerCase() + '.png';
  if (b.classList.contains('open')) { b.disabled = true; b.textContent = 'Ban raha hai…'; const png = await make(r);
    card.querySelector('.pv').innerHTML = `<img src="${png}" alt="Certificate">`; b.outerHTML = `<div class="bt"><button class="wa" data-a="share">💬 WhatsApp / Share</button><button data-a="dl">⬇️ Download</button><button data-a="cp">📋 Message copy</button></div><p class="hint">Photo ke saath message bhi jaata hai. Agar WhatsApp mein message na dikhe to wahan dabakar <b>Paste</b> kijiye — message pehle se copy hai.</p>`;
    card.querySelector('.pv img').onclick = () => full(png); return; }
  const a = b.dataset.a, png = await make(r);
  // the image itself + the message as caption (owner: "image share ho, message ke saath — link nahi"); the message is also copied in case an app drops it
  const txt = textOf(r); copyText(txt);
  if (a === 'cp') { say('Message copy ho gaya — WhatsApp mein dabakar Paste kijiye.'); return; }
  if (a === 'share') { try { const f = new File([await (await fetch(png)).blob()], fn, { type: 'image/png' });
    if (navigator.canShare && navigator.canShare({ files: [f] })) { await navigator.share({ files: [f], text: txt }); return; } } catch (er) { if (er && er.name === 'AbortError') return; }
    say('Is browser se photo seedha share nahi hoti — certificate download ho gaya. Gallery se WhatsApp par bhejiye; message copy hai, Paste kar dijiye. (Chrome mein link kholne se seedha share hota hai.)'); }
  const l = document.createElement('a'); l.href = png; l.download = fn; document.body.appendChild(l); l.click(); l.remove();
});
