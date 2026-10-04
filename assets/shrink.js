/* ============================================================
   Maakit — photo bhejne se pehle chhoti kar do

   Gaav ke net par phone ki 4-5 MB wali photo chadhte-chadhte
   kat jaati hai. Ye script usi photo ko phone ke andar hi
   ~200 KB ka bana deti hai — dikhne me koi farq nahi padta,
   kyunki website waise bhi use 500px ka bana deti hai.

   Purane phone me ye kaam na ho paye to photo jaisi hai
   waisi hi chali jaati hai — kuch rukta nahi.
   ============================================================ */
(function (w, d) {
  'use strict';

  var NAAP  = 1200;        // sabse lambi taraf itni px (website waise bhi 500-700 ka banati hai)
  var MAAN  = 0.80;        // JPEG ki safai
  var BADI  = 1200 * 1024; // itni se badi rah gayi to ek baar aur kaso
  var NAAP2 = 900, MAAN2 = 0.70;
  var CHHOD = 400 * 1024;  // itni chhoti photo ko chhed-chhad ki zaroorat nahi

  function jpegFile(blob, naam) {
    var n = (naam || 'photo').replace(/\.[^.]+$/, '') + '.jpg';
    try { return new File([blob], n, { type: 'image/jpeg', lastModified: Date.now() }); }
    catch (e) { blob.name = n; return blob; }      // purana browser
  }

  function canvasSe(src, w0, h0, cb, naap, maan) {
    naap = naap || NAAP; maan = maan || MAAN;
    var s = Math.min(1, naap / Math.max(w0, h0));
    var cw = Math.max(1, Math.round(w0 * s)), ch = Math.max(1, Math.round(h0 * s));
    var c = d.createElement('canvas');
    c.width = cw; c.height = ch;
    var ctx = c.getContext('2d');
    if (!ctx) return cb(null);
    ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, cw, ch);   // PNG ka khaalipan safed ho jaye
    ctx.drawImage(src, 0, 0, cw, ch);
    if (c.toBlob) c.toBlob(function (b) { cb(b); }, 'image/jpeg', maan);
    else cb(null);
  }

  /* ek photo chhoti karo — hamesha kuch na kuch wapas deta hai */
  function chhoti(file) {
    return new Promise(function (done) {
      if (!file || !/^image\//i.test(file.type || '')) return done(file);
      if (file.size <= CHHOD) return done(file);          // pehle se chhoti hai

      var band = false;
      var ghanti = setTimeout(function () { if (!band) { band = true; done(file); } }, 12000);
      function wapas(x) { if (band) return; band = true; clearTimeout(ghanti); done(x || file); }

      function aage(src, w0, h0, saaf) {
        canvasSe(src, w0, h0, function (b) {
          // ab bhi badi hai? ek baar aur, thoda aur kaskar
          if (b && b.size > BADI) {
            canvasSe(src, w0, h0, function (b2) {
              if (saaf) try { saaf(); } catch (e) {}
              var best = (b2 && b2.size < b.size) ? b2 : b;
              if (!best || best.size >= file.size) return wapas(file);
              wapas(jpegFile(best, file.name));
            }, NAAP2, MAAN2);
            return;
          }
          if (saaf) try { saaf(); } catch (e) {}
          if (!b || b.size >= file.size) return wapas(file);   // fayda hi nahi hua
          wapas(jpegFile(b, file.name));
        });
      }

      // naya tarika — photo ka ghumav (EXIF) bhi khud theek kar deta hai
      if (w.createImageBitmap) {
        var p;
        try { p = w.createImageBitmap(file, { imageOrientation: 'from-image' }); }
        catch (e) { p = null; }
        if (p && p.then) {
          p.then(function (bmp) {
            aage(bmp, bmp.width, bmp.height, function () { bmp.close && bmp.close(); });
          })['catch'](function () { puranaTarika(); });
          return;
        }
      }
      puranaTarika();

      function puranaTarika() {
        var url = (w.URL || w.webkitURL).createObjectURL(file);
        var im = new Image();
        im.onload = function () {
          aage(im, im.naturalWidth || im.width, im.naturalHeight || im.height,
               function () { (w.URL || w.webkitURL).revokeObjectURL(url); });
        };
        im.onerror = function () { (w.URL || w.webkitURL).revokeObjectURL(url); wapas(file); };
        im.src = url;
      }
    });
  }

  /* kai photo ek saath */
  function sabChhoti(files) {
    var kaam = [];
    for (var i = 0; i < files.length; i++) kaam.push(chhoti(files[i]));
    return Promise.all(kaam);
  }

  /* chhoti ki hui file wapas input me bitha do */
  function bithao(input, files) {
    if (!w.DataTransfer) return false;
    try {
      var dt = new DataTransfer();
      for (var i = 0; i < files.length; i++) dt.items.add(files[i]);
      input.files = dt.files;
      return true;
    } catch (e) { return false; }
  }

  /* form bhejne se pehle usme padi saari photo chhoti kar do */
  function formSambhalo(form) {
    if (!form || form.__mkShrink) return;
    form.__mkShrink = true;

    form.addEventListener('submit', function (ev) {
      if (form.__mkChalDo) return;                       // dusri baar — jaane do

      var inputs = [], lage = [];
      form.querySelectorAll('input[type=file]').forEach(function (inp) {
        if (!inp.files || !inp.files.length) return;
        if (!/image/.test(inp.accept || 'image')) return;
        inputs.push(inp);
      });
      if (!inputs.length) return;                        // koi photo hi nahi

      // kya kuch chhota karne layak hai bhi?
      var kaamHai = inputs.some(function (inp) {
        for (var i = 0; i < inp.files.length; i++) if (inp.files[i].size > CHHOD) return true;
        return false;
      });
      if (!kaamHai) return;

      ev.preventDefault();

      var btn = form.querySelector('button[type=submit], button:not([type])');
      var purana = btn ? btn.innerHTML : null;
      if (btn) { btn.disabled = true; btn.innerHTML = 'फ़ोटो छोटी की जा रही है…'; }

      Promise.all(inputs.map(function (inp) {
        return sabChhoti(inp.files).then(function (nayi) { lage.push([inp, nayi]); });
      })).then(function () {
        lage.forEach(function (x) { bithao(x[0], x[1]); });
      })['catch'](function () { /* kuch na kuch to jayega hi */ })
        .then(function () {
          if (btn) { btn.innerHTML = 'भेजी जा रही है…'; }
          form.__mkChalDo = true;
          if (form.requestSubmit) form.requestSubmit(); else form.submit();
        });
    });
  }

  /* page par jitne form hain, sab sambhal lo */
  function sabForm() {
    d.querySelectorAll('form[enctype="multipart/form-data"]').forEach(formSambhalo);
  }

  w.mkShrink     = chhoti;        // ek photo, promise
  w.mkShrinkAll  = sabChhoti;     // kai photo
  w.mkShrinkForm = formSambhalo;  // ek form

  if (d.readyState === 'loading') d.addEventListener('DOMContentLoaded', sabForm);
  else sabForm();
})(window, document);
