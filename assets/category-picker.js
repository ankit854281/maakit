'use strict';
document.querySelectorAll('[data-category-picker]').forEach(function (picker) {
  var query = picker.querySelector('.category-query');
  var select = picker.querySelector('select');
  var preview = picker.querySelector('.category-preview');
  var choices = Array.from(select.options).slice(1).map(function (option) { return option.cloneNode(true); });
  function show() {
    var option = select.selectedOptions[0];
    preview.hidden = !option || !option.value;
    if (!preview.hidden) preview.textContent = option.dataset.count + ' ' + preview.dataset.countLabel + ' — ' + option.dataset.preview;
  }
  query.addEventListener('input', function () {
    var words = query.value.toLocaleLowerCase().trim().split(/\s+/).filter(Boolean);
    var selected = select.value;
    select.replaceChildren(new Option(select.options[0].text, ''));
    choices.forEach(function (option) {
      var text = option.dataset.search.toLocaleLowerCase();
      if (words.every(function (word) { return text.includes(word); })) select.add(option.cloneNode(true));
    });
    select.value = Array.from(select.options).some(function (option) { return option.value === selected; }) ? selected : '';
    show();
    if (select.options.length === 1) { preview.hidden = false; preview.textContent = preview.dataset.empty; }
  });
  select.addEventListener('change', show);
  show();
});
