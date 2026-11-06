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
  api('teacher_api.php?a=comp').then(r => { COMP = Object.assign(r, { ok: true, at: Date.now() }); softRender(); }).catch(e => { COMP = { err: e.message }; softRender(); });
  return COMP;
}
const POS = { '1': '🥇 1st', '2': '🥈 2nd', '3': '🥉 3rd' };
// Result (owner, 6 Nov 2026): only the Principal picks 🥇🥈🥉 and taps "Submit result"; then "Edit result" opens it again.
// Teachers see the positions (and certificates carry them) only after the result is submitted.
function resOf(ev, cat, sec) { return ev && (ev.res || {})[cat + '|' + sec]; }
function posOf(n) { const E = COMP && COMP.events && COMP.events.find(e => e.id === n.ev); return isP() || resOf(E, n.cat, n.sec) ? n.pos || '' : ''; }
async function compRes(ev, cat, sec, lock) {
  if (lock && !COMP.entries.some(n => n.ev === ev && n.cat === cat && n.sec === sec && n.pos) && !confirm('Abhi koi position nahi chuni. Phir bhi submit karein?')) return;
  try { const r = await api('teacher_api.php?a=comp', { op: 'res', ev, cat, sec, lock }); const i = COMP.events.findIndex(e => e.id === ev); COMP.events[i] = r.event; toast(lock ? 'Result submit ho gaya ✓ Teachers ko positions dikhne lagi.' : 'Ab positions badal sakte hain — phir Submit result dabaiye.'); render(); } catch (e) { toast(e.message); }
}
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
  const L = C.entries.filter(n => n.ev === ev.id && n.cat === cat && n.sec === sec).sort((a, b) => (posOf(a) || '9').localeCompare(posOf(b) || '9') || a.cls.localeCompare(b.cls) || (a.name || '').localeCompare(b.name || ''));
  const kids = n => n.sids.map(studentById).filter(Boolean);
  const nm = n => n.type === 'group' ? 'Team ' + n.name : (kids(n)[0] || {}).name || '—';
  const mine = n => isP() || myClasses().includes(n.cls);
  const done = resOf(ev, cat, sec), canPick = isP() && !done;
  return `<div class="card cp-h"><small>${esc(fmtLong(ev.date))}</small><h3>🎉 ${esc(ev.title)}</h3>${C.events.length > 1 ? `<div class="chips">${C.events.map(e => `<button class="chip ${e.id === ev.id ? 'on' : ''}" onclick="go('comp',{ev:'${e.id}'})">${esc(e.title)}</button>`).join('')}</div>` : ''}</div>
    <div class="btns cp-tools"><button class="btn s l" onclick="signOpen()">✍️ Mera sign${SIGNS && SIGNS[mySignKey()] ? ' ✓' : ''}</button><button class="btn s l" onclick="newsOpen()">🤖 Kaise karein?</button><button class="btn s wa" onclick="compLink()">🔗 Parents ke liye link</button></div>
    ${seg(ev.cats.map(c => [c, c]), cat, `(k=>go('comp',{ev:'${ev.id}',cat:k,sec:${jsq(sec)}}))`)}
    ${seg(ev.secs.map(s => [s.name, s.name]), sec, `(k=>go('comp',{ev:'${ev.id}',cat:${jsq(cat)},sec:k}))`)}
    <div class="btns"><button class="btn go" onclick="compAdd('${ev.id}',${jsq(cat)},${jsq(sec)},'group')">+ Team</button><button class="btn l" onclick="compAdd('${ev.id}',${jsq(cat)},${jsq(sec)},'solo')">+ Single child</button>${L.length ? `<button class="btn w" onclick="certPrintAll('${ev.id}',${jsq(cat)},${jsq(sec)})">${ic('print')} All certificates</button>` : ''}</div>
    <div class="card"><h3>${esc(cat)} · ${esc(sec)} <small>${L.length} entr${L.length === 1 ? 'y' : 'ies'}</small></h3>
      ${L.map(n => `<div class="cp-e ${posOf(n) ? 'win' : ''}"><div class="cp-phs"><div class="cp-ph" onclick="compPhoto('${ev.id}','${n.id}','w')">${n.photoW ? `<img data-ph="${ev.id}|${n.id}|w" alt="">` : `<span>${/diya|deep|lamp/i.test(n.cat) ? '🪔' : '🎨'}<small>${esc(n.cat.split(' ')[0])}</small></span>`}</div><div class="cp-ph sm" onclick="compPhoto('${ev.id}','${n.id}','')">${n.photo ? `<img data-ph="${ev.id}|${n.id}|" alt="">` : `<span>👫<small>Team</small></span>`}</div></div>
        <div class="t"><b>${esc(nm(n))}</b><small>Class ${esc(n.cls)}${n.type === 'group' ? ' · ' + kids(n).map(s => esc(s.name.split(' ')[0])).join(', ') : ''}</small>
          ${canPick ? `<div class="cp-pos">${['1', '2', '3'].map(p => `<button class="${n.pos === p ? 'on' : ''}" onclick="compPos('${ev.id}','${n.id}','${n.pos === p ? '' : p}')">${POS[p]}</button>`).join('')}</div>` : posOf(n) ? `<div class="cp-pos"><span class="cp-won">${POS[posOf(n)]} Position</span></div>` : ''}
          <div class="btns"><button class="btn s l" onclick="certOpen('${ev.id}','${n.id}')">🎓 Certificate</button>${mine(n) ? `<button class="btn s w" onclick="compDel('${ev.id}','${n.id}')">✕</button>` : ''}</div></div></div>`).join('') || '<div class="empty">No team or child yet. Tap “+ Team” or “+ Single child”.</div>'}</div>
    ${done ? `<div class="card cp-res ok"><b>✅ Result submitted</b><small>${esc(done.by || '')} · ${esc(fmtDate(new Date(done.at).toISOString().slice(0, 10)))}</small>${isP() ? `<button class="btn l full" onclick="compRes('${ev.id}',${jsq(cat)},${jsq(sec)},false)">✏️ Edit result</button>` : ''}</div>`
      : isP() ? (L.length ? `<button class="btn go full cp-sub" onclick="compRes('${ev.id}',${jsq(cat)},${jsq(sec)},true)">✅ Submit result · ${esc(cat)} · ${esc(sec)}</button>` : '')
      : `<div class="card cp-res"><b>🏆 Position Principal sir chunenge</b><small>Result submit hote hi yahan aur certificate par position aa jayegi.</small></div>`}
    <p class="mut">Positions: ${isP() ? 'tap 🥇 / 🥈 / 🥉 (tap again to clear), then “Submit result”. To change later tap “Edit result”.' : 'only the Principal chooses them.'} Photos: big box 🎨 = rangoli / diya, small box 👫 = the team. Certificates: one for the team and one for every child.</p>`;
}
// Public page where parents type their registered mobile number and get / share their child's certificates (cert.php).
function compLink() { const u = location.origin + location.pathname.replace(/[^/]*$/, '') + 'cert.php'; window.__CT = [`🏆 *${S.settings.school || 'School'} — Competition Certificates*\n\nApne bachche ka certificate yahan dekhiye, download kijiye aur share kijiye 👇\n${u}\n\nWahi mobile number daaliye jo school mein diya hai. 🎉`]; certCopy(0); }
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
  // Absent / leave children of the competition day are hidden (owner, 6 Nov 2026); "Dikhao" brings them back if the attendance was wrong.
  const att = attOf(E.date, D.c), away = new Set((att && att.absent) || []), all = studentsOf(D.c), list = all.filter(s => D.showAbs || !away.has(s.id) || D.sel[s.id]), hid = all.length - list.length;
  SHEET = () => `<h2>${type === 'group' ? '+ Team' : '+ Single child'} · ${esc(cat)}</h2>
    ${cls.length > 1 ? `<div class="chips">${cls.map(c => `<button class="chip ${c === D.c ? 'on' : ''}" onclick="DRAFT.ce.c=${jsq(c)};DRAFT.ce.sel={};compAdd('${ev}',${jsq(cat)},${jsq(sec)},'${type}')">${esc(c)}</button>`).join('')}</div>` : `<p class="mut" style="margin:0">Class ${esc(D.c)}</p>`}
    ${type === 'group' ? `<div class="fld">Team name<input id="ceN" class="inp" maxlength="60" value="${esc(D.name)}" placeholder="e.g. Roshni, Deepak, Rainbow" oninput="DRAFT.ce.name=this.value"></div><div class="chips">${['Roshni', 'Deepak', 'Rainbow', 'Tara', 'Kamal', 'Ujala'].map(x => `<button class="chip" onclick="$('ceN').value=${jsq(x)};DRAFT.ce.name=${jsq(x)}">${x}</button>`).join('')}</div>` : ''}
    <p class="mut" style="margin:6px 0 0">${type === 'group' ? 'Tap the children of this team' : 'Tap the children (each is entered alone)'} — grey = already in ${esc(cat)}.</p>
    ${hid ? `<p class="cp-abs">🚫 ${hid} bachche aaj absent / leave hain — list mein nahi dikh rahe. <button class="chip" onclick="DRAFT.ce.showAbs=true;compAdd('${ev}',${jsq(cat)},${jsq(sec)},'${type}')">Dikhao</button></p>` : !att ? `<p class="cp-abs">Class ${esc(D.c)} ki haziri ${E.date === todayISO() ? 'aaj abhi' : 'us din'} nahi lagi — sab bachche dikh rahe hain.</p>` : ''}
    <div class="pick">${list.map(s => `<button type="button" class="pk ${D.sel[s.id] ? 'on' : ''} ${taken.has(s.id) ? 'dim' : ''}" onclick="DRAFT.ce.sel[${jsq(s.id)}]=!DRAFT.ce.sel[${jsq(s.id)}];this.classList.toggle('on')"><span class="ph sm">${ph[s.id] ? `<img src="${esc(ph[s.id].src)}" alt="">` : `<i>${esc(initials(s.name))}</i>`}</span><b>${esc(s.name)}</b>${away.has(s.id) ? '<em>Absent</em>' : ''}</button>`).join('')}</div>
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
function compPhoto(ev, id, kind) {
  CP_TARGET = [ev, id, kind || '']; let inp = $('cpIn'); if (!inp) { inp = document.createElement('input'); inp.type = 'file'; inp.accept = 'image/*'; inp.capture = 'environment'; inp.id = 'cpIn'; inp.hidden = true; inp.onchange = () => compPhotoGot(inp); document.body.appendChild(inp); }
  inp.value = ''; inp.click();
}
async function compPhotoGot(inp) {
  const f = inp.files && inp.files[0]; if (!f || !CP_TARGET) return; const [ev, id, kind] = CP_TARGET;
  try { toast('Saving the photo…'); const data = await new Promise((ok, bad) => { const img = new Image(), u = URL.createObjectURL(f); img.onload = () => { const m = 1100, r = Math.min(1, m / Math.max(img.width, img.height)), cv = document.createElement('canvas'); cv.width = Math.round(img.width * r); cv.height = Math.round(img.height * r); cv.getContext('2d').drawImage(img, 0, 0, cv.width, cv.height); URL.revokeObjectURL(u); let q = .8, o = cv.toDataURL('image/jpeg', q); while (o.length > 650000 && q > .35) { q -= .1; o = cv.toDataURL('image/jpeg', q); } ok(o); }; img.onerror = () => bad(new Error('This photo could not be opened.')); img.src = u; });
    const r = await api('teacher_api.php?a=comp', { op: 'photo', ev, id, data, kind }); CPH[ev + '|' + id + '|' + kind] = data; const i = COMP.entries.findIndex(n => n.id === id); COMP.entries[i] = r.entry; toast('Photo saved.'); render();
  } catch (e) { toast(e.message); }
}
async function phOf(ev, id, kind) { kind = kind || ''; const k = ev + '|' + id + '|' + kind; if (CPH[k] !== undefined) return CPH[k]; try { const r = await api('teacher_api.php?a=comp&ph=' + encodeURIComponent(ev + '|' + id) + (kind ? '&k=' + kind : '')); CPH[k] = r.data || ''; } catch (e) { CPH[k] = ''; } return CPH[k]; }
function compThumbs() { document.querySelectorAll('img[data-ph]').forEach(async im => { if (im.src) return; const [ev, id, kind] = im.dataset.ph.split('|'); const d = await phOf(ev, id, kind); if (d) im.src = d; }); }

async function certData(evId, id, sid) {
  const SG = await needSigns(); const n0 = COMP.entries.find(z => z.id === id);
  if (n0 && !Object.keys(PHOTOS[n0.cls] || {}).length) { try { PHOTOS[n0.cls] = (await api('teacher_api.php?a=photos&cls=' + encodeURIComponent(n0.cls))).photos || {}; } catch (e) {} }
  const E = COMP.events.find(e => e.id === evId), n = COMP.entries.find(z => z.id === id), kids = n.sids.map(studentById).filter(Boolean), k = sid ? studentById(sid) : null;
  const ct = (S.classes.find(c => c.c === n.cls) || {}).ct;
  return { school: S.settings.school || 'SN Mishra Singapore International School', event: E.title, date: fmtLong(E.date), cat: n.cat, sec: n.sec, pos: posOf(n), cls: n.cls,
    name: k ? k.name : n.type === 'group' ? 'Team ' + n.name : (kids[0] || {}).name, team: n.type === 'group' ? n.name : '', member: !!k && n.type === 'group',
    members: !k && n.type === 'group' ? kids.map(s => s.name).join(', ') : '', teacher: ct ? T(ct).name : '', principal: (S.teachers.P || {}).name || '', photo: n.photo ? await phOf(evId, id) : '', signed: fmtDate(E.date) + ' ' + E.date.slice(0, 4), year: E.date.slice(0, 4), no: certNo(E.date, id, sid), seal: S.settings.school || '', tSign: ct ? SG[ct] || '' : '', pSign: SG.P || '', photoW: n.photoW ? await phOf(evId, id, 'w') : '', kids: kids.map(s => ({ name: s.name, src: ((PHOTOS[n.cls] || {})[s.id] || {}).src || '', me: s.id === sid })) };
}
async function certOpen(evId, id) {
  const n = COMP.entries.find(z => z.id === id), kids = n.sids.map(studentById).filter(Boolean);
  SHEET = () => `<h2>🎓 Certificates</h2><div class="cert-pv"><div class="load"><i></i>Making the certificate…</div></div>
    ${n.type === 'group' ? `<p class="mut" style="margin:0">Upar team ka certificate. Har bachche ka:</p><div class="chips">${kids.map(s => `<button class="chip" onclick="certShow('${evId}','${id}',${jsq(s.id)})">${esc(s.name)}</button>`).join('')}</div>
      <button class="btn w full" onclick="certPrintEntry('${evId}','${id}')">${ic('print')} Print team + every child</button>` : ''}`;
  render(); certShow(evId, id, '');
}
async function certShow(evId, id, sid) {
  const o = await certData(evId, id, sid), png = await certPNG(o), box = document.querySelector('.cert-pv'); if (!box) return;
  const n = COMP.entries.find(z => z.id === id), fn = ('certificate-' + (sid ? (studentById(sid) || {}).name : n.type === 'group' ? 'team-' + n.name : (studentById(n.sids[0]) || {}).name) + '-' + n.cat).replace(/[^a-z0-9]+/gi, '-').toLowerCase() + '.png';
  window.__CERT = { png, fn, o };
  const kids = (sid ? [sid] : n.sids).map(studentById).filter(Boolean), caps = certCaps(o), old = oldApp();
  window.__CT = [...kids.map(k => certMsg(o, k)), ...caps];
  box.innerHTML = `<img src="${png}" alt="Certificate" onclick="certFull()">
    <div class="cs-step"><b>1 · Certificate bhejiye</b>${old
      ? `<button class="btn wa full" onclick="certFull()">📸 Bada karke screenshot lein</button><small>Bada hone par phone ka screenshot lijiye, phir WhatsApp par bhejiye.</small>`
      : `<div class="btns"><button class="btn wa" onclick="certShare()">${ic('wa')} Share</button><a class="btn l" href="${png}" download="${esc(fn)}">Download</a><button class="btn w" onclick="certPrint([window.__CERT.png])">${ic('print')} Print</button></div>`}</div>
    <div class="cs-step"><b>2 · Parent ko message</b><small>Kisko: bachche ke mummy-papa ko (school record wala number). Pehle certificate bhejiye, phir yeh message.</small>
      ${kids.map((k, i) => `<div class="cs-row"><div><b>${esc(k.name)}</b><small>${k.phone ? '📞 ' + esc(String(k.phone).slice(-10)) : 'Number save nahi hai'}</small></div>
        ${k.phone && !old ? `<a class="btn wa s" target="_blank" rel="noopener" href="${wa(k.phone, window.__CT[i])}">${ic('wa')} Bhejo</a>` : ''}<button class="btn l s" onclick="certCopy(${i})">Copy</button></div>`).join('')}
      <details class="cs-msg"><summary>Message dekhiye</summary><pre>${esc(window.__CT[0] || '')}</pre></details></div>
    <div class="cs-step"><b>3 · WhatsApp Status / Story</b><small>Story wali photo banaiye → screenshot / share → WhatsApp → Status → photo chuniye → neeche ka caption paste kijiye.</small>
      <button class="btn go full" onclick="certStoryOpen()">✨ Story photo banao</button>
      ${caps.map((c, j) => `<div class="cs-cap"><p>${esc(c)}</p><button class="btn l s" onclick="certCopy(${kids.length + j})">Copy</button></div>`).join('')}</div>`;
}
function certCopy(i) { const t = (window.__CT || [])[i]; if (!t) return; const done = () => toast('Copy ho gaya — WhatsApp mein dabakar rakhiye → Paste.');
  const fb = () => { const ta = document.createElement('textarea'); ta.value = t; ta.style.position = 'fixed'; ta.style.opacity = '0'; document.body.appendChild(ta); ta.select(); try { document.execCommand('copy'); done(); } catch (e) { toast('Copy nahi hua — message dabakar rakhiye aur copy kijiye.'); } ta.remove(); };
  if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(t).then(done, fb); else fb(); }
function certMsg(o, k) {
  const by = `\n\n— ${myName()}\n${o.school}`, team = o.team ? ` ne *Team ${o.team}* ke saath` : ' ne';
  return o.pos
    ? `Namaste 🙏\nBadhai ho! 🎉 Aapke bachche *${k.name}* (Class ${o.cls})${team} *${o.event}* ki *${o.cat}* pratiyogita mein *${POSE[o.pos]} ${POSW[o.pos]} position* jeeti hai 🏆\nBachche ki mehnat aur creativity par poore school ko garv hai. Certificate saath mein bheja hai — ghar par zaroor shabashi dijiye 💐${by}`
    : `Namaste 🙏\nAapke bachche *${k.name}* (Class ${o.cls})${team} *${o.event}* ki *${o.cat}* pratiyogita mein bahut sundar bhaag liya 🎨✨\nHar bachcha apne aap mein ek kalakaar hai — aise hi aage badhte rahein. Participation certificate saath mein bheja hai, ghar par zaroor shabashi dijiye 💐${by}`;
}
function certCaps(o) {
  const nm = o.name, yr = o.year, tag = `#SNMSIS #${String(o.event).replace(/[^A-Za-z0-9]/g, '') || 'Diwali' + yr}`;
  return o.pos ? [`🏆 Proud moment! ${nm} (Class ${o.cls}) ne ${o.cat} pratiyogita mein ${POSE[o.pos]} ${POSW[o.pos]} position jeeti! Mehnat + creativity = jeet ✨ ${tag}`,
      `Jeet unki hoti hai jo dil se mehnat karte hain 💪 Bahut bahut badhai ${nm}! 🎉 — ${o.school}`,
      `🪔 Chhote haath, bade sapne! ${o.event} mein hamare ${nm} ka kamaal 🌟 ${tag}`]
    : [`🎨 Har bachcha ek kalakaar! ${nm} ne ${o.cat} pratiyogita mein sundar bhaag liya ✨ ${tag}`,
      `Jeetna zaroori nahi, koshish karna zaroori hai 🌟 Shabash ${nm}! — ${o.school}`,
      `🪔 Diwali ki roshni, bachchon ki creativity — ${o.event} ki khoobsurat yaadein 💛 ${tag}`];
}
async function certStoryOpen() {
  const c = window.__CERT; if (!c) return; toast('Story photo ban rahi hai…'); const st = await certStory(c.png, c.o), sfn = c.fn.replace(/\.png$/, '-story.png');
  if (!oldApp()) { if (window.SNMSISFiles && SNMSISFiles.shareImage) return SNMSISFiles.shareImage(sfn, st.split(',')[1]);
    try { const f = new File([await (await fetch(st)).blob()], sfn, { type: 'image/png' }); if (navigator.canShare && navigator.canShare({ files: [f] })) return await navigator.share({ files: [f] }); } catch (e) { if (e && e.name === 'AbortError') return; } }
  certFull(st);
}
async function certShare() {
  const c = window.__CERT; if (!c) return;
  if (window.SNMSISFiles && SNMSISFiles.shareImage) return SNMSISFiles.shareImage(c.fn, c.png.split(',')[1]);
  try { const blob = await (await fetch(c.png)).blob(), file = new File([blob], c.fn, { type: 'image/png' });
    if (navigator.canShare && navigator.canShare({ files: [file] })) return await navigator.share({ files: [file], title: 'Certificate' });
  } catch (e) { if (e && e.name === 'AbortError') return; }
  certFull();
}
// The installed Teacher App 1.0 cannot hand a picture to WhatsApp (no share bridge, links blocked). Owner, 6 Nov 2026:
// teachers must NOT have to download a new app — so the certificate opens full screen for a phone screenshot instead.
function oldApp() { return !!(window.SNMSISFiles && !SNMSISFiles.shareImage); }
function certFull(src) {
  const img = typeof src === 'string' ? src : (window.__CERT || {}).png; if (!img) return; let o = $('certFull'); if (o) o.remove();
  o = document.createElement('div'); o.id = 'certFull'; o.className = 'cert-full'; o.onclick = () => o.remove();
  o.innerHTML = `<img src="${img}" alt="Certificate"><div class="cf-tip">Ab screenshot lijiye 📸 · band karne ke liye tap karein</div>`;
  document.body.appendChild(o); setTimeout(() => { const t = o.querySelector('.cf-tip'); if (t) t.classList.add('gone'); }, 2600);
}
function certPrint(list) { let p = document.querySelector('.prt'); if (!p) { p = document.createElement('div'); p.className = 'prt'; document.body.appendChild(p); }
  p.innerHTML = list.map(src => `<img src="${src}" style="width:100%;display:block;page-break-after:always">`).join(''); setTimeout(() => window.print(), 300); }
