// Shared by Teacher and ERP entry points. Challenge secrets stay in memory.
window.schoolCompleteOtp = function (challenge, verify, resend) {
  if (!challenge.otpRequired) return Promise.resolve(challenge);
  return new Promise((resolve, reject) => {
    const dialog = document.createElement('dialog');
    dialog.style.cssText = 'width:min(360px,90vw);border:0;border-radius:20px;padding:24px;font:inherit';
    dialog.innerHTML = '<form><h2>Mobile OTP</h2><p data-destination></p>' +
      '<label>6-digit OTP / 6 ankon ka code<input class="inp" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required></label>' +
      '<p data-error role="alert"></p><button class="btn" type="submit">Verify / Login karein</button> ' +
      '<button class="btn" type="button" data-resend>Resend OTP</button> ' +
      '<button class="btn" type="button" data-cancel>Cancel</button></form>';
    const form = dialog.querySelector('form');
    const error = dialog.querySelector('[data-error]');
    const input = form.elements.code;
    let pending = false;
    let closed = false;
    function destination() {
      dialog.querySelector('[data-destination]').textContent =
        'Code sent by ' + (challenge.channel === 'whatsapp' ? 'WhatsApp' : 'SMS') +
        ' to ' + challenge.mobile + '. Valid for 5 minutes. Apna OTP kisi ko na dein.';
    }
    function busy(value) {
      pending = value;
      form.querySelectorAll('button').forEach(button => { button.disabled = value; });
    }
    function finish(result) {
      closed = true;
      dialog.close(); dialog.remove();
      if (result) resolve(result); else reject(new Error('Login cancelled.'));
    }
    form.onsubmit = async event => {
      event.preventDefault();
      if (pending) return;
      busy(true); error.textContent = '';
      try { finish(await verify({ tid: challenge.tid, challenge: challenge.challenge, binding: challenge.binding, code: input.value.trim() })); }
      catch (e) { error.textContent = e.message; input.value = ''; input.focus(); }
      finally { if (!closed) busy(false); }
    };
    dialog.querySelector('[data-resend]').onclick = async () => {
      if (pending) return;
      busy(true); error.textContent = '';
      try { const next = await resend(); if (!next.otpRequired) throw new Error('Please cancel and start login again.'); challenge = next; input.value = ''; destination(); }
      catch (e) { error.textContent = e.message; }
      finally { busy(false); }
    };
    dialog.querySelector('[data-cancel]').onclick = () => finish(null);
    dialog.oncancel = event => { event.preventDefault(); if (!pending) finish(null); };
    document.body.appendChild(dialog); destination(); dialog.showModal(); input.focus();
  });
};
