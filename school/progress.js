'use strict';
// Progress Report — the old ERP's Examination-I cards (exam_termcard1.php / exam_progcard1.php), same layout and maths.
// Old cards are shown exactly as the old ERP printed them; cards for marks entered here are built in the same HTML.
if (new URLSearchParams(location.search).get('look') === 'classic') document.documentElement.classList.add('classic');
const $ = id => document.getElementById(id);
const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
const NEXT = { PG: 'LKG', LKG: 'UKG', UKG: '1st', '1st': '2nd', '2nd': '3rd', '3rd': '4th', '4th': '5th', '5th': '6th', '6th': '7th', '7th': '8th', '8th': '9th', '9th': '10th', '10th': '11th', '11th': '12th', '12th': '—' };
let IX = null, TAB = 'card', Q = { ses: '', cls: '1st', sec: '', exam: 'Term-2', stu: '', src: 'auto' }, CARDS = [], MK = null, BUSY = false, IMP = null;
const SES0 = (new URLSearchParams(location.search).get('session') || '').replace(/^(20\d\d)-20(\d\d)$/, '$1-$2');
const PHOTO = {};   // old path or 'sid:<id>' -> data URL

function toast(t) { const el = $('prToast'); el.textContent = t; clearTimeout(toast.t); toast.t = setTimeout(() => { el.textContent = ''; }, 7000); }
async function api(a, body, q) {
  const r = await fetch('progress_api.php?a=' + a + (q ? '&' + new URLSearchParams(q) : ''), { method: body ? 'POST' : 'GET', headers: { 'Content-Type': 'application/json', 'X-School-Token': localStorage.getItem('sch_token') || '' }, ...(body ? { body: JSON.stringify(body) } : {}) });
  let d; try { d = await r.json(); } catch (e) { throw Error('The server did not answer properly. Please try again.'); }
  if (!r.ok || !d.ok) throw Error(d.error || 'Could not complete this.');
  return d;
}

// ---------------------------------------------------------------- old ERP maths (9-point scale, Examination-I)
function grade(p) { return p >= 91 ? 'A1' : p >= 81 ? 'A2' : p >= 71 ? 'B1' : p >= 61 ? 'B2' : p >= 51 ? 'C1' : p >= 41 ? 'C2' : p >= 33 ? 'D' : p >= 21 ? 'E1' : 'E2'; }
const pct = (a, b) => b ? Math.round(a / b * 10000) / 100 : 0;                 // 2 decimals, "77" not "77.00" — as the old card prints
const num = v => v === '' || v === undefined || v === null || String(v).toUpperCase() === 'AB' ? 0 : Number(v);
const show = v => v === undefined || v === null ? '' : String(v);
function termOf(marks, sid, subjects) {
  const by = Object.fromEntries(Object.values(marks || {}).map(x => [x.subject, x]));
  const rows = subjects.map(s => { const m = ((by[s] || {}).rows || {})[sid] || {}; const t = num(m.pt) + num(m.nb) + num(m.sea) + num(m.te); return { s, m, t, g: grade(t) }; });
  const tot = k => rows.reduce((a, r) => a + num(r.m[k]), 0), sum = rows.reduce((a, r) => a + r.t, 0), max = rows.length * 100;
  return { rows, pt: tot('pt'), nb: tot('nb'), sea: tot('sea'), te: tot('te'), sum, max, p: pct(sum, max), g: grade(pct(sum, max)), any: rows.some(r => ['pt', 'nb', 'sea', 'te'].some(k => show(r.m[k]) !== '')) };
}

