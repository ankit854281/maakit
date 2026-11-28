'use strict';
// =====================================================================================================
// Family Zone (owner, 27 Nov 2026) — for parents and students, in the web ERP and inside the Family app.
//   ptmr  Tomorrow      next school day: bells with timing, subject + teacher, what to bring, homework to finish, bag tick-list
//   pcal  Calendar      month view: holidays, exams, events, PTM and the child's own present/absent days
//   prec  My record     attendance (session + this month + streak), exams, points, game level, achievements
//   psyl  Exam syllabus chapters marked for each exam + date sheets
//   pzone Learning Hub  basics, tricks, study tips, short stories, fun facts, extra class, classmates' tricks
//   pset  Settings      language, bigger text, screen time (today / 7 days / daily reminder), PIN, log out
// Status stories (WhatsApp-style rings) = word of the day, trick, fact, story and teacher-approved classmates' tricks.
// Only this family's data (PD from pdata) and teacher-approved posts (tips_api.php) are shown. Loaded after app.js.
// =====================================================================================================

// ---------------------------------------------------------------- content (original, checked; easy English)
const GYAAN = {
  basics: [
    { b: 'p', t: 'Place value', x: 'In 345, 3 is in the hundreds place (300), 4 is in the tens place (40) and 5 is in the ones place (5). 300 + 40 + 5 = 345.' },
    { b: 'p', t: 'Odd and even', x: 'Even numbers end in 0, 2, 4, 6 or 8 and can be shared into two equal groups. Odd numbers end in 1, 3, 5, 7 or 9.' },
    { b: 'p', t: 'Noun and verb', x: 'A noun is a naming word: Riya, school, mango. A verb is an action word: run, eat, write. "Riya eats a mango."' },
    { b: 'p', t: 'Shapes', x: 'Triangle: 3 sides. Square: 4 equal sides. Rectangle: 4 sides, opposite sides equal. Circle: no corners.' },
    { b: 'p', t: 'Adjective', x: 'An adjective describes a noun: a red ball, a tall tree, a kind girl.' },
    { b: 'p', t: 'Money', x: '100 paise = ₹1. ₹50 + ₹20 + ₹5 = ₹75.' },
    { b: 'p', t: 'Time', x: '60 seconds = 1 minute, 60 minutes = 1 hour, 24 hours = 1 day, 7 days = 1 week.' },
    { b: 'p', t: 'Plants need', x: 'Plants need sunlight, water, air and soil to grow. Leaves make food for the plant.' },
    { b: 'u', t: '(a + b)²', x: '(a + b)² = a² + 2ab + b². Example: (3 + 2)² = 9 + 12 + 4 = 25.' },
    { b: 'u', t: 'Area of a circle', x: 'Area = πr² and circumference = 2πr. Take π = 22/7 or 3.14.' },
    { b: 'u', t: 'Speed', x: 'Speed = distance ÷ time. A bus that goes 120 km in 3 hours has a speed of 40 km/h.' },
    { b: 'u', t: 'Tenses', x: 'Past: I wrote. Present: I write. Future: I will write. Continuous: I am writing.' },
    { b: 'u', t: 'Photosynthesis', x: 'Carbon dioxide + water → (sunlight, chlorophyll) → glucose + oxygen. It happens in the leaves.' },
    { b: 'u', t: "Newton's first law", x: 'An object stays at rest, or keeps moving in a straight line, unless a force acts on it.' },
    { b: 'u', t: 'Percentage', x: 'Percentage = (part ÷ whole) × 100. 18 out of 24 = 18 ÷ 24 × 100 = 75%.' },
    { b: 'u', t: 'Simple interest', x: 'SI = (P × R × T) ÷ 100. ₹1,000 at 5% for 2 years = ₹100.' }
  ],
  tricks: [
    { t: 'Multiply by 11', x: 'For a 2-digit number, add the two digits and put the sum in the middle. 52 × 11 → 5 (5+2) 2 = 572. If the sum is 10 or more, carry 1: 57 × 11 = 627.' },
    { t: 'Squares ending in 5', x: 'For 35²: 3 × 4 = 12, then write 25 → 1225. For 75²: 7 × 8 = 56 → 5625.' },
    { t: 'Multiply by 5', x: 'Halve the number, then multiply by 10. 48 × 5 → 24 × 10 = 240.' },
    { t: 'Multiply by 9', x: 'Multiply by 10, then take the number away once. 37 × 9 = 370 − 37 = 333.' },
    { t: '9 table on fingers', x: 'Hold up 10 fingers. For 9 × 4, fold the 4th finger: 3 fingers on the left, 6 on the right → 36.' },
    { t: 'Divisible by 3 or 9', x: 'Add the digits. 471 → 4 + 7 + 1 = 12, and 12 is divisible by 3, so 471 is too. If the digit sum is divisible by 9, the number is divisible by 9.' },
    { t: 'Divisible by 4', x: 'Look only at the last two digits. 7,316 → 16 is divisible by 4, so 7,316 is too.' },
    { t: 'Percent swap', x: 'x% of y = y% of x. 8% of 50 is hard? Do 50% of 8 = 4.' },
    { t: 'Round and fix', x: '298 + 457 → 300 + 457 = 757, then take away the extra 2 → 755.' },
    { t: 'Double and halve', x: '25 × 16 = 50 × 8 = 100 × 4 = 400. Double one number, halve the other.' },
    { t: 'Planets in order', x: '"My Very Educated Mother Just Served Us Noodles" → Mercury, Venus, Earth, Mars, Jupiter, Saturn, Uranus, Neptune.' },
    { t: 'Rainbow colours', x: 'VIBGYOR → Violet, Indigo, Blue, Green, Yellow, Orange, Red.' },
    { t: 'A or an?', x: 'Go by the sound, not the letter: an hour, an umbrella, a university, a one-rupee coin.' },
    { t: 'Its or it’s?', x: 'It’s = it is ("It’s raining"). Its = belongs to it ("The dog wagged its tail").' }
  ],
  tips: [
    { t: '25 + 5 rule', x: 'Study for 25 minutes, then rest for 5. Small children can do 15 + 5. Four rounds, then a longer break.' },
    { t: 'Teach someone', x: 'Explain today’s lesson to Mummy, Papa or a younger brother or sister. If you can teach it, you know it.' },
    { t: 'Read, close, say', x: 'Read a paragraph, close the book and say it in your own words. Then check what you missed.' },
    { t: 'Revise 1-3-7', x: 'Revise a lesson the next day, after 3 days and after 7 days. It then stays for a long time.' },
    { t: 'Mistake copy', x: 'Keep one thin copy. Write every mistake and the right answer. Read it before every test.' },
    { t: 'Hard subject first', x: 'Do the most difficult subject first, when your mind is fresh. Keep the easy one for last.' },
    { t: 'Phone away', x: 'Keep the phone in another room while studying. One notification can break 10 minutes of focus.' },
    { t: 'Sleep is study', x: 'Sleep 9–10 hours. The brain fixes what you learnt while you sleep.' },
    { t: 'Formula card', x: 'Write all formulas of a chapter on one small card. Read it on the way to school.' },
    { t: 'Water and a walk', x: 'Feeling sleepy while studying? Drink a glass of water and walk for two minutes.' }
  ],
  stories: [
    { t: 'The cracked pot', x: 'A water carrier had two pots. One was cracked and leaked half its water every day, and it felt ashamed. One morning the carrier said, "Look at your side of the path." Flowers were growing all along it — the leaking water had watered them every day.', m: 'Moral: everyone has a strength of their own.' },
    { t: 'The thirsty crow', x: 'A thirsty crow found a pot with very little water at the bottom. Its beak could not reach. It did not give up: it dropped small stones into the pot, one by one, until the water rose to the top. Then it drank.', m: 'Moral: think, keep trying, and you will find a way.' },
    { t: 'The hare and the tortoise', x: 'A fast hare laughed at a slow tortoise and agreed to a race. Sure of winning, the hare slept under a tree. The tortoise kept walking, step by step, and crossed the finish line first.', m: 'Moral: slow and steady wins the race.' },
    { t: 'The lion and the mouse', x: 'A lion let a tiny mouse go instead of eating it. Days later the lion was caught in a hunter’s net. The mouse heard it roar, came running and cut the ropes with its teeth.', m: 'Moral: no act of kindness is too small.' },
    { t: 'Ten minutes a day', x: 'Riya and Aman had the same exam. Aman studied for six hours the night before. Riya read for ten minutes every day for a month. On exam day Aman was tired and confused; Riya was calm and remembered everything.', m: 'Moral: ten minutes every day beats six hours in one night.' },
    { t: 'The mistake copy', x: 'Kabir kept losing marks in maths. His teacher gave him a thin copy: "Write every mistake here, and the right way." Each week the list got shorter. In the next test, he did not repeat a single old mistake.', m: 'Moral: learn from a mistake so you never repeat it.' },
    { t: 'The girl who asked', x: 'Meera never understood fractions, but she was shy to ask. One day she raised her hand. The teacher smiled and explained again — and five other children said, "We didn’t understand either!"', m: 'Moral: asking a question takes courage — and helps everyone.' },
    { t: 'The ant and the grasshopper', x: 'All summer the ant carried grain while the grasshopper sang and laughed. When winter came, the ant had food and a warm home. The grasshopper was cold and hungry, and learnt to prepare for tomorrow.', m: 'Moral: work hard today to be ready for tomorrow.' }
  ],
  facts: [
    'An octopus has three hearts.', 'Light from the Sun takes about 8 minutes to reach the Earth.', 'A day on Venus is longer than its whole year.',
    'The Ganga is the longest river in India.', 'Some kinds of bamboo can grow almost one metre in a single day.', 'Your heart beats about one lakh times every day.',
    'Indian mathematicians first used zero as a number; Brahmagupta wrote rules for it about 1,400 years ago.', 'Ice floats because water expands when it freezes.',
    'Sound travels about four times faster in water than in air.', 'About 71% of the Earth’s surface is covered by water.',
    'Chandrayaan-3 made India the first country to land near the Moon’s south pole, on 23 August 2023.', 'Aryabhata, launched in 1975, was India’s first satellite.',
    'The blue whale is the largest animal ever known — bigger than any dinosaur.', 'Spiders are not insects: spiders have 8 legs, insects have 6.',
    'Honeybees tell each other where flowers are with a "waggle dance".', 'The tiger is India’s national animal and the peacock is the national bird.'
  ]
};
const FZ_KIND = { trick: ['💡', 'Shortcut'], formula: ['📐', 'Formula'], question: ['❓', 'Question'], fact: ['🌍', 'Fact'] };
const fzDay = () => Math.floor(new Date(todayISO() + 'T00:00:00').getTime() / 86400000);
const fzPick = (list, k = 0) => list[(fzDay() + k) % list.length];
const fzBand = kid => (/^(PG|LKG|UKG|1st|2nd|3rd|4th|5th)$/.test(kid.cls) ? 'p' : 'u');
const fzLS = { get(k, d) { try { const v = localStorage.getItem(k); return v == null ? d : JSON.parse(v); } catch (e) { return d; } }, set(k, v) { try { localStorage.setItem(k, JSON.stringify(v)); } catch (e) {} } };