async function certPrintEntry(evId, id) { const n = COMP.entries.find(z => z.id === id); toast('Making the certificates…'); const L = [await certPNG(await certData(evId, id, ''))]; for (const sid of n.sids) L.push(await certPNG(await certData(evId, id, sid))); certPrint(L); }
async function certPrintAll(evId, cat, sec) {
  const L = COMP.entries.filter(n => n.ev === evId && n.cat === cat && n.sec === sec), out = []; if (!L.length) return; toast('Making ' + L.reduce((a, n) => a + n.sids.length + (n.type === 'group' ? 1 : 0), 0) + ' certificates…');
  for (const n of L) { if (n.type === 'group') out.push(await certPNG(await certData(evId, n.id, ''))); for (const sid of n.sids) out.push(await certPNG(await certData(evId, n.id, n.type === 'group' ? sid : ''))); }
  certPrint(out);
}

function signCard() { if (!isP()) return ''; const sg = SIGNS ? SIGNS.P : undefined; if (SIGNS === null) needSigns().then(() => softRender());
  return `<div class="card sg-card"><div class="cp-dh"><span>✍️</span><div><small>Certificates ke liye</small><b>${sg ? 'Aapka sign lag gaya ✓' : 'Apna sign lagaiye'}</b></div>${sg ? `<img src="${sg}" alt="">` : ''}</div>
    <button class="btn ${sg ? 'l' : 'go'} full" onclick="signOpen()">${sg ? 'Sign badlo' : '✍️ Sign kijiye (ungli se)'}</button></div>`; }
