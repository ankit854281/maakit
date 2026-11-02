'use strict';
(function(){
  const states=new WeakMap(), esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const t=s=>window.SchoolLang?SchoolLang.t(s):s, label=s=>'<span data-school-label="'+esc(s)+'">'+esc(t(s))+'</span>';
  const money=n=>n==null?'—':'₹'+(n/100).toLocaleString('en-IN',{maximumFractionDigits:2}), date=d=>d?d.slice(8,10)+'-'+d.slice(5,7)+'-'+d.slice(0,4):'—';
  const nonce=()=>{const a=new Uint8Array(16);crypto.getRandomValues(a);return [...a].map(x=>x.toString(16).padStart(2,'0')).join('');};
  const autoRemark=c=>'<span data-auto-remark-en="'+esc(c.autoRemark||c.note||'')+'" data-auto-remark-hi="'+esc(c.autoRemarkHi||c.autoRemark||c.note||'')+'">'+esc(SchoolLang.get()==='hinglish'?(c.autoRemarkHi||c.autoRemark||c.note):(c.autoRemark||c.note))+'</span>';
  const outcomes=['Promised to pay','Not answered','Switched off','Call back later','Will visit school','Already paid','Wrong number'];
  async function api(action,body){
    let token='';try{token=localStorage.getItem('sch_token')||'';}catch(e){}
    let r;try{r=await fetch('followup_api.php?a='+action,{method:body?'POST':'GET',headers:{'X-School-Token':token,'Content-Type':'application/json'},...(body?{body:JSON.stringify(body)}:{})});}
    catch(e){throw Error('No internet connection. Please try again.');}
    let d;try{d=await r.json();}catch(e){throw Error('Could not load. Please try again.');}
    if(!r.ok||!d.ok)throw Error(d.error||'Could not load. Please try again.');return d;
  }
  function box(mode){return '<section class="ff" data-ff="'+mode+'"><p>'+label('Loading…')+'</p></section>';}
  function message(root,text,error=false){const el=root.querySelector('[data-ff-message]');if(el){el.textContent=t(text);el.className='ff-message'+(error?' ff-error':'');el.focus();}}
  function status(p){return label(({fulfilled:'Payment recorded',partial:'Part payment recorded',overdue:'Date passed',pending:'Waiting for payment'})[p.status]||'Waiting for payment');}
  function promiseCard(p,child){
    return '<article class="ff-promise ff-'+esc(p.status)+'"><div class="ff-row"><strong class="ff-identity">'+esc(child?.name||p.sid)+'</strong><span class="ff-badge">'+status(p)+'</span></div>'+
      '<div class="ff-row"><span>'+label('Payment date')+' <b>'+date(p.promise)+'</b></span><span>'+label('Promised amount')+' <b>'+money(p.amount)+'</b></span></div>'+
      '<p>'+label('Still to pay')+': <b>'+money(p.remaining)+'</b></p>'+
      (p.previousDate?'<small>'+label('Previous date')+': '+date(p.previousDate)+' · '+p.revision+' submissions</small>':'')+
      (p.note?'<p class="ff-usertext">'+esc(p.note)+'</p>':'')+
      '<small>'+label('Submitted')+': '+date(p.date)+' · <span class="ff-identity">'+esc(p.by)+'</span></small>'+
      (p.history?.length?'<details><summary>'+label('Promise history')+'</summary>'+p.history.map(h=>'<p>'+date(h.promise)+' · '+money(h.amount)+' · <span class="ff-usertext">'+esc(h.note||'')+'</span></p>').join('')+'</details>':'')+'</article>';
  }
  function parent(root,s){
    const d=s.data,by=Object.fromEntries(d.children.map(c=>[c.sid,c])),prom=new Map(d.promises.map(p=>[p.sid,p]));
    root.innerHTML='<div class="ff-row"><h2>'+label('Your fee account')+'</h2><button type="button" data-ff-refresh>'+label('Refresh')+'</button></div><p>'+label('Current session')+': '+esc(d.session)+'</p><p data-ff-message role="status" tabindex="-1"></p>'+
      d.children.map(c=>{
        const p=prom.get(c.sid),copied=c.source==='copied';
        return '<article class="ff-child"><h3 class="ff-identity">'+esc(c.name)+' · '+esc(c.cls)+'</h3><div class="ff-row"><span>'+label('Current due')+'</span><strong class="ff-amount">'+money(c.due)+'</strong></div>'+
          (c.due==null?'<p>'+label('No fee card available. Please contact the school office.')+'</p>':c.overallDue==null?'':'<small>'+label('Whole session balance')+': '+money(c.overallDue)+'</small>')+
          (copied?'<p class="ff-warning">'+label('Fee card copied on')+' '+esc(c.asOn||'—')+'. '+label('Figures are from the copied fee card; contact the office for newer payments.')+'</p>':c.source==='unavailable'?'':'<p class="ff-source">'+label(c.source==='detailed'?'Detailed fee ledger':c.source==='legacy'?'Fee desk account':'Live fee ledger')+'</p>')+
          (p?promiseCard(p,c):'')+
          (d.canPromise&&c.due>0?'<form data-ff-promise="'+esc(c.sid)+'" data-nonce="'+nonce()+'"><h4>'+label(p?'Update payment date':'Promise a payment date')+'</h4>'+
            '<div class="ff-grid"><label>'+label('Payment date')+'<input type="date" name="promise" min="'+d.today+'" max="'+d.maxDate+'" value="" required></label>'+
            '<label>'+label('Amount you will pay')+'<input type="number" name="amount" min="0.01" max="'+(c.due/100)+'" step="0.01" inputmode="decimal" required></label></div>'+
            '<div class="ff-tabs">'+[[0,'Today'],[1,'Tomorrow'],[3,'In 3 days'],[7,'In 1 week']].map(([n,l])=>'<button type="button" data-ff-quick="'+n+'">'+label(l)+'</button>').join('')+'</div><label>'+label('Reason or note (optional)')+'<input name="note" maxlength="300"></label><p class="ff-hint">'+label('Choose a date within the next 7 days.')+' '+label('The school office and Chairman can see this date and amount.')+'</p>'+
            '<button type="submit" class="ff-primary">'+label('Submit payment date')+'</button><p class="ff-hint">'+label('This is a payment promise, not a payment or a receipt.')+'</p></form>':'')+'</article>';
      }).join('')+
      (d.receipts.length?'<article class="ff-child"><h3>'+label('Receipts')+'</h3><div class="ff-table"><table><thead><tr><th>'+label('Date')+'</th><th>'+label('Student')+'</th><th>'+label('Receipt')+'</th><th>'+label('Amount')+'</th></tr></thead><tbody>'+
        d.receipts.slice().sort((a,b)=>b.date.localeCompare(a.date)).slice(0,100).map(r=>'<tr><td>'+date(r.date)+'</td><td class="ff-identity">'+esc(r.name)+'</td><td>'+esc(r.no)+'</td><td>'+money(r.amount)+'</td></tr>').join('')+'</tbody></table></div></article>':'');
  }
  function resultForm(d,c){
    return '<form class="ff-result" data-ff-result="'+esc(c.id)+'" data-sid="'+esc(c.sid)+'" data-nonce="'+nonce()+'"><h3>'+label('Save call result')+'</h3><p>'+label('Dialer requested; connection is not confirmed.')+'</p><small>'+label('Automatic remark')+': <span class="ff-usertext">'+autoRemark(c)+'</span></small>'+
      '<label>'+label('What happened on the call?')+'<select name="outcome" required><option value="">'+esc(t('Choose result'))+'</option>'+outcomes.map(o=>'<option value="'+esc(o)+'">'+esc(t(o))+'</option>').join('')+'</select></label>'+
      '<div class="ff-grid"><label>'+label('Date they gave')+'<input type="date" name="promise" min="'+d.today+'" max="'+d.maxDate+'"></label><label>'+label('Amount they promised')+'<input type="number" name="amount" min="0.01" step="0.01" inputmode="decimal"></label></div>'+
      '<label>'+label('Note')+'<input name="note" maxlength="300"></label><button class="ff-primary" type="submit">'+label('Save result')+'</button></form>';
  }
  function staff(root,s){
    const d=s.data,f=s.filters,by=Object.fromEntries(d.children.map(c=>[c.sid,c])),prom=new Map(d.promises.map(p=>[p.sid,p]));
    const tags=[...new Set(d.children.map(c=>c.tag||''))].sort(),classes=[...new Set(d.children.map(c=>c.cls))].sort();
    const match=c=>(!f.tag||c.tag===f.tag||(f.tag==='__none'&&!c.tag))&&(!f.cls||c.cls===f.cls)&&(!f.q||[c.name,c.father,c.phone,c.tag].some(x=>String(x||'').toLowerCase().includes(f.q.toLowerCase())));
    const promises=d.promises.filter(p=>by[p.sid]&&match(by[p.sid]));
    let rows=d.children.filter(c=>c.due>0&&match(c));
    const last=c=>d.calls.find(a=>(a.sids||[a.sid]).includes(c.sid));
    rows=rows.filter(c=>{const p=prom.get(c.sid),call=last(c);return f.list==='today'?!!(p&&p.status!=='fulfilled'&&p.promise<=d.today||call?.promise&&call.promise<=d.today):
      f.list==='overdue'?p?.status==='overdue':f.list==='parent'?!!p:f.list==='fresh'?(!call||call.date<new Date(Date.parse(d.today+'T12:00:00Z')-7*86400000).toISOString().slice(0,10)):true;});
    rows.sort((a,b)=>b.due-a.due);
    const attempts=d.calls.filter(c=>c.startedAt&&(c.startedDate||c.date)===d.today).length,results=d.calls.filter(c=>(c.completedDate||c.date)===d.today&&!c.initiated).length;
    root.innerHTML='<div class="ff-row"><h2>'+label('Fee follow-ups')+'</h2><button data-ff-refresh type="button">'+label('Refresh')+'</button></div><p>'+label('Current session')+': '+esc(d.session)+'</p>'+
      '<p data-ff-message role="status" tabindex="-1"></p><div class="ff-stats"><div>'+label('Call attempts today')+'<strong>'+attempts+'</strong></div><div>'+label('Results saved today')+'<strong>'+results+'</strong></div><div>'+label('Parent payment dates')+'<strong>'+d.promises.length+'</strong></div></div>'+
      '<div class="ff-filters"><input data-ff-filter="q" placeholder="'+esc(t('Search name, father, mobile or tag'))+'" value="'+esc(f.q)+'" aria-label="Search">'+
      '<select data-ff-filter="tag" aria-label="Tag"><option value="">'+esc(t('All tags'))+'</option>'+tags.map(v=>'<option value="'+esc(v||'__none')+'" '+((v||'__none')===f.tag?'selected':'')+'>'+esc(v||t('No tag'))+'</option>').join('')+'</select>'+
      '<select data-ff-filter="cls" aria-label="Class"><option value="">'+esc(t('All classes'))+'</option>'+classes.map(v=>'<option value="'+esc(v)+'" '+(v===f.cls?'selected':'')+'>'+esc(v)+'</option>').join('')+'</select></div>'+
      '<section><h3>'+label('Parent payment dates')+'</h3><div class="ff-promises">'+(promises.length?promises.map(p=>promiseCard(p,by[p.sid])).join(''):'<p>'+label('No payment dates submitted yet.')+'</p>')+'</div></section>'+
      '<section><h3>'+label('Calling list')+' · '+rows.length+'</h3><div class="ff-tabs">'+[['all','All with dues'],['today','Follow-up today'],['overdue','Overdue promises'],['parent','Parent dates'],['fresh','Not called in 7 days']].map(([v,l])=>'<button type="button" data-ff-list="'+v+'" aria-pressed="'+(v===f.list)+'">'+label(l)+'</button>').join('')+'</div>'+
      (s.draft?resultForm(d,s.draft):'')+
      rows.slice(0,s.limit).map(c=>{
        const call=last(c),p=prom.get(c.sid);
        return '<article class="ff-call"><div class="ff-row"><div><h4 class="ff-identity">'+esc(c.name)+' <span class="ff-badge">'+esc(c.tag||t('No tag'))+'</span></h4><p class="ff-identity">'+esc(c.cls)+(c.section?'-'+esc(c.section):'')+' · '+esc(c.father||'')+'</p></div><strong>'+money(c.due)+'</strong></div>'+
          (p?'<p>'+label('Parent')+': '+date(p.promise)+' · '+money(p.amount)+' · '+status(p)+'</p>':'')+
          (call?'<p>'+label('Last call')+': '+date(call.date)+' · '+label(call.outcome)+'</p>':'<p>'+label('Not called yet')+'</p>')+
          (c.phone?'<button type="button" class="ff-primary" data-ff-call="'+esc(c.sid)+'">'+label('Call and auto remark')+'</button>':'<p>'+label('No mobile number')+'</p>')+'</article>';
      }).join('')+(rows.length>s.limit?'<button type="button" data-ff-more>Show more ('+(rows.length-s.limit)+')</button>':'')+(!rows.length?'<p>'+label('Nothing due in this list.')+'</p>':'')+'</section>'+
      '<details><summary>'+label('Recent calls')+' · '+d.calls.length+'</summary><div class="ff-table"><table><thead><tr><th>'+label('Date')+'</th><th>'+label('Student')+'</th><th>'+label('Tag')+'</th><th>'+label('Result')+'</th><th>'+label('Note')+'</th><th>'+label('By')+'</th></tr></thead><tbody>'+
      d.calls.slice(0,100).map(c=>'<tr><td>'+date(c.date)+'</td><td class="ff-identity">'+esc((c.sids||[c.sid]).map(id=>by[id]?.name).filter(Boolean).join(', '))+'</td><td class="ff-identity">'+esc(c.tag||by[c.sid]?.tag||'')+'</td><td data-school-label>'+label(c.outcome)+(c.promise?' · '+date(c.promise):'')+(c.amount?' · '+money(c.amount):'')+'</td><td class="ff-usertext">'+autoRemark(c)+(c.note!==c.autoRemark?' '+esc(c.note||''):'')+'</td><td class="ff-identity">'+esc(c.by||'')+'</td></tr>').join('')+'</tbody></table></div></details>';
  }
  function draw(root,s){if(!root.isConnected)return;const before=root.querySelector('[data-ff-result]');if(before&&s.draft&&before.dataset.ffResult===s.draft.id)s.draftValues={id:s.draft.id,nonce:before.dataset.nonce,values:Object.fromEntries(new FormData(before))};if(root.dataset.ff==='parent')parent(root,s);else staff(root,s);const after=root.querySelector('[data-ff-result]');if(after&&s.draftValues?.id===s.draft?.id){for(const [key,v] of Object.entries(s.draftValues.values))if(after.elements[key])after.elements[key].value=v;after.dataset.nonce=s.draftValues.nonce;after.elements.promise.required=['Promised to pay','Call back later','Will visit school'].includes(after.elements.outcome.value);after.elements.amount.required=after.elements.outcome.value==='Promised to pay';}SchoolLang?.apply();}
  async function refresh(root,s){if(s.loading||s.busy)return;s.loading=true;try{s.data=await api(root.dataset.ff==='parent'?'mine':'list');s.dirty=false;draw(root,s);}catch(e){if(!s.data)root.innerHTML='<p data-ff-message role="alert">'+esc(t(e.message))+'</p><button data-ff-refresh>'+label('Try again')+'</button>';else message(root,e.message,true);}finally{s.loading=false;}}
  function mount(root){
    if(states.has(root))return;
    const s={data:null,filters:{q:'',tag:'',cls:'',list:'all'},draft:null,busy:false,loading:false,dirty:false,limit:100,attemptNonces:{}};
    states.set(root,s);
    root.addEventListener('input',e=>{if(e.target.closest('form'))s.dirty=true;});
    root.addEventListener('change',e=>{
      const filter=e.target.dataset.ffFilter;if(filter){s.filters[filter]=e.target.value;draw(root,s);}
      if(e.target.name==='outcome'){const form=e.target.form,need=['Promised to pay','Call back later','Will visit school'].includes(e.target.value);form.elements.promise.required=need;form.elements.amount.required=e.target.value==='Promised to pay';}
    });
    root.addEventListener('input',e=>{if(e.target.dataset.ffFilter==='q'){
      const value=e.target.value,pos=e.target.selectionStart;s.filters.q=value;clearTimeout(s.searchTimer);s.searchTimer=setTimeout(()=>{draw(root,s);const q=root.querySelector('[data-ff-filter="q"]');q.focus();q.setSelectionRange(pos,pos);},220);
    }});
    root.addEventListener('click',async e=>{
      const b=e.target.closest('button');if(!b||s.busy)return;
      if(b.hasAttribute('data-ff-refresh')){if(s.dirty&&!confirm(t('Discard the unsaved form and refresh?')))return;return refresh(root,s);}
      if(b.hasAttribute('data-ff-quick')){const form=b.closest('form');if(form){form.elements.promise.value=new Date(Date.parse(s.data.today+'T12:00:00Z')+Number(b.dataset.ffQuick)*86400000).toISOString().slice(0,10);s.dirty=true;}return;}
      if(b.hasAttribute('data-ff-more')){s.limit+=100;return draw(root,s);}
      if(b.dataset.ffList){s.filters.list=b.dataset.ffList;return draw(root,s);}
      if(b.dataset.ffCall){
        const sid=b.dataset.ffCall,c=s.data.children.find(x=>x.sid===sid);if(!c?.phone)return;
        if(s.dirty&&!confirm(t('Discard the unsaved form and start another call?')))return;
        s.busy=true;b.disabled=true;
        try{
          s.attemptNonces[sid] ||= nonce();const d=await api('attempt',{sid,nonce:s.attemptNonces[sid]});delete s.attemptNonces[sid];
          s.draft=d.call;s.draftValues=null;s.data.calls.unshift(d.call);s.dirty=false;draw(root,s);
          message(root,'Call started; save the result after the call.');
          root.querySelector('[data-ff-result]')?.scrollIntoView({block:'center',behavior:'smooth'});
          location.href='tel:+91'+c.phone;
        }catch(x){message(root,x.message,true);b.disabled=false;}finally{s.busy=false;}
      }
    });
    root.addEventListener('submit',async e=>{
      const form=e.target;if(!form.matches('[data-ff-promise],[data-ff-result]'))return;e.preventDefault();if(s.busy)return;
      const body=Object.fromEntries(new FormData(form));body.nonce=form.dataset.nonce;const isParent=form.hasAttribute('data-ff-promise');
      body.sid=isParent?form.dataset.ffPromise:form.dataset.sid;if(!isParent)body.id=form.dataset.ffResult;
      s.busy=true;const b=form.querySelector('[type="submit"]');b.disabled=true;
      try{
        await api(isParent?'promise':'result',body);s.draft=null;s.draftValues=null;s.dirty=false;s.data=await api(isParent?'mine':'list');draw(root,s);
        message(root,isParent?'Payment date saved.':'Call result saved.');
      }catch(x){message(root,x.message,true);b.disabled=false;}finally{s.busy=false;}
    });
    refresh(root,s);
  }
  function mountAll(){if(typeof document==='undefined'||!document)return;document.querySelectorAll('[data-ff]').forEach(mount);}
  window.SchoolFollowup={parent:()=>box('parent'),staff:()=>box('staff'),api};
  function boot(){new MutationObserver(mountAll).observe(document.body,{childList:true,subtree:true});mountAll();setInterval(()=>{if(!document.hidden)document.querySelectorAll('[data-ff]').forEach(root=>{const s=states.get(root);if(s&&!s.dirty&&!s.draft&&!root.contains(document.activeElement))refresh(root,s);});},60000);}
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',boot);else boot();
})();