// ---------------------------------------------------------------- shared helpers
function fzNextSchoolDay(kid) {
  let d = todayISO();
  for (let i = 0; i < 14; i++) {
    d = addDays(d, 1); const wd = new Date(d + 'T00:00:00').getDay();
    if (wd === 0) continue;
    if (typeof holidayOn === 'function' && holidayOn(d)) continue;
    if ((PD.clsHol || []).some(h => h.cls === kid.cls && h.date === d)) continue;
    return { date: d, idx: wd - 1 };
  }
  return null;
}
function fzMonthAtt(kid, ym) { const r = PD.att.filter(a => a.sid === kid.id && a.date.slice(0, 7) === ym); const p = r.filter(x => x.present).length; return { p, n: r.length, pct: r.length ? Math.round(p / r.length * 100) : null }; }
function fzStreak(kid) { const r = PD.att.filter(a => a.sid === kid.id).sort((a, b) => b.date.localeCompare(a.date)); let n = 0; for (const x of r) { if (!x.present) break; n++; } return n; }
function fzExamPct(kid) {
  return (PD.term || []).filter(t => t.sid === kid.id && t.pct != null).map(t => ({ name: t.exam, pct: t.pct })).concat((S.exams || []).map(e => { const ms = PD.marks.filter(m => m.sid === kid.id && m.exam === e.id); let g = 0, mx = 0;
    ms.forEach(m => { if (m.mark !== 'AB' && m.mark !== '' && m.max) { g += +m.mark; mx += +m.max; } }); return mx ? { name: e.name, pct: Math.round(g / mx * 1000) / 10, ms } : null; }).filter(Boolean));
}
function fzBack(title, sub) { return `${kidSwitch()}${head(title, sub || '')}`; }

// ---------------------------------------------------------------- 1. TOMORROW (Kal ki taiyari)
function vPTomorrow() {
  const kid = pKid(); if (!kid) return vPHome();
  const nx = fzNextSchoolDay(kid), today = todayISO();
  if (!nx) return fzBack('Next school day', 'Class ' + esc(kid.cls)) + '<div class="card text-sm">There is no school day in the next two weeks.</div>';
  const tt = (PD.tt || {})[kid.cls], day = tt && tt[nx.idx] ? tt[nx.idx] : null;
  const exams = examItems().filter(e => e.cls.includes(kid.cls) && e.date === nx.date);
  const subs = day ? [...new Set(day.filter(Boolean).map(x => x.s))] : [];
  const bagKey = 'sn_bag_' + kid.id + '_' + nx.date, bag = fzLS.get(bagKey, {});
  const hwToday = PD.hw.filter(h => h.cls === kid.cls && h.date === today && (h.hw || h.books));
  const pending = ((PD.academics || {}).pending || []).filter(t => t.sid === kid.id);
  const bells = day ? BELLS.map((b, i) => { const a = day[i];
    return `<div class="fz-bell"><span class="fz-time">${t12(b.s)}<small>${t12(b.e)}</small></span><span class="fz-dot" style="background:${a ? fzColor(a.s) : '#cbd5e1'}"></span>
      <span class="flex-1 min-w-0"><b>${a ? esc(a.s) : 'Self study'}</b>${a && a.t ? `<small class="block text-slate-500">${esc(a.t)}</small>` : ''}</span><span class="text-xs text-slate-400">${b.n}</span></div>`
      + (i === RECESS_AFTER ? '<div class="fz-bell fz-recess"><span class="fz-time">10:45<small>11:05</small></span><span class="fz-dot" style="background:#f2a93b"></span><b class="flex-1">Recess 🍎</b></div>' : ''); }).join('') : '';
  return fzBack('Next school day · ' + fmtDate(nx.date), 'Class ' + esc(kid.cls) + ' · timetable, homework and bag checklist')
    + (exams.length ? `<div class="card border-2 border-rose bg-rose-50"><h3 class="h3">📝 Exam on this day</h3>${exams.map(e => `<p class="mt-1 font-semibold">${esc(e.title)}</p>`).join('')}<p class="text-sm text-slate-600 mt-1">Revise this evening and get a good night’s sleep.</p></div>` : '')
    + `<div class="card"><div class="flex justify-between items-baseline gap-2"><h3 class="h3">⏰ Timetable</h3><span class="text-xs text-slate-500">Assembly 8:40 AM · leave 1:30 PM</span></div>
      ${day ? `<div class="mt-2">${bells}</div>` : '<p class="text-sm text-slate-500 mt-1">The school has not shared this class’s timetable yet.</p>'}</div>`
    + `<div class="card"><h3 class="h3">🎒 Bag checklist</h3><p class="text-xs text-slate-500">Tick each item as you pack it.</p>
      ${subs.length ? `<div class="fz-bag mt-2">${subs.map(s => `<label class="${bag[s] ? 'on' : ''}"><input type="checkbox" ${bag[s] ? 'checked' : ''} onchange="fzBag(${jsq(bagKey)},${jsq(s)},this.checked)"> ${esc(s)} book &amp; copy</label>`).join('')}
        <label class="${bag._diary ? 'on' : ''}"><input type="checkbox" ${bag._diary ? 'checked' : ''} onchange="fzBag(${jsq(bagKey)},'_diary',this.checked)"> School diary, pencil box, water bottle</label></div>
        <p class="text-sm mt-2 ${Object.values(bag).filter(Boolean).length >= subs.length + 1 ? 'text-leaf font-semibold' : 'text-slate-500'}">${Object.values(bag).filter(Boolean).length} of ${subs.length + 1} packed${Object.values(bag).filter(Boolean).length >= subs.length + 1 ? ' — all packed ✅' : ''}</p>` : '<p class="text-sm text-slate-500 mt-1">Bag list appears once the timetable is shared.</p>'}</div>`
    + `<div class="card"><h3 class="h3">✍️ Homework due</h3>${hwToday.length ? hwToday.map(h => `<div class="py-2 border-b border-slate-100 last:border-0 text-sm"><span class="subj">${esc(h.subj)}</span> ${h.hw ? `<b>${esc(h.hw)}</b>` : ''}${h.books ? `<span class="block text-slate-600 mt-1">Bring: ${esc(h.books)}</span>` : ''}</div>`).join('') : '<p class="text-sm text-slate-500 mt-1">No homework written in today’s diary yet.</p>'}
      ${pending.length ? `<p class="text-sm font-semibold text-rose mt-3">Still pending from before:</p>${pending.map(t => `<p class="text-sm">${esc(t.date)} · ${esc(t.subj)} — ${esc(t.task || 'Homework')}</p>`).join('')}` : ''}</div>`
    + `<div class="card word-card"><h3 class="h3">💡 Today’s shortcut</h3><p class="font-semibold mt-1">${esc(fzPick(GYAAN.tricks).t)}</p><p class="text-sm mt-1">${esc(fzPick(GYAAN.tricks).x)}</p></div>`;
}
function fzBag(key, s, on) { const b = fzLS.get(key, {}); b[s] = on; fzLS.set(key, b); render(); }
function fzColor(s) { const C = ['#1FA855', '#027EB5', '#E0662B', '#8E44C9', '#D6336C', '#0E9F9A', '#B7791F', '#3D5AFE']; let h = 0; for (const ch of String(s || '')) h = (h * 31 + ch.charCodeAt(0)) >>> 0; return C[h % C.length]; }

