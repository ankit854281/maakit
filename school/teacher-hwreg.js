'use strict';
// =====================================================================================================
// Teacher App — Homework register (owner, 28 Nov 2026: "Adarsh Sir, Rati Ma'am aur Principal Sir me class ke according,
// student ke according show kare ki homework done or not done").
//   Class list → each class: % done, children who did not do it, subjects checked, homework given but not checked yet.
//   Tap a class → every child with ✓ / ½ / ✗ per subject (one day) or done % and "not done" count (7 / 30 days).
//   Tap a child → each homework with its status and who checked it.
// Data: duty_api.php?a=hwreg&from=&to= (checks = kind tchChk from Classroom → Check HW; pending = diary homework with no check after it).
// Who: Principal, Duty teacher (Adarsh Sir), homework sharer (Rati Ma'am) — every class. View 'hwreg'; Home / Principal cards.
// =====================================================================================================
const HR = { d: {}, busy: false, r: 'today', cls: '', f: 'all', open: '' };
function hrCan() { return !!(S && ME) && (isP() || (typeof isDutyMe === 'function' && isDutyMe()) || (typeof isRati === 'function' && isRati(me())) || (typeof isHwSharer === 'function' && isHwSharer())); }
function hrRange(r) { const t = todayISO(); return r === 'yday' ? [addDays(t, -1), addDays(t, -1)] : r === 'w' ? [addDays(t, -6), t] : r === 'm' ? [addDays(t, -29), t] : [t, t]; }
function hrNeed(r, force) {
  const [f, t] = hrRange(r), k = f + '|' + t, x = HR.d[k];
  if (!HR.busy && (force || !x || Date.now() - x.at > 120000)) { HR.busy = true;
    api('duty_api.php?a=hwreg&from=' + f + '&to=' + t).then(d => { HR.d[k] = { ...d, at: Date.now() }; }).catch(e => { HR.d[k] = { error: e.message, at: Date.now() }; }).finally(() => { HR.busy = false; softRender(); }); }
  return HR.d[k] || null;
}
const HR_ST = { done: ['✓', 'ok', 'Done'], half: ['½', 'half', 'Half'], not: ['✗', 'bad', 'Not done'] };
function hrClass(D, c) {
  const C = D.checks.filter(x => x.cls === c), kids = studentsOf(c).filter(s => !s.inactive), by = {};
  kids.forEach(s => { by[s.id] = { s, n: 0, d: 0, h: 0, x: 0, L: [] }; });
  C.forEach(ch => Object.entries(ch.rows).forEach(([sid, st]) => { const b = by[sid]; if (!b) return; b.n++; b[st === 'done' ? 'd' : st === 'half' ? 'h' : 'x']++; b.L.push({ date: ch.date, subj: ch.subj, hw: ch.hw, by: ch.by, st }); }));
  const B = Object.values(by), n = B.reduce((a, b) => a + b.n, 0), got = B.reduce((a, b) => a + b.d + b.h / 2, 0);
  const P = D.pending.filter(p => p.cls === c), due = P.filter(p => p.date === todayISO()), late = P.filter(p => p.date < todayISO());
  return { c, C, B, kids, pct: n ? Math.round(got * 100 / n) : null, notKids: B.filter(b => b.x), halfKids: B.filter(b => b.h && !b.x), subs: [...new Set(C.map(x => x.subj))], late, due };
}
function hrTone(p) { return p == null ? 'mut' : p >= 85 ? 'ok' : p >= 60 ? 'warn' : 'bad'; }
function vHwReg() {
  if (!hrCan()) return '<div class="card">The homework register is for the Principal, the Duty teacher and Rati Ma\'am.</div>';
  const D = hrNeed(HR.r), one = HR.r === 'today' || HR.r === 'yday', [f, t] = hrRange(HR.r);
  const rangeTxt = one ? fmtDate(f) : fmtDate(f) + ' – ' + fmtDate(t);
  let h = `<div class="card hr-top"><h2>📚 Homework register</h2>${HR.cls ? '' : '<p>Class-wise and child-wise — who did the homework and who did not, from the teachers’ homework checks.</p>'}
    <div class="hr-rng">${[['today', 'Today'], ['yday', 'Yesterday'], ['w', '7 days'], ['m', '30 days']].map(([k, l]) => `<button class="${HR.r === k ? 'on' : ''}" onclick="HR.r='${k}';HR.open='';render()">${l}</button>`).join('')}</div></div>`;
  if (!D) return h + '<div class="load"><i></i></div>';
  if (D.error) return h + `<div class="ban warn">${esc(D.error)}</div>`;
  if (HR.cls && D.classes.includes(HR.cls)) return h + hrClassView(D, hrClass(D, HR.cls), one, rangeTxt);
  const R = D.classes.map(c => hrClass(D, c)), checked = R.filter(x => x.C.length), not = R.reduce((a, x) => a + x.notKids.length, 0), late = R.reduce((a, x) => a + x.late.length, 0);
  h += `<div class="hr-sum"><div class="t-ok"><b>${checked.length}<small>/${R.length}</small></b><span>classes checked</span></div><div class="t-bad"><b>${not}</b><span>children did not do it</span></div><div class="t-warn"><b>${late}</b><span>homework not checked yet</span></div></div>`;
  h += `<p class="hr-cap">${esc(rangeTxt)} · tap a class to see every child</p><div class="hr-grid">${R.map(x => { const tn = hrTone(x.pct);
    return `<button class="hr-c hk-${tn}" onclick="HR.cls=${jsq(x.c)};HR.f='all';HR.open='';render()"><div class="hr-ch"><b>${esc(x.c)}</b><span class="hr-pct">${x.pct == null ? '—' : x.pct + '%'}</span></div>
      <div class="hr-bar"><i style="width:${x.pct || 0}%"></i></div>
      <small>${x.C.length ? `${x.subs.length} subject${x.subs.length === 1 ? '' : 's'} checked` : 'No check yet'}</small>
      <div class="hr-tags">${x.notKids.length ? `<em class="bad">✗ ${x.notKids.length} not done</em>` : x.C.length ? '<em class="ok">✓ all done</em>' : ''}${x.late.length ? `<em class="warn">⏳ ${x.late.length} not checked</em>` : ''}</div></button>`; }).join('')}</div>`;
  if (late) h += `<div class="card hr-pend"><b>⏳ Homework given but not checked yet</b><small>Checked in Classroom → Check HW at the next class of that subject.</small>${R.filter(x => x.late.length).map(x => x.late.map(p => `<div><span class="pill">${esc(p.cls)}</span> <b>${esc(p.subj)}</b> · ${esc(fmtDate(p.date))}<p>${esc(p.hw)} <i>— ${esc(p.teacher || '')}</i></p></div>`).join('')).join('')}</div>`;
  return h;
}
function hrClassView(D, X, one, rangeTxt) {
  const cnt = { all: X.B.length, not: X.notKids.length, half: X.halfKids.length, done: X.B.filter(b => b.n && !b.x && !b.h).length, none: X.B.filter(b => !b.n).length };
  let L = X.B.filter(b => HR.f === 'all' || (HR.f === 'not' && b.x) || (HR.f === 'half' && b.h && !b.x) || (HR.f === 'done' && b.n && !b.x && !b.h) || (HR.f === 'none' && !b.n));
  L = L.sort((a, b) => (b.x - a.x) || (b.h - a.h) || String(a.s.rollno || '').localeCompare(String(b.s.rollno || ''), 'en', { numeric: true }) || String(a.s.name).localeCompare(String(b.s.name)));
  let h = `<div class="hr-back"><button onclick="HR.cls='';render()">← All classes</button>${D.classes.length > 1 ? `<select onchange="HR.cls=this.value;HR.f='all';HR.open='';render()">${D.classes.map(c => `<option ${c === X.c ? 'selected' : ''}>${esc(c)}</option>`).join('')}</select>` : ''}</div>`;
  h += `<div class="card hr-hd hk-${hrTone(X.pct)}"><div><small>${esc(rangeTxt)}</small><h3>Class ${esc(X.c)}</h3><p>${X.C.length} homework check${X.C.length === 1 ? '' : 's'} · ${X.subs.map(esc).join(', ') || 'no subject checked'}</p></div><span class="hr-ring" style="--p:${X.pct || 0}"><b>${X.pct == null ? '—' : X.pct + '%'}</b><small>done</small></span></div>`;
  if (one && X.C.length) h += `<div class="hr-subs">${X.C.map(ch => { const v = Object.values(ch.rows), nn = v.filter(s => s === 'not').length;
    return `<div><b>${esc(ch.subj)}</b><p>${esc(ch.hw || 'Homework')}</p><small>Checked by ${esc(ch.by || '—')} · ${v.filter(s => s === 'done').length} done${nn ? ` · <span class="t-bad">${nn} not done</span>` : ''}</small></div>`; }).join('')}</div>`;
  if (X.late.length || X.due.length) h += `<div class="hr-wait">${X.late.map(p => `<span class="warn">⏳ ${esc(p.subj)} (${esc(fmtDate(p.date))}) not checked yet</span>`).join('')}${X.due.map(p => `<span>📝 ${esc(p.subj)} — due next class</span>`).join('')}</div>`;
  if (!X.C.length) return h + crEmpty('📒', 'No homework check in these days', 'Subject teachers check homework in Classroom → Check HW. It shows here by itself.');
  h += '<div class="hr-st">' + crStats([['all', 'All', cnt.all, 'violet', ''], ['not', 'Not done', cnt.not, 'red', ''], ['half', 'Half', cnt.half, 'amber', ''], ['done', 'All done', cnt.done, 'green', '']], HR.f, k => `HR.f='${k}';HR.open='';render()`) + '</div>';
  h += `<div class="hr-list">${L.map(b => { const s = b.s, p = b.n ? Math.round((b.d + b.h / 2) * 100 / b.n) : null, op = HR.open === s.id;
    const day = one ? `<div class="hr-chips">${b.L.length ? b.L.map(x => `<span class="${HR_ST[x.st][1]}">${HR_ST[x.st][0]} ${esc(x.subj)}</span>`).join('') : '<span class="mut">Not in the check (absent / new)</span>'}</div>`
      : `<div class="hr-mini"><div class="hr-bar sm hk-${hrTone(p)}"><i style="width:${p || 0}%"></i></div><small>${b.n ? `${p}% · ${b.d}/${b.n} done${b.x ? ` · <b class="t-bad">${b.x} not done</b>` : ''}${b.h ? ` · ${b.h} half` : ''}` : 'Not in any check'}</small></div>`;
    return `<div class="hr-s${b.x ? ' x' : ''}${op ? ' op' : ''}"><button onclick="HR.open=HR.open===${jsq(s.id)}?'':${jsq(s.id)};render()">${crAva(s)}<div class="hr-n"><b>${esc(s.name)}</b><small>Roll ${esc(s.rollno || '—')}${!one && b.x ? ' · ' + esc([...new Set(b.L.filter(x => x.st === 'not').map(x => x.subj))].join(', ')) : ''}</small>${day}</div><i class="hr-arw">${op ? '▴' : '▾'}</i></button>
      ${op ? `<div class="hr-det">${b.L.length ? b.L.map(x => `<div><span class="${HR_ST[x.st][1]}">${HR_ST[x.st][0]} ${HR_ST[x.st][2]}</span><p><b>${esc(x.subj)}</b> · ${esc(fmtDate(x.date))}<br>${esc(x.hw || '')}<small> — checked by ${esc(x.by || '—')}</small></p></div>`).join('') : '<p class="mut">No homework check for this child in these days.</p>'}</div>` : ''}</div>`; }).join('') || crEmpty('✅', 'Nobody here', 'Choose another filter.')}</div>`;
  return h;
}
// Home cards: Duty teacher + Rati Ma'am (teacher home) and the Principal dashboard
function hrHomeCard() {
  if (!hrCan()) return ''; const D = hrNeed('today'); if (!D || D.error) return '';
  const R = D.classes.map(c => hrClass(D, c)), checked = R.filter(x => x.C.length).length, not = R.reduce((a, x) => a + x.notKids.length, 0), late = R.reduce((a, x) => a + x.late.length, 0);
  return `<div class="card hr-home"><div class="row"><div><b>📚 Homework register — class-wise, child-wise</b><small>Today: <b>${checked}/${R.length}</b> classes checked · <b class="${not ? 't-bad' : ''}">${not}</b> children did not do it · <b>${late}</b> not checked yet</small></div><button class="btn go" onclick="HR.cls='';go('hwreg')">Open</button></div></div>`;
}
if (typeof vHome === 'function') { const _vhR = vHome; vHome = function () { const h = _vhR.apply(this, arguments); return (typeof pMode === 'function' && pMode()) ? h : hrHomeCard() + h; }; }
if (typeof vPdash === 'function') { const _vpR = vPdash; vPdash = function () { return hrHomeCard() + _vpR.apply(this, arguments); }; }
(function () { const st = document.createElement('style'); st.textContent = `
.hr-top h2{margin:0;font-size:19px}.hr-top h2+.hr-rng{margin-top:8px}.hr-top p{margin:4px 0 10px;color:#5b6472;font-size:13.5px}
.hr-rng{display:grid;grid-template-columns:repeat(4,1fr);gap:4px;background:#f1f3f8;border-radius:12px;padding:3px}.hr-rng button{border:0;background:none;padding:8px 0;border-radius:9px;font-weight:700;font-size:13px;color:#5b6472}.hr-rng button.on{background:#fff;color:#4338ca;box-shadow:0 2px 8px -4px #1e1b4b55}
.hr-sum{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin:10px 0}.hr-sum div{background:#fff;border-radius:14px;padding:10px 8px;text-align:center;border:1px solid #e8eaf2}.hr-sum b{display:block;font-size:22px;line-height:1.1}.hr-sum b small{font-size:13px;color:#94a3b8}.hr-sum span{font-size:11.5px;color:#5b6472;font-weight:600}
.hr-sum .t-ok b{color:#15803d}.hr-sum .t-bad b{color:#c0352b}.hr-sum .t-warn b{color:#b45309}.hr-cap{margin:4px 2px 8px;font-size:12.5px;color:#64748b;font-weight:600}
.hr-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}.hr-c{text-align:left;background:#fff;border:1px solid #e8eaf2;border-radius:16px;padding:10px 11px;border-top:4px solid var(--tc,#cbd5e1);box-shadow:0 8px 20px -18px #1e1b4b}
.hk-ok{--tc:#22c55e}.hk-warn{--tc:#f59e0b}.hk-bad{--tc:#ef4444}.hk-mut{--tc:#cbd5e1}
.hr-ch{display:flex;justify-content:space-between;align-items:baseline}.hr-ch b{font-size:17px}.hr-pct{font-weight:800;font-size:16px;color:var(--tc)}.hr-c.hk-mut .hr-pct{color:#94a3b8}
.hr-bar{height:7px;border-radius:99px;background:#eef0f6;overflow:hidden;margin:6px 0 5px}.hr-bar i{display:block;height:100%;border-radius:99px;background:var(--tc)}.hr-bar.sm{height:5px;margin:4px 0 2px}
.hr-c small{font-size:11.5px;color:#64748b}.hr-tags{display:flex;flex-wrap:wrap;gap:4px;margin-top:5px}.hr-tags em,.hr-wait span{font-style:normal;font-size:11px;font-weight:700;border-radius:99px;padding:2px 7px;background:#f1f5f9;color:#475569}
.hr-tags .bad{background:#fee2e2;color:#b91c1c}.hr-tags .ok{background:#dcfce7;color:#15803d}.hr-tags .warn,.hr-wait .warn{background:#fef3c7;color:#92400e}
.hr-pend>div{border-top:1px solid #eef1f4;padding:7px 0}.hr-pend p{margin:2px 0 0;font-size:13px}.hr-pend i{color:#94a3b8}.hr-pend small{display:block;color:#64748b;margin-bottom:4px}
.hr-back{display:flex;gap:8px;align-items:center;margin:2px 0 8px}.hr-back button{border:0;background:#eef2ff;color:#4338ca;font-weight:700;border-radius:99px;padding:7px 12px}.hr-back select{margin-left:auto;border:1px solid #e2e8f0;border-radius:10px;padding:6px 8px;font-weight:700}
.hr-hd{display:flex;align-items:center;gap:10px;color:#fff;background:linear-gradient(135deg,#312e81,#4338ca 60%,#0e9f9a)!important;border:0}.hr-hd.hk-bad{background:linear-gradient(135deg,#7f1d1d,#b91c1c 60%,#ea580c)!important}.hr-hd.hk-ok{background:linear-gradient(135deg,#065f46,#0f766e 60%,#0891b2)!important}
.hr-hd>div{flex:1;min-width:0}.hr-hd small{opacity:.85;font-size:12px}.hr-hd h3{margin:2px 0;font-size:21px;color:#fff}.hr-hd p{margin:0;font-size:12.5px;opacity:.92}
.hr-ring{--p:0;width:64px;height:64px;border-radius:50%;flex:none;display:grid;place-content:center;text-align:center;background:radial-gradient(closest-side,#fff 76%,transparent 78%),conic-gradient(#fff calc(var(--p)*1%),#ffffff44 0)}.hr-ring b{color:#1e1b4b;font-size:15px;line-height:1}.hr-ring small{color:#64748b;font-size:9.5px;opacity:1}
.hr-subs{display:flex;gap:8px;overflow-x:auto;margin:8px 0;padding-bottom:2px}.hr-subs>div{flex:0 0 72%;background:#fff;border:1px solid #e8eaf2;border-radius:14px;padding:9px 11px}.hr-subs b{font-size:14px;color:#0f766e}.hr-subs p{margin:2px 0;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.hr-subs small{font-size:11.5px;color:#64748b}
.hr-st .cr-sum{grid-template-columns:repeat(4,minmax(0,1fr))!important;gap:6px}.hr-st .cr-sum button{padding-left:2px;padding-right:2px}
.hr-wait{display:flex;flex-wrap:wrap;gap:5px;margin:6px 0}
.hr-list{display:flex;flex-direction:column;gap:6px;margin-top:8px}.hr-s{background:#fff;border:1px solid #e8eaf2;border-radius:14px;overflow:hidden}.hr-s.x{border-left:4px solid #ef4444}
.hr-s>button{display:flex;align-items:center;gap:9px;width:100%;text-align:left;background:none;border:0;padding:8px 10px}.hr-n{flex:1;min-width:0}.hr-n b{font-size:14px}.hr-n small{display:block;font-size:11.5px;color:#64748b}.hr-arw{font-style:normal;color:#94a3b8}
.hr-chips{display:flex;flex-wrap:wrap;gap:4px;margin-top:4px}.hr-chips span,.hr-det span{font-size:11px;font-weight:700;border-radius:8px;padding:2px 6px;white-space:nowrap}
.hr-chips .ok,.hr-det .ok{background:#dcfce7;color:#15803d}.hr-chips .half,.hr-det .half{background:#fef3c7;color:#92400e}.hr-chips .bad,.hr-det .bad{background:#fee2e2;color:#b91c1c}
.hr-det{padding:2px 12px 10px 52px}.hr-det>div{display:flex;gap:8px;align-items:flex-start;padding:6px 0;border-top:1px dashed #e8eaf2}.hr-det p{margin:0;font-size:12.5px}.hr-det small{color:#94a3b8}
.hr-home{border:2px solid #c7d2fe}.hr-home small{display:block;margin-top:2px}.hr-mini small b{font-size:inherit}.hr-n .t-bad,.hr-mini .t-bad,.hr-home .t-bad,.hr-subs .t-bad{color:#c0352b}`;
document.head.appendChild(st); })();
