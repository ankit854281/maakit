// ============================================================================
//  Teacher App — owner, 11 Nov 2026 ("astha maam ko padne keliye add kar dijiye taaki vo study karke apna exam de").
//  Loaded LAST. Adds "Meri Exam Taiyari" (school/padhai/: Real Analysis 25 Nov, Hydrodynamics 28 Nov 2026).
//  Owner, same day: "ye sab teacher me kyu show kar rha hai bas astha me show kare" — shown ONLY when the logged-in
//  person is a teacher (not the Principal) whose own name contains astha/aastha. Nobody else sees the menu item
//  or the card. Static pages, no school data is read or written; "padh liya" ticks live in localStorage in padhai/.
//  1. ☰ Menu: group "Meri padhai". The group is added to / removed from MENU right before EVERY render, so a sheet
//     that re-renders later (sync, language change) never shows it to anyone else.
//  2. Home (teacher mode): one card at the top with the exam countdown.
// ============================================================================
'use strict';
(function () {
  const GROUP = ['Meri padhai', [['study', {}, 'book', 'Meri Exam Taiyari', 'Real Analysis · Hydrodynamics']]];
  function studyOn() {
    try { return !!(S && ME && ME.role === 'teacher' && /a+stha/i.test(myName() || '')); } catch (e) { return false; }
  }
  function syncMenu() {
    if (typeof MENU === 'undefined' || !Array.isArray(MENU)) return;
    const i = MENU.indexOf(GROUP), on = studyOn();
    if (on && i < 0) MENU.unshift(GROUP);
    if (!on && i >= 0) MENU.splice(i, 1);
  }
  function openStudy() { if (studyOn()) location.href = 'padhai/index.html'; }
  window.openStudy = openStudy;

  const _go = go;
  go = function (tab, p) { if (tab === 'study') return openStudy(); return _go(tab, p); };

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
    try { syncMenu(); } catch (e) { /* ignore */ }
    _render();
    try {
      if (!S || TAB !== 'home' || SHEET || !studyOn()) return;
      const body = document.querySelector('.scroll .body');
      if (body && !body.querySelector('.study-card')) { const h = card(); if (h) body.insertAdjacentHTML('afterbegin', h); }
    } catch (e) { /* never break the app for this card */ }
  };
})();