// Dashboard card (owner, 6 Nov 2026: "teacher hi group banayenge — Principal aur teacher ke dashboard mein add karo").
// Shown from 2 days before to 3 days after the competition: teachers see their classes' teams, the Principal sees which classes have not added any.
function compCard() {
  const C = needComp(); if (!C.ok) return '';
  const t = todayISO(), dd = (a, b) => Math.round((new Date(a + 'T00:00:00') - new Date(b + 'T00:00:00')) / 864e5);
  const ev = C.events.find(e => dd(e.date, t) <= 2 && dd(t, e.date) <= 3); if (!ev) return '';
  const all = ev.secs.flatMap(s => s.classes), mine = isP() && pMode() ? all : all.filter(c => myClasses().includes(c)); if (!mine.length) return '';
  const E = C.entries.filter(n => n.ev === ev.id), cnt = c => E.filter(n => n.cls === c).length, kids = E.filter(n => mine.includes(n.cls)).reduce((a, n) => a + n.sids.length, 0);
  const none = mine.filter(c => !cnt(c)), wins = E.filter(n => posOf(n) && mine.includes(n.cls)).length;
  return `<div class="card cp-dash" onclick="go('comp')"><div class="cp-dh"><span>🪔</span><div><small>${ev.date === t ? 'Aaj' : esc(fmtDate(ev.date))} · ${ev.cats.map(esc).join(' + ')}</small><b>${esc(ev.title)}</b></div></div>
    <div class="cp-dn"><div><b>${E.filter(n => mine.includes(n.cls)).length}</b><small>teams / entries</small></div><div><b>${kids}</b><small>bachche</small></div><div><b>${wins}</b><small>winners</small></div></div>
    <div class="cp-dc">${mine.map(c => `<span class="${cnt(c) ? 'ok' : 'no'}">${esc(c)} ${cnt(c) ? '✓' + cnt(c) : '—'}</span>`).join('')}</div>
    ${none.length ? `<p class="cp-dw">${isP() && pMode() ? 'Abhi team nahi bani: ' + none.map(esc).join(', ') : 'Apni class ki team banaiye: ' + none.map(esc).join(', ')}</p>` : ''}
    <button class="btn go full" onclick="event.stopPropagation();go('comp')">${isP() && pMode() ? 'Competition kholo' : '+ Team banao / certificate'}</button></div>`;
}

