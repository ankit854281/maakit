// Public certificate page (cert.php): find by the registered mobile number, draw each certificate (cert-draw.js), share / download.
'use strict';
const $ = id => document.getElementById(id);
const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
const LINK = location.origin + location.pathname;
const POSN = { 1: 'First', 2: 'Second', 3: 'Third' };
let DATA = null; const PNG = {};
setTimeout(() => { const i = $('intro'); if (i) { i.classList.add('out'); setTimeout(() => i.remove(), 700); } }, 2600);
const longDate = d => new Date(d + 'T00:00:00').toLocaleDateString('en-IN', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
const shortDate = d => new Date(d + 'T00:00:00').toLocaleDateString('en-IN', { weekday: 'short', day: '2-digit', month: 'short' }) + ' ' + d.slice(0, 4);

$('f').addEventListener('submit', async e => {
  e.preventDefault(); const m = $('mob').value.replace(/\D/g, '').slice(-10); const msg = $('msg');
  if (m.length !== 10) { msg.textContent = '10 ank ka mobile number likhiye.'; return; }
  msg.textContent = 'Dhoondh rahe hain…'; $('res').innerHTML = '';
  try {
    const r = await fetch('cert_api.php?a=find', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ mobile: m }) }), j = await r.json().catch(() => null);
    if (!r.ok || !j || !j.ok) { msg.textContent = (j && j.error) || 'Server problem. Dobara try karein.'; return; }
    DATA = j; msg.textContent = ''; show();
  } catch (er) { msg.textContent = 'Internet nahi hai. Dobara try karein.'; }
});
function dataOf(child, c) {
  return { school: DATA.school, event: c.ev.title, date: longDate(c.ev.date), cat: c.cat, sec: c.sec, pos: c.pos, cls: c.cls, name: child.name, team: c.team, member: !!c.team, members: '',
    teacher: c.teacher, principal: DATA.principal, photo: c.photo, photoW: c.photoW, signed: shortDate(c.ev.date), year: c.ev.date.slice(0, 4), no: certNo(c.ev.date, c.id, child.id),
    seal: DATA.school, tSign: c.tSign, pSign: DATA.pSign, kids: c.kids.map(k => ({ name: k.name, src: k.me ? child.src : '', me: k.me })) };
}
function show() {
  const L = DATA.children; const any = L.some(k => k.certs.length);
  $('res').innerHTML = L.map((k, ki) => `<section class="card kid"><div class="kh">${k.src ? `<img src="${k.src}" alt="">` : `<span>${esc(certInitials(k.name))}</span>`}<div><b>${esc(k.name)}</b><small>Class ${esc(k.cls)}</small></div></div>
    ${k.certs.map((c, ci) => `<div class="cert" id="c${ki}_${ci}"><div class="ct"><b>${esc(c.cat)}${c.team ? ' · Team ' + esc(c.team) : ''}</b><small>${esc(c.ev.title)} · ${c.pos ? '🏆 ' + POSN[c.pos] + ' Position' : 'Participation'}</small></div>
      <div class="pv"><div class="ld"><i></i>Certificate ban raha hai…</div></div>
      <div class="bt"><button class="go" data-a="share" data-k="${ki}" data-c="${ci}">📤 Share</button><button data-a="dl" data-k="${ki}" data-c="${ci}">⬇️ Download</button><button class="wa" data-a="wa" data-k="${ki}" data-c="${ci}">💬 WhatsApp</button></div></div>`).join('') || '<p class="none">Is bachche ka abhi koi certificate nahi hai.</p>'}</section>`).join('')
    + (any ? '<p class="tip">📤 Share dabakar seedha WhatsApp / Status par bhejiye. Download karke gallery mein bhi rakh sakte hain.</p>' : '');
  (async () => { for (let ki = 0; ki < L.length; ki++) for (let ci = 0; ci < L[ki].certs.length; ci++) { const png = await certPNG(dataOf(L[ki], L[ki].certs[ci])); PNG[ki + '_' + ci] = png; const pv = document.querySelector(`#c${ki}_${ci} .pv`); if (pv) pv.innerHTML = `<img src="${png}" alt="Certificate">`; } })();
}
function textOf(k, c) {
  const what = c.pos ? `${POSN[c.pos]} Position 🏆` : 'participation certificate 🎉';
  return `🌟 I achieved this! 🌟\n${k.name} (Class ${k.cls}) ko ${c.ev.title} ki ${c.cat} pratiyogita mein ${what} mila!\n${DATA.school}\n\nApna certificate yahan dekhiye 👉 ${LINK}`;
}
document.addEventListener('click', async e => {
  const b = e.target.closest('button[data-a]'); if (!b) return; const ki = +b.dataset.k, ci = +b.dataset.c, k = DATA.children[ki], c = k.certs[ci], key = ki + '_' + ci;
  const png = PNG[key] || (PNG[key] = await certPNG(dataOf(k, c))), fn = ('certificate-' + k.name + '-' + c.cat).replace(/[^a-z0-9]+/gi, '-').toLowerCase() + '.png';
  if (b.dataset.a === 'wa') { location.href = 'https://wa.me/?text=' + encodeURIComponent(textOf(k, c)); return; }
  if (b.dataset.a === 'share') {
    try { const f = new File([await (await fetch(png)).blob()], fn, { type: 'image/png' });
      if (navigator.canShare && navigator.canShare({ files: [f] })) { await navigator.share({ files: [f], text: textOf(k, c) }); return; } } catch (er) { if (er && er.name === 'AbortError') return; }
  }
  const a = document.createElement('a'); a.href = png; a.download = fn; document.body.appendChild(a); a.click(); a.remove();
  if (b.dataset.a === 'share') { const m = $('msg'); m.textContent = 'Is phone par seedha share nahi hota — certificate download ho gaya, gallery se WhatsApp par bhejiye.'; }
});
