'use strict';
// =====================================================================================================
// Teacher App — Students' tricks + Class test (owner, 27 Nov 2026). Teachers mostly use the Teacher App, so the
// two checks that were only on the web Quiz Duel page now live here too:
//   1. Students' tricks — a student shares a trick / formula / question / fact; a teacher of that class approves it,
//      then the whole class sees it as a Status in the Family app (tips_api.php).
//   2. Class test & rank — who took today's class test (made from the diary topic), score, level, challenges won
//      (game_api.php?a=tarena). Below 60% is red, so the teacher knows whom to help.
// Home card shows what is waiting. Screen: TAB 'arena'.
// =====================================================================================================
const AR = { tips: null, tipsAt: 0, tipsLoading: false, rep: {}, repLoading: false, cls: '', tab: 'tips' };
function arNeed(force) {
  if (!AR.tipsLoading && (force || !AR.tips || Date.now() - AR.tipsAt > 120000)) {
    AR.tipsLoading = true;
    api('tips_api.php?a=tlist').then(r => { AR.tips = r.tips || []; AR.tipsErr = ''; }).catch(e => { AR.tips = AR.tips || []; AR.tipsErr = e.message; })
      .finally(() => { AR.tipsAt = Date.now(); AR.tipsLoading = false; softRender(); });
  }
  const key = AR.cls || '_';
  if (!AR.repLoading && (force || !AR.rep[key] || Date.now() - AR.rep[key].at > 120000)) {
    AR.repLoading = true;
    api('game_api.php?a=tarena' + (AR.cls ? '&cls=' + encodeURIComponent(AR.cls) : '')).then(r => { AR.rep[key] = { ...r, at: Date.now() }; if (!AR.cls) AR.cls = r.cls; AR.rep[r.cls] = AR.rep[key]; })
      .catch(e => { AR.rep[key] = { error: e.message, at: Date.now() }; }).finally(() => { AR.repLoading = false; softRender(); });
  }
  return AR;
}
const AR_KIND = { trick: ['💡', 'Trick'], formula: ['📐', 'Formula'], question: ['❓', 'Question'], fact: ['🌍', 'Fact'] };
async function arDecide(id, status) {
  try { await api('tips_api.php?a=decide', { id, status }); toast(status === 'approved' ? 'Approved — the class sees it as a Status' : 'Not approved'); arNeed(true); }
  catch (e) { toast(e.message); }
}
async function arAddQ() {
  const R = AR.rep[AR.cls || '_'], t = R.topics[+(($('ar-top') || {}).value || 0)], v = id => (($(id) || {}).value || '').trim();
  const ans = +((document.querySelector('input[name=ar-ans]:checked') || {}).value || 0);
  try { await api('quiz_api.php?a=add', { cls: R.cls, subj: t.subj, topic: t.topic, text: v('ar-q'), opts: [0, 1, 2, 3].map(i => v('ar-o' + i)), ans });
    toast('Added — it is in the class test now ✓'); ['ar-q', 'ar-o0', 'ar-o1', 'ar-o2', 'ar-o3'].forEach(id => { if ($(id)) $(id).value = ''; }); }
  catch (e) { toast(e.message); }
}
function arHomeCard() {
  arNeed(); const pend = (AR.tips || []).filter(t => t.status === 'pending').length, R = AR.rep[AR.cls || '_'];
  if (!pend && !(R && R.date && R.rows)) return '';
  const took = R && R.rows ? R.rows.filter(r => r.test && r.test.done) : [];
  const low = took.filter(r => r.test.right / r.test.n < 0.6).length;
  return `<div class="card" onclick="go('arena')" style="cursor:pointer"><h3 style="margin:0 0 4px">🎯 Class test & students' tricks ${pend ? `<span class="pill bad">${pend} to check</span>` : ''}</h3>
    <p class="mut" style="margin:0">${R && R.date ? `Class ${esc(R.cls)}: <b>${took.length} of ${R.rows.length}</b> took the test${low ? ` · <b style="color:#B42318">${low} below 60%</b>` : ''}` : ''}${pend ? (R && R.date ? ' · ' : '') + `${pend} trick${pend > 1 ? 's' : ''} waiting for you` : ''}</p></div>`;
}
function vArena() {
  arNeed(); const pend = (AR.tips || []).filter(t => t.status === 'pending');
  const tabs = crTabs([['tips', 'chat', "Students' tricks", pend.length || ''], ['test', 'chart', 'Class test & rank']], AR.tab, k => `AR.tab='${k}';render()`);
  let body = '';
  if (AR.tab === 'tips') {
    if (!AR.tips) body = '<div class="load"><i></i></div>';
    else {
      body = (AR.tipsErr ? `<div class="ban warn">${esc(AR.tipsErr)}</div>` : '') + crHead("Students' tricks", 'Approve good ones — the whole class sees them as a Status, with the student’s short name.')
        + (pend.length ? pend.map(t => `<div class="card"><b>${AR_KIND[t.kind][0]} ${AR_KIND[t.kind][1]} · Class ${esc(t.cls)} · ${esc(t.name)}</b>${t.subj ? ` <small class="mut">${esc(t.subj)}</small>` : ''}
            <p style="margin:6px 0">${esc(t.text)}</p>${t.ans ? `<p class="mut" style="margin:0 0 6px">Answer: <b>${esc(t.ans)}</b></p>` : ''}
            <div style="display:flex;gap:8px"><button class="btn s go" onclick="arDecide(${jsq(t.id)},'approved')">✓ Approve</button><button class="btn s l" onclick="arDecide(${jsq(t.id)},'rejected')">Not now</button></div></div>`).join('')
          : crEmpty('✅', 'Nothing to check', 'When a student shares a trick, it appears here.'))
        + (AR.tips.filter(t => t.status !== 'pending').length ? `<div class="card"><b>Recently checked</b>${AR.tips.filter(t => t.status !== 'pending').slice(0, 15).map(t => `<p style="margin:6px 0 0;font-size:14px">${AR_KIND[t.kind][0]} ${esc(t.text)} <small class="mut">— ${esc(t.name)} · ${t.status === 'approved' ? '✓ shown' : 'not shown'}</small></p>`).join('')}</div>` : '');
    }
  } else {
    const R = AR.rep[AR.cls || '_'];
    if (!R) body = '<div class="load"><i></i></div>';
    else if (R.error) body = `<div class="ban warn">${esc(R.error)}</div>`;
    else {
      const took = R.rows.filter(r => r.test && r.test.done), avg = took.length ? Math.round(took.reduce((a, r) => a + r.test.right / r.test.n, 0) / took.length * 100) : 0, low = took.filter(r => r.test.right / r.test.n < 0.6), not = R.rows.filter(r => !r.test);
      body = (R.classes.length > 1 ? `<div class="chips" style="display:flex;gap:6px;overflow-x:auto;margin-bottom:8px">${R.classes.map(c => `<button class="btn s ${c === R.cls ? 'go' : 'l'}" onclick="AR.cls=${jsq(c)};arNeed(true);render()">${esc(c)}</button>`).join('')}</div>` : '')
        + crHead(`Class ${esc(R.cls)} · class test`, R.date ? `From the diary of ${esc(fmtDate(R.date))}: ${R.topics.map(t => esc(t.subj) + ' — ' + esc(t.topic)).join(' · ')}` : 'No diary topic in the last 3 days — write the topic in the diary and the test is made from it.')
        + (R.date ? crStats([['took', 'took the test', took.length, 'ok', 'of ' + R.rows.length], ['avg', 'average', avg + '%', avg >= 60 ? 'ok' : 'bad', ''], ['low', 'below 60%', low.length, low.length ? 'bad' : 'ok', 'need help']], '', () => '') : '')
        + (R.date && R.topics.length ? `<div class="card"><b>➕ Add a test question</b><p class="mut" style="margin:2px 0 8px;font-size:13px">It goes straight into the class test for this topic. Two or three questions are enough.</p>
            <select id="ar-top" class="inp">${R.topics.map((t, i) => `<option value="${i}">${esc(t.subj)} — ${esc(t.topic)}</option>`).join('')}</select>
            <textarea id="ar-q" class="inp" rows="2" maxlength="300" placeholder="Question" style="margin-top:6px"></textarea>
            ${[0, 1, 2, 3].map(i => `<label style="display:flex;gap:8px;align-items:center;margin-top:6px"><input type="radio" name="ar-ans" value="${i}" ${i === 0 ? 'checked' : ''}><input id="ar-o${i}" class="inp" maxlength="120" placeholder="Choice ${'ABCD'[i]}${i === 0 ? ' (tick the right one)' : ''}"></label>`).join('')}
            <button class="btn go" style="margin-top:8px;width:100%" onclick="arAddQ()">Add to the test</button></div>` : '')
        + (low.length ? `<div class="card"><b style="color:#B42318">Help these children</b>${low.map(r => `<p style="margin:6px 0 0">${esc(r.name)} — ${r.test.right}/${r.test.n}</p>`).join('')}</div>` : '')
        + (R.date && not.length ? `<div class="card"><b>Not taken yet (${not.length})</b><p class="mut" style="margin:6px 0 0">${not.map(r => esc(r.name)).join(', ')}</p></div>` : '')
        + `<div class="card"><b>Class rank · ${esc(R.month)}</b><table style="width:100%;font-size:14px;margin-top:6px;border-collapse:collapse"><tr class="mut"><td>#</td><td>Student</td><td>Level</td><td style="text-align:right">Points</td><td style="text-align:right">Right %</td></tr>
          ${R.rows.map((r, i) => `<tr style="border-top:1px solid #eef0f4"><td>${i + 1}</td><td>${esc(r.name)}</td><td>${r.tier.icon} ${esc(r.tier.name)}${r.tier.div ? ' ' + r.tier.div : ''}</td><td style="text-align:right">${r.rp}</td><td style="text-align:right">${r.acc}%</td></tr>`).join('')}</table>
          <p class="mut" style="margin:8px 0 0;font-size:12px">Questions come from the AI pack for your diary topic and from questions you add in Quiz Duel with the same topic.</p></div>`;
    }
  }
  return `<div class="card"><h2 style="margin:0">🎯 Class test & tricks</h2><p class="mut" style="margin:4px 0 0">What the children learnt from your diary, and the tricks they want to share.</p></div>` + tabs + body;
}
if (typeof vHome === 'function') { const _vhA = vHome; vHome = function () { return arHomeCard() + _vhA.apply(this, arguments); }; }
// Principal: Family App usage at a glance (owner, 28 Nov 2026: "kitne student login kiye hain kaise pata chalega")
const FAM = { d: null, at: 0, busy: false };
function famCard() {
  if (!FAM.busy && (!FAM.d || Date.now() - FAM.at > 300000)) { FAM.busy = true; api('famstats_api.php?a=data').then(r => { FAM.d = r; }).catch(() => {}).finally(() => { FAM.at = Date.now(); FAM.busy = false; softRender(); }); }
  if (!FAM.d) return ''; const T = FAM.d.totals, p = T.families ? Math.round(T.famLogged * 100 / T.families) : 0;
  return `<a class="card" href="famstats.php" target="_blank" rel="noopener" style="display:block;text-decoration:none;color:inherit"><h3 style="margin:0 0 4px">📱 Family App ${p < 50 ? '<span class="pill bad">' + p + '%</span>' : '<span class="pill ok">' + p + '%</span>'}</h3>
    <p class="mut" style="margin:0"><b>${T.famLogged} of ${T.families}</b> families logged in · <b>${T.today}</b> opened today · <b>${T.week}</b> this week · ${FAM.d.never.length} students not yet — tap to send invites</p></a>`;
}
if (typeof vPdash === 'function') { const _vpA = vPdash; vPdash = function () { return famCard() + arHomeCard() + _vpA.apply(this, arguments); }; }
