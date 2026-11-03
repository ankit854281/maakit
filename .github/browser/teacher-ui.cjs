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
  page.on('console', m => { if (m.type() === 'error') errors.push('Console: ' + m.text()); });
  page.on('dialog', async d => { await d.accept(); });
  const shot = async (n) => page.screenshot({ path: path.join(dir, n + '.png'), fullPage: true });
  const pause = async (ms = 400) => page.waitForTimeout(ms);
  const run = async (name, fn) => {
    try { await fn(); console.log('PASS Teacher UI:', name); }
    catch (e) { failures.push(name + ': ' + e.stack); console.error('FAIL Teacher UI:', name, e.message); }
    try { await shot(name.replace(/[^a-z0-9-]/ig, '-')); } catch (e) { failures.push(name + ' screenshot: ' + e.message); }
  };
  await page.goto('http://127.0.0.1:8099/school/teacher.php', { waitUntil: 'domcontentloaded' });
  await page.locator('#lgT').waitFor();
  await page.locator('#lgT').selectOption('K');
  await page.locator('#lgPin').fill('7777');
  await page.locator('.lg button').filter({ hasText: 'Open' }).click();
  await page.locator('.tabs').waitFor({ timeout: 15000 });
  await run('01-Aaj', async () => {
    assert.match(await page.locator('.tabs button.tab.on').innerText(), /Aaj/);
    assert.ok((await page.locator('.scroll').innerText()).length > 50);
  });
  await run('02-Haziri-Day', async () => {
    await page.locator('.tabs button.tab').filter({ hasText: 'Haziri' }).click();
    await page.locator('.stu').first().waitFor();
    assert.equal(await page.locator('.stu').count(), 12);
    await page.locator('.stu').nth(0).locator('button.A').click();
    await page.locator('.stu').nth(1).locator('button.L').click();
    await page.locator('.scroll').evaluate(el => { el.scrollTop = 450; });
    const before = await page.locator('.scroll').evaluate(el => el.scrollTop);
    await page.locator('.stu').nth(9).locator('button.A').click();
    const after = await page.locator('.scroll').evaluate(el => el.scrollTop);
    assert.ok(before > 0 && Math.abs(after - before) < 40, 'tapping student ten must keep scroll position');
    assert.match(await page.locator('.cnt').innerText(), /2 A/);
    assert.match(await page.locator('.cnt').innerText(), /1 L/);
    await page.getByRole('button', { name: /Save attendance|Update attendance/ }).click();
    await page.locator('a.wa').first().waitFor();
  });
  await run('03-Haziri-Class-Holiday', async () => {
    await page.getByRole('button', { name: 'Mark holiday', exact: true }).first().click();
    await page.locator('#hT').fill('CI class-only holiday');
    await page.locator('.sheet button').filter({ hasText: 'Mark holiday' }).click();
    await page.getByText('CI class-only holiday').first().waitFor();
    await page.getByRole('button', { name: /Remove holiday/ }).click();
    await page.locator('.stu').first().waitFor();
  });
  await run('04-Haziri-Register', async () => {
    await page.evaluate(() => go('att', { c: '9th', m: 'reg', ym: '2026-10' }));
    await page.locator('.reg tbody tr').first().waitFor();
    assert.equal(await page.locator('.reg tbody tr').count(), 12);
    assert.ok((await page.locator('.reg').evaluate(el => el.scrollWidth > el.clientWidth)));
    assert.equal(await page.locator('.reg .nm').first().evaluate(el => getComputedStyle(el).position), 'sticky');
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
    await page.locator('.tabs button.tab').filter({ hasText: 'Diary' }).click();
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
    await page.getByRole('button', { name: 'Fill from syllabus' }).click();
    assert.ok((await page.locator('#lpT').inputValue()).length);
    await page.getByRole('button', { name: /Send to Principal/ }).click();
    await page.getByText(/Submitted|Sent to the Principal/).first().waitFor();
    await page.evaluate(() => go('plan', { m: 'syl' }));
    await page.getByText('The French Revolution').first().waitFor();
  });
  await run('08-Notes', async () => {
    await page.locator('.tabs button.tab').filter({ hasText: 'Notes' }).click();
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
    await page.locator('.stu').first().waitFor();
    await context.setOffline(true);
    await page.locator('.stu').first().locator('button.A').click();
    await page.getByRole('button', { name: /Save attendance|Update attendance/ }).click();
    await pause(600);
    assert.match(await page.locator('#net').innerText(), /waiting.*offline/i);
    await context.setOffline(false);
    await page.evaluate(() => flush());
    await page.waitForFunction(() => document.querySelector('#net') && /Saved/i.test(document.querySelector('#net').textContent), { timeout: 12000 });
  });
  await run('12-Console', async () => { assert.deepEqual(errors, [], 'browser console/page errors'); });
  await browser.close();
  if (failures.length) {
    console.error('\nTEACHER UI FAILURES:\n' + failures.join('\n\n'));
    process.exit(1);
  }
  console.log('Teacher UI 390x844 screenshots and checks passed');
})().catch(e => { console.error(e); process.exit(1); });
