'use strict';
// Scoped owner-requested integrations; leave the existing ERP screens and fee code intact.
if(typeof VIEWS!=='undefined'){
  VIEWS.pfees=()=>head('Fees')+SchoolFollowup.parent();
  VIEWS.fcalls=()=>head('Dues Calling')+SchoolFollowup.staff();
}
if(typeof callBody==='function'){
  callBody=()=>SchoolFollowup.staff();
  const originalOverview=overview;
  overview=()=>{const calls=D.calls;D.calls=calls.filter(c=>!c.initiated);try{return originalOverview();}finally{D.calls=calls;}};
}
if(typeof callHtml==='function')callHtml=()=>SchoolFollowup.staff();
SchoolLang.apply();
