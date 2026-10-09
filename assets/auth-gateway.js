/* Existing PHP login verifies credentials. JWTs stay in memory and HttpOnly cookies. */
(() => {
  'use strict';
  const root=document.querySelector('[data-auth-context], [data-auth-role]');
  if (!root) return;
  const hindi=document.documentElement.lang.startsWith('hi');
  const form=root.querySelector('[data-role-login]');
  if (form) form.addEventListener('submit', async event => {
    event.preventDefault();
    const button=form.querySelector('button');const status=form.querySelector('[data-login-status]');
    button.disabled=true;
    try {
      const login=await fetch(form.action,{method:'POST',credentials:'same-origin',body:new FormData(form)});
      if (!login.ok || !login.redirected) throw new Error('LOGIN_FAILED');
      const role=root.dataset.authRole;
      MaakitApi.clear();
      let context=role==='customer'?'customer':(role==='vendor'?'shop':'staff');
      try { await MaakitApi.token(context,true); }
      catch (error) { if(role!=='vendor' || error.status!==401) throw error;context='staff';await MaakitApi.token(context,true); }
      const destination=role==='customer'?'/account.php':role==='admin'?'/admin/':role==='vendor'&&context==='shop'?'/shop.php':'/public/partner-dashboard.php';
      window.location.assign(role==='rider' && new URL(login.url).pathname.startsWith('/delivery')?'/delivery/':destination);
    } catch(error) {
      status.textContent=hindi?'लॉगिन या सुरक्षित कनेक्शन पूरा नहीं हुआ। जानकारी और मंजूरी की स्थिति जाँचकर फिर कोशिश करें।':'Login or secure connection failed. Check credentials and approval status, then retry.';
    } finally { button.disabled=false; }
  });
  if (!root.dataset.authContext) return;
  const status=root.querySelector('[data-auth-status]');
  MaakitApi.token(root.dataset.authContext).then(() => {
    if(status) status.textContent=hindi?'सुरक्षित लॉगिन जुड़ गया है।':'Secure session connected.';
  }).catch(() => {
    if(status) status.textContent=hindi?'सुरक्षित API अभी नहीं जुड़ सकी। थोड़ी देर बाद फिर कोशिश करें।':'Secure API connection unavailable. Try again shortly.';
  });
})();