// ---------------------------------------------------------------- signatures drawn on the phone (owner, 6 Nov 2026: "Principal sir sign kaise karenge")
let SIGNS = null;
async function needSigns(force) { if (SIGNS && !force) return SIGNS; try { SIGNS = (await api('teacher_api.php?a=comp&sg=1')).signs || {}; } catch (e) { SIGNS = SIGNS || {}; } return SIGNS; }
function mySignKey() { return isP() ? 'P' : me(); }
function signOpen() {
  needSigns().then(() => { const had = SIGNS && SIGNS[mySignKey()];
    SHEET = () => `<h2>✍️ Mera sign</h2><p class="mut" style="margin:0 0 8px">Ungli se neeche white box mein apna sign kijiye. Yeh sign har certificate par ${isP() ? 'Principal' : 'Class Teacher'} ki jagah aayega.</p>
      ${had ? `<p class="mut" style="margin:0 0 4px">Abhi wala sign:</p><img class="sg-old" src="${had}" alt="">` : ''}
      <canvas id="sgPad" class="sg-pad" width="700" height="280"></canvas>
      <div class="btns"><button class="btn l" onclick="signClear()">Mitao</button><button class="btn go" onclick="signSave()">Save sign</button>${had ? `<button class="btn w" onclick="signRemove()">Hatao</button>` : ''}</div>`;
    render(); setTimeout(signBind, 50); });
}
let SG_DIRTY = false;
// Pointer events are handled on the document (the sheet can be re-drawn while it opens — per-canvas handlers would be lost).
function signBind() { SG_DIRTY = false; }
(function () { let on = false; const pad = e => e.target && e.target.id === 'sgPad' ? e.target : null;
  const pt = (c, e) => { const r = c.getBoundingClientRect(); return [(e.clientX - r.left) * c.width / r.width, (e.clientY - r.top) * c.height / r.height]; };
  const pen = c => { const x = c.getContext('2d'); x.lineWidth = 6; x.lineCap = 'round'; x.lineJoin = 'round'; x.strokeStyle = '#1E3A8A'; return x; };
  document.addEventListener('pointerdown', e => { const c = pad(e); if (!c) return; on = true; try { c.setPointerCapture(e.pointerId); } catch (er) {} const x = pen(c), [a, b] = pt(c, e); x.beginPath(); x.moveTo(a, b); e.preventDefault(); });
  document.addEventListener('pointermove', e => { const c = on && $('sgPad'); if (!c) return; const x = pen(c), [a, b] = pt(c, e); x.lineTo(a, b); x.stroke(); SG_DIRTY = true; e.preventDefault(); }, { passive: false });
  ['pointerup', 'pointercancel'].forEach(t => document.addEventListener(t, () => { on = false; }));
})();
function signClear() { const c = $('sgPad'); if (c) { c.getContext('2d').clearRect(0, 0, c.width, c.height); SG_DIRTY = false; } }
function signTrim(c) { const x = c.getContext('2d'), d = x.getImageData(0, 0, c.width, c.height).data; let x0 = c.width, y0 = c.height, x1 = 0, y1 = 0;
  for (let y = 0; y < c.height; y++) for (let i = 0; i < c.width; i++) if (d[(y * c.width + i) * 4 + 3] > 10) { if (i < x0) x0 = i; if (i > x1) x1 = i; if (y < y0) y0 = y; if (y > y1) y1 = y; }
  if (x1 <= x0) return null; const o = document.createElement('canvas'); o.width = x1 - x0 + 16; o.height = y1 - y0 + 16; o.getContext('2d').drawImage(c, x0 - 8, y0 - 8, o.width, o.height, 0, 0, o.width, o.height); return o.toDataURL('image/png'); }
