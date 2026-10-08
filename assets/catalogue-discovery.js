'use strict';
(function () {
  var rail = document.getElementById('shop-category-rail');
  var button = document.querySelector('[data-rail-toggle="shop-category-rail"]');
  if (!rail || !button) return;
  var motion = window.matchMedia('(prefers-reduced-motion: reduce)');
  var playing = false; // Customers choose when category movement starts.
  var interacting = false;
  function label() {
    button.textContent = playing ? button.dataset.playing : button.dataset.paused;
    button.setAttribute('aria-pressed', String(playing));
  }
  button.addEventListener('click', function () { playing = !playing; label(); });
  rail.addEventListener('mouseenter', function () { interacting = true; });
  rail.addEventListener('mouseleave', function () { interacting = false; });
  rail.addEventListener('pointerdown', function () { playing = false; label(); });
  rail.addEventListener('focusin', function () { playing = false; label(); });
  motion.addEventListener('change', function () { if (motion.matches) { playing = false; label(); } });
  label();
  window.setInterval(function () {
    if (!playing || interacting || document.hidden || rail.scrollWidth <= rail.clientWidth) return;
    var end = rail.scrollWidth - rail.clientWidth;
    rail.scrollTo({ left: rail.scrollLeft >= end - 4 ? 0 : Math.min(end, rail.scrollLeft + 160), behavior: motion.matches ? 'auto' : 'smooth' });
  }, 4200);
})();
