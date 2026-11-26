// Voice commands and the Connect overview use the teacher's existing app access.
'use strict';

const teacherVoiceCommands = [
  ['work', 'What is pending?', 'आज क्या बाकी है?', /\b(pending|baaki|baki|today.?s work)\b|बाकी|लंबित/u],
  ['followups', 'Follow-ups', 'फ़ॉलो-अप', /\b(follow ?ups?|callbacks?|call back|call again)\b|फ[ॉो]लो|फ़ॉलो|दोबारा कॉल/u],
  ['fee', 'Fee calls', 'फ़ीस कॉल', /\b(fees?|fees? calling|fees? dues)\b|फ[ी़]+स|फ़ीस|शुल्क/u],
  ['calls', 'Parent calls', 'अभिभावकों को कॉल', /\b(parent calls?|parent calling|ptm calls?)\b|अभिभावक.*कॉल|पेरेंट.*कॉल|पीटीएम.*कॉल/u],
  ['staff', 'Staff chat', 'स्टाफ़ चैट', /\b(staff chat|staff room|teacher chat)\b|स्टाफ|शिक्षक.*चैट/u],
  ['parents', 'Parent inbox', 'अभिभावक संदेश', /\b(parents? (chat|inbox)|inbox)\b|अभिभावक.*संदेश|पेरेंट.*चैट/u],
  ['ptm', 'PTM message', 'पीटीएम संदेश', /\b(ptm|meeting invitation)\b|पीटीएम|बैठक/u],
  ['att', 'Attendance', 'हाज़िरी', /\b(attendance|haziri|hajiri|hazri|upasthiti)\b|ह[ा]\S*िरी|उपस्थिति|अटेंडेंस/u],
  ['diary', 'Class diary', 'क्लास डायरी', /\b(diary|classroom|class work)\b|डायरी|क्लासरूम/u],
  ['homework', 'Homework', 'होमवर्क', /\b(homework|home work|grihkarya)\b|होमवर्क|गृहकार्य/u],
  ['syllabus', 'Syllabus', 'सिलेबस', /\b(syllabus|chapters?|pathya)\b|सिलेबस|पाठ्यक्रम|अध्याय/u],
  ['plan', 'Lesson plan', 'पाठ योजना', /\b(lesson plan|planner)\b|पाठ योजना|लेसन प्लान/u],
  ['results', 'Results', 'रिज़ल्ट', /\b(results?|marks?)\b|रिज़ल्ट|रिजल्ट|अंक|परिणाम/u],
  ['leave', 'Leave', 'छुट्टी', /\b(leave|chhutti|chutti)\b|छुट्टी|अवकाश/u],
  ['connect', 'Connect', 'कनेक्ट', /\b(connect)\b|कनेक्ट/u]
];

function teacherVoiceMatch(text) {
  const normalized = text.toLocaleLowerCase().replace(/[?!.,।-]+/g, ' ').replace(/\s+/g, ' ').trim();
  const fee = teacherVoiceCommands.find(([id]) => id === 'fee');
  if (fee[3].test(normalized) && /\b(pending|follow ?ups?|callbacks?|call back|due|overdue)\b|बाकी|दोबारा/u.test(normalized)) return 'feeFollow';
  return teacherVoiceCommands.find(command => command[3].test(normalized))?.[0] || '';
}

function teacherVoiceWork() {
  const today = todayISO();
  const attendance = myCT().filter(c => isWorkDay(today, c) && studentsOf(c).length && !attOf(today, c)).length;
  const diary = myBells(today).filter(b => bellOver(today, b.bell) && !diaryEntry(today, b.bell)).length;
  return [attendance, diary];
}

