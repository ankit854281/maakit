<?php
require_once __DIR__ . '/inc/fn.php';
require_once __DIR__ . '/inc/icons.php';
require_once __DIR__ . '/inc/books.php';

$page_title = t('Put up your old book — Maakit', 'अपनी पुरानी किताब डालिए — Maakit');
$tab  = 'kitaab';
$me   = cust();
$err  = '';
$done = null;

$kinds  = book_kinds();
$groups = book_groups();
$vill   = village_list($pdo);

// form me jo bhara tha wo wapas dikhane ke liye
$f = [
    'kind'   => get('kind', 'bech'),
    'title'  => '', 'author' => '', 'grp' => 'school', 'class_sub' => '',
    'lang'   => 'hi', 'halat' => 'theek', 'price' => '', 'want' => '', 'note' => '',
    'name'   => $me['name']    ?? '',
    'mobile' => $me['mobile']  ?? '',
    'village'=> $me['village'] ?? '',
    'landmark' => $me['landmark'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) {
    foreach ($f as $k => $_) {
        if (isset($_POST[$k])) $f[$k] = trim($_POST[$k]);
    }
    $mob = preg_replace('/\D/', '', $f['mobile']);

    if (!isset($kinds[$f['kind']]))        $err = t('Choose: sell, free or exchange.', 'चुनिए — बेचनी है, मुफ़्त देनी है, या बदलनी है।');
    elseif (mb_strlen($f['title']) < 2)    $err = t('Please write the book name.', 'किताब का नाम लिखिए।');
    elseif (!isset($groups[$f['grp']]))    $err = t('Choose the kind of book.', 'किताब किस तरह की है, चुनिए।');
    elseif (strlen($mob) !== 10)           $err = t('Mobile number must be 10 digits.', 'मोबाइल नंबर 10 अंकों का लिखिए।');
    elseif (mb_strlen($f['name']) < 2)     $err = t('Please write your name.', 'अपना नाम लिखिए।');
    elseif (mb_strlen($f['village']) < 2)  $err = t('Please choose your village.', 'अपना गाँव चुनिए।');
    elseif ($f['kind'] === 'bech' && (int)$f['price'] <= 0)
        $err = t('Write the price, or choose “Free”.', 'दाम लिखिए, या “मुफ़्त देनी है” चुनिए।');
    elseif ($f['kind'] === 'bech' && (int)$f['price'] > 20000)
        $err = t('Price looks too high. Please check.', 'दाम बहुत ज़्यादा लग रहा है। एक बार देख लीजिए।');
    elseif (books_today_by($pdo, $mob) >= BOOKS_PER_DAY)
        $err = t2("You have already put up " . BOOKS_PER_DAY . " books today. Please come back tomorrow.",
                  "आज आप " . BOOKS_PER_DAY . " किताबें डाल चुके हैं। कल फिर डालिए।");
    else {
        $p1 = save_item_photo('photo',  'bk', 700);
        $p2 = save_item_photo('photo2', 'bk', 700);
        $code = new_manage_code();

        $ins = $pdo->prepare("INSERT INTO books
            (kind, title, author, grp, class_sub, lang, halat, price, want, note, photo, photo2,
             seller_name, seller_mobile, village, landmark, customer_id, manage_code, status)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, 'live')");
        $ins->execute([
            $f['kind'], mb_substr($f['title'], 0, 160), mb_substr($f['author'], 0, 120), $f['grp'],
            mb_substr($f['class_sub'], 0, 80), (isset(book_langs()[$f['lang']]) ? $f['lang'] : 'hi'),
            (isset(book_halat_list()[$f['halat']]) ? $f['halat'] : 'theek'),
            ($f['kind'] === 'bech' ? (int)$f['price'] : null),
            ($f['kind'] === 'badal' ? mb_substr($f['want'], 0, 160) : null),
            mb_substr($f['note'], 0, 400), $p1, $p2,
            mb_substr($f['name'], 0, 80), $mob, mb_substr($f['village'], 0, 80),
            mb_substr($f['landmark'], 0, 120), $me['id'] ?? null, $code,
        ]);
        $done = ['id' => (int)$pdo->lastInsertId(), 'code' => $code];
    }
}

include __DIR__ . '/inc/head.php';
?>
<section>
<div class="wrap" style="max-width:640px">

<?php if ($done): ?>
  <div class="box" style="border-color:var(--ok)">
    <h2 style="margin-top:0"><?= t('Your book is up!', 'आपकी किताब चढ़ गई!') ?></h2>
    <p class="lead"><?= t('It is showing on the books page right now.', 'अभी से किताब वाले पेज पर दिख रही है।') ?></p>

    <div class="codebox">
      <div class="l"><?= t('Your book code — write it down', 'आपका किताब कोड — लिख लीजिए') ?></div>
      <div class="c">
        <?php foreach (str_split($done['code']) as $ch): ?><span><?= h($ch) ?></span><?php endforeach; ?>
      </div>
      <div class="h">
        <?= t('With this code you can mark the book sold, or remove it later.',
              'इसी कोड से आगे चलकर किताब को “बिक गई” कर सकते हैं या हटा सकते हैं।') ?>
      </div>
    </div>

    <div style="display:flex;gap:9px;flex-wrap:wrap">
      <a class="btn btn-brand" href="/book.php?id=<?= (int)$done['id'] ?>"><?= t('See my book', 'अपनी किताब देखिए') ?></a>
      <a class="btn btn-line" href="/book-add.php"><?= t('Put up one more', 'एक और डालिए') ?></a>
      <a class="btn btn-line" href="/books.php"><?= t('All books', 'सारी किताबें') ?></a>
    </div>
  </div>

<?php else: ?>

  <div class="pghead">
    <span class="bigic"><?= svc_icon('books', 34) ?></span>
    <h1><?= t('Put up your old book', 'अपनी पुरानी किताब डालिए') ?>
      <span><?= t('Free · takes two minutes', 'फ़्री · दो मिनट का काम') ?></span></h1>
    <p>
      <?= t('Free to put up. The buyer pays you directly — Maakit only charges for delivery.',
            'डालना बिल्कुल फ़्री है। किताब का पैसा खरीदने वाला आपको सीधे देगा — Maakit सिर्फ़ पहुँचाने का चार्ज लेता है।') ?>
    </p>
  </div>

  <?php if ($err): ?><div class="err"><?= h($err) ?></div><?php endif; ?>

  <form method="post" enctype="multipart/form-data" class="box">
    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">

    <div class="field">
      <label><?= t('What do you want to do?', 'क्या करना है?') ?></label>
      <div class="pickers" id="kindPick">
        <?php foreach ($kinds as $k => $v): ?>
          <label class="pick <?= $f['kind'] === $k ? 'on' : '' ?>">
            <input type="radio" name="kind" value="<?= h($k) ?>" <?= $f['kind'] === $k ? 'checked' : '' ?>>
            <span><?= svc_icon($v['ic'], 22) ?> <?= h(t($v['l'][0], $v['l'][1])) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="field">
      <label><?= t('Book name', 'किताब का नाम') ?> *</label>
      <input type="text" name="title" required maxlength="160" value="<?= h($f['title']) ?>"
             placeholder="<?= h(t('e.g. Science — Class 10 NCERT', 'जैसे: विज्ञान — कक्षा 10 NCERT')) ?>">
    </div>

    <div class="field">
      <label><?= t('Writer / publisher', 'लेखक / प्रकाशक') ?></label>
      <input type="text" name="author" maxlength="120" value="<?= h($f['author']) ?>"
             placeholder="<?= h(t('Leave blank if you don’t know', 'पता न हो तो खाली छोड़ दीजिए')) ?>">
    </div>

    <div class="field">
      <label><?= t('What kind of book?', 'किस तरह की किताब?') ?> *</label>
      <select name="grp">
        <?php foreach ($groups as $k => $l): ?>
          <option value="<?= h($k) ?>" <?= $f['grp'] === $k ? 'selected' : '' ?>><?= h(t($l[0], $l[1])) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field">
      <label><?= t('Class / subject', 'कक्षा / विषय') ?></label>
      <input type="text" name="class_sub" maxlength="80" value="<?= h($f['class_sub']) ?>"
             placeholder="<?= h(t('e.g. Class 10 — Science', 'जैसे: कक्षा 10 — विज्ञान')) ?>">
    </div>

    <div class="grid g2">
      <div class="field">
        <label><?= t('Language', 'भाषा') ?></label>
        <select name="lang">
          <?php foreach (book_langs() as $k => $l): ?>
            <option value="<?= h($k) ?>" <?= $f['lang'] === $k ? 'selected' : '' ?>><?= h(t($l[0], $l[1])) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label><?= t('Condition', 'किताब की हालत') ?></label>
        <select name="halat">
          <?php foreach (book_halat_list() as $k => $l): ?>
            <option value="<?= h($k) ?>" <?= $f['halat'] === $k ? 'selected' : '' ?>><?= h(t($l[0], $l[1])) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="field" id="priceBox">
      <label><?= t('Price (₹)', 'दाम (₹)') ?> *</label>
      <input type="number" name="price" min="1" max="20000" inputmode="numeric" value="<?= h($f['price']) ?>"
             placeholder="<?= h(t('e.g. 60', 'जैसे: 60')) ?>">
      <div class="help"><?= t('This money the buyer gives you directly, when the book reaches them.',
                              'यह पैसा खरीदने वाला आपको सीधे देगा, जब किताब उस तक पहुँचेगी।') ?></div>
    </div>

    <div class="field" id="wantBox">
      <label><?= t('What do you want in exchange?', 'बदले में क्या चाहिए?') ?></label>
      <input type="text" name="want" maxlength="160" value="<?= h($f['want']) ?>"
             placeholder="<?= h(t('e.g. Class 11 Maths book', 'जैसे: कक्षा 11 गणित की किताब')) ?>">
    </div>

    <div class="field">
      <label><?= t('Photo of the book', 'किताब की फ़ोटो') ?></label>
      <input type="file" name="photo" accept="image/*">
      <div class="help"><?= t('Not necessary — but a photo gets it picked up much faster.',
                              'ज़रूरी नहीं है — पर फ़ोटो वाली किताब बहुत जल्दी जाती है।') ?></div>
    </div>

    <div class="field">
      <label><?= t('One more photo (inside pages)', 'एक और फ़ोटो (अंदर के पन्ने)') ?></label>
      <input type="file" name="photo2" accept="image/*">
    </div>

    <div class="field">
      <label><?= t('Anything else to tell?', 'और कुछ बताना है?') ?></label>
      <textarea name="note" rows="2" maxlength="400"
        placeholder="<?= h(t('e.g. Last two pages torn, rest is fine', 'जैसे: आखिरी दो पन्ने फटे हैं, बाकी ठीक है')) ?>"><?= h($f['note']) ?></textarea>
    </div>

    <hr style="border:none;border-top:1px solid var(--line);margin:18px 0">

    <div class="grid g2">
      <div class="field">
        <label><?= t('Your name', 'आपका नाम') ?> *</label>
        <input type="text" name="name" required maxlength="80" value="<?= h($f['name']) ?>">
      </div>
      <div class="field">
        <label><?= t('Mobile number', 'मोबाइल नंबर') ?> *</label>
        <input type="tel" name="mobile" required inputmode="numeric" maxlength="10" value="<?= h($f['mobile']) ?>">
      </div>
    </div>
    <div class="help" style="margin:-4px 0 12px">
      <?= svc_icon('shield', 14) ?>
      <?= t('Your number is not shown on the website. Only Maakit sees it, to arrange the delivery.',
            'आपका नंबर वेबसाइट पर किसी को नहीं दिखेगा। सिर्फ़ Maakit देखेगा, किताब पहुँचाने के लिए।') ?>
    </div>

    <div class="field">
      <label><?= t('Your village', 'आपका गाँव') ?> *</label>
      <select name="village" required>
        <option value=""><?= t('— choose —', '— चुनिए —') ?></option>
        <?php foreach ($vill as $v): $vn = vname($v); ?>
          <option value="<?= h($v['name']) ?>" <?= $f['village'] === $v['name'] ? 'selected' : '' ?>><?= h($vn) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field">
      <label><?= t('Landmark (to find your house)', 'पहचान (घर ढूँढने के लिए)') ?></label>
      <input type="text" name="landmark" maxlength="120" value="<?= h($f['landmark']) ?>"
             placeholder="<?= h(t('e.g. near the school', 'जैसे: स्कूल के पास')) ?>">
    </div>

    <button class="btn btn-brand" type="submit" style="width:100%;font-size:17px;padding:13px">
      <?= t('Put my book up', 'मेरी किताब डाल दीजिए') ?>
    </button>
    <p class="help" style="text-align:center;margin-top:10px">
      <?= t('Putting it up is free. Maakit charges only when the book is delivered.',
            'डालना फ़्री है। Maakit सिर्फ़ किताब पहुँचाने का चार्ज लेता है।') ?>
    </p>
  </form>
<?php endif; ?>

</div>
</section>

<script>
/* bechna / muft / badalna — uske hisaab se khana dikhao-chhupao */
(function(){
  var box = document.getElementById('kindPick'); if (!box) return;
  var price = document.getElementById('priceBox'), want = document.getElementById('wantBox');
  function sync(){
    var k = (box.querySelector('input:checked') || {}).value || 'bech';
    box.querySelectorAll('.pick').forEach(function(p){
      p.classList.toggle('on', p.querySelector('input').checked);
    });
    price.hidden = (k !== 'bech');
    want.hidden  = (k !== 'badal');
    var inp = price.querySelector('input');
    if (k === 'bech') inp.setAttribute('required',''); else inp.removeAttribute('required');
  }
  box.addEventListener('change', sync);
  sync();
})();
</script>
<?php include __DIR__ . '/inc/foot.php'; ?>
