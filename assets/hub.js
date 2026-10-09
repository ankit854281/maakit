(() => {
  'use strict';
  const hi=document.documentElement.lang.startsWith('hi');
  const text=(en,hindi)=>hi?hindi:en;
  for (const form of document.querySelectorAll('.hub-rfq')) {
    let key=null;
    form.addEventListener('input',()=>{key=null;});
    form.addEventListener('submit',async e=>{
      e.preventDefault();if (!form.reportValidity()) return;
      const button=form.querySelector('button');if (button.disabled) return;
      const status=form.querySelector('[role=status]');button.disabled=true;
      status.textContent=text('Sending your requirement…','आपकी जरूरत भेज रहे हैं…');
      try {
        if (!key) key=crypto.randomUUID();
        await MaakitApi.request('rfq',{method:'POST',key,body:{offer_id:form.dataset.offer,quantity:Number(form.elements.quantity.value),message:form.elements.message.value}});
        status.textContent=text('Sent. The supplier will see your quote request.','भेज दिया। सप्लायर को आपकी थोक भाव की माँग दिखेगी।');
      } catch (error) {
        status.textContent=error.status===401?text('Sign in to send a wholesale request.','थोक भाव पूछने के लिए अपने खाते में लॉगिन कीजिए।'):text('Could not send. Please try again.','भेज नहीं पाए। एक बार फिर कोशिश कीजिए।');
        if (error.status===401) { const a=document.createElement('a');a.href='/account.php';a.textContent=text(' Sign in',' लॉगिन कीजिए');status.append(a); }
      } finally { button.disabled=false; }
    });
  }
})();
