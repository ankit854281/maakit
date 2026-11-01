/* Maakit trial app — drawn illustrations (flat, soft-shaded, no outlines).
 * Every drawing is a 64×64 SVG. MKILL(key, ...args) returns the markup. */
(function () {
  'use strict';
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
      col = col || '#1A9BB8';
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

  window.MKILL = function (key) {
    var f = D[key];
    if (!f) return '';
    return f.apply(null, Array.prototype.slice.call(arguments, 1));
  };
})();
