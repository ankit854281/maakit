// ============================================================================
//  Teacher App — Competitions + certificates (owner, 6 Nov 2026: Rangoli & Diya making; sections PG–UKG, 1st–5th,
//  6th–10th; children's own TEAMS with a name, or a child alone; photo of the rangoli; positions; a certificate for
//  every child and for every team; teachers can share). Data: teacher_api.php?a=comp (kind tchComp). Loaded last.
// ============================================================================
'use strict';
let COMP = null; const CPH = {};
const COMP_DEF = { title: 'Diwali Competition ' + new Date().getFullYear(), cats: ['Rangoli', 'Diya making'], secs: [['PG – UKG', ['PG', 'LKG', 'UKG']], ['1st – 5th', ['1st', '2nd', '3rd', '4th', '5th']], ['6th – 10th', ['6th', '7th', '8th', '9th', '10th']]] };
function needComp(force) {
  if (COMP && !force && (COMP.loading || COMP.ok)) return COMP;
  COMP = Object.assign({}, COMP || {}, { loading: true });
  api('teacher_api.php?a=comp').then(r => { COMP = Object.assign(r, { ok: true }); softRender(); }).catch(e => { COMP = { err: e.message }; softRender(); });
  return COMP;
}
const POS = { '1': '🥇 1st', '2': '🥈 2nd', '3': '🥉 3rd' };
function vComp() {
  const C = needComp();
  if (C.err) return `<div class="card">${esc(C.err)} <button class="btn s l" onclick="COMP=null;render()">Try again</button></div>`;
  if (!C.ok) return '<div class="load"><i></i></div>';
  const ev = C.events.find(e => e.id === P.ev) || C.events[0];
  if (!ev) return isP() ? `<div class="card cp-new"><h3>🎉 Start a competition</h3><p class="mut" style="margin:0 0 8px">Ready to use — change anything if needed.</p>
      <div class="fld">Name<input id="cpT" class="inp" maxlength="120" value="${esc(COMP_DEF.title)}"></div><div class="fld">Date<input id="cpD" type="date" class="inp" value="${todayISO()}"></div>
      <div class="fld">Competitions<input id="cpC" class="inp" maxlength="120" value="${esc(COMP_DEF.cats.join(', '))}"></div>
      <p class="mut" style="margin:6px 0">Sections: ${COMP_DEF.secs.map(s => '<b>' + esc(s[0]) + '</b>').join(' · ')}</p>
      <button class="btn go full" onclick="compCreate()">Create competition</button></div>`
    : '<div class="card empty">No competition yet. The Principal starts it in the app (☰ → Competition).</div>';
  const cat = ev.cats.includes(P.cat) ? P.cat : ev.cats[0], sec = ev.secs.some(s => s.name === P.sec) ? P.sec : (ev.secs.find(s => s.classes.some(c => myClasses().includes(c))) || ev.secs[0]).name;
  const L = C.entries.filter(n => n.ev === ev.id && n.cat === cat && n.sec === sec).sort((a, b) => (a.pos || '9').localeCompare(b.pos || '9') || a.cls.localeCompare(b.cls) || (a.name || '').localeCompare(b.name || ''));
  const kids = n => n.sids.map(studentById).filter(Boolean);
  const nm = n => n.type === 'group' ? 'Team ' + n.name : (kids(n)[0] || {}).name || '—';
  const mine = n => isP() || myClasses().includes(n.cls);
  return `<div class="card cp-h"><small>${esc(fmtLong(ev.date))}</small><h3>🎉 ${esc(ev.title)}</h3>${C.events.length > 1 ? `<div class="chips">${C.events.map(e => `<button class="chip ${e.id === ev.id ? 'on' : ''}" onclick="go('comp',{ev:'${e.id}'})">${esc(e.title)}</button>`).join('')}</div>` : ''}</div>
    ${seg(ev.cats.map(c => [c, c]), cat, `(k=>go('comp',{ev:'${ev.id}',cat:k,sec:${jsq(sec)}}))`)}
    ${seg(ev.secs.map(s => [s.name, s.name]), sec, `(k=>go('comp',{ev:'${ev.id}',cat:${jsq(cat)},sec:k}))`)}
    <div class="btns"><button class="btn go" onclick="compAdd('${ev.id}',${jsq(cat)},${jsq(sec)},'group')">+ Team</button><button class="btn l" onclick="compAdd('${ev.id}',${jsq(cat)},${jsq(sec)},'solo')">+ Single child</button>${L.length ? `<button class="btn w" onclick="certPrintAll('${ev.id}',${jsq(cat)},${jsq(sec)})">${ic('print')} All certificates</button>` : ''}</div>
    <div class="card"><h3>${esc(cat)} · ${esc(sec)} <small>${L.length} entr${L.length === 1 ? 'y' : 'ies'}</small></h3>
      ${L.map(n => `<div class="cp-e ${n.pos ? 'win' : ''}"><div class="cp-ph" onclick="compPhoto('${ev.id}','${n.id}')">${n.photo ? `<img data-ph="${ev.id}|${n.id}" alt="">` : `<span>${ic('cam')}<small>Photo</small></span>`}</div>
        <div class="t"><b>${esc(nm(n))}</b><small>Class ${esc(n.cls)}${n.type === 'group' ? ' · ' + kids(n).map(s => esc(s.name.split(' ')[0])).join(', ') : ''}</small>
          <div class="cp-pos">${['1', '2', '3'].map(p => `<button class="${n.pos === p ? 'on' : ''}" onclick="compPos('${ev.id}','${n.id}','${n.pos === p ? '' : p}')">${POS[p]}</button>`).join('')}</div>
          <div class="btns"><button class="btn s l" onclick="certOpen('${ev.id}','${n.id}')">🎓 Certificate</button>${mine(n) ? `<button class="btn s w" onclick="compDel('${ev.id}','${n.id}')">✕</button>` : ''}</div></div></div>`).join('') || '<div class="empty">No team or child yet. Tap “+ Team” or “+ Single child”.</div>'}</div>
    <p class="mut">Positions: tap 🥇 / 🥈 / 🥉 (tap again to clear). Photo: tap the box. Certificates: one for the team and one for every child.</p>`;
}
async function compCreate() {
  const title = ($('cpT').value || '').trim(), date = $('cpD').value, cats = ($('cpC').value || '').split(',').map(x => x.trim()).filter(Boolean);
  try { await api('teacher_api.php?a=comp', { op: 'event', title, date, cats, secs: COMP_DEF.secs.map(([name, classes]) => ({ name, classes })) }); toast('Competition created. Teachers can add teams now.'); needComp(true); }
  catch (e) { toast(e.message); }
}
function compAdd(ev, cat, sec, type) {
  const E = COMP.events.find(e => e.id === ev), S2 = E.secs.find(s => s.name === sec), cls = S2.classes.filter(c => isP() || myClasses().includes(c));
  if (!cls.length) return toast('None of your classes is in ' + sec + '.');
  if (!DRAFT.ce || DRAFT.ce.k !== ev + cat + sec + type) DRAFT.ce = { k: ev + cat + sec + type, c: cls[0], sel: {}, name: '' };
  const D = DRAFT.ce, ph = needPhotos(D.c), taken = new Set(COMP.entries.filter(n => n.ev === ev && n.cat === cat).flatMap(n => n.sids));
  SHEET = () => `<h2>${type === 'group' ? '+ Team' : '+ Single child'} · ${esc(cat)}</h2>
    ${cls.length > 1 ? `<div class="chips">${cls.map(c => `<button class="chip ${c === D.c ? 'on' : ''}" onclick="DRAFT.ce.c=${jsq(c)};DRAFT.ce.sel={};compAdd('${ev}',${jsq(cat)},${jsq(sec)},'${type}')">${esc(c)}</button>`).join('')}</div>` : `<p class="mut" style="margin:0">Class ${esc(D.c)}</p>`}
    ${type === 'group' ? `<div class="fld">Team name<input id="ceN" class="inp" maxlength="60" value="${esc(D.name)}" placeholder="e.g. Roshni, Deepak, Rainbow" oninput="DRAFT.ce.name=this.value"></div><div class="chips">${['Roshni', 'Deepak', 'Rainbow', 'Tara', 'Kamal', 'Ujala'].map(x => `<button class="chip" onclick="$('ceN').value=${jsq(x)};DRAFT.ce.name=${jsq(x)}">${x}</button>`).join('')}</div>` : ''}
    <p class="mut" style="margin:6px 0 0">${type === 'group' ? 'Tap the children of this team' : 'Tap the children (each is entered alone)'} — grey = already in ${esc(cat)}.</p>
    <div class="pick">${studentsOf(D.c).map(s => `<button type="button" class="pk ${D.sel[s.id] ? 'on' : ''} ${taken.has(s.id) ? 'dim' : ''}" onclick="DRAFT.ce.sel[${jsq(s.id)}]=!DRAFT.ce.sel[${jsq(s.id)}];this.classList.toggle('on')"><span class="ph sm">${ph[s.id] ? `<img src="${esc(ph[s.id].src)}" alt="">` : `<i>${esc(initials(s.name))}</i>`}</span><b>${esc(s.name)}</b></button>`).join('')}</div>
    <button class="btn go full" onclick="compSave('${ev}',${jsq(cat)},'${type}')">Save</button>`;
  render();
}
async function compSave(ev, cat, type) {
  const D = DRAFT.ce, sids = Object.keys(D.sel).filter(k => D.sel[k]); if (!sids.length) return toast('Choose the children.');
  if (type === 'group' && !String(D.name).trim()) return toast('Write the team name.');
  try {
    if (type === 'group') await api('teacher_api.php?a=comp', { op: 'entry', ev, cat, type, name: D.name.trim(), sids });
    else for (const sid of sids) await api('teacher_api.php?a=comp', { op: 'entry', ev, cat, type: 'solo', sids: [sid] });
    DRAFT.ce = null; SHEET = null; toast('Saved.'); needComp(true);
  } catch (e) { toast(e.message); }
}
async function compPos(ev, id, pos) { try { const r = await api('teacher_api.php?a=comp', { op: 'pos', ev, id, pos }); const i = COMP.entries.findIndex(n => n.id === id); COMP.entries[i] = r.entry; render(); } catch (e) { toast(e.message); } }
async function compDel(ev, id) { if (!confirm('Remove this entry?')) return; try { await api('teacher_api.php?a=comp', { op: 'del', ev, id }); COMP.entries = COMP.entries.filter(n => n.id !== id); render(); } catch (e) { toast(e.message); } }
let CP_TARGET = null;
function compPhoto(ev, id) {
  CP_TARGET = [ev, id]; let inp = $('cpIn'); if (!inp) { inp = document.createElement('input'); inp.type = 'file'; inp.accept = 'image/*'; inp.capture = 'environment'; inp.id = 'cpIn'; inp.hidden = true; inp.onchange = () => compPhotoGot(inp); document.body.appendChild(inp); }
  inp.value = ''; inp.click();
}
async function compPhotoGot(inp) {
  const f = inp.files && inp.files[0]; if (!f || !CP_TARGET) return; const [ev, id] = CP_TARGET;
  try { toast('Saving the photo…'); const data = await new Promise((ok, bad) => { const img = new Image(), u = URL.createObjectURL(f); img.onload = () => { const m = 1100, r = Math.min(1, m / Math.max(img.width, img.height)), cv = document.createElement('canvas'); cv.width = Math.round(img.width * r); cv.height = Math.round(img.height * r); cv.getContext('2d').drawImage(img, 0, 0, cv.width, cv.height); URL.revokeObjectURL(u); let q = .8, o = cv.toDataURL('image/jpeg', q); while (o.length > 650000 && q > .35) { q -= .1; o = cv.toDataURL('image/jpeg', q); } ok(o); }; img.onerror = () => bad(new Error('This photo could not be opened.')); img.src = u; });
    const r = await api('teacher_api.php?a=comp', { op: 'photo', ev, id, data }); CPH[ev + '|' + id] = data; const i = COMP.entries.findIndex(n => n.id === id); COMP.entries[i] = r.entry; toast('Photo saved.'); render();
  } catch (e) { toast(e.message); }
}
async function phOf(ev, id) { const k = ev + '|' + id; if (CPH[k] !== undefined) return CPH[k]; try { const r = await api('teacher_api.php?a=comp&ph=' + encodeURIComponent(k)); CPH[k] = r.data || ''; } catch (e) { CPH[k] = ''; } return CPH[k]; }
function compThumbs() { document.querySelectorAll('img[data-ph]').forEach(async im => { if (im.src) return; const [ev, id] = im.dataset.ph.split('|'); const d = await phOf(ev, id); if (d) im.src = d; }); }