// ---------------------------------------------------------------- 2. CALENDAR
let FZM = 0, FZD = null;
function vPCal() {
  const kid = pKid(); if (!kid) return vPHome();
  const base = new Date(todayISO().slice(0, 7) + '-01T00:00:00'); base.setMonth(base.getMonth() + FZM);
  const y = base.getFullYear(), m = base.getMonth(), ym = y + '-' + String(m + 1).padStart(2, '0');
  const items = [...S.cal, ...examItems().filter(e => e.cls.includes(kid.cls)), ...(PD.clsHol || []).filter(h => h.cls === kid.cls).map(h => ({ date: h.date, title: h.title || 'Class holiday', type: 'Holiday' }))];
  const on = iso => items.filter(e => e.date <= iso && (e.to || e.date) >= iso);
  const att = {}; PD.att.filter(a => a.sid === kid.id).forEach(a => { att[a.date] = a.present; });
  const first = new Date(y, m, 1).getDay(), days = new Date(y, m + 1, 0).getDate(), today = todayISO();
  const col = t => t === 'Holiday' ? 'hol' : t === 'Exam' ? 'exam' : t === 'PTM' ? 'ptm' : 'evt';
  let cells = '';
  for (let i = 0; i < first; i++) cells += '<span></span>';
  for (let d = 1; d <= days; d++) {
    const iso = ym + '-' + String(d).padStart(2, '0'), it = on(iso), sun = new Date(iso + 'T00:00:00').getDay() === 0;
    cells += `<button onclick="FZD='${iso}';render()" class="fz-day ${iso === today ? 'today' : ''} ${FZD === iso ? 'sel' : ''} ${sun ? 'sun' : ''} ${att[iso] === true ? 'p' : att[iso] === false ? 'a' : ''}">
      <b>${d}</b><i>${it.slice(0, 3).map(e => `<em class="${col(e.type)}"></em>`).join('')}</i></button>`;
  }
  const ma = fzMonthAtt(kid, ym), list = items.filter(e => (e.date || '').slice(0, 7) === ym || ((e.to || '') >= ym + '-01' && (e.date || '') <= ym + '-31')).sort((a, b) => a.date.localeCompare(b.date));
  const sel = FZD && FZD.slice(0, 7) === ym ? on(FZD) : null;
  return fzBack('Calendar', esc(kid.name) + ' · Class ' + esc(kid.cls))
    + `<div class="card"><div class="flex items-center justify-between gap-2"><button class="btn btn-l btn-sm" onclick="FZM--;FZD=null;render()">‹</button>
      <h3 class="h3">${base.toLocaleDateString('en-IN', { month: 'long', year: 'numeric' })}</h3><button class="btn btn-l btn-sm" onclick="FZM++;FZD=null;render()">›</button></div>
      <div class="fz-cal mt-3">${['S', 'M', 'T', 'W', 'T', 'F', 'S'].map(x => `<span class="fz-wk">${x}</span>`).join('')}${cells}</div>
      <div class="fz-legend mt-3"><span><em class="hol"></em>Holiday</span><span><em class="exam"></em>Exam</span><span><em class="evt"></em>Event</span><span><em class="ptm"></em>PTM</span><span><i class="p"></i>Present</span><span><i class="a"></i>Absent</span></div>
      ${ma.n ? `<p class="text-sm mt-2">This month: present <b>${ma.p}</b> of ${ma.n} day${ma.n === 1 ? '' : 's'} (${ma.pct}%)</p>` : ''}</div>`
    + (sel ? `<div class="card"><h3 class="h3">${fmtDate(FZD)}</h3>${sel.length ? sel.map(e => `<p class="text-sm mt-1"><b>${esc(e.title)}</b> <span class="text-slate-500">${esc(e.type || '')}</span></p>`).join('') : '<p class="text-sm text-slate-500 mt-1">Nothing on this day.</p>'}${att[FZD] != null ? `<p class="text-sm mt-1">${att[FZD] ? '✅ Present' : '❌ Absent'}</p>` : ''}</div>` : '')
    + `<div class="card"><h3 class="h3">This month</h3>${list.length ? list.map(e => `<div class="flex justify-between gap-2 py-2 border-b border-slate-100 last:border-0 text-sm"><span><em class="fz-chip ${col(e.type)}"></em><b>${esc(e.title)}</b> <span class="text-slate-500">${esc(e.type || '')}</span></span><span class="text-slate-500 shrink-0">${fmtDate(e.date)}${e.to && e.to !== e.date ? ' – ' + fmtDate(e.to) : ''}</span></div>`).join('') : '<p class="text-sm text-slate-500 mt-1">Nothing scheduled this month.</p>'}</div>`;
}

// ---------------------------------------------------------------- 3. MY RECORD + achievements
let FZG = {}, FZT = {};
async function fzLoadExtra(kid) {
  if (FZG[kid.id] === undefined) { FZG[kid.id] = null; try { FZG[kid.id] = await gapi('state', null, { sid: kid.id }); } catch (e) { FZG[kid.id] = { error: e.message }; } if (view === 'prec' || view === 'phome') render(); }
  if (FZT[kid.id] === undefined) { FZT[kid.id] = null; fzTips(kid); }
}
function fzBadges(kid) {
  const sum = (PD.attSum || {})[kid.id] || { present: 0, total: 0 }, ma = fzMonthAtt(kid, todayISO().slice(0, 7)), ex = fzExamPct(kid), g = FZG[kid.id] && !FZG[kid.id].error ? FZG[kid.id] : null;
  const pts = PD.behav.filter(b => b.sid === kid.id).reduce((s, b) => s + (+b.pts || 0), 0), spk = PD.speak.filter(x => x.sid === kid.id).length, tp = FZT[kid.id] && FZT[kid.id].mine ? FZT[kid.id].mine.filter(t => t.status === 'approved').length : 0;
  return [
    ['🌟', 'Perfect month', '100% attendance this month', ma.n >= 10 && ma.p === ma.n],
    ['📅', 'Regular star', '90% or more attendance this session', sum.total >= 20 && sum.present / sum.total >= .9],
    ['🔥', '10-day streak', 'Present 10 school days in a row', fzStreak(kid) >= 10],
    ['🏅', '80% club', '80% or more in an exam', ex.some(e => e.pct >= 80)],
    ['👑', 'Class ranker', '1st, 2nd or 3rd in class in an exam', (PD.term || []).some(t => t.sid === kid.id && t.rank && t.rank <= 3)],
    ['📒', 'Homework star', '95% or more homework done (10+ checks)', (k => k.hw.length >= 10 && k.pct >= 95)(fzChecks(kid))],
    ['📝', 'Test champion', 'Full marks in a class test', !!(g && g.today && g.today.played && g.today.played.done && g.today.played.right === g.today.played.n)],
    ['🎯', 'Challenge winner', 'Won a quiz challenge this month', !!(g && g.season && g.season.win > 0)],
    ['🥇', 'Gold level', 'Reached Gold level in the Game Zone', !!(g && g.season && ['Gold', 'Platinum', 'Diamond', 'Crown', 'Ace', 'Conqueror'].includes(g.season.tier.name))],
    ['🎮', '7-day player', 'Learned in the Game Zone 7 days in a row', !!(g && g.stats && g.stats.streak >= 7)],
    ['🎤', 'Confident speaker', 'Took part in 3 speaking activities', spk >= 3],
    ['👍', 'Good conduct', '10 or more good points from teachers', pts >= 10],
    ['🧩', 'Reward collector', 'Earned 5 Game Zone rewards', !!(g && g.stickers && g.stickers.length >= 5)],
    ['💡', 'Knowledge sharer', 'Shared a post approved for the class', tp > 0]
  ];
}
function vPRecord() {
  const kid = pKid(); if (!kid) return vPHome(); fzLoadExtra(kid);
  const sum = (PD.attSum || {})[kid.id] || { present: 0, total: 0 }, sp = sum.total ? Math.round(sum.present / sum.total * 100) : null;
  const ym = todayISO().slice(0, 7), ma = fzMonthAtt(kid, ym), ex = fzExamPct(kid), g = FZG[kid.id] && !FZG[kid.id].error ? FZG[kid.id] : null;
  const pts = PD.behav.filter(b => b.sid === kid.id).reduce((s, b) => s + (+b.pts || 0), 0), B = fzBadges(kid), got = B.filter(b => b[3]).length;
  const ring = (pct, label, sub, c) => `<div class="fz-ring" style="--p:${pct == null ? 0 : pct};--c:${c}"><div><b>${pct == null ? '—' : pct + '%'}</b><span>${label}</span></div><small>${sub}</small></div>`;
  return fzBack('Achievements & record', esc(kid.name) + ' · Class ' + esc(kid.cls) + ' · ' + esc(S.settings.session || ''))
    + `<section class="arena-card"><div class="flex items-center gap-3"><div class="arena-badge" style="--tier:#f2a93b">🏆</div><div><p class="text-white/70 text-xs">Achievements</p><h2 class="text-2xl font-extrabold">${got} of ${B.length} unlocked</h2>
      <p class="text-sm text-white/80">${got ? 'Keep it up — each badge reflects a good habit.' : 'Your first badge is within reach this week.'}</p></div></div></section>`
    + `<div class="card"><h3 class="h3">📅 Attendance</h3><div class="grid grid-cols-3 gap-2 mt-3">
      ${ring(sp, 'session', sum.total ? `${sum.present} of ${sum.total} days` : 'not marked yet', '#0f8a5f')}
      ${ring(ma.pct, 'this month', ma.n ? `${ma.p} of ${ma.n} days` : 'not marked yet', '#3aa7e8')}
      <div class="fz-ring" style="--p:${Math.min(100, fzStreak(kid) * 10)};--c:#f08c00"><div><b>${fzStreak(kid)}</b><span>day streak</span></div><small>present in a row</small></div></div>
      ${sp != null && sp < 75 ? '<p class="text-sm text-rose font-semibold mt-3">Attendance is below the required 75%. Regular attendance is important.</p>' : ''}</div>`
    + `<div class="card"><h3 class="h3">🏅 Badges</h3><div class="fz-badges mt-3">${B.map(([e, n, d, ok]) => `<div class="${ok ? 'on' : ''}" title="${esc(d)}"><span>${ok ? e : '🔒'}</span><b>${esc(n)}</b><small>${esc(d)}</small></div>`).join('')}</div></div>`
    + `<div class="card"><h3 class="h3">📊 Exams</h3>${ex.length ? ex.map(e => `<div class="mt-3"><div class="flex justify-between text-sm"><b>${esc(e.name)}</b><span>${e.pct}% · ${grade(e.pct)}</span></div><div class="fz-meter mt-1"><i style="width:${Math.min(100, e.pct)}%;background:${e.pct >= 80 ? '#0f8a5f' : e.pct >= 60 ? '#f2a93b' : '#c0352b'}"></i></div></div>`).join('') : '<p class="text-sm text-slate-500 mt-1">No exam marks published yet.</p>'}</div>`
    + `<div class="card"><h3 class="h3">🎮 Learning & conduct</h3><div class="grid grid-cols-2 gap-2 mt-3 text-sm">
      <div class="fz-mini"><b>${g && g.season ? g.season.tier.icon + ' ' + esc(g.season.tier.name) + (g.season.tier.div ? ' ' + g.season.tier.div : '') : '—'}</b><span>Game Zone level</span></div>
      <div class="fz-mini"><b>${g && g.stats ? g.stats.xp : '—'}</b><span>total XP</span></div>
      <div class="fz-mini"><b>${pts > 0 ? '+' : ''}${pts}</b><span>good points (last 75 days)</span></div>
      <div class="fz-mini"><b>${PD.speak.filter(x => x.sid === kid.id).length}</b><span>speaking activities</span></div></div>
      ${g && g.stickers && g.stickers.length ? `<p class="text-2xl mt-3">${g.stickers.map(x => x[0]).join(' ')}</p>` : ''}</div>`
    + `<button class="btn btn-l w-full" onclick="go('psyl')">📚 Exam syllabus — chapters coming in each exam</button>`;
}
function vPSyl() { const kid = pKid(); if (!kid) return vPHome(); const a = (typeof parentDateSheets === 'function' ? parentDateSheets(kid) : '') + pExamSyl(kid);
  return fzBack('Exam syllabus', 'Class ' + esc(kid.cls) + ' · chapters the teachers marked for each exam') + (a || '<div class="card text-sm text-slate-500">The teachers have not marked exam chapters yet.</div>'); }

