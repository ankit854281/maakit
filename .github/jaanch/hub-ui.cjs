// CI-only real PHP browser checks. No runtime npm requirement.
const {chromium}=require('/tmp/maakit-ui/node_modules/playwright');
const assert=require('node:assert/strict');
(async()=>{const browser=await chromium.launch({headless:true});try{
 const page=await browser.newPage({viewport:{width:390,height:844},reducedMotion:'reduce'});const errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.route('https://fonts.googleapis.com/**',r=>r.abort());await page.route('https://fonts.gstatic.com/**',r=>r.abort());
 for(const width of [360,390,1080]){await page.setViewportSize({width,height:844});
  for(const segment of ['LOCAL_SHOPPING','HOME_SERVICES','B2B']){await page.goto('http://127.0.0.1:8099/public/index.php?segment='+segment+'&lang=hi');await page.locator('.maakit-segments').waitFor();
   assert.equal(await page.locator('[data-segment]').count(),3);assert.equal(await page.locator('[aria-current="page"][data-segment]').getAttribute('data-segment'),segment);
   assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'No overflow '+segment+' '+width);
   if(segment==='LOCAL_SHOPPING'){const rail=page.locator('.hub-category-slider');await rail.evaluate(el=>{el.scrollLeft=250;});assert(await rail.evaluate(el=>el.scrollLeft)>0,'horizontal categories');}
   assert.equal(await page.locator('main a[href="/mere.php"]').count(),0,'account link uses actual account.php');
   await page.screenshot({path:'/tmp/catalogue-hub-'+segment+'-'+width+'.png',fullPage:width===390});
  }
 }
 await page.setViewportSize({width:390,height:844});await page.goto('http://127.0.0.1:8099/public/index.php?segment=HOME_SERVICES&lang=en');
 await page.getByRole('searchbox').fill('Painting');await page.locator('.hub-search button').click();assert.equal(await page.locator('.hub-product').count(),1,'search stays in services');
 await page.locator('.hub-product').click();assert((await page.locator('#f_event').inputValue()).startsWith('Painting'),'one-click card retains selected work');
 assert.deepEqual(errors,[]);console.log('Three-segment mobile layout, category sliding and context search passed');
 }finally{await browser.close();}})().catch(e=>{console.error(e);process.exit(1);});