// ---------------------------------------------------------------- certificates (drawn on a canvas → PNG)
const LOGO_IMG = new Image(); LOGO_IMG.src = 'img/snmsis-logo.png';
function loadImg(src) { return new Promise(ok => { if (!src) return ok(null); const i = new Image(); i.onload = () => ok(i); i.onerror = () => ok(null); i.src = src; }); }
function wrapText(x, text, maxW) { const w = String(text).split(' '), out = []; let l = ''; w.forEach(t => { const tt = l ? l + ' ' + t : t; if (x.measureText(tt).width > maxW && l) { out.push(l); l = t; } else l = tt; }); if (l) out.push(l); return out; }
async function certPNG(o) {
  const W = 1600, H = 1130, cv = document.createElement('canvas'); cv.width = W; cv.height = H; const x = cv.getContext('2d');
  const win = !!o.pos, gold = '#C8901E', ink = '#17324D', acc = win ? '#B45309' : '#1D4ED8';
  x.fillStyle = '#FFFDF6'; x.fillRect(0, 0, W, H);
  const g = x.createLinearGradient(0, 0, W, H); g.addColorStop(0, '#F2A93B'); g.addColorStop(.5, '#E3417F'); g.addColorStop(1, '#6366F1');
  x.fillStyle = g; x.fillRect(0, 0, W, 26); x.fillRect(0, H - 26, W, 26); x.fillRect(0, 0, 26, H); x.fillRect(W - 26, 0, 26, H);
  x.strokeStyle = gold; x.lineWidth = 4; x.strokeRect(50, 50, W - 100, H - 100); x.lineWidth = 1.5; x.strokeRect(62, 62, W - 124, H - 124);
  if (LOGO_IMG.complete && LOGO_IMG.naturalWidth) x.drawImage(LOGO_IMG, W / 2 - 70, 82, 140, 140);
  x.textAlign = 'center'; x.fillStyle = ink; x.font = '700 40px Georgia, serif'; x.fillText(o.school || '', W / 2, 262);
  x.font = '22px Arial'; x.fillStyle = '#5B6782'; x.fillText(o.place || 'Bhadohi, Uttar Pradesh', W / 2, 296);
  x.fillStyle = acc; x.font = '700 58px Georgia, serif'; x.fillText(win ? 'CERTIFICATE OF ACHIEVEMENT' : 'CERTIFICATE OF PARTICIPATION', W / 2, 390);
  x.fillStyle = '#5B6782'; x.font = 'italic 28px Georgia, serif'; x.fillText(o.team && !o.member ? 'This is proudly presented to the team' : 'This is proudly presented to', W / 2, 452);
  x.fillStyle = ink; x.font = '700 66px Georgia, serif'; x.fillText(o.name, W / 2, 540);
  x.strokeStyle = gold; x.lineWidth = 2; x.beginPath(); x.moveTo(W / 2 - 360, 560); x.lineTo(W / 2 + 360, 560); x.stroke();
  let y = 606; x.font = '26px Arial'; x.fillStyle = '#334155';
  if (o.members) { wrapText(x, 'Members: ' + o.members, 1150).forEach(l => { x.fillText(l, W / 2, y); y += 36; }); }
  const line = `${o.cls ? 'of Class ' + o.cls + ' ' : ''}${o.member && o.team ? 'as a member of Team ' + o.team + ' ' : ''}${win ? 'for winning ' + ({ 1: 'FIRST', 2: 'SECOND', 3: 'THIRD' })[o.pos] + ' POSITION' : 'for taking part'} in the ${o.cat} competition (${o.sec})`;
  x.font = '30px Georgia, serif'; x.fillStyle = ink; wrapText(x, line, 1200).forEach(l => { x.fillText(l, W / 2, y + 14); y += 42; });
  x.font = '26px Arial'; x.fillStyle = '#5B6782'; x.fillText(`${o.event} · ${o.date}`, W / 2, y + 30);
  if (win) { x.beginPath(); x.arc(250, 820, 84, 0, Math.PI * 2); x.fillStyle = o.pos === '1' ? '#F5C842' : o.pos === '2' ? '#CBD5E1' : '#E3A06B'; x.fill(); x.lineWidth = 6; x.strokeStyle = '#fff'; x.stroke();
    x.fillStyle = ink; x.font = '700 72px Georgia, serif'; x.fillText(o.pos, 250, 838); x.font = '700 22px Arial'; x.fillText(({ 1: 'FIRST', 2: 'SECOND', 3: 'THIRD' })[o.pos], 250, 872); }
  const ph = await loadImg(o.photo); if (ph) { const s = Math.min(250 / ph.width, 170 / ph.height), w = ph.width * s, h = ph.height * s; x.save(); x.translate(W - 260, 800); x.rotate(.04); x.fillStyle = '#fff'; x.fillRect(-w / 2 - 10, -h / 2 - 10, w + 20, h + 46); x.drawImage(ph, -w / 2, -h / 2, w, h); x.fillStyle = '#5B6782'; x.font = '18px Arial'; x.fillText(o.cat, 0, h / 2 + 26); x.restore(); }
  x.strokeStyle = '#94A3B8'; x.lineWidth = 2; [[360, o.teacher || 'Class Teacher', 'Class Teacher'], [W - 360, o.principal, 'Principal']].forEach(([cx, n, r]) => { x.beginPath(); x.moveTo(cx - 170, H - 170); x.lineTo(cx + 170, H - 170); x.stroke(); x.fillStyle = ink; x.font = '700 24px Arial'; x.fillText(n, cx, H - 138); x.fillStyle = '#5B6782'; x.font = '20px Arial'; x.fillText(r, cx, H - 110); });
  x.fillStyle = '#94A3B8'; x.font = '16px Arial'; x.fillText('Powered by Maakit', W / 2, H - 76);
  return cv.toDataURL('image/png');
}
async function certData(evId, id, sid) {
  const E = COMP.events.find(e => e.id === evId), n = COMP.entries.find(z => z.id === id), kids = n.sids.map(studentById).filter(Boolean), k = sid ? studentById(sid) : null;
  const ct = (S.classes.find(c => c.c === n.cls) || {}).ct;
  return { school: S.settings.school || 'SN Mishra Singapore International School', event: E.title, date: fmtLong(E.date), cat: n.cat, sec: n.sec, pos: n.pos, cls: n.cls,
    name: k ? k.name : n.type === 'group' ? 'Team ' + n.name : (kids[0] || {}).name, team: n.type === 'group' ? n.name : '', member: !!k && n.type === 'group',
    members: !k && n.type === 'group' ? kids.map(s => s.name).join(', ') : '', teacher: ct ? T(ct).name : '', principal: (S.teachers.P || {}).name || '', photo: n.photo ? await phOf(evId, id) : '' };
}
async function certOpen(evId, id) {
  const n = COMP.entries.find(z => z.id === id), kids = n.sids.map(studentById).filter(Boolean);
  SHEET = () => `<h2>🎓 Certificates</h2><div class="cert-pv"><div class="load"><i></i>Making the certificate…</div></div>
    ${n.type === 'group' ? `<p class="mut" style="margin:0">Upar team ka certificate. Har bachche ka:</p><div class="chips">${kids.map(s => `<button class="chip" onclick="certShow('${evId}','${id}',${jsq(s.id)})">${esc(s.name)}</button>`).join('')}</div>
      <button class="btn w full" onclick="certPrintEntry('${evId}','${id}')">${ic('print')} Print team + every child</button>` : ''}`;
  render(); certShow(evId, id, '');
}
async function certShow(evId, id, sid) {
  const png = await certPNG(await certData(evId, id, sid)), box = document.querySelector('.cert-pv'); if (!box) return;
  const n = COMP.entries.find(z => z.id === id), fn = ('certificate-' + (sid ? (studentById(sid) || {}).name : n.type === 'group' ? 'team-' + n.name : (studentById(n.sids[0]) || {}).name) + '-' + n.cat).replace(/[^a-z0-9]+/gi, '-').toLowerCase() + '.png';
  window.__CERT = { png, fn };
  box.innerHTML = `<img src="${png}" alt="Certificate"><div class="btns"><button class="btn wa" onclick="certShare()">${ic('wa')} Share</button><a class="btn l" href="${png}" download="${esc(fn)}">Download</a><button class="btn w" onclick="certPrint([window.__CERT.png])">${ic('print')} Print</button></div>`;
}
async function certShare() {
  const c = window.__CERT; if (!c) return;
  if (window.SNMSISFiles && SNMSISFiles.shareImage) return SNMSISFiles.shareImage(c.fn, c.png.split(',')[1]);
  try { const blob = await (await fetch(c.png)).blob(), file = new File([blob], c.fn, { type: 'image/png' });
    if (navigator.canShare && navigator.canShare({ files: [file] })) return await navigator.share({ files: [file], title: 'Certificate' });
  } catch (e) { if (e && e.name === 'AbortError') return; }
  toast('Sharing a picture does not work here — tap Download, then send it on WhatsApp. (Install the new Teacher App to share directly.)');
}
function certPrint(list) { let p = document.querySelector('.prt'); if (!p) { p = document.createElement('div'); p.className = 'prt'; document.body.appendChild(p); }
  p.innerHTML = list.map(src => `<img src="${src}" style="width:100%;display:block;page-break-after:always">`).join(''); setTimeout(() => window.print(), 300); }
