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
    for (const width of [360,390,1080]) {
      await page.setViewportSize({width,height:844});
      await page.goto('http://127.0.0.1:8099/search.php?q=paint&lang=hi');
      const results=page.locator('.search-rail').first();
      await results.locator('a').first().waitFor();
      assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Search fits '+width+'px');
      const sizes=await results.locator('a').evaluateAll(cards=>cards.map(c=>({w:c.getBoundingClientRect().width,h:c.getBoundingClientRect().height})));
      assert(sizes.every(s=>Math.abs(s.w-sizes[0].w)<1 && Math.abs(s.h-sizes[0].h)<1),'Search cards align at '+width+'px');
      await page.getByRole('button',{name:'अगले कार्ड',exact:true}).first().click();
      await page.waitForFunction(()=>document.querySelector('.search-rail').scrollLeft>0);
      await page.getByRole('button',{name:'पिछले कार्ड',exact:true}).first().click();
      await page.waitForFunction(()=>document.querySelector('.search-rail').scrollLeft<3);
      await page.screenshot({path:'/tmp/catalogue-search-slider-'+width+'.png',fullPage:width===390});
    }
    await page.setViewportSize({width:390,height:844});
    await page.goto('http://127.0.0.1:8099/search.php?q=paint&lang=en');
    assert(await page.locator('#search-category-hints option[value="Paint Store"]').count()===1,'Search offers shop category hints');
    await page.getByRole('navigation',{name:'Matching shop categories'}).getByRole('link',{name:'Paint Store',exact:true}).click();
    assert(page.url().includes('type=Paint'),'Category hint opens complete shop catalogue');
    await page.goto('http://127.0.0.1:8099/search.php?q=Asian&lang=en');
    await page.getByText('Asian Paints Interior Emulsion',{exact:true}).click();
    assert(page.url().includes('bazaar.php'),'Search card opens product listing');
    assert((await page.locator('body').textContent()).includes('Shop price not added yet'),'Unpriced discovery keeps confirmation wording');
    const fresh=Date.now()>=Date.parse('2026-10-08T00:00:00Z') && Date.now()<Date.parse('2026-11-08T00:00:00Z');
    await page.goto('http://127.0.0.1:8099/search.php?q=Tata%20Salt&lang=en');
    assert((await page.locator('.discovery-product').first().textContent()).includes('Tata Salt'),'Exact product name ranks above category matches');
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
    assert.equal(await page.locator('.bazaar-cards>.bazaar-card').count(),1,'Product link opens only the selected product');
    if(fresh){
      const direct=await browser.newPage({viewport:{width:390,height:844},reducedMotion:'reduce'});
      await direct.route('https://fonts.googleapis.com/**', route=>route.abort());
      await direct.route('https://fonts.gstatic.com/**', route=>route.abort());
      await direct.goto(page.url());
      await direct.locator('[name="pack"]').selectOption({index:1});
      const selected=await direct.locator('[name="pack"]').inputValue();
      assert(selected.includes('Tata Salt') && selected.includes('2 kg'),'Selected pack includes the exact reference product');
      await direct.getByRole('button',{name:'Continue to address →',exact:true}).click();
      await direct.locator('#paneOut').waitFor({state:'visible'});
      assert((await direct.locator('#noteHid').inputValue()).includes(selected),'Selected reference pack reaches checkout');
      await direct.screenshot({path:'/tmp/catalogue-direct-checkout.png',fullPage:true});
      await direct.close();
    }
    await page.goto('http://127.0.0.1:8099/bazaar.php?type=Grocery%20%2F%20Kirana%20Store&q=Tata%20Salt&lang=en');
    const request=page.locator('.catalogue-request').first();
    await request.locator('[name="qty"]').fill('2');
    await request.locator('[name="pack"]').fill('1 kg');
    await request.locator('[name="urgent"]').check();
    await request.getByRole('button',{name:'Add to request cart'}).click();
    await page.locator('.catalogue-request-cart').waitFor();
    if(!await page.locator('.request-cart-row').first().isVisible()) await page.locator('.catalogue-request-cart summary').click();
    assert(await page.locator('.request-cart-row').first().isVisible(),'Cart summary opens selected items');
    const edit=page.locator('.request-cart-edit').first();
    const formData=await edit.evaluate(form=>Object.fromEntries(new FormData(form)));
    await page.request.post(page.url(),{maxRedirects:0,form:{...formData,qty:'0',request_action:'update'}});
    await page.reload();
    assert.equal(await page.locator('.request-cart-edit [name="qty"]').first().inputValue(),'2','Server rejects zero quantity');

    await edit.locator('[name="qty"]').fill('3');
    await edit.getByRole('button',{name:'Save quantity',exact:true}).click();
    assert(await page.locator('.request-cart-edit').first().isVisible(),'Cart stays expanded after editing');
    assert.equal(await page.locator('.request-cart-edit [name="qty"]').first().inputValue(),'3','Quantity is saved');
    assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Cart editing fits mobile');
    await page.screenshot({path:'/tmp/catalogue-cart-polished.png',fullPage:true});
    await page.goto('http://127.0.0.1:8099/bazaar.php?type=Hardware%20Shop&q=Hammer&lang=en');
    await page.locator('.catalogue-request').first().getByRole('button',{name:'Add to request cart'}).click();
    assert((await page.locator('.catalogue-request-cart').textContent()).includes('Tata Salt'),'Cart survives category changes');
    await page.getByRole('link',{name:'Send this request',exact:true}).click();
    await page.locator('#paneOut').waitFor({state:'visible'});
    const note=await page.locator('#noteHid').inputValue();
    assert(note.includes('Tata Salt') && note.includes('3 × 1 kg') && note.includes('Urgent') && note.includes('Hammer'),'Selected product, pack, quantity and urgency reach checkout');
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
    const contrast=await page.getByRole('button',{name:'Request changes'}).evaluate(el=>{
      const css=getComputedStyle(el),lum=color=>{
        const rgb=color.match(/[0-9.]+/g).slice(0,3).map(Number).map(v=>{v/=255;return v<=.04045?v/12.92:Math.pow((v+.055)/1.055,2.4);});
        return .2126*rgb[0]+.7152*rgb[1]+.0722*rgb[2];
      };
      const a=lum(css.color),b=lum(css.backgroundColor);return (Math.max(a,b)+.05)/(Math.min(a,b)+.05);
    });
    assert(contrast>=4.5,'Change-request text must be readable on its button background');
    await page.screenshot({path:'/tmp/catalogue-quote-mobile.png',fullPage:true});
    assert.deepEqual(errors,[],'No browser script errors');
    console.log('Catalogue mobile layout, category search/preview and product navigation passed');
  } finally {await browser.close();}
})().catch(error=>{console.error(error);process.exit(1);});