// ---------------------------------------------------------------- 4. LEARNING HUB + classmates' tricks
let FZTAB = 'tricks';
async function tapi(a, body, q) {
  const opt = { method: body ? 'POST' : 'GET', headers: { 'X-School-Token': TOKEN || '' } };
  if (body) { opt.headers['Content-Type'] = 'application/json'; opt.body = JSON.stringify(body); }
  let r, j = null; try { r = await fetch('tips_api.php?a=' + a + (q ? '&' + new URLSearchParams(q) : ''), opt); } catch (e) { throw new Error('No internet connection. Please try again.'); }
  try { j = await r.json(); } catch (e) {}
  if (!r.ok || !j || !j.ok) throw new Error((j && j.error) || 'Server problem. Please try again.');
  return j;
}
async function fzTips(kid) { try { FZT[kid.id] = await tapi('list', null, { sid: kid.id }); } catch (e) { FZT[kid.id] = { error: e.message, tips: [], mine: [] }; } if (['pzone', 'prec', 'phome'].includes(view)) render(); }
async function fzPost() {
  const kid = pKid(), v = id => (document.getElementById(id) || {}).value || '';
  try { await tapi('post', { sid: kid.id, kind: v('fz-kind'), subj: v('fz-subj'), text: v('fz-text'), ans: v('fz-ans') }); toast('Sent for review. Once your teacher approves it, your class will see it.'); FZT[kid.id] = null; fzTips(kid); }
  catch (e) { toast(e.message, 'a'); }
}
function fzCard(icon, title, body, extra) { return `<div class="fz-card"><span class="fz-ic">${icon}</span><div class="min-w-0"><b>${esc(title)}</b><p>${esc(body)}</p>${extra || ''}</div></div>`; }
function vPZone() {
  const kid = pKid(); if (!kid) return vPHome();
  if (FZT[kid.id] === undefined) { FZT[kid.id] = null; fzTips(kid); }
  const tabs = [['tricks', '💡 Shortcuts'], ['basics', '🧱 Basics'], ['tips', '🎯 Study tips'], ['stories', '📖 Stories'], ['fun', '🌍 Facts'], ['extra', '➕ Extra practice'], ['class', '👫 Class corner']];
  const T = FZT[kid.id], band = fzBand(kid);
  let body = '';
  if (FZTAB === 'tricks') body = GYAAN.tricks.map((x, i) => fzCard('💡', x.t, x.x, i === (fzDay() % GYAAN.tricks.length) ? '<span class="pill bg-marigold/20 text-ink mt-1">Today’s shortcut</span>' : '')).join('');
  else if (FZTAB === 'basics') body = `<p class="text-sm text-slate-600">Strong basics for Class ${esc(kid.cls)}. Read one a day and say it aloud.</p>` + GYAAN.basics.filter(x => x.b === band).concat(GYAAN.basics.filter(x => x.b !== band)).map(x => fzCard('🧱', x.t, x.x)).join('');
  else if (FZTAB === 'tips') body = GYAAN.tips.map(x => fzCard('🎯', x.t, x.x)).join('');
  else if (FZTAB === 'stories') body = GYAAN.stories.map(x => fzCard('📖', x.t, x.x, `<p class="fz-moral">${esc(x.m)}</p>`)).join('');
  else if (FZTAB === 'fun') body = GYAAN.facts.map((x, i) => fzCard(['🌍', '🔭', '🐙', '🌿', '🧪', '🚀'][i % 6], 'Did you know?', x)).join('');
  else if (FZTAB === 'extra') {
    const notes = typeof pNotes === 'function' ? pNotes(kid) : '', g = FZG[kid.id] && !FZG[kid.id].error ? FZG[kid.id] : null; if (FZG[kid.id] === undefined) fzLoadExtra(kid);
    body = `<div class="card"><h3 class="h3">➕ Extra practice at home</h3><p class="text-sm text-slate-600 mt-1">Questions you missed in the Game Zone return after 1, 3 and 7 days, so you can master them.</p>
      <button class="btn btn-g mt-3" onclick="go('game')">${g && g.revengeDue ? `Review ${g.revengeDue} question${g.revengeDue > 1 ? 's' : ''} you missed` : 'Open the Game Zone'}</button></div>`
      + (g && g.today && g.today.topics.length ? `<div class="card"><h3 class="h3">Today in class</h3>${g.today.topics.map(t => `<p class="text-sm mt-1"><span class="subj">${esc(t.subj)}</span> ${esc(t.topic)}</p>`).join('')}<p class="text-xs text-slate-500 mt-2">Read these once tonight, then take the class test in the Game Zone.</p></div>` : '')
      + (notes || '<div class="card text-sm text-slate-500">Chapter notes from the teachers will appear here.</div>');
  } else {
    body = `<div class="card"><h3 class="h3">💬 Share with your class</h3><p class="text-xs text-slate-500">A shortcut, a formula, a challenging question (with its answer) or an interesting fact. Your teacher reviews it first; once approved, your whole class can see it.</p>
      <div class="grid grid-cols-2 gap-2 mt-3"><select id="fz-kind" class="inp" onchange="document.getElementById('fz-answ').style.display=this.value==='question'?'block':'none'">${Object.entries(FZ_KIND).map(([k, v]) => `<option value="${k}">${v[0]} ${v[1]}</option>`).join('')}</select>
      <input id="fz-subj" class="inp" maxlength="40" placeholder="Subject (e.g. Maths)"></div>
      <textarea id="fz-text" class="inp mt-2" rows="3" maxlength="300" placeholder="Write it clearly, in your own words"></textarea>
      <div id="fz-answ" style="display:none"><input id="fz-ans" class="inp mt-2" maxlength="200" placeholder="Answer (shown when classmates tap)"></div>
      <button class="btn btn-g mt-3" onclick="fzPost()" ${T && T.left === 0 ? 'disabled' : ''}>Send to teacher</button> <span class="text-xs text-slate-500">${T && T.left != null ? T.left + ' left today' : ''}</span></div>`
      + (!T ? '<div class="card text-sm text-slate-500">Loading…</div>' : T.error ? `<div class="card text-sm">${esc(T.error)}</div>`
        : (T.mine.length ? `<div class="card"><h3 class="h3">My submissions</h3>${T.mine.map(t => `<p class="text-sm mt-2">${FZ_KIND[t.kind][0]} ${esc(t.text)} <span class="pill ${t.status === 'approved' ? 'bg-leaf/10 text-leaf' : t.status === 'rejected' ? 'bg-rose/10 text-rose' : 'bg-marigold/20 text-ink'}">${t.status === 'approved' ? 'Approved' : t.status === 'rejected' ? 'Not approved' : 'Awaiting review'}</span></p>`).join('')}</div>` : '')
        + (T.tips.length ? T.tips.map(t => fzCard(FZ_KIND[t.kind][0], FZ_KIND[t.kind][1] + ' by ' + t.by + (t.subj ? ' · ' + t.subj : ''), t.text, t.ans ? `<details class="mt-1 text-sm"><summary class="cursor-pointer font-semibold text-leaf">Show answer</summary>${esc(t.ans)}</details>` : '')).join('') : '<div class="card text-sm text-slate-500">Nothing has been shared yet. Be the first to share.</div>'));
  }
  return fzBack('Learning Hub', 'Basics, shortcuts, study tips, stories and facts — grow stronger every day')
    + `<div class="flex gap-2 overflow-x-auto noscroll">${tabs.map(([k, l]) => `<button onclick="FZTAB='${k}';render()" class="chip ${FZTAB === k ? 'chip-on' : ''}">${l}</button>`).join('')}</div>` + body;
}

// ---------------------------------------------------------------- 5. STATUS STORIES (WhatsApp-style)
function fzStories(kid) {
  const w = typeof wordOf === 'function' ? wordOf(todayISO()) : null, tr = fzPick(GYAAN.tricks), st = fzPick(GYAAN.stories), fa = fzPick(GYAAN.facts), ti = fzPick(GYAAN.tips);
  const S1 = [];
  if (w) S1.push({ k: 'word', l: 'Word', e: '🔤', bg: '#3D5AFE', h: 'Word of the day', b: w.w + ' (' + w.h + ')', s: w.m + ' — "' + w.e + '"' });
  S1.push({ k: 'trick', l: 'Shortcut', e: '💡', bg: '#E0662B', h: tr.t, b: tr.x });
  S1.push({ k: 'fact', l: 'Fact', e: '🌍', bg: '#0E9F9A', h: 'Did you know?', b: fa });
  S1.push({ k: 'story', l: 'Story', e: '📖', bg: '#8E44C9', h: st.t, b: st.x, s: st.m });
  S1.push({ k: 'tip', l: 'Tip', e: '🎯', bg: '#1FA855', h: ti.t, b: ti.x });
  const T = FZT[kid.id]; if (T === undefined) { FZT[kid.id] = null; fzTips(kid); }
  (T && T.tips ? T.tips.slice(0, 8) : []).forEach(t => S1.push({ k: 'tip:' + t.id, l: t.by, e: FZ_KIND[t.kind][0], bg: '#D6336C', h: FZ_KIND[t.kind][1] + ' by ' + t.by + (t.subj ? ' · ' + t.subj : ''), b: t.text, a: t.ans }));
  return S1;
}
function gyStatusRow(kid) {
  const L = fzStories(kid), seen = fzLS.get('sn_story_seen', {}), day = todayISO();
  return `<div class="fz-status"><p>Today’s learning</p><div>${L.map((s, i) => `<button onclick="gyOpen(${i})" class="${seen[s.k] === day ? 'seen' : ''}"><span class="r" style="--bg:${s.bg}"><span>${s.e}</span></span><small>${esc(s.l)}</small></button>`).join('')}
    <button onclick="FZTAB='class';go('pzone')"><span class="r add"><span>＋</span></span><small>Share</small></button></div></div>`;
}
let GYI = 0, GYT = null;
function gyOpen(i) {
  const kid = pKid(), L = fzStories(kid); if (!L[i]) return gyClose();
  GYI = i; const s = L[i]; GYA = s.a || ''; const seen = fzLS.get('sn_story_seen', {}); seen[s.k] = todayISO(); fzLS.set('sn_story_seen', seen);
  let el = document.getElementById('gy-story'); if (!el) { el = document.createElement('div'); el.id = 'gy-story'; el.setAttribute('role', 'dialog'); document.body.appendChild(el); }
  el.style.setProperty('--bg', s.bg);
  el.innerHTML = `<div class="gy-bars">${L.map((x, j) => `<i class="${j < i ? 'done' : j === i ? 'now' : ''}"></i>`).join('')}</div>
    <div class="gy-head"><span>${s.e}</span><b>${esc(s.l)}</b><button onclick="gyClose()" aria-label="Close">×</button></div>
    <div class="gy-body" onclick="gyTap(event)"><small>${esc(s.h)}</small><h2>${esc(s.b)}</h2>${s.s ? `<p>${esc(s.s)}</p>` : ''}
      ${s.a ? '<button class="gy-ans" onclick="event.stopPropagation();gyAns(this)">Tap to see the answer</button>' : ''}</div>
    <p class="gy-foot">Tap right for next · left to go back</p>`;
  clearTimeout(GYT); GYT = setTimeout(() => gyOpen(GYI + 1), s.a ? 15000 : 9000);
}
let GYA = '';
function gyAns(btn) { clearTimeout(GYT); const p = document.createElement('p'); p.className = 'gy-a'; p.textContent = '✅ ' + GYA; btn.replaceWith(p); }
function gyTap(e) { const x = e.clientX / window.innerWidth; gyOpen(x < .3 ? Math.max(0, GYI - 1) : GYI + 1); }
function gyClose() { clearTimeout(GYT); const el = document.getElementById('gy-story'); if (el) el.remove(); if (view === 'phome') render(); }

