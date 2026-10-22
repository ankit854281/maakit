// Issue #54: both login doors must work in either language, on a phone too.
// Read-only: no credentials, messages or login attempts are submitted.
const { chromium } = require('/tmp/maakit-ui/node_modules/playwright');
const assert = require('node:assert/strict');
const { pathToFileURL } = require('node:url');
const path = require('node:path');

(async () => {
  const { PAGES } = await import(pathToFileURL(path.join(__dirname, '../chowkidar/pages.mjs')));
  const checks = PAGES.filter(p => p.path === '/login.php' || p.path === '/login.php?as=team');
  assert.equal(checks.length, 2, 'Chowkidar watches both login routes');
  const browser = await chromium.launch({ headless: true });
  try {
    for (const width of [360, 390, 1080]) {
      const context = await browser.newContext({ viewport: { width, height: 844 } });
      const page = await context.newPage();
      const errors = [];
      page.on('pageerror', e => errors.push(e.message));
      await page.route('https://fonts.googleapis.com/**', r => r.abort());
      await page.route('https://fonts.gstatic.com/**', r => r.abort());
      for (const lang of ['en', 'hi']) {
        await page.goto('http://127.0.0.1:8099/login.php?lang=' + lang);
        for (const role of ['dukan', 'team', 'dukan']) {
          const href = role === 'team' ? '/login.php?as=team' : '/login.php';
          await page.locator('.lg-tabs a[href="' + href + '"]').click();
          assert.equal(new URL(page.url()).pathname, '/login.php');
          assert.equal(new URL(page.url()).searchParams.get('as'), role === 'team' ? 'team' : null);
          assert.equal(await page.locator('html').getAttribute('lang'), lang === 'hi' ? 'hi' : 'en-IN', 'Role switching keeps language');
          for (const tab of await page.locator('.lg-tabs a').all()) {
            assert(await tab.isVisible(), 'Both role choices are visible');
            assert((await tab.innerText()).trim(), 'Role choices have visible names');
          }
          assert.equal(await page.locator('.lg-tabs [aria-selected="true"]').getAttribute('href'), href);
          const form = page.locator('form[data-remember="' + role + '"]');
          assert(await form.isVisible());
          assert.equal(await form.getAttribute('method'), 'post');
          assert.equal(await form.locator('[name="do"]').inputValue(), role);
          assert(await form.locator('[name="csrf"]').inputValue(), 'CSRF token exists');
          for (const field of role === 'dukan' ? ['mobile', 'code'] : ['username', 'password']) {
            const input = form.locator('[name="' + field + '"]');
            assert(await input.isVisible(), field + ' is visible');
            assert(await input.isEditable(), field + ' is editable');
            assert(await input.evaluate(el => el.required && el.labels.length > 0 && el.labels[0].innerText.trim().length > 0), field + ' has a label and is required');
          }
          assert(await form.locator('button[type="submit"]').isVisible());
          if (role === 'dukan') {
            await form.locator('[name="code"]').fill('abc-1234');
            assert.equal(await form.locator('[name="code"]').inputValue(), 'ABC-1234');
          } else {
            assert.equal(await form.locator('[name="password"]').getAttribute('type'), 'password');
          }
          // Exercise the actual live-monitor contract, not a duplicate expectation.
          const check = checks.find(p => p.path === href);
          const html = await page.content();
          for (const token of check.chahiye) assert(html.includes(token), 'Monitor contract: ' + token);
          for (const token of check.nahi) assert(!html.includes(token), 'No private panel before login');
          assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), 'Login fits ' + width + 'px');
        }
      }
      assert.deepEqual(errors, [], 'No login JavaScript errors');
      await context.close();
    }
    console.log('Login: bilingual role navigation, visible labelled fields, code input and Chowkidar contract passed');
  } finally {
    await browser.close();
  }
})().catch(e => { console.error(e); process.exit(1); });
