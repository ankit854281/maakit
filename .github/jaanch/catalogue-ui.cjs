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
      await rail.evaluate(el=>{el.scrollLeft=0;});
      await page.screenshot({path:'/tmp/catalogue-home-'+width+'.png',fullPage:width===390});
    }
    await page.setViewportSize({width:390,height:844});
    await page.goto('http://127.0.0.1:8099/register-business.php?lang=hi');
    const picker=page.locator('[data-category-picker]');
    for (const word of ['paint','पेंट','rang']) {
      await picker.locator('input').fill(word);
      assert(await picker.locator('select option[value="Paint Store"]').count()===1,'Category search: '+word);
    }
    await picker.locator('.category-matches button').filter({hasText:'Paint Store'}).click();
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
    const fresh=Date.now()>=Date.parse('2026-10-08T00:00:00Z') && Date.now()<Date.parse('2026-11-08T00:00:00Z');
    await page.goto('http://127.0.0.1:8099/search.php?q=Tata%20Salt&lang=en');
    if (fresh) assert((await page.locator('.discovery-product').first().textContent()).includes('₹28 / 1 kg'),'Search shows sourced pack reference');
    await page.locator('.discovery-product').first().click();
    if (fresh) {
      const reference=page.locator('.market-reference');
      assert((await reference.textContent()).includes('₹28') && (await reference.textContent()).includes('2026-10-08'),'Price and checked date visible');
      assert((await reference.textContent()).includes('not a confirmed local shop price'),'Reference clearly differs from shop price');
      assert.equal(await reference.locator('a').getAttribute('href'),'https://www.bigbasket.com/pd/241600/tata-salt-iodized-1-kg-pouch/','Source available');
    } else assert.equal(await page.locator('.market-reference').count(),0,'Stale references stay hidden');
    assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Reference prices fit phone');
    await page.screenshot({path:'/tmp/catalogue-reference-prices.png',fullPage:true});
    const request=page.locator('.catalogue-request').first();
    await request.locator('[name="qty"]').fill('2');
    await request.locator('[name="pack"]').fill('1 kg');
    await request.locator('[name="urgent"]').check();
    await request.getByRole('button',{name:'Add to request cart'}).click();
    await page.locator('.catalogue-request-cart').waitFor();
    await page.goto('http://127.0.0.1:8099/bazaar.php?type=Hardware%20Shop&q=Hammer&lang=en');
    await page.locator('.catalogue-request').first().getByRole('button',{name:'Add to request cart'}).click();
    assert((await page.locator('.catalogue-request-cart').textContent()).includes('Tata Salt'),'Cart survives category changes');
    await page.getByRole('link',{name:'Send this request',exact:true}).click();
    await page.locator('#paneOut').waitFor({state:'visible'});
    const note=await page.locator('#noteHid').inputValue();
    assert(note.includes('Tata Salt') && note.includes('2 × 1 kg') && note.includes('Urgent') && note.includes('Hammer'),'Selected product, pack, quantity and urgency reach checkout');
    assert((await page.locator('#noteShow').textContent()).includes('before purchase'),'Request requires price confirmation');
    assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Request checkout fits mobile');
    await page.screenshot({path:'/tmp/catalogue-request-checkout.png',fullPage:true});
    await page.goto('http://127.0.0.1:8099/order.php?catalog_id=99999999&qty=2&lang=en#pata');
    assert.equal(await page.locator('#noteHid').inputValue(),'','Unknown catalogue IDs cannot prefill fake requests');
    const quoteHtml=require('node:fs').readFileSync('/tmp/catalogue-quote-preview.html','utf8');
    await page.setContent(quoteHtml.replace('<head>','<head><base href="http://127.0.0.1:8099/">'));
    await page.locator('.order-quote').waitFor();
    assert(await page.getByRole('button',{name:'Accept these prices'}).isVisible(),'Customer approval controls visible');
    assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Quote fits mobile');
    await page.screenshot({path:'/tmp/catalogue-quote-mobile.png',fullPage:true});
    assert.deepEqual(errors,[],'No browser script errors');
    console.log('Catalogue mobile layout, category search/preview and product navigation passed');
  } finally {await browser.close();}
})().catch(error=>{console.error(error);process.exit(1);});