// ---------------------------------------------------------------- showing a card safely
const TAGS = new Set(['TABLE', 'TBODY', 'THEAD', 'TFOOT', 'TR', 'TD', 'TH', 'DIV', 'SPAN', 'B', 'STRONG', 'I', 'U', 'BR', 'P', 'IMG', 'FONT', 'CENTER', 'SMALL', 'HR', 'H1', 'H2', 'H3', 'H4', 'SUP', 'SUB']);
const ATTRS = new Set(['style', 'colspan', 'rowspan', 'width', 'height', 'align', 'valign', 'border', 'cellspacing', 'cellpadding', 'class', 'color', 'size', 'bgcolor', 'face', 'alt']);
function bargraph(src) {
  const q = new URLSearchParams(src.split('?')[1] || ''), subs = [], marks = [];
  for (const [k, v] of q) { if (k.startsWith('subjects')) subs.push(v); else if (k.startsWith('marks')) marks.push(Number(v) || 0); }
  return svgBars(subs, marks);
}
function svgBars(subs, marks) {
  const W = 300, H = 130, n = Math.max(1, subs.length), bw = Math.min(28, (W - 30) / n - 8), top = Math.max(100, ...marks);
  const bars = subs.map((s, i) => { const h = Math.round((marks[i] || 0) / top * (H - 34)), x = 26 + i * ((W - 30) / n); return `<rect x="${x}" y="${H - 20 - h}" width="${bw}" height="${h}" fill="#4F81BD"/><text x="${x + bw / 2}" y="${H - 24 - h}" font-size="8" text-anchor="middle">${marks[i] || 0}</text><text x="${x + bw / 2}" y="${H - 8}" font-size="7" text-anchor="middle">${esc(s.slice(0, 8))}</text>`; }).join('');
  return 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(`<svg xmlns="http://www.w3.org/2000/svg" width="${W}" height="${H}"><rect width="100%" height="100%" fill="#fff"/><line x1="22" y1="4" x2="22" y2="${H - 20}" stroke="#888"/><line x1="22" y1="${H - 20}" x2="${W}" y2="${H - 20}" stroke="#888"/>${bars}</svg>`);
}
function clean(html) {
  const doc = new DOMParser().parseFromString('<div id="x">' + html + '</div>', 'text/html'), root = doc.getElementById('x'), need = [];
  (function walk(el) {
    for (const c of [...el.children]) {
      if (!TAGS.has(c.tagName)) { if (['SCRIPT', 'STYLE', 'IFRAME', 'OBJECT', 'EMBED', 'FORM', 'INPUT', 'BUTTON', 'SELECT', 'TEXTAREA', 'LINK', 'META'].includes(c.tagName)) { c.remove(); continue; } c.replaceWith(...c.childNodes); continue; }
      for (const a of [...c.attributes]) { const n = a.name.toLowerCase(); if (n === 'src' && c.tagName === 'IMG') continue; if (!ATTRS.has(n) || (n === 'style' && /url\s*\(|expression|javascript|@import/i.test(a.value))) c.removeAttribute(a.name); }
      if (c.tagName === 'IMG') {
        const s = c.getAttribute('src') || '';
        if (s.startsWith('exam_bargraph.php')) c.setAttribute('src', bargraph(s));
        else if (/^(uploads\/|website_upload\/|princ_sign1\.png$|sid:)/.test(s)) { c.setAttribute('data-ph', s); c.setAttribute('src', PHOTO[s] || 'data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw=='); if (!PHOTO[s]) need.push(s); }
        else if (s.startsWith('data:image/svg+xml')) { /* our own chart */ }
        else c.removeAttribute('src');
      }
      walk(c);
    }
  })(root);
  return { html: root.innerHTML, need };
}
async function fillPhotos(need) {
  need = [...new Set(need)].filter(x => !PHOTO[x]);
  const sids = need.filter(x => x.startsWith('sid:')), paths = need.filter(x => !x.startsWith('sid:'));
  for (let i = 0; i < paths.length; i += 60) { try { Object.assign(PHOTO, (await api('photos', null, { ids: paths.slice(i, i + 60).join(',') })).photos); } catch (e) {} }
  for (const s of sids) { try { const p = (await api('photos', null, { sid: s.slice(4) })).photos; if (p[s.slice(4)]) PHOTO[s] = p[s.slice(4)]; } catch (e) {} }
  document.querySelectorAll('img[data-ph]').forEach(img => { const k = img.getAttribute('data-ph'); if (PHOTO[k]) img.src = PHOTO[k]; });
}
async function gunzip(b64) {
  const bin = Uint8Array.from(atob(b64), c => c.charCodeAt(0));
  return await new Response(new Blob([bin]).stream().pipeThrough(new DecompressionStream('gzip'))).text();
}

// ---------------------------------------------------------------- cards built here, in the old ERP's own HTML
const HEADER = 'website_upload/2024/03/f2024031156p1.webp';
function headHtml(ses, examTitle, s, photo) {
  const y = ses.replace(/^(20\d\d)-(\d\d)$/, '$1-20$2');
  return `<tr><td style=" font-size:24px" align="center" height="80"><table width="100%" border="0" cellspacing="0" cellpadding="0" class="k1"><tbody><tr><td><table border="0" width="100%" cellspacing="0" cellpadding="0"><tbody>
<tr><td colspan="2"><img src="${HEADER}" alt="" width="100%"></td></tr>
<tr><td style="font-family: 'Courier New', Courier; font-size: 24px; color: #0000ff;" colspan="2" align="center"><strong>Progress Report</strong></td></tr>
<tr><td colspan="2" align="center"><span style="font-family: 'Courier New', Courier; font-size: 22px;"><strong><strong>Session : <strong>${esc(y)}</strong></strong></strong></span></td></tr>
<tr><td style="font-family: 'Courier New', Courier; font-size: 18px;" colspan="2" align="center"><strong>${esc(examTitle)}</strong></td></tr>
<tr><td width="89%"><table border="0" width="100%" cellspacing="2" cellpadding="0"><tbody>
<tr><td style="font-family: Katrina-Bold;" width="26%">Name of the Student :</td><td style="color: #0000ff; font-family: Katrina-Bold;" width="32%">${esc(s.name)}</td><td style="color: #000000; font-family: Katrina-Bold;" width="42%"><strong>Class</strong> <span style="font-family: Katrina-Bold;">: </span><strong><strong style="color: #0000ff;">${esc(s.cls)}&nbsp;${esc(s.section || '')}</strong></strong></td></tr>
<tr><td style="font-family: Katrina-Bold;">Date of Birth :</td><td><span style="color: #0000ff;">${esc(/^\d{4}-\d\d-\d\d$/.test(s.dob || '') ? s.dob.split('-').reverse().join('/') : s.dob || '')}</span></td><td><span style="font-family: Katrina-Bold;">Roll No. : <strong style="font-family: 'Courier New', Courier; color: #ff0000;">${esc(s.roll || '')}</strong></span></td></tr>
<tr><td style="font-family: Katrina-Bold;">Father's Name :</td><td><span style="color: #0000ff;">Mr. ${esc(s.father || '')}</span></td><td>Mother's Name : <span style="color: #0000ff;">Mrs. ${esc(s.mother || '')}</span></td></tr>
<tr><td style="font-family: Katrina-Bold;">Father's Mobile No :</td><td><span style="color: #0000ff;">${esc(s.phone || '')}</span></td><td>&nbsp;</td></tr></tbody></table></td>
<td align="center" width="11%"><img src="${photo}" alt="" width="75" height="90"></td></tr></tbody></table></td></tr>`;
}
const grading = `<table border="0" cellspacing="0" cellpadding="0" class="k3" style="font-size:11px; text-align:center; width:400px"><tbody><tr><td colspan="4"><b>Instructions</b></td></tr><tr><td colspan="4">Grading scale for scholastic areas (Grading on 9 Point Scale)</td></tr><tr><td><b>MARKS RANGE</b></td><td><b> GRADE</b></td><td><b>MARKS RANGE</b></td><td><b> GRADE</b></td></tr><tr><td>91.00-100.00</td><td>A1</td><td>81.00-90.99</td><td>A2</td></tr><tr><td>71.00-80.99</td><td>B1</td><td>61.00-70.99</td><td>B2</td></tr><tr><td>51.00-60.99</td><td>C1</td><td>41.00-50.99</td><td>C2</td></tr><tr><td>33.00-40.99</td><td>D</td><td>21.00-32.99</td><td>E1</td></tr><tr><td>0.00-20.99</td><td>E2</td><td></td><td></td></tr></tbody></table>`;
const sign = w => `<img src="princ_sign1.png" width="${w}" style="height: 20px;">`;
const coRow = (l, g) => `<tr><td width="77%" style="font-size:10px; font-weight:bold" align="left">${l}&nbsp;</td><td width="23%" style="font-size:10px; font-weight:bold" align="center">${esc(g || '')}&nbsp;</td></tr>`;
function termCard(ses, exam, s, T, X) {
  const cell = v => `<td align="center">${esc(show(v))}</td>`;
  return `<table width="722" border="0" cellspacing="0" cellpadding="0" class="k" style="page-break-after:always; width:720px;" align="center"><tbody>${headHtml(ses, exam, s, 'sid:' + s.id)}
<tr><td><table width="100%" border="0" cellspacing="0" cellpadding="0" class="k" align="center"><tbody>
<tr><td valign="left" style="font-family:'Courier New', Courier; font-size:18px; color:#FF0000" align="left"><b>PART - 1 ACADEMIC PERFORMANCE : <span style="color:#00F">SCHOLASTIC AREAS</span></b></td></tr>
<tr><td height="240" valign="top"><table border="0" cellspacing="0" cellpadding="0" class="k2" width="100%"><tbody>
<tr><td style="background-color:#CCC" colspan="13"><div align="center"><b>${esc(exam)}</b></div></td></tr>
<tr style="font-weight:bold; text-align:center"><td style="background-color:#CCC" width="28%" rowspan="2">SUBJECT</td><td style="background-color:#CCC" width="14%">Pre. Test</td><td style="background-color:#CCC" width="14%">Notebook</td><td style="background-color:#CCC" width="12%">SEA</td><td style="background-color:#CCC" width="11%">Term Exam</td><td style="background-color:#CCC" width="11%">Total</td><td style="background-color:#CCC" width="10%" rowspan="2">Grade</td></tr>
<tr style="font-weight:bold; text-align:center"><td style="background-color:#CCC">10</td><td style="background-color:#CCC">5</td><td style="background-color:#CCC">5</td><td style="background-color:#CCC">80</td><td style="background-color:#CCC">100</td></tr>
${T.rows.map(r => `<tr style="font-weight:bold"><td style="">${esc(r.s.toUpperCase())}</td>${cell(r.m.pt)}${cell(r.m.nb)}${cell(r.m.sea)}${cell(r.m.te)}${cell(r.t)}${cell(r.g)}</tr>`).join('')}
<tr style="font-weight:bold"><td style="" align="center">Total</td>${cell(T.pt)}${cell(T.nb)}${cell(T.sea)}${cell(T.te)}${cell(T.sum)}<td align="center">&nbsp;</td></tr></tbody></table></td></tr>
<tr><td height="30"><table width="100%" border="0" cellspacing="0" cellpadding="0" class="k1"><tbody><tr><td width="30%"><strong>Overall Marks : <b style="color:#0000FF">${T.sum}/${T.max}</b></strong></td><td width="27%" height="24">Percentage : <b style="color:#0000FF">${T.p}%</b></td><td width="25%" align="right"><strong>Overall Grade : <b style="color:#0000FF">${T.g}</b></strong></td><td align="center" width="18%" style="display:none">Rank : <b style="color:#0000FF">${esc(s.rank || '')}</b></td></tr></tbody></table></td></tr>
<tr><td height="40px"><span style="font-family:'Courier New', Courier; font-size:18px; color:#FF0000">PART - 2 CO-SCHOLASTIC</span></td></tr>
<tr><td height="40px"><table width="100%" border="0" class="k2" cellspacing="0" cellpadding="0"><tbody><tr><td style="font-weight:bold; font-size:12px;background-color:#FFCC99; ">2 : Co-Scholastic Area</td><td style="font-weight:bold; font-size:12px;background-color:#FFCC99;"><span style="font-weight:bold; font-size:12px;background-color:#FFCC99">3 : Discipline</span></td></tr>
<tr><td width="50%" valign="top" style=""><table width="100%" border="0" cellspacing="0" cellpadding="0"><tbody>${coRow('Art Education', X.art)}${coRow('Work Education / Vocational Education', X.work)}${coRow('Health &amp; Physical Education', X.health)}</tbody></table></td>
<td width="50%" valign="top" style=""><table width="100%" border="0" cellspacing="0" cellpadding="0"><tbody>${coRow('Discipline', X.disc)}</tbody></table></td></tr></tbody></table></td></tr>
<tr><td><table width="100%" border="0" cellspacing="0" cellpadding="0" class="k2"><tbody><tr><td width="50%" align="center"><span class="k1" style="font-weight:bold; font-size:11px">Class Teacher's Remark</span></td><td colspan="2" align="center" style="display:none"><span class="k1" style="font-weight:bold; font-size:12px">Attendance</span></td></tr>
<tr><td rowspan="2" valign="top"><b><strong style="font-family:'Courier New', Courier;font-size:11px;color:#0000FF">${esc(X.remark || '')} </strong></b></td><td style="display:none">Total Working Days </td><td style="display:none"> ${esc(X.days || '')} </td></tr>
<tr><td width="28%" style="display:none">Total Presents</td><td width="22%" style="display:none"> ${esc(X.present || '')} </td></tr></tbody></table></td></tr></tbody></table></td></tr>
<tr><td><table width="100%" border="0" cellspacing="0" cellpadding="0" class="k1"><tbody><tr><td>DATE : </td><td>&nbsp;</td><td>&nbsp;</td></tr><tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr><tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
<tr><td width="33%" align="left">PARENTS SIGNATURE</td><td width="34%" align="center"><strong>Class Teacher</strong></td><td width="33%" align="center"><div align="center" style="margin-top:-20px;">${sign('70%')}<br></div>Principal </td></tr></tbody></table></td></tr>
<tr><td align="center"><table width="100%" border="0" cellspacing="0" cellpadding="0" class="k1"><tbody><tr><td width="44%" align="center"><img src="${svgBars(T.rows.map(r => r.s.toUpperCase()), T.rows.map(r => r.t))}" height="130px"></td><td width="56%" align="center">${grading}</td></tr></tbody></table></td></tr></tbody></table>`;
}
function finalCard(ses, s, A, B, XA, XB, rank) {
  const cell = v => `<td align="center">${esc(show(v))}</td>`, blue = v => `<b style="color:#0000FF">${esc(v)}</b>`;
  const fs = A.sum + B.sum, fm = A.max + B.max, fp = pct(fs, fm);
  const d1 = num(XA.days), d2 = num(XB.days), p1 = num(XA.present), p2 = num(XB.present);
  const att = (p, d) => d ? String(pct(p, d)) : '';
  const co = (l, a, b, w) => `<tr><td width="${w[0]}" style="font-size:10px; font-weight:bold" align="left">${l}&nbsp;</td><td width="${w[1]}" style="font-size:10px; font-weight:bold" align="center">${esc(a || '')}&nbsp;</td><td width="${w[2]}" style="font-size:10px; font-weight:bold" align="center">${esc(b || '')}</td></tr>`;
  const coHead = `<tr><td style="font-size:10px; font-weight:bold" align="left">[On a 3-point (A-C) grading scale]</td><td style="font-size:10px; font-weight:bold" align="center">Term-1 (Grade)</td><td style="font-size:10px; font-weight:bold" align="center">Term-2 (Grade)</td></tr>`;
  const result = XB.result || (B.any || A.any ? (fp >= 33 ? 'PASS' : 'FAIL') : ''), promoted = XB.promoted || (result === 'PASS' ? NEXT[s.cls] || '' : '');
  return `<table width="722" border="0" cellspacing="0" cellpadding="0" class="k" style="page-break-after:always; width:720px;border-width:1px !important" align="center"><tbody>${headHtml(ses, '', s, 'sid:' + s.id)}
<tr><td><table width="100%" border="0" cellspacing="0" cellpadding="0" class="k" align="center"><tbody>
<tr><td valign="left" style="font-family:'Courier New', Courier; font-size:18px; color:#FF0000" align="left"><b>PART - 1 ACADEMIC PERFORMANCE : <span style="color:#00F">SCHOLASTIC AREAS</span></b></td></tr>
<tr><td height="200" valign="top"><table border="0" cellspacing="0" cellpadding="0" class="k2" width="100%"><tbody>
<tr><td>&nbsp;</td><td style="background-color:#CCC" colspan="6"><div align="center"><b>Term-1</b>(100 Marks)</div></td><td colspan="6" style="background-color:#CCC"><div align="center"><b>Term-2</b>(100 Marks)</div></td></tr>
<tr style="font-weight:bold; text-align:center"><td style="background-color:#CCC" width="24%" rowspan="2">SUBJECT</td><td style="background-color:#CCC" width="5%">Pre. Test</td><td style="background-color:#CCC" width="6%">Note Book</td><td style="background-color:#CCC" width="5%">SEA</td><td style="background-color:#CCC" width="8%">Half Yearly</td><td style="background-color:#CCC" width="7%">Total</td><td style="background-color:#CCC" width="7%" rowspan="2">Grade</td><td style="background-color:#CCC" width="5%">Pre. Test</td><td style="background-color:#CCC" width="6%">Note Book</td><td style="background-color:#CCC" width="4%">SEA</td><td style="background-color:#CCC" width="9%"> Yearly Exam</td><td style="background-color:#CCC" width="7%">Total</td><td style="background-color:#CCC" width="7%" rowspan="2">Grade</td></tr>
<tr style="font-weight:bold; text-align:center">${[10, 5, 5, 80, 100, 10, 5, 5, 80, 100].map(v => `<td style="background-color:#CCC">${v}</td>`).join('')}</tr>
${A.rows.map((r, i) => { const q = B.rows[i]; return `<tr style="font-weight:bold"><td style="">${esc(r.s.toUpperCase())}</td>${cell(r.m.pt)}${cell(r.m.nb)}${cell(r.m.sea)}${cell(r.m.te)}${cell(r.t)}${cell(r.g)}${cell(q.m.pt)}${cell(q.m.nb)}${cell(q.m.sea)}${cell(q.m.te)}${cell(q.t)}${cell(q.g)}</tr>`; }).join('')}
<tr style="font-weight:bold"><td style="" align="center">Total</td>${cell(A.pt)}${cell(A.nb)}${cell(A.sea)}${cell(A.te)}${cell(A.sum)}<td align="center">&nbsp;</td>${cell(B.pt)}${cell(B.nb)}${cell(B.sea)}${cell(B.te)}${cell(B.sum)}<td align="center">&nbsp;</td></tr></tbody></table></td></tr>
<tr><td height="20"><table width="100%" border="0" cellspacing="0" cellpadding="0" class="k4"><tbody><tr><td height="24" align="center" colspan="3"><b>Term-1</b></td><td colspan="3" align="center"><b>Term-2</b></td><td colspan="8" align="center"><b>FINAL</b></td></tr>
<tr><td align="center"><strong>Overall Marks</strong></td><td align="center" height="24">Percentage</td><td align="center"><strong>Overall<br>Grade</strong></td><td align="center"><strong>Overall<br>Marks</strong></td><td align="center">Percentage</td><td align="center"><strong>Overall<br>Grade</strong></td><td align="center"><strong>Overall<br>Marks</strong></td><td align="center">Percentage</td><td align="center"><strong>Overall<br> Grade</strong></td><td align="center" style="display:none">Rank</td></tr>
<tr><td width="11%" align="center"><strong>${blue(A.sum + '/' + A.max)}</strong></td><td width="9%" align="center" height="24">${blue(A.p + '%')}</td><td width="9%" align="center"><strong>${blue(A.g)}</strong></td><td width="9%" align="center"><strong>${blue(B.sum + '/' + B.max)}</strong></td><td width="10%" align="center">${blue(B.p + '%')}</td><td width="8%" align="center"><strong>${blue(B.g)}</strong></td><td width="11%" align="center"><strong>${blue(fs + '/' + fm)}</strong></td><td width="11%" align="center">${blue(fp + '%')} </td><td width="10%" align="center"><strong>${blue(grade(fp))}</strong></td><td width="12%" align="center" style="display:none">${blue(String(rank || ''))}</td></tr></tbody></table></td></tr>
<tr><td height="20px"><span style="font-family:'Courier New', Courier; font-size:18px; color:#FF0000;font-weight:bold">PART - 2 CO-SCHOLASTIC</span></td></tr>
<tr><td height="40px"><table width="100%" border="0" class="k2" cellpadding="0" cellspacing="0"><tbody><tr><td style="font-weight:bold; font-size:12px;background-color:#FFCC99 ">2 : Co-Scholastic Area</td><td style="font-weight:bold; font-size:12px;background-color:#FFCC99;">3 : Discipline</td></tr>
<tr><td width="50%" valign="top" style=""><table width="100%" border="0" cellspacing="0" cellpadding="0"><tbody>${coHead}${co('Art Education', XA.art, XB.art, ['71%', '15%', '14%'])}${co('Work Education / Vocational Education', XA.work, XB.work, ['71%', '15%', '14%'])}${co('Health &amp; Physical Education', XA.health, XB.health, ['71%', '15%', '14%'])}</tbody></table></td>
<td width="50%" valign="top" style=""><table width="100%" border="0" cellspacing="0" cellpadding="0"><tbody>${coHead}${co('Discipline', XA.disc, XB.disc, ['73%', '14%', '13%'])}</tbody></table></td></tr></tbody></table></td></tr>
<tr><td><table width="100%" border="0" cellspacing="0" cellpadding="0" class="k2"><tbody><tr><td width="43%" align="center"><span class="k1" style="font-weight:bold; font-size:10px">Class Teacher's Remark</span></td><td colspan="4" align="center"><span class="k1" style="font-weight:bold; font-size:10px">Attendance</span></td></tr>
<tr><td rowspan="4" valign="top"><b><strong style="font-family:'Courier New', Courier;font-size:16px;color:#0000FF">${esc(XB.remark || XA.remark || '')} </strong></b></td><td>&nbsp;</td><td align="center"><span style="font-size:10px; font-weight:bold">Term-1 </span></td><td align="center"><span style="font-size:10px; font-weight:bold">Term-2 </span></td><td align="center"><span style="font-size:10px; font-weight:bold">Final</span></td></tr>
<tr style="display:none"><td>Total Working Days </td><td align="center"> ${esc(show(XA.days))} </td><td align="center"> ${esc(show(XB.days))} </td><td align="center"> ${d1 + d2 || ''} </td></tr>
<tr style="display:none"><td width="24%">Total Presents</td><td width="11%" align="center"> ${esc(show(XA.present))} </td><td width="13%" align="center"> ${esc(show(XB.present))} </td><td width="9%" align="center"> ${p1 + p2 || ''} </td></tr>
<tr><td>Percentage</td><td align="center"><span class="k1" style="font-weight:bold;color:#0000FF;"> ${att(p1, d1)} </span></td><td align="center"><span class="k1" style="font-weight:bold;color:#0000FF;"> ${att(p2, d2)} </span></td><td align="center"><span class="k1" style="font-weight:bold;color:#0000FF;"> ${att(p1 + p2, d1 + d2)} </span></td></tr></tbody></table></td></tr></tbody></table></td></tr>
<tr><td><table width="100%" border="0" cellspacing="0" cellpadding="0" class="k1"><tbody>
<tr><td colspan="3" style="font-family:'Courier New', Courier;font-size:16px;color:#000000; font-weight:bold">Result : <span style="font-size:16px; color:#000000;font-family:'Courier New', Courier; font-weight:bold"><b style="color:#FF0000"> ${esc(result)} </b></span></td></tr>
<tr><td colspan="3" style="font-family:'Courier New', Courier;font-size:16px;color:#000000; font-weight:bold">Promoted to Class : <b><strong style="font-family:'Courier New', Courier;font-size:16px;color:#0000FF; font-weight:bold">${esc(promoted)} </strong></b></td></tr>
<tr><td colspan="2" align="left" style="font-family:'Courier New', Courier;font-size:16px;color:#000000; font-weight:bold">Place : </td><td align="center">&nbsp;</td></tr><tr><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>
<tr><td width="33%" align="left" style="font-family:'Courier New', Courier;font-size:16px;color:#000000; font-weight:bold">Date : </td><td width="34%" align="center" style="font-family:'Courier New', Courier;font-size:16px;color:#000000; font-weight:bold"><strong>Class Teacher</strong></td><td width="33%" align="center" style="font-family:'Courier New', Courier;font-size:16px;color:#000000; font-weight:bold"><div align="center" style="margin-top:-20px;">${sign('100%')}</div>Principal </td></tr></tbody></table></td></tr>
<tr><td align="center"><table width="100%" border="0" cellspacing="0" cellpadding="0"><tbody><tr><td width="56%" align="center">${grading}</td></tr></tbody></table></td></tr></tbody></table>`;
}
const OLDCSS = `.k td, .k th{ border-style: solid; border-width: 1px; border-color: #000; padding: 0px; font-family: Katrina-Bold; font-size: 11px; } .k1 td, .k1 th{ border-style: solid; border-width: 0px; border-color: #000; padding: 1px; font-family: Katrina-Bold; font-size: 14px; } .k2 td, .k2 th{ border-style: solid; border-width: 1px; border-color: #000; padding: 1px; font-family: Verdana; font-size: 11px; } .k4 td, .k4 th{ border-style: solid; border-width: 1px; border-color: #000; padding: 0px; font-family: Verdana; font-size: 9px; } .k3 td, .k3 th{ border-style: solid; border-width: 1px; border-color: #000; padding: 0px; font-family: Arial; font-size: 11px; } .k tr td table tr td table { font-weight: bold; }`;

// ---------------------------------------------------------------- loading
async function boot() {
  try { IX = await api('index'); Q.ses = SES0 || IX.session || Object.keys(IX.summary).sort().reverse()[0] || ''; render(); }
  catch (e) { $('prRoot').innerHTML = `<div class="pr"><h3>Progress Report</h3><p>${esc(e.message)}</p></div>`; }
}
async function showCards() {
  if (BUSY) return; BUSY = true; CARDS = []; render();
  try {
    const out = [], need = [];
    const old = Q.exam && (await api('index', null, { ses: Q.ses, exam: Q.exam, cls: Q.cls })).cards.filter(c => (!Q.sec || c.section === Q.sec) && (!Q.stu || c.name === Q.stu));
    const mine = await newCards();
    const useNew = Q.src === 'new' || (Q.src === 'auto' && mine.length);
    if (useNew) for (const h of mine) { const c = clean(h); out.push(c.html); need.push(...c.need); }
    else if (old.length) {
      for (let i = 0; i < old.length; i += 40) {
        const d = await api('cards', null, { keys: old.slice(i, i + 40).map(c => c.key).join(',') });
        for (const c of old.slice(i, i + 40)) if (d.cards[c.key]) { const x = clean(await gunzip(d.cards[c.key])); out.push(x.html); need.push(...x.need); }
      }
    }
    CARDS = out; BUSY = false; render(); fillPhotos(need);
    if (!out.length) toast('No progress cards for this choice.');
  } catch (e) { BUSY = false; toast(e.message); render(); }
}
async function newCards() {
  if (!['Term-1', 'Term-2', 'Final'].includes(Q.exam)) return [];
  const A = await api('marks', null, { ses: Q.ses, exam: Q.exam === 'Final' ? 'Term-2' : Q.exam, cls: Q.cls });
  const kids = A.students.filter(s => (!Q.sec || s.section === Q.sec) && (!Q.stu || s.name === Q.stu));
  const used = Object.values(A.marks).concat(Q.exam === 'Final' && A.other ? Object.values(A.other.marks) : []).map(m => m.subject);
  const subjects = A.subjects.filter(s => used.includes(s)).concat([...new Set(used)].filter(s => !A.subjects.includes(s)));
  if (!subjects.length) return [];
  const ex = r => (A.extra.rows || {})[r] || {};
  if (Q.exam !== 'Final') {
    const T = Object.fromEntries(A.students.map(s => [s.id, termOf(A.marks, s.id, subjects)]));
    const rank = Object.fromEntries(Object.entries(T).filter(([, t]) => t.any).sort((a, b) => b[1].sum - a[1].sum).map(([id], i) => [id, i + 1]));
    return kids.filter(s => T[s.id].any).map(s => termCard(Q.ses, Q.exam, { ...s, rank: rank[s.id] }, T[s.id], ex(s.id)));
  }
  const o = A.other, exA = r => ((o.extra || {}).rows || {})[r] || {};
  const F = Object.fromEntries(A.students.map(s => [s.id, [termOf(o.marks, s.id, subjects), termOf(A.marks, s.id, subjects)]]));
  const rank = Object.fromEntries(Object.entries(F).filter(([, t]) => t[0].any || t[1].any).sort((a, b) => (b[1][0].sum + b[1][1].sum) - (a[1][0].sum + a[1][1].sum)).map(([id], i) => [id, i + 1]));
  return kids.filter(s => F[s.id][0].any || F[s.id][1].any).map(s => finalCard(Q.ses, s, F[s.id][0], F[s.id][1], exA(s.id), ex(s.id), rank[s.id]));
}
function printCards() { if (!CARDS.length) return toast('Show the cards first.'); window.print(); }

// ---------------------------------------------------------------- marks entry (same components as the old Examination-I)
async function openEntry() {
  MK = null; render();
  if (!['Term-1', 'Term-2'].includes(Q.exam)) { Q.exam = 'Term-1'; }
  try { MK = await api('marks', null, { ses: Q.ses, exam: Q.exam, cls: Q.cls }); MK.subject = MK.subject && MK.mySubjects.includes(MK.subject) ? MK.subject : (MK.mySubjects[0] || ''); render(); } catch (e) { toast(e.message); }
}
function entryRow(s, m) {
  const t = num(m.pt) + num(m.nb) + num(m.sea) + num(m.te), any = ['pt', 'nb', 'sea', 'te'].some(k => show(m[k]) !== '');
  return `<tr><td>${esc(s.name)}</td>${IX.parts.map(([k, , max]) => `<td><input class="pr-n" data-sid="${esc(s.id)}" data-k="${k}" value="${esc(show(m[k]))}" maxlength="5" oninput="entryTotal(this)" placeholder="/${max}"></td>`).join('')}<td class="pr-t">${any ? t : ''}</td><td class="pr-t">${any ? grade(t) : ''}</td></tr>`;
}
function entryTotal(el) { const tr = el.closest('tr'), v = [...tr.querySelectorAll('input')].map(i => i.value); const any = v.some(x => x.trim() !== ''); const t = v.reduce((a, x) => a + num(x.trim()), 0); tr.cells[5].textContent = any ? t : ''; tr.cells[6].textContent = any ? grade(t) : ''; }
async function saveMarks() {
  const rows = {}; document.querySelectorAll('.pr-n').forEach(i => { (rows[i.dataset.sid] = rows[i.dataset.sid] || {})[i.dataset.k] = i.value.trim(); });
  try { await api('marks', { ses: Q.ses, exam: Q.exam, cls: Q.cls, subject: MK.subject, rows }); toast('Marks saved.'); await openEntry(); } catch (e) { toast(e.message); }
}
async function saveExtra() {
  const rows = {}; document.querySelectorAll('[data-x]').forEach(i => { (rows[i.dataset.sid] = rows[i.dataset.sid] || {})[i.dataset.x] = i.value.trim(); });
  try { await api('extra', { ses: Q.ses, exam: Q.exam, cls: Q.cls, rows }); toast('Saved.'); await openEntry(); } catch (e) { toast(e.message); }
}

// ---------------------------------------------------------------- import (Principal)
async function readImport(file) {
  try { const d = JSON.parse(await file.text()); IMP = { d, prev: await api('import', { ...d, apply: false }) }; render(); } catch (e) { IMP = null; toast(e.message); render(); }
}
async function applyImport() { try { const r = await api('import', { ...IMP.d, apply: true }); toast('Imported: ' + (r.toAdd || 0) + ' new' + (r.linked !== undefined ? ', ' + r.linked + ' photos linked to students' : '') + '.'); IMP = null; IX = await api('index'); render(); } catch (e) { toast(e.message); } }

// ---------------------------------------------------------------- drawing
const opt = (v, t, on) => `<option value="${esc(v)}" ${on ? 'selected' : ''}>${esc(t)}</option>`;
function render() {
  if (!IX) return;
  const sessions = [...new Set([Q.ses, IX.session, ...Object.keys(IX.summary)].filter(Boolean))].sort().reverse();
  const tabs = [['card', 'Progress Card'], ['entry', 'Marks Entry'], ['extra', 'Co-Scholastic / Remark / Attendance']].concat(IX.isPrincipal ? [['import', 'Import from old ERP']] : []);
  const sel = `<div class="pr-sel">
    <label>Session<select onchange="Q.ses=this.value;CARDS=[];${TAB === 'card' ? 'render()' : 'openEntry()'}">${sessions.map(x => opt(x, x.replace(/^(20\d\d)-(\d\d)$/, '$1-20$2'), x === Q.ses)).join('')}</select></label>
    <label>Class<select onchange="Q.cls=this.value;Q.sec='';Q.stu='';CARDS=[];${TAB === 'card' ? 'render()' : 'openEntry()'}">${IX.classes.map(c => opt(c, c, c === Q.cls)).join('')}</select></label>
    <label>Exam Type<select onchange="Q.exam=this.value;CARDS=[];${TAB === 'card' ? 'render()' : 'openEntry()'}">${(TAB === 'card' ? IX.exams : ['Term-1', 'Term-2']).map(e => opt(e, e === 'Final' ? 'Final (Term-1 + Term-2)' : e, e === Q.exam)).join('')}</select></label>
    ${TAB === 'card' ? `<label>Section<select onchange="Q.sec=this.value;CARDS=[]">${opt('', 'All', !Q.sec)}${['A', 'B', 'C', 'D', 'E'].map(x => opt(x, x, x === Q.sec)).join('')}</select></label>
    <label>Student<input value="${esc(Q.stu)}" placeholder="All" onchange="Q.stu=this.value.trim();CARDS=[]"></label>
    <label>Marks from<select onchange="Q.src=this.value;CARDS=[]">${opt('auto', 'Automatic', Q.src === 'auto')}${opt('old', 'Old ERP card', Q.src === 'old')}${opt('new', 'Marks entered here', Q.src === 'new')}</select></label>` : ''}
  </div>`;
  let body = '';
  if (TAB === 'card') {
    const n = ((IX.summary[Q.ses] || {})[Q.exam] || {})[Q.cls] || 0;
    body = `${sel}<div class="pr-tools"><button onclick="showCards()" ${BUSY ? 'disabled' : ''}>${BUSY ? 'Loading…' : 'Show'}</button> <button onclick="printCards()">Print</button> <span class="pr-muted">${n} old ERP card(s) for this class and exam.</span></div>
      <style>${OLDCSS}</style><div id="prCards" class="pr-cards">${CARDS.join('')}</div>`;
  } else if (TAB === 'entry') {
    body = sel + (!MK ? '<p class="pr-muted">Opening…</p>' : !MK.mySubjects.length ? '<p class="pr-muted">No subject of this class is given to you in the timetable allocation.</p>' : `
      <label class="pr-inline">Subject <select onchange="MK.subject=this.value;render()">${MK.mySubjects.map(s => opt(s, s, s === MK.subject)).join('')}</select></label>
      <table class="pr-e"><tr><th>Name</th>${IX.parts.map(([, l, m]) => `<th>${esc(l)}<br>${m}</th>`).join('')}<th>Total<br>100</th><th>Grade</th></tr>
      ${MK.students.map(s => entryRow(s, (((MK.marks[Q.ses + '|' + Q.exam + '|' + Q.cls + '|' + MK.subject] || {}).rows || {})[s.id]) || {})).join('')}</table>
      <p class="pr-muted">Write AB for absent. Total = Pre. Test + Notebook + SEA + Term Exam; grade on the 9-point scale, as in the old ERP.</p><button onclick="saveMarks()">Save Marks</button>`);
  } else if (TAB === 'extra') {
    const X = MK && MK.extra ? MK.extra.rows || {} : {};
    const g = (sid, k) => `<select data-sid="${esc(sid)}" data-x="${k}">${['', 'A', 'B', 'C'].map(v => opt(v, v || '—', (X[sid] || {})[k] === v)).join('')}</select>`;
    const t = (sid, k, w) => `<input data-sid="${esc(sid)}" data-x="${k}" value="${esc((X[sid] || {})[k] || '')}" style="width:${w}px">`;
    body = sel + (!MK ? '<p class="pr-muted">Opening…</p>' : !MK.classTeacher ? '<p class="pr-muted">Only the class teacher or the Principal fills this.</p>' : `
      <table class="pr-e"><tr><th>Name</th><th>Art Education</th><th>Work Education</th><th>Health &amp; Physical</th><th>Discipline</th><th>Working Days</th><th>Presents</th><th>Class Teacher's Remark</th>${Q.exam === 'Term-2' ? '<th>Result</th><th>Promoted to</th>' : ''}</tr>
      ${MK.students.map(s => `<tr><td>${esc(s.name)}</td><td>${g(s.id, 'art')}</td><td>${g(s.id, 'work')}</td><td>${g(s.id, 'health')}</td><td>${g(s.id, 'disc')}</td><td>${t(s.id, 'days', 50)}</td><td>${t(s.id, 'present', 50)}</td><td>${t(s.id, 'remark', 260)}</td>${Q.exam === 'Term-2' ? `<td>${t(s.id, 'result', 60)}</td><td>${t(s.id, 'promoted', 50)}</td>` : ''}</tr>`).join('')}</table>
      <p class="pr-muted">Result left empty = PASS at 33% or more, and "Promoted to" = the next class, as on the old cards.</p><button onclick="saveExtra()">Save</button>`);
  } else {
    const p = IMP && IMP.prev;
    body = `<div class="pr-box"><p>Choose a file made from the old ERP: <b>7-Old-ERP-Progress-Reports-&lt;session&gt;.json</b> (one per session) or <b>8-Old-ERP-Photos.json</b>. You see what will come in first; nothing already here is changed.</p>
      <input type="file" accept=".json,application/json" onchange="readImport(this.files[0])">
      ${p ? `<p>${p.toAdd} new, ${p.unchanged} already here${p.linked !== undefined ? `, ${p.linked} photos will be linked to students${p.notInErp ? ' (' + p.notInErp + ' children are not in the new ERP; their photos are still kept for their old cards)' : ''}` : ''}.</p><button onclick="applyImport()">Import</button>` : ''}</div>
      <p class="pr-muted">Old ERP cards here: ${Object.entries(IX.summary).sort().map(([s, e]) => esc(s) + ' — ' + Object.entries(e).map(([x, c]) => esc(x) + ' ' + Object.values(c).reduce((a, b) => a + b, 0)).join(', ')).join(' · ') || 'none yet'}.</p>`;
  }
  $('prRoot').innerHTML = `<div class="pr"><div class="pr-tabs">${tabs.map(([k, l]) => `<button class="${TAB === k ? 'on' : ''}" onclick="TAB='${k}';${k === 'entry' || k === 'extra' ? 'openEntry()' : 'render()'}">${esc(l)}</button>`).join('')}</div>
    <h3 class="pr-title">${TAB === 'card' ? (Q.exam === 'Final' ? 'Progress Card(I-VIII)' : 'Term Progress Card') : TAB === 'entry' ? 'Marks Entry(Term)' : TAB === 'extra' ? 'Class Teacher Attendance/Remark' : 'Import from old ERP'}</h3>${body}</div>`;
  if (TAB === 'card' && CARDS.length) fillPhotos([...document.querySelectorAll('img[data-ph]')].map(i => i.getAttribute('data-ph')));
}
boot();
