'use strict';
// Scoped owner-requested integrations; leave the existing ERP screens and fee code intact.
if(typeof VIEWS!=='undefined'){
  VIEWS.pfees=()=>head('Fees')+SchoolFollowup.parent();
  VIEWS.fcalls=()=>head('Dues Calling')+SchoolFollowup.staff();
}
if(typeof callBody==='function'){
  // Chairman App: its Calling tab has Calls · Send messages · Sent messages; the follow-up list is the Calls part.
  if(typeof callBodyCalls==='function')callBodyCalls=()=>SchoolFollowup.staff();else callBody=()=>SchoolFollowup.staff();
  const originalOverview=overview;
  overview=()=>{const calls=D.calls;D.calls=calls.filter(c=>!c.initiated);try{return originalOverview();}finally{D.calls=calls;}};
}
if(typeof callHtml==='function')callHtml=()=>SchoolFollowup.staff();
SchoolLang.apply();