function teacherVoiceAction(id) {
  const routes = {
    followups: ['conn', { m: 'desk' }], fee: ['conn', { m: 'fee' }], feeFollow: ['conn', { m: 'fee', f: 'follow' }],
    calls: ['conn', { m: 'calls' }], staff: ['conn', { m: 'staff' }], parents: ['conn', { m: 'chat' }],
    ptm: ['conn', { m: 'msg', t: 'ptm' }], att: ['att'], diary: ['diary'], homework: ['diary', { m: 'check' }],
    syllabus: ['plan', { m: 'syl' }], plan: ['plan', { m: 'lp' }], results: ['res'], connect: ['conn', { m: 'desk' }]
  };
  const command = teacherVoiceCommands.find(x => x[0] === id) || teacherVoiceCommands.find(([key]) => key === 'fee');
  const unavailable = ((id === 'fee' || id === 'feeFollow') && !feeCls().length) ||
    (id === 'calls' && !pcCan()) || (id === 'att' && !myCT().length);
  if (unavailable) return { reply: ['This section is unavailable for your current role.', 'इस भूमिका में यह सेक्शन उपलब्ध नहीं है।'] };
  if (id === 'work') {
    const [attendance, diary] = teacherVoiceWork();
    return { reply: ['Attendance pending for ' + attendance + ' classes. Diary pending for ' + diary + ' completed periods.',
      attendance + ' कक्षाओं की हाज़िरी बाकी है। ' + diary + ' पूरे हुए पीरियड की डायरी बाकी है।'] };
  }
  if (id === 'leave') return { reply: ['Opening your leave form.', 'आपका छुट्टी फ़ॉर्म खोल रहा हूँ।'], open: openMe };
  const route = routes[id];
  return route ? { reply: ['Opening ' + command[1] + '.', command[2] + ' खोल रहा हूँ।'], open: () => go(...route) } : null;
}

let teacherVoiceRecognition = null;
let teacherVoiceSession = null;
let teacherVoiceSerial = 0;
let teacherVoiceReply = null;

function teacherVoiceLanguage() {
  return $('tv-lang')?.value || (store.get('ta_lang') === 'en' || !store.get('ta_lang') ? 'en-IN' : 'hi-IN');
}

function teacherVoiceNative(message) {
  const bridge = window.SNMSISTeacherVoice;
  if (!bridge || typeof bridge.postMessage !== 'function') return false;
  try {
    bridge.onmessage = event => {
      try {
        const response = JSON.parse(event.data);
        teacherVoiceResult(response.id, response.text, response.error);
      } catch { /* Ignore messages outside the voice bridge protocol. */ }
    };
    bridge.postMessage(JSON.stringify(message));
    return true;
  } catch { return false; }
}

function teacherVoiceSay(text, lang) {
  if ('speechSynthesis' in window && typeof SpeechSynthesisUtterance !== 'undefined' && !window.SNMSISTeacherVoice) {
    const message = new SpeechSynthesisUtterance(text);
    message.lang = lang;
    message.rate = 0.92;
    window.speechSynthesis.cancel();
    window.speechSynthesis.speak(message);
    return;
  }
  if (!teacherVoiceNative({ action: 'speak', text, lang })) toast(text);
}

function teacherVoiceStatus(text) {
  const status = $('tv-status');
  if (status) status.textContent = text;
}

function teacherVoiceStop() {
  if (teacherVoiceSession) clearTimeout(teacherVoiceSession.timeout);
  teacherVoiceSession = null;
  const active = teacherVoiceRecognition;
  teacherVoiceRecognition = null;
  if (active) active.abort();
  const button = $('tv-mic');
  if (button) button.disabled = false;
}

function teacherVoiceDispatch(text) {
  if (!S || !ME || !$('tv-command')) return;
  const lang = teacherVoiceLanguage(), hindi = lang === 'hi-IN';
  const id = teacherVoiceMatch(text);
  const action = id && teacherVoiceAction(id);
  const reply = action ? action.reply : ['Try Attendance, Fee calls, Staff chat or What is pending?', 'हाज़िरी, फ़ीस कॉल, स्टाफ़ चैट या आज क्या बाकी है बोलें।'];
  teacherVoiceReply = { text: reply[hindi ? 1 : 0], lang };
  teacherVoiceSay(teacherVoiceReply.text, lang);
  if (action && action.open) {
    teacherVoiceStop();
    action.open();
    toast(teacherVoiceReply.text);
  } else {
    teacherVoiceStatus(teacherVoiceReply.text);
  }
}

function teacherVoiceResult(id, text, error) {
  if (!teacherVoiceSession || teacherVoiceSession.id !== id) return;
  const sameLogin = teacherVoiceSession.token === store.get('sch_token');
  teacherVoiceStop();
  if (!sameLogin || !$('tv-command')) return;
  if (!text) {
    teacherVoiceStatus(error || 'No speech detected. Try again or type the command.');
    return;
  }
  $('tv-command').value = text;
  teacherVoiceDispatch(text);
}

