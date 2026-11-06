// ============================================================================
//  Teacher App — Activity Calendar (owner, 6 Nov 2026: "kaun class mein kaun activity / competition kab — day ke
//  according, month ke according, educational, PG to 10th, syllabus se related; Mitra strong ho taaki koi na kahe
//  'samajh nahi aaya'"). Fixed national / world days only (movable festivals come from the school calendar).
//  Each day: why (the learning), the subject it ties to, one activity per group (PG–UKG / 1st–5th / 6th–10th) and
//  ready steps. Tab `acts`; card on Home and Principal dashboard; Mitra explains every step; Principal makes the
//  competition in one tap (teacher-comp.js). Loaded after teacher-comp.js.
// ============================================================================
'use strict';
// [MM-DD, title, why (the learning), subject, PG–UKG, 1st–5th, 6th–10th, extra step for this day]
const ACTS = [
  ['01-12', 'Rashtriya Yuva Diwas', 'Swami Vivekananda se seekh: aatmavishwas aur bolne ki himmat.', 'Hindi / English', 'Mera sapna (bolkar batana)', 'Kahani sunao: Vivekananda', 'Bhashan pratiyogita', 'Bachchon ko 2 minute ka bhashan likhne mein madad karein.'],
  ['01-24', 'Rashtriya Balika Diwas', 'Betiyon ki barabari aur samman.', 'EVS / SST', 'Meri didi / maa (drawing)', 'Poster: beti padhao', 'Nibandh: mahilaon ki bhumika', 'Mahila staff ko judge banayein.'],
  ['01-26', 'Gantantra Diwas', 'Samvidhan, desh aur kartavya ki samajh.', 'SST / EVS', 'Tiranga colouring', 'Deshbhakti geet (group)', 'Quiz: Samvidhan aur desh', 'Parade aur jhanda — 25 Jan ko abhyas.'],
  ['02-21', 'Matribhasha Diwas', 'Apni bhasha par garv; Hindi padhne-likhne ki aadat.', 'Hindi', 'Hindi kavita (rhyme)', 'Sundar lekh (Hindi)', 'Hindi kahani lekhan', 'Sundar lekh ke liye ek jaisa kaagaz dein.'],
  ['02-28', 'Rashtriya Vigyan Diwas', 'C.V. Raman ki khoj; sawaal poochhne aur prayog ki aadat.', 'Science / EVS', 'Tairta-doobta (float-sink) khel', 'Simple science model', 'Science exhibition / model', 'Model ke liye ghar ka saamaan hi — kharch nahi.'],
  ['03-03', 'Vishwa Vanyajeev Diwas', 'Jaanwar aur jungle bachana.', 'EVS / Science', 'Jaanwar ki aawaz pehchano', 'Mask banao (jaanwar)', 'Poster: vilupt hote jeev', ''],
  ['03-08', 'Antarrashtriya Mahila Diwas', 'Ghar aur samaj mein mahilaon ka yogdaan.', 'SST / English', 'Maa ke liye card', 'Meri maa (2 line bolna)', 'Mahila hero par bhashan', 'Mataon ko school bulayein (vaikalpik).'],
  ['03-22', 'Vishwa Jal Diwas', 'Paani bachana; jal chakra.', 'EVS / Science', 'Paani bachao rhyme', 'Drawing: paani bachao', 'Jal chakra model / quiz', ''],
  ['04-07', 'Vishwa Swasthya Diwas', 'Saaf-safai, achha khaana, haath dhona.', 'EVS / Science', 'Haath dhone ka khel', 'Healthy tiffin pratiyogita', 'Poster: santulit aahar', 'Tiffin pratiyogita ki soochna 2 din pehle parents ko.'],
  ['04-22', 'Prithvi Diwas (Earth Day)', 'Dharti, ped aur kachra-prabandhan.', 'EVS / Geography', 'Patte se chitra (leaf art)', 'Kabaad se jugaad', 'Paryavaran quiz / poster', ''],
  ['04-23', 'Vishwa Pustak Diwas', 'Padhne ki aadat; library.', 'English / Hindi', 'Picture book padhna', 'Kahani padho-sunao', 'Book review (1 page)', 'Har class mein 20 minute ka padhne ka samay.'],
  ['06-05', 'Vishwa Paryavaran Diwas', 'Ped lagana, plastic kam karna.', 'EVS / Science', 'Ghar par paudha (photo)', 'Ped lagao, naam do', 'Plastic-mukt school abhiyan', 'Chhuttiyon mein ho to ghar se photo mangayein.'],
  ['06-21', 'Antarrashtriya Yog Diwas', 'Sharir aur mann ki sehat.', 'Physical Education', 'Animal yoga (jaanwar jaise aasan)', 'Surya namaskar', 'Yog pratiyogita', ''],
  ['07-01', 'Doctors Day', 'Doctor aur sehat-sevaon ka samman.', 'EVS / Science', 'Doctor-doctor role play', 'Thank-you card (doctor)', 'Pratham chikitsa (first aid) demo', 'Kisi parent doctor ko bulayein (vaikalpik).'],
  ['07-29', 'Vishwa Baagh Diwas', 'Baagh aur jungle ka santulan.', 'EVS / Science', 'Baagh mask', 'Baagh par 5 baatein', 'Debate: jungle bachao', ''],
  ['08-15', 'Swatantrata Diwas', 'Azadi ki ladai aur senaniyon ka samman.', 'SST / Hindi', 'Fancy dress: senani', 'Deshbhakti kavita', 'Quiz: swatantrata sangram', 'Jhanda-vandan ka abhyas 13–14 Aug.'],
  ['08-29', 'Rashtriya Khel Diwas', 'Major Dhyan Chand; khel aur team spirit.', 'Physical Education', 'Daud aur balance khel', 'Relay race, kho-kho', 'Kabaddi / volleyball match', ''],
  ['09-05', 'Shikshak Diwas', 'Dr. Radhakrishnan; shikshak ka samman.', 'English / Hindi', 'Teacher ke liye card', 'Ek din ka teacher', 'Self-governance day', 'Badi class ke bachche chhoti class padhayein.'],
  ['09-08', 'Antarrashtriya Saksharta Diwas', 'Padhna-likhna sabka haq.', 'Hindi / English', 'Akshar pehchano khel', 'Shabd-khel (spelling)', 'Spelling bee', ''],
  ['09-14', 'Hindi Diwas', 'Hindi ka sammaan aur sahi likhna.', 'Hindi', 'Hindi rhyme', 'Sundar lekh', 'Kavita path / nibandh', ''],
  ['10-02', 'Gandhi Jayanti', 'Satya, ahinsa, swachhta.', 'SST / Hindi', 'Swachhta khel (kachra sahi dabbe mein)', 'Gandhi ji par kahani', 'Swachhta abhiyan + nibandh', 'Ek din pehle class saaf karne ka abhiyan.'],
  ['10-16', 'Vishwa Khadya Diwas', 'Khaana barbaad na karna; poshan.', 'EVS / Science', 'Phal-sabzi pehchano', 'Bina aag ka khaana (salad)', 'Poshan quiz / chart', 'Chaku sirf teacher ki nigrani mein.'],
  ['10-31', 'Rashtriya Ekta Diwas', 'Sardar Patel; desh ki ekta.', 'SST', 'Haath pakad ke chain', 'Rajya aur pehnava (chart)', 'Run for Unity / quiz', ''],
  ['11-14', 'Bal Diwas', 'Chacha Nehru; bachchon ki pratibha ka utsav.', 'All', 'Fancy dress', 'Drawing / story telling', 'Quiz / talent show', 'Teachers bachchon ke liye chhota programme karein.'],
  ['11-26', 'Samvidhan Diwas', 'Adhikar aur kartavya.', 'SST / Civics', 'Achhi aadatein (good manners)', 'Class ke niyam banao', 'Prastavana path + quiz', 'Prastavana ki copy har class mein lagayein.'],
  ['12-02', 'Pradushan Niyantran Diwas', 'Hawa, paani, shor ka pradushan.', 'EVS / Science', 'Saaf hawa drawing', 'Poster: pradushan', 'Debate: pataakhe aur pradushan', ''],
  ['12-14', 'Urja Sanrakshan Diwas', 'Bijli bachana.', 'EVS / Science', 'Light band karo khel', 'Bijli bachao slogan', 'Solar / urja model', ''],
  ['12-22', 'Rashtriya Ganit Diwas', 'Ramanujan; ganit mein maza.', 'Maths', 'Ginti aur aakar khel', 'Maths quiz / table race', 'Maths olympiad / puzzle', 'Sawaal syllabus ke chapter se hi rakhein.'],
  ['12-25', 'Christmas / Sharad utsav', 'Baantne aur madad karne ki khushi.', 'English', 'Santa craft', 'Card making', 'Carol / skit (English)', '']
];
const ACT_GROUPS = [['pre', 'PG – UKG', ['PG', 'LKG', 'UKG']], ['pri', '1st – 5th', ['1st', '2nd', '3rd', '4th', '5th']], ['sec', '6th – 10th', ['6th', '7th', '8th', '9th', '10th']]];
function actYearDate(mmdd) { const t = todayISO(), y = +t.slice(0, 4), d = y + '-' + mmdd; return d >= addDays(t, -2) ? d : (y + 1) + '-' + mmdd; }
function actList() { return ACTS.map(a => ({ date: actYearDate(a[0]), title: a[1], why: a[2], subj: a[3], g: { pre: a[4], pri: a[5], sec: a[6] }, extra: a[7] })).sort((a, b) => a.date.localeCompare(b.date)); }
function actMyGroups() { if (isP() && pMode()) return ACT_GROUPS.map(g => g[0]); const my = myClasses(); return ACT_GROUPS.filter(g => g[2].some(c => my.includes(c))).map(g => g[0]); }
// Syllabus link: the newest diary topic of this subject in a class (last 21 days), so the activity can use the chapter now being taught.
function actTopic(cls, subj) {
  const keys = String(subj).toLowerCase().split(/[\s/]+/).filter(w => w.length > 2 && w !== 'all'); if (!keys.length) return '';
  for (let i = 0; i < 21; i++) { const d = addDays(todayISO(), -i), byT = S.diary[d] || {};
    for (const t of Object.keys(byT)) for (const e of Object.values(byT[t] || {})) if (e && e.cls === cls && e.topic && keys.some(k => String(e.subj || '').toLowerCase().includes(k))) return e.subj + ': ' + e.topic; }
  return '';
}
function actDays(d) { const n = Math.round((new Date(d + 'T00:00:00') - new Date(todayISO() + 'T00:00:00')) / 864e5); return n === 0 ? 'Aaj' : n === 1 ? 'Kal' : n < 0 ? 'Ho gaya' : n + ' din baad'; }