// ---------------------------------------------------------------- 6. SETTINGS + SCREEN TIME
const FZ_USE = 'sn_use', FZ_LIMIT = 'sn_use_limit';
function fzUseToday() { const u = fzLS.get(FZ_USE, {}); return Math.round((u[todayISO()] || 0) / 60); }
setInterval(() => {
  if (typeof U === 'undefined' || !U || !isFam() || document.visibilityState !== 'visible') return;
  const u = fzLS.get(FZ_USE, {}), d = todayISO(); u[d] = (u[d] || 0) + 15;
  Object.keys(u).sort().slice(0, -30).forEach(k => delete u[k]); fzLS.set(FZ_USE, u);
  const lim = +fzLS.get(FZ_LIMIT, 0), told = fzLS.get('sn_use_told', '');
  if (lim && u[d] / 60 >= lim && told !== d) { fzLS.set('sn_use_told', d); toast(`You have used the app for ${lim} minutes today. It is a good time to take a break.`, 'a'); }
}, 15000);
function fzApplyBig() { document.documentElement.classList.toggle('sn-big', !!fzLS.get('sn_big', false)); }
fzApplyBig();
function vPSettings() {
  const u = fzLS.get(FZ_USE, {}), lim = +fzLS.get(FZ_LIMIT, 0), big = !!fzLS.get('sn_big', false), d0 = todayISO();
  const week = [6, 5, 4, 3, 2, 1, 0].map(n => { const d = addDays(d0, -n); return { d, m: Math.round((u[d] || 0) / 60) }; }), mx = Math.max(30, ...week.map(x => x.m)), tot = week.reduce((s, x) => s + x.m, 0);
  const row = (icon, title, sub, ctl) => `<div class="fz-set"><span class="fz-ic">${icon}</span><div class="flex-1 min-w-0"><b>${title}</b><small>${sub}</small></div>${ctl}</div>`;
  return head('Settings', esc(S.settings.school || '') + ' · ' + esc(S.settings.session || ''))
    + `<div class="card"><h3 class="h3">⏱️ Screen time on this phone</h3><div class="flex items-baseline gap-2 mt-2"><b class="text-3xl">${fzUseToday()}</b><span class="text-sm text-slate-500">minutes today · ${tot} minutes in 7 days</span></div>
      <div class="fz-week mt-3">${week.map(x => `<div><i style="height:${Math.max(4, Math.round(x.m / mx * 100))}%" class="${lim && x.m >= lim ? 'over' : ''}"></i><small>${new Date(x.d + 'T00:00:00').toLocaleDateString('en-IN', { weekday: 'narrow' })}</small><em>${x.m}</em></div>`).join('')}</div>
      <label class="lbl mt-3">Remind me to take a break after</label><select class="inp" onchange="fzLS.set('${FZ_LIMIT}',+this.value);render()">${[[0, 'No reminder'], [30, '30 minutes a day'], [45, '45 minutes a day'], [60, '1 hour a day'], [90, '1½ hours a day'], [120, '2 hours a day']].map(([v, l]) => `<option value="${v}" ${v === lim ? 'selected' : ''}>${l}</option>`).join('')}</select>
      <p class="text-xs text-slate-500 mt-2">Counted only while the school app is open on this phone. Nothing is sent to the school.</p></div>`
    + `<div class="card space-y-1">
      ${row('🌐', 'Language', 'English or Hinglish', window.SchoolLang && SchoolLang.control ? SchoolLang.control() : '')}
      ${row('🔠', 'Larger text', 'Makes every screen easier to read', `<button class="fz-sw ${big ? 'on' : ''}" onclick="fzLS.set('sn_big',${!big});fzApplyBig();render()" aria-pressed="${big}"><i></i></button>`)}
      ${PD.kids.length > 1 ? row('👧', 'Child', 'Switch between your children', `<select class="inp w-auto" onchange="PKID=this.value;render()">${PD.kids.map(k => `<option value="${esc(k.id)}" ${k.id === PKID ? 'selected' : ''}>${esc(k.name.split(' ')[0])} · ${esc(k.cls)}</option>`).join('')}</select>`) : ''}
      ${row('🔑', 'Login PIN', 'Change your 4-digit PIN', '<button class="btn btn-sm btn-l" onclick="changeMyPin()">Change</button>')}
      ${window.SNFamily && SNFamily.intro ? row('🎬', 'Welcome screens', 'See the welcome again', '<button class="btn btn-sm btn-l" onclick="SNFamily.intro()">Play</button>') : ''}
      ${row('🚪', 'Log out', 'Sign out from this phone', '<button class="btn btn-sm btn-r" onclick="logout()">Log out</button>')}</div>`
    + `<div class="card text-sm text-slate-600">🔒 This app shows only your own family's school information. Classmates see only short names in ranks, and only posts a teacher has approved.</div>`;
}

// ---------------------------------------------------------------- teacher: check classmates' tricks (Quiz Duel page)
let FZTT = null;
async function fzLoadTT() { try { FZTT = await tapi('tlist'); } catch (e) { FZTT = { error: e.message }; } if (view === 'quizt') render(); }
async function fzDecide(id, status) { try { await tapi('decide', { id, status }); toast(status === 'approved' ? 'Approved — the class will see it as a Status' : 'Rejected'); FZTT = null; render(); } catch (e) { toast(e.message, 'r'); } }
function tipTeacherCard() {
  if (!FZTT) { FZTT = { busy: true }; fzLoadTT(); return ''; }
  if (FZTT.busy || FZTT.error) return '';
  const pend = FZTT.tips.filter(t => t.status === 'pending');
  return `<div class="card space-y-2"><h3 class="h3">💬 Students' tricks to check (${pend.length})</h3><p class="text-sm text-slate-600">Approved tricks, formulas and questions show to the whole class as a Status in the Family app, with the student's short name.</p>
    ${pend.length ? pend.map(t => `<div class="border border-line rounded-2xl p-3 text-sm"><b>${FZ_KIND[t.kind][0]} ${FZ_KIND[t.kind][1]} · Class ${esc(t.cls)} · ${esc(t.name)}${t.subj ? ' · ' + esc(t.subj) : ''}</b><p class="mt-1">${esc(t.text)}</p>${t.ans ? `<p class="text-slate-600">Answer: ${esc(t.ans)}</p>` : ''}
      <div class="flex gap-2 mt-2"><button class="btn btn-sm btn-g" onclick="fzDecide(${jsq(t.id)},'approved')">Approve</button><button class="btn btn-sm btn-l" onclick="fzDecide(${jsq(t.id)},'rejected')">Reject</button></div></div>`).join('') : '<p class="text-sm text-slate-500">Nothing waiting.</p>'}</div>`;
}

