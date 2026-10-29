const {chromium}=require('playwright');
const fs=require('node:fs');
(async()=>{
 const browser=await chromium.launch({headless:true});
 fs.mkdirSync('/tmp/sarathi-mobile-shots',{recursive:true});
 let count=0;
 try {
  for(const width of [360,390]) for(const lang of ['hi','en']) {
   const page=await browser.newPage({viewport:{width,height:844},isMobile:true,deviceScaleFactor:1});
   await page.route('**/*',route=>new URL(route.request().url()).hostname==='127.0.0.1'?route.continue():route.abort());
   for(const [file,fixture] of [
    ['account.php','rider'],['apply.php','admin'],['apply.php','new'],['apply.php','rider'],
    ['kaam.php','rider'],['jankari.php','rider'],['khata.php','rider'],['beete.php','rider'],['madad.php','rider'],
    ['admin/index.php','admin'],['admin/profiles.php','admin'],['index.php','guest'],['ghar.php','guest'],['ghar.php','rider'],['role.php','new'],['sarathi.php','guest'],['admin/places.php','admin']]) {
     const url=`http://127.0.0.1:8098/smartsarathi/${file}?fixture=${fixture}&lang=${lang}`;
     const response=await page.goto(url);await page.waitForTimeout(300);if(response.status()!==200)throw Error(`${url}: HTTP ${response.status()}`);
     if(/Fatal error|Warning:|Uncaught/.test(await page.locator('body').innerText()))throw Error(`${url}: PHP error`);
     try{await page.locator('details').evaluateAll(xs=>xs.forEach(x=>x.open=true));}catch(e){throw Error(url+': '+e.message);}
     const problems=await page.evaluate(()=>{
      const p=[];
      if(document.querySelector('meta[name=viewport]')?.content!=='width=device-width,initial-scale=1,viewport-fit=cover')p.push('viewport');
      if(document.documentElement.scrollWidth>innerWidth+1)p.push(`overflow ${document.documentElement.scrollWidth}/${innerWidth}`);
      for(const b of document.querySelectorAll('button,.sx-pill,.sr-language a,.sc-nav a')){
       const r=b.getBoundingClientRect(),s=getComputedStyle(b);
       if(!r.width||!r.height||s.visibility==='hidden'||Number(s.opacity)===0)continue;
       if(r.height<43.9)p.push(`small tap: ${b.outerHTML.slice(0,120)} ${r.height}`);
      }
      return p;
     });
     if(problems.length)throw Error(`${file}/${fixture}/${lang}/${width}: ${problems.join('; ')}`);
     await page.screenshot({path:`/tmp/sarathi-mobile-shots/${file.replaceAll('/','-')}-${fixture}-${lang}-${width}.png`,fullPage:true});count++;
   }
   await page.close();
  }
  console.log(`${count} rendered page/state/width/language checks passed (360px and 390px).`);
 } finally {await browser.close();}
})().catch(e=>{console.error(e);console.log('::error::'+String(e.message||e).replace(/\n/g,' ').slice(0,900));process.exit(1);});
