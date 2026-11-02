const fs=require('node:fs'),assert=require('node:assert/strict'),{JSDOM}=require('jsdom');
const boot=fs.readFileSync('school/family-android/app/src/main/assets/family-start.js','utf8');
function fixture(role=''){
 const dom=new JSDOM('<!doctype html><html><head></head><body><main id="root"></main></body></html>',{url:'https://maakit.in/school/',runScripts:'dangerously',pretendToBeVisual:true});
 const w=dom.window;if(role)w.localStorage.setItem('sn_family_role',role);
 w.eval("var loginRole='teacher';var U=null;var STATUS={setup:false,reset:false};var SchoolLang={control:()=>'<button data-school-language>English / Hinglish</button>',apply:()=>{}};function vLogin(){return '<p>Attendance, diaries, lessons and results for every class — on every teacher\\'s phone.</p><div class=\"seg\">'+[['teacher','Teacher'],['parent','Parent'],['student','Student'],['principal','Staff']].map(([r,l])=>'<button onclick=\"loginRole=\\''+r+'\\';render()\">'+l+'</button>').join('')+'</div><label>'+loginRole+'</label>';}function render(){document.getElementById('root').innerHTML=vLogin();}");
 w.eval(fs.readFileSync('school/family-android/app/src/main/assets/language.js','utf8'));w.eval(boot);return {dom,w};
}
let {dom,w}=fixture();
assert.equal(w.loginRole,'parent');w.document.querySelector('[data-school-language]').click();assert.equal(w.SchoolLang.t('Log in'),'Login karein','Hinglish login available');w.document.querySelector('[data-school-language]').click();assert.deepEqual([...w.document.querySelectorAll('.seg button')].map(e=>e.textContent),['Parent','Student']);
assert(!w.document.getElementById('root').textContent.includes("teacher's phone"));
w.document.querySelectorAll('.seg button')[1].click();assert.equal(w.loginRole,'student');assert.equal(w.localStorage.getItem('sn_family_role'),'student');
w.eval(boot);assert.equal(w.document.querySelectorAll('#sn-family-language').length,1,'bootstrap is idempotent');
w.STATUS.setup=true;w.render();assert(w.document.getElementById('root').textContent.includes('school office'));assert(!w.document.getElementById('root').innerHTML.includes('su-key'),'no admin setup form in family entry');dom.window.close();
({dom,w}=fixture('student'));assert.equal(w.loginRole,'student','role remembered');dom.window.close();
console.log('Family entry: Parent/Student only, parent default, remembered role, idempotent bootstrap and no staff setup passed');