// ---------------------------------------------------------------- 6b. RESULTS, RANKERS, HOMEWORK & COPY CHECKS (owner, 27 Nov 2026)
function fzTerm(kid) { const L = (PD.term || []).filter(t => t.sid === kid.id); return L.find(t => t.exam === 'Annual') || L.find(t => t.exam === 'Half-Yearly') || null; }
const FZ_ORD = n => n + (['th', 'st', 'nd', 'rd'][(n % 100 > 10 && n % 100 < 14) ? 0 : (n % 10 < 4 ? n % 10 : 0)] || 'th');
function fzChecks(kid) {
  const C = PD.checks || { hw: [], copy: [] }, hw = C.hw.filter(x => x.sid === kid.id && x.hw), cp = C.copy.filter(x => x.sid === kid.id);
  const done = hw.filter(x => x.hw === 'done').length, half = hw.filter(x => x.hw === 'half').length, not = hw.filter(x => x.hw === 'not').length;
  const bySub = {}; hw.forEach(x => { const b = bySub[x.subj] = bySub[x.subj] || { n: 0, d: 0, not: 0 }; b.n++; b.d += x.hw === 'done' ? 1 : x.hw === 'half' ? .5 : 0; if (x.hw === 'not') b.not++; });
  return { hw, cp, done, half, not, pct: hw.length ? Math.round((done + half / 2) / hw.length * 100) : null, bySub, cpOk: cp.filter(x => x.st === 'ok').length, cpInc: cp.filter(x => x.st === 'inc').length };
}
function fzResultCard(kid) {
  const T = fzTerm(kid); if (!T) return '';
  const medal = r => ['🥇', '🥈', '🥉', '🏅', '🎖️'][r - 1] || '🏅';
  return `<section class="fr-card"><div class="fr-top"><div><small>${esc(T.exam)} examination · Class ${esc(T.cls)}</small><h2>${T.pct != null ? T.pct + '%' : '—'}</h2><p>Grade ${T.pct != null ? grade(T.pct) : '—'} · ${esc(String(T.tot))} / ${T.max}</p></div>
      ${T.rank ? `<div class="fr-rank"><small>Rank in class</small><b>${T.rank}</b><small>of ${T.of}</small></div>` : `<div class="fr-rank"><small>Grade</small><b>${T.pct != null ? grade(T.pct) : '—'}</b><small>${T.complete ? 'well done' : 'marks pending'}</small></div>`}</div>
    <div class="fr-tbl"><div class="fr-h"><span>Subject</span><span>PT</span><span>NB</span><span>SEA</span><span>Exam</span><span>Total</span></div>
      ${T.rows.map(r => r.none ? `<div><span>${esc(r.s)}</span><span class="mut" style="grid-column:span 5">Marks not entered yet</span></div>` : `<div><span>${esc(r.s)}</span><span>${esc(String(r.pt))}</span><span>${esc(String(r.nb))}</span><span>${esc(String(r.sea))}</span><span>${esc(String(r.te))}</span><b class="${r.ab ? 'mut' : r.t >= 80 ? 'hi' : r.t < 33 ? 'lo' : ''}">${r.ab ? 'AB' : r.t}</b></div>`).join('')}</div>
    <p class="fr-note">Pre-Test 10 + Notebook 5 + Subject Enrichment 5 + Term Exam 80 = 100 per subject.</p></section>
  ${T.toppers.length ? `<section class="fr-rankers"><h3>🏆 Class ${esc(T.cls)} rankers · ${esc(T.exam)}</h3>${T.toppers.map(x => `<div class="r${x.rank}"><span>${medal(x.rank)}</span><b>${esc(x.name)}</b><em>${FZ_ORD(x.rank)}</em><strong>${x.pct}%</strong></div>`).join('')}</section>` : ''}`;
}
function fzChecksCard(kid, compact) {
  const K = fzChecks(kid); if (!K.hw.length && !K.cp.length) return compact ? '' : '<div class="card text-sm text-slate-500">Homework and copy checks appear here once the teachers record them.</div>';
  return `<button class="fc-card" onclick="go('pchk')"><div><small>Homework &amp; copy checks · this session</small><b>${K.pct != null ? K.pct + '% homework done' : 'Copy checks recorded'}</b>
    <p>${K.hw.length} homework check${K.hw.length === 1 ? '' : 's'}${K.not ? ` · <span class="t-bad">${K.not} not done</span>` : ''} · ${K.cpOk} chapter cop${K.cpOk === 1 ? 'y' : 'ies'} complete${K.cpInc ? ` · <span class="t-bad">${K.cpInc} incomplete</span>` : ''}</p></div>
    <span class="fh-ring ${K.pct == null ? 'mut' : K.pct >= 80 ? 'ok' : 'bad'}" style="--p:${K.pct || 0}"><b>${K.pct != null ? K.pct + '%' : '—'}</b></span></button>`;
}
function vPChecks() {
  const kid = pKid(); if (!kid) return vPHome(); const K = fzChecks(kid);
  const lab = { done: ['✅', 'Done'], half: ['🟡', 'Half done'], not: ['❌', 'Not done'] };
  const cpBySub = {}; K.cp.forEach(x => (cpBySub[x.subj] = cpBySub[x.subj] || []).push(x));
  return fzBack('Homework & copy checks', esc(kid.name) + ' · Class ' + esc(kid.cls) + ' · this session')
    + `<div class="card"><div class="grid grid-cols-3 gap-2 text-center">
      <div class="fz-mini"><b class="t-ok">${K.done}</b><span>done</span></div><div class="fz-mini"><b style="color:#b7791f">${K.half}</b><span>half done</span></div><div class="fz-mini"><b class="t-bad">${K.not}</b><span>not done</span></div></div>
      ${K.pct != null ? `<p class="text-sm mt-3">Homework completion: <b>${K.pct}%</b> of ${K.hw.length} checks</p><div class="fz-meter mt-1"><i style="width:${K.pct}%;background:${K.pct >= 80 ? '#0f8a5f' : K.pct >= 60 ? '#f2a93b' : '#c0352b'}"></i></div>` : '<p class="text-sm text-slate-500 mt-2">No homework check recorded yet.</p>'}</div>`
    + (Object.keys(K.bySub).length ? `<div class="card"><h3 class="h3">By subject</h3>${Object.entries(K.bySub).sort((a, b) => a[0].localeCompare(b[0])).map(([sb, b]) => { const p = Math.round(b.d / b.n * 100);
      return `<div class="mt-3"><div class="flex justify-between text-sm"><span><span class="subj">${esc(sb)}</span></span><span>${p}% · ${b.n} check${b.n > 1 ? 's' : ''}${b.not ? ` · <span class="t-bad">${b.not} not done</span>` : ''}</span></div><div class="fz-meter mt-1"><i style="width:${p}%;background:${fzColor(sb)}"></i></div></div>`; }).join('')}</div>` : '')
    + (Object.keys(cpBySub).length ? `<div class="card"><h3 class="h3">📒 Copy check by chapter</h3>${Object.entries(cpBySub).map(([sb, l]) => `<div class="mt-3"><span class="subj">${esc(sb)}</span>${l.map(x => `<div class="flex justify-between gap-2 text-sm py-1 border-b border-slate-100 last:border-0"><span>${esc(x.ch)}</span><span class="${x.st === 'ok' ? 't-ok' : 't-bad'} font-semibold shrink-0">${x.st === 'ok' ? '✓ Complete' : '✗ Incomplete'}</span></div>`).join('')}</div>`).join('')}</div>` : '')
    + (K.hw.length ? `<div class="card"><h3 class="h3">Recent homework checks</h3>${K.hw.slice(0, 40).map(x => `<div class="py-2 border-b border-slate-100 last:border-0 text-sm"><div class="flex justify-between gap-2"><span><span class="subj">${esc(x.subj)}</span> ${esc(fmtDate(x.date))}</span><b>${lab[x.hw] ? lab[x.hw][0] + ' ' + lab[x.hw][1] : ''}</b></div>${x.task ? `<p class="text-slate-600 mt-1">${esc(x.task)}</p>` : ''}${x.by ? `<p class="text-xs text-slate-400">Checked by ${esc(x.by)}</p>` : ''}</div>`).join('')}</div>` : '');
}

