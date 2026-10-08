<?php
require_once __DIR__ . '/catalog.php';

/** A normal select remains usable without JavaScript; search only narrows choices. */
function catalog_picker(PDO $pdo, $selected = '', $required = false) {
    static $serial = 0, $loaded = false, $types = null, $samples = null;
    if ($types === null) {
        $types = dukan_types($pdo);
        $samples = [];
        foreach (catalog_load($pdo) as $row) {
            $key = $row['shop_type'];
            if (count($samples[$key] ?? []) < 5) {
                $samples[$key][] = t($row['name_en'], $row['name_hi'] ?: $row['name_en']);
            }
        }
    }
    $id = 'catalog-picker-' . ++$serial;
    ?>
    <div class="catalog-picker" data-category-picker>
      <label for="<?= h($id) ?>-search"><?= t('Find your shop category', 'अपनी दुकान की category खोजिए') ?></label>
      <input id="<?= h($id) ?>-search" type="search" class="category-query" autocomplete="off" placeholder="<?= h(t('Try paint, kirana, mobile…', 'जैसे paint, पेंट, kirana, मोबाइल…')) ?>" aria-controls="<?= h($id) ?>">
      <div class="category-matches" hidden aria-label="<?= h(t('Matching categories', 'मिलती categories')) ?>"></div>
      <label for="<?= h($id) ?>"><?= t('Choose the matching category', 'सही category चुनिए') ?></label>
      <select id="<?= h($id) ?>" name="shop_type" <?= $required ? 'required' : '' ?>>
        <option value=""><?= t('Choose a category', 'category चुनिए') ?></option>
        <?php foreach ($types as $type): ?>
          <option value="<?= h($type['slug']) ?>" <?= $type['slug'] === $selected ? 'selected' : '' ?> data-search="<?= h($type['slug'].' '.$type['name_hi'].' '.(catalog_meta()[$type['slug']]['hi'] ?? '').' '.catalog_picker_aliases($type['slug'])) ?>" data-preview="<?= h(implode(' · ', $samples[$type['slug']] ?? [])) ?>" data-count="<?= (int)$type['ginti'] ?>"><?= h($type['slug']) ?> · <?= h(catalog_meta()[$type['slug']]['hi'] ?? $type['name_hi']) ?> (<?= (int)$type['ginti'] ?>)</option>
        <?php endforeach; ?>
      </select>
      <div class="category-preview" role="status" aria-live="polite" data-count-label="<?= h(t('products ready to add', 'सामान जोड़ने के लिए तैयार')) ?>" data-empty="<?= h(t('No matching category. Try another word.', 'category नहीं मिली। दूसरा शब्द लिखिए।')) ?>" hidden></div>
      <p class="help"><?= t('Choose once to get the related product list. Check pack sizes and save your own prices before customers order. Reference prices appear only when another shop has a recent matching price.', 'एक बार चुनिए, संबंधित सामान की सूची मिलेगी। पैक जाँचकर अपने दाम सेव कीजिए, फिर ग्राहक ऑर्डर कर सकेंगे। हाल का मिलता-जुलता दुकान का दाम हो तभी संदर्भ मिलेगा।') ?></p>
    </div>
    <?php if (!$loaded): $loaded = true; ?>
      <script src="/assets/category-picker.js" defer></script>
    <?php endif;
}