function teacherVoiceListen() {
  if (teacherVoiceSession || !$('tv-mic')) return;
  const lang = teacherVoiceLanguage(), id = ++teacherVoiceSerial;
  if ('speechSynthesis' in window) window.speechSynthesis.cancel();
  const timeout = setTimeout(() => teacherVoiceResult(id, '', 'Voice recognition timed out. Try again or type the command.'), 25000);
  teacherVoiceSession = { id, token: store.get('sch_token'), timeout };
  $('tv-mic').disabled = true;
  teacherVoiceStatus('Listening…');
  if (teacherVoiceNative({ action: 'listen', lang, id })) return;

  const Recognizer = window.SpeechRecognition || window.webkitSpeechRecognition;
  if (!Recognizer) return teacherVoiceResult(id, '', 'Microphone commands are unavailable here. Type a command below.');
  try {
    const recognizer = new Recognizer();
    teacherVoiceRecognition = recognizer;
    recognizer.lang = lang;
    recognizer.interimResults = false;
    recognizer.onresult = event => teacherVoiceResult(id, event.results[0][0].transcript);
    recognizer.onerror = event => teacherVoiceResult(id, '', event.error === 'not-allowed'
      ? 'Microphone access is off. You can type the command.'
      : 'Voice recognition could not finish. Try again or type the command.');
    recognizer.onend = () => teacherVoiceResult(id, '');
    recognizer.start();
  } catch {
    teacherVoiceResult(id, '', 'Could not start the microphone. Type a command below.');
  }
}

function teacherVoiceOpen() {
  if (!S || !ME || SHEET) return;
  if ((DRAFT.att?.changed || DRAFT.chk?.changed || DRAFT.kit?.changed || DRAFT.rec?.changed ||
    Object.keys(DRAFT.reg?.days || {}).length || DRAFT.tg?.dirty || DRAFT.he?.dirty || DRAFT.note) &&
    !confirm('Open the assistant while this screen has unsaved edits?')) return;
  const lang = teacherVoiceLanguage();
  SHEET = () => `<div class="row"><h2>Voice assistant</h2><button class="btn s l" onclick="closeSheet()">Close</button></div>
    <div class="two"><label class="fld">Voice language
      <select id="tv-lang" class="inp"><option value="en-IN" ${lang === 'en-IN' ? 'selected' : ''}>English</option>
      <option value="hi-IN" ${lang === 'hi-IN' ? 'selected' : ''}>हिंदी / Hinglish</option></select></label>
      <button id="tv-mic" type="button" class="btn go tv-listen" onclick="teacherVoiceListen()">🎙 Speak</button></div>
    <small class="mut">Use short commands. Your device or browser handles microphone recognition.</small>
    <form class="send" onsubmit="event.preventDefault();teacherVoiceDispatch($('tv-command').value)">
      <input id="tv-command" class="inp" maxlength="150" autocomplete="off" aria-label="Type a voice command" placeholder="Type a command">
      <button class="btn l" type="submit">Ask</button></form>
    <p id="tv-status" class="mut" role="status" aria-live="polite">Try “What is pending?” or “Staff chat”.</p>
    <div class="chips">${teacherVoiceCommands.filter(([id]) => ['work', 'att', 'diary', 'syllabus', 'followups', 'staff'].includes(id))
      .map(([, en, hi]) => `<button type="button" class="chip" onclick="teacherVoiceDispatch(${jsq(en)})">${esc(lang === 'hi-IN' ? hi : en)}</button>`).join('')}</div>
    ${teacherVoiceReply ? '<button type="button" class="btn l" onclick="teacherVoiceSay(teacherVoiceReply.text,teacherVoiceReply.lang)">Replay last reply</button>' : ''}`;
  render();
  $('tv-lang')?.focus();
}

function resetTeacherConnect() {
  CHAT = null; SC = null; SCR = {}; PCS = null;
  for (const cache of [FEE, TOURC]) for (const c of Object.keys(cache)) delete cache[c];
}

function teacherConnectLoad(path, current, save, redraw = render) {
  const token = store.get('sch_token');
  const request = { loading: true };
  save(request);
  const receive = data => {
    if (token !== store.get('sch_token') || current() !== request) return;
    save(data);
    redraw();
  };
  api(path).then(receive).catch(e => receive({ err: e.message }));
}

