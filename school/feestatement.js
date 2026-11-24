'use strict';
// Full fee statement (feestatement.php). Data: feecollect_api.php?a=state (search list) and ?a=statement&sid= (all sessions).
(function () {
  const $ = id => document.getElementById(id);
  const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
  const tok = () => { try { const q = new URLSearchParams(location.search); return localStorage.getItem(q.get('tk') === 'sal' ? 'sal_token' : 'sch_token') || localStorage.getItem('sch_token') || localStorage.getItem('sal_token') || ''; } catch (e) { return ''; } };
  const rs = p => '₹' + Math.round((+p || 0) / 100).toLocaleString('en-IN');
  const MN = { 4: 'April', 5: 'May', 6: 'June', 7: 'July', 8: 'August', 9: 'September', 10: 'October', 11: 'November', 12: 'December', 1: 'January', 2: 'February', 3: 'March' };
  const ord = m => (m + 8) % 12;   // April = 0 … March = 11
  const dmy = d => d ? String(d).slice(0, 10).split('-').reverse().join('-') : '';
  async function get(path) { const r = await fetch(path, { cache: 'no-store', headers: { 'X-School-Token': tok() } }); let j = null; try { j = await r.json(); } catch (e) {}
    if (!r.ok || !j || !j.ok) throw Error((j && j.error) || (r.status === 401 ? 'Please log in to the school app first, then open this page again.' : 'Server problem. Please try again.')); return j; }
  let STU = null, D = null;
  async function search(v) {
    v = v.trim().toLowerCase(); if (v.length < 2) { $('list').innerHTML = ''; return; }
    try { if (!STU) STU = (await get('feecollect_api.php?a=state')).students; } catch (e) { $('list').innerHTML = `<div class="msg">${esc(e.message)}</div>`; return; }
    const L = STU.filter(s => [s.name, s.cls, s.father, s.adm, s.phone].join(' ').toLowerCase().includes(v)).slice(0, 25);
    $('list').innerHTML = L.map(s => `<button data-sid="${esc(s.id)}"><b>${esc(s.name)}</b> <small>Class ${esc(s.cls)}${s.section ? '-' + esc(s.section) : ''} · ${esc(s.father || '')} · Adm ${esc(s.adm || '—')}</small></button>`).join('') || '<div class="msg">Nobody found.</div>';
  }
  function sesBlock(S, cur, today) {
    if (S.none) return `<h3>Session ${esc(S.ses)}</h3><p class="note">No fee card for this session (not admitted yet, or no record).</p>`;
    const isCur = S.ses === cur, tm = +today.slice(5, 7), nowO = ord(tm), heads = S.heads.filter(h => !h.na);
    const byM = {}; heads.forEach(h => { (byM[h.m] = byM[h.m] || []).push(h); });
    const months = Object.keys(byM).map(Number).sort((a, b) => ord(a) - ord(b));
    let tF = 0, tD = 0, tP = 0, tB = 0, tdB = 0;
    const rows = months.map(m => { const H = byM[m], f = H.reduce((a, h) => a + h.amt, 0), d = H.reduce((a, h) => a + h.disc, 0), p = H.reduce((a, h) => a + h.paid - h.disc, 0), b = f - d - p, up = isCur && ord(m) > nowO;
      tF += f; tD += d; tP += p; tB += b; if (!up) tdB += b;
      return `<tr class="${up ? 'up' : ''}"><td><b>${MN[m]}</b>${up ? ' <small>(coming)</small>' : ''}</td><td>${H.map(h => esc(h.name) + ' ' + rs(h.amt)).join(', ')}</td><td class="n">${rs(f)}</td><td class="n">${d ? rs(d) : '—'}</td><td class="n grn">${p ? rs(p) : '—'}</td><td class="n ${b > 0 ? 'red' : ''}">${b > 0 ? rs(b) : '✓'}</td></tr>`; }).join('');
    const R = S.receipts || [];
    return `<h3>Session ${esc(S.ses)} · Class ${esc(S.cls)}${S.section ? '-' + esc(S.section) : ''}${S.scheme ? ' · ' + esc(S.scheme) : ''}</h3>
      ${S.src === 'card' ? `<p class="note">From the old ERP fee card${S.from ? ' (' + esc(S.from.replace('Old ERP fee card copied on ', 'copied ')) + ')' : ''}.</p>` : ''}
      ${S.oldDue ? `<p class="note"><b>Old due brought from earlier sessions:</b> <span class="red">${rs(S.oldDue)}</span></p>` : ''}
      <table><tr><th>Month</th><th>Fee heads</th><th class="n">Fee</th><th class="n">Concession</th><th class="n">Paid</th><th class="n">Due</th></tr>${rows}
        <tr class="tot"><td colspan="2">Session ${esc(S.ses)} total${isCur ? ' (full year, April–March)' : ''}</td><td class="n">${rs(tF)}</td><td class="n">${tD ? rs(tD) : '—'}</td><td class="n">${rs(tP)}</td><td class="n ${tB > 0 ? 'red' : ''}">${rs(tB)}</td></tr>
        ${isCur ? `<tr class="sub"><td colspan="5"><b>Due till today (${MN[tm]})</b></td><td class="n red"><b>${rs(tdB)}</b></td></tr>` : ''}</table>
      ${R.length ? `<table><tr><th>Receipt</th><th>Date</th><th>For</th><th>Mode</th><th class="n">Amount</th></tr>${R.map(r => `<tr><td>${esc(r.no)}</td><td>${esc(dmy(r.date))}</td><td>${esc((r.lines || []).map(l => l[1] + (l[2] ? ' (' + l[2] + ')' : '')).join(', '))}</td><td>${esc(r.mode)}</td><td class="n">${rs(r.total)}</td></tr>`).join('')}</table>` : (S.src === 'card' ? '' : '<p class="note">No receipt in the new ERP for this session.</p>')}`;
  }
  function sums(S, cur, today) {
    if (S.none) return null; const isCur = S.ses === cur, nowO = ord(+today.slice(5, 7)), H = S.heads.filter(h => !h.na);
    const f = H.reduce((a, h) => a + h.amt, 0), d = H.reduce((a, h) => a + h.disc, 0), p = H.reduce((a, h) => a + h.paid - h.disc, 0);
    const bt = H.filter(h => !isCur || ord(h.m) <= nowO).reduce((a, h) => a + h.amt - h.paid, 0);
    return { f, d, p, b: f - d - p, bt };
  }
  async function open(sid) {
    $('list').innerHTML = ''; $('out').innerHTML = '<div class="msg">Making the statement…</div>';
    try { D = await get('feecollect_api.php?a=statement&sid=' + encodeURIComponent(sid)); } catch (e) { $('out').innerHTML = `<div class="msg">${esc(e.message)}</div>`; return; }
    const s = D.student, cur = D.current, today = D.today, SS = D.sessions, C = SS.find(x => x.ses === cur && !x.none), cs = C ? sums(C, cur, today) : null;
    const oldNow = C ? C.oldDue : 0, dueToday = (cs ? cs.bt : 0) + oldNow, dueMarch = (cs ? cs.b : 0) + oldNow;
    const allPaid = SS.reduce((a, x) => { const z = sums(x, cur, today); return a + (z ? z.p : 0); }, 0);
    $('out').innerHTML = `<div class="pg"><div class="hd"><h1>${esc((D.school || '').toUpperCase())}</h1><p>Jagannathpur, Bhadohi (U.P.)</p><h2>Fee Statement · ${esc(SS[0].ses)} to ${esc(cur)}</h2></div>
      <div class="who"><div>Student: <b>${esc(s.name)}</b></div><div>Class: <b>${esc(s.cls)}${s.section ? '-' + esc(s.section) : ''}</b> · Adm. No. <b>${esc(s.adm || '—')}</b></div><div>Father: <b>${esc(s.father || '—')}</b></div><div>Mobile: <b>${esc(s.phone || '—')}</b> · As on <b>${esc(dmy(today))}</b></div></div>
      <div class="big"><div><span>Paid (all sessions shown)</span><b class="grn">${rs(allPaid)}</b></div><div><span>Old due in ${esc(cur)}</span><b class="${oldNow ? 'red' : ''}">${rs(oldNow)}</b></div><div><span>Total due till today</span><b class="red">${rs(dueToday)}</b></div><div><span>Total due till March ${esc('20' + cur.slice(5))}</span><b class="red">${rs(dueMarch)}</b></div></div>
      <table><tr><th>Session</th><th class="n">Fee</th><th class="n">Concession</th><th class="n">Paid</th><th class="n">Balance</th></tr>${SS.map(x => { const z = sums(x, cur, today); return z ? `<tr><td>${esc(x.ses)}${x.ses === cur ? ' (full year)' : ''}</td><td class="n">${rs(z.f)}</td><td class="n">${z.d ? rs(z.d) : '—'}</td><td class="n grn">${rs(z.p)}</td><td class="n ${z.b > 0 ? 'red' : ''}">${rs(z.b)}</td></tr>` : `<tr><td>${esc(x.ses)}</td><td colspan="4" class="note">no fee card</td></tr>`; }).join('')}</table>
      <p class="note">An earlier session's unpaid balance is carried into the next session as "Old due", so add the current session's due and its old due for what is payable now — not every row of the Balance column.</p>
      ${SS.map(x => sesBlock(x, cur, today)).join('')}
      <div class="sign"><span>Accountant</span><span>Principal</span><span>Chairman</span></div></div>`;
    $('pr').style.display = ''; $('sh').style.display = navigator.share ? '' : 'none';
    document.title = 'Fee Statement — ' + s.name + ' (' + s.cls + ')';
  }
  $('q').addEventListener('input', e => search(e.target.value));
  $('list').addEventListener('click', e => { const b = e.target.closest('button[data-sid]'); if (b) { history.replaceState(null, '', '?sid=' + encodeURIComponent(b.dataset.sid) + (/[?&]tk=sal/.test(location.search) ? '&tk=sal' : '')); open(b.dataset.sid); } });
  $('pr').addEventListener('click', () => window.print());
  $('sh').addEventListener('click', () => { if (navigator.share) navigator.share({ title: document.title, url: location.href }).catch(() => {}); });
  const sid = new URLSearchParams(location.search).get('sid'); if (sid) open(sid);
})();
