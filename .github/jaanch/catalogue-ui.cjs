// CI-only browser checks; no orders or uploads, and no npm on production hosting.
const { chromium } = require('/tmp/maakit-ui/node_modules/playwright');
const assert = require('node:assert/strict');
(async () => {
  const browser = await chromium.launch({headless:true});
  try {
    const page = await browser.newPage({viewport:{width:390,height:844}, reducedMotion:'reduce'});
    await page.route('https://fonts.googleapis.com/**', route => route.abort());
    await page.route('https://fonts.gstatic.com/**', route => route.abort());
    const errors=[]; page.on('pageerror', error => errors.push(error.message));
    for (const width of [360,390,1080]) {
      await page.setViewportSize({width,height:844});
      await page.goto('http://127.0.0.1:8099/?lang=hi');
      await page.locator('.discovery-product').first().waitFor();
      assert(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), 'Home must fit '+width+'px');
      assert.equal(await page.locator('[data-rail-toggle]').getAttribute('aria-pressed'),'false','Reduced motion disables automatic movement');
      const rail=page.locator('#shop-category-rail');
      await rail.evaluate(el => {el.scrollLeft=200;});
      if(width<800) assert(await rail.evaluate(el=>el.scrollLeft)>0,'Category rail can be swiped');
      await page.screenshot({path:'/tmp/catalogue-home-'+width+'.png',fullPage:width===390});
    }
    await page.setViewportSize({width:390,height:844});
    await page.goto('http://127.0.0.1:8099/register-business.php?lang=hi');
    const picker=page.locator('[data-category-picker]');
    for (const word of ['paint','पेंट','asian']) {
      await picker.locator('input').fill(word);
      assert(await picker.locator('select option[value="Paint Store"]').count()===1,'Category search: '+word);
    }
    await picker.locator('select').selectOption('Paint Store');
    await picker.locator('.category-preview').waitFor({state:'visible'});
    assert((await picker.locator('.category-preview').textContent()).includes('एशियन'),'Paint category previews brand products');
    await picker.locator('input').fill('zznonexistent');
    assert.equal(await picker.locator('select').inputValue(),'','A filtered-out category cannot remain silently selected');
    assert((await picker.locator('.category-preview').textContent()).includes('नहीं मिली'),'No-match guidance');
    await picker.locator('input').fill('');
    await picker.locator('select').selectOption('Paint Store');
    assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Registration fits phone');
    await page.screenshot({path:'/tmp/catalogue-registration.png',fullPage:true});
    await page.goto('http://127.0.0.1:8099/search.php?q=Asian&lang=en');
    await page.getByText('Asian Paints Interior Emulsion',{exact:true}).click();
    assert(page.url().includes('bazaar.php'),'Search card opens product listing');
    assert((await page.locator('body').textContent()).includes('Shop price not added yet'),'Unpriced discovery keeps confirmation wording');
    assert.deepEqual(errors,[],'No browser script errors');
    console.log('Catalogue mobile layout, category search/preview and product navigation passed');
  } finally {await browser.close();}
})().catch(error=>{console.error(error);process.exit(1);});
