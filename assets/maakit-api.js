/* Tokens stay in memory. Every exchange requires the same-origin PHP login and CSRF. */
(() => {
  'use strict';
  const cache = new Map();
  const pending = new Map();
  function csrf() { return document.querySelector('[data-csrf]')?.dataset.csrf || ''; }
  async function token(context='customer', force=false) {
    if (!force && cache.get(context)?.until > Date.now()+30000) return cache.get(context).token;
    if (pending.has(context)) return pending.get(context);
    const promise=(async()=>{
      const body=new URLSearchParams({context});
      const response=await fetch('/api/v1/session.php',{method:'POST',credentials:'same-origin',headers:{'X-CSRF-Token':csrf()},body});
      const result=await response.json();
      if (!response.ok) { const e=new Error(result.error?.code||'SESSION_UNAVAILABLE');e.status=response.status;throw e; }
      cache.set(context,{token:result.data.access_token,until:Date.now()+result.data.expires_in*1000});
      return result.data.access_token;
    })();
    pending.set(context,promise);try { return await promise; } finally { pending.delete(context); }
  }
  async function request(action, {context='customer', method='GET', body, key, retry=true, signal}={}) {
    const headers={'Authorization':'Bearer '+await token(context)};
    if (body) headers['Content-Type']='application/json';
    if (key) headers['Idempotency-Key']=key;
    const response=await fetch('/api/v1/index.php?action='+action,{method,credentials:'same-origin',headers,body:body?JSON.stringify(body):undefined,signal});
    const result=await response.json();
    if (response.status===401&&retry) { cache.delete(context);await token(context,true);return request(action,{context,method,body,key,retry:false,signal}); }
    if (!response.ok) { const e=new Error(result.error?.code||'REQUEST_FAILED');e.status=response.status;throw e; }
    return result.data;
  }
  window.MaakitApi=Object.freeze({request,token,clear(){cache.clear();}});
})();