async function signSave() { const c = $('sgPad'); if (!c || !SG_DIRTY) return toast('Pehle box mein sign kijiye.'); const data = signTrim(c); if (!data) return toast('Pehle box mein sign kijiye.');
  try { await api('teacher_api.php?a=comp', { op: 'sign', data }); SIGNS[mySignKey()] = data; SHEET = null; toast('Sign save ho gaya ✓ Ab har certificate par yahi aayega.'); render(); } catch (e) { toast(e.message); } }
async function signRemove() { try { await api('teacher_api.php?a=comp', { op: 'sign', data: '' }); delete SIGNS[mySignKey()]; SHEET = null; toast('Sign hata diya.'); render(); } catch (e) { toast(e.message); } }

// ---------------------------------------------------------------- festival wishes for WhatsApp Status (owner, 6 Nov 2026: Dhanteras story)
// Dates checked on 6 Nov 2026 (Time Out India). Holidays in the school calendar are added on top. Add next year's dates here.
const FEST = [['2026-11-06', 'Dhanteras', 'Maa Lakshmi aur Bhagwan Dhanvantari aapke ghar sukh, samriddhi aur achchi sehat layein.'],
  ['2026-11-07', 'Choti Diwali', 'Har andhera door ho aur har ghar khushiyon se roshan ho.'],
  ['2026-11-08', 'Diwali', 'Diyon ki roshni aapke jeevan mein khushiyan, gyaan aur samriddhi laaye.'],
  ['2026-11-10', 'Govardhan Puja', 'Bhagwan Shri Krishna sabki raksha karein aur ghar ann-dhan se bhara rahe.'],
  ['2026-11-11', 'Bhai Dooj', 'Bhai-behen ka pyaar aur bandhan hamesha aise hi mazboot rahe.']];
