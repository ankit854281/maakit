'use strict';
const $ = id => document.getElementById(id);
const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const money = n => '₹' + Number(n || 0).toLocaleString('en-IN', {maximumFractionDigits:2});
const paths = {home:'M3 10l9-7 9 7M5 9v12h14V9M9 21v-8h6v8',fees:'M4 4h16v16H4zM8 8h8M8 12h8M8 16h4',attendance:'M5 4h14v17H5zM9 3v3M15 3v3M8 12l3 3 5-6',staff:'M16 21v-3a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v3M9 10a4 4 0 1 0 0-8 4 4 0 0 0 0 8M19 8v6M16 11h6',receipts:'M6 3h12v18l-3-2-3 2-3-2-3 2zM9 7h6M9 11h6M9 15h4'};
const icon = name => '<svg class="icon" viewBox="0 0 24 24" aria-hidden="true"><path d="' + paths[name] + '"/></svg>';
const tabs = [['home','Home'],['fees','Fees'],['receipts','Receipts'],['attendance','Attendance'],['staff','Staff']];
let ME = null, DATA = null, STAFF = [], TAB = 'home', QUERY = '', FRAME = '', ERROR = '', BUSY = false, ADD = null, LOGIN = false;
let loadVersion = 0;
const newNonce = () => Array.from(crypto.getRandomValues(new Uint8Array(12)), b => b.toString(16).padStart(2,'0')).join('');
function toast(message) { $('toast').textContent = message; clearTimeout(toast.timer); toast.timer = setTimeout(() => $('toast').textContent = '', 6000); }
async function api(path, body) {
  const response = await fetch(path, {method:body ? 'POST':'GET',cache:'no-store',headers:{'Content-Type':'application/json','X-School-Token':localStorage.getItem('sch_token') || ''}, ...(body ? {body:JSON.stringify(body)} : {})});
  let data; try { data = await response.json(); } catch { throw Error('Could not reach the school server. Please retry.'); }
  if (!response.ok || !data.ok) throw Object.assign(Error(data.error || 'Please try again.'), {code:response.status});
  return data;
}
const sum = (rows, key) => rows.reduce((n, row) => n + Number(row[key] || 0), 0);
const validReceipts = () => (DATA?.receipts || []).filter(r => r.status !== 'Cancelled' && r.session === DATA.ses);
function due(row) { return Math.max(0, Number(row.oldDue || 0) - Number(row.oldPaid || 0)) + (row.heads || []).reduce((n,h) => n + Math.max(0,h.amt - h.paid),0); }
function goto(tab) { TAB=tab; FRAME=''; QUERY=''; ADD=null; ERROR=''; history.pushState({tab},''); render(); }
function loginView(change=false) {
  LOGIN=true;
  $('app').innerHTML = '<div class="shell"><header><div class="brand"><img src="img/snmsis-logo.png" alt="SNMSIS">SNMSIS · SCHOOL OFFICE</div><h1>Your work keeps<br>our school moving.</h1><p class="hero-note">Accounts, people and daily records. One place for every school day.</p></header><div class="login"><h2>'+(change?'Choose your new PIN':'Welcome to Accounts')+'</h2><p class="muted">Sign in with your Accountant PIN.</p><form id="loginForm" class="panel"><label for="pin">'+(change?'Current PIN':'Accountant PIN')+'</label><input id="pin" type="password" inputmode="numeric" pattern="[0-9]{4,8}" minlength="4" maxlength="8" required autocomplete="current-password">'+(change?'<label for="newpin">New PIN</label><input id="newpin" type="password" inputmode="numeric" pattern="[0-9]{4,8}" required minlength="4" maxlength="8" autocomplete="new-password">':'')+'<button class="primary" type="submit">'+(change?'Save new PIN':'Open my office')+'</button><p id="loginError" role="alert"></p></form><div class="welcome"><b>Every careful entry builds trust.</b><p class="muted">Thank you for keeping the school organised and helping families every day.</p></div></div></div>';
  $('loginForm').onsubmit = async event => {
    event.preventDefault(); const button=event.currentTarget.querySelector('button'); button.disabled=true;
    try {
      if(change) await api('api.php?a=setpin',{old:$('pin').value,pin:$('newpin').value});
      else {const result=await api('api.php?a=login',{role:'accountant',pin:$('pin').value});localStorage.setItem('sch_token',result.token);if(result.mustChange){loginView(true);return;}}
      LOGIN=false; await load();
    } catch(error) { $('loginError').textContent=error.message; } finally {button.disabled=false;}
  };
}
async function load() {
  const version=++loadVersion;
  try {
    const staff=await api('accountant_api.php?a=staff');
    const data=await api('feedash_api.php?a=data');
    if(version!==loadVersion)return;
    ME=staff.me;STAFF=staff.staff;DATA=data;ERROR='';LOGIN=false;render();
  } catch(error) {
    if(version!==loadVersion)return;
    if([401,403,428].includes(error.code)){ME=null;DATA=null;STAFF=[];loginView(error.code===428);}
    else {ERROR=error.message;render();}
  }
}
function home() {
  const today=validReceipts().filter(r=>r.date===DATA.today);
  return '<div class="stats"><div class="stat green"><span>COLLECTED TODAY</span><b>'+money(sum(today,'received'))+'</b><span>'+today.length+' receipts</span></div><div class="stat warm"><span>SESSION COLLECTION</span><b>'+money(sum(validReceipts(),'received'))+'</b><span>'+esc(DATA.ses)+'</span></div></div><div class="section"><h2>Your daily desk</h2><button data-action="refresh">Refresh</button></div><div class="quick">'+[['fees','Collect fees','Find a child & issue receipt'],['attendance','Attendance','Today’s class-wise report'],['staff','Add teacher / staff','Keep your team up to date'],['receipts','Receipts','Find collections & reprint']].map(([key,label,sub])=>'<button data-tab="'+key+'">'+icon(key)+'<strong>'+label+'</strong><small>'+sub+'</small></button>').join('')+'</div><div class="section"><h2>Recent receipts</h2></div><div class="panel">'+receiptRows(validReceipts().slice().sort((a,b)=>b.date.localeCompare(a.date)).slice(0,5))+'</div><div class="welcome"><b>Small details. A better school day.</b><p class="muted">Your care with accounts gives parents confidence and your team more time to teach.</p></div>';
}
function receiptRows(rows) {
  if(!rows.length)return '<p class="muted">No receipts for this selection.</p>';
  return rows.map(r=>'<div class="row"><div class="grow"><b>'+esc(r.name)+'</b><small>'+esc(r.no)+' · '+esc(r.date)+' · '+esc(r.mode)+'</small></div><div><b>'+money(r.received)+'</b>'+(r.sid?'<button data-student="'+esc(r.sid)+'">View / print</button>':'')+'</div></div>').join('');
}
function feeRows() {
  const q=QUERY.toLowerCase();
  return (DATA.dues || []).filter(r=>[r.name,r.adm,r.father,r.phone,r.cls].join(' ').toLowerCase().includes(q)).map(r=>'<div class="row"><div class="grow"><b>'+esc(r.name)+'</b><small>Class '+esc(r.cls)+' · '+esc(r.adm)+' · '+esc(r.father)+'</small><small>Session balance '+money(due(r))+'</small></div><button class="primary" data-student="'+esc(r.sid)+'">Collect</button></div>').join('') || '<p class="muted">No matching fee cards. Use “Open fee desk” to search all students.</p>';
}
function staffView() {
  if(ADD) return '<button data-action="staffBack">← Staff list</button><h2>Add '+(ADD.kind==='teacher'?'a teacher':'a staff member')+'</h2><form id="staffForm" class="panel"><label for="staffName">Full name</label><input id="staffName" name="name" required minlength="2" maxlength="60" autocomplete="name"><label for="staffPhone">Mobile number (optional)</label><input id="staffPhone" name="phone" type="tel" inputmode="tel" maxlength="16" autocomplete="tel">'+(ADD.kind==='staff'?'<label for="staffRole">Designation</label><input id="staffRole" name="designation" placeholder="Driver, office assistant, support staff…" required maxlength="40">':'<p class="notice">The teacher appears in the Teacher App. First login uses the school’s initial PIN and asks them to choose a new PIN. The Principal assigns classes and subjects.</p>')+'<p class="muted">The Principal can set salary and leave allowance from Staff Salary.</p><button class="primary" id="saveStaff" type="submit">Save '+ADD.kind+'</button><p id="staffError" role="alert"></p></form>';
  return '<div class="toolbar"><button class="primary" data-action="addTeacher">+ Add teacher</button><button data-action="addStaff">+ Add staff</button></div><div class="section"><h2>School team</h2><span class="badge">'+STAFF.length+' members</span></div><div class="panel">'+STAFF.map(s=>'<div class="row"><div class="avatar">'+esc(s.name.slice(0,1))+'</div><div class="grow"><b>'+esc(s.name)+'</b><small>'+esc(s.role)+(s.active?'':' · Inactive')+'</small></div><span class="badge">'+(s.teaching?'Teacher':'Staff')+'</span></div>').join('')+'</div>';
}
function content() {
  if(FRAME)return '<div class="toolbar"><button data-action="closeFrame">← Back to '+esc(TAB)+'</button></div><iframe class="frame" title="Fee collection and receipts" src="'+esc(FRAME)+'"></iframe>';
  if(TAB==='home')return home();
  if(TAB==='attendance')return '<iframe class="frame" title="Daily attendance" src="dailyattendance.php"></iframe>';
  if(TAB==='staff')return staffView();
  if(TAB==='fees')return '<div class="toolbar"><button class="primary" data-action="collect">Open fee desk</button></div>'+(!DATA.live?'<p class="notice">Imported fee records · as on '+esc(DATA.asOn || 'the last import')+'. Collection opens when the Principal activates the session.</p>':'')+'<label for="search">Find student</label><input id="search" type="search" placeholder="Name, admission no., class or parent" value="'+esc(QUERY)+'"><p class="muted">Balances shown for the full session '+esc(DATA.ses)+'.</p><div id="results" class="panel list">'+feeRows()+'</div>';
  return '<label for="search">Find receipt</label><input id="search" type="search" placeholder="Student name or receipt number" value="'+esc(QUERY)+'"><div id="results" class="panel list">'+receiptRows(validReceipts().filter(r=>[r.name,r.no].join(' ').toLowerCase().includes(QUERY.toLowerCase())).slice().reverse())+'</div>';
}
function render() {
  if(!ME || !DATA){$('app').innerHTML='<div class="login"><h1>School office</h1><p class="error">'+esc(ERROR || 'Opening your office…')+'</p><button id="retry">Retry</button></div>';$('retry').onclick=load;return;}
  $('app').innerHTML='<div class="shell"><header><div class="brand"><img src="img/snmsis-logo.png" alt="SNMSIS">SNMSIS · ACCOUNTS<button data-action="logout" aria-label="Sign out">Sign out</button></div><h1>'+(TAB==='home'?'Welcome, '+esc(ME.label || 'Akash'):esc(tabs.find(t=>t[0]===TAB)[1]))+'</h1><p class="hero-note">'+(TAB==='home'?'Your school office, ready for the day.':TAB==='staff'?'The people who make every school day possible.':TAB==='fees'?'Clear records. Confident conversations.':TAB==='attendance'?'Every class, together in one daily report.':'Every payment, easy to find.')+'</p></header><main>'+(ERROR?'<p class="error">'+esc(ERROR)+'</p>':'')+content()+'</main><nav class="dock" aria-label="Main navigation">'+tabs.map(([key,label])=>'<button class="'+(TAB===key?'active':'')+'" data-tab="'+key+'" aria-current="'+(TAB===key?'page':'false')+'">'+icon(key)+label+'</button>').join('')+'</nav></div>';
  if($('search'))$('search').oninput=event=>{QUERY=event.target.value;$('results').innerHTML=TAB==='fees'?feeRows():receiptRows(validReceipts().filter(r=>[r.name,r.no].join(' ').toLowerCase().includes(QUERY.toLowerCase())).slice().reverse());};
  if($('staffForm'))$('staffForm').onsubmit=saveStaff;
}
async function saveStaff(event) {
  event.preventDefault();if(BUSY)return;BUSY=true;$('saveStaff').disabled=true;
  const form=new FormData(event.currentTarget);
  try {
    const result=await api('accountant_api.php?a=add',{kind:ADD.kind,nonce:ADD.nonce,name:form.get('name'),phone:form.get('phone'),designation:form.get('designation')});
    const name=form.get('name');ADD=null;await load();toast(name+' added successfully.');
  } catch(error) {if($('staffError'))$('staffError').textContent=error.message;}
  finally {BUSY=false;if($('saveStaff'))$('saveStaff').disabled=false;}
}
$('app').addEventListener('click',async event=>{
  const button=event.target.closest('button');if(!button || BUSY)return;
  if(button.dataset.tab){goto(button.dataset.tab);return;}
  if(button.hasAttribute('data-student')){FRAME='feecollect.php?mobile=1&session='+encodeURIComponent(DATA.ses)+'&sid='+encodeURIComponent(button.dataset.student);render();return;}
  switch(button.dataset.action){
    case 'refresh':button.disabled=true;await load();break;
    case 'collect':FRAME='feecollect.php?mobile=1&session='+encodeURIComponent(DATA.ses);render();break;
    case 'closeFrame':FRAME='';await load();break;
    case 'staffBack':ADD=null;render();break;
    case 'addTeacher':ADD={kind:'teacher',nonce:newNonce()};render();break;
    case 'addStaff':ADD={kind:'staff',nonce:newNonce()};render();break;
    case 'logout':
      try {await api('api.php?a=logout',{});localStorage.removeItem('sch_token');ME=null;DATA=null;STAFF=[];loginView();}catch(error){toast(error.message);}break;
  }
});
window.addEventListener('popstate',event=>{if(LOGIN)return;TAB=event.state?.tab || 'home';FRAME='';ADD=null;render();});
load();
