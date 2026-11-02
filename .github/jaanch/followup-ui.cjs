const fs=require('node:fs');const assert=require('node:assert/strict');const {JSDOM,VirtualConsole}=require('jsdom');
const language=fs.readFileSync('school/language.js','utf8'),followup=fs.readFileSync('school/followup.js','utf8');
const tick=ms=>new Promise(r=>setTimeout(r,ms));const requests=[];
const child={sid:'synthetic-1',name:'Synthetic Child',father:'Synthetic Parent',phone:'9811100001',cls:'2nd',section:'A',tag:'Company',due:100000,overallDue:150000,source:'copied',asOn:'02-11-2026'};
const data={ok:true,role:'parent',session:'2026-27',today:'2026-11-02',maxDate:'2026-11-09',children:[child],promises:[],calls:[],receipts:[],canPromise:true,canCall:false};
function boot(mode='parent',savedLang='en'){
 const v=new VirtualConsole();v.on('jsdomError',e=>{if(!e.message.includes('Not implemented: navigation'))throw e;});
 const dom=new JSDOM('<!doctype html><body><button data-school-language>English / Hinglish</button><main></main></body>',{url:'https://maakit.in/school/',runScripts:'outside-only',pretendToBeVisual:true,virtualConsole:v});
 const w=dom.window;w.localStorage.setItem('sch_token','synthetic-token');w.localStorage.setItem('sch_ui_lang',savedLang);
 const mock=structuredClone(data);if(mode==='staff'){mock.role='chairman';mock.canCall=true;mock.canPromise=false;mock.promises=[{id:'p1',sid:child.sid,promise:'2026-11-06',amount:50000,remaining:50000,date:'2026-11-02',at:1,by:'Synthetic Parent',status:'pending',revision:1}];}
 w.fetch=async(url,opts={})=>{
  const action=new URL(url,w.location.href).searchParams.get('a'),body=opts.body?JSON.parse(opts.body):null;requests.push({action,body,token:opts.headers['X-School-Token']});
  if(action==='promise'){mock.promises=[{...body,id:'promise-1',amount:Math.round(Number(body.amount)*100),remaining:Math.round(Number(body.amount)*100),date:mock.today,by:'Synthetic Parent',status:'pending',revision:1}];return {ok:true,json:async()=>({ok:true,promise:mock.promises[0]})};}
  if(action==='attempt'){const c={id:'attempt-1',sid:body.sid,sids:[body.sid],date:mock.today,startedDate:mock.today,startedAt:1,at:1,by:'Chairman',initiated:true,outcome:'Call initiated',tag:'Company',autoRemark:'Dialer requested. Connection not confirmed.',autoRemarkHi:'Call ki koshish. Baat confirm nahi hai.'};mock.calls.unshift(c);return{ok:true,json:async()=>({ok:true,call:c})};}
  if(action==='result'){Object.assign(mock.calls[0],body,{initiated:false,completedDate:mock.today});return{ok:true,json:async()=>({ok:true,call:mock.calls[0]})};}
  return{ok:true,json:async()=>structuredClone(mock)};
 };
 w.confirm=()=>true;w.HTMLElement.prototype.scrollIntoView=function(){};
 w.eval(language);w.eval(followup);w.document.querySelector('main').innerHTML=mode==='parent'?w.SchoolFollowup.parent():w.SchoolFollowup.staff();
 return {dom,w,mock};
}
(async()=>{
 const {dom,w}=boot();await tick(160);
 let form=w.document.querySelector('[data-ff-promise]');assert(form,'parent payment form rendered');
 assert.equal(form.elements.promise.min,'2026-11-02');assert.equal(form.elements.promise.max,'2026-11-09');assert.equal(form.elements.amount.max,'1000');
 form.elements.promise.value='2026-11-10';assert.equal(form.elements.promise.validity.rangeOverflow,true,'8th day blocked in UI');
 form.elements.promise.value='2026-11-09';form.elements.amount.value='125.25';form.elements.note.value='My own note';
 w.document.querySelector('[data-school-language]').click();await tick(150);
 assert.equal(w.localStorage.getItem('sch_ui_lang'),'hinglish');assert(w.document.body.textContent.includes('Fees dene ki date'));
 form=w.document.querySelector('[data-ff-promise]');assert.equal(form.elements.note.value,'My own note','language change preserves parent draft');
 assert.equal(form.elements.promise.value,'2026-11-09');assert(w.document.body.textContent.includes('Synthetic Child'),'identity untouched');
 form.dispatchEvent(new w.Event('submit',{bubbles:true,cancelable:true}));await tick(180);
 const post=requests.find(x=>x.action==='promise');assert.equal(post.body.amount,'125.25');assert.equal(post.body.promise,'2026-11-09');assert.equal(post.token,'synthetic-token');
 assert(w.document.body.textContent.includes('save ho gayi'),'submission feedback translated');assert(w.document.body.textContent.includes('₹125.25'),'saved amount shown');
 w.document.querySelector('[data-school-language]').click();await tick(100);assert(w.document.body.textContent.includes('Your fee account'),'English restored');dom.window.close();
 const s=boot('staff','hinglish');await tick(160);
 assert(s.w.document.body.textContent.includes('Parents ki di hui dates'));assert(s.w.document.body.textContent.includes('Company'),'tag visible');
 s.w.document.querySelector('[data-ff-call]').click();await tick(180);
 let result=s.w.document.querySelector('[data-ff-result]');assert(result,'call result form opened');assert(s.w.document.body.textContent.includes('Baat confirm nahi hai.'),'Hinglish automatic remark');
 result.elements.outcome.value='Promised to pay';result.elements.outcome.dispatchEvent(new s.w.Event('change',{bubbles:true}));assert(result.elements.promise.required&&result.elements.amount.required,'promise result requires date and amount');
 result.elements.promise.value='2026-11-05';result.elements.amount.value='200';result.elements.note.value='Actual saved remark';
 result.dispatchEvent(new s.w.Event('submit',{bubbles:true,cancelable:true}));await tick(180);assert(requests.some(x=>x.action==='result'&&x.body.amount==='200'));
 assert(s.w.document.body.textContent.includes('nateeja save ho gaya'),'call save feedback');
 s.w.document.querySelector('[data-school-language]').click();await tick(120);assert(s.w.document.body.textContent.includes('Calling list'));
 s.dom.window.close();
 console.log('Follow-up UI: parent bounds, remembered Hinglish/English, draft preservation, tags, honest call attempt and saved result passed');
})().catch(e=>{console.error(e);process.exit(1);});
