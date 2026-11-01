/* Maakit trial app — drawn illustrations (flat, soft-shaded, no outlines).
 * Every drawing is a 64×64 SVG. MKILL(key, ...args) returns the markup. */
(function () {
  'use strict';
  /* One calm palette for every drawing (owner: subtle, professional, not every icon a different colour). */
  var PAL = {
    '#E8458A':'#D6246E','#F25C98':'#DE5A92','#25A55F':'#6FA287','#2FA35E':'#6FA287','#1E6B40':'#4E7F66','#3DBB6B':'#86B79A','#1F9A57':'#6FA287',
    '#1D7BD8':'#8A5C84','#5B8DEF':'#B9A3B8','#BFE6FF':'#F1E7EE','#DFF7FB':'#F3EBF1','#E9F3FF':'#F7EFF4','#DCEBFF':'#F3E8EF','#E1E7EC':'#ECE6EB',
    '#FFB020':'#E5A447','#FFC34D':'#EDB862','#F29A12':'#D28D36','#FFD27A':'#F1CD94','#F7922B':'#E19A55','#F08A1C':'#D48A3E','#FF8A3D':'#DE9A6E',
    '#D9530C':'#B9683F','#A3440B':'#8A4A2E','#7A3A0B':'#6B3A26','#E0332B':'#C94A42','#FF8A7A':'#E58C83','#D92B22':'#B63E36','#FF6B6B':'#E39090',
    '#A47AFF':'#B78FB0','#7B4FEA':'#8E5A86','#C4A8FF':'#DCC3D6','#8F63F5':'#9D6C94','#5A33C8':'#6E3F68','#6A3FD8':'#7E4A76','#FF6B2B':'#E07A4A',
    '#FFE27A':'#F3D9A2','#F2B705':'#DDAE52','#FFB547':'#E7A862','#E8700F':'#C77A40','#FFF1A8':'#F6E8C0','#F6DFA9':'#F3E3C2',
    '#9DB7D6':'#C2B4C6','#3FA66B':'#6FA287','#7EC8F2':'#BBA7C3','#E9A21B':'#D49E4E','#F5C842':'#E6C46A','#F2C230':'#DDB459','#F2B544':'#DDB06A',
    '#1A9BB8':'#B23A6E','#F7A93B':'#E2A458','#FFF4B0':'#FBEFC9','#FF9A1F':'#E59A45'
  };
  function calm(x) { return String(x).replace(/#[0-9A-Fa-f]{6}/g, function (h) { return PAL[h.toUpperCase()] || PAL[h] || h; }); }
  var n = 0;
  function id() { return 'mk' + (++n); }
  function lg(i, a, b, horiz) {
    return '<linearGradient id="' + i + '" x1="0" y1="0" x2="' + (horiz ? 1 : 0) + '" y2="' + (horiz ? 0 : 1) + '"><stop offset="0" stop-color="' + a + '"/><stop offset="1" stop-color="' + b + '"/></linearGradient>';
  }
  function rg(i, a, b) {
    return '<radialGradient id="' + i + '" cx="35%" cy="30%" r="75%"><stop offset="0" stop-color="' + a + '"/><stop offset="1" stop-color="' + b + '"/></radialGradient>';
  }
  function svg(defs, body) {
    return '<svg class="ill" viewBox="0 0 64 64" aria-hidden="true"><defs>' + defs + '</defs>' +
      '<ellipse cx="32" cy="59" rx="21" ry="3.2" fill="#2A1530" opacity=".13"/>' + body + '</svg>';
  }
  function shine(x, y, w, h) { return '<rect x="' + x + '" y="' + y + '" width="' + w + '" height="' + h + '" rx="' + Math.min(w, h) / 2 + '" fill="#fff" opacity=".45"/>'; }
  function lighten(hex, k) {
    var c = parseInt(hex.slice(1), 16), r = c >> 16, g = (c >> 8) & 255, b = c & 255;
    r = Math.round(r + (255 - r) * k); g = Math.round(g + (255 - g) * k); b = Math.round(b + (255 - b) * k);
    return '#' + ((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1);
  }
  function darken(hex, k) {
    var c = parseInt(hex.slice(1), 16), r = c >> 16, g = (c >> 8) & 255, b = c & 255;
    r = Math.round(r * (1 - k)); g = Math.round(g * (1 - k)); b = Math.round(b * (1 - k));
    return '#' + ((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1);
  }
  function wheel(cx, cy, r) {
    return '<circle cx="' + cx + '" cy="' + cy + '" r="' + r + '" fill="#2A1530"/><circle cx="' + cx + '" cy="' + cy + '" r="' + (r * .45) + '" fill="#C9D3DA"/>';
  }

  var D = {
    /* ---------- service tabs ---------- */
    shop: function () {
      var a = id(), b = id();
      var stripes = '';
      for (var i = 0; i < 5; i++) stripes += '<rect x="' + (7 + i * 10) + '" y="17" width="10" height="9" fill="' + (i % 2 ? '#FFFFFF' : '#E8458A') + '"/><circle cx="' + (12 + i * 10) + '" cy="26" r="5" fill="' + (i % 2 ? '#FFFFFF' : '#E8458A') + '"/>';
      return svg(lg(a, '#FFF9F2', '#F6D9C4') + lg(b, '#FFC34D', '#F29A12'),
        '<rect x="10" y="24" width="44" height="33" rx="3" fill="url(#' + a + ')"/>' +
        '<rect x="15" y="7" width="34" height="9" rx="3" fill="url(#' + b + ')"/><rect x="20" y="10" width="24" height="3" rx="1.5" fill="#fff" opacity=".7"/>' +
        '<rect x="6" y="15" width="52" height="3" rx="1.5" fill="#A3164F"/>' + stripes +
        '<rect x="14" y="35" width="13" height="11" rx="2" fill="#BFE6FF"/><rect x="15.5" y="36.5" width="4" height="8" rx="1" fill="#fff" opacity=".6"/>' +
        '<rect x="33" y="35" width="15" height="22" rx="2" fill="#8A3B12"/><rect x="35" y="37" width="11" height="9" rx="1.5" fill="#BFE6FF"/><circle cx="45" cy="49" r="1.2" fill="#FFC34D"/>' +
        '<rect x="14" y="48" width="13" height="3" rx="1" fill="#25A55F"/><rect x="16" y="45" width="3" height="3" fill="#FFB020"/><rect x="21" y="44.5" width="3" height="3.5" fill="#D6246E"/>');
    },
    suv: function (col) {
      col = col || '#B23A6E';
      var a = id();
      return svg(lg(a, lighten(col, .25), darken(col, .2)),
        '<rect x="20" y="13" width="22" height="3" rx="1.5" fill="#2A1530" opacity=".7"/><rect x="22" y="16" width="2" height="4" fill="#2A1530" opacity=".7"/><rect x="38" y="16" width="2" height="4" fill="#2A1530" opacity=".7"/>' +
        '<path d="M5 44c0-6 3-9 7-10l5-12c1-2 2-2 4-2h22c2 0 3 1 4 2l6 12c5 1 6 4 6 10v4c0 2-1 3-3 3H8c-2 0-3-1-3-3z" fill="url(#' + a + ')"/>' +
        '<path d="M19 23h11v10H15z" fill="#DFF7FB"/><path d="M33 23h10l4 10H33z" fill="#DFF7FB"/><path d="M20 24h3l-3 7h-2z" fill="#fff" opacity=".7"/>' +
        '<rect x="5" y="40" width="54" height="3" fill="#fff" opacity=".25"/>' +
        '<rect x="54" y="38" width="5" height="3" rx="1.5" fill="#FFE27A"/><rect x="5" y="38" width="4" height="3" rx="1.5" fill="#FF6B6B"/>' +
        wheel(17, 50, 6.5) + wheel(47, 50, 6.5));
    },
    toolbox: function () {
      var a = id(), b = id();
      return svg(lg(a, '#FF8A3D', '#D9530C') + lg(b, '#E1E7EC', '#9AA7B1'),
        '<path d="M24 19v-4a3 3 0 0 1 3-3h10a3 3 0 0 1 3 3v4" fill="none" stroke="#7A3A0B" stroke-width="3.5"/>' +
        '<rect x="7" y="19" width="50" height="35" rx="6" fill="url(#' + a + ')"/><rect x="7" y="31" width="50" height="4" fill="#A3440B" opacity=".5"/>' +
        '<rect x="28" y="29" width="8" height="8" rx="2" fill="#FFD27A"/>' + shine(11, 22, 18, 3) +
        '<g transform="rotate(-35 46 14)"><rect x="44" y="4" width="5" height="22" rx="2.5" fill="url(#' + b + ')"/><path d="M41 3a6 6 0 0 0 11 0v-3h-3v3h-5v-3h-3z" fill="#9AA7B1"/></g>');
    },
    parcel: function () {
      return svg('',
        '<path d="M32 8l22 10v28L32 56 10 46V18z" fill="#E7A764"/><path d="M32 8l22 10-22 10-22-10z" fill="#F7C98C"/><path d="M32 28l22-10v28L32 56z" fill="#C9813E"/>' +
        '<path d="M21 13l22 10v8l-5-2v-7L16 15z" fill="#FBE7C6"/>' +
        '<rect x="14" y="34" width="11" height="8" rx="1.5" transform="skewY(24)" fill="#fff" opacity=".85"/>' +
        '<path d="M8 40h-5M9 46H1M10 52H4" stroke="#A47AFF" stroke-width="2.5" stroke-linecap="round"/>');
    },

    /* ---------- categories ---------- */
    sack: function () {
      var a = id();
      return svg(lg(a, '#FFF3DB', '#E9C98C'),
        '<path d="M18 16c4 2 24 2 28 0l-2 6c5 6 8 14 8 22 0 8-6 13-20 13S12 52 12 44c0-8 3-16 8-22z" fill="url(#' + a + ')"/>' +
        '<path d="M20 22c6 2 18 2 24 0" stroke="#B08A4A" stroke-width="2" fill="none"/>' +
        '<ellipse cx="32" cy="15" rx="12" ry="3" fill="#F6DFA9"/><circle cx="27" cy="13" r="1.4" fill="#C9A15A"/><circle cx="33" cy="12" r="1.4" fill="#C9A15A"/><circle cx="37" cy="14" r="1.4" fill="#C9A15A"/>' +
        '<rect x="20" y="33" width="24" height="13" rx="3" fill="#D6246E"/><path d="M26 42v-6l6 4 6-4v6" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>' + shine(16, 26, 4, 12));
    },
    vegbasket: function () {
      var a = id();
      return svg(rg(a, '#FF8A7A', '#E0332B'),
        '<path d="M14 30c-2-8 4-14 10-12-1 5-5 9-10 12z" fill="#2FA35E"/>' +
        '<path d="M40 16l14 22c1 2-1 4-3 3L36 25z" fill="#F7922B"/><path d="M38 14c-2-4 0-7 3-7 0 3-1 5-3 7zM40 15c2-3 6-4 8-2-2 2-5 3-8 2z" fill="#2FA35E"/>' +
        '<circle cx="28" cy="36" r="13" fill="url(#' + a + ')"/><path d="M24 23c2 2 6 2 8 0-1 3-2 4-4 4s-3-1-4-4z" fill="#2FA35E"/>' + shine(20, 29, 5, 7) +
        '<path d="M8 44h48l-5 13H13z" fill="#C98B4A"/><path d="M8 44h48v3H8z" fill="#A86F35"/><path d="M18 47v10M28 47v10M38 47v10M48 47v10" stroke="#A86F35" stroke-width="1.5"/>');
    },
    medicine: function () {
      var a = id();
      return svg(lg(a, '#FFFFFF', '#DCE6F2'),
        '<rect x="18" y="8" width="22" height="9" rx="3" fill="#1D7BD8"/>' +
        '<rect x="15" y="15" width="28" height="40" rx="6" fill="url(#' + a + ')"/>' +
        '<rect x="19" y="26" width="20" height="18" rx="3" fill="#E9F3FF"/><path d="M27 29h4v4h4v4h-4v4h-4v-4h-4v-4h4z" fill="#E0332B"/>' + shine(18, 18, 3, 30) +
        '<rect x="42" y="38" width="16" height="8" rx="4" transform="rotate(-30 50 42)" fill="#FFB020"/><rect x="42" y="38" width="8" height="8" rx="4" transform="rotate(-30 50 42)" fill="#fff"/>');
    },
    sweets: function () {
      var a = id();
      var l = function (cx, cy) { return '<circle cx="' + cx + '" cy="' + cy + '" r="9" fill="url(#' + a + ')"/><circle cx="' + (cx - 2) + '" cy="' + (cy - 3) + '" r="1" fill="#fff" opacity=".7"/><circle cx="' + (cx + 3) + '" cy="' + (cy + 1) + '" r="1" fill="#B8560A"/><circle cx="' + (cx - 1) + '" cy="' + (cy + 4) + '" r="1" fill="#B8560A"/>'; };
      return svg(rg(a, '#FFD27A', '#F08A1C'),
        '<ellipse cx="32" cy="47" rx="27" ry="8" fill="#C0C9D2"/><ellipse cx="32" cy="45" rx="27" ry="7" fill="#E8EDF2"/><ellipse cx="32" cy="45" rx="20" ry="4.5" fill="#D3DBE3"/>' +
        l(22, 38) + l(42, 38) + l(32, 28) + '<path d="M30 17c1-3 4-4 6-3-1 2-3 3-6 3z" fill="#2FA35E"/>');
    },
    dairy: function () {
      var a = id();
      return svg(lg(a, '#FFFFFF', '#E3ECF5'),
        '<path d="M14 18h16v6l4 6v24a3 3 0 0 1-3 3H13a3 3 0 0 1-3-3V30l4-6z" fill="url(#' + a + ')"/><rect x="13" y="12" width="18" height="7" rx="2" fill="#1D7BD8"/>' +
        '<rect x="10" y="36" width="24" height="11" fill="#1D7BD8" opacity=".85"/><path d="M14 43c3-3 6-3 8 0s5 3 8 0" stroke="#fff" stroke-width="1.6" fill="none"/>' + shine(13, 24, 3, 26) +
        '<path d="M38 30h18l-2 26H40z" fill="#E8F2FB" opacity=".9"/><path d="M39 38h16l-1.4 18H40.4z" fill="#fff"/><path d="M39 38c3 2 6 2 8 0s5-2 8 0" fill="#F2F7FC"/>');
    },
    diya: function () {
      var a = id(), b = id();
      return svg(lg(a, '#E07A3A', '#9A3D12') + rg(b, '#FFF4B0', '#FF9A1F'),
        '<path d="M32 10c6 8 7 13 3 17-2 2-4 2-6 0-4-4-3-9 3-17z" fill="url(#' + b + ')"/><path d="M32 18c2 3 2 5 1 7h-2c-1-2-1-4 1-7z" fill="#fff" opacity=".8"/>' +
        '<rect x="31" y="26" width="2" height="5" fill="#5A2A10"/>' +
        '<path d="M8 33h48c-2 12-12 19-24 19S10 45 8 33z" fill="url(#' + a + ')"/><path d="M8 33h48c0 2-4 4-24 4S8 35 8 33z" fill="#F2A15F"/>' +
        '<path d="M18 42c4 3 24 3 28 0" stroke="#FFD27A" stroke-width="2" fill="none" stroke-dasharray="2 3"/>');
    },

    /* ---------- vehicles / services / parcel cards ---------- */
    van: function () {
      var a = id();
      return svg(lg(a, '#FFE27A', '#F2B705'),
        '<path d="M6 46V24c0-3 2-5 5-5h28c2 0 3 1 4 2l9 11c4 1 6 4 6 8v6c0 2-1 3-3 3H9c-2 0-3-1-3-3z" fill="url(#' + a + ')"/>' +
        '<rect x="11" y="24" width="9" height="9" rx="2" fill="#DFF7FB"/><rect x="23" y="24" width="9" height="9" rx="2" fill="#DFF7FB"/><path d="M36 24h4l8 9H36z" fill="#DFF7FB"/>' +
        '<rect x="6" y="38" width="52" height="4" fill="#1F9A57"/><rect x="54" y="40" width="4" height="3" rx="1.5" fill="#fff"/>' + wheel(17, 50, 6) + wheel(46, 50, 6));
    },
    bus: function () {
      var a = id();
      return svg(lg(a, '#FFB547', '#E8700F'),
        '<rect x="5" y="14" width="54" height="36" rx="7" fill="url(#' + a + ')"/><rect x="5" y="40" width="54" height="4" fill="#A3440B" opacity=".35"/>' +
        '<rect x="9" y="19" width="9" height="11" rx="2" fill="#DFF7FB"/><rect x="20" y="19" width="9" height="11" rx="2" fill="#DFF7FB"/><rect x="31" y="19" width="9" height="11" rx="2" fill="#DFF7FB"/><rect x="42" y="19" width="13" height="16" rx="2" fill="#DFF7FB"/>' +
        shine(9, 33, 30, 2.5) + '<rect x="54" y="38" width="5" height="3" rx="1.5" fill="#FFF1A8"/>' + wheel(16, 50, 6) + wheel(46, 50, 6));
    },
    tour: function () {
      return D.suv('#F4F6F8').replace('<rect x="20" y="13"', '<rect x="21" y="5" width="11" height="9" rx="2" fill="#D6246E"/><rect x="33" y="7" width="9" height="7" rx="2" fill="#FFB020"/><rect x="20" y="13"');
    },
    kalash: function () {
      var a = id();
      return svg(lg(a, '#F2A15F', '#B5541C'),
        '<path d="M22 22l-8-10c4 0 8 3 10 6M42 22l8-10c-4 0-8 3-10 6M28 20l-4-12c3 1 6 5 6 9M36 20l4-12c-3 1-6 5-6 9" fill="#2FA35E"/>' +
        '<ellipse cx="32" cy="18" rx="8" ry="9" fill="#8A5A2B"/><path d="M28 12c2-2 6-2 8 0" stroke="#C49A6C" stroke-width="2" fill="none"/>' +
        '<path d="M22 25h20l-2 4c8 3 12 9 12 15 0 8-8 13-20 13S12 52 12 44c0-6 4-12 12-15z" fill="url(#' + a + ')"/>' +
        '<path d="M14 40h36" stroke="#FFD27A" stroke-width="2.5"/><circle cx="32" cy="46" r="3.5" fill="#D6246E"/><circle cx="32" cy="46" r="1.5" fill="#FFD27A"/>' + shine(17, 33, 4, 12));
    },
    tent: function () {
      var s = '';
      for (var i = 0; i < 6; i++) s += '<path d="M32 10L' + (8 + i * 8) + ' 50h8z" fill="' + (i % 2 ? '#FFFFFF' : '#F08A1C') + '"/>';
      return svg('', s + '<path d="M32 10L6 50h52z" fill="none" stroke="#A3440B" stroke-width="1.5" stroke-linejoin="round"/>' +
        '<path d="M27 50l5-15 5 15z" fill="#7A2E07"/><rect x="31" y="3" width="2" height="8" fill="#7A3A0B"/><path d="M33 3h9l-3 3 3 3h-9z" fill="#D6246E"/>' +
        '<path d="M4 50h56" stroke="#7A3A0B" stroke-width="2.5" stroke-linecap="round"/>');
    },
    kadhai: function () {
      var j = function (cx, cy) { return '<circle cx="' + cx + '" cy="' + cy + '" r="5.5" fill="none" stroke="#F7922B" stroke-width="2.6"/><circle cx="' + cx + '" cy="' + cy + '" r="2" fill="none" stroke="#F7922B" stroke-width="2.2"/>'; };
      return svg('',
        j(23, 28) + j(34, 25) + j(43, 30) +
        '<path d="M6 32h52c-1 13-12 21-26 21S7 45 6 32z" fill="#3A2A33"/><path d="M6 32h52c0 2-6 4-26 4S6 34 6 32z" fill="#5A4752"/>' +
        '<path d="M6 34c-4 0-5 4-3 5M58 34c4 0 5 4 3 5" stroke="#3A2A33" stroke-width="3" fill="none" stroke-linecap="round"/>' + shine(14, 39, 10, 2.5));
    },
    docs: function () {
      var a = id();
      return svg(lg(a, '#C4A8FF', '#7B4FEA'),
        '<rect x="16" y="6" width="30" height="38" rx="3" fill="#fff" transform="rotate(-8 31 25)"/><path d="M21 15h18M21 21h18M21 27h12" stroke="#C9B6E8" stroke-width="2" transform="rotate(-8 31 25)"/>' +
        '<rect x="8" y="26" width="48" height="30" rx="4" fill="url(#' + a + ')"/><path d="M8 28l24 16 24-16" fill="none" stroke="#fff" stroke-width="2.5" stroke-linejoin="round" opacity=".85"/>' +
        '<circle cx="47" cy="46" r="5" fill="#D6246E"/><circle cx="47" cy="46" r="2" fill="#FFB020"/>');
    },
    tiffin: function () {
      var a = id(), t = function (y) { return '<rect x="14" y="' + y + '" width="36" height="11" rx="3" fill="url(#' + a + ')"/><rect x="14" y="' + (y + 8) + '" width="36" height="2" fill="#8A97A3"/>'; };
      return svg(lg(a, '#F4F7FA', '#AEBAC5', true),
        '<path d="M18 22V9a3 3 0 0 1 3-3h22a3 3 0 0 1 3 3v13" fill="none" stroke="#8A97A3" stroke-width="3"/>' + t(18) + t(30) + t(42) + shine(17, 20, 10, 2));
    },
    bag: function () {
      var a = id();
      return svg(lg(a, '#F25C98', '#A3164F'),
        '<path d="M23 20v-5a9 9 0 0 1 18 0v5" fill="none" stroke="#A3164F" stroke-width="3.5" stroke-linecap="round"/>' +
        '<path d="M12 20h40l-3 34a3 3 0 0 1-3 3H18a3 3 0 0 1-3-3z" fill="url(#' + a + ')"/>' +
        '<path d="M24 46V32l8 7 8-7v14" fill="none" stroke="#fff" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"/><circle cx="46" cy="25" r="3.5" fill="#FFB020"/>' +
        '<rect x="40" y="10" width="8" height="12" rx="1.5" fill="#25A55F"/><rect x="16" y="12" width="8" height="10" rx="1.5" fill="#FFB020"/>');
    },
    gift: function () {
      var a = id();
      return svg(lg(a, '#A47AFF', '#5A33C8'),
        '<path d="M32 18c-6-10-16-8-14-2 1 3 8 3 14 2zM32 18c6-10 16-8 14-2-1 3-8 3-14 2z" fill="#FFB020"/>' +
        '<rect x="10" y="18" width="44" height="11" rx="3" fill="#8F63F5"/><rect x="13" y="29" width="38" height="27" rx="3" fill="url(#' + a + ')"/>' +
        '<rect x="28" y="18" width="8" height="38" fill="#FFB020"/><rect x="28" y="29" width="8" height="2" fill="#E0951A"/>' + shine(16, 32, 3, 18));
    },


    /* ---------- food (v2) ---------- */
    plate: function (inner) {
      return svg('', '<ellipse cx="32" cy="44" rx="28" ry="11" fill="#D7DEE5"/><ellipse cx="32" cy="42" rx="28" ry="10" fill="#FFFFFF"/><ellipse cx="32" cy="42" rx="21" ry="6.5" fill="#F1F4F7"/>' + (inner || ''));
    },
    samosa: function () {
      var a = id();
      var one = function (x, y, r) { return '<g transform="translate(' + x + ' ' + y + ') rotate(' + r + ')"><path d="M0 -14L13 9H-13z" fill="url(#' + a + ')"/><path d="M0 -14L13 9H-13z" fill="none" stroke="#B9651F" stroke-width="1.2" stroke-linejoin="round"/><path d="M-6 2l4 2M2 -3l3 3M-2 6l5 0" stroke="#C97A2E" stroke-width="1" stroke-linecap="round"/></g>'; };
      return D.plate(one(22, 36, -12) + one(42, 35, 14) + one(32, 30, 0) + '<path d="M48 44c3 0 6 1 6 3s-3 3-6 3-6-1-6-3 3-3 6-3z" fill="#2FA35E"/>').replace('<defs></defs>', '<defs>' + lg(a, '#F7C574', '#D98A30') + '</defs>');
    },
    chai: function () {
      var a = id();
      return svg(lg(a, '#FFFFFF', '#E9EDF1', true),
        '<path d="M24 8c-3 4 3 6 0 10M32 6c-3 4 3 6 0 10M40 8c-3 4 3 6 0 10" stroke="#C9B6A6" stroke-width="2" fill="none" stroke-linecap="round"/>' +
        '<ellipse cx="32" cy="52" rx="22" ry="5" fill="#E3E8ED"/>' +
        '<path d="M14 22h36l-3 26a6 6 0 0 1-6 5H23a6 6 0 0 1-6-5z" fill="url(#' + a + ')"/><ellipse cx="32" cy="22" rx="18" ry="4.5" fill="#C98A52"/><ellipse cx="29" cy="21.5" rx="7" ry="1.3" fill="#E2B07E"/>' +
        '<path d="M49 28c7 0 8 12 0 13" stroke="#E3E8ED" stroke-width="4" fill="none"/><path d="M20 34h24" stroke="#F36B21" stroke-width="3"/>');
    },
    jalebi: function () {
      var sp = function (cx, cy) { return '<path d="M' + cx + ' ' + cy + 'm-7 0a7 7 0 1 0 7 -7a4.5 4.5 0 1 0 -4.5 4.5a2 2 0 1 0 2 -2" fill="none" stroke="#F28C1E" stroke-width="3.4" stroke-linecap="round"/><path d="M' + cx + ' ' + cy + 'm-7 0a7 7 0 1 0 7 -7" fill="none" stroke="#FFC261" stroke-width="1.2" stroke-linecap="round" opacity=".8"/>'; };
      return D.plate(sp(22, 38) + sp(40, 38) + sp(31, 30));
    },
    mealthali: function () {
      var k = function (cx, cy, c, top) { return '<ellipse cx="' + cx + '" cy="' + (cy + 2) + '" rx="7.5" ry="3.2" fill="#AEBAC5"/><ellipse cx="' + cx + '" cy="' + cy + '" rx="7.5" ry="3.2" fill="#DDE3E9"/><ellipse cx="' + cx + '" cy="' + cy + '" rx="6" ry="2.4" fill="' + c + '"/>' + (top || ''); };
      return svg('',
        '<ellipse cx="32" cy="44" rx="29" ry="13" fill="#9AA7B1"/><ellipse cx="32" cy="42" rx="29" ry="12" fill="#E3E9EE"/><ellipse cx="32" cy="42" rx="24" ry="9" fill="#CBD4DC"/>' +
        k(18, 37, '#E8B23A') + k(30, 33, '#B5452B') + k(43, 36, '#F7F3EA') +
        '<ellipse cx="24" cy="46" rx="9" ry="3.6" fill="#F7EBD3"/><ellipse cx="24" cy="45" rx="8" ry="3" fill="#E9C98F"/>' +
        '<ellipse cx="40" cy="47" rx="7" ry="3" fill="#FFFFFF"/><circle cx="38" cy="46" r="1" fill="#F3EEE4"/><circle cx="42" cy="47" r="1" fill="#F3EEE4"/>' +
        '<path d="M48 42l6 4" stroke="#2FA35E" stroke-width="2.5" stroke-linecap="round"/>');
    },
    paratha: function () {
      var a = id();
      return D.plate('<ellipse cx="31" cy="38" rx="19" ry="8" fill="url(#' + a + ')"/><ellipse cx="33" cy="35" rx="17" ry="7" fill="#EDBB6A"/><path d="M22 34c4 2 8 2 12 0M28 38c4 1 8 1 11-1" stroke="#C98A3A" stroke-width="1.3" fill="none" stroke-linecap="round"/><circle cx="26" cy="36" r="1.2" fill="#9A5F22"/><circle cx="38" cy="33" r="1.2" fill="#9A5F22"/><rect x="40" y="38" width="9" height="6" rx="2" fill="#FFF2B8"/>').replace('<defs></defs>', '<defs>' + lg(a, '#F2C27A', '#C98A3A') + '</defs>');
    },
    lassi: function () {
      var a = id();
      return svg(lg(a, '#FFFFFF', '#F6EBD8', true),
        '<path d="M18 14h28l-4 40a4 4 0 0 1-4 3H26a4 4 0 0 1-4-3z" fill="url(#' + a + ')"/><path d="M18 14h28l-1 8H19z" fill="#FFF9EE"/>' +
        '<circle cx="28" cy="13" r="5" fill="#FFFDF7"/><circle cx="35" cy="12" r="6" fill="#FFFFFF"/><circle cx="40" cy="14" r="4" fill="#FFFDF7"/><circle cx="34" cy="10" r="1.6" fill="#F28C1E"/><path d="M30 10l2-2" stroke="#2FA35E" stroke-width="1.5"/>' +
        '<rect x="38" y="2" width="3" height="18" rx="1.5" transform="rotate(14 39 11)" fill="#F36B21"/>' + shine(22, 20, 3, 28));
    },
    chaat: function () {
      return svg('',
        '<path d="M8 32h48c-1 13-11 21-24 21S9 45 8 32z" fill="#3E7D5A"/><path d="M8 32h48c0 2-8 4-24 4S8 34 8 32z" fill="#5FA27C"/>' +
        '<ellipse cx="32" cy="31" rx="22" ry="6" fill="#F3E3C2"/><circle cx="22" cy="29" r="3.5" fill="#E6B05A"/><circle cx="31" cy="27" r="4" fill="#E9BB6A"/><circle cx="41" cy="29" r="3.5" fill="#E6B05A"/>' +
        '<path d="M18 31c4-3 10-3 14 0s10 3 14 0" stroke="#B5452B" stroke-width="2" fill="none"/><path d="M20 27c3 1 6 1 9 0M34 25c3 1 5 1 8 0" stroke="#FFFFFF" stroke-width="1.6" fill="none"/>' +
        '<circle cx="26" cy="26" r="1" fill="#C0392B"/><circle cx="37" cy="26" r="1" fill="#C0392B"/><circle cx="30" cy="31" r="1" fill="#2FA35E"/><circle cx="44" cy="27" r="1" fill="#2FA35E"/>');
    },
    kachori: function () {
      var a = id(), one = function (x, y) { return '<ellipse cx="' + x + '" cy="' + y + '" rx="11" ry="7" fill="url(#' + a + ')"/><path d="M' + (x - 6) + ' ' + (y - 2) + 'c3-2 9-2 12 0" stroke="#FCE1A8" stroke-width="1.4" fill="none"/><circle cx="' + (x + 3) + '" cy="' + (y + 2) + '" r="1" fill="#A8641F"/>'; };
      return D.plate(one(23, 38) + one(41, 38) + one(32, 31)).replace('<defs></defs>', '<defs>' + rg(a, '#F5C77A', '#C97C2A') + '</defs>');
    },
    poha: function () {
      return D.plate('<ellipse cx="32" cy="37" rx="18" ry="7.5" fill="#F2D35A"/><circle cx="24" cy="35" r="1.5" fill="#2FA35E"/><circle cx="36" cy="33" r="1.5" fill="#2FA35E"/><circle cx="30" cy="38" r="1.2" fill="#E0332B"/><circle cx="40" cy="38" r="1.3" fill="#8A5A2B"/><circle cx="27" cy="31" r="1.1" fill="#8A5A2B"/><path d="M42 30l6-3" stroke="#2FA35E" stroke-width="2" stroke-linecap="round"/><circle cx="44" cy="40" r="3" fill="#F7E36A"/>');
    },

    /* ---------- tab icons (v2, bright) ---------- */
    t_food: function () {
      var a = id(), b = id();
      return svg(lg(a, '#FFFFFF', '#E3E7EE') + lg(b, '#FFE0B0', '#F7B860'),
        '<path d="M24 10c-3 4 3 6 0 10M32 8c-3 4 3 6 0 10M40 10c-3 4 3 6 0 10" stroke="#fff" stroke-width="2.2" fill="none" stroke-linecap="round" opacity=".85"/>' +
        '<ellipse cx="32" cy="32" rx="22" ry="7" fill="url(#' + b + ')"/><circle cx="24" cy="30" r="3" fill="#E0332B"/><circle cx="35" cy="28" r="3.2" fill="#2FA35E"/><circle cx="42" cy="31" r="2.5" fill="#F2D35A"/><path d="M27 33c3-2 6-2 9 0" stroke="#fff" stroke-width="1.5" fill="none"/>' +
        '<path d="M9 32h46c-1 13-10 21-23 21S10 45 9 32z" fill="url(#' + a + ')"/><path d="M11 38h42" stroke="#F36B21" stroke-width="3"/>' + shine(14, 41, 12, 3) + '<rect x="24" y="52" width="16" height="4" rx="2" fill="#C9CFD8"/>');
    },
    t_shops: function () {
      var a = id();
      return svg(lg(a, '#7FB2FF', '#3D6FE0'),
        '<path d="M8 22l4-12h40l4 12z" fill="#FF5A7A"/><path d="M8 22h48v4a6 6 0 0 1-8 5 6 6 0 0 1-8 0 6 6 0 0 1-8 0 6 6 0 0 1-8 0 6 6 0 0 1-8 0 6 6 0 0 1-8-5z" fill="#FFFFFF"/><path d="M16 10l-2 12M26 10l-1 12M38 10l1 12M48 10l2 12" stroke="#E63E62" stroke-width="2"/>' +
        '<rect x="11" y="30" width="42" height="24" rx="3" fill="url(#' + a + ')"/><rect x="16" y="35" width="13" height="19" rx="2" fill="#FFE27A"/><rect x="33" y="35" width="15" height="10" rx="2" fill="#DFF0FF"/>' +
        '<path d="M40 40l2-6h8l2 6z" fill="#2FA35E"/><rect x="43" y="38" width="12" height="13" rx="2" fill="#FF8A3D"/><path d="M46 38v-3a3 3 0 0 1 6 0v3" stroke="#B9380A" stroke-width="1.5" fill="none"/>');
    },
    t_bike: function () {
      return svg('',
        '<path d="M20 44l6-12h12l8 12" stroke="#2A1F2D" stroke-width="3" fill="none" stroke-linejoin="round"/>' +
        '<path d="M12 40c0-6 6-8 12-8h12l6-8h6l-4 10c6 1 9 4 9 8z" fill="#3D7BE8"/><path d="M14 40h40" stroke="#2A5DC4" stroke-width="2"/>' +
        '<rect x="22" y="14" width="13" height="18" rx="5" fill="#F36B21" transform="rotate(14 28 23)"/><circle cx="26" cy="10" r="6.5" fill="#FFFFFF"/><path d="M19.5 10a6.5 6.5 0 0 1 13 0z" fill="#E0332B"/><rect x="26" y="9" width="6" height="3" rx="1.5" fill="#2A1F2D"/>' +
        '<path d="M32 22l10 2" stroke="#F36B21" stroke-width="4" stroke-linecap="round"/><path d="M26 32l6 10" stroke="#3A3F58" stroke-width="5" stroke-linecap="round"/>' +
        '<circle cx="17" cy="47" r="7.5" fill="#2A1F2D"/><circle cx="17" cy="47" r="3" fill="#C9D3DA"/><circle cx="49" cy="47" r="7.5" fill="#2A1F2D"/><circle cx="49" cy="47" r="3" fill="#C9D3DA"/>');
    },
    t_parcel: function () {
      return svg('',
        '<path d="M4 22h28v26H4z" fill="#FFFFFF"/><path d="M32 28h12l8 10v10H32z" fill="#3D7BE8"/><path d="M36 31h7l5 7H36z" fill="#DFF0FF"/>' +
        '<rect x="4" y="22" width="28" height="26" rx="2" fill="#F7F9FC"/><path d="M8 18l9-6 9 6v10H8z" fill="#E7A764"/><path d="M8 18l9-6 9 6-9 5z" fill="#F7C98C"/><path d="M15 15l6-3" stroke="#FBE7C6" stroke-width="2"/>' +
        '<rect x="10" y="30" width="16" height="12" rx="2" fill="#D98A40"/><rect x="10" y="30" width="16" height="4" fill="#F0B068"/>' +
        '<circle cx="14" cy="50" r="6" fill="#2A1F2D"/><circle cx="14" cy="50" r="2.4" fill="#C9D3DA"/><circle cx="44" cy="50" r="6" fill="#2A1F2D"/><circle cx="44" cy="50" r="2.4" fill="#C9D3DA"/>' +
        '<path d="M56 30h6M54 36h8" stroke="#fff" stroke-width="2.4" stroke-linecap="round" opacity=".8"/>');
    },

    auto: function () {
      return svg('',
        '<path d="M10 22c0-6 5-10 12-10h14c8 0 14 6 16 14l2 6v12H10z" fill="#2A1F2D"/>' +
        '<path d="M10 28h44v18H10z" fill="#F2C230"/><path d="M10 28h22v18H10z" fill="#3FA66B"/><rect x="14" y="30" width="14" height="10" rx="2" fill="#DFF0E6"/><path d="M36 30h12l4 10H36z" fill="#FFF6D6"/>' +
        '<rect x="54" y="38" width="5" height="3" rx="1.5" fill="#FFF1A8"/>' + wheel(18, 49, 6) + wheel(48, 49, 6));
    },
    erick: function () {
      return svg('',
        '<rect x="8" y="12" width="46" height="5" rx="2.5" fill="#2A5DC4"/><path d="M12 17v15M50 17v15" stroke="#9AA7B1" stroke-width="2"/>' +
        '<path d="M8 32h48v12a3 3 0 0 1-3 3H11a3 3 0 0 1-3-3z" fill="#3D7BE8"/><rect x="14" y="26" width="20" height="8" rx="3" fill="#8A5A3C"/><rect x="38" y="26" width="12" height="8" rx="3" fill="#8A5A3C"/>' +
        '<path d="M52 32l6-10" stroke="#2A1F2D" stroke-width="2.5" stroke-linecap="round"/>' + wheel(16, 49, 6) + wheel(46, 49, 6));
    },
    pin: function (c) {
      c = c || '#D6246E';
      return svg('', '<ellipse cx="32" cy="56" rx="10" ry="3" fill="#2A1530" opacity=".15"/><path d="M32 56C20 42 13 33 13 24a19 19 0 0 1 38 0c0 9-7 18-19 32z" fill="' + c + '"/><circle cx="32" cy="24" r="8" fill="#fff"/>');
    },
    /* ---------- shop items ---------- */
    packet: function (c, lbl) {
      var a = id();
      return svg(lg(a, lighten(c, .3), darken(c, .12)),
        '<path d="M16 10h32l-2 5v40a3 3 0 0 1-3 3H21a3 3 0 0 1-3-3V15z" fill="url(#' + a + ')"/><path d="M16 10h32l-2 5H18z" fill="' + darken(c, .25) + '"/>' +
        '<path d="M19 8l2 2M24 8l2 2M29 8l2 2M34 8l2 2M39 8l2 2M44 8l2 2" stroke="' + darken(c, .25) + '" stroke-width="1.5"/>' +
        '<rect x="19" y="27" width="26" height="13" rx="6.5" fill="#fff"/><text x="32" y="36" text-anchor="middle" font-family="Lexend,sans-serif" font-size="6.4" font-weight="800" letter-spacing=".2" fill="' + darken(c, .35) + '">' + (lbl || '') + '</text>' +
        shine(21, 17, 3, 8) + shine(21, 43, 3, 9));
    },
    bottle: function (c, cap) {
      var a = id();
      return svg(lg(a, lighten(c, .35), c, true),
        '<rect x="27" y="5" width="10" height="7" rx="2" fill="' + (cap || '#2A1530') + '"/><path d="M27 12h10v5l6 7v30a4 4 0 0 1-4 4H25a4 4 0 0 1-4-4V24l6-7z" fill="url(#' + a + ')"/>' +
        '<rect x="21" y="30" width="22" height="14" fill="#fff" opacity=".9"/><rect x="25" y="34" width="14" height="2" rx="1" fill="' + darken(c, .2) + '"/><rect x="25" y="38" width="9" height="2" rx="1" fill="' + darken(c, .2) + '" opacity=".6"/>' +
        shine(24, 20, 3, 30));
    },
    bowl: function (c, kind) {
      var a = id(), top;
      if (kind === 'balls') top = '<ellipse cx="32" cy="33" rx="22" ry="5" fill="#F2D27A"/><circle cx="23" cy="30" r="6" fill="#FFF3D6" stroke="#E8C37A" stroke-width=".8"/><circle cx="33" cy="27" r="6.5" fill="#FFF0CC" stroke="#E8C37A" stroke-width=".8"/><circle cx="42" cy="31" r="6" fill="#FFF3D6" stroke="#E8C37A" stroke-width=".8"/>';
      else top = '<ellipse cx="32" cy="32" rx="22" ry="6" fill="' + c + '"/><ellipse cx="27" cy="31" rx="6" ry="1.6" fill="#fff" opacity=".5"/>';
      return svg(lg(a, '#F4F7FA', '#B9C5D0'),
        top + '<path d="M9 33h46c-1 12-10 20-23 20S10 45 9 33z" fill="url(#' + a + ')"/><path d="M24 53h16l2 4H22z" fill="#9AA7B1"/>' + shine(14, 37, 10, 2.5));
    },
    jar: function (c, lid) {
      var a = id();
      return svg(lg(a, lighten(c, .3), darken(c, .1), true),
        '<rect x="18" y="8" width="28" height="9" rx="3" fill="' + (lid || '#D6246E') + '"/><rect x="18" y="14" width="28" height="3" fill="#000" opacity=".12"/>' +
        '<path d="M17 18h30a3 3 0 0 1 3 3v31a5 5 0 0 1-5 5H19a5 5 0 0 1-5-5V21a3 3 0 0 1 3-3z" fill="url(#' + a + ')"/>' +
        '<rect x="18" y="30" width="28" height="14" rx="2" fill="#fff" opacity=".92"/><rect x="22" y="34" width="20" height="2.2" rx="1" fill="' + darken(c, .3) + '"/><rect x="22" y="38.5" width="12" height="2" rx="1" fill="' + darken(c, .3) + '" opacity=".6"/>' +
        shine(18, 21, 3, 30));
    },
    potato: function () {
      var a = id();
      return svg(rg(a, '#E9C08A', '#B5803F'),
        '<path d="M10 36c0-12 10-20 22-18s22 4 22 16-10 20-24 20S10 46 10 36z" fill="url(#' + a + ')"/>' +
        '<circle cx="22" cy="32" r="1.5" fill="#8A5A2B"/><circle cx="38" cy="28" r="1.5" fill="#8A5A2B"/><circle cx="44" cy="40" r="1.5" fill="#8A5A2B"/><circle cx="28" cy="44" r="1.5" fill="#8A5A2B"/>' + shine(16, 27, 8, 3));
    },
    onion: function () {
      var a = id();
      return svg(rg(a, '#D98AC0', '#8E2E6E'),
        '<path d="M32 6c1 5 0 9-2 12" stroke="#5FAA4E" stroke-width="3" fill="none" stroke-linecap="round"/>' +
        '<path d="M32 16c14 4 22 14 20 26-2 10-11 15-20 15S14 52 12 42c-2-12 6-22 20-26z" fill="url(#' + a + ')"/>' +
        '<path d="M32 18c-6 10-6 26 0 38M32 18c6 10 6 26 0 38" stroke="#fff" stroke-width="1.2" opacity=".35" fill="none"/>' + shine(18, 30, 4, 12));
    },
    tomato: function () {
      var a = id();
      return svg(rg(a, '#FF8A7A', '#D92B22'),
        '<circle cx="32" cy="36" r="20" fill="url(#' + a + ')"/><path d="M32 18l4-5 2 6 6-1-4 5 6 2-8 1-6 3-6-3-8-1 6-2-4-5 6 1 2-6z" fill="#2FA35E"/>' + shine(19, 30, 6, 9));
    },
    chilli: function () {
      return svg('',
        '<path d="M12 46c14 0 30-10 36-28l6 2c-5 22-26 36-42 32-4-1-4-6 0-6z" fill="#2FA35E"/><path d="M14 47c12-1 26-10 31-24" stroke="#fff" stroke-width="2" opacity=".4" fill="none" stroke-linecap="round"/>' +
        '<path d="M48 18c0-5 3-9 7-10 1 3-1 7-3 9z" fill="#1E6B40"/>' +
        '<path d="M20 54c10 0 20-7 24-18l5 1c-4 13-16 22-29 21-3 0-3-4 0-4z" fill="#3DBB6B" opacity=".9"/>');
    },
    bandage: function () {
      var a = id();
      return svg(lg(a, '#FFFFFF', '#E6ECF2', true),
        '<ellipse cx="26" cy="22" rx="14" ry="6" fill="#F2F5F8"/><rect x="12" y="22" width="28" height="22" fill="url(#' + a + ')"/><ellipse cx="26" cy="44" rx="14" ry="6" fill="#DCE3EA"/><ellipse cx="26" cy="22" rx="14" ry="6" fill="#fff"/><ellipse cx="26" cy="22" rx="5" ry="2" fill="#C9D3DA"/>' +
        '<path d="M40 32h16l-2 8H40z" fill="#fff"/><path d="M44 34h8M44 37h6" stroke="#C9D3DA" stroke-width="1.2"/><path d="M22 31h8M26 27v8" stroke="#E0332B" stroke-width="3"/>');
    },
    thermo: function () {
      return svg('',
        '<rect x="12" y="10" width="14" height="44" rx="7" fill="#fff" transform="rotate(30 32 32)"/><rect x="16" y="14" width="6" height="30" rx="3" fill="#DCEBFF" transform="rotate(30 32 32)"/>' +
        '<rect x="17" y="30" width="4" height="15" rx="2" fill="#E0332B" transform="rotate(30 32 32)"/><circle cx="19" cy="49" r="5" fill="#E0332B" transform="rotate(30 32 32)"/>' +
        '<rect x="30" y="34" width="22" height="10" rx="3" fill="#1D7BD8"/><text x="41" y="41.5" text-anchor="middle" font-family="Lexend,sans-serif" font-size="6" font-weight="800" fill="#fff">98.6</text>');
    },
    sweetbox: function (c) {
      var a = id();
      return svg(lg(a, lighten(c, .25), darken(c, .15)),
        '<rect x="8" y="26" width="48" height="28" rx="4" fill="url(#' + a + ')"/><path d="M8 26l6-10h36l6 10z" fill="' + lighten(c, .45) + '"/>' +
        '<circle cx="20" cy="20" r="5" fill="#F7A93B"/><circle cx="32" cy="18" r="5.5" fill="#FFC14D"/><circle cx="44" cy="20" r="5" fill="#F7A93B"/>' +
        '<rect x="20" y="34" width="24" height="12" rx="6" fill="#fff"/><path d="M27 43v-6l5 4 5-4v6" fill="none" stroke="' + darken(c, .3) + '" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>');
    },
    paneer: function () {
      return svg('',
        '<path d="M10 26l20-10 24 8-20 12z" fill="#FFFDF4"/><path d="M10 26l24 10v18L10 44z" fill="#F4EBD3"/><path d="M34 36l20-12v18L34 54z" fill="#E8DCBC"/>' +
        '<circle cx="18" cy="36" r="1.5" fill="#E0D2AE"/><circle cx="26" cy="42" r="1.5" fill="#E0D2AE"/><circle cx="44" cy="40" r="1.5" fill="#D2C49E"/><circle cx="30" cy="24" r="1.5" fill="#EFE5CA"/>');
    },
    incense: function () {
      var a = id();
      return svg(lg(a, '#C4A8FF', '#6A3FD8'),
        '<path d="M26 6c-3 4 3 6 0 10M34 4c-3 4 3 6 0 10M42 7c-3 4 3 6 0 10" stroke="#B9AFC5" stroke-width="1.6" fill="none" stroke-linecap="round"/>' +
        '<path d="M26 18v18M34 16v20M42 19v17" stroke="#7A3A0B" stroke-width="1.8"/><circle cx="26" cy="18" r="1.4" fill="#FF6B2B"/><circle cx="34" cy="16" r="1.4" fill="#FF6B2B"/><circle cx="42" cy="19" r="1.4" fill="#FF6B2B"/>' +
        '<rect x="16" y="32" width="32" height="24" rx="3" fill="url(#' + a + ')"/><rect x="21" y="38" width="22" height="10" rx="5" fill="#FFB020"/>');
    },
    thali: function () {
      return svg('',
        '<ellipse cx="32" cy="42" rx="27" ry="11" fill="#AEBAC5"/><ellipse cx="32" cy="40" rx="27" ry="10" fill="#E3E9EE"/><ellipse cx="32" cy="40" rx="20" ry="6.5" fill="#CBD4DC"/>' +
        '<path d="M22 37c0-3 4-4 6-2 1 1 1 3 0 4z" fill="#E0332B"/><circle cx="34" cy="38" r="3" fill="#FFB020"/><path d="M40 37h6c0 3-6 3-6 0z" fill="#F7922B"/><path d="M43 33c1 1 1 2 0 4" stroke="#FFC34D" stroke-width="1.5"/>' +
        '<circle cx="28" cy="43" r="2" fill="#2FA35E"/>');
    }
  };


  /* ---------- banner scenes, 360 × 220 ---------- */
  function nest(key, x, y, w) { return D[key]().replace('<svg class="ill" viewBox="0 0 64 64"', '<svg x="' + x + '" y="' + y + '" width="' + w + '" height="' + w + '" viewBox="0 0 64 64"'); }
  function sceneSvg(defs, body) {
    var sky = id();
    return '<svg class="scene" viewBox="0 0 360 264" preserveAspectRatio="xMidYMax slice" aria-hidden="true"><defs>' + lg(sky, '#FFF8FA', '#F6E4EC') + defs + '</defs>' +
      '<rect width="360" height="264" fill="url(#' + sky + ')"/>' +
      '<circle cx="324" cy="34" r="20" fill="#F6D9A8" opacity=".6"/><g transform="translate(0 44)">' +
      '<path d="M0 150h40v-30h26v18h22v-40h30v52h24v-26h20v26h18v-60h34v60h22v-34h28v34h26v-22h24v22h46v70H0z" fill="#EFDCE5"/>' +
      '<path d="M134 108h4v8h-4zM248 106h5v8h-5zM40 128h4v6h-4zM96 116h4v8h-4z" fill="#F9EEF3"/>' +
      '<rect y="186" width="360" height="34" fill="#E9D6DF"/><path d="M0 200h360" stroke="#F7EBF0" stroke-width="3" stroke-dasharray="18 14"/>' + body + '</g></svg>';
  }
  function plant(x, y, s) {
    s = s || 1;
    return '<g transform="translate(' + x + ' ' + y + ') scale(' + s + ')"><path d="M-8 0h16l-2 12H-6z" fill="#B9683F"/><path d="M0 0c-10-6-12-18-4-22 2 8 4 14 4 22zM0 0c10-6 12-18 4-22-2 8-4 14-4 22zM0 0c0-12-2-22 0-28 2 6 0 16 0 28z" fill="#6FA287"/></g>';
  }
  function tree(x, y, s) {
    return '<g transform="translate(' + x + ' ' + y + ') scale(' + (s || 1) + ')"><rect x="-3" y="-10" width="6" height="22" rx="2" fill="#8A5A3C"/><circle cx="0" cy="-22" r="18" fill="#86B79A"/><circle cx="-10" cy="-14" r="11" fill="#6FA287"/><circle cx="11" cy="-16" r="12" fill="#7AAB8F"/></g>';
  }
  /* standing person, feet at (x,y) */
  function man(x, y, o) {
    o = o || {};
    var shirt = o.shirt || '#4A1236', pant = o.pant || '#5E5462', skin = o.skin || '#E2AE86';
    return '<g transform="translate(' + x + ' ' + y + ')">' +
      '<rect x="-9" y="-34" width="8" height="33" rx="3.5" fill="' + pant + '"/><rect x="1" y="-34" width="8" height="33" rx="3.5" fill="' + pant + '"/>' +
      '<ellipse cx="-5" cy="-1" rx="6" ry="2.6" fill="#2A1F2D"/><ellipse cx="5" cy="-1" rx="6" ry="2.6" fill="#2A1F2D"/>' +
      '<rect x="-12" y="-66" width="24" height="36" rx="10" fill="' + shirt + '"/>' +
      (o.badge ? '<rect x="2" y="-58" width="7" height="7" rx="2" fill="#D6246E"/><path d="M3.6 -52.6v-3.4l1.9 1.6 1.9-1.6v3.4" stroke="#fff" stroke-width=".9" fill="none"/>' : '') +
      '<rect x="-4" y="-72" width="8" height="8" rx="3" fill="' + skin + '"/><circle cx="0" cy="-80" r="10" fill="' + skin + '"/>' +
      (o.helmet ? '<path d="M-11 -80a11 11 0 0 1 22 0v2h-22z" fill="#D6246E"/><path d="M-11 -80h14v4h-14z" fill="#A3164F"/><rect x="3" y="-81" width="9" height="5" rx="2" fill="#F3EBF1" opacity=".9"/>'
                : '<path d="M-10 -82c0-8 6-11 10-11s10 3 10 11c-3-3-6-4-10-4s-7 1-10 4z" fill="#2A1F2D"/>') +
      (o.arms || '<path d="M-11 -60l-6 22M11 -60l6 22" stroke="' + shirt + '" stroke-width="7" stroke-linecap="round"/>') +
      '</g>';
  }
  function woman(x, y, o) {
    o = o || {};
    var saree = o.saree || '#E7A9C3', skin = o.skin || '#D99E78';
    return '<g transform="translate(' + x + ' ' + y + ')">' +
      '<path d="M-12 -62h24l6 60h-36z" fill="' + saree + '"/><path d="M-12 -62h24l-4 22h-20z" fill="#E5A447"/>' +
      '<path d="M8 -62c6 10 6 30 10 60h-8c-2-24-4-44-2-60z" fill="#D6246E" opacity=".75"/>' +
      '<ellipse cx="-6" cy="-1" rx="5" ry="2.2" fill="#2A1F2D"/><ellipse cx="6" cy="-1" rx="5" ry="2.2" fill="#2A1F2D"/>' +
      '<rect x="-4" y="-68" width="8" height="8" rx="3" fill="' + skin + '"/><circle cx="0" cy="-76" r="9.5" fill="' + skin + '"/>' +
      '<path d="M-10 -77c0-9 5-12 10-12s10 3 10 12c-2-4-6-6-10-6s-8 2-10 6z" fill="#2A1F2D"/><circle cx="9" cy="-80" r="5" fill="#2A1F2D"/>' +
      '<circle cx="0" cy="-82" r="1.4" fill="#D6246E"/>' +
      (o.arms || '<path d="M-11 -58l-5 22M11 -58l5 22" stroke="' + skin + '" stroke-width="5.5" stroke-linecap="round"/>') +
      '</g>';
  }
  function scooter(x, y, flip, rider) {
    var g = '<g transform="translate(' + x + ' ' + y + ')' + (flip ? ' scale(-1 1)' : '') + '">' +
      '<rect x="-40" y="-60" width="34" height="30" rx="5" fill="#D6246E"/><rect x="-40" y="-60" width="34" height="7" rx="3" fill="#A3164F"/>' +
      '<rect x="-30" y="-47" width="14" height="11" rx="2" fill="#fff"/><path d="M-27 -38.5v-5.5l4 3.5 4-3.5v5.5" stroke="#D6246E" stroke-width="1.6" fill="none" stroke-linecap="round" stroke-linejoin="round"/>' +
      '<path d="M-44 -18c0-10 8-14 18-14h26l10-20h8l-6 22c8 2 12 6 12 12z" fill="#B23A6E"/><path d="M-6 -30h16" stroke="#8E1D52" stroke-width="3"/>' +
      '<path d="M10 -52l6-4" stroke="#2A1F2D" stroke-width="3" stroke-linecap="round"/><circle cx="18" cy="-40" r="3" fill="#F6E8C0"/>' +
      '<circle cx="-30" cy="-8" r="10" fill="#2A1F2D"/><circle cx="-30" cy="-8" r="4" fill="#C9C2CC"/><circle cx="16" cy="-8" r="10" fill="#2A1F2D"/><circle cx="16" cy="-8" r="4" fill="#C9C2CC"/>';
    if (rider) g +=
      '<path d="M-14 -34l8 0 6 16" stroke="#5E5462" stroke-width="8" stroke-linecap="round" fill="none"/>' +
      '<rect x="-24" y="-66" width="22" height="34" rx="9" fill="#4A1236" transform="rotate(12 -13 -49)"/>' +
      '<path d="M-6 -58l18 4" stroke="#4A1236" stroke-width="6.5" stroke-linecap="round"/>' +
      '<circle cx="-8" cy="-76" r="10" fill="#E2AE86"/><path d="M-19 -76a11 11 0 0 1 22 0v2h-22z" fill="#D6246E"/><rect x="-6" y="-77" width="9" height="5" rx="2" fill="#F3EBF1"/>';
    return g + '</g>';
  }
  function speed(x, y) { return '<path d="M' + x + ' ' + y + 'h-26M' + (x - 6) + ' ' + (y + 10) + 'h-34M' + x + ' ' + (y + 20) + 'h-22" stroke="#E2C6D3" stroke-width="3" stroke-linecap="round"/>'; }

  var SC = {
    shops: function () {
      return sceneSvg('',
        '<rect x="246" y="96" width="114" height="92" fill="#FFF8F3"/><path d="M238 96h130v-12H238z" fill="#8E1D52"/>' +
        '<rect x="282" y="116" width="40" height="72" rx="3" fill="#6B2A48"/><rect x="286" y="120" width="32" height="68" rx="2" fill="#F3E2EA"/><circle cx="314" cy="156" r="2" fill="#E5A447"/>' +
        '<rect x="330" y="116" width="22" height="22" rx="3" fill="#F3EBF1"/><path d="M341 116v22M330 127h22" stroke="#E2C6D3" stroke-width="2"/>' +
        '<path d="M276 104c8 6 44 6 52 0" stroke="#E5A447" stroke-width="3" fill="none" stroke-dasharray="1 5" stroke-linecap="round"/>' +
        plant(262, 176, .9) + scooter(118, 190, false, false) + tree(30, 176, .9) +
        man(214, 190, { helmet: true, badge: true, arms: '<path d="M-11 -60l14 20M11 -60l22 14" stroke="#4A1236" stroke-width="7" stroke-linecap="round"/>' }) +
        nest('bag', 222, 122, 34) +
        woman(272, 190, { arms: '<path d="M-11 -58l-16 20M11 -58l-2 22" stroke="#D99E78" stroke-width="5.5" stroke-linecap="round"/>' }));
    },
    vehicle: function () {
      return sceneSvg('',
        tree(36, 182, 1) + tree(84, 186, .8) + '<path d="M120 186l40-70h40l40 70z" fill="#E3CDD8" opacity=".6"/>' +
        nest('suv', 150, 78, 150) + speed(150, 140) +
        '<g transform="translate(320 186)"><rect x="-10" y="-34" width="20" height="34" rx="9" fill="#F7F1F4"/><rect x="-10" y="-34" width="20" height="10" rx="5" fill="#D6246E"/></g>' + plant(110, 176, .8));
    },
    services: function () {
      var lights = '';
      for (var i = 0; i < 9; i++) lights += '<circle cx="' + (150 + i * 24) + '" cy="' + (70 + Math.sin(i / 8 * Math.PI) * 14) + '" r="5" fill="' + (i % 2 ? '#E5A447' : '#EDB862') + '"/>';
      return sceneSvg('',
        '<path d="M140 92l70-36 70 36 70-36v6l-70 36-70-36-70 36z" fill="#B23A6E"/>' +
        '<path d="M140 92h210v96H140z" fill="#FFF8F3" opacity=".9"/><path d="M140 92l35 22 35-22 35 22 35-22 35 22 35-22" fill="none" stroke="#E7A9C3" stroke-width="5"/>' +
        '<path d="M150 70q96 26 192 0" stroke="#6FA287" stroke-width="2" fill="none"/>' + lights +
        '<rect x="160" y="150" width="80" height="38" rx="4" fill="#E9D6DF"/>' + nest('kalash', 168, 104, 54) + nest('kadhai', 250, 128, 64) + nest('diya', 206, 140, 36) + tree(40, 182, .9) + plant(116, 176, .9));
    },
    ride: function () {
      return sceneSvg('', tree(300, 182, 1) + tree(340, 186, .8) + speed(90, 140) + scooter(190, 190, false, true) + plant(40, 176, .8));
    },
    parcel: function () {
      return sceneSvg('',
        tree(326, 182, .9) + nest('parcel', 236, 118, 70) + nest('docs', 288, 136, 50) + speed(70, 140) +
        scooter(150, 190, false, true) + plant(40, 176, .8));
    }
  };

  window.MKILL = function (key) {
    var f = D[key];
    if (!f) return '';
    var args = Array.prototype.slice.call(arguments, 1).map(function (a) { return typeof a === 'string' ? calm(a) : a; });
    return f.apply(null, Array.prototype.slice.call(arguments, 1));
  };
  window.MKSCENE = function (key, lang) { var f = SC[key]; return f ? f(lang || 'en') : ''; };
})();