function festNow() {
  const t = todayISO(), tm = (() => { const d = new Date(t + 'T00:00:00'); d.setDate(d.getDate() + 1); return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); })();
  const L = FEST.filter(f => f[0] === t || f[0] === tm).map(f => ({ date: f[0], name: f[1], line: f[2] }));
  (S.cal || []).filter(e => e.type === 'Holiday' && (e.date === t || e.date === tm) && !/vacation|district|local|exam|sunday|rain|weather|trip/i.test(e.title))
    .forEach(e => { const nm = String(e.title).replace(/\s*\(.*?\)\s*/g, '').trim(); if (nm && !L.some(f => f.name.toLowerCase() === nm.toLowerCase())) L.push({ date: e.date, name: nm, line: 'Aapko aur aapke parivaar ko dher saari khushiyan aur shubhkamnayein.' }); });
  return L.sort((a, b) => a.date.localeCompare(b.date));
}
function festMsg(f) { return `🪔 *${f.name} ki hardik shubhkamnayein* 🪔\n${f.line}\n\n${S.settings.school || 'School'} parivaar ki or se aapko aur aapke parivaar ko dher saari shubhkamnayein 🙏\n— ${myName()}`; }
function festCard() {
  const L = festNow(); if (!L.length) return ''; window.__FEST = L; const t = todayISO();
  return `<div class="card fs-card"><div class="cp-dh"><span>🪔</span><div><small>Tyohar · parents aur Status ke liye</small><b>${L.map(f => (f.date === t ? 'Aaj ' : 'Kal ') + esc(f.name)).join(' · ')}</b></div></div>
    ${L.map((f, i) => `<div class="btns"><button class="btn go" onclick="festStoryOpen(${i})">✨ ${esc(f.name)} story photo</button><button class="btn l" onclick="festCopy(${i})">Copy wish</button></div>`).join('')}
    <small class="mut">Story photo → screenshot / share → WhatsApp Status. Wish copy karke parents group mein bhejiye.</small></div>`;
}
function festCopy(i) { const f = (window.__FEST || [])[i]; if (!f) return; window.__CT = [festMsg(f)]; certCopy(0); }
async function festStory(f) {
  await certFont(); const W = 1080, H = 1920, cv = document.createElement('canvas'); cv.width = W; cv.height = H; const x = cv.getContext('2d');
  const g = x.createLinearGradient(0, 0, 0, H); g.addColorStop(0, '#3B0A45'); g.addColorStop(.55, '#7C2D12'); g.addColorStop(1, '#C2410C'); x.fillStyle = g; x.fillRect(0, 0, W, H);
  x.save(); x.globalAlpha = .14; cMandala(x, W / 2, 1180, 520, ['#FDE68A', '#FDBA74', '#FDE68A', '#fff', '#fff']); x.restore();
  for (let i = 0; i < 70; i++) { x.fillStyle = `rgba(253,230,138,${.25 + (i % 5) * .12})`; x.beginPath(); x.arc((i * 137) % W, (i * 263) % 900 + 120, 2 + (i % 3), 0, 7); x.fill(); }
  cToran(x, 0, W, 0);
  x.fillStyle = '#fff'; x.beginPath(); x.arc(W / 2, 240, 74, 0, 7); x.fill(); if (LOGO_IMG.complete && LOGO_IMG.naturalWidth) x.drawImage(LOGO_IMG, W / 2 - 63, 177, 126, 126);
  x.textAlign = 'center'; x.fillStyle = '#FDE68A'; x.font = '600 34px ' + PF; x.fillText(S.settings.school || '', W / 2, 368);
  x.fillStyle = '#fff'; x.font = '500 40px ' + PF; cSpaced(x, 'HAPPY', W / 2, 520, 18);
  let fs = 170; x.font = `${fs}px GreatVibes, cursive`; while (x.measureText(f.name).width > W - 100 && fs > 90) { fs -= 8; x.font = `${fs}px GreatVibes, cursive`; }
  const tg = x.createLinearGradient(0, 560, 0, 720); tg.addColorStop(0, '#FEF3C7'); tg.addColorStop(1, '#F59E0B'); x.fillStyle = tg; x.fillText(f.name, W / 2, 690);
  cDiya(x, W / 2 - 230, 1080, 85); cDiya(x, W / 2 + 230, 1080, 85); cDiya(x, W / 2, 1030, 130);
  x.fillStyle = '#fff'; x.font = '500 42px ' + PF; let y = 1300; wrapText(x, f.line, W - 160).forEach(l => { x.fillText(l, W / 2, y); y += 60; });
  x.fillStyle = 'rgba(255,255,255,.9)'; x.font = '400 32px ' + PF; y += 40; x.fillText('Aapko aur aapke parivaar ko', W / 2, y); x.fillText('hardik shubhkamnayein 🙏', W / 2, y + 46);
  x.fillStyle = '#FDE68A'; x.font = '600 34px ' + PF; x.fillText('— ' + (isP() ? 'Principal, Teachers & Staff' : myName()), W / 2, H - 150);
  x.fillStyle = 'rgba(255,255,255,.8)'; x.font = '400 28px ' + PF; x.fillText((S.settings.school || '') + ', Bhadohi', W / 2, H - 100);
  return cv.toDataURL('image/png');
}
async function festStoryOpen(i) {
  const f = (window.__FEST || [])[i]; if (!f) return; toast('Story photo ban rahi hai…'); const st = await festStory(f), fn = f.name.toLowerCase().replace(/[^a-z0-9]+/g, '-') + '-wishes.png';
  if (!oldApp()) { if (window.SNMSISFiles && SNMSISFiles.shareImage) return SNMSISFiles.shareImage(fn, st.split(',')[1]);
    try { const fl = new File([await (await fetch(st)).blob()], fn, { type: 'image/png' }); if (navigator.canShare && navigator.canShare({ files: [fl] })) return await navigator.share({ files: [fl], text: festMsg(f) }); } catch (e) { if (e && e.name === 'AbortError') return; } }
  certFull(st);
}

