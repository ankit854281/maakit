'use strict';
(() => {
  const root = document.querySelector('.search-page');
  if (!root) return;
  const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
  root.querySelectorAll('.search-rail').forEach((rail, index) => {
    if (!rail.children.length) return;
    rail.id = 'search-rail-' + index;
    const controls = document.createElement('div');
    controls.className = 'search-slider-controls';
    const buttons = [-1, 1].map(direction => {
      const button = document.createElement('button');
      button.type = 'button';
      button.textContent = direction < 0 ? '‹' : '›';
      button.setAttribute('aria-label', direction < 0 ? root.dataset.previous : root.dataset.next);
      button.setAttribute('aria-controls', rail.id);
      button.addEventListener('click', () => {
        const card = rail.firstElementChild;
        const step = card.getBoundingClientRect().width + parseFloat(getComputedStyle(rail).gap);
        rail.scrollBy({left: direction * step, behavior: reduced.matches ? 'instant' : 'smooth'});
      });
      controls.append(button);
      return button;
    });
    rail.before(controls);
    const update = () => {
      const end = rail.scrollWidth - rail.clientWidth;
      controls.hidden = end <= 2;
      buttons[0].disabled = rail.scrollLeft <= 2;
      buttons[1].disabled = rail.scrollLeft >= end - 2;
    };
    rail.addEventListener('scroll', update, {passive:true});
    new ResizeObserver(update).observe(rail);
    update();
  });
})();
