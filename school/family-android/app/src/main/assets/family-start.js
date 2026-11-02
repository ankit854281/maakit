'use strict';
(function(){
  if(window.__snFamily)return;window.__snFamily=true;
  const allowed=['parent','student'];
  let preferred='parent';try{const r=localStorage.getItem('sn_family_role');if(allowed.includes(r))preferred=r;}catch(e){}
  if(typeof loginRole!=='undefined')loginRole=preferred;
  if(typeof vLogin==='function'){
    const original=vLogin;
    vLogin=function(){
      if(!allowed.includes(loginRole))loginRole=preferred;
      try{localStorage.setItem('sn_family_role',loginRole);}catch(e){}
      if(typeof STATUS!=='undefined'&&STATUS&&(STATUS.setup||STATUS.reset))
        return '<div style="padding:24px"><h2>SNMSIS Family</h2><p>Please ask the school office to complete the school setup.</p></div>';
      const shell=document.createElement('div');shell.innerHTML=original();
      shell.querySelectorAll('.seg button').forEach(b=>{
        const role=(b.getAttribute('onclick')||'').match(/loginRole='([^']+)'/);
        if(role&&!allowed.includes(role[1]))b.remove();
      });
      shell.querySelectorAll('p').forEach(p=>{if(p.textContent==="Attendance, diaries, lessons and results for every class — on every teacher's phone.")
        p.textContent='Homework, attendance, results and fees — for students and parents.';});
      return shell.innerHTML;
    };
  }
  if(!document.getElementById('sn-family-language')){
    const bar=document.createElement('div');bar.id='sn-family-language';bar.className='school-language-bar';
    bar.style.cssText='display:flex;justify-content:space-between;align-items:center;padding:8px 12px;background:#eeedff;color:#352b90;font:13px sans-serif';
    const name=document.createElement('b');name.textContent='SNMSIS Family';bar.appendChild(name);
    if(window.SchoolLang){const holder=document.createElement('span');holder.innerHTML=SchoolLang.control();bar.appendChild(holder);}
    document.body.prepend(bar);
    const style=document.createElement('style');style.textContent='.school-language{border:1px solid #b7b2ea;border-radius:9px;padding:8px 10px;background:white;color:#352b90;font:12px sans-serif;min-height:36px;cursor:pointer}';document.head.appendChild(style);
  }
  if(typeof render==='function' && (typeof U==='undefined'||!U))render();
  if(window.SchoolLang)SchoolLang.apply();
})();