async function certPrintEntry(evId, id) { const n = COMP.entries.find(z => z.id === id); toast('Making the certificates…'); const L = [await certPNG(await certData(evId, id, ''))]; for (const sid of n.sids) L.push(await certPNG(await certData(evId, id, sid))); certPrint(L); }
async function certPrintAll(evId, cat, sec) {
  const L = COMP.entries.filter(n => n.ev === evId && n.cat === cat && n.sec === sec), out = []; if (!L.length) return; toast('Making ' + L.reduce((a, n) => a + n.sids.length + (n.type === 'group' ? 1 : 0), 0) + ' certificates…');
  for (const n of L) { if (n.type === 'group') out.push(await certPNG(await certData(evId, n.id, ''))); for (const sid of n.sids) out.push(await certPNG(await certData(evId, n.id, n.type === 'group' ? sid : ''))); }
  certPrint(out);
}
(function () {
  const _render = render; render = function () { _render(); if (S && TAB === 'comp') compThumbs(); };
  const _go = go; go = function (tab, p) { if (tab === 'comp') DRAFT.ce = null; return _go(tab, p); };
  if (typeof MENU !== 'undefined') MENU.forEach(g => { if (g[0] === 'Students') { const i = g[1].findIndex(x => x[3] === 'Competition'); const it = ['comp', {}, 'chart', 'Competition', 'Team, photo, jeetne wale, certificate']; if (i >= 0) g[1][i] = it; else g[1].unshift(it); } });
})();
