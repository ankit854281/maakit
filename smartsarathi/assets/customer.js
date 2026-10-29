/* Smart Sarathi — customer single-page app (ghar.php).
   Views: home · book (map + sheet) · track · mine. No page reloads.
   Fare/km are always computed on the server (book-api.php). */
(function () {
  'use strict';
  var C = JSON.parse(document.getElementById('ca-config').textContent);
  var L_ = C.L, S = C.services;
  var CENTER = [25.3956, 82.5686]; // Bhadohi
  var GROUP = { bike: ['bike', 'auto', 'erick', 'cab'], auto: ['auto', 'bike', 'erick', 'cab'], erick: ['erick', 'auto', 'bike', 'cab'],
    cab: ['cab', 'auto', 'bike'], parcel: ['parcel'], loader: ['loader'], saath: ['saath'], care: ['care'], seva: ['seva'] };
  var DELIVERY = { parcel: 1, seva: 1 }, LATER = { saath: 1, care: 1 };
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
  var store = { get: function (k) { try { return localStorage.getItem(k); } catch (e) { return null; } }, set: function (k, v) { try { localStorage.setItem(k, v); } catch (e) {} } };

  var st = { svc: 'bike', pick: null, drop: null, field: 'pick', quote: null, choice: null, when: 'now', self: true, seats: 1, busy: false };
  var map, tmap, mk = {}, tmk = {}, poll = null;

  /* ---------- api ---------- */
  function api(a, data) {
    var opt = { credentials: 'same-origin', headers: { 'Accept': 'application/json' } };
    var url = C.api + '?a=' + encodeURIComponent(a);
    if (data) {
      var fd = new FormData(); fd.append('a', a); fd.append('csrf', C.csrf);
      Object.keys(data).forEach(function (k) { if (data[k] !== null && data[k] !== undefined) fd.append(k, data[k]); });
      opt.method = 'POST'; opt.body = fd;
    } else if (arguments[2]) { url += '&' + arguments[2]; }
    return fetch(url, opt).then(function (r) { return r.json().catch(function () { return { ok: false, error: L_.err }; }); })
      .then(function (j) { if (j && j.reload) location.reload(); return j; })
      .catch(function () { return { ok: false, error: L_.err }; });
  }

  /* ---------- views ---------- */
  function show(v, push) {
    $$('.ca-view').forEach(function (el) { el.hidden = el.getAttribute('data-view') !== v; });
    $$('#nav [data-go]').forEach(function (b) { if (b.getAttribute('data-go') === v) b.setAttribute('aria-current', 'page'); else b.removeAttribute('aria-current'); });
    document.body.classList.toggle('mapon', v === 'book' || v === 'track');
    if (v !== 'track' && poll) { clearInterval(poll); poll = null; }
    if (push !== false) history.pushState({ v: v, no: st.trackNo || null }, '', v === 'home' ? location.pathname : '#' + v + (v === 'track' && st.trackNo ? '/' + st.trackNo : ''));
    window.scrollTo(0, 0);
  }
  window.addEventListener('popstate', function (e) {
    var s = e.state || { v: 'home' };
    if (s.v === 'track' && s.no) { openTrack(s.no, false); return; }
    show(s.v === 'book' || s.v === 'mine' ? s.v : 'home', false);
    if (s.v === 'book' && map) setTimeout(function () { map.invalidateSize(); }, 50);
  });

  /* ---------- home ---------- */
  $$('[data-open]').forEach(function (b) { b.addEventListener('click', function () { openBook(b.getAttribute('data-open')); }); });
  $$('[data-go]').forEach(function (b) { b.addEventListener('click', function () {
    var v = b.getAttribute('data-go');
    if (v === 'mine') { openMine(); return; }
    show(v);
  }); });

  /* ---------- book ---------- */
  function openBook(svc, noGps) {
    st.svc = svc; st.choice = GROUP[svc][0]; st.quote = null; st.when = LATER[svc] ? 'later' : 'now';
    $('#b-title').textContent = S[svc] ? S[svc].name : L_.where;
    show('book');
    initMap();
    if (st.pick && !mk.pick && map) { var pg = st.pick.geo.split(','); setPlace('pick', +pg[0], +pg[1], st.pick.addr); }
    setField(st.pick ? 'drop' : 'pick', true);
    if (st.pick && st.drop) pinHide();
    if (!st.pick && !noGps) gps(true);
    render();
  }
  function initMap() {
    if (!window.L) { $('#hint').textContent = L_.err; return; }
    if (map) { setTimeout(function () { map.invalidateSize(); }, 60); return; }
    map = L.map('map', { zoomControl: false, attributionControl: false }).setView(CENTER, 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(map);
    map.on('click', function (e) { setPlace(st.field, e.latlng.lat, e.latlng.lng, null); });
    // v3.2 beech ka pin: sirf haath se khiskane par jagah lagti hai (fitBounds/setView par nahi)
    var dragged = false;
    map.on('dragstart', function () { dragged = true; if (pinOn) $('#cpin').classList.add('lift'); });
    map.on('moveend', function () {
      if (!dragged) return; dragged = false;
      $('#cpin').classList.remove('lift');
      if (!pinOn) return;
      var c = map.getCenter();
      setPlace(st.field, c.lat, c.lng, null, true);
    });
    setTimeout(function () { map.invalidateSize(); }, 60);
  }

  /* ---------- v3.2: beech ka pin (Uber/Rapido jaisa) ---------- */
  var pinOn = false;
  function markVis(f, on) { if (mk[f]) mk[f].setStyle(on ? { opacity: 1, fillOpacity: 1 } : { opacity: 0, fillOpacity: 0 }); }
  function pinShow(f) {
    var p = $('#cpin'); if (!p || !map) return;
    pinOn = true; p.hidden = false; p.className = 'ca-cpin ' + (f === 'pick' ? 'p' : 'd');
    $('#cpin-l').textContent = f === 'pick' ? L_.setPick : L_.setDrop;
    markVis('pick', f !== 'pick'); markVis('drop', f !== 'drop');
  }
  function pinHide() {
    var p = $('#cpin'); pinOn = false; if (p) p.hidden = true;
    markVis('pick', true); markVis('drop', true);
  }
  function setField(f, pin) {
    st.field = f;
    $$('.ca-place').forEach(function (p) { p.classList.toggle('on', p.getAttribute('data-field') === f); });
    $('#hint').textContent = L_.dragMap + ' / ' + L_.tapMap + ' — ' + (f === 'pick' ? L_.pickup : L_.drop);
    if (pin && map) {
      if (st[f]) { var g = st[f].geo.split(','); map.setView([+g[0], +g[1]], Math.max(map.getZoom(), 15)); }
      pinShow(f);
    }
  }
  $$('.ca-place input').forEach(function (inp) {
    var f = inp.closest('.ca-place').getAttribute('data-field');
    inp.addEventListener('focus', function () { setField(f, true); });
    inp.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); search(f); } });
  });
  $('[data-search]').addEventListener('click', function () { search('drop'); });
  $('[data-gps]').addEventListener('click', function () { gps(false); });

  function gps(quiet) {
    if (!navigator.geolocation) { if (!quiet) $('#hint').textContent = L_.gpsFail; return; }
    navigator.geolocation.getCurrentPosition(function (p) {
      setPlace('pick', p.coords.latitude, p.coords.longitude, L_.myLoc);
    }, function () { if (!quiet) $('#hint').textContent = L_.gpsFail; }, { enableHighAccuracy: true, timeout: 12000, maximumAge: 30000 });
  }
  function search(f) {
    var q = $('#' + f + '-q').value.trim();
    if (q.length < 2) return;
    setField(f);
    var ul = $('#results'); ul.hidden = false; ul.innerHTML = '<li><button type="button" disabled>' + esc(L_.searching) + '</button></li>';
    var url = 'https://nominatim.openstreetmap.org/search?format=jsonv2&limit=5&countrycodes=in&accept-language=' + C.lang +
      '&viewbox=82.2,25.1,83.2,25.6&q=' + encodeURIComponent(q);
    fetch(url).then(function (r) { return r.json(); }).then(function (rows) {
      if (!rows || !rows.length) { ul.innerHTML = '<li><button type="button" disabled>' + esc(L_.noResult) + '</button></li>'; return; }
      ul.innerHTML = rows.map(function (r, i) { return '<li><button type="button" data-i="' + i + '">' + esc(r.display_name) + '</button></li>'; }).join('');
      $$('button[data-i]', ul).forEach(function (b) { b.addEventListener('click', function () {
        var r = rows[+b.getAttribute('data-i')];
        ul.hidden = true;
        setPlace(f, +r.lat, +r.lon, r.display_name.split(',').slice(0, 3).join(','));
      }); });
    }).catch(function () { ul.innerHTML = '<li><button type="button" disabled>' + esc(L_.noResult) + '</button></li>'; });
  }
  function setPlace(f, lat, lng, label, keepView) {
    var geo = lat.toFixed(6) + ',' + lng.toFixed(6);
    st[f] = { geo: geo, addr: label || geo };
    var inp = $('#' + f + '-q'); inp.value = label || '…';
    if (mk[f]) map.removeLayer(mk[f]);
    mk[f] = L.circleMarker([lat, lng], { radius: 10, color: '#0B1F3A', weight: 3, fillColor: f === 'pick' ? '#10C98F' : '#0B1F3A', fillOpacity: 1 }).addTo(map);
    if (pinOn && f === st.field) markVis(f, false);
    if (!label || label === L_.myLoc) {
      fetch('https://nominatim.openstreetmap.org/reverse?format=jsonv2&zoom=17&accept-language=' + C.lang + '&lat=' + lat + '&lon=' + lng)
        .then(function (r) { return r.json(); }).then(function (j) {
          if (j && j.display_name && st[f] && st[f].geo === geo) {
            st[f].addr = j.display_name.split(',').slice(0, 3).join(',');
            inp.value = st[f].addr;
          }
        }).catch(function () {});
    }
    if (keepView) { if (st.pick && st.drop) getQuote(); return; }
    if (f === 'pick' && !st.drop) setField('drop');
    if (st.pick && st.drop) {
      pinHide();
      map.fitBounds(L.latLngBounds([mk.pick.getLatLng(), mk.drop.getLatLng()]), { padding: [40, 40] });
      getQuote();
    } else { map.setView([lat, lng], 15); if (pinOn) pinShow(st.field); }
  }

  /* ---------- v3.2: pickup <-> drop badliye ---------- */
  $('#swap').addEventListener('click', function () {
    if (!map) return;
    var a = st.pick, b = st.drop;
    ['pick', 'drop'].forEach(function (f) { if (mk[f]) { map.removeLayer(mk[f]); mk[f] = null; } st[f] = null; $('#' + f + '-q').value = ''; });
    st.quote = null; pinHide();
    if (b) { var g = b.geo.split(','); setPlace('pick', +g[0], +g[1], b.addr); }
    if (a) { var h = a.geo.split(','); setPlace('drop', +h[0], +h[1], a.addr); }
    if (!a || !b) { setField(st.pick ? 'drop' : 'pick', true); render(); }
  });
  function getQuote() {
    $('#berr').textContent = '';
    api('quote', { pick_geo: st.pick.geo, drop_geo: st.drop.geo }).then(function (j) {
      if (!j.ok) { st.quote = null; $('#berr').textContent = j.error || L_.err; render(); return; }
      st.quote = j; render();
    });
  }

  function render() {
    var box = $('#opts');
    if (!st.quote) { box.hidden = !$('#berr').textContent; $('#optlist').innerHTML = ''; $('#bookbtn').hidden = true; if (!st.pick || !st.drop) $('#hint').textContent = L_.setBoth; return; }
    box.hidden = false; $('#bookbtn').hidden = false;
    $('#route').textContent = st.quote.km + ' ' + L_.km + ' · ~' + st.quote.eta + ' ' + L_.min;
    var keys = GROUP[st.svc];
    $('#optlist').innerHTML = keys.map(function (k) {
      var q = st.quote.fares[k]; if (!q) return '';
      return '<button type="button" class="ca-opt" role="radio" aria-checked="' + (k === st.choice) + '" data-k="' + k + '"><span><b>' + esc(q.name) + '</b><small>' + esc(q.tag) +
        '</small></span><span class="f">₹' + q.fare + (k === 'saath' ? '<small>/' + esc(L_.seats) + '</small>' : '') + '</span></button>';
    }).join('');
    $$('.ca-opt').forEach(function (b) { b.addEventListener('click', function () { st.choice = b.getAttribute('data-k'); render(); }); });
    renderExtra();
    var fare = st.quote.fares[st.choice].fare * (st.choice === 'saath' ? st.seats : 1);
    $('#bookbtn').textContent = (C.loggedIn ? L_.book : L_.loginBook) + ' · ₹' + fare;
    $('#payline').textContent = L_.pay + ((LATER[st.choice] || st.when === 'later') ? ' · ' + L_.officeAssign : '');
  }
  function renderExtra() {
    var x = $('#extra'), k = st.choice, h = '';
    var name = store.get('sr_name') || '';
    h += '<div class="ca-seg" role="group"><button type="button" data-when="now" aria-pressed="' + (st.when === 'now') + '">' + esc(L_.now) + '</button><button type="button" data-when="later" aria-pressed="' + (st.when === 'later') + '">' + esc(L_.later) + '</button></div>';
    if (st.when === 'later') h += '<label>' + esc(L_.when) + '<input type="datetime-local" id="x-at" value="' + esc(st.at || '') + '"></label>';
    h += '<label>' + esc(L_.name) + '<input id="x-name" autocomplete="name" maxlength="80" value="' + esc(name) + '"></label>';
    if (k === 'saath') h += '<label>' + esc(L_.seats) + '<select id="x-seats">' + [1, 2, 3, 4, 5, 6].map(function (n) { return '<option' + (n === st.seats ? ' selected' : '') + '>' + n + '</option>'; }).join('') + '</select></label>';
    if (DELIVERY[k]) {
      h += '<div class="ca-seg" role="group"><button type="button" data-self="1" aria-pressed="' + st.self + '">' + esc(L_.self) + '</button><button type="button" data-self="0" aria-pressed="' + !st.self + '">' + esc(L_.other) + '</button></div>';
      if (!st.self) h += '<label>' + esc(L_.rName) + '<input id="x-rname" maxlength="80" value="' + esc(st.rname || '') + '"></label><label>' + esc(L_.rMob) + '<input id="x-rmob" inputmode="numeric" maxlength="10" value="' + esc(st.rmob || '') + '"></label>';
      h += '<label>' + esc(L_.items) + '<input id="x-items" maxlength="300" value="' + esc(st.items || '') + '"></label>';
    }
    h += '<label>' + esc(L_.note) + '<input id="x-note" maxlength="300" value="' + esc(st.note || '') + '"></label>';
    h += '<div class="ca-clues"><small>' + esc(L_.clue) + '</small>' + (L_.clues || []).map(function (c, i) { return '<button type="button" class="ca-clue" data-clue="' + i + '">' + esc(c) + '</button>'; }).join('') + '</div>';
    x.innerHTML = h;
    $$('[data-clue]', x).forEach(function (b) { b.addEventListener('click', function () {
      keep();
      var c = L_.clues[+b.getAttribute('data-clue')], n = st.note || '';
      if (n.indexOf(c) === -1) st.note = (n ? n + ', ' : '') + c;
      st.note = st.note.slice(0, 300); render();
    }); });
    $$('[data-when]', x).forEach(function (b) { b.addEventListener('click', function () { keep(); st.when = b.getAttribute('data-when'); render(); }); });
    $$('[data-self]', x).forEach(function (b) { b.addEventListener('click', function () { keep(); st.self = b.getAttribute('data-self') === '1'; render(); }); });
    var se = $('#x-seats', x); if (se) se.addEventListener('change', function () { keep(); st.seats = +se.value; render(); });
  }
  function keep() {
    var v = function (id) { var e = $('#' + id); return e ? e.value.trim() : undefined; };
    if (v('x-name') !== undefined) store.set('sr_name', v('x-name'));
    ['at', 'rname', 'rmob', 'items', 'note'].forEach(function (k) { var val = v('x-' + k); if (val !== undefined) st[k] = val; });
  }

  $('#bookbtn').addEventListener('click', function () {
    if (st.busy || !st.quote) return;
    keep();
    var data = { service: st.choice, name: store.get('sr_name') || '', pick_address: st.pick.addr, pick_geo: st.pick.geo,
      drop_address: st.drop.addr, drop_geo: st.drop.geo, when: st.when, at: st.when === 'later' ? (st.at || '') : '',
      self: st.self ? '1' : '0', drop_name: st.rname || '', drop_mobile: st.rmob || '', items: st.items || '', note: st.note || '', seats: st.seats };
    if (!C.loggedIn) {
      try { sessionStorage.setItem('sr_draft', JSON.stringify({ svc: st.svc, choice: st.choice, pick: st.pick, drop: st.drop, when: st.when, at: st.at, self: st.self, seats: st.seats, rname: st.rname, rmob: st.rmob, items: st.items, note: st.note })); } catch (e) {}
      location.href = C.login;
      return;
    }
    st.busy = true; var btn = $('#bookbtn'); btn.disabled = true; btn.textContent = L_.booking; $('#berr').textContent = '';
    api('book', data).then(function (j) {
      st.busy = false; btn.disabled = false;
      if (j.login) { C.loggedIn = false; render(); return; }
      if (!j.ok) { $('#berr').textContent = j.error || L_.err; render(); waOffer(data); return; }
      try { sessionStorage.removeItem('sr_draft'); } catch (e) {}
      addRecent(st.drop);
      st.pick = st.drop = null; st.quote = null;
      ['pick', 'drop'].forEach(function (f) { if (mk[f]) { map.removeLayer(mk[f]); mk[f] = null; } $('#' + f + '-q').value = ''; });
      openTrack(j.job_no);
    });
  });

  // v3.2: booking fail ho to WhatsApp par wahi jaankari (dafter ke number par) — sirf tab, jab app se nahi hui
  function waOffer(d) {
    var num = String(C.phone || '').replace(/\D/g, ''); if (!num) return;
    var q = st.quote && st.quote.fares[d.service];
    var txt = L_.waHead + '\n' + (q ? q.name : d.service) + (d.when === 'later' && d.at ? ' · ' + d.at : '') +
      '\n' + L_.pickup + ': ' + d.pick_address + ' (https://maps.google.com/?q=' + d.pick_geo + ')' +
      '\n' + L_.drop + ': ' + d.drop_address + ' (https://maps.google.com/?q=' + d.drop_geo + ')' +
      (d.note ? '\n' + d.note : '') + (q ? '\n' + L_.fare + ': ₹' + q.fare : '');
    var a = document.createElement('a');
    a.className = 'ca-wa'; a.target = '_blank'; a.rel = 'noopener';
    a.href = 'https://wa.me/' + num + '?text=' + encodeURIComponent(txt);
    a.textContent = L_.waTry;
    var e = $('#berr'); e.appendChild(document.createElement('br')); e.appendChild(a);
  }

  /* ---------- track ---------- */
  function openTrack(no, push) {
    st.trackNo = no;
    show('track', push);
    if (!window.L) { $('#tsheet').innerHTML = '<p class="ca-err">' + esc(L_.err) + '</p>'; }
    else if (!tmap) {
      tmap = L.map('tmap', { zoomControl: false, attributionControl: false }).setView(CENTER, 13);
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(tmap);
    }
    if (tmap) setTimeout(function () { tmap.invalidateSize(); }, 60);
    $('#tsheet').innerHTML = '<div class="ca-grab"></div><p class="ca-sub">' + esc(L_.searching) + '</p>';
    loadTrack(true);
    if (poll) clearInterval(poll);
    poll = setInterval(function () { if (!document.hidden) loadTrack(false); }, 8000);
  }
  function geoLL(g) { if (!g) return null; var p = String(g).split(','); return p.length === 2 ? [+p[0], +p[1]] : null; }
  function loadTrack(fit) {
    var no = st.trackNo;
    api('status', null, 'no=' + encodeURIComponent(no)).then(function (j) {
      if (no !== st.trackNo) return;
      if (!j.ok) { $('#tsheet').innerHTML = '<div class="ca-grab"></div><p class="ca-err">' + esc(j.error || L_.err) + '</p>'; return; }
      var b = j.job, n = { 'new': 1, assigned: 2, picked: 3, delivered: 4, cancelled: 0 }[b.status] || 1;
      var pts = [];
      if (tmap) [['p', b.pick_geo, '#10C98F'], ['d', b.drop_geo, '#0B1F3A']].forEach(function (x) {
        var ll = geoLL(x[1]); if (!ll) return; pts.push(ll);
        if (tmk[x[0]]) tmk[x[0]].setLatLng(ll); else tmk[x[0]] = L.circleMarker(ll, { radius: 9, color: '#0B1F3A', weight: 3, fillColor: x[2], fillOpacity: 1 }).addTo(tmap);
      });
      if (tmap && b.rider_at) {
        if (tmk.r) tmk.r.setLatLng(b.rider_at); else tmk.r = L.circleMarker(b.rider_at, { radius: 12, color: '#fff', weight: 4, fillColor: '#10C98F', fillOpacity: 1 }).addTo(tmap);
        pts.push(b.rider_at);
      } else if (tmap && tmk.r) { tmap.removeLayer(tmk.r); tmk.r = null; }
      if (tmap && fit && pts.length) tmap.fitBounds(L.latLngBounds(pts), { padding: [40, 40], maxZoom: 15 });
      var live = b.status === 'assigned' || b.status === 'picked';
      var h = '<div class="ca-grab"></div><p class="ca-status">' + (live || b.status === 'new' ? '<i></i>' : '') + esc(L_['st_' + b.status] || b.status) + '</p>';
      h += '<p class="ca-sub">' + esc(b.job_no) + ' · ' + esc(b.drop || '') + '</p>';
      if (b.status !== 'cancelled') h += '<div class="ca-steps">' + [1, 2, 3, 4].map(function (i) { return '<span class="' + (i <= n ? 'on' : '') + '"></span>'; }).join('') + '</div>';
      if (b.status === 'new') h += '<p class="ca-sub">' + esc(L_.officeAssign) + '</p>';
      if (b.rider) {
        h += '<div class="ca-rider"><span class="av">' + esc((b.rider.name || '?').charAt(0)) + '</span><span><b>' + esc(b.rider.name) + '</b><small>' + esc(b.rider.vehicle || '') + '</small></span>' +
          (b.rider.number ? '<span class="ca-plate">' + esc(b.rider.number) + '</span>' : '') + '</div>';
        if (live && !b.rider_at) h += '<p class="ca-sub">' + esc(L_.liveOff) + '</p>';
      }
      if (b.start_otp && (b.status === 'new' || b.status === 'assigned')) h += '<div class="ca-otp"><span>' + esc(L_.startOtp) + '</span><b>' + esc(b.start_otp) + '</b></div>';
      if (b.end_otp && b.status === 'picked') h += '<div class="ca-otp"><span>' + esc(L_.endOtp) + '</span><b>' + esc(b.end_otp) + '</b></div>';
      h += '<div class="ca-sum"><span>' + esc(L_.fare) + '</span><b>₹' + b.fare + '</b></div>';
      h += '<div class="ca-acts">' + (b.rider ? '<a class="call" href="tel:+91' + esc(b.rider.mobile) + '">' + esc(L_.call) + '</a>' : '<a class="call" href="tel:' + esc(C.phone) + '">' + esc(L_.call) + '</a>') +
        '<button type="button" id="t-share">' + esc(L_.share) + '</button></div>';
      if (b.status === 'new' || b.status === 'assigned') h += '<button type="button" class="ca-cta ghost" id="t-cancel">' + esc(L_.cancel) + '</button>';
      $('#tsheet').innerHTML = h;
      var sh = $('#t-share'); if (sh) sh.addEventListener('click', function () {
        var txt = 'Smart Sarathi ' + b.job_no + ' — ' + (L_['st_' + b.status] || '') + (b.rider ? ' · ' + b.rider.name + ' ' + (b.rider.number || '') : '');
        if (navigator.share) navigator.share({ text: txt }).catch(function () {}); else window.open('https://wa.me/?text=' + encodeURIComponent(txt), '_blank', 'noopener');
      });
      var cb = $('#t-cancel'); if (cb) cb.addEventListener('click', function () {
        if (!confirm(L_.cancelQ)) return;
        api('cancel', { no: b.job_no }).then(function (r) { if (!r.ok) alert(r.error || L_.err); loadTrack(false); });
      });
      if (b.status === 'delivered' || b.status === 'cancelled') { if (poll) { clearInterval(poll); poll = null; } }
    });
  }

  /* ---------- mine ---------- */
  function openMine() {
    show('mine');
    var box = $('#minelist');
    if (!C.loggedIn) { box.innerHTML = '<p class="ca-empty"><a class="ca-cta" style="display:grid;place-items:center" href="' + esc(C.login) + '">' + esc(L_.loginOnly) + '</a></p>'; return; }
    box.innerHTML = '<p class="ca-empty">' + esc(L_.searching) + '</p>';
    api('mine', null).then(function (j) {
      if (!j.ok || !j.jobs.length) { box.innerHTML = '<p class="ca-empty">' + esc(j.ok ? L_.none : (j.error || L_.err)) + '</p>'; return; }
      box.innerHTML = j.jobs.map(function (x) {
        var live = x.status === 'new' || x.status === 'assigned' || x.status === 'picked';
        return '<button type="button" class="ca-item" data-no="' + esc(x.job_no) + '"><span><b>' + esc(x.drop) + '</b><small>' + esc(x.job_no) + ' · ' + esc(x.when) + ' · ₹' + x.fare + '</small></span><span class="ca-badge' + (live ? ' live' : '') + '">' + esc(L_['st_' + x.status] || x.status) + '</span></button>';
      }).join('');
      $$('[data-no]', box).forEach(function (b) { b.addEventListener('click', function () { openTrack(b.getAttribute('data-no')); }); });
    });
  }


  /* ---------- v3.1: install the app (PWA) ---------- */
  (function () {
    var btn = $('#install'), help = $('#install-help'); if (!btn) return;
    var standalone = (window.matchMedia && matchMedia('(display-mode: standalone)').matches) || navigator.standalone;
    if (standalone) return;
    var ios = /iphone|ipad|ipod/i.test(navigator.userAgent), deferred = null;
    btn.hidden = false;
    window.addEventListener('beforeinstallprompt', function (e) { e.preventDefault(); deferred = e; btn.hidden = false; });
    window.addEventListener('appinstalled', function () { btn.hidden = true; help.hidden = true; });
    btn.addEventListener('click', function () {
      if (deferred) {
        deferred.prompt();
        deferred.userChoice.then(function (r) { if (r && r.outcome === 'accepted') btn.hidden = true; deferred = null; });
        return;
      }
      $$('#install-help ol').forEach(function (o) { o.hidden = (o.getAttribute('data-os') === 'ios') !== ios; });
      help.hidden = false; help.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
    $('#install-x').addEventListener('click', function () { help.hidden = true; });
  })();

  /* ---------- v3: welcome, location card, mic, recent places ---------- */
  var welcome = $('#welcome');
  if (welcome && !$('.ob-lang') && !C.loggedIn && !store.get('sr_welcome')) welcome.hidden = false;
  $$('[data-seen]').forEach(function (b) { b.addEventListener('click', function () { store.set('sr_welcome', '1'); if (b.hasAttribute('data-close')) welcome.hidden = true; }); });

  var hmap = null;
  function hereShow(lat, lng) {
    var box = $('#here'); box.classList.add('on'); $('#here-pill').hidden = false;
    if (window.L) {
      if (!hmap) {
        hmap = L.map('hmap', { zoomControl: false, attributionControl: false, dragging: false, scrollWheelZoom: false, doubleClickZoom: false, touchZoom: false, keyboard: false, tap: false });
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(hmap);
      }
      hmap.setView([lat, lng], 16);
      L.circleMarker([lat, lng], { radius: 9, color: '#fff', weight: 3, fillColor: '#10C98F', fillOpacity: 1 }).addTo(hmap);
      setTimeout(function () { hmap.invalidateSize(); }, 50);
    }
    $('#here-addr').textContent = L_.here;
    var geo = lat.toFixed(6) + ',' + lng.toFixed(6);
    if (!st.pick) st.pick = { geo: geo, addr: L_.myLoc };
    fetch('https://nominatim.openstreetmap.org/reverse?format=jsonv2&zoom=17&accept-language=' + C.lang + '&lat=' + lat + '&lon=' + lng)
      .then(function (r) { return r.json(); }).then(function (j) {
        if (!j || !j.display_name) return;
        var a = j.display_name.split(',').slice(0, 3).join(',');
        $('#here-addr').textContent = a;
        if (st.pick && st.pick.geo === geo) st.pick.addr = a;
      }).catch(function () {});
  }
  function hereLocate(fromTap) {
    if (!navigator.geolocation) return;
    navigator.geolocation.getCurrentPosition(function (p) { hereShow(p.coords.latitude, p.coords.longitude); },
      function () { if (fromTap) alert(L_.gpsFail); }, { enableHighAccuracy: true, timeout: 12000, maximumAge: 60000 });
  }
  var hb = $('#here-on'); if (hb) hb.addEventListener('click', function () { hereLocate(true); });
  if (navigator.permissions && navigator.permissions.query) {
    navigator.permissions.query({ name: 'geolocation' }).then(function (r) { if (r.state === 'granted') hereLocate(false); }).catch(function () {});
  }

  var mic = $('#mic'), Rec = window.SpeechRecognition || window.webkitSpeechRecognition;
  if (mic) mic.addEventListener('click', function () {
    var out = $('#listen');
    if (!Rec) { out.hidden = false; out.textContent = L_.noMic; return; }
    var r = new Rec(); r.lang = C.lang === 'en' ? 'en-IN' : 'hi-IN'; r.interimResults = false; r.maxAlternatives = 1;
    mic.classList.add('on'); out.hidden = false; out.textContent = L_.listen;
    r.onresult = function (e) {
      var said = (e.results[0][0].transcript || '').trim();
      out.hidden = true;
      if (!said) return;
      openBook('bike');
      $('#drop-q').value = said;
      search('drop');
    };
    r.onerror = function () { out.textContent = L_.noMic; };
    r.onend = function () { mic.classList.remove('on'); };
    try { r.start(); } catch (e) { mic.classList.remove('on'); }
  });

  function recent() { try { var a = JSON.parse(store.get('sr_recent') || '[]'); return Array.isArray(a) ? a.filter(function (x) { return x && typeof x.addr === 'string' && x.geo; }) : []; } catch (e) { return []; } }
  function addRecent(pl) {
    if (!pl || !pl.geo) return;
    var a = recent().filter(function (x) { return x.addr !== pl.addr; });
    var old = recent().filter(function (x) { return x.addr === pl.addr; })[0];
    a.unshift({ addr: pl.addr, geo: pl.geo, fav: !!(old && old.fav) });
    store.set('sr_recent', JSON.stringify(a.slice(0, 8))); drawRecent();
  }
  function drawRecent() {
    var a = recent().sort(function (x, y) { return (y.fav ? 1 : 0) - (x.fav ? 1 : 0); }).slice(0, 4), box = $('#recent');
    if (!box) return;
    box.hidden = !a.length;
    $('#recentlist').innerHTML = a.map(function (x, i) {
      var parts = String(x.addr).split(',');
      return '<li><button type="button" data-r="' + i + '"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#4B5A6B" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5M12 7v5l3 2"/></svg><span style="min-width:0"><b>' + esc(parts[0]) + '</b><small>' + esc(parts.slice(1).join(',').trim()) + '</small></span></button>' +
        '<button type="button" class="ca-fav" data-f="' + i + '" aria-pressed="' + !!x.fav + '" aria-label="' + esc(L_.fav) + '"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#4B5A6B" stroke-width="2" aria-hidden="true"><path d="M12 20s-7-4.4-9-9a5 5 0 0 1 9-3 5 5 0 0 1 9 3c-2 4.6-9 9-9 9z"/></svg></button></li>';
    }).join('');
    $$('[data-r]', box).forEach(function (b) { b.addEventListener('click', function () {
      var x = a[+b.getAttribute('data-r')], g = x.geo.split(',');
      openBook('bike'); setPlace('drop', +g[0], +g[1], x.addr);
    }); });
    $$('[data-f]', box).forEach(function (b) { b.addEventListener('click', function () {
      var x = a[+b.getAttribute('data-f')], all = recent();
      all.forEach(function (y) { if (y.addr === x.addr) y.fav = !y.fav; });
      store.set('sr_recent', JSON.stringify(all)); drawRecent();
    }); });
  }
  drawRecent();

  /* ---------- start ---------- */
  history.replaceState({ v: 'home' }, '', location.pathname + location.search);
  var draft = null; try { draft = JSON.parse(sessionStorage.getItem('sr_draft') || 'null'); } catch (e) {}
  var m = location.hash.match(/^#track\/([A-Za-z0-9-]+)$/);
  if (m && C.loggedIn) openTrack(m[1]);
  else if (location.hash === '#mine') openMine();
  else if (draft && C.loggedIn && GROUP[draft.svc]) {
    openBook(draft.svc, true);
    ['choice', 'when', 'at', 'self', 'seats', 'rname', 'rmob', 'items', 'note'].forEach(function (k) { if (draft[k] !== undefined) st[k] = draft[k]; });
    if (draft.pick) { var p = draft.pick.geo.split(','); setPlace('pick', +p[0], +p[1], draft.pick.addr); }
    if (draft.drop) { var d = draft.drop.geo.split(','); setPlace('drop', +d[0], +d[1], draft.drop.addr); }
  }
})();