function teacherConnectRefresh() {
  const token = store.get('sch_token');
  CHAT = null;
  for (const c of Object.keys(FEE)) delete FEE[c];
  if (pcCan()) pcNeed(true);
  scLoad(null).then(() => { if (token === store.get('sch_token')) softRender(); });
  render();
}

function teacherConnectDesk() {
  const canCall = pcCan();
  const data = canCall ? pcNeed() : null;
  const counts = data?.campaigns && !data.err ? pcSum(data) : null;
  const staffCount = SC?.rooms ? scUnread() : '—';
  const quantity = key => counts ? counts[key] : '—';
  const add = (title, line, mode, badge, params = {}, open) => `<button type="button" class="tc-link" onclick="${open || "go('conn'," + esc(JSON.stringify({ m: mode, ...params })) + ')'}">
    <span><b>${title}</b><small>${line}</small></span><span class="tc-number">${badge ?? '›'}</span></button>`;
  return `<div class="card tc-desk"><div class="row"><div><h2>Connect</h2><small class="mut">${esc(fmtLong(todayISO()))}</small></div>
    <button type="button" class="btn s l" onclick="teacherConnectRefresh()">Refresh</button></div>
    ${data?.err ? '<p class="mut" role="status">Parent call totals could not load. Tap Refresh to try again.</p>' : ''}
    ${canCall ? add('Call again', 'Due and overdue callbacks', 'calls', quantity('again'), {}, "pcOpen('&amp;view=again')") : ''}
    ${canCall ? add('Attendance follow-ups', 'Absent children still to call', 'calls', quantity('todo')) : ''}
    ${canCall ? add('PTM & event calls', 'Calls remaining in active lists', 'calls', quantity('left')) : ''}
    ${feeCls().length ? add('Fee follow-ups', 'Payment promises and callbacks due', 'fee', null, { f: 'follow' }) : ''}
    ${!isP() ? add('Parent inbox', 'Search and find conversations awaiting a reply', 'chat') : ''}
    ${add('Staff chat', 'Conversations with new messages', 'staff', staffCount)}
    ${add('Message to parents', 'Attendance, homework, PTM and appreciation', 'msg')}
    </div>`;
}

function teacherConnectFeeFollowup(child, call, promises, today) {
  const isPayment = call?.outcome === 'Promised to pay';
  const scheduled = ['Promised to pay', 'Call back later', 'Will visit school'].includes(call?.outcome);
  const date = scheduled ? call.promise || promises[child.sid]?.date : !call ? promises[child.sid]?.date : '';
  if (!date || date > today || (child.due <= 0 && (isPayment || !call))) return null;
  return date;
}

function teacherConnectFilter(input) {
  const query = input.value.trim().toLocaleLowerCase();
  const panel = input.closest('[data-tc-search]');
  const list = panel?.nextElementSibling;
  if (!list) return;
  let visible = 0;
  for (const row of list.querySelectorAll('[data-tc-query]')) {
    row.hidden = !row.dataset.tcQuery.includes(query);
    if (!row.hidden) visible++;
  }
  const status = panel.querySelector('small');
  if (status) status.textContent = query ? visible + (visible === 1 ? ' match' : ' matches') : '';
}

function teacherConnectSearch(placeholder) {
  return `<div data-tc-search><input id="tc-search" class="inp" type="search" maxlength="80" autocomplete="off" data-keep
    aria-label="${esc(placeholder)}" placeholder="${esc(placeholder)}" oninput="teacherConnectFilter(this)">
    <small class="mut" aria-live="polite"></small></div>`;
}

function teacherConnectParentInbox(kids, messages) {
  const latest = new Map();
  for (const message of messages) {
    if (!latest.has(message.sid) || latest.get(message.sid).at <= message.at) latest.set(message.sid, message);
  }
  return kids.filter(kid => latest.has(kid.id)).map(kid => {
    const message = latest.get(kid.id);
    return { kid, message, awaiting: message.sender.startsWith('p:') };
  }).sort((a, b) => b.message.at - a.message.at);
}

document.addEventListener('keydown', event => {
  if (event.key === 'Escape' && $('tv-command')) closeSheet();
});
document.addEventListener('visibilitychange', () => {
  if (document.hidden && teacherVoiceRecognition) teacherVoiceStop();
});
