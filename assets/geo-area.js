/* ============================================================
   Maakit — "Use my location": phone ki GPS se apna sewa-kshetra
   (gaon / ilaka) dhoondhna.

   1. Browser se lat/lng (grahak ki ijaazat se hi)
   2. OpenStreetMap (Nominatim) se us jagah ka naam + PIN
   3. Wo naam/PIN Maakit ke chalu ilakon se milana

   Kuch bhi galat ho to chupchaap null — panna kabhi nahi tootta.
   Istemal:
     MkGeo.detect(areas).then(function (r) { r.match, r.place, r.near })
     areas = [{name, name_en, city, state, pincode}, ...]
   ============================================================ */
(function () {
  function norm(s) {
    return String(s || '').toLowerCase()
      .replace(/\(.*?\)/g, ' ')
      .replace(/\b(village|gaon|gram|town|nagar panchayat|tehsil|block|district|ward|no\.?|mohalla)\b/g, ' ')
      .replace(/[^a-z0-9ऀ-ॿ ]+/g, ' ').replace(/\s+/g, ' ').trim();
  }
  function same(a, b) {
    a = norm(a); b = norm(b);
    if (!a || !b) return false;
    if (a === b) return true;
    // "bhadohi city" ~ "bhadohi", "gyanpur" ~ "gyanpur bazar"
    return (a.length > 3 && b.length > 3) && (a.indexOf(b + ' ') === 0 || b.indexOf(a + ' ') === 0);
  }

  function where() {
    return new Promise(function (ok, no) {
      if (!navigator.geolocation) return no(new Error('nogps'));
      navigator.geolocation.getCurrentPosition(function (p) { ok(p.coords); },
        function (e) { no(e); }, { enableHighAccuracy: true, timeout: 12000, maximumAge: 300000 });
    });
  }

  function place(lat, lng) {
    var u = 'https://nominatim.openstreetmap.org/reverse?format=jsonv2&addressdetails=1&zoom=16&accept-language=en'
      + '&lat=' + encodeURIComponent(lat) + '&lon=' + encodeURIComponent(lng);
    return fetch(u, { headers: { 'Accept': 'application/json' } }).then(function (r) { return r.json(); })
      .then(function (j) { return j && j.address ? j : null; }).catch(function () { return null; });
  }

  // Sabse pakka mel pehle: gaon/mohalla ka naam → PIN → shahar
  function match(areas, addr) {
    if (!addr) return { match: null, near: [] };
    var small = [addr.village, addr.hamlet, addr.suburb, addr.neighbourhood, addr.quarter, addr.town, addr.city_district];
    var big = [addr.city, addr.town, addr.municipality, addr.county, addr.state_district];
    var pin = String(addr.postcode || '').replace(/\D/g, '');
    var best = null, bestScore = 0, near = [];
    areas.forEach(function (a) {
      var s = 0;
      small.forEach(function (x) { if (x && (same(x, a.name_en) || same(x, a.name))) s = Math.max(s, 3); });
      if (pin && a.pincode && String(a.pincode) === pin) s = Math.max(s, 2);
      if (s < 2) big.forEach(function (x) { if (x && (same(x, a.name_en) || same(x, a.name))) s = Math.max(s, 2); });
      if (s < 2) big.forEach(function (x) { if (x && a.city && same(x, a.city)) s = Math.max(s, 1); });
      if (s >= 1) near.push({ area: a, s: s });
      if (s > bestScore) { bestScore = s; best = a; }
    });
    near.sort(function (x, y) { return y.s - x.s; });
    // shahar bhar ka mel (1) pakka nahi — sirf "paas ke ilake" dikhte hain
    return { match: bestScore >= 2 ? best : null, near: near.slice(0, 4).map(function (n) { return n.area; }) };
  }

  function label(j) {
    if (!j || !j.address) return '';
    var a = j.address;
    var parts = [a.village || a.hamlet || a.suburb || a.neighbourhood || a.town || a.city, a.city || a.county || a.state_district, a.postcode];
    return parts.filter(function (x, i, arr) { return x && arr.indexOf(x) === i; }).join(', ');
  }

  window.MkGeo = {
    detect: function (areas) {
      return where().then(function (c) {
        return place(c.latitude, c.longitude).then(function (j) {
          var m = match(areas || [], j && j.address);
          return { lat: c.latitude, lng: c.longitude, place: label(j), match: m.match, near: m.near, raw: j };
        });
      });
    },
    // Ijaazat pehle se di hui hai? (tab bina pooche chala sakte hain)
    allowed: function () {
      if (!navigator.permissions || !navigator.permissions.query) return Promise.resolve(false);
      return navigator.permissions.query({ name: 'geolocation' }).then(function (p) { return p.state === 'granted'; }).catch(function () { return false; });
    },
    match: match
  };
})();