// ---------------------------------------------------------------- "Naya kya hai" helper (owner, 6 Nov 2026: a friendly bot that tells everyone about new changes)
// When you ship something teachers must learn, bump NEWS_VER and rewrite NEWS — every user sees it once, and again from ☰.
const NEWS_VER = '2026-11-06-coord';
const NEWS = { title: 'Diwali Competition + Certificate', steps: [
  ['📋', 'Pehle haziri lagaiye', 'Jo bachche absent / leave hain woh team banate waqt list mein nahi aayenge.'],
  ['🪔', 'Home par “Competition” card', '“+ Team banao” dabaiye. Rangoli ya Diya chuniye, phir apni class ka group.'],
  ['👫', 'Team banaiye', 'Team ka naam likhiye (jaise Roshni), bachchon par tap kijiye, Save. Akele bachche ke liye “+ Single child”.'],
  ['📷', '2 photo lagaiye', 'Bada box 🎨 = rangoli / diya ki photo. Chhota box 👫 = team ke bachchon ki photo. Dono certificate par aayengi, aur bachchon ki attendance wali photo neeche gol mein.'],
  ['🥇', 'Result', 'Jeetne wali team par 🥇 🥈 🥉 dabaiye. Baaki sab ko “Participation” certificate apne aap milega.'],
  ['🎓', 'Certificate', '🎓 dabaiye — team ka ek, aur har bachche ka alag certificate. Screenshot ya Share karke parents ko bhejiye.'],
  ['💬', 'Parent ko message', 'Certificate ke neeche taiyaar message hai — Copy karke usi bachche ke parent ko WhatsApp par bhejiye.'],
  ['✨', 'WhatsApp Status', '“Story photo banao” + caption copy → WhatsApp → Status.'],
  ['📅', 'Activity Calendar (naya)', 'Home par 📅 card ya ☰ → Activity Calendar: har din ki activity har class ke liye, syllabus se judi. 🤖 “Kaise karein?” poora tarika batata hai.'],
  ['👩‍💼', 'Coordinator (naya)', 'Coordinator aapki copy jaanch kar report bhejengi — Home par dikhegi. 👍 “Dekh liya” ya ✋ “Sahmat nahi” dabaiye. Kabhi aapko “🔍 Dobara jaanch” milegi — 2 minute ka kaam.'],
  ['🎉', 'Tyohar wishes', 'Home par 🪔 Tyohar card: Dhanteras / Diwali ki story photo aur wish message — Status aur parents group ke liye.']] };
