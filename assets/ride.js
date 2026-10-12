/* ============================================================
   सवारी / पार्सल — ride.php ka JavaScript
   Naksha (Leaflet + OpenStreetMap), GPS, jagah khojna, kiraya-andaza.
   Bina iske bhi form chalta hai — ye sirf aasaan banata hai.
   ============================================================ */
(function () {
  var C = window.RD || {}, L$ = C.L || {};
  var f = document.querySelector('[data-rd]'); if (!f) return;
  var root = document.querySelector('.rd');
  var $ = function (s, el) { return (el || f).querySelector(s); };
  var $$ = function (s, el) { return Array.prototype.slice.call((el || f).querySelectorAll(s)); };
  var geoA = $('[data-geo=a]'), geoB = $('[data-geo=b]'), pa = $('[data-pa]'), da = $('[data-da]');
  var kindIn = $('[data-kind-in]');

  // ---------- chhota sandesh ----------
  var toastEl = document.createElement('div'); toastEl.className = 'rd-toast'; toastEl.setAttribute('role', 'status'); document.body.appendChild(toastEl);
  function toast(m) { toastEl.textContent = m; toastEl.classList.add('show'); clearTimeout(toastEl._t); toastEl._t = setTimeout(function () { toastEl.classList.remove('show'); }, 2800); }
  function esc(s) { var d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }

  // ---------- sawari / parcel badalna (bina reload) ----------
  function setKind(k) {
    kindIn.value = k; root.dataset.kind = k;
    $$('[data-seg]', document).forEach(function (a) { var on = a.dataset.seg === k; a.classList.toggle('on', on); if (on) a.setAttribute('aria-current', 'page'); else a.removeAttribute('aria-current'); });
    $$('[data-only]').forEach(function (fs) { var on = fs.dataset.only === k; fs.hidden = !on; fs.disabled = !on; });
    ['h', 'l', 'go'].forEach(function (p) {
      $$('[data-' + p + '-ride]', document).forEach(function (e) { e.hidden = k !== 'ride'; });
      $$('[data-' + p + '-parcel]', document).forEach(function (e) { e.hidden = k !== 'parcel'; });
    });
    try { history.replaceState(null, '', k === 'parcel' ? '/ride.php?t=parcel' : '/ride.php'); } catch (e) {}
    syncSelf(); fare();
  }
  $$('[data-seg]', document).forEach(function (a) { a.addEventListener('click', function (e) { e.preventDefault(); setKind(a.dataset.seg); }); });

  // ---------- doori aur kiraya ----------
  function parse(g) { if (!g) return null; var p = g.split(','); return p.length === 2 ? [+p[0], +p[1]] : null; }
  function km() {
    var a = parse(geoA.value), b = parse(geoB.value); if (!a || !b) return null;
    var R = 6371, r = Math.PI / 180, dl = (b[0] - a[0]) * r, dn = (b[1] - a[1]) * r;
    var h = Math.pow(Math.sin(dl / 2), 2) + Math.cos(a[0] * r) * Math.cos(b[0] * r) * Math.pow(Math.sin(dn / 2), 2);
    return Math.max(0.5, Math.round(2 * R * Math.asin(Math.min(1, Math.sqrt(h))) * 1.3 * 10) / 10);
  }
  function price(v, d) { return Math.max(v.base, Math.ceil((v.base + d * v.km) / 5) * 5); }
  function fare() {
    var d = km(), k = kindIn.value, far = d !== null && d > C.max;
    Object.keys(C.v || {}).forEach(function (key) {
      var el = $('[data-fare="' + key + '"]'); if (!el) return;
      el.textContent = d === null ? (L$.from + ' ₹' + C.v[key].base) : '₹' + price(C.v[key], d);
    });
    $$('[data-km],[data-km-p]').forEach(function (e) { e.textContent = d === null ? '' : '· ' + d + ' ' + L$.km; });
    var vk = k === 'parcel' ? 'parcel' : (($('[name=vehicle]:checked') || {}).value || 'bike'), v = (C.v || {})[vk];
    var bl = document.querySelector('[data-bar-l]'), bf = document.querySelector('[data-bar-f]');
    if (v && bl) bl.textContent = v.name + (d !== null ? ' · ' + d + ' ' + L$.km : '');
    if (bf) bf.textContent = far ? L$.far + C.max + ' km' : (d === null || !v ? L$.after : '₹' + price(v, d) + ' ' + L$.approx);
    root.classList.toggle('rd-far', !!far);
    seats(); line();
  }
  function seats() {
    var vk = ($('[name=vehicle]:checked') || {}).value, v = (C.v || {})[vk], box = $('[data-seats]'); if (!box || !v) return;
    box.hidden = v.seats <= 1;
    $$('[name=seats]').forEach(function (r) { r.disabled = +r.value > v.seats; if (r.checked && r.disabled) $('[name=seats][value="1"]').checked = true; });
  }
  $$('[name=vehicle]').forEach(function (r) { r.addEventListener('change', fare); });

  // ---------- naksha ----------
  var map = null, mA = null, mB = null, ln = null, pinMode = false;
  function icon(cls, txt) { return window.L.divIcon({ className: 'rd-mk ' + cls, html: '<span>' + txt + '</span>', iconSize: [30, 38], iconAnchor: [15, 36] }); }
  function initMap() {
    if (!window.L || map) return;
    var el = document.getElementById('rdMap'); el.hidden = false;
    var a = parse(geoA.value) || C.center;
    map = window.L.map(el, { zoomControl: false, attributionControl: true }).setView(a, parse(geoA.value) ? 15 : 12);
    window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '© OpenStreetMap' }).addTo(map);
    map.on('click', function (e) {
      // pehle pickup na ho to pehla chhoona pickup, warna drop
      var g = e.latlng.lat.toFixed(6) + ',' + e.latlng.lng.toFixed(6);
      if (!geoA.value && !pinMode) { setA(g, true); } else { setB(g, true); }
      pinMode = false; tip(false);
    });
    marks();
    $('[data-onmap]').hidden = false;
  }
  function tip(on) { var t = document.querySelector('[data-map-tip]'); if (t) t.hidden = !on; }
  function marks() {
    if (!map) return;
    var a = parse(geoA.value), b = parse(geoB.value);
    if (a) { if (!mA) { mA = window.L.marker(a, { icon: icon('a', 'A'), draggable: true }).addTo(map); mA.on('dragend', function () { var p = mA.getLatLng(); setA(p.lat.toFixed(6) + ',' + p.lng.toFixed(6), true); }); } else mA.setLatLng(a); }
    else if (mA) { map.removeLayer(mA); mA = null; }
    if (b) { if (!mB) { mB = window.L.marker(b, { icon: icon('b', 'B'), draggable: true }).addTo(map); mB.on('dragend', function () { var p = mB.getLatLng(); setB(p.lat.toFixed(6) + ',' + p.lng.toFixed(6), true); }); } else mB.setLatLng(b); }
    else if (mB) { map.removeLayer(mB); mB = null; }
    if (a && b) map.fitBounds([a, b], { padding: [40, 40], maxZoom: 16 }); else if (a || b) map.setView(a || b, 15);
  }
  function line() {
    if (!map) return;
    var a = parse(geoA.value), b = parse(geoB.value);
    if (ln) { map.removeLayer(ln); ln = null; }
    if (a && b) ln = window.L.polyline([a, b], { color: '#111827', weight: 3, dashArray: '6 8', opacity: .8 }).addTo(map);
  }

  // ---------- jagah ka naam (OpenStreetMap) ----------
  function short(a) {
    a = a || {};
    return [a.house_number, a.road, a.neighbourhood || a.suburb || a.hamlet, a.village || a.town || a.city, a.postcode].filter(Boolean).join(', ');
  }
  function reverse(g, cb) {
    var p = parse(g); if (!p) return;
    fetch('https://nominatim.openstreetmap.org/reverse?format=jsonv2&zoom=18&accept-language=' + C.lang + '&lat=' + p[0] + '&lon=' + p[1])
      .then(function (r) { return r.json(); }).then(function (j) { cb(short(j && j.address) || (j && j.display_name) || ''); }).catch(function () { cb(''); });
  }
  function setA(g, named) {
    geoA.value = g; marks(); fare();
    if (named) reverse(g, function (n) { pa.value = n || L$.pin; pa.dataset.auto = '1'; });
  }
  function setB(g, named) {
    geoB.value = g; marks(); fare(); hideResults();
    if (named) reverse(g, function (n) { da.value = n || L$.pin; da.dataset.geoText = da.value; findBtn(); });
  }

  // ---------- GPS ----------
  var gps = $('[data-gps]');
  function locate(quiet) {
    if (!navigator.geolocation) { if (!quiet) toast(L$.nogps); return; }
    gps.classList.add('busy');
    navigator.geolocation.getCurrentPosition(function (p) {
      gps.classList.remove('busy'); gps.classList.add('done');
      var g = p.coords.latitude.toFixed(6) + ',' + p.coords.longitude.toFixed(6);
      geoA.value = g; marks(); fare();
      if (map) map.setView([p.coords.latitude, p.coords.longitude], 16);
      reverse(g, function (n) { if (!pa.value.trim() || pa.dataset.auto === '1' || quiet) { pa.value = n || L$.here; pa.dataset.auto = '1'; } });
    }, function () { gps.classList.remove('busy'); if (!quiet) toast(L$.nogps); }, { enableHighAccuracy: true, timeout: 12000, maximumAge: 60000 });
  }
  gps.addEventListener('click', function () { locate(false); });
  var lb = document.querySelector('[data-locate]'); if (lb) lb.addEventListener('click', function () { locate(false); });
  pa.addEventListener('input', function () { pa.dataset.auto = ''; });

  // ---------- drop: khojiye ----------
  var fb = $('[data-find]'), res = $('[data-results]');
  function findBtn() { fb.hidden = da.value.trim().length < 3 || da.value === da.dataset.geoText; }
  function hideResults() { res.hidden = true; res.innerHTML = ''; }
  da.addEventListener('input', function () {
    if (da.value !== da.dataset.geoText && geoB.value) { geoB.value = ''; marks(); fare(); }
    findBtn(); quick();
  });
  da.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); search(); } });
  fb.addEventListener('click', search);
  var lastQ = 0;
  function search() {
    var q = da.value.trim(); if (q.length < 3) return;
    if (Date.now() - lastQ < 1100) return; lastQ = Date.now(); // Nominatim: 1 second me ek
    res.hidden = false; res.innerHTML = '<p class="rd-res-msg">' + esc(L$.searching) + '</p>';
    var a = parse(geoA.value) || C.center, d = 0.45;
    var vb = (a[1] - d) + ',' + (a[0] + d) + ',' + (a[1] + d) + ',' + (a[0] - d);
    fetch('https://nominatim.openstreetmap.org/search?format=jsonv2&addressdetails=1&limit=5&countrycodes=in&accept-language=' + C.lang + '&viewbox=' + vb + '&q=' + encodeURIComponent(q))
      .then(function (r) { return r.json(); }).then(function (list) {
        if (!list || !list.length) { res.innerHTML = '<p class="rd-res-msg">' + esc(L$.none) + '</p>'; return; }
        res.innerHTML = '';
        list.forEach(function (it) {
          var b = document.createElement('button'); b.type = 'button';
          var main = (it.name || (it.display_name || '').split(',')[0]), sub = short(it.address) || it.display_name;
          b.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a7 7 0 0 0-7 7c0 5 7 13 7 13s7-8 7-13a7 7 0 0 0-7-7zm0 9.5A2.5 2.5 0 1 1 12 6.5a2.5 2.5 0 0 1 0 5z"/></svg><span><b>' + esc(main) + '</b><small>' + esc(sub) + '</small></span>';
          b.addEventListener('click', function () {
            da.value = main + (sub && sub.indexOf(main) !== 0 ? ', ' + sub : ''); da.dataset.geoText = da.value;
            setB((+it.lat).toFixed(6) + ',' + (+it.lon).toFixed(6), false); findBtn();
          });
          res.appendChild(b);
        });
      }).catch(function () { res.innerHTML = '<p class="rd-res-msg">' + esc(L$.none) + '</p>'; });
  }
  var om = $('[data-onmap]');
  om.addEventListener('click', function () { pinMode = true; tip(true); document.getElementById('rdMap').scrollIntoView({ behavior: 'smooth', block: 'center' }); });

  // ---------- pichhli jagahen (sirf is phone me) ----------
  var rec = []; try { rec = JSON.parse(localStorage.getItem('rd_recent') || '[]'); } catch (e) {}
  function quick() {
    var box = $('[data-quick]'); if (!box) return;
    if (!rec.length || da.value.trim()) { box.hidden = true; return; }
    box.hidden = false; box.innerHTML = '';
    rec.slice(0, 4).forEach(function (r) {
      var b = document.createElement('button'); b.type = 'button';
      b.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg><span>' + esc(r.a) + '</span>';
      b.addEventListener('click', function () { da.value = r.a; da.dataset.geoText = r.g ? r.a : ''; if (r.g) setB(r.g, false); box.hidden = true; findBtn(); });
      box.appendChild(b);
    });
  }

  // ---------- ulta ----------
  var sw = $('[data-swap]'); sw.hidden = false;
  sw.addEventListener('click', function () {
    var t = pa.value; pa.value = da.value; da.value = t;
    t = geoA.value; geoA.value = geoB.value; geoB.value = t;
    da.dataset.geoText = geoB.value ? da.value : ''; findBtn(); marks(); fare();
  });

  // ---------- parcel: main khud lunga ----------
  var self = $('[data-self]'), recv = $('[data-recv]');
  function syncSelf() {
    if (!self) return;
    var off = self.checked || kindIn.value !== 'parcel';
    recv.hidden = self.checked; $$('input', recv).forEach(function (i) { i.disabled = off; });
  }
  if (self) self.addEventListener('change', syncSelf);

  // ---------- samay ----------
  var at = $('[data-at]');
  $$('[data-when]').forEach(function (r) { r.addEventListener('change', function () { at.hidden = !(r.checked && r.value === 'later'); if (!at.hidden) at.querySelector('input').focus(); }); });

  // ---------- naam/mobile yaad (sirf is phone me) ----------
  try { $$('[data-keep]').forEach(function (i) { var v = localStorage.getItem('rd_' + i.dataset.keep); if (v && !i.value) i.value = v; }); } catch (e) {}
  $$('input[type=tel]').forEach(function (m) { m.addEventListener('input', function () { var d = m.value.replace(/\D/g, ''); if (d.length > 10 && d.indexOf('91') === 0) d = d.slice(2); m.value = d.slice(0, 10); }); });

  // ---------- bhejna ----------
  f.addEventListener('submit', function (e) {
    var bad = null;
    $$('input,textarea').forEach(function (i) {
      if (i.disabled || i.type === 'hidden' || i.closest('[hidden]')) return;
      var b = !i.checkValidity(); var w = i.closest('.rd-f, .rd-pt'); if (w) w.classList.toggle('bad', b); if (b && !bad) bad = i;
    });
    if (kindIn.value === 'parcel' && !$('[name=ptype]:checked') && !$('[name=items]').value.trim()) { bad = bad || $('[name=ptype]'); $('.rd-chips').classList.add('bad'); }
    if (root.classList.contains('rd-far')) { e.preventDefault(); toast(document.querySelector('[data-bar-f]').textContent); return; }
    if (bad) { e.preventDefault(); toast(L$.need); try { bad.focus({ preventScroll: true }); bad.scrollIntoView({ behavior: 'smooth', block: 'center' }); } catch (x) {} return; }
    try {
      $$('[data-keep]').forEach(function (i) { localStorage.setItem('rd_' + i.dataset.keep, i.value); });
      var r = { a: da.value.trim(), g: geoB.value };
      if (r.a) { rec = rec.filter(function (x) { return x.a !== r.a; }); rec.unshift(r); localStorage.setItem('rd_recent', JSON.stringify(rec.slice(0, 6))); }
    } catch (x) {}
    var go = document.querySelector('.rd-go'); setTimeout(function () { go.disabled = true; go.classList.add('busy'); }, 0);
  });
  $$('.rd-chips input').forEach(function (r) { r.addEventListener('change', function () { $('.rd-chips').classList.remove('bad'); }); });

  // ---------- shuru ----------
  syncSelf(); quick(); findBtn(); fare();
  function start() {
    initMap(); fare();
    if (!geoA.value && navigator.permissions && navigator.permissions.query) {
      navigator.permissions.query({ name: 'geolocation' }).then(function (s) { if (s.state === 'granted') locate(true); }).catch(function () {});
    }
  }
  if (window.L) start(); else window.addEventListener('load', start);
})();
