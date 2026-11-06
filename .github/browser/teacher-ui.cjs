// Teacher App browser acceptance test, synthetic local CI database only.
// Run against php -S 127.0.0.1:8099 with teacher-flow.php + teacher-ui-seed.php.
const { chromium } = require('/tmp/teacher-playwright/node_modules/playwright');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
(async () => {
  const dir = '/tmp/teacher-ui-screens';
  fs.mkdirSync(dir, { recursive: true });
  const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
  const context = await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2,
    acceptDownloads: true, permissions: [] });
  const page = await context.newPage();
  const errors = [], failures = [];
  page.on('pageerror', e => errors.push('Page: ' + e.message));
  page.on('console', m => { if (m.type() === 'error' && !/net::ERR_INTERNET_DISCONNECTED/.test(m.text())) errors.push('Console: ' + m.text()); });
  let answerDialog = d => d.accept();
  page.on('dialog', d => answerDialog(d));
  const shot = async (n) => page.screenshot({ path: path.join(dir, n + '.png'), fullPage: true });
  const pause = async (ms = 400) => page.waitForTimeout(ms);
  const run = async (name, fn) => {
    try { await fn(); console.log('PASS Teacher UI:', name); }
    catch (e) { failures.push(name + ': ' + e.stack); console.error('FAIL Teacher UI:', name, e.message); }
    try { await shot(name.replace(/[^a-z0-9-]/ig, '-')); } catch (e) { failures.push(name + ' screenshot: ' + e.message); }
  };
  await page.goto('http://127.0.0.1:8099/school/teacher.php', { waitUntil: 'domcontentloaded' });
  await page.locator('#lgT').waitFor();
  await page.evaluate(() => localStorage.setItem('ta_intro_day', todayISO()));   // the opening story is checked in step 20
  await page.locator('#lgT').selectOption('K');
  await page.locator('#lgPin').fill('7777');
  await page.locator('.lg button').filter({ hasText: 'Open' }).click();
  await page.locator('.tabs').waitFor({ timeout: 15000 });
  await run('01-Aaj', async () => {
    assert.match(await page.locator('.tabs button.tab.on').innerText(), /Today/);
    assert.ok((await page.locator('.scroll').innerText()).length > 50);
  });
  await run('02-Haziri-Day', async () => {
    await page.locator('.tabs button.tab').filter({ hasText: 'Attendance' }).click();
    await page.locator('.tile').first().waitFor();
    assert.equal(await page.locator('.tile').count(), 12);
    await page.locator('.tile').nth(0).click();                       // tap once: Present → Absent
    await page.locator('.tile').nth(1).click(); await page.locator('.tile').nth(1).click();   // twice: Leave
    await page.locator('.tile').nth(9).evaluate(el => el.scrollIntoView({ block: 'center' }));
    const before = await page.locator('.scroll').evaluate(el => el.scrollTop);
    await page.locator('.tile').nth(9).click();
    const after = await page.locator('.scroll').evaluate(el => el.scrollTop);
    assert.ok(before > 0 && Math.abs(after - before) < 40, 'tapping student ten must keep scroll position');
    assert.match(await page.locator('.cnt').innerText(), /2 A/);
    assert.match(await page.locator('.cnt').innerText(), /1 L/);
    await page.getByRole('button', { name: /Submit attendance|Update attendance/ }).click();
    await page.locator('a.wa').first().waitFor();
  });
  await run('03-Haziri-Class-Holiday', async () => {
    await page.getByRole('button', { name: 'Mark holiday', exact: true }).first().click();
    await page.locator('#hT').fill('CI class-only holiday');
    await page.locator('.sheet button').filter({ hasText: 'Mark holiday' }).click();
    await page.getByText('CI class-only holiday').first().waitFor();
    await page.getByRole('button', { name: /Remove holiday/ }).click();
    await page.locator('.tile').first().waitFor();
  });
  await run('04-Haziri-Register', async () => {
    await page.evaluate(() => go('att', { c: '9th', m: 'reg', ym: '2026-10' }));
    await page.locator('.reg tbody tr').first().waitFor();
    assert.equal(await page.locator('.reg tbody tr').count(), 12);
    assert.ok((await page.locator('.reg').evaluate(el => el.scrollWidth > el.clientWidth)));
    assert.equal(await page.locator('.reg .nm').first().evaluate(el => getComputedStyle(el).position), 'sticky');
    await page.locator('.reg').evaluate(el => { el.scrollLeft = 220; });
    const xBefore = await page.locator('.reg').evaluate(el => el.scrollLeft);
    await page.evaluate(() => render());
    const xAfter = await page.locator('.reg').evaluate(el => el.scrollLeft);
    assert.ok(xBefore > 0 && Math.abs(xAfter - xBefore) < 10, 'register horizontal scroll must stay after re-render');
    const cell = page.locator('.reg tbody tr').first().locator('td button').first();
    await cell.click();
    await cell.click();
    await cell.click();
    await page.getByRole('button', { name: /Fill them all as Present/ }).click();
    await page.getByRole('button', { name: /Save .* changed day/ }).click();
    await pause(650);
    await page.reload();
    await page.locator('.tabs').waitFor();
    await page.evaluate(() => go('att', { c: '9th', m: 'reg', ym: '2026-10' }));
    await page.locator('.reg tbody tr').first().waitFor();
  });
  await run('05-Haziri-Not-Marked', async () => {
    await page.evaluate(() => go('att', { c: '9th', m: 'pending', from: '2026-10-20' }));
    await page.locator('.chk input[type=checkbox]').first().waitFor();
    await page.locator('.chk input[type=checkbox]').first().check();
    await page.getByRole('button', { name: /Mark .* day\(s\) — everyone present/ }).click();
    await pause(700);
    assert.match(await page.locator('.scroll').innerText(), /Every school day is marked|Not marked/);
  });
  await run('06-Diary', async () => {
    await page.locator('.tabs button.tab').filter({ hasText: 'Classroom' }).click();
    await page.locator('#b0-t').waitFor();
    await page.locator('#b0-t').fill('CI French Revolution');
    await page.locator('#b0-cw').fill('Class questions 1 to 5');
    await page.locator('#b0-h').fill('Review the chapter');
    await page.locator('#b0-min').fill('15');
    const save = page.locator('button').filter({ hasText: /^Save/ }).last();
    await save.click();
    await page.getByText(/Saved/).first().waitFor();
  });
  await run('07-Plan-And-Syllabus', async () => {
    await page.locator('.tabs button.tab').filter({ hasText: 'Plan' }).click();
    await page.getByRole('button', { name: 'Auto fill from syllabus' }).click();
    assert.ok((await page.locator('#lpT').inputValue()).length);
    await page.getByRole('button', { name: /Send to Principal/ }).click();
    await page.getByText(/Submitted|Sent to the Principal/).first().waitFor();
    await page.evaluate(() => go('plan', { m: 'syl' }));
    await page.getByText('The French Revolution').first().waitFor();
  });
  await run('08-Notes', async () => {
    await page.locator('.tabs button.tab').filter({ hasText: 'Planner' }).click(); await page.locator('.seg button').filter({ hasText: 'Notes' }).click();   // Notes live inside Planner
    await page.getByRole('button', { name: /New notes from book photos/ }).click();
    await page.locator('#nC').fill('CI Chapter Note');
    await page.locator('#nB').fill('This note is created only in a CI test database.');
    await page.getByRole('button', { name: 'Save notes' }).click();
    await page.getByText('CI Chapter Note').first().waitFor();
  });
  await run('09-Result', async () => {
    await page.locator('.tabs button.tab').filter({ hasText: 'Result' }).click();
    await page.locator('.mk input').first().waitFor();
    await page.locator('.mk input').first().fill('AB');
    const second = page.locator('.mk input').nth(1);
    await second.fill('99');
    assert.ok((await second.getAttribute('class') || '').includes('bad'), 'over-maximum value must be red');
    await page.getByRole('button', { name: /Save .* marks/ }).click();
    await second.fill('5');
    await page.getByRole('button', { name: /Save .* marks/ }).click();
    await page.getByText(/marks saved/).first().waitFor({ timeout: 10000 });
    await page.evaluate(() => go('res', { c: '9th', m: 'sheet' }));
    assert.ok((await page.locator('.scroll').innerText()).length > 100);
  });
  await run('10-Account-Sheet', async () => {
    await page.locator('button.av').click();
    await page.locator('.sheet').waitFor();
    assert.match(await page.locator('.sheet').innerText(), /leave|PIN|Log out/i);
  });
  await run('11-Offline-Attendance', async () => {
    await page.evaluate(() => { SHEET = null; go('att', { c: '9th' }); });
    await page.locator('.tile').first().waitFor();
    await context.setOffline(true);
    await page.locator('.tile').first().click();
    await page.getByRole('button', { name: /Submit attendance|Update attendance/ }).click();
    await pause(600);
    assert.match(await page.locator('#net').innerText(), /waiting.*offline/i);
    await context.setOffline(false);
    await page.evaluate(() => flush());
    await page.waitForFunction(() => document.querySelector('#net') && /Saved/i.test(document.querySelector('#net').textContent), { timeout: 12000 });
  });
  await run('12a-Syllabus-assistance-and-calendar', async () => {
    await page.evaluate(() => { SHEET = null; go('diary', {date: todayISO(), bell: 0}); });
    await page.locator('#b0-h').fill('French');
    await page.locator('#b0-h-suggest button').first().click();
    assert.match(await page.locator('#b0-h').inputValue(), /French Revolution/);
    await page.locator('#b0-t').fill('Teacher written topic');
    await page.getByRole('button', {name:'Auto fill from syllabus'}).first().click();
    assert.equal(await page.locator('#b0-t').inputValue(), 'Teacher written topic');
    assert.equal(await page.evaluate(() => syllabusSuggestions('unassigned|subject', 'homework', '').length), 0);
    assert.equal(await page.evaluate(() => {
      const original = S.cal;
      try { S.cal = [{type:'Holiday', date:'2026-11-09', title:'Test closure'}]; return nextSchoolDay('2026-11-07', '9th'); }
      finally { S.cal = original; }
    }), '2026-11-10');
    await page.evaluate(() => go('att', {c:'9th', m:'cal'}));
    await page.locator('.cal-big .calendar-day').first().waitFor();
    assert.ok(await page.locator('.calendar-day.holiday').count());
    assert.ok(await page.locator('.calendar-day[aria-pressed=true]').count());
  });
  await run('12-Console', async () => { assert.deepEqual(errors, [], 'browser console/page errors'); });
  // A separate browser session checks the real Principal ERP screen, not only its PHP API.
  await run('13-Principal-Notes-Page', async () => {
    const ctx = await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2 });
    const office = await ctx.newPage();
    const officeErrors = [];
    office.on('pageerror', e => officeErrors.push(e.message));
    await office.goto('http://127.0.0.1:8099/school/', { waitUntil: 'domcontentloaded' });
    const login = await office.evaluate(async () => {
      const r = await fetch('api.php?a=login', { method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ role: 'principal', pin: '2468' }) });
      return r.json();
    });
    assert.ok(login.ok && login.token, 'Principal login token');
    await office.evaluate(token => localStorage.setItem('sch_token', token), login.token);
    await office.reload({ waitUntil: 'domcontentloaded' });
    await office.waitForFunction(() => typeof U !== 'undefined' && U && U.role === 'principal', null, { timeout: 15000 });
    await office.evaluate(() => go('tnotes'));
    await office.getByText('Teacher chapter notes').waitFor({ timeout: 15000 });
    await office.locator('summary').filter({ hasText: '9th' }).click();
    await office.getByText('CI Chapter Note').first().waitFor();
    await office.locator('button').filter({ hasText: 'View note' }).first().click();
    await office.getByText('This note is created only in a CI test database.').waitFor();
    await office.screenshot({ path: path.join(dir, '13-Principal-ERP-Detail.png'), fullPage: true });
    assert.deepEqual(officeErrors, [], 'Principal page errors');
    await ctx.close();
  });
  await run('14-Parent-Shared-Notes', async () => {
    const result = await page.evaluate(async () => {
      const response = await fetch('api.php?a=login', { method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ role: 'parent', phone: '9876500001', pin: '4455' }) });
      const auth = await response.json();
      if (!auth.ok || !auth.token) return { error: 'Parent login failed' };
      const data = await fetch('api.php?a=pdata', { headers: { 'X-School-Token': auth.token } });
      return data.json();
    });
    assert.ok(result.ok && Array.isArray(result.notes), 'Parent homework notes');
    assert.ok(result.notes.some(n => n.chapter === 'CI Chapter Note'), 'Parent must receive shared teacher note');
    assert.ok(result.notes.every(n => !Object.hasOwn(n, 'tid')), 'No teacher internal IDs in parent note response');
  });
  await run('15-Unsaved-typing-survives-refresh', async () => {
    // Owner, 5 Nov 2026: marks and diary typed but not saved must survive the 45-second refresh and other saves.
    await page.evaluate(() => { SHEET = null; go('res', { c: '9th', m: 'marks', exam: 'Term-2' }); });
    await page.locator('.mk input').first().waitFor();
    await page.locator('.mk input').nth(4).fill('7');
    await page.evaluate(() => { document.activeElement.blur(); softRender(); });
    assert.equal(await page.locator('.mk input').nth(4).inputValue(), '7', 'typed mark must survive a background refresh');
    await page.evaluate(() => go('diary'));
    await page.evaluate(() => go('res', { c: '9th', m: 'marks', exam: 'Term-2' }));
    await page.locator('.mk input').first().waitFor();
    assert.equal(await page.locator('.mk input').nth(4).inputValue(), '7', 'typed mark must survive switching tabs');
    const d = await page.evaluate(() => addDays(todayISO(), -2));
    await page.evaluate(d => go('diary', { date: d, bell: 5, add: { date: d, bell: 5, cls: '9th', subj: 'Social Science', extra: true } }), d);
    await page.locator('#b5-t').waitFor();
    await page.locator('#b5-t').fill('Typed, not saved');
    await page.evaluate(() => { document.activeElement.blur(); softRender(); });
    assert.equal(await page.locator('#b5-t').inputValue(), 'Typed, not saved', 'diary typing must survive a background refresh');
    const hwDay = await page.locator('#b5-date').inputValue();
    assert.ok(hwDay > d, 'homework must default to the next school day, not the same day');
  });
  await run('16-Incomplete-result-not-graded', async () => {
    await page.evaluate(() => go('res', { c: '9th', m: 'sheet', exam: 'Term-1' }));
    await page.locator('.res').waitFor();
    const txt = await page.locator('.res').innerText();
    assert.match(txt, /Pending/, 'a student with a missing subject shows Pending, not a grade');
    assert.equal(await page.locator('.res a.wa').count(), 0, 'no WhatsApp result while a subject is missing');
  });
  await run('17-Hindi-and-Hinglish', async () => {
    await page.evaluate(() => { setLang('hi'); go('att', { c: '9th' }); });
    await page.locator('.tile').first().waitFor();
    assert.match(await page.locator('.tabs').innerText(), /हाज़िरी/);
    assert.match(await page.locator('.scroll').innerText(), /उपस्थित|अनुपस्थित/);
    await page.evaluate(() => setLang('hg'));
    await pause(200);
    assert.match(await page.locator('.tabs').innerText(), /Haziri/);
    await page.evaluate(() => setLang('en'));
    await pause(200);
    assert.match(await page.locator('.tabs').innerText(), /Attendance/);
  });
  await run('18-Attendance-undo-calendar-report', async () => {
    await page.evaluate(() => { setLang('en'); SHEET = null; DRAFT.att = null; go('att', { c: '9th', m: 'day', date: addDays(todayISO(), -1) }); });
    if (await page.locator('.tile').count()) {
      const before = await page.evaluate(() => JSON.stringify(attOf(addDays(todayISO(), -1), '9th')));
      await page.locator('.tile').nth(2).click();
      await page.locator('.btn.submit').click();
      await page.locator('.submitted').waitFor();
      await page.getByRole('button', { name: 'Undo' }).click();
      await pause(300);
      assert.equal(await page.evaluate(() => JSON.stringify(attOf(addDays(todayISO(), -1), '9th'))), before, 'Undo must bring back the earlier attendance (or none)');
    }
    await page.evaluate(() => go('att', { c: '9th', m: 'cal' }));
    await page.locator('.cal-big').waitFor();
    assert.match(await page.locator('.att-report').innerText(), /Attendance report/);
    const stats = await page.evaluate(() => api('teacher_api.php?a=attstats&cls=9th'));
    assert.ok(stats.ok && stats.days >= 1, 'attendance statistics');
  });
  await run('19-Classroom-phrases-and-check-work', async () => {
    await page.evaluate(() => { setLang('en'); SHEET = null; const d = addDays(todayISO(), -3); go('diary', { date: d, bell: 6, add: { date: d, bell: 6, cls: '9th', subj: 'Social Science', extra: true } }); });
    await page.locator('#b6-h').waitFor();
    await page.locator('.dform:has(#b6-h) .chips.phr').nth(1).locator('button').first().click();
    assert.match(await page.locator('#b6-h').inputValue(), /Learn the questions-answers of/);
    assert.equal(await page.locator('#b6-min').inputValue(), '15', 'homework chip fills 15 minutes when empty');
    await page.evaluate(() => go('diary', { m: 'check' }));
    await page.locator('.chk-row').first().waitFor();
    assert.equal(await page.locator('.chk-row').count(), 12);
    await page.getByRole('button', { name: 'All homework done' }).click();
    await page.locator('.chk-row').nth(2).locator('button.not').click();
    await page.locator('.chk-row').nth(2).locator('button.cp').click();
    assert.match(await page.locator('.chk-card .cnt').innerText(), /1 not/);
    await page.locator('.chk-card .sticky .btn.go').click();
    await page.getByText(/Checking saved|Saved/).first().waitFor();
    assert.ok(await page.locator('a.wa').count() >= 1, 'parents of a child who did not do homework can be told on WhatsApp');
  });
  await run('20-Opening-story', async () => {
    await page.evaluate(() => { SHEET = null; showTeacherWelcome(true); });
    await page.locator('#ta-welcome .tw-s.s1.on').waitFor();
    const first = await page.locator('#twLang').innerText();
    await pause(900);
    assert.notEqual(await page.locator('#twLang').innerText(), first, 'SNMSIS changes language');
    assert.equal(await page.locator('.tw-bars i').count(), 3, 'three layers only (owner)');
    await page.locator('.tw-tap.r').click();
    await page.locator('#ta-welcome .tw-s.s3.on').waitFor();
    assert.match(await page.locator('.tw-s.s3').innerText(), /Kartikey/);
    await page.locator('.tw-tap.r').click();
    await page.locator('#ta-welcome .tw-s.s4.on').waitFor();
    await page.locator('.tw-skip').click();
    await page.waitForFunction(() => !document.getElementById('ta-welcome'));
  });
  await run('21-Connect-fee-bill-messages-chat', async () => {
    await page.evaluate(() => { SHEET = null; setLang('en'); });
    await page.locator('.tabs button.tab').filter({ hasText: 'Connect' }).click();
    await page.locator('.fee-row').first().waitFor();
    const aman = page.locator('.fee-row').filter({ hasText: 'Aman' });
    assert.match(await aman.innerText(), /₹2,500/, 'fee due from the fee card');
    assert.match(await page.locator('.fee-sum').innerText(), /with dues/);
    await aman.locator('button[aria-label^="Call result"]').click();
    await page.locator('.sheet .chip').filter({ hasText: 'Promised to pay' }).click();
    await page.locator('#fcA').fill('1000'); await page.locator('#fcN').fill('Father will pay Saturday');
    await page.locator('.sheet .btn.go').click();
    await page.getByText(/Call saved/).first().waitFor();
    await page.evaluate(() => go('conn', { m: 'fee', f: 'all' }));
    assert.match(await page.locator('.fee-row').filter({ hasText: 'Aman' }).innerText(), /Promised to pay|Will pay/);
    await page.locator('.fee-row').filter({ hasText: 'Aman' }).locator('button[aria-label^="Demand bill"]').click();
    assert.match(await page.locator('.sheet pre.bill').innerText(), /FEE DEMAND BILL[\s\S]*₹2,500/);
    assert.ok(await page.locator('.sheet a.wa').count() === 1);
    await page.evaluate(() => { SHEET = null; go('conn', { m: 'msg', t: 'praise' }); });
    await page.locator('.pk').first().click(); await page.locator('.pk').nth(1).click();
    assert.equal(await page.locator('a.wa').count(), 2, 'one WhatsApp per chosen child');
    assert.match(await page.locator('pre.preview').innerText(), /Well done/);
    await page.evaluate(() => go('conn', { m: 'chat' }));
    await page.locator('#chK').waitFor();
    await page.locator('#chK').selectOption('s1');
    await page.locator('#chT').fill('CI hello parent');
    await page.locator('.send .btn.go').click();
    await page.locator('.bub.out').filter({ hasText: 'CI hello parent' }).waitFor({ timeout: 10000 });
    // A subject teacher who is not class teacher never sees fee calls (server refuses; tab hides it).
  });
  // ---- owner, 5 Nov 2026 (4): attendance on the diary card, Q-number chips, CW → HW ideas, Attendance message,
  // syllabus books/pages/exams + chapter text, question paper, My work check, students for subject teachers.
  await run('22-Classroom-qno-and-ideas', async () => {
    await page.evaluate(() => { SHEET = null; setLang('en'); go('diary', { date: todayISO(), bell: 0 }); });
    await page.locator('#b0-t').waitFor();
    assert.match(await page.locator('.attl').first().innerText(), /present|not marked/, 'class attendance shown small on the diary card');
    const qn = page.locator('#b0-h').locator('xpath=..').locator('.qn button');
    for (const n of ['6', '7', '8', '9']) await qn.filter({ hasText: new RegExp('^' + n + '$') }).click();
    assert.match(await page.locator('#b0-h').inputValue(), /Q\. 6–9/);
    await qn.filter({ hasText: /^9$/ }).click();
    assert.match(await page.locator('#b0-h').inputValue(), /Q\. 6–8/, 'tapping again updates the same text');
    await page.locator('#b0-cw').fill('Ex 2.1 Q1-5 done. Read the chapter in class.');
    await page.locator('#b0-idea button').first().waitFor();
    const ideas = await page.locator('#b0-idea').innerText();
    assert.match(ideas, /Q\. 6–9 of Ex 2\.1/, 'next questions follow the class work');
    assert.match(ideas, /difficult words/);
    await page.locator('#b0-idea button').filter({ hasText: /next questions/ }).click();
    assert.match(await page.locator('#b0-h').inputValue(), /Do the next questions/);
    await page.evaluate(() => go('conn', { m: 'msg', t: 'absent' }));
    assert.match(await page.locator('.card').filter({ hasText: '1 · Choose the message' }).innerText(), /Attendance[\s\S]*(attendance register|not marked)/);
  });
  await run('23-Syllabus-books-pages-exams-text', async () => {
    await page.evaluate(() => go('plan', { m: 'syl' }));
    await page.locator('#sylBk').fill('CI Reader');
    await page.locator('#sylNc').fill('3');
    await page.getByRole('button', { name: 'Make rows' }).click();
    const rows = page.locator('.syi');
    assert.equal(await rows.count(), 3);
    const data = [['CI Water Cycle', '1', '9'], ['CI Rivers of India', '10', '18'], ['CI Soil', '19', '25']];
    for (let i = 0; i < 3; i++) { const f = rows.nth(i).locator('input'); await f.nth(0).fill(data[i][0]); await f.nth(1).fill(data[i][1]); await f.nth(2).fill(data[i][2]); }
    await page.getByRole('button', { name: /Save chapters of CI Reader/ }).click();
    await page.getByText('Chapters saved.').first().waitFor();
    const water = page.locator('.syr').filter({ hasText: 'CI Water Cycle' });
    assert.match(await water.innerText(), /Page 1–9/);
    await water.locator('.exb').filter({ hasText: 'UT-3' }).click();
    await page.locator('.syr').filter({ hasText: 'CI Rivers of India' }).locator('.exb').filter({ hasText: 'UT-3' }).click();
    await page.getByRole('button', { name: /Save exam chapters/ }).click();
    const ex = await page.evaluate(() => { const y = Object.values(S.syl).find(v => (v.chapters || []).some(c => c.n === 'CI Water Cycle')); return y.chapters.filter(c => (c.ex || []).includes('ut3')).map(c => c.n + '|' + c.book + '|' + c.pf + '-' + c.pt); });
    assert.deepEqual(ex, ['CI Water Cycle|CI Reader|1-9', 'CI Rivers of India|CI Reader|10-18']);
    await page.locator('.chip').filter({ hasText: /^UT-3 2$/ }).click();
    assert.ok(await page.locator('a.wa').filter({ hasText: 'UT-3 syllabus' }).count() === 1, 'exam syllabus can be sent to the class group');
    await water.locator('.txb').click();
    await page.locator('#chT').fill('The Water Cycle\nWater evaporates from the oceans because of the heat of the sun. The water vapour rises and cools to form clouds in the sky. Clouds bring rain when the droplets become heavy. Rain water flows back to the oceans through rivers.\nEvaporation\nThe sun heats the water of the oceans and rivers every day. Evaporation is faster on a hot and windy day.\nCondensation\nWhen water vapour cools it changes into tiny droplets of water.\nExercise\n1. What is evaporation?\n2. How are clouds formed?\n3. Why does it rain?');
    await page.locator('.sheet .btn.go').filter({ hasText: 'Save text' }).click();
    await page.getByText(/Chapter text saved/).first().waitFor();
    await page.locator('.syr').filter({ hasText: 'CI Water Cycle' }).locator('.txb.on').waitFor();
  });
  await run('24-Question-paper', async () => {
    await page.evaluate(() => go('plan', { m: 'paper', ex: 'ut3' }));
    await page.getByRole('button', { name: 'Make the paper' }).click();
    const out = await page.locator('#ppOut').inputValue();
    assert.match(out, /UT-3 · Class 9th/);
    assert.match(out, /Section A — Fill in the blanks/);
    assert.match(out, /What is evaporation\?|How are clouds formed\?|Why does it rain\?/, 'questions come from the chapter text');
    assert.match(out, /Chapters: CI Water Cycle, CI Rivers of India/);
  });
  await run('27-Syllabus-contents-photo-rows', async () => {
    const route = '**/scan_api.php?a=read';
    let response = { ok: false, noAi: true, error: 'AI ki aaj ki free seema khatam — phone padh raha hai' };
    await page.route(route, r => r.fulfill({ json: response }));
    try {
      await page.evaluate(() => {
        DRAFT.sb = null;
        go('plan', { m: 'syl' });
        AI = true;
        window.Tesseract = { recognize: async () => ({ data: { text: [
          'Foreword ii', 'About this Book v', 'Preface iii', 'Contents', 'Chapter', '§ Chapter', 'Unit',
          'Answer Key 81', 'Glossary 90', 'Appendix 92', 'Introduction', 'प्रस्तावना ii', 'आमुख iii',
          'विषय-सूची', 'उत्तरमाला 98', '& f 3 The River .... 2', 'st A New Day ........ 9',
          'Chapter 3: Water & Soil 14', 'hj A Walk', 'Lesson 5: Birds … २२', 'पहला पाठ vi'
        ].join('\n') } }) };
      });
      await page.locator('#sylBk').fill('CI Photo Book');
      const upload = () => page.locator('input[type=file]').setInputFiles({
        name: 'contents.png', mimeType: 'image/png',
        buffer: Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/aXcAAAAASUVORK5CYII=', 'base64')
      });
      await upload();
      await page.getByText('Phone read the photo. Please check the rows before saving.').waitFor();
      assert.match(await page.locator('#toast').innerText(), /AI ki aaj ki free seema khatam/);
      assert.equal(await page.locator('#scL').count(), 0);
      const draft = () => page.evaluate(() => DRAFT.sb.rows.map(r => [r.n, r.pf, r.pt, r.uncertain]));
      assert.deepEqual(await draft(), [
        ['The River', '2', '8', true], ['A New Day', '9', '13', true], ['Water & Soil', '14', '', true],
        ['A Walk', '', '21', true], ['Birds', '22', '', false], ['पहला पाठ', 'vi', '', false]
      ]);
      assert.equal(await page.locator('.syi.review').count(), 4);
      await page.getByRole('button', { name: 'Remove row 4', exact: true }).click();
      assert.deepEqual((await draft())[2].slice(0, 3), ['Water & Soil', '14', '21']);
      await page.locator('.syi').nth(0).getByRole('textbox', { name: 'Chapter name' }).fill('The Big River');
      await page.locator('.syi').nth(1).getByRole('textbox', { name: 'From page' }).fill('10');
      assert.equal(await page.locator('.syi').first().getByRole('textbox', { name: 'To page' }).inputValue(), '9');
      assert.ok(await page.locator('.syi').first().evaluate(el => {
        const row = el.getBoundingClientRect();
        return innerWidth === 390 && row.right <= innerWidth &&
          [...el.children].every(c => c.getBoundingClientRect().right <= row.right + 1);
      }));
      await page.getByRole('button', { name: 'Save chapters of CI Photo Book' }).click();
      await page.locator('.syr').filter({ hasText: 'The Big River' }).waitFor();

      response = { ok: true, uncertain: false, chapters: [
        { name: 'Foreword', page: 'i' }, { name: 'CI Roman Start', page: 'ii', topics: ['A topic'] },
        { name: 'CI Roman Next', page: 'v', topics: [] }, { name: 'CI Page Missing', page: '', topics: [] }
      ] };
      await page.evaluate(() => { AI = true; });
      await page.locator('#sylBk').fill('CI AI Book');
      await upload();
      await page.getByText('AI read the photo. Please check the rows before saving.').waitFor();
      assert.deepEqual(await draft(), [
        ['CI Roman Start', 'ii', 'iv', false], ['CI Roman Next', 'v', '', false], ['CI Page Missing', '', '', true]
      ]);
      await page.getByRole('button', { name: 'Save chapters of CI AI Book' }).click();
      const saved = await page.evaluate(() => S.syl[DRAFT.sylHint].chapters.find(ch => ch.n === 'CI Roman Start'));
      assert.deepEqual([saved.book, saved.pf, saved.pt, saved.topics], ['CI AI Book', 'ii', 'iv', ['A topic']]);
    } finally {
      await page.unroute(route);
      await page.evaluate(() => { delete window.Tesseract; AI = null; });
    }
  });
  await run('28-Syllabus-remove-per-book', async () => {
    const reader = page.locator('.sybk').filter({ hasText: 'CI Reader' });
    const ai = page.locator('.sybk').filter({ hasText: 'CI AI Book' });
    const prompts = [];
    try {
      await reader.getByRole('button', { name: 'Select', exact: true }).click();
      await reader.getByRole('checkbox', { name: 'CI Water Cycle' }).check();
      answerDialog = d => { prompts.push(d.message()); return d.dismiss(); };
      await reader.getByRole('button', { name: 'Remove selected' }).click();
      assert.match(prompts[0], /saved chapter text/);
      assert.equal(await reader.getByRole('checkbox', { name: 'CI Water Cycle' }).count(), 1);
      answerDialog = d => { prompts.push(d.message()); return d.accept(); };
      await reader.getByRole('button', { name: 'Remove selected' }).click();
      assert.equal(await reader.getByText('CI Water Cycle').count(), 0);
      assert.match(await reader.locator('.syr').filter({ hasText: 'CI Rivers of India' }).innerText(), /Page 10–18/);
      assert.equal(await reader.locator('.syr').filter({ hasText: 'CI Rivers of India' }).locator('.exb.on').filter({ hasText: 'UT-3' }).count(), 1);
      assert.ok(await page.evaluate(() => !!chapRec(DRAFT.sylHint, 'CI Water Cycle')), 'saved text stays available');
      await ai.getByRole('button', { name: 'Select', exact: true }).click();
      await ai.getByRole('button', { name: 'Remove all chapters of this book' }).click();
      assert.match(prompts[2], /Remove all chapters of this book/);
      assert.equal(await ai.count(), 0);
      assert.equal(await reader.locator('.syr').count(), 2);
    } finally {
      answerDialog = d => d.accept();
    }
  });
  await run('25-My-work-check', async () => {
    await page.evaluate(() => { MYX = null; go('home'); });
    await page.locator('.mywork').waitFor();
    assert.match(await page.locator('.mywork').innerText(), /My work check/);
    assert.ok(await page.locator('.mywork .mw').count() >= 1);
    await page.locator('.quick-grid button').filter({ hasText: 'Students' }).click();
    await page.locator('.stu-r').first().waitFor();
    await page.locator('.stu-r').first().click();
    await page.locator('.prof').waitFor();
    assert.match(await page.locator('.prof').innerText(), /Admission no\./);
  });
  await run('26-Subject-teacher-sees-students', async () => {
    await page.evaluate(() => { localStorage.clear(); sessionStorage.clear(); });
    await page.goto('http://127.0.0.1:8099/school/teacher.php', { waitUntil: 'domcontentloaded' });
    await page.locator('#lgT').waitFor();
    await page.evaluate(() => localStorage.setItem('ta_intro_day', todayISO()));
    await page.locator('#lgT').selectOption('MA');
    await page.locator('#lgPin').fill('8888');
    await page.locator('.lg button').filter({ hasText: 'Open' }).click();
    await page.locator('.tabs').waitFor({ timeout: 15000 });
    await page.evaluate(() => { const w = document.getElementById('ta-welcome'); if (w) closeTeacherWelcome(); go('att', { c: '9th' }); });
    await page.locator('.stu-r').first().waitFor();
    assert.match(await page.locator('.ban.inf').first().innerText(), /You teach this class/);
    assert.equal(await page.locator('.seg button').filter({ hasText: 'Mark' }).count(), 0, 'a subject teacher cannot mark attendance');
    await page.locator('.stu-r').first().click();
    assert.ok(await page.locator('.yn button[disabled]').count() > 0, 'record is read-only for a subject teacher');
  });
  await run('27-Board-and-school-holiday', async () => {
    await page.evaluate(() => { localStorage.clear(); sessionStorage.clear(); });
    await page.goto('http://127.0.0.1:8099/school/teacher.php', { waitUntil: 'domcontentloaded' });
    await page.locator('.lg').waitFor();
    await page.evaluate(() => localStorage.setItem('ta_intro_day', todayISO()));
    await page.locator('.lg .seg button').filter({ hasText: 'Principal' }).click();
    await page.locator('#lgPin').fill('2468');
    await page.locator('.lg button').filter({ hasText: 'Open' }).click();
    await page.locator('.tabs').waitFor({ timeout: 15000 });
    await page.evaluate(() => { if (document.getElementById('ta-welcome')) closeTeacherWelcome(); setMode('principal'); });
    assert.equal(await page.locator('.tabs.t5 .tab').count(), 5, 'Principal mode: Home, Cross-check, Students, Staff, Connect');
    await page.locator('.pd-hero').waitFor();
    await page.locator('.tabs .tab').filter({ hasText: 'Students' }).click();
    await page.locator('.bd-head').waitFor();
    assert.match(await page.locator('.bd-head').innerText(), /Total students\s*\d+/);
    await page.locator('.seg button').filter({ hasText: 'All students' }).click();
    await page.locator('.bd-cnt button').first().waitFor();
    await page.locator('.seg button').filter({ hasText: 'Teachers' }).click();
    await page.locator('.card').filter({ hasText: 'Teacher work' }).waitFor();
    await page.evaluate(() => go('att', { c: '9th', m: 'cal', ym: '2026-06' }));
    await page.getByRole('button', { name: /Holiday — all classes/ }).click();
    await page.locator('#hrF').fill('2026-06-02'); await page.locator('#hrT').fill('2026-06-30');
    await page.locator('.sheet .chip').filter({ hasText: 'Summer vacation' }).click();
    await page.locator('.sheet .btn.go').click();
    await page.locator('.hol-list .sch').filter({ hasText: 'Summer vacation' }).waitFor();
    assert.match(await page.locator('.calendar-day.holiday.sch').first().innerText(), /Vac/);
    assert.ok(await page.evaluate(() => S.cal.some(e => e.title === 'Summer vacation' && e.date === '2026-06-02' && e.to === '2026-06-30')), 'one school-wide holiday record');
    await page.evaluate(() => go('board', { m: 'gen', c: '9th' }));
    await page.locator('.gen-r').first().waitFor();
    await page.locator('.gen-r').nth(0).locator('button.g').click();
    await page.locator('.gen-r').nth(1).locator('button.b').click();
    await page.getByRole('button', { name: /Save Class 9th/ }).click();
    await page.getByText(/child\(ren\) saved/).first().waitFor();
    await page.evaluate(() => go('board', { m: 'prof' }));
    await page.locator('.bd-cnt button').first().waitFor();
    assert.match(await page.locator('.bd-head').innerText(), /boys · \d+ girls/);
  });
  await run('28-Back-button-help-principal-home', async () => {
    await page.evaluate(() => { SHEET = null; go('pdash'); });
    await page.locator('.pd-hero').waitFor();
    await page.evaluate(() => go('board', { m: 'gen', c: '9th' }));
    await page.locator('.backbar').waitFor();
    await page.goBack();
    await page.waitForFunction(() => TAB === 'pdash');
    await page.locator('.helpb').click();
    await page.locator('.sheet .help-i').first().waitFor();
    await page.goBack();
    await page.waitForFunction(() => !SHEET && !document.querySelector('.sheet'));
    assert.equal(await page.evaluate(() => TAB), 'pdash', 'back closes the help sheet and stays on the screen');
    await page.evaluate(() => { setRec('leave', 'ci-lv', { id: 'ci-lv', tid: 'K', from: todayISO(), to: todayISO(), reason: 'CI family function', status: 'Pending' }); render(); });
    await page.locator('.pd-ap').filter({ hasText: 'leave' }).locator('button').filter({ hasText: 'Approve' }).click();
    await page.waitForFunction(() => S.leaves.find(l => l.id === 'ci-lv').status === 'Approved');
    await page.evaluate(() => go('prin'));
    await page.evaluate(() => { const ev = (t, x) => { const e = new Event(t, { bubbles: true }); e.touches = t === 'touchstart' ? [{ clientX: x, clientY: 300 }] : []; e.changedTouches = [{ clientX: x, clientY: 300 }]; document.querySelector('.body').dispatchEvent(e); }; ev('touchstart', 300); ev('touchend', 120); });
    await page.waitForFunction(() => TAB === 'board');
  });
  await run('29-Cross-check-every-feature-arrangement-timetable', async () => {
    for (const v of ['att', 'diary', 'tch', 'hw', 'kit', 'lp', 'syl']) {
      await page.evaluate(v => { SHEET = null; go('prin', { v }); }, v);
      await page.locator('.xv button.on').waitFor();
      assert.ok((await page.locator('.scroll').innerText()).length > 40, 'cross-check ' + v + ' draws');
    }
    await page.evaluate(() => { SHEET = null; go('prin'); });
    assert.equal(await page.locator('.xv button.on').innerText(), 'Attendance', 'Cross-check opens on Attendance');
    await page.evaluate(() => go('prin', { v: 'tch' }));
    await page.locator('.xc-big div').first().click();
    await page.locator('.sheet h2').waitFor();
    await page.evaluate(() => { SHEET = null; go('prin', { v: 'att' }); });
    assert.match(await page.locator('.kp').innerText(), /Students present[\s\S]*Absent[\s\S]*Total students/);
    await page.evaluate(() => go('prin', { v: 'diary' }));
    await page.locator('.xc-big').first().waitFor().catch(() => {});
    await page.evaluate(() => go('staff'));
    await page.locator('.xst div').first().waitFor();
    assert.equal(await page.evaluate(() => TAB), 'staffhub', 'Arrangement lives in the Staff tab');
    const name = await page.locator('.xst div').nth(1).locator('b').innerText();
    await page.locator('.xst div').nth(1).click();
    await page.waitForFunction(n => staffAbsent(todayISO()).some(t => T(t).name === n), name);
    await page.evaluate(() => go('tt', { c: '9th' }));
    await page.locator('table.tt').waitFor();
    await page.locator('table.tt td').first().click();
    await page.locator('.sheet .tt-o button').first().waitFor();
    await page.locator('.sheet .tt-o button').filter({ hasText: 'Free / no class' }).click();
    await page.waitForFunction(() => S.grid && S.grid['9th'] && S.grid['9th'][0][0] === -1);
    await page.evaluate(() => go('pdash'));
    await page.locator('.quick-grid button').filter({ hasText: 'Arrangement' }).waitFor();
  });
  await run('30-Menu-search-staff-tab-class-levels', async () => {
    await page.evaluate(() => { SHEET = null; go('pdash'); });
    await page.locator('.menub').click();
    await page.locator('.mn-l button').filter({ hasText: 'Timetable' }).click();
    await page.waitForFunction(() => TAB === 'staffhub' && P.m === 'tt');
    await page.evaluate(() => go('pdash'));
    await page.locator('#srQ').fill('Ama');
    await page.locator('#srR button').first().click();
    await page.waitForFunction(() => TAB === 'att' && !!P.sid);
    await page.evaluate(() => go('att', { c: '9th', m: 'bulk' }));
    await page.locator('.bk-r').first().waitFor();
    await page.getByRole('button', { name: 'All: Good' }).click();
    await page.locator('.bk-r').nth(0).locator('button.l4').click();
    await page.getByRole('button', { name: /Save · \d+ child/ }).click();
    await page.getByText(/child\(ren\) saved/).first().waitFor();
    await page.evaluate(() => go('att', { c: '9th', m: 'bulk', bm: 'rec', bf: 'id' }));
    await page.locator('.bk-r').first().waitFor();
    await page.locator('.bk-r').nth(0).locator('button.x').click();
    await page.getByRole('button', { name: /Save · 1 child/ }).click();
    await page.getByText(/child\(ren\) saved/).first().waitFor();
  });
  await browser.close();
  if (failures.length) {
    console.error('\nTEACHER UI FAILURES:\n' + failures.join('\n\n'));
    process.exit(1);
  }
  console.log('Teacher UI 390x844 screenshots and checks passed');
})().catch(e => { console.error(e); process.exit(1); });
