'use strict';
(function(){
 if(window.__snFamily)return;window.__snFamily=true;
 const allowed=['parent','student'],themes=[['#6366f1','#4338ca','#eeedff'],['#ff914b','#ed641c','#fff1e7'],['#eb5c96','#c81f64','#fdebf2'],['#35bc88','#14915a','#e8f8ee'],['#26c4ce','#0b8a99','#e7f8fa']];
 const icons=['<path d="M8 21 24 8l16 13v20H28V28h-8v13H8Z" fill="#fff"/><path d="m4 23 20-17 20 17" fill="none" stroke="#ffd976" stroke-width="4"/>','<path d="M7 10h15l3 4 16-4v29l-17 4-17-4Z" fill="#fff"/><path d="M24 14v29M11 19h9m-9 7h9m-9 7h9m9-13 8-2m-8 9 8-2m-8 9 8-2" stroke="#ffb55b" stroke-width="2"/>','<rect x="6" y="12" width="36" height="29" rx="7" fill="#fff"/><rect x="10" y="8" width="26" height="10" rx="3" fill="#ffd976"/><circle cx="26" cy="28" r="9" fill="#f4a8c6"/><text x="26" y="33" text-anchor="middle" font-size="15" fill="#9b2251">₹</text>','<rect x="7" y="7" width="34" height="35" rx="7" fill="#fff"/><path d="M14 32v-8m10 8V17m10 15V12" stroke="#46c693" stroke-width="5"/><path d="m12 17 8-5 7 2 8-8" stroke="#ffd976" stroke-width="3" fill="none"/>','<rect x="9" y="6" width="30" height="37" rx="5" fill="#fff"/><path d="M15 15h17m-17 7h17m-17 7h10" stroke="#48c7cf" stroke-width="3"/><circle cx="35" cy="35" r="9" fill="#ffd976"/><path d="m31 35 3 3 5-6" stroke="#8d6820" stroke-width="2" fill="none"/>'];
 const drawing=i=>'<svg viewBox="0 0 48 48" aria-hidden="true">'+icons[i]+'</svg>';
 const t=s=>window.SchoolLang?SchoolLang.t(s):s;
 let preferred='parent',lastTheme=0,introTimer=null;
 try{const r=localStorage.getItem('sn_family_role');if(allowed.includes(r))preferred=r;}catch(e){}
 if(typeof loginRole!=='undefined')loginRole=preferred;
 if(typeof vLogin==='function'){
  const original=vLogin;
  vLogin=function(){
   if(!allowed.includes(loginRole))loginRole=preferred;
   try{localStorage.setItem('sn_family_role',loginRole);}catch(e){}
   if(typeof STATUS!=='undefined'&&STATUS&&(STATUS.setup||STATUS.reset))return '<div class="card">Please ask the school office to complete the school setup.</div>';
   const shell=document.createElement('div');shell.innerHTML=original();
   shell.querySelectorAll('.seg button').forEach(b=>{const r=(b.getAttribute('onclick')||'').match(/loginRole='([^']+)'/);if(r&&!allowed.includes(r[1]))b.remove();});
   shell.querySelectorAll('p').forEach(p=>{if(p.textContent==="Attendance, diaries, lessons and results for every class — on every teacher's phone.")p.textContent='Homework, attendance, results and fees — for students and parents.';});
   const form=shell.querySelector('.max-w-sm.bg-white');
   if(!form)return shell.innerHTML;
   return '<div class="fam-login"><div class="fam-login-art"><div class="fam-orbit"></div><img class="fam-logo" src="img/chairman-512.png" alt="SNMSIS school logo"><small>SN MISHRA SINGAPORE INTERNATIONAL SCHOOL</small><h1>Small steps.<br>Bright futures.</h1><p>Student & Parents App</p><div class="fam-drawings">'+drawing(1)+drawing(3)+drawing(4)+'</div></div><section class="fam-login-form">'+form.innerHTML+'</section></div>';
  };
 }
 function language(){return window.SchoolLang?SchoolLang.control():'';}
 if(!document.getElementById('sn-family-language')){
  const bar=document.createElement('div');bar.id='sn-family-language';bar.className='fam-language';bar.innerHTML='<b>SNMSIS Family</b><span>'+language()+'</span>';document.body.prepend(bar);
 }
 const root=document.getElementById('root');
 document.documentElement.classList.add('sn-family');
 const originalRender=typeof render==='function'?render:null;
 if(originalRender)render=function(){
  originalRender();
  if(typeof U==='undefined'||!U||!allowed.includes(U.role)||typeof S==='undefined'||!S){document.body.classList.remove('fam-signed-in');return;}
  document.body.classList.add('fam-signed-in');
  const content=root.querySelector('main.erp-wrap');if(!content)return;
  const all=NAV[U.role]||[],isParent=U.role==='parent';
  const tabs=[['phome','Home',0],['phw','Homework',1],[isParent?'pfees':'game',isParent?'Fees':'Play',2],['presults','Progress',3],['pnotices','Notices',4]];
  const active=Math.max(0,tabs.findIndex(x=>x[0]===view));
  const previousIndex=lastTheme,theme=themes[active],previous=themes[lastTheme];lastTheme=active;
  const kid=typeof pKid==='function'&&typeof PD!=='undefined'&&PD?pKid():null;
  const hero='<div class="fam-thought"><span>✦</span><div><b>'+t('A little better, every day')+'</b><p>'+t('Together, we help every child shine.')+'</p></div>'+drawing(1)+'</div>';
  const child=kid?'<div class="fam-child"><span class="fam-avatar">'+esc((kid.name||'?').split(/\s+/).map(x=>x[0]).slice(0,2).join(''))+'</span><div><small>'+t('Your learning journey')+'</small><b>'+esc(kid.name)+'</b><p>Class '+esc(kid.cls)+'</p></div><span class="fam-star">✦</span></div>':'';
  const oldNodes=[...content.childNodes];
  root.innerHTML='<div class="fam-phone" style="--fa:'+theme[1]+';--fb:'+theme[0]+';--ft:'+theme[2]+';--old:'+previous[1]+'"><header class="fam-head"><div class="fam-head-wash"></div><div class="fam-heading"><img src="img/chairman-512.png" alt="School logo"><div><small>SNMSIS · '+t(isParent?'Parent':'Student')+'</small><b>'+t('Welcome to your school')+'</b></div><button class="fam-account" onclick="SNFamily.menu()" aria-label="Open account menu">☰</button></div><nav class="fam-tabs" aria-label="Main menu"><span class="fam-fold" style="transform:translateX('+(active*100)+'%)"></span>'+tabs.map(([key,label,icon])=>'<button class="fam-tab '+(view===key?'on':'')+'" onclick="go(\''+key+'\')" '+(view===key?'aria-current="page"':'')+'>'+drawing(icon)+'<span>'+t(label)+'</span></button>').join('')+'</nav><div class="fam-session"><span>● '+esc(S.settings.session||'School session')+'</span>'+language()+'</div></header><main class="fam-content">'+(view==='phome'?hero+child:'')+'<div class="fam-real-screen"></div><footer class="fam-footer">SNMSIS Family · Powered by Maakit</footer></main><dialog id="fam-menu"><div class="fam-sheet"><div class="fam-sheet-top"><b>'+t('Your school, at a glance')+'</b><button onclick="document.getElementById(\'fam-menu\').close()" aria-label="Close menu">×</button></div><div class="fam-shortcuts">'+all.map(([key,icon,label])=>'<button onclick="document.getElementById(\'fam-menu\').close();go(\''+key+'\')"><i class="fa-solid '+icon+'"></i>'+t(label)+'</button>').join('')+'</div><button onclick="SNFamily.intro()">'+t('Replay welcome')+'</button><button onclick="changeMyPin()">'+t('Change PIN')+'</button><button onclick="logout()">'+t('Log out')+'</button></div></dialog></div>';
  const fold=root.querySelector('.fam-fold');
  if(fold.animate&&!(window.matchMedia&&matchMedia('(prefers-reduced-motion: reduce)').matches)){fold.animate([{transform:'translateX('+(previousIndex*100)+'%)'},{transform:'translateX('+(active*100)+'%)'}],{duration:500,easing:'cubic-bezier(.22,1,.36,1)'});const wash=root.querySelector('.fam-head-wash');wash.style.background=previous[1];wash.animate([{opacity:1},{opacity:0}],{duration:550,fill:'forwards'});}
  const target=root.querySelector('.fam-real-screen');oldNodes.forEach(n=>target.appendChild(n));
  if(view==='phome'){const oldHero=target.querySelector('.kid-hero');if(oldHero){oldHero.classList.add('fam-stats');const stats=oldHero.querySelector('.grid');if(stats){oldHero.replaceChildren(stats);stats.classList.add('fam-stat-grid');}}}
  if(view==='faculty'&&typeof loadParentFaculty==='function')loadParentFaculty();
  if(window.SchoolLang)SchoolLang.apply();
 };
 window.SNFamily={
  menu(){const d=document.getElementById('fam-menu');if(d)d.showModal();},
  closeIntro(){clearTimeout(introTimer);document.getElementById('fam-intro')?.remove();},
  intro(){
   this.closeIntro();
   const reduced=window.matchMedia&&matchMedia('(prefers-reduced-motion: reduce)').matches;
   const el=document.createElement('div');el.id='fam-intro';el.setAttribute('role','dialog');el.setAttribute('aria-label','Welcome to SNMSIS Family');el.innerHTML='<div class="fam-story"><i></i><i></i><i></i></div><button class="fam-skip" onclick="SNFamily.closeIntro()">Skip welcome →</button><div class="fam-intro-scene"></div><small class="fam-intro-foot">SNMSIS FAMILY · BHADOHI</small>';document.body.appendChild(el);
   const scenes=[
    '<img class="fam-logo fam-intro-logo" src="img/chairman-512.png" alt="School logo"><div class="fam-script">SNMSIS</div><small>एस एन एम एस आई एस</small><h1>'+t('A bright new beginning')+'</h1><p>SN Mishra Singapore<br>International School</p>',
    '<div class="fam-intro-illustration">'+drawing(1)+'</div><small>LEARN · EXPLORE · GROW</small><h1>'+t('Every small step counts')+'</h1><p>'+t('Homework, attendance and progress.<br>Your school day, beautifully connected.')+'</p>',
    '<div class="fam-intro-illustration">'+drawing(3)+'</div><small>STUDENTS + PARENTS + SCHOOL</small><h1>'+t('Growing together')+'</h1><p>'+t('One family. One school.<br>A world of possibilities.')+'</p><button class="fam-start" onclick="SNFamily.closeIntro()">'+t('Let’s begin')+' →</button>'
   ];
   let index=0;const show=()=>{if(!el.isConnected)return;el.querySelector('.fam-intro-scene').innerHTML=scenes[index];el.querySelectorAll('.fam-story i').forEach((b,i)=>{b.className=i<=index?'done':'';});index++;if(index<scenes.length)introTimer=setTimeout(show,reduced?1800:3200);else introTimer=setTimeout(()=>this.closeIntro(),reduced?2500:4000);};show();
  }
 };
 if(originalRender)render();
 if(window.SchoolLang)SchoolLang.apply();
 SNFamily.intro();
})();
