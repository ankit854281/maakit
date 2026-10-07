/* Fresh browser positions only; no background GPS guarantee. */
(function () {
  'use strict';
  var labels = window.MAAKIT_TRACKING, active = null, generation = 0;
  if (!labels) return;
  function request(action, entry, extra) {
    var data = new FormData();
    data.append('a', action); data.append('csrf', labels.csrf); data.append('o', entry.order);
    if (entry.token) data.append('token', entry.token);
    Object.keys(extra || {}).forEach(function (key) { data.append(key, extra[key]); });
    return fetch('/api.php', {method: 'POST', body: data, credentials: 'same-origin'})
      .then(function (r) { if (!r.ok) throw new Error('tracking'); return r.json(); })
      .then(function (r) { if (!r.ok) throw new Error('tracking'); return r; });
  }
  function stop(message) {
    var old = active; active = null; generation++;
    if (!old) return;
    if (old.watch !== null) navigator.geolocation.clearWatch(old.watch);
    old.box.querySelector('.trkOn').style.display = '';
    old.box.querySelector('.trkOff').style.display = 'none';
    old.box.querySelector('.trkMsg').textContent = message || labels.stopped;
    try { sessionStorage.removeItem('mk_trk'); } catch (e) {}
    if (old.token) request('stop_tracking', old).catch(function () {
      old.box.querySelector('.trkMsg').textContent = labels.failed;
    });
  }
  function start(box) {
    if (!navigator.geolocation) { box.querySelector('.trkMsg').textContent = labels.denied; return; }
    stop('');
    var entry = {box: box, order: box.getAttribute('data-o'), token: null, watch: null, lastSent: 0};
    var version = generation; active = entry;
    box.querySelector('.trkOn').style.display = 'none'; box.querySelector('.trkOff').style.display = '';
    box.querySelector('.trkMsg').textContent = labels.waiting;
    request('start_tracking', entry).then(function (reply) {
      entry.token = reply.token;
      if (active !== entry || version !== generation) { request('stop_tracking', entry).catch(function () {}); return; }
      try { sessionStorage.setItem('mk_trk', entry.order); } catch (e) {}
      entry.watch = navigator.geolocation.watchPosition(function (position) {
        if (active !== entry) return;
        if (document.hidden) { box.querySelector('.trkMsg').textContent = labels.paused; return; }
        if (!position.timestamp || Date.now() - position.timestamp > 60000) { box.querySelector('.trkMsg').textContent = labels.stale; return; }
        if (Date.now() - entry.lastSent < 10000) return;
        entry.lastSent = Date.now();
        request('ping', entry, {lat: position.coords.latitude, lng: position.coords.longitude,
          acc: Math.round(position.coords.accuracy || 0), at: Math.floor(position.timestamp / 1000)})
          .then(function () { if (active === entry) box.querySelector('.trkMsg').textContent = labels.shared; })
          .catch(function () { if (active === entry) box.querySelector('.trkMsg').textContent = labels.failed; });
      }, function (error) { if (active === entry) stop(error.code === 1 ? labels.denied : labels.stale); },
      {enableHighAccuracy: true, maximumAge: 10000, timeout: 20000});
    }).catch(function () { if (active === entry) stop(labels.failed); });
  }
  document.addEventListener('click', function (event) {
    var on = event.target.closest('.trkOn'), off = event.target.closest('.trkOff');
    if (on) start(on.closest('.trk')); if (off) stop(labels.stopped);
  });
  document.addEventListener('submit', function (event) {
    if (event.target.querySelector('input[name=status][value=Delivered]')) stop('');
  });
  document.addEventListener('visibilitychange', function () {
    if (active) active.box.querySelector('.trkMsg').textContent = document.hidden ? labels.paused : labels.waiting;
  });
  try {
    var previous = sessionStorage.getItem('mk_trk');
    Array.prototype.forEach.call(document.querySelectorAll('.trk'), function (box) {
      if (box.getAttribute('data-o') === previous) start(box);
    });
  } catch (e) {}
})();
