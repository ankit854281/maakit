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
 // ---- WhatsApp look (owner, 27 Nov 2026: "ekdam WhatsApp look do") -------------------------------------------------
 // Home = a chat list (Homework, Attendance, Fees, Notices, Class teacher, Results, Games…) with last update, time and a
 // green unread badge; children as Status circles; Homework and Notices open as chat bubbles on the WhatsApp wallpaper;
 // every other screen keeps the school's real screen under a WhatsApp chat header. Only this family's data (PD) is used.
 const WA={f:'all',seen:{}};
 try{WA.seen=JSON.parse(localStorage.getItem('sn_wa_seen')||'{}')||{};}catch(e){}
 const seenOf=k=>WA.seen[k]||'',markSeen=(k,v)=>{if(WA.seen[k]===v)return;WA.seen[k]=v;try{localStorage.setItem('sn_wa_seen',JSON.stringify(WA.seen));}catch(e){}};
 const P={chat:'<path d="M12 3a9 9 0 0 0-7.8 13.5L3 21l4.6-1.2A9 9 0 1 0 12 3Z"/>',book:'<path d="M4 5a2 2 0 0 1 2-2h13v16H6a2 2 0 0 0-2 2Z"/><path d="M4 19a2 2 0 0 1 2-2h13v4H6a2 2 0 0 1-2-2Z"/>',fee:'<rect x="3" y="6" width="18" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/>',play:'<rect x="3" y="7" width="18" height="11" rx="4"/><path d="M8 11v3M6.5 12.5h3M15 12h.01M17.5 13.5h.01"/>',chart:'<path d="M4 20V10M10 20V4M16 20v-7M21 20H3"/>',bell:'<path d="M6 16V11a6 6 0 1 1 12 0v5l2 2H4Z"/><path d="M10 21h4"/>',back:'<path d="M15 5l-7 7 7 7"/>',dots:'<circle cx="12" cy="5" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="12" cy="19" r="1.6"/>',check:'<path d="M5 12l4 4 10-10"/>',person:'<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',star:'<path d="M12 3l2.6 5.6 6 .7-4.5 4 1.3 6-5.4-3.1-5.4 3.1 1.3-6-4.5-4 6-.7Z"/>',cal:'<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',bag:'<path d="M6 8h12l1 12H5Z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/>',bulb:'<path d="M9 18h6M10 21h4"/><path d="M12 3a6 6 0 0 0-4 10.5c.7.7 1 1.5 1 2.5h6c0-1 .3-1.8 1-2.5A6 6 0 0 0 12 3Z"/>',trophy:'<path d="M8 4h8v5a4 4 0 0 1-8 0Z"/><path d="M8 6H5a3 3 0 0 0 3 4M16 6h3a3 3 0 0 1-3 4M12 13v4M8 21h8M9 17h6"/>',list:'<path d="M9 6h11M9 12h11M9 18h11"/><path d="M4 6l1 1 2-2M4 12l1 1 2-2M4 18l1 1 2-2"/>',gear:'<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1Z"/>'};
 const ic=n=>'<svg viewBox="0 0 24 24" aria-hidden="true">'+(P[n]||'')+'</svg>';
 const SUBC=['#1FA855','#027EB5','#E0662B','#8E44C9','#D6336C','#0E9F9A','#B7791F','#3D5AFE'];
 const subColor=s=>{let h=0;for(const ch of String(s||''))h=(h*31+ch.charCodeAt(0))>>>0;return SUBC[h%SUBC.length];};
 const dayLabel=d=>{if(!d)return '';const t0=todayISO();if(d===t0)return t('Today');if(typeof addDays==='function'&&d===addDays(t0,-1))return t('Yesterday');try{return new Date(d+'T00:00:00').toLocaleDateString('en-IN',{weekday:'long',day:'numeric',month:'short'});}catch(e){return d;}};
 const short=d=>{if(!d)return '';const t0=todayISO();if(d===t0)return t('Today');if(typeof addDays==='function'&&d===addDays(t0,-1))return t('Yesterday');return d.slice(8,10)+'/'+d.slice(5,7);};
 const navHas=k=>(NAV[U.role]||[]).some(x=>x[0]===k);
 function kidStatus(kid){
  if(!PD.kids.length)return '';
  return '<div class="wa-status fam-child">'+PD.kids.map(k=>'<button class="'+(k.id===kid.id?'on':'')+'" onclick="PKID=\''+esc(k.id)+'\';render()"><span class="ring"><span>'+esc((k.name||'?').split(/\s+/).map(x=>x[0]).slice(0,2).join(''))+'</span></span><small>'+esc((k.name||'').split(' ')[0])+'</small><i>'+esc(k.cls)+'</i></button>').join('')+'</div>';
 }
 function chats(kid,isParent){
  const date=todayISO(),at=pAtt(kid),hw=PD.hw.filter(h=>h.cls===kid.cls).sort((a,b)=>b.date.localeCompare(a.date)),last=hw[0];
  const hwNew=hw.filter(h=>h.date>(seenOf('hw:'+kid.id)||'0000')).length;
  const nts=(PD.notices||[]).slice().sort((a,b)=>String(b.date).localeCompare(String(a.date))),nNew=nts.filter(n=>String(n.date)>(seenOf('nt')||'0000')).length;
  const exams=(S.exams||[]).filter(e=>PD.marks.some(m=>m.sid===kid.id&&m.exam===e.id)),le=exams[exams.length-1];
  let pct=null;if(le){let g=0,mx=0;PD.marks.filter(m=>m.sid===kid.id&&m.exam===le.id).forEach(m=>{if(m.mark!=='AB'&&m.mark!==''&&m.max){g+=+m.mark;mx+=+m.max;}});pct=mx?Math.round(g/mx*1000)/10:null;}
  const R=[];
  R.push({g:'study',go:'phw',ic:'book',c:'#1FA855',title:t('Homework & class work'),prev:last?('<b style="color:'+subColor(last.subj)+'">'+esc(last.subj)+':</b> '+esc(last.hw||last.classwork||last.topic||'')):t('Homework will appear here when the teacher writes the diary'),time:last?short(last.date):'',n:hwNew});
  const Z=window.FZ&&VIEWS.ptmr?window.FZ:null;
  if(Z)R.push({g:'study',go:'ptmr',ic:'bag',c:'#F08C00',title:t('Next school day'),prev:esc(Z.tomorrow(kid)),time:Z.tomorrowDate(kid)?short(Z.tomorrowDate(kid)):''});
  R.push({g:'school',go:'presults',ic:'check',c:at.today&&!at.today.present?'#E5484D':'#00A884',title:t('Attendance'),prev:(at.today?(at.today.present?'✅ '+t('Present today'):'❌ '+t('Absent today')):t("Today's attendance not marked yet"))+(at.pct!=null?' · '+at.pct+'% '+t('overall'):''),time:at.today?short(date):''});
  if(isParent&&navHas('pfees'))R.push({g:'school',go:'pfees',ic:'fee',c:'#E0662B',title:t('Fees'),prev:t('Fee account, receipts and online payment'),time:''});
  R.push({g:'school',go:'pnotices',ic:'bell',c:'#027EB5',title:t('Notices & calendar'),prev:nts[0]?esc(nts[0].title||''):t('No notice yet'),time:nts[0]?short(String(nts[0].date||'')):'',n:nNew});
  if(Z&&VIEWS.pleave)R.push({g:'school',go:'pleave',ic:'cal',c:'#E0662B',title:t('Leave application'),prev:t('Apply for leave — the class teacher approves it'),time:''});
  if(Z)R.push({g:'school',go:'pcal',ic:'cal',c:'#5B6B7B',title:t('Calendar'),prev:esc(Z.calendar(kid)),time:''});
  if(navHas('studio'))R.push({g:'school',go:'studio',ic:'person',c:'#8E44C9',title:kid.ct?esc(kid.ct):t('Class teacher'),prev:t('Class teacher')+' · Class '+esc(kid.cls)+' — '+t('message the teacher'),time:''});
  const zr=window.FZ&&FZ.result?FZ.result(kid):'';
  R.push({g:'study',go:'presults',ic:'chart',c:'#D6336C',title:t('Results & progress'),prev:zr?esc(zr):le?esc(le.name)+(pct!=null?' · '+pct+'%':''):t('No exam marks published yet'),time:''});
  if(Z&&VIEWS.pchk)R.push({g:'study',go:'pchk',ic:'check',c:'#0F8A5F',title:t('Homework & copy checks'),prev:esc(FZ.checks(kid)||'Checks appear here once teachers record them'),time:''});
  if(Z)R.push({g:'study',go:'prec',ic:'trophy',c:'#B7791F',title:t('Achievements & record'),prev:esc(Z.record(kid)),time:''});
  if(Z)R.push({g:'study',go:'pzone',ic:'bulb',c:'#E0662B',title:t('Learning Hub'),prev:esc(Z.zone()),time:''});
  if(Z)R.push({g:'study',go:'psyl',ic:'list',c:'#0E9F9A',title:t('Exam syllabus'),prev:t('Chapters coming in each exam'),time:''});
  if(navHas('game'))R.push({g:'study',go:'game',ic:'play',c:'#3D5AFE',title:t('Game Zone'),prev:t('Practise and learn — 5 questions a round'),time:''});
  if(navHas('quiz'))R.push({g:'study',go:'quiz',ic:'star',c:'#B7791F',title:t('Quiz Duel'),prev:t('Test classmates on what you have learnt'),time:''});
  if(navHas('learning'))R.push({g:'study',go:'learning',ic:'book',c:'#0E9F9A',title:t('Learning Adventure'),prev:t('Lessons and practice from the teachers'),time:''});
  if(navHas('faculty'))R.push({g:'school',go:'faculty',ic:'person',c:'#5B6B7B',title:t('Our Teachers'),prev:t('Meet the teachers'),time:''});
  const F=WA.f,list=R.filter(r=>F==='all'||(F==='unread'?r.n>0:r.g===F));
  const chip=(k,l)=>'<button class="'+(F===k?'on':'')+'" onclick="SNFamily.filter(\''+k+'\')">'+t(l)+'</button>';
  return '<div class="wa-chips">'+chip('all','All')+chip('unread','Unread')+chip('study','Study')+chip('school','School')+'</div>'+
   '<div class="wa-list fam-real-screen fam-stats">'+(list.length?list.map(r=>'<button class="wa-row" onclick="go(\''+r.go+'\')"><span class="wa-av" style="background:'+r.c+'">'+ic(r.ic)+'</span><span class="wa-mid"><b>'+r.title+'</b><small>'+r.prev+'</small></span><span class="wa-end"><small class="'+(r.n?'g':'')+'">'+esc(r.time||'')+'</small>'+(r.n?'<em>'+r.n+'</em>':'')+'</span></button>').join(''):'<p class="wa-none">'+t('No unread updates')+' ✓</p>')+'</div>'+
   '<p class="wa-lock">🔒 '+(isParent?t('Only your child\'s school information is shown here.'):t('Only your own school information is shown here.'))+'</p>';
 }
 function hwChat(kid){
  const rows=PD.hw.filter(h=>h.cls===kid.cls).sort((a,b)=>a.date.localeCompare(b.date)),by={};rows.forEach(h=>(by[h.date]=by[h.date]||[]).push(h));
  markSeen('hw:'+kid.id,todayISO());
  const extra=(typeof parentAcademicChecks==='function'?parentAcademicChecks(kid):'')+(typeof pNotes==='function'?pNotes(kid):'');
  return '<div class="wa-chat fam-real-screen"><div class="wa-sys">🔒 '+t('Class work and homework from the teachers\' diary — last 10 days.')+'</div>'+
   (Object.keys(by).length?Object.entries(by).map(([d,l])=>'<div class="wa-day"><span>'+esc(dayLabel(d))+'</span></div>'+l.map(h=>'<div class="wa-b"><b style="color:'+subColor(h.subj)+'">'+esc(h.subj)+'</b>'+(h.topic?'<p>📖 '+esc(h.topic)+'</p>':'')+(h.classwork?'<p><i>'+t('Class work')+':</i> '+esc(h.classwork)+'</p>':'')+(h.hw?'<p class="hw"><i>'+t('Homework')+':</i> <strong>'+esc(h.hw)+'</strong></p>':'')+(h.books?'<p><i>'+t('Bring')+':</i> '+esc(h.books)+'</p>':'')+(h.revision?'<p><i>'+t('Revision')+':</i> '+esc(h.revision)+'</p>':'')+'<time>'+esc(short(d))+' ✓✓</time></div>').join('')).join(''):'<div class="wa-sys">'+t('Nothing written in the last 10 days.')+'</div>')+
   (extra?'<div class="wa-extra">'+extra+'</div>':'')+'</div>';
 }
 function noticeChat(){
  const nts=(PD.notices||[]).slice().sort((a,b)=>String(a.date).localeCompare(String(b.date)));
  if(nts.length)markSeen('nt',String(nts[nts.length-1].date||''));
  const date=todayISO(),kidCls=PD.kids.map(k=>k.cls),up=[...S.cal,...(typeof examItems==='function'?examItems().filter(e=>e.cls.some(c=>kidCls.includes(c))):[])].filter(e=>(e.to||e.date)>=date).sort((a,b)=>a.date.localeCompare(b.date)).slice(0,12);
  return '<div class="wa-chat fam-real-screen"><div class="wa-sys">📢 '+t('School notices — only the school can send messages here.')+'</div>'+
   nts.map(n=>'<div class="wa-b"><b style="color:#027EB5">'+esc(n.title||'')+'</b>'+(n.body?'<p>'+esc(n.body)+'</p>':'')+'<time>'+esc(short(String(n.date||'')))+'</time></div>').join('')+
   (up.length?'<div class="wa-day"><span>'+t('Coming up')+'</span></div><div class="wa-b">'+up.map(e=>'<p>'+ic('cal').replace('<svg','<svg class="mini"')+' <b>'+esc(e.title)+'</b> · '+esc(dayLabel(e.date))+(e.type?' <i>'+esc(e.type)+'</i>':'')+'</p>').join('')+'</div>':'')+
   '<div class="wa-sys">'+t('Change your login PIN')+' <button class="wa-mini" onclick="changeMyPin()">'+t('Change PIN')+'</button></div></div>';
 }
 const TITLES={pinfo:'Student profile',pleave:'Leave application',pchk:'Homework & copy checks',ptmr:'Next school day',pcal:'Calendar',prec:'Achievements & record',psyl:'Exam syllabus',pzone:'Learning Hub',pset:'Settings',phw:'Homework & class work',pfees:'Fees',presults:'Results & progress',pnotices:'Notices & calendar',game:'Game Zone',quiz:'Quiz Duel',learning:'Learning Adventure',faculty:'Our Teachers',studio:'Class teacher',finance:'Fee Ledger'};
 if(originalRender)render=function(){
  originalRender();
  if(typeof U==='undefined'||!U||!allowed.includes(U.role)||typeof S==='undefined'||!S){document.body.classList.remove('fam-signed-in');return;}
  document.body.classList.add('fam-signed-in');
  const content=root.querySelector('main.erp-wrap');if(!content)return;
  const all=NAV[U.role]||[],isParent=U.role==='parent';
  const tabs=[['phome','Chats','chat'],['phw','Homework','book'],[isParent?'pfees':'game',isParent?'Fees':'Practice',isParent?'fee':'play'],['presults','Progress','chart'],['pnotices','Updates','bell']];
  const kid=typeof pKid==='function'&&typeof PD!=='undefined'&&PD&&PD.kids&&PD.kids.length?pKid():null;
  const home=view==='phome',chatView=view==='phw'||view==='pnotices';
  const oldNodes=[...content.childNodes];
  const head=home&&kid&&window.FZ&&FZ.home?'':home
   ?'<header class="wa-top"><div class="wa-brand"><b>SNMSIS</b><small>'+t(isParent?'Parent':'Student')+' · '+esc(S.settings.session||'')+'</small></div><span class="wa-lang">'+language()+'</span><button class="wa-ib fam-account" onclick="SNFamily.menu()" aria-label="Open account menu">'+ic('dots')+'</button></header>'
   :'<header class="wa-top wa-chathead"><button class="wa-ib" onclick="go(\'phome\')" aria-label="Back">'+ic('back')+'</button><span class="wa-av sm" style="background:'+(view==='phw'?'#1FA855':view==='pnotices'?'#027EB5':'#00A884')+'">'+ic(view==='phw'?'book':view==='pnotices'?'bell':view==='pfees'?'fee':view==='presults'?'chart':{pinfo:'person',pleave:'cal',pchk:'check',ptmr:'bag',pcal:'cal',prec:'trophy',psyl:'list',pzone:'bulb',pset:'gear'}[view]||'chat')+'</span><div class="wa-brand"><b>'+t(TITLES[view]||'SNMSIS')+'</b><small>'+(kid?esc(kid.name)+' · Class '+esc(kid.cls):'SNMSIS')+'</small></div><button class="wa-ib fam-account" onclick="SNFamily.menu()" aria-label="Open account menu">'+ic('dots')+'</button></header>';
  const rich=home&&kid&&window.FZ&&FZ.home;
  const body=!kid?'<div class="wa-page fam-real-screen"></div>':rich?FZ.home(kid,isParent,'<span class="wa-lang">'+language()+'</span><button class="wa-ib fam-account" onclick="SNFamily.menu()" aria-label="Open account menu">'+ic('dots')+'</button>')+chats(kid,isParent):home?kidStatus(kid)+(window.FZ&&FZ.status?FZ.status(kid):'')+chats(kid,isParent):view==='phw'?(PD.kids.length>1?kidStatus(kid):'')+hwChat(kid):view==='pnotices'?noticeChat():'<div class="wa-page fam-real-screen"></div>';
  root.innerHTML='<div class="fam-phone wa'+(chatView?' wa-wall':'')+(home&&kid&&window.FZ&&FZ.home?' fh-on':'')+'">'+head+'<main class="wa-main">'+body+'</main><nav class="fam-tabs wa-nav" aria-label="Main menu">'+tabs.map(([key,label,icon])=>'<button class="fam-tab '+(view===key?'on':'')+'" onclick="go(\''+key+'\')" '+(view===key?'aria-current="page"':'')+'><span class="pill">'+ic(icon)+'</span><span>'+t(label)+'</span></button>').join('')+'</nav>'+
   '<dialog id="fam-menu"><div class="fam-sheet"><div class="fam-sheet-top"><b>'+t('Your school, at a glance')+'</b><button onclick="document.getElementById(\'fam-menu\').close()" aria-label="Close menu">×</button></div><div class="fam-shortcuts">'+all.map(([key,icon,label])=>'<button onclick="document.getElementById(\'fam-menu\').close();go(\''+key+'\')"><i class="fa-solid '+icon+'"></i>'+t(label)+'</button>').join('')+'</div>'+(VIEWS.pset?'<button onclick="document.getElementById(\'fam-menu\').close();go(\'pset\')">⚙️ '+t('Settings')+'</button>':'')+'<button onclick="SNFamily.intro()">'+t('Replay welcome')+'</button><button onclick="changeMyPin()">'+t('Change PIN')+'</button><button onclick="logout()">'+t('Log out')+'</button></div></dialog></div>';
  const target=root.querySelector('.wa-page');if(target)oldNodes.forEach(n=>target.appendChild(n));
  if(view==='faculty'&&typeof loadParentFaculty==='function')loadParentFaculty();
  if(chatView){const m=root.querySelector('.wa-main');if(m)requestAnimationFrame(()=>{m.scrollTop=m.scrollHeight;});}
  if(window.SchoolLang)SchoolLang.apply();
 };
 window.SNFamily={
  filter(k){WA.f=k;render();},
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