// ---------------------------------------------------------------- 6c. STUDENT PROFILE + LEAVE (owner, 28 Nov 2026)
async function fzApi(path, body) {
  const opt = { method: body ? 'POST' : 'GET', headers: { 'X-School-Token': TOKEN || '' } };
  if (body) { opt.headers['Content-Type'] = 'application/json'; opt.body = JSON.stringify(body); }
  let r, j = null; try { r = await fetch(path, opt); } catch (e) { throw new Error('No internet connection. Please try again.'); }
  try { j = await r.json(); } catch (e) {}
  if (!r.ok || !j || !j.ok) throw new Error((j && j.error) || 'Server problem. Please try again.');
  return j;
}
function fzAva(kid, cls) { const ini = esc((kid.name || '?').split(/\s+/).map(x => x[0]).slice(0, 2).join('')); return kid.photo ? `<img class="${cls || ''}" src="${esc(kid.photo)}" alt="${esc(kid.name)}">` : `<span class="${cls || ''} fz-ini">${ini}</span>`; }
function vPInfo() {
  const kid = pKid(); if (!kid) return vPHome(); const I = kid.info || {}, T = fzTerm(kid), sum = (PD.attSum || {})[kid.id] || { present: 0, total: 0 }, sp = sum.total ? Math.round(sum.present / sum.total * 100) : null;
  const fd = d => /^\d{4}-\d{2}-\d{2}$/.test(d) ? fmtDate(d) : d;
  const rows = [['Admission no.', I.adm], ['Class', kid.cls + (I.section ? ' – ' + I.section : '')], ['Roll no.', I.rollno], ['Date of birth', I.dob ? fd(I.dob) : ''], ['Gender', I.gender], ['Blood group', I.blood], ['House', I.house],
    ['Father’s name', I.father], ['Mother’s name', I.mother], ['Mobile', I.phone], ['Other mobile', I.phone2], ['Address', I.address], ['Admission date', I.admDate ? fd(I.admDate) : ''], ['Class teacher', kid.ct]].filter(r => r[1]);
  return `${kidSwitch()}<section class="fp-hero">${fzAva(kid, 'fp-pic')}<h2>${esc(kid.name)}</h2><p>Class ${esc(kid.cls)}${I.adm ? ' · Adm. ' + esc(I.adm) : ''}</p>
      <div class="fp-stats"><div><b>${sp == null ? '—' : sp + '%'}</b><span>attendance</span></div><div><b>${T ? (T.rank ? FZ_ORD(T.rank) : grade(T.pct)) : '—'}</b><span>${T ? (T.rank ? 'rank · ' : 'grade · ') + esc(T.exam) : 'result'}</span></div><div><b>${(K => K.pct != null ? K.pct + '%' : '—')(fzChecks(kid))}</b><span>homework done</span></div></div></section>
    <div class="card"><h3 class="h3">Student information</h3><div class="fp-list">${rows.map(([k, v]) => `<div><span>${esc(k)}</span><b>${esc(v)}</b></div>`).join('')}</div>
      <p class="text-xs text-slate-500 mt-3">To correct any detail or the photo, please contact the school office.</p></div>
    <div class="grid grid-cols-2 gap-2"><button class="btn btn-l" onclick="go('pleave')">🗓 Apply for leave</button><button class="btn btn-l" onclick="go('prec')">🏆 Achievements</button></div>`;
}
let FZL = {};
async function fzLvLoad(kid, force) { if (FZL[kid.id] && !force) return; FZL[kid.id] = FZL[kid.id] || null; try { FZL[kid.id] = await fzApi('famleave_api.php?a=list&sid=' + encodeURIComponent(kid.id)); } catch (e) { FZL[kid.id] = { error: e.message, leaves: [], reasons: [] }; } if (view === 'pleave') render(); }
let FZLR = 'Illness';
async function fzLvApply() {
  const kid = pKid(), v = id => (document.getElementById(id) || {}).value || '';
  try { await fzApi('famleave_api.php?a=apply', { sid: kid.id, from: v('lv-from'), to: v('lv-to') || v('lv-from'), reason: FZLR, note: v('lv-note') }); toast('Leave application sent to the class teacher.'); fzLvLoad(kid, true); }
  catch (e) { toast(e.message, 'a'); }
}
async function fzLvCancel(id) { const kid = pKid(); try { await fzApi('famleave_api.php?a=cancel', { sid: kid.id, id }); toast('Application withdrawn.'); fzLvLoad(kid, true); } catch (e) { toast(e.message, 'a'); } }
function vPLeave() {
  const kid = pKid(); if (!kid) return vPHome(); if (FZL[kid.id] === undefined) fzLvLoad(kid); const L = FZL[kid.id], t = todayISO();
  const st = { pending: ['Waiting for class teacher', 'bg-marigold/20 text-ink'], approved: ['Approved', 'bg-leaf/10 text-leaf'], rejected: ['Not approved', 'bg-rose/10 text-rose'], cancelled: ['Withdrawn', 'bg-slate-100 text-slate-500'] };
  return fzBack('Leave application', esc(kid.name) + ' · Class ' + esc(kid.cls))
    + `<div class="card space-y-3"><h3 class="h3">🗓 Apply for leave</h3>
      <div class="grid grid-cols-2 gap-2"><div><label class="lbl">From</label><input id="lv-from" type="date" class="inp" value="${t}" min="${addDays(t, -7)}"></div><div><label class="lbl">To</label><input id="lv-to" type="date" class="inp" value="${t}" min="${addDays(t, -7)}"></div></div>
      <div><label class="lbl">Reason</label><div class="flex flex-wrap gap-2">${['Illness', 'Family function', 'Travel', 'Other'].map(r => `<button class="chip ${FZLR === r ? 'chip-on' : ''}" onclick="FZLR='${r}';render()">${{ Illness: '🤒', 'Family function': '🎉', Travel: '🚌', Other: '✏️' }[r]} ${r}</button>`).join('')}</div></div>
      <div><label class="lbl">Note for the teacher ${FZLR === 'Other' ? '(required)' : '(optional)'}</label><textarea id="lv-note" class="inp" rows="2" maxlength="300" placeholder="e.g. fever since last night"></textarea></div>
      <button class="btn btn-p w-full py-3" onclick="fzLvApply()">Send to class teacher</button>
      <p class="text-xs text-slate-500">Approved leave is shown as Leave (L) in attendance, not as absent. Sundays are not counted.</p></div>`
    + (!L ? '<div class="card text-sm text-slate-500">Loading…</div>' : L.error ? `<div class="card text-sm">${esc(L.error)}</div>`
      : `<div class="card"><h3 class="h3">My applications</h3>${L.leaves.length ? L.leaves.map(x => `<div class="py-3 border-b border-slate-100 last:border-0 text-sm"><div class="flex justify-between gap-2"><b>${esc(fmtDate(x.from))}${x.to !== x.from ? ' – ' + esc(fmtDate(x.to)) : ''}</b><span class="pill ${st[x.status][1]}">${st[x.status][0]}</span></div>
          <p class="text-slate-600 mt-1">${esc(x.reason)} · ${x.days} school day${x.days > 1 ? 's' : ''}${x.note ? ' — ' + esc(x.note) : ''}</p>${x.decidedBy ? `<p class="text-xs text-slate-400">${x.status === 'approved' ? 'Approved' : 'Reviewed'} by ${esc(x.decidedBy)}${x.reply ? ': ' + esc(x.reply) : ''}</p>` : ''}
          ${x.status === 'pending' ? `<button class="text-xs underline text-rose mt-1" onclick="fzLvCancel(${jsq(x.id)})">Withdraw</button>` : ''}</div>`).join('') : '<p class="text-sm text-slate-500 mt-1">No leave applied yet.</p>'}</div>`);
}

// ---------------------------------------------------------------- 7. PREMIUM HOME (owner, 27 Nov 2026: "better than the Teacher and Chairman apps")
// Hero header with greeting and child switch, a "today at a glance" card, learning stories, quick actions, today's
// lessons, class-test banner, coming-up timeline and the latest notice. The WhatsApp-style update list follows below.
function fzGreet() { const h = new Date().getHours(); return h < 12 ? 'Good morning' : h < 17 ? 'Good afternoon' : 'Good evening'; }
function fzDaysTo(iso) { return Math.round((new Date(iso + 'T00:00:00') - new Date(todayISO() + 'T00:00:00')) / 86400000); }
let FZ_ANIM = true;
function fzHome(kid, isParent, ctl) {
  fzLoadExtra(kid); const anim = FZ_ANIM; if (anim) setTimeout(() => { FZ_ANIM = false; }, 900);
  const today = todayISO(), at = pAtt(kid), sum = (PD.attSum || {})[kid.id] || { present: 0, total: 0 }, sp = sum.total ? Math.round(sum.present / sum.total * 100) : at.pct;
  const hwT = PD.hw.filter(h => h.cls === kid.cls && h.date === today), hwN = hwT.filter(h => h.hw).length;
  const g = FZG[kid.id] && !FZG[kid.id].error ? FZG[kid.id] : null;
  const exam = examItems().filter(e => e.cls.includes(kid.cls) && e.date >= today).sort((a, b) => a.date.localeCompare(b.date))[0];
  const up = [...S.cal, ...examItems().filter(e => e.cls.includes(kid.cls))].filter(e => (e.to || e.date) >= today).sort((a, b) => a.date.localeCompare(b.date)).slice(0, 3);
  const nt = (PD.notices || []).slice().sort((a, b) => String(b.date).localeCompare(String(a.date)))[0];
  const nx = fzNextSchoolDay(kid), fday = nx ? ((((PD.tt || {})[kid.cls] || [])[nx.idx]) || []) : [], fi = fday.findIndex(Boolean), first = fi >= 0 ? fday[fi] : null;
  const name = (kid.name || '').split(' ')[0], ini = (kid.name || '?').split(/\s+/).map(x => x[0]).slice(0, 2).join('');
  const attTxt = at.today ? (at.today.present ? 'Present' : 'Absent') : 'Not marked', attTone = at.today ? (at.today.present ? 'ok' : 'bad') : 'mut';
  const QA = [['phw', '📘', 'Homework', '#2563eb'], ['ptmr', '🎒', 'Next day', '#f08c00'], ['presults', '📊', 'Results', '#d6336c'], ['pcal', '📅', 'Calendar', '#0e9f9a'],
    ['pchk', '📒', 'Checks', '#0f8a5f'], ['pleave', '🗓', 'Leave', '#e0662b'], ['pinfo', '🪪', 'Profile', '#5b6b7b'], ['pzone', '💡', 'Learning Hub', '#8e44c9'], ['game', '🎯', 'Game Zone', '#3d5afe'], isParent ? ['pfees', '💳', 'Fees', '#e0662b'] : ['prec', '🏆', 'Achievements', '#b7791f'], ['pnotices', '📢', 'Notices', '#027eb5']]
    .filter(q => VIEWS[q[0]]);
  return `<div class="fh fam-real-screen${anim ? ' anim' : ''}">
    <section class="fh-hero"><i class="fh-glow a"></i><i class="fh-glow b"></i>
      <div class="fh-top"><img src="img/chairman-512.png" alt="School logo"><div><b>SNMSIS</b><small>${isParent ? 'Parent' : 'Student'} · ${esc(S.settings.session || '')}</small></div><span class="fh-ctl">${ctl || ''}</span></div>
      <div class="fh-me"><button onclick="go('pinfo')" aria-label="Student profile">${fzAva(kid, 'fh-pic')}</button><div><p class="fh-hi">${fzGreet()},</p><h1>${esc(isParent ? name + '’s family' : name)}</h1></div></div>
      <div class="fh-tags"><span>Class ${esc(kid.cls)}</span>${kid.ct ? `<span>Class teacher · ${esc(kid.ct)}</span>` : ''}<span>${new Date().toLocaleDateString('en-IN', { weekday: 'long', day: 'numeric', month: 'long' })}</span></div>
      ${PD.kids.length > 1 ? `<div class="fh-kids fam-child">${PD.kids.map(k => `<button class="${k.id === kid.id ? 'on' : ''}" onclick="PKID='${esc(k.id)}';render()">${k.photo ? `<img src="${esc(k.photo)}" alt="">` : `<span>${esc((k.name || '?').split(/\s+/).map(x => x[0]).slice(0, 2).join(''))}</span>`}${esc((k.name || '').split(' ')[0])} · ${esc(k.cls)}</button>`).join('')}</div>` : ''}
    </section>
    <section class="fh-glance">
      <button onclick="go('presults')"><span class="fh-ring ${attTone}" style="--p:${sp == null ? 0 : sp}"><b>${sp == null ? '—' : sp + '%'}</b></span><b class="t-${attTone}">${attTxt}</b><small>attendance</small></button>
      <button onclick="go('phw')"><span class="fh-num">${hwN}</span><b>${hwN === 1 ? 'Task' : 'Tasks'}</b><small>homework today</small></button>
      ${(() => { const T = fzTerm(kid), K = fzChecks(kid);
        if (T) return `<button onclick="go('presults')"><span class="fh-num">${T.rank ? (['🥇', '🥈', '🥉'][T.rank - 1] || T.rank) : esc(T.pct != null ? grade(T.pct) : '—')}</span><b>${T.rank ? FZ_ORD(T.rank) + ' in class' : T.pct != null ? T.pct + '%' : 'Result'}</b><small>${esc(T.exam)}</small></button>`;
        return `<button onclick="go('pchk')"><span class="fh-num">${K.pct != null ? K.pct + '%' : '—'}</span><b>Homework</b><small>${K.pct != null ? 'completed' : 'checks'}</small></button>`; })()}
      <button onclick="go('pcal')"><span class="fh-num">${exam ? Math.max(0, fzDaysTo(exam.date)) : '—'}</span><b>${exam ? (fzDaysTo(exam.date) === 1 ? 'Day' : 'Days') : 'No exam'}</b><small>${exam ? 'to ' + esc(exam.title.split(':')[0]) : 'scheduled'}</small></button>
    </section>
    ${gyStatusRow(kid)}
    ${(() => { if (!g || !g.today || !g.today.topics.length) return ''; const td = g.today, tp = td.topics.map(t => esc(t.subj) + ': ' + esc(t.topic)).join(' · ');
      if (td.played && td.played.done) return `<button class="fh-cta done" onclick="go('game')"><span>✅</span><div><b>Class test done · ${td.played.right} of ${td.played.n} correct</b><small>${tp}</small></div><em>${Math.round(td.played.right / td.played.n * 100)}%</em></button>`;
      if (td.ready) return `<button class="fh-cta" onclick="go('game')"><span>📝</span><div><b>${td.isToday ? 'Today’s' : 'Last class’s'} test is ready</b><small>${tp} · ${td.ready} questions</small></div><em>${td.played ? 'Continue' : 'Start'}</em></button>`;
      return `<div class="fh-cta wait"><span>⏳</span><div><b>Class test is being prepared</b><small>From today’s class: ${tp}</small></div></div>`; })()}
    <div class="fh-sec"><h3>Quick access</h3></div>
    <section class="fh-grid">${QA.map(([k, e, l, c]) => `<button onclick="go('${k}')" style="--c:${c}"><span>${e}</span><b>${l}</b></button>`).join('')}</section>
    <div class="fh-sec"><h3>Today in class</h3><button onclick="go('phw')">See all</button></div>
    ${hwT.length ? `<section class="fh-rail">${hwT.map(h => `<button onclick="go('phw')" class="fh-lesson" style="--c:${fzColor(h.subj)}"><small>${esc(h.subj)}</small><b>${esc(h.topic || 'Class work')}</b>${h.hw ? `<p>📝 ${esc(h.hw)}</p>` : '<p class="mut">No homework</p>'}</button>`).join('')}</section>`
      : `<div class="fh-empty">📖 Lessons appear here as soon as the teachers fill in today’s diary.</div>`}
    ${nx ? `<button class="fh-next" onclick="go('ptmr')"><span>🎒</span><div><small>Next school day · ${esc(fmtDate(nx.date))}</small><b>${first ? 'First period: ' + esc(first.s) + ' at ' + t12(BELLS[fi].s) : 'Timetable, homework and bag checklist'}</b></div><em>›</em></button>` : ''}
    ${fzTerm(kid) || fzChecks(kid).hw.length ? `<div class="fh-sec"><h3>Progress</h3><button onclick="go('presults')">Results</button></div><div class="fh-pad">${(() => { const T = fzTerm(kid); return T ? `<button class="fc-card" onclick="go('presults')"><div><small>${esc(T.exam)} result</small><b>${T.pct}% · Grade ${grade(T.pct)}${T.rank ? ' · ' + FZ_ORD(T.rank) + ' in class' : ''}</b><p>${T.toppers.length ? 'Rankers: ' + T.toppers.map(x => esc(x.name.split(' ')[0]) + ' (' + FZ_ORD(x.rank) + ')').join(', ') : 'Tap for subject-wise marks'}</p></div><span class="fh-ring ok" style="--p:${T.pct}"><b>${T.pct}%</b></span></button>` : ''; })()}${fzChecksCard(kid, true)}</div>` : ''}
    ${up.length ? `<div class="fh-sec"><h3>Coming up</h3><button onclick="go('pcal')">Calendar</button></div><section class="fh-time">${up.map(e => { const d = new Date(e.date + 'T00:00:00'); return `<div><span class="${e.type === 'Holiday' ? 'hol' : e.type === 'Exam' ? 'exam' : e.type === 'PTM' ? 'ptm' : 'evt'}"><b>${d.getDate()}</b>${d.toLocaleDateString('en-IN', { month: 'short' })}</span><p><b>${esc(e.title)}</b><small>${esc(e.type || '')}${fzDaysTo(e.date) > 0 ? ' · in ' + fzDaysTo(e.date) + ' day' + (fzDaysTo(e.date) > 1 ? 's' : '') : ' · today'}</small></p></div>`; }).join('')}</section>` : ''}
    ${nt ? `<button class="fh-notice" onclick="go('pnotices')"><small>📢 Latest notice · ${esc(fmtDate(String(nt.date || '')))}</small><b>${esc(nt.title || '')}</b>${nt.body ? `<p>${esc(String(nt.body).slice(0, 140))}${String(nt.body).length > 140 ? '…' : ''}</p>` : ''}</button>` : ''}
    <div class="fh-sec"><h3>All updates</h3></div>
  </div>`;
}

