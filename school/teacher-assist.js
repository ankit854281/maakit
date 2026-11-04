// Suggestions use only this class/subject's saved syllabus. Teachers review before saving.
function syllabusSuggestions(key, kind, query) {
  const chapters = ((S.syl[key] || {}).chapters || []);
  const topics = chapters.flatMap(ch => [String(ch.n || ''), ...(ch.topics || []).filter(t => typeof t === 'string')]).filter(Boolean);
  const words = topics.flatMap(topic => topic.match(/[\p{L}\p{M}\p{N}]{2,}/gu) || []);
  const sentences = kind === 'homework' ? topics.map(topic => 'Revise ' + topic + ' and write what you understood.')
    : kind === 'goal' ? topics.map(topic => 'Explain ' + topic + ' with examples and check understanding.') : topics;
  const fragment = query.trim().toLocaleLowerCase();
  const lastWord = fragment.split(/\s+/).pop();
  return [...new Set([...sentences, ...words])].filter(text => !fragment || text.toLocaleLowerCase().includes(fragment) ||
    (lastWord.length > 1 && text.toLocaleLowerCase().includes(lastWord))).slice(0, 5);
}
function suggestionBox(id, key, kind) {
  return `<div id="${id}-suggest" class="syllabus-suggestions" data-key="${esc(key)}" data-kind="${kind}" aria-label="Syllabus suggestions"></div>`;
}
function showSyllabusSuggestions(input) {
  const box = $(input.id + '-suggest');
  if (!box || !S) return;
  const start = input.value.lastIndexOf('\n', Math.max(0, input.selectionStart - 1)) + 1;
  const query = input.value.slice(start, input.selectionStart);
  const choices = syllabusSuggestions(box.dataset.key, box.dataset.kind, query);
  box.replaceChildren();
  const label = document.createElement('small');
  label.textContent = choices.length ? 'From your syllabus · tap to use' : 'No matching syllabus topic. You can keep typing.';
  box.appendChild(label);
  choices.forEach(text => {
    const button = document.createElement('button');
    button.type = 'button'; button.textContent = text;
    button.onmousedown = event => event.preventDefault();
    button.onclick = () => {
      const end = input.selectionStart;
      const wordStart = input.value.slice(0, end).search(/[\p{L}\p{M}\p{N}]*$/u);
      const replaceFrom = /\s/.test(text) ? start : Math.max(start, wordStart);
      const prefix = input.value.slice(0, replaceFrom), suffix = input.value.slice(end);
      const next = prefix + text + suffix;
      if (input.maxLength > 0 && next.length > input.maxLength) return toast('This suggestion is too long for the field.');
      input.value = next; input.focus(); input.setSelectionRange(prefix.length + text.length, prefix.length + text.length);
      input.dispatchEvent(new Event('input', { bubbles: true }));
      box.replaceChildren();
    };
    box.appendChild(button);
  });
}
document.addEventListener('input', event => showSyllabusSuggestions(event.target));
document.addEventListener('focusin', event => showSyllabusSuggestions(event.target));

function nextSchoolDay(date, cls) {
  for (let offset = 1; offset <= 370; offset++) {
    const next = addDays(date, offset);
    if (isWorkDay(next, cls)) return next;
  }
  return null;
}
function diaryAutoFill(date, bell, cls, subject) {
  const id = 'b' + bell;
  const topic = $(id + '-t').value.trim() || nextChapter(cls, subject);
  if (!topic) return toast('Add your syllabus chapters first, or type the topic.');
  const values = { t: topic, h: 'Revise ' + topic + ' and write what you understood.', rev: 'Revise ' + topic + '.' };
  let changed = 0;
  Object.entries(values).forEach(([field, value]) => {
    const input = $(id + '-' + field);
    if (!input.value.trim()) {
      input.value = value.slice(0, input.maxLength);
      input.classList.remove('auto-filled');
      void input.offsetWidth;
      input.classList.add('auto-filled');
      changed++;
    }
  });
  toast(changed ? 'Draft filled. Review the work and enter homework minutes before saving.' : 'Your writing is already filled. Use the syllabus suggestions to edit.');
}
function homeworkNextDay(id, date, cls) {
  const next = nextSchoolDay(date, cls);
  if (!next) return toast('No school day found. Check the holiday calendar.');
  $(id + '-date').value = next;
  toast('Homework date: ' + fmtDate(next) + '. Sundays and saved holidays skipped.');
}

function attendanceCalendar(cls, selected) {
  const ym = P.calendarMonth || ymOf(selected), today = todayISO();
  const first = ym + '-01', offset = new Date(first + 'T00:00:00').getDay();
  const loaded = !!MONTHS[cls + '|' + ym] || first >= (loadedFrom || today);
  const cells = Array.from({ length: offset }, () => '<span></span>');
  for (let n = 1; n <= daysIn(ym); n++) {
    const date = ym + '-' + pad(n);
    const holiday = dayIdx(date) < 0 ? 'Sunday' : (holidayOn(date) || clsHolOn(date, cls) || {}).title;
    const status = holiday ? 'Holiday' : date > today ? 'Upcoming' : !loaded ? 'Open' : attOf(date, cls) ? 'Saved' : 'Pending';
    cells.push(`<button type="button" class="calendar-day ${holiday ? 'holiday' : status.toLowerCase()}" ${date > today ? 'disabled' : ''} aria-pressed="${date === selected}" aria-label="${esc(fmtDate(date) + ' · ' + (holiday || status))}" onclick="attendancePick(${jsq(cls)},'${date}')"><b>${n}</b><small>${esc(holiday === 'Sunday' ? 'Sun' : status)}</small></button>`);
  }
  return `<details class="card attendance-calendar"><summary>Attendance calendar · ${ymName(ym)}</summary><div class="row"><button class="btn s w" onclick="attendanceMonth(${jsq(cls)},'${ymAdd(ym,-1)}')" aria-label="Previous month">${ic('back')}</button><b>${ymName(ym)}</b><button class="btn s w" ${ym >= ymOf(today) ? 'disabled' : ''} onclick="attendanceMonth(${jsq(cls)},'${ymAdd(ym,1)}')" aria-label="Next month">${ic('next')}</button></div><div class="calendar-grid">${['Sun','Mon','Tue','Wed','Thu','Fri','Sat'].map(d=>`<span class="calendar-label">${d}</span>`).join('')}${cells.join('')}</div><p class="mut">Sunday and saved holidays are automatic. Tap a date to mark attendance.</p></details>`;
}
function attendancePick(cls, date) {
  if (DRAFT.att && DRAFT.att.changed && !confirm('Leave attendance changes without saving?')) return;
  DRAFT.att = null; go('att', { c: cls, date });
}
async function attendanceMonth(cls, ym) {
  if (DRAFT.att && DRAFT.att.changed && !confirm('Leave attendance changes without saving?')) return;
  try {
    await ensureMonth(cls, ym);
    DRAFT.att = null; P.calendarMonth = ym; render();
    document.querySelector('.attendance-calendar').open = true;
  } catch (e) { toast(e.message); }
}
