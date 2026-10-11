  </div></main>
</div>

<div class="sp-toast<?= $sp_flash ? ' show' : '' ?>" id="spToast" role="status" aria-live="polite"><?= $sp_flash ? h($sp_flash) : '' ?></div>
<a class="sp-alert" id="spAlert" href="/shop.php?tab=order" hidden><?= sp_icon('bell', 18) ?><span><?= t('New order received', 'नया ऑर्डर आया है') ?></span><b><?= t('Open', 'देखिए') ?> →</b></a>

<script>
/* Dukaan panel ke apne-aap wale kaam. JS band ho to sab form purane tareeke se chalte hain. */
(function () {
  var root = document.querySelector('.sp'); if (!root) return;
  var toast = document.getElementById('spToast'), tt;
  var T = {
    saved: <?= json_encode(t('Saved', 'सेव हो गया')) ?>,
    fail:  <?= json_encode(t('Could not save — check the internet and try again.', 'सेव नहीं हुआ — इंटरनेट देखकर दोबारा कीजिए।')) ?>,
    order: <?= json_encode(t('New order', 'नया ऑर्डर')) ?>
  };
  function say(msg, bad) {
    if (!toast || !msg) return;
    toast.textContent = msg; toast.classList.toggle('bad', !!bad);
    toast.classList.add('show'); clearTimeout(tt);
    tt = setTimeout(function () { toast.classList.remove('show'); }, 2600);
  }
  if (toast && toast.classList.contains('show')) tt = setTimeout(function () { toast.classList.remove('show'); }, 3200);
  function buzz(ms) { try { navigator.vibrate && navigator.vibrate(ms || 12); } catch (e) {} }

  // Back: isi website se aaye hain to wahin wapas, warna panel ke pehle panne par
  var back = root.querySelector('[data-back]');
  if (back) back.addEventListener('click', function (e) {
    try {
      if (document.referrer && new URL(document.referrer).host === location.host && history.length > 1
          && document.referrer.indexOf('/login.php') < 0) { e.preventDefault(); history.back(); }
    } catch (x) {}
  });

  // Form ko bina panna badle bhejna. Jawab JSON na aaye to normal submit.
  function send(form, done) {
    var fd = new FormData(form); fd.set('ajax', '1');
    form.classList.add('busy'); form.classList.remove('err', 'done');
    return fetch(location.pathname, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        form.classList.remove('busy');
        if (!j || j.ok === false) { form.classList.add('err'); say((j && j.msg) || T.fail, true); return; }
        form.classList.add('done'); setTimeout(function () { form.classList.remove('done'); }, 1600);
        buzz(); done && done(j);
      })
      .catch(function () { form.classList.remove('busy'); form.classList.add('err'); say(T.fail, true); });
  }

  // 1. Khuli / band switch
  root.querySelectorAll('[data-sp-open]').forEach(function (f) {
    f.addEventListener('submit', function (e) {
      e.preventDefault();
      var btn = f.querySelector('.sp-switch'), inp = f.querySelector('[name=open]');
      send(f, function (j) {
        var on = !!j.open;
        document.querySelectorAll('.sp-switch').forEach(function (b) {
          b.classList.toggle('on', on); b.setAttribute('aria-checked', on ? 'true' : 'false');
          var s = b.querySelector('span'); if (s) s.textContent = on ? b.dataset.on : b.dataset.off;
        });
        document.querySelectorAll('[data-sp-open] [name=open]').forEach(function (i) { i.value = on ? '0' : '1'; });
        document.querySelectorAll('[data-open-seg]').forEach(function (s) { s.classList.toggle('on', s.dataset.openSeg === (on ? '1' : '0')); });
        say(j.msg);
      });
    });
  });

  root.classList.add('js');
  // chuna hua tab phone par dikhta rahe
  var on = root.querySelector('.sp-tabs a.on');
  if (on && on.scrollIntoView) { var w = on.parentNode; w.scrollLeft = on.offsetLeft - (w.clientWidth - on.offsetWidth) / 2; }
  window.spSay = say;

  function bind(scope) {
  // 2. Daam — likhte hi apne aap sev (blur / Enter / 1.2 sec ruk kar)
  scope.querySelectorAll('form[data-ajax=price]').forEach(function (f) {
    var inp = f.querySelector('input[name=price]'), last = inp.value, tm;
    function go() {
      clearTimeout(tm);
      var v = parseInt(inp.value, 10);
      if (!v || v < 1 || String(v) === last) return;
      send(f, function (j) { last = String(j.price); inp.value = j.price; });
    }
    inp.addEventListener('input', function () { clearTimeout(tm); tm = setTimeout(go, 1200); });
    inp.addEventListener('change', go);
    f.addEventListener('submit', function (e) { e.preventDefault(); go(); });
  });

  // 3. Hai / khatam switch
  scope.querySelectorAll('form[data-ajax=stock]').forEach(function (f) {
    f.addEventListener('submit', function (e) {
      e.preventDefault();
      send(f, function (j) {
        var row = f.closest('[data-row]'), on = j.stock === 'hai';
        var b = f.querySelector('.sp-switch');
        if (b) { b.classList.toggle('on', on); b.setAttribute('aria-checked', on ? 'true' : 'false');
                 var s = b.querySelector('span'); if (s) s.textContent = on ? b.dataset.on : b.dataset.off; }
        if (row) row.classList.toggle('is-out', !on);
        say(j.msg);
      });
    });
  });
  }
  bind(root);
  window.spBind = bind;

  // 4. Naye order par apne aap nazar — har 25 sec
  var naye = parseInt(root.dataset.naye || '0', 10), baseTitle = document.title.replace(/^\(\d+\)\s*/, '');
  function setCount(n) {
    document.querySelectorAll('[data-sp-badge]').forEach(function (b) { b.textContent = n; b.hidden = !n; });
    document.querySelectorAll('[data-sp-naye]').forEach(function (b) { b.textContent = n; });
    document.title = (n ? '(' + n + ') ' : '') + baseTitle;
  }
  function ding() {
    try {
      var C = window.AudioContext || window.webkitAudioContext; if (!C) return;
      var c = new C(), o = c.createOscillator(), g = c.createGain();
      o.type = 'sine'; o.frequency.setValueAtTime(880, c.currentTime); o.frequency.setValueAtTime(1320, c.currentTime + .15);
      g.gain.setValueAtTime(.0001, c.currentTime); g.gain.exponentialRampToValueAtTime(.25, c.currentTime + .02);
      g.gain.exponentialRampToValueAtTime(.0001, c.currentTime + .5);
      o.connect(g); g.connect(c.destination); o.start(); o.stop(c.currentTime + .55);
    } catch (e) {}
  }
  setCount(naye);
  function poll() {
    if (document.hidden) return;
    fetch('/shop.php?poll=1', { credentials: 'same-origin', cache: 'no-store' })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        var n = j.naye | 0;
        if (n > naye) {
          ding(); buzz([80, 60, 80]);
          var a = document.getElementById('spAlert'); if (a) { a.hidden = false; a.classList.add('show'); }
          // order wale panne par hain aur kuch likh nahi rahe — seedha taaza kar do
          if (root.dataset.tab === 'order' && !document.querySelector('.sp-main input:focus, .sp-main textarea:focus')) location.reload();
        }
        naye = n; setCount(n);
      }).catch(function () {});
  }
  setInterval(poll, 25000);
  document.addEventListener('visibilitychange', function () { if (!document.hidden) poll(); });

  // 5. Bhejte waqt button par "ruko" — do baar dabane se bachav
  root.querySelectorAll('form:not([data-ajax]):not([data-sp-open])').forEach(function (f) {
    f.addEventListener('submit', function (ev) {
      var b = ev.submitter || f.querySelector('button:not([type=button])');
      if (b) setTimeout(function () { if (!ev.defaultPrevented) { b.disabled = true; b.classList.add('busy'); } }, 0);
    });
  });
})();
</script>