// ---------------------------------------------------------------- register views + menu
if (typeof VIEWS !== 'undefined' && typeof NAV !== 'undefined') {
  Object.assign(VIEWS, { ptmr: vPTomorrow, pcal: vPCal, prec: vPRecord, psyl: vPSyl, pzone: vPZone, pset: vPSettings, pchk: vPChecks, pinfo: vPInfo, pleave: vPLeave });
  const res0 = VIEWS.presults; if (res0) VIEWS.presults = () => { const kid = PD && PD.kids && PD.kids.length ? pKid() : null; if (!kid) return res0();
    const T = fzTerm(kid); let h = res0(); if (T) h = h.replace('<div class="card text-sm text-slate-500">No exam marks published yet.</div>', ''); const extra = fzResultCard(kid) + fzChecksCard(kid, true), at = h.indexOf('<div class="card">'); return at < 0 ? h + extra : h.slice(0, at) + extra + h.slice(at); };
  const add = [['pinfo', 'fa-id-card', 'Student Profile'], ['pleave', 'fa-calendar-xmark', 'Leave Application'], ['pchk', 'fa-clipboard-check', 'Homework & Copy Checks'], ['ptmr', 'fa-calendar-day', 'Next School Day'], ['pcal', 'fa-calendar', 'Calendar'], ['prec', 'fa-trophy', 'Achievements'], ['psyl', 'fa-list-check', 'Exam Syllabus'], ['pzone', 'fa-lightbulb', 'Learning Hub'], ['pset', 'fa-gear', 'Settings']];
  for (const role of ['parent', 'student']) { if (!NAV[role]) continue; add.forEach(x => { if (!NAV[role].some(y => y[0] === x[0])) NAV[role].push(x); });
    if (typeof GROUPS !== 'undefined' && GROUPS[role]) { const g = GROUPS[role]; g[0][2].push('pinfo', 'pleave', 'ptmr', 'pcal'); g[1][2].push('pzone'); g[g.length - 1][2].push('pchk', 'prec', 'psyl', 'pset'); } }
  // web (not the app): quick links on the parent home
  const home = VIEWS.phome;
  if (home) VIEWS.phome = () => { const h = home(); if (!PD || !PD.kids.length || document.documentElement.classList.contains('sn-family')) return h; const kid = pKid();
    return `<div class="fz-quick">${[['ptmr', '🎒', 'Next day'], ['pcal', '📅', 'Calendar'], ['prec', '🏆', 'Achievements'], ['pzone', '💡', 'Learning Hub'], ['pset', '⚙️', 'Settings']].map(([k, e, l]) => `<button onclick="go('${k}')"><span>${e}</span>${l}</button>`).join('')}</div>${gyStatusRow(kid)}` + h; };
}
// Principal / Chairman (web ERP): Family App usage report (famstats.php)
if (typeof VIEWS !== 'undefined' && typeof NAV !== 'undefined') {
  for (const role of ['principal', 'chairman']) if (NAV[role] && !NAV[role].some(x => x[0] === 'famstats')) NAV[role].push(['famstats', 'fa-mobile-screen', 'Family App Usage']);
  VIEWS.famstats = () => head('Family App usage', 'Who has logged in to the Student & Parents app, who uses it, and who has not yet.', '<a href="famstats.php" target="_blank" class="btn btn-l"><i class="fa-solid fa-up-right-from-square"></i> Open full page</a>')
    + '<iframe title="Family App usage" src="famstats.php?embed=1" style="width:100%;min-height:85vh;border:0;border-radius:16px"></iframe>';
}
// For the Family app chat list (family-start.js): one-line previews
window.FZ = {
  tomorrow(kid) { const nx = fzNextSchoolDay(kid); if (!nx) return ''; const day = ((PD.tt || {})[kid.cls] || [])[nx.idx]; const s = day ? [...new Set(day.filter(Boolean).map(x => x.s))] : [];
    const ex = examItems().filter(e => e.cls.includes(kid.cls) && e.date === nx.date); return (ex.length ? '📝 ' + ex[0].title + ' · ' : '') + (s.length ? s.slice(0, 4).join(', ') + (s.length > 4 ? '…' : '') : 'Bag list and homework to finish'); },
  tomorrowDate(kid) { const nx = fzNextSchoolDay(kid); return nx ? nx.date : ''; },
  record(kid) { const sum = (PD.attSum || {})[kid.id]; const sp = sum && sum.total ? Math.round(sum.present / sum.total * 100) : null; const got = fzBadges(kid).filter(b => b[3]).length;
    return (sp != null ? 'Attendance ' + sp + '% this session · ' : '') + got + ' badge' + (got === 1 ? '' : 's'); },
  calendar(kid) { const d = todayISO(); const n = [...S.cal, ...examItems().filter(e => e.cls.includes(kid.cls))].filter(e => (e.to || e.date) >= d).sort((a, b) => a.date.localeCompare(b.date))[0]; return n ? n.title + ' · ' + fmtDate(n.date) : 'Holidays, exams and events'; },
  zone() { return '💡 ' + fzPick(GYAAN.tricks).t + ' · 📖 ' + fzPick(GYAAN.stories).t; },
  result(kid) { const T = fzTerm(kid); return T ? T.exam + ' · ' + T.pct + '% · Grade ' + grade(T.pct) + (T.rank ? ' · ' + FZ_ORD(T.rank) + ' in class' : '') : ''; },
  checks(kid) { const K = fzChecks(kid); return K.hw.length || K.cp.length ? (K.pct != null ? K.pct + '% homework done' : '') + (K.not ? ' · ' + K.not + ' not done' : '') + ' · ' + K.cpOk + ' copies complete' : ''; },
  status: gyStatusRow,
  home: fzHome
};