function newsOpen() {
  let o = $('nwBox'); if (o) o.remove(); o = document.createElement('div'); o.id = 'nwBox'; o.className = 'nw';
  o.innerHTML = `<div class="nw-in"><div class="nw-bot"><span class="nw-face">🤖</span><div class="nw-bub"><b>Namaste ${esc(myName().split(' ')[0])} ji! 👋</b><br>Main Maakit Mitra hoon. App mein naya aaya hai: <b>${esc(NEWS.title)}</b>. Aise use kijiye 👇</div></div>
    <ol class="nw-l">${NEWS.steps.map(([e, t, d]) => `<li><span>${e}</span><div><b>${esc(t)}</b><small>${esc(d)}</small></div></li>`).join('')}</ol>
    <button class="btn go full" id="nwOk">Samajh gaya 👍</button><small class="nw-f">Dobara dekhna ho to ☰ menu → “Naya kya hai”.</small></div>`;
  document.body.appendChild(o); $('nwOk').onclick = () => { store.set('ta_news', NEWS_VER); o.remove(); };
}
(function () {
  const _vHome = vHome; vHome = function () { return festCard() + compCard() + (typeof actCard === 'function' ? actCard() : '') + _vHome(); };
  const _vPdash = vPdash; vPdash = function () { return signCard() + festCard() + compCard() + (typeof actCard === 'function' ? actCard() : '') + _vPdash(); };
  const _om = openMenu; openMenu = function () { _om(); const f = SHEET; if (f) { SHEET = () => f().replace('</h2>', '</h2><div class="btns"><button class="btn go" onclick="SHEET=null;render();newsOpen()">🤖 Naya kya hai</button><button class="btn l" onclick="SHEET=null;signOpen()">✍️ Mera sign</button></div>'); render(); } };
  const _render = render; render = function () { _render(); if (S && TAB === 'comp') compThumbs();
    if (S && ME && !window.__nwShown && store.get('ta_news') !== NEWS_VER && !document.getElementById('ta-welcome')) { window.__nwShown = true; setTimeout(newsOpen, 900); } };
  const _go = go; go = function (tab, p) { if (tab === 'comp') DRAFT.ce = null; if ((tab === 'home' || tab === 'pdash' || tab === 'comp') && COMP && COMP.ok && Date.now() - COMP.at > 60000) needComp(true); return _go(tab, p); };
  if (typeof MENU !== 'undefined') MENU.forEach(g => { if (g[0] === 'Students') { const i = g[1].findIndex(x => x[3] === 'Competition'); const it = ['comp', {}, 'chart', 'Competition', 'Team, photo, jeetne wale, certificate']; if (i >= 0) g[1][i] = it; else g[1].unshift(it); } });
})();
