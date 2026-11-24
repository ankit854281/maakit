'use strict';
// Full fee statement (feestatement.php). Data: feecollect_api.php?a=state (search list) and ?a=statement&sid= (all sessions).
// 25 Nov 2026 (owner: "sibling nahi hai aur confusion ho raha hai, proper banaiye"): children with the same mobile number are
// brothers/sisters (same rule as the Sibling Demand Bill). Page 1 = family table; then one clear statement per child.
(function () {
  const $ = id => document.getElementById(id);
  const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
  const tok = () => { try { const q = new URLSearchParams(location.search); return localStorage.getItem(q.get('tk') === 'sal' ? 'sal_token' : 'sch_token') || localStorage.getItem('sch_token') || localStorage.getItem('sal_token') || ''; } catch (e) { return ''; } };
  const rs = p => '₹' + Math.round((+p || 0) / 100).toLocaleString('en-IN');
  const MN = { 4: 'April', 5: 'May', 6: 'June', 7: 'July', 8: 'August', 9: 'September', 10: 'October', 11: 'November', 12: 'December', 1: 'January', 2: 'February', 3: 'March' };
  const ord = m => (m + 8) % 12;   // April = 0 … March = 11
  const dmy = d => d ? String(d).slice(0, 10).split('-').reverse().join('-') : '';
  const phone10 = p => { const d = String(p || '').replace(/\D/g, ''); return d.length >= 10 ? d.slice(-10) : ''; };
  async function get(path) { const r = await fetch(path, { cache: 'no-store', headers: { 'X-School-Token': tok() } }); let j = null; try { j = await r.json(); } catch (e) {}
    if (!r.ok || !j || !j.ok) throw Error((j && j.error) || (r.status === 401 ? 'Please log in to the school app first, then open this page again.' : 'Server problem. Please try again.')); return j; }
  let STU = null;
  async function loadStu() { if (!STU) STU = (await get('feecollect_api.php?a=state')).students; return STU; }
  async function search(v) {
    v = v.trim().toLowerCase(); if (v.length < 2) { $('list').innerHTML = ''; return; }
    try { await loadStu(); } catch (e) { $('list').innerHTML = `<div class="msg">${esc(e.message)}</div>`; return; }
    const L = STU.filter(s => [s.name, s.cls, s.father, s.adm, s.phone].join(' ').toLowerCase().includes(v)).slice(0, 25);
    $('list').innerHTML = L.map(s => `<button data-sid="${esc(s.id)}"><b>${esc(s.name)}</b> <small>Class ${esc(s.cls)}${s.section ? '-' + esc(s.section) : ''} · ${esc(s.father || '')} · Adm ${esc(s.adm || '—')}</small></button>`).join('') || '<div class="msg">Nobody found.</div>';
  }
  // One session of one child: fee, concession, paid, balance (balance = fee − paid, concession already inside paid).
  function sesBlock(S, cur, today) {
    if (S.none) return `<h3>Session ${esc(S.ses)}</h3><p class="note">Is session ka fee card nahi mila (abhi tak admission nahi, ya record nahi).</p>`;
    const isCur = S.ses === cur, tm = +today.slice(5, 7), nowO = ord(tm), heads = S.heads.filter(h => !h.na);
    const byM = {}; heads.forEach(h => { (byM[h.m] = byM[h.m] || []).push(h); });
    const months = Object.keys(byM).map(Number).sort((a, b) => ord(a) - ord(b));
    let tF = 0, tD = 0, tP = 0, tB = 0;
    const rows = months.map(m => { const H = byM[m], f = H.reduce((a, h) => a + h.amt, 0), d = H.reduce((a, h) => a + h.disc, 0), p = H.reduce((a, h) => a + h.paid - h.disc, 0), b = f - d - p, up = isCur && ord(m) > nowO;
      tF += f; tD += d; tP += p; tB += b;
      return `<tr class="${up ? 'up' : ''}"><td><b>${MN[m]}</b>${up ? ' <small>(abhi aana baaki)</small>' : ''}</td><td>${H.map(h => esc(h.name) + ' ' + rs(h.amt)).join(', ')}</td><td class="n">${rs(f)}</td><td class="n">${d ? rs(d) : '—'}</td><td class="n grn">${p ? rs(p) : '—'}</td><td class="n ${b > 0 ? 'red' : ''}">${b > 0 ? rs(b) : '✓ poora'}</td></tr>`; }).join('');
    const R = S.receipts || [];
    return `<h3>Session ${esc(S.ses)} · Class ${esc(S.cls)}${S.section ? '-' + esc(S.section) : ''}${S.scheme ? ' · ' + esc(S.scheme) : ''}</h3>
      ${S.src === 'card' ? `<p class="note">Purane ERP ke fee card se (${S.from ? esc(S.from.replace('Old ERP fee card copied on ', 'copied ')) : 'copy kiya hua'}).</p>` : ''}
      ${S.oldDue ? `<p class="note"><b>Pichhle session ka baaki (purana baaki):</b> <span class="red">${rs(S.oldDue)}</span></p>` : ''}
      <table><tr><th>Mahina</th><th>Fee heads</th><th class="n">Fee</th><th class="n">Chhoot</th><th class="n">Jama</th><th class="n">Baaki</th></tr>${rows}
        <tr class="tot"><td colspan="2">Session ${esc(S.ses)} ka total${isCur ? ' (April se March)' : ''}</td><td class="n">${rs(tF)}</td><td class="n">${tD ? rs(tD) : '—'}</td><td class="n">${rs(tP)}</td><td class="n ${tB > 0 ? 'red' : ''}">${rs(tB)}</td></tr></table>
      ${R.length ? `<table><tr><th>Receipt no.</th><th>Tarikh</th><th>Kis cheez ki fee</th><th>Kaise diya</th><th class="n">Rakam</th></tr>${R.map(r => `<tr><td>${esc(r.no)}</td><td>${esc(dmy(r.date))}</td><td>${esc((r.lines || []).map(l => l[1] + (l[2] ? ' (' + l[2] + ')' : '')).join(', '))}</td><td>${esc(r.mode)}</td><td class="n">${rs(r.total)}</td></tr>`).join('')}</table>` : (S.src === 'card' ? '' : '<p class="note">Is session ki koi receipt naye ERP mein nahi hai.</p>')}`;
  }
  // Numbers for one child. "Aaj tak ka baaki" = purana baaki + current session's months up to this month.
  function summ(D) {
    const cur = D.current, today = D.today, nowO = ord(+today.slice(5, 7)), SS = D.sessions;
    const C = SS.find(x => x.ses === cur && !x.none);
    let paid = 0; SS.forEach(x => { if (x.none) return; x.heads.filter(h => !h.na).forEach(h => { paid += h.paid - h.disc; }); });
    const oldNow = C ? C.oldDue : 0;
    let sessTillToday = 0, sessFull = 0;
    if (C) C.heads.filter(h => !h.na).forEach(h => { const due = h.amt - h.paid; sessFull += due; if (ord(h.m) <= nowO) sessTillToday += due; });
    return { paid, oldNow, dueToday: oldNow + sessTillToday, dueMarch: oldNow + sessFull };
  }
  function bigBoxes(z, cur) {
    return `<div class="big"><div><span>Ab tak kul jama</span><b class="grn">${rs(z.paid)}</b></div><div><span>Purana baaki (pichhle session se)</span><b class="${z.oldNow ? 'red' : ''}">${rs(z.oldNow)}</b></div><div><span>Aaj tak ka kul baaki</span><b class="red">${rs(z.dueToday)}</b></div><div><span>March ${esc('20' + cur.slice(5))} tak ka kul baaki</span><b class="red">${rs(z.dueMarch)}</b></div></div>
      <p class="note">Aaj tak ka baaki = purana baaki + is session mein ab tak ke mahino ka baaki. March tak ka baaki = purana baaki + is session ka poora baaki. Dono mein kuch bhi do baar nahi jodha gaya.</p>`;
  }
  function childPage(D, sibNames, idx, total, cur, today) {
    const s = D.student, z = summ(D), SS = D.sessions;
    const others = sibNames.filter(n => n !== s.name);
    return `<div class="pg ${idx ? 'pb' : ''}"><div class="hd"><h1>${esc((D.school || '').toUpperCase())}</h1><p>Jagannathpur, Bhadohi (U.P.)</p><h2>Fee Statement · ${esc(SS[0].ses)} se ${esc(cur)} tak</h2>${total > 1 ? `<p class="note">Bachcha ${idx + 1} / ${total} (ek hi mobile par)</p>` : ''}</div>
      <div class="who"><div>Bachche ka naam: <b>${esc(s.name)}</b></div><div>Class: <b>${esc(s.cls)}${s.section ? '-' + esc(s.section) : ''}</b> · Adm. No. <b>${esc(s.adm || '—')}</b></div><div>Pita ka naam: <b>${esc(s.father || '—')}</b></div><div>Mobile: <b>${esc(s.phone || '—')}</b> · Tak ka hisaab: <b>${esc(dmy(today))}</b></div>${others.length ? `<div>Bhai/Behen (same mobile): <b>${others.map(esc).join(', ')}</b></div>` : ''}</div>
      ${bigBoxes(z, cur)}
      <table><tr><th>Session</th><th class="n">Fee</th><th class="n">Chhoot</th><th class="n">Jama</th><th class="n">Baaki</th></tr>${SS.map(x => { const q = sums(x, cur, today); return q ? `<tr><td>${esc(x.ses)}${x.ses === cur ? ' (chalu)' : ''}</td><td class="n">${rs(q.f)}</td><td class="n">${q.d ? rs(q.d) : '—'}</td><td class="n grn">${rs(q.p)}</td><td class="n ${q.b > 0 ? 'red' : ''}">${rs(q.b)}</td></tr>` : `<tr><td>${esc(x.ses)}</td><td colspan="4" class="note">fee card nahi</td></tr>`; }).join('')}</table>
      <p class="note">Session ka "Baaki" us session ka apna baaki hai. Wo agle session mein "Purana baaki" ban jaata hai, isliye upar ke kul baaki mein use ek hi baar joda gaya hai.</p>
      ${SS.map(x => sesBlock(x, cur, today)).join('')}
      <div class="sign"><span>Accountant</span><span>Principal</span><span>Chairman</span></div></div>`;
  }
  function sums(S, cur, today) {
    if (S.none) return null; const isCur = S.ses === cur, nowO = ord(+today.slice(5, 7)), H = S.heads.filter(h => !h.na);
    const f = H.reduce((a, h) => a + h.amt, 0), d = H.reduce((a, h) => a + h.disc, 0), p = H.reduce((a, h) => a + h.paid - h.disc, 0);
    return { f, d, p, b: f - d - p };
  }
  function familyPage(list, cur) {
    const rowsH = list.map(x => { const z = x.z; return `<tr><td><b>${esc(x.D.student.name)}</b><br><small>Class ${esc(x.D.student.cls)}${x.D.student.section ? '-' + esc(x.D.student.section) : ''} · Adm ${esc(x.D.student.adm || '—')}</small></td><td class="n grn">${rs(z.paid)}</td><td class="n ${z.oldNow ? 'red' : ''}">${rs(z.oldNow)}</td><td class="n red"><b>${rs(z.dueToday)}</b></td><td class="n red">${rs(z.dueMarch)}</td></tr>`; }).join('');
    const T = list.reduce((a, x) => ({ paid: a.paid + x.z.paid, oldNow: a.oldNow + x.z.oldNow, dueToday: a.dueToday + x.z.dueToday, dueMarch: a.dueMarch + x.z.dueMarch }), { paid: 0, oldNow: 0, dueToday: 0, dueMarch: 0 });
    return `<div class="pg"><div class="hd"><h1>${esc((list[0].D.school || '').toUpperCase())}</h1><p>Jagannathpur, Bhadohi (U.P.)</p><h2>Family Fee Summary · ek hi mobile: ${esc(list[0].D.student.phone || '—')}</h2></div>
      <p class="note">Is mobile number par ${list.length} bachche hain. Har bachche ka hisaab neeche alag statement mein hai.</p>
      <table><tr><th>Bachcha</th><th class="n">Ab tak jama</th><th class="n">Purana baaki</th><th class="n">Aaj tak ka baaki</th><th class="n">March tak ka baaki</th></tr>${rowsH}
        <tr class="tot"><td>Family total</td><td class="n grn">${rs(T.paid)}</td><td class="n">${rs(T.oldNow)}</td><td class="n red">${rs(T.dueToday)}</td><td class="n red">${rs(T.dueMarch)}</td></tr></table>
      <p class="note">Purana baaki = pichhle session ka baaki jo ab is session mein aa gaya. Aaj tak ka baaki = purana baaki + is session ke ab tak ke mahine. Family total in sab bachchon ka jod hai.</p>
      <div class="sign"><span>Accountant</span><span>Principal</span><span>Chairman</span></div></div>`;
  }
  async function open(sid) {
    $('list').innerHTML = ''; $('out').innerHTML = '<div class="msg">Statement ban raha hai…</div>';
    let D;
    try { D = await get('feecollect_api.php?a=statement&sid=' + encodeURIComponent(sid)); } catch (e) { $('out').innerHTML = `<div class="msg">${esc(e.message)}</div>`; return; }
    // Same mobile number = brothers/sisters (same rule as Sibling Demand Bill).
    let sibs = [];
    try { await loadStu(); const p = phone10(D.student.phone); if (p) sibs = STU.filter(x => phone10(x.phone) === p && x.ledger !== false); } catch (e) { sibs = []; }
    if (!sibs.some(x => x.id === sid)) sibs.unshift({ id: sid });
    let list;
    try { list = await Promise.all(sibs.map(x => x.id === sid ? Promise.resolve(D) : get('feecollect_api.php?a=statement&sid=' + encodeURIComponent(x.id)))); } catch (e) { list = [D]; }
    list = list.map(x => ({ D: x, z: summ(x) }));
    const cur = D.current, today = D.today;
    const names = list.map(x => x.D.student.name);
    const pages = (list.length > 1 ? [familyPage(list, cur)] : []).concat(list.map((x, i) => childPage(x.D, names, i, list.length, cur, today)));
    $('out').innerHTML = pages.join('');
    $('pr').style.display = ''; $('sh').style.display = navigator.share ? '' : 'none';
    document.title = 'Fee Statement — ' + D.student.name + ' (' + D.student.cls + ')' + (list.length > 1 ? ' + ' + (list.length - 1) + ' bhai/behen' : '');
  }
  $('q').addEventListener('input', e => search(e.target.value));
  $('list').addEventListener('click', e => { const b = e.target.closest('button[data-sid]'); if (b) { history.replaceState(null, '', '?sid=' + encodeURIComponent(b.dataset.sid) + (/[?&]tk=sal/.test(location.search) ? '&tk=sal' : '')); open(b.dataset.sid); } });
  $('pr').addEventListener('click', () => window.print());
  $('sh').addEventListener('click', () => { if (navigator.share) navigator.share({ title: document.title, url: location.href }).catch(() => {}); });
  const sid = new URLSearchParams(location.search).get('sid'); if (sid) open(sid);
})();
