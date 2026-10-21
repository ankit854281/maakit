// CI-only browser regression tests. Geocoding fixtures never contact providers.
const {chromium}=require('/tmp/maakit-ui/node_modules/playwright');
const assert=require('node:assert/strict');
(async()=>{const browser=await chromium.launch({headless:true});try{
 const page=await browser.newPage({viewport:{width:390,height:844}});const errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.route('https://fonts.googleapis.com/**',r=>r.abort());await page.route('https://fonts.gstatic.com/**',r=>r.abort());
 await page.route('https://cdnjs.cloudflare.com/**',r=>r.abort());
 await page.route('https://nominatim.openstreetmap.org/search?**',r=>r.fulfill({contentType:'application/json',body:JSON.stringify([
  {name:'CI Bhadohi Station',display_name:'CI Bhadohi Station, Bhadohi',lat:25.395,lon:82.568,address:{city:'Bhadohi'}},
  {name:'CI Gyanpur Bus Stand',display_name:'CI Gyanpur Bus Stand, Gyanpur',lat:25.35,lon:82.47,address:{town:'Gyanpur'}},
  {name:'Wrong town bus stand',display_name:'Mirzapur',lat:25.15,lon:82.57,address:{city:'Mirzapur'}}
 ])}));
 await page.goto('http://127.0.0.1:8099/ride.php?lang=en');
 const pick=page.locator('[data-pa]'),drop=page.locator('[data-da]');
 await pick.fill('Bhadohi station');await pick.press('Enter');
 await page.locator('[data-results] button').getByText('CI Bhadohi Station',{exact:true}).click();
 assert.equal(await page.locator('[data-geo=a]').inputValue(),'25.395000,82.568000');
 assert.equal(await page.locator('[name=name]').inputValue(),'','pickup search did not submit booking');
 await page.waitForTimeout(1150);
 await drop.fill('Gyanpur bus stand');await drop.press('Enter');
 await page.locator('[data-results] button').getByText('CI Gyanpur Bus Stand',{exact:true}).waitFor();
 assert.equal(await page.locator('[data-results] button').count(),1,'exclude different towns');
 await page.locator('[data-results] button').click();
 assert.equal(await page.locator('[data-geo=b]').inputValue(),'25.350000,82.470000');
 assert.match(await page.locator('[data-bar-f]').innerText(),/₹/,'two selected places show fare');
 await pick.fill('Changed pickup');assert.equal(await page.locator('[data-geo=a]').inputValue(),'','edited address clears stale pin');
 assert.doesNotMatch(await page.locator('[data-bar-f]').innerText(),/₹/,'edited pickup clears stale fare');
 assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'ride fits phone');
 await page.goto('http://127.0.0.1:8099/order.php?lang=en');
 assert.equal(await page.getByLabel('Payment method',{exact:true}).count(),1,'labelled payment select');
 assert.equal(await page.locator('#payment option').first().getAttribute('value'),'डिलीवरी पर कैश','stored payment values unchanged');
 assert.match(await page.locator('#payment option').first().innerText(),/Cash/,'English payment label');
 assert.match(await page.locator('#weight option').first().innerText(),/kg/,'English weight');
 assert.deepEqual(errors,[]);console.log('Launch browser: pickup/drop search, locality filtering, stale-pin invalidation, fare and English checkout passed');
}finally{await browser.close();}})().catch(e=>{console.error(e);process.exit(1);});
