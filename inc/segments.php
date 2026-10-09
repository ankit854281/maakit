<?php
function maakit_segments($active='LOCAL_SHOPPING') { ?>
<nav class="maakit-segments" aria-label="<?= h(t('Choose how to use Maakit','Maakit में अपना काम चुनिए')) ?>">
<?php foreach (['LOCAL_SHOPPING'=>['Local Shopping','स्थानीय शॉपिंग'],'HOME_SERVICES'=>['Home Services','घरेलू सेवाएँ'],'B2B'=>['B2B Wholesale','थोक व्यापार']] as $key=>$label): ?>
<a href="/public/index.php?segment=<?= h($key) ?>" data-segment="<?= h($key) ?>" <?= $active===$key?'aria-current="page"':'' ?>><?= h(t($label[0],$label[1])) ?></a>
<?php endforeach; ?>
</nav>
<?php }
