<?php
require_once __DIR__ . '/inc/fn.php';
require_once __DIR__ . '/inc/category-picker.php';
$shop_type = post('shop_type');
$type_choices = array_column(dukan_types($pdo), 'slug');
$tab = 'kaam';
$page_title = 'अपना काम जोड़िए — Maakit';
$err = ''; $done = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) {
    $name = post('name'); $owner = post('owner'); $cat = post('category');
    $work = post('work'); $mobile = preg_replace('/\D/', '', post('mobile'));
    $village = post('village'); $address = post('address'); $about = post('about');

    if (mb_strlen($name) < 2) { $err = 'दुकान/काम का नाम लिखिए।'; }
    elseif (!cat_by_slug($cat)) { $err = 'काम की श्रेणी चुनिए।'; }
    elseif ($shop_type !== '' && !in_array($shop_type, $type_choices, true)) { $err = t('Choose a shop category from the list.', 'सूची से दुकान की category चुनिए।'); }
    elseif (strlen($mobile) !== 10) { $err = 'मोबाइल नंबर 10 अंकों का लिखिए।'; }
    else {
        $photo = null;
        if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $sz = $_FILES['photo']['size'];
            $info = @getimagesize($_FILES['photo']['tmp_name']);
            $okType = $info && in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true);
            if (!$okType) { $err = 'फ़ोटो jpg, png या webp होनी चाहिए।'; }
            elseif ($sz > 4 * 1024 * 1024) { $err = 'फ़ोटो 4 MB से छोटी होनी चाहिए।'; }
            else {
                $ext = $info[2] === IMAGETYPE_PNG ? 'png' : ($info[2] === IMAGETYPE_WEBP ? 'webp' : 'jpg');
                $photo = 'biz_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                move_uploaded_file($_FILES['photo']['tmp_name'], __DIR__ . '/uploads/' . $photo);
            }
        }
        if (!$err) {
            $pdo->beginTransaction();
            $tok = in_array($cat, ['nai', 'parlour'], true) ? 1 : 0;
            $pdo->prepare("INSERT INTO businesses (name, owner, category, work, mobile, village, address, about, photo, token_enabled, shop_type, items_on, status)
                           VALUES (?,?,?,?,?,?,?,?,?,?,?,?, 'pending')")
                ->execute([$name, $owner, $cat, $work, $mobile, $village, $address, $about, $photo, $tok, $shop_type ?: null, $shop_type !== '' ? 1 : 0]);
            if ($shop_type !== '') dukan_kism_bharo($pdo, (int)$pdo->lastInsertId(), $shop_type);
            $pdo->commit();
            $done = true;
        }
    }
}
include __DIR__ . '/inc/head.php';
?>
<section>
<div class="wrap" style="max-width:720px">
<?php if ($done): ?>
  <div class="box">
    <h2>धन्यवाद 🙏</h2>
    <p>आपकी जानकारी मिल गई है। जाँच के बाद आपका नाम वेबसाइट पर दिखने लगेगा, आम तौर पर एक दिन में।</p>
    <p class="help">कोई बदलाव कराना हो तो <?= MAAKIT_NUMBER_SHOW ?> पर WhatsApp कीजिए।</p>
    <a class="btn btn-brand" href="/">होम पर जाइए</a>
  </div>
<?php else: ?>
  <h2>अपना काम / दुकान जोड़िए</h2>
  <p class="lead">बिल्कुल मुफ़्त। आपके इलाके के लोग आपको ढूंढ पाएँगे और सीधे फ़ोन कर सकेंगे।</p>
  <?php if ($err): ?><div class="err"><?= h($err) ?></div><?php endif; ?>
  <form method="post" enctype="multipart/form-data" class="box">
    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
    <div class="field"><label>दुकान या काम का नाम</label><input type="text" name="name" value="<?= h(post('name')) ?>" placeholder="जैसे: गुप्ता किराना स्टोर / रमेश बिजली मिस्त्री" required></div>
    <div class="field"><label>आपका नाम</label><input type="text" name="owner" value="<?= h(post('owner')) ?>"></div>
    <div class="field"><label>काम किस तरह का है</label>
      <select name="category" required>
        <option value="">— चुनिए —</option>
        <?php foreach (categories() as $c): ?><option value="<?= h($c['slug']) ?>" <?= post('category') === $c['slug'] ? 'selected' : '' ?>><?= h($c['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="field"><?php catalog_picker($pdo, $shop_type); ?></div>
    <div class="field"><label>एक लाइन में अपना काम</label><input type="text" name="work" value="<?= h(post('work')) ?>" placeholder="जैसे: घर की वायरिंग, पंखा-मोटर की मरम्मत"></div>
    <div class="field"><label>मोबाइल नंबर (यही लोगों को दिखेगा)</label><input type="tel" name="mobile" value="<?= h(post('mobile')) ?>" required></div>
    <div class="field"><label>गाँव / कस्बा</label><input type="text" name="village" value="<?= h(post('village')) ?>"></div>
    <div class="field"><label>पता (पहचान के साथ)</label><input type="text" name="address" value="<?= h(post('address')) ?>"></div>
    <div class="field"><label>अपने काम के बारे में (कितने साल से, क्या-क्या करते हैं, समय)</label><textarea name="about"><?= h(post('about')) ?></textarea></div>
    <div class="field"><label>अपने काम या दुकान की फ़ोटो</label><input type="file" name="photo" accept="image/*"><p class="help">एक साफ़ फ़ोटो सबसे ज़्यादा भरोसा दिलाती है।</p></div>
    <button class="btn btn-brand" type="submit">भेजिए</button>
    <p class="help">भेजने के बाद जाँच होगी, फिर आपका नाम दिखने लगेगा। शराब, सूद पर पैसा, और ग़ैर-क़ानूनी काम नहीं जोड़े जाते।</p>
  </form>
<?php endif; ?>
</div>
</section>
<?php include __DIR__ . '/inc/foot.php'; ?>
