/* Saaman ki photo slide — ungli se khiskao ya ‹ › dabao.
   Track me CSS scroll-snap hai, isliye JS na chale tab bhi
   photo khisak jaati hain; JS sirf button, gol nishaan aur
   "2 / 5" ginti chalata hai. */
(function () {
  function setup(box) {
    var track = box.querySelector('.ps-track');
    var slides = box.querySelectorAll('.ps-slide');
    var dots = box.querySelectorAll('.ps-dots i');
    var count = box.querySelector('.ps-count b');
    if (!track || slides.length < 2) return;
    function cur() {
      var w = track.clientWidth || 1;
      return Math.max(0, Math.min(slides.length - 1, Math.round(track.scrollLeft / w)));
    }
    function mark() {
      var n = cur();
      for (var i = 0; i < dots.length; i++) dots[i].classList.toggle('on', i === n);
      if (count) count.textContent = n + 1;
    }
    function go(n) {
      n = (n + slides.length) % slides.length;
      track.scrollTo({ left: n * track.clientWidth, behavior: 'smooth' });
    }
    var prev = box.querySelector('.ps-prev'), next = box.querySelector('.ps-next');
    if (prev) prev.addEventListener('click', function () { go(cur() - 1); });
    if (next) next.addEventListener('click', function () { go(cur() + 1); });
    for (var i = 0; i < dots.length; i++) (function (k) {
      dots[k].addEventListener('click', function () { go(k); });
    })(i);
    track.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowLeft') { e.preventDefault(); go(cur() - 1); }
      if (e.key === 'ArrowRight') { e.preventDefault(); go(cur() + 1); }
    });
    var t = null;
    track.addEventListener('scroll', function () { clearTimeout(t); t = setTimeout(mark, 60); }, { passive: true });
    mark();
  }
  function all() {
    var boxes = document.querySelectorAll('[data-slider]');
    for (var i = 0; i < boxes.length; i++) setup(boxes[i]);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', all);
  else all();
})();
