// ============================================================================
//  Teacher App — owner, 11 Nov 2026 ("astha maam ko padne keliye add kar dijiye taaki vo study karke apna exam de").
//  Loaded LAST. Adds "Meri Exam Taiyari" (school/padhai/: Real Analysis 25 Nov, Hydrodynamics 28 Nov 2026) for
//  Astha Ma'am only (name contains astha/aastha) — the Principal also sees it, to check. Static pages, no data
//  is read or written; the "padh liya" ticks live in the phone's localStorage inside padhai/.
//  1. ☰ Menu: group "Meri padhai" → opens padhai/index.html.
//  2. Home (teacher mode): one card at the top with the exam countdown.
// ============================================================================
'use strict';
(function () {
  function studyOn() { try { return isP() || /a+stha/i.test(myName() || ''); } catch (e) { return false; } }
  function openStudy() { location.href = 'padhai/index.html'; }
  window.openStudy = openStudy;

  // 1. Menu entry (MENU is a const array in teacher-nav.js; openMenu() reads it each time)
  if (typeof MENU !== 'undefined' && Array.isArray(MENU) && !MENU.some(g => g[0] === 'Meri padhai')) {
    MENU.splice(0, 0, ['Meri padhai', [['study', {}, 'book', 'Meri Exam Taiyari', 'Real Analysis · Hydrodynamics ebook']]]);
    const _open = openMenu;
    openMenu = function () {
      const keep = MENU[0];
      if (!studyOn() && keep && keep[0] === 'Meri padhai') { MENU.shift(); try { _open(); } finally { MENU.unshift(keep); } return; }
      _open();
    };
  }
  const _go = go;
  go = function (tab, p) { if (tab === 'study') return openStudy(); return _go(tab, p); };

  // 2. Home card with countdown
  function daysTo(iso) { const t = new Date(todayISO() + 'T00:00:00'), d = new Date(iso + 'T00:00:00'); return Math.round((d - t) / 864e5); }
  function card() {
    const ra = daysTo('2026-11-25'), hy = daysTo('2026-11-28');
    if (hy < 0) return '';
    const left = (n, nm) => n > 0 ? `${nm}: <b>${n} din baaki</b>` : n === 0 ? `${nm}: <b>aaj exam hai — all the best!</b>` : `${nm}: ho gaya ✓`;
    return `<div class="card study-card" style="background:linear-gradient(120deg,#6d28d9,#c026d3);color:#fff;border:0">
      <h3 style="color:#fff;margin:0 0 4px">📚 Meri Exam Taiyari</h3>
      <div style="font-size:14px;line-height:1.6">${left(ra, 'Real Analysis (25 Nov)')}<br>${left(hy, 'Hydrodynamics (28 Nov)')}</div>
      <button class="btn full" style="margin-top:10px;background:#fff;color:#6d28d9;font-weight:800" onclick="openStudy()">Ebook kholiye →</button></div>`;
  }
  const _render = render;
  render = function () {
    _render();
    try {
      if (!S || TAB !== 'home' || SHEET || !studyOn()) return;
      const body = document.querySelector('.scroll .body');
      if (body && !body.querySelector('.study-card')) { const h = card(); if (h) body.insertAdjacentHTML('afterbegin', h); }
    } catch (e) { /* never break the app for this card */ }
  };
})();
