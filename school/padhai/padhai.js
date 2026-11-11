/* Astha Ma'am — Exam Taiyari: topic "padh liya" ticks (saved on this phone) + maths rendering (KaTeX). */
(function () {
  'use strict';
  var KEY = 'padhai_done_' + (document.body.getAttribute('data-book') || 'book');
  var saved = {};
  try { saved = JSON.parse(localStorage.getItem(KEY) || '{}') || {}; } catch (e) { saved = {}; }
  function save() { try { localStorage.setItem(KEY, JSON.stringify(saved)); } catch (e) { /* storage off: ticks only for this visit */ } }
  function progress() {
    var all = document.querySelectorAll('.card[data-id]').length, n = 0;
    Array.prototype.forEach.call(document.querySelectorAll('.card[data-id]'), function (c) { if (saved[c.getAttribute('data-id')]) n++; });
    var p = document.getElementById('prog'); if (p) p.textContent = n + '/' + all + ' padh liya';
  }
  Array.prototype.forEach.call(document.querySelectorAll('.card[data-id]'), function (c) {
    var id = c.getAttribute('data-id');
    var lab = document.createElement('label'); lab.className = 'done';
    lab.innerHTML = '<input type="checkbox"> Yeh topic padh liya ✓';
    var box = lab.querySelector('input'); box.checked = !!saved[id]; if (box.checked) c.classList.add('is-done');
    box.addEventListener('change', function () { if (box.checked) saved[id] = 1; else delete saved[id]; c.classList.toggle('is-done', box.checked); save(); progress(); });
    c.appendChild(lab);
  });
  progress();
  function render() {
    if (window.renderMathInElement) {
      try { window.renderMathInElement(document.body, { delimiters: [{ left: '$$', right: '$$', display: true }, { left: '\\(', right: '\\)', display: false }], throwOnError: false }); } catch (e) { /* show plain text */ }
    }
  }
  if (document.readyState === 'complete') render(); else window.addEventListener('load', render);
})();