function actCard() {
  const L = actList().filter(a => a.date >= todayISO() && a.date <= addDays(todayISO(), 12)).slice(0, 2); if (!L.length) return '';
  const mg = actMyGroups();
  return `<div class="card ac-card"><div class="cp-dh"><span>📅</span><div><small>Aane wali activity</small><b>${L.map(a => esc(a.title) + ' · ' + actDays(a.date)).join('  |  ')}</b></div></div>
    ${L.map((a, i) => `<div class="ac-mini"><b>${esc(a.title)}</b> <small>${esc(fmtDate(a.date))}</small><div>${ACT_GROUPS.filter(g => mg.includes(g[0])).map(g => `<span>${esc(g[1])}: ${esc(a.g[g[0]])}</span>`).join('')}</div></div>`).join('')}
    <div class="btns"><button class="btn go" onclick="actMitra('${L[0].date}')">🤖 Kaise karein?</button><button class="btn l" onclick="go('acts')">Poora calendar</button></div></div>`;
}
function vActs() {
  const all = actList(), t = todayISO(), months = []; for (let i = 0; i < 6; i++) { const d = new Date(t.slice(0, 7) + '-01T00:00:00'); d.setMonth(d.getMonth() + i); months.push(d.getFullYear() + '-' + pad(d.getMonth() + 1)); }
  const m = months.includes(P.m) ? P.m : months[0], L = all.filter(a => a.date.slice(0, 7) === m), mg = actMyGroups(), my = myClasses();
  const mName = k => new Date(k + '-01T00:00:00').toLocaleDateString(LOC(), { month: 'short' });
  return `<div class="card ac-h"><h3>📅 Activity Calendar</h3><p class="mut" style="margin:0">Har din ki seekh, har class ki activity — syllabus se judi. 🤖 dabaiye, Mitra poora tarika batayega.</p></div>
    ${seg(months.map(k => [k, mName(k)]), m, "(k=>go('acts',{m:k}))")}
    ${L.map(a => `<div class="card ac-i ${a.date < t ? 'past' : ''}"><div class="ac-top"><span class="ac-d"><b>${+a.date.slice(8)}</b><small>${esc(mName(a.date.slice(0, 7)))}</small></span><div><b>${esc(a.title)}</b><small>${actDays(a.date)} · ${esc(a.subj)}</small></div></div>
      <p class="ac-why">🎯 ${esc(a.why)}</p>
      <div class="ac-g">${ACT_GROUPS.map(g => `<div class="${mg.includes(g[0]) ? 'mine' : ''}"><small>${esc(g[1])}</small><b>${esc(a.g[g[0]])}</b></div>`).join('')}</div>
      ${(() => { const tp = my.filter(c => ACT_GROUPS.some(g => mg.includes(g[0]) && g[2].includes(c))).map(c => [c, actTopic(c, a.subj)]).filter(x => x[1]).slice(0, 3); return tp.length ? `<p class="ac-syl">📖 Abhi syllabus mein: ${tp.map(([c, x]) => `<b>${esc(c)}</b> — ${esc(x)}`).join(' · ')} — activity isi chapter se jodiye.</p>` : ''; })()}
      <div class="btns"><button class="btn s go" onclick="actMitra('${a.date}')">🤖 Kaise karein?</button><button class="btn s l" onclick="actCopy('${a.date}')">📢 Staff notice copy</button>${isP() && a.date >= t ? `<button class="btn s w" onclick="actComp('${a.date}')">🎉 Competition banao</button>` : ''}</div></div>`).join('') || '<div class="card empty">Is mahine koi tay din nahi. Tyohar ki activity school calendar se jodiye.</div>'}`;
}
function actFind(date) { return actList().find(a => a.date === date); }
function actNotice(a) {
  return `📢 *${a.title} — ${fmtLong(a.date)}*\n🎯 ${a.why}\n\n${ACT_GROUPS.map(g => `• *${g[1]}:* ${a.g[g[0]]}`).join('\n')}\n\nTaiyari: ${addDays(a.date, -3) >= todayISO() ? fmtDate(addDays(a.date, -3)) + ' tak' : 'aaj se'} bachchon ko batayein. Activity class ke chapter (${a.subj}) se jodiye.${a.extra ? '\n' + a.extra : ''}\nPhoto app mein lagayein, certificate sabko milega.\n— ${myName()}`;
}
function actCopy(date) { const a = actFind(date); if (!a) return; window.__CT = [actNotice(a)]; certCopy(0); }
async function actComp(date) {
  const a = actFind(date); if (!a) return; if (!confirm(`“${a.title}” ki competition banayein? (${fmtDate(a.date)})`)) return;
  try { await api('teacher_api.php?a=comp', { op: 'event', title: a.title + ' ' + a.date.slice(0, 4), date: a.date, cats: [a.title.slice(0, 40)], secs: COMP_DEF.secs.map(([name, classes]) => ({ name, classes })) });
    toast('Competition ban gaya. Teachers team bana sakte hain.'); COMP = null; go('comp'); } catch (e) { toast(e.message); }
}
// Mitra, step by step for one day — written so that nobody needs to ask "kya karna hai".
function actMitra(date) {
  const a = actFind(date); if (!a) return; const mg = actMyGroups(), d3 = addDays(a.date, -3), d1 = addDays(a.date, -1);
  const steps = [
    ['📢', 'Aaj: teachers ko batayein', `“📢 Staff notice copy” dabaiye aur staff group mein bhejiye. Har group ki activity usme likhi hai.`],
    ['🧒', `${d3 >= todayISO() ? fmtDate(d3) : 'Jaldi'}: bachchon ko batayein`, `Class mein bataiye kya karna hai aur ghar se kya laana hai. Kharch wala saamaan nahi — ghar ka saamaan hi.`],
    ['📖', 'Syllabus se jodiye', `Is din ka vishay: ${a.subj}. Class mein abhi jo chapter chal raha hai, activity usi se jodiye — jaise sawaal, chitra ya model usi chapter ke.`],
    ['⚖️', `${fmtDate(d1)}: judge aur niyam`, `2 teacher judge. Number 3 baaton par: 1) vishay se juda hai, 2) mehnat aur saaf-safai, 3) apna naya idea. Har baat 5 number.`],
    ['🎉', `${fmtDate(a.date)}: activity ka din`, `Bachchon ko team ya akele bhaag lene dein. Har team / bachche ki photo app mein Competition mein lagayein.`],
    ['🥇', 'Result aur certificate', `Jeetne walon par 🥇🥈🥉. Baaki sabko participation certificate apne aap — har bachche ko certificate milega.`],
    ['💬', 'Parents tak', `Certificate ke saath taiyaar message parent ko bhejiye, aur story photo Status par lagaiye.`]];
  let o = $('nwBox'); if (o) o.remove(); o = document.createElement('div'); o.id = 'nwBox'; o.className = 'nw';
  o.innerHTML = `<div class="nw-in"><div class="nw-bot"><span class="nw-face">🤖</span><div class="nw-bub"><b>${esc(a.title)} · ${esc(fmtDate(a.date))}</b><br>🎯 ${esc(a.why)}</div></div>
    <div class="ac-g">${ACT_GROUPS.map(g => `<div class="${mg.includes(g[0]) ? 'mine' : ''}"><small>${esc(g[1])}</small><b>${esc(a.g[g[0]])}</b></div>`).join('')}</div>
    <ol class="nw-l">${steps.map(([e, t2, x]) => `<li><span>${e}</span><div><b>${esc(t2)}</b><small>${esc(x)}</small></div></li>`).join('')}${a.extra ? `<li><span>⭐</span><div><b>Is din ke liye khaas</b><small>${esc(a.extra)}</small></div></li>` : ''}</ol>
    <div class="btns"><button class="btn l" onclick="actCopy('${a.date}')">📢 Staff notice copy</button>${isP() && a.date >= todayISO() ? `<button class="btn w" onclick="this.closest('.nw').remove();actComp('${a.date}')">🎉 Competition banao</button>` : ''}</div>
    <button class="btn go full" onclick="this.closest('.nw').remove()">Samajh gaya 👍</button><small class="nw-f">Phir bhi kuch na samjhe to Principal se poochhiye — ya ☰ → Activity Calendar.</small></div>`;
  document.body.appendChild(o);
}
(function () {
  if (typeof MENU !== 'undefined') MENU.forEach(g => { if (g[0] === 'Students') g[1].splice(1, 0, ['acts', {}, 'list', 'Activity Calendar', 'Kaun din, kaun activity, kaise']); });
})();
