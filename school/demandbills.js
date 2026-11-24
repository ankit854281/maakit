'use strict';
// ERP Demand Bill page (demandbills.php) — owner, 20 Nov 2026. Old-ERP style: Class → Section → Student (or all) →
// Bill type Sibling (one bill per family, same mobile number) / Single → Upto month → Submit. Bills are the approved
// 12 Nov design drawn by demandbill.js (DemandBill.draw). One child = demandbill_api.php?a=bill, many = ?a=bulk.
(function () {
  const $ = id => document.getElementById(id);
  const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
  const rs = n => '₹' + Math.round(+n || 0).toLocaleString('en-IN');
  const CLASSES = ['PG', 'LKG', 'UKG', '1st', '2nd', '3rd', '4th', '5th', '6th', '7th', '8th', '9th', '10th', '11th', '12th'];
  const MON = [[4, 'April'], [5, 'May'], [6, 'June'], [7, 'July'], [8, 'August'], [9, 'September'], [10, 'October'], [11, 'November'], [12, 'December'], [1, 'January'], [2, 'February'], [3, 'March']];
  const tok = () => { try { return localStorage.getItem('sch_token') || ''; } catch (e) { return ''; } };
  async function get(path) {
    const r = await fetch(path, { cache: 'no-store', headers: { 'X-School-Token': tok() } }); let j = null; try { j = await r.json(); } catch (e) {}
    if (r.status === 401) throw Error('Please log in to the ERP first, then open Demand Bill again.');
    if (!r.ok || !j || !j.ok) throw Error((j && j.error) || 'Server problem. Please try again.'); return j;
  }
  let LIST = null, BILLS = [], IMGS = [];
  const F = { cls: '', sec: '', sid: '', mode: 'sib', upto: new Date().getMonth() + 1, due: true };

  async function start() {
    try { LIST = await get('demandbill_api.php?a=list'); } catch (e) { $('form').innerHTML = `<div class="msg">${esc(e.message)}</div>`; return; }
    form();
  }
  function kidsOf() { return LIST.rows.filter(r => (!F.cls || r.cls === F.cls) && (!F.sec || (r.section || '').toUpperCase() === F.sec.toUpperCase())).sort((a, b) => a.name.localeCompare(b.name)); }
  function form() {
    const cls = CLASSES.filter(c => LIST.rows.some(r => r.cls === c)), secs = [...new Set(LIST.rows.filter(r => r.cls === F.cls).map(r => (r.section || '').toUpperCase()).filter(Boolean))].sort();
    const kids = F.cls ? kidsOf() : [];
    $('form').innerHTML = `<div class="grid">
      <label>Session</label><select disabled><option>${esc(LIST.ses.replace(/^(20\d\d)-(\d\d)$/, '$1-20$2'))}</option></select>
      <label>Search child</label><div class="sr"><input type="text" id="q" placeholder="Name, father, mobile or adm. no" autocomplete="off"><div class="sug" id="sug" hidden></div></div>
      <label>Class</label><select id="cls"><option value="">All classes</option>${cls.map(c => `<option ${F.cls === c ? 'selected' : ''}>${c}</option>`).join('')}</select>
      <label>Section</label><select id="sec" ${F.cls ? '' : 'disabled'}><option value="">All</option>${secs.map(s => `<option ${F.sec === s ? 'selected' : ''}>${esc(s)}</option>`).join('')}</select>
      <label>Student</label><select id="sid" ${F.cls ? '' : 'disabled'}><option value="">All students${F.cls ? ' (' + kids.length + ')' : ''}</option>${kids.map(k => `<option value="${esc(k.sid)}" ${F.sid === k.sid ? 'selected' : ''}>${esc(k.name)} - ${esc(k.father)}</option>`).join('')}</select>
      <label>Upto Month</label><select id="upto">${MON.map(([m, n]) => `<option value="${m}" ${F.upto === m ? 'selected' : ''}>${n}</option>`).join('')}</select>
      <label>Bill type</label><div class="rad"><label><input type="radio" name="mode" value="sib" ${F.mode === 'sib' ? 'checked' : ''}> Sibling (brothers / sisters in one bill)</label><label><input type="radio" name="mode" value="single" ${F.mode === 'single' ? 'checked' : ''}> Single (each child alone)</label></div>
      <label></label><label style="display:flex;gap:6px;align-items:center"><input type="checkbox" id="due" ${F.due ? 'checked' : ''} ${F.sid ? 'disabled' : ''}> Only children with fee due</label>
    </div>
    <div class="act"><button class="btn" id="go">Submit</button><span class="note" style="margin:0">Sibling = children with the same mobile number come in one bill with the family total.</span></div>`;
    $('cls').onchange = e => { F.cls = e.target.value; F.sec = ''; F.sid = ''; form(); };
    $('sec').onchange = e => { F.sec = e.target.value; F.sid = ''; form(); };
    $('sid').onchange = e => { F.sid = e.target.value; form(); };
    $('upto').onchange = e => { F.upto = +e.target.value; };
    $('due').onchange = e => { F.due = e.target.checked; };
    document.querySelectorAll('input[name=mode]').forEach(r => { r.onchange = () => { F.mode = r.value; }; });
    $('go').onclick = submit;
    $('q').oninput = e => suggest(e.target.value);
  }
  function suggest(q) {
    q = q.toLowerCase().trim(); const el = $('sug');
    if (q.length < 2) { el.hidden = true; return; }
    const L = LIST.rows.filter(r => (r.name + ' ' + r.father + ' ' + r.mobile + ' ' + r.adm).toLowerCase().includes(q)).slice(0, 30);
    el.innerHTML = L.length ? L.map(r => `<button data-sid="${esc(r.sid)}"><b>${esc(r.name)}</b> <small>Class ${esc(r.cls)}${r.section ? '-' + esc(r.section) : ''} · ${esc(r.father)} · ${esc(r.mobile)}</small></button>`).join('') : '<div class="msg">Nobody found.</div>';
    el.hidden = false;
    el.querySelectorAll('button').forEach(b => { b.onclick = () => { const r = LIST.rows.find(x => x.sid === b.dataset.sid); F.cls = r.cls; F.sec = ''; F.sid = r.sid; form(); submit(); }; });
  }
  async function submit() {
    $('out').innerHTML = '<div class="pan msg">Making the demand bills…</div>';
    const mode = F.mode === 'single' ? 'single' : 'sib';
    try {
      if (F.sid) { const B = await get(`demandbill_api.php?a=bill&sid=${encodeURIComponent(F.sid)}&mode=${mode}&upto=${F.upto}`); BILLS = [{ ...B, kids: B.kids }]; }
      else { const R = await get(`demandbill_api.php?a=bulk&cls=${encodeURIComponent(F.cls)}&section=${encodeURIComponent(F.sec)}&mode=${mode}&upto=${F.upto}&due=${F.due ? 1 : 0}`); BILLS = R.bills.map((kids, i) => ({ ...R, bills: undefined, pays: undefined, kids, pay: (R.pays || [])[i] || '' })); }
    } catch (e) { $('out').innerHTML = `<div class="pan msg">${esc(e.message)}</div>`; return; }
    show();
  }
  const famTotal = B => B.kids.reduce((a, k) => a + k.total.now, 0);
  function show() {
    IMGS = [];
    if (!BILLS.length) { $('out').innerHTML = '<div class="pan msg">No bill — nobody has fee due for this choice.</div>'; return; }
    const tot = BILLS.reduce((a, B) => a + famTotal(B), 0), kids = BILLS.reduce((a, B) => a + B.kids.length, 0), B0 = BILLS[0];
    $('out').innerHTML = `<div class="pan sumb"><span><b>${BILLS.length}</b> bill${BILLS.length > 1 ? 's' : ''} · ${kids} child${kids > 1 ? 'ren' : ''}</span><span>Payable till ${esc(B0.upto)} ${esc(B0.uptoYear)}: <b>${rs(tot)}</b></span>
      <button class="btn y" id="pall">🖨 Print all</button><span class="note" id="prog" style="margin:0"></span></div><div class="bills" id="bl"></div>`;
    $('pall').onclick = printAll;
    const bl = $('bl'); let i = 0;
    const step = () => {   // draw a few at a time so a whole class does not freeze the page
      for (let n = 0; n < 6 && i < BILLS.length; n++, i++) {
        const B = BILLS[i], url = DemandBill.draw(B).toDataURL('image/jpeg', 0.9); IMGS[i] = url;
        const k0 = B.kids[0], ph = (String(k0.mobile).match(/\d{10}/) || [''])[0];
        const d = document.createElement('div'); d.className = 'bill';
        d.innerHTML = `<h4><span>${esc(B.kids.map(k => k.name + ' (' + k.cls + ')').join(', '))}</span><em>${rs(famTotal(B))}</em></h4><img alt="Demand bill" src="${url}">
          <div class="bb"><a class="btn g" target="_blank" rel="noopener" href="https://wa.me/${ph ? '91' + ph : ''}?text=${encodeURIComponent(DemandBill.waText(B))}">WhatsApp</a><a class="btn l" download="Demand-bill-${esc(k0.name.replace(/\W+/g, '-'))}.jpg" href="${url}">Download</a><button class="btn l" data-p="${i}">Print</button></div>`;
        d.querySelector('img').onclick = () => zoom(url); d.querySelector('[data-p]').onclick = () => printImgs([url]);
        bl.appendChild(d);
      }
      $('prog').textContent = i < BILLS.length ? `Making bills… ${i}/${BILLS.length}` : '';
      if (i < BILLS.length) setTimeout(step, 30);
    };
    step();
  }
  function zoom(url) { const z = document.createElement('div'); z.className = 'zoom'; z.innerHTML = `<img src="${url}" alt="">`; z.onclick = () => z.remove(); document.body.appendChild(z); }
  function printImgs(urls) {
    const w = window.open('', '_blank'); if (!w) return alert('Please allow pop-ups for maakit.in to print.');
    w.document.write(`<!doctype html><html><head><title>Demand bills</title><style>@page{size:A4;margin:8mm}body{margin:0}img{width:100%;display:block;page-break-after:always;break-after:page}img:last-child{page-break-after:auto;break-after:auto}</style></head><body>${urls.map(u => `<img src="${u}">`).join('')}<script>let n=document.images.length;[...document.images].forEach(i=>{if(i.complete){if(--n===0)print()}else i.onload=()=>{if(--n===0)print()}})<\/script></body></html>`);
    w.document.close();
  }
  function printAll() {
    if (IMGS.filter(Boolean).length < BILLS.length) return alert('Bills are still being made — wait a moment, then press Print all.');
    printImgs(IMGS);
  }
  start();
})();
