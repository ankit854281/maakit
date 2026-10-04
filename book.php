<?php
require_once __DIR__ . '/inc/fn.php';
require_once __DIR__ . '/inc/icons.php';
require_once __DIR__ . '/inc/books.php';

$id = (int)get('id');
$b  = book_get($pdo, $id);
if (!$b) {
    $page_title = t('Book not found — Maakit', 'किताब नहीं मिली — Maakit');
    $tab = 'kitaab';
    include __DIR__ . '/inc/head.php'; ?>
    <section><div class="wrap">
      <div class="empty">
        <div class="e"><?= svc_icon('book', 52) ?></div>
        <b style="font-size:18px"><?= t('This book is no longer here.', 'यह किताब अब यहाँ नहीं है।') ?></b>
        <p class="help"><?= t('It may have been sold or taken down.', 'शायद बिक गई हो या हटा दी गई हो।') ?></p>
        <a class="btn btn-brand" style="margin-top:12px" href="/books.php"><?= t('See other books', 'दूसरी किताबें देखिए') ?></a>
      </div>
    </div></section>
    <?php include __DIR__ . '/inc/foot.php'; exit;
}

$page_title = $b['title'] . ' — ' . t('old book', 'पुरानी किताब') . ' | Maakit';
$tab  = 'kitaab';
$me   = cust();
$from = book_charge_from($pdo);
$err  = ''; $done = null; $reported = false;

/* kitni baar dekhi gayi */
try { $pdo->prepare("UPDATE books SET views = views + 1 WHERE id=?")->execute([$id]); } catch (Throwable $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) {
    $do = post('do');

    /* ---------- galat kitaab ki shikayat ---------- */
    if ($do === 'report') {
        $why = mb_substr(trim(post('why')), 0, 300);
        $pdo->prepare("UPDATE books SET reports = reports + 1, report_note = ? WHERE id=?")
            ->execute([$why, $id]);
        $reported = true;
    }

    /* ---------- mangwaiye — maujooda order system me order ---------- */
    elseif ($do === 'ask') {
        $name     = mb_substr(trim(post('name')), 0, 80);
        $mobile   = preg_replace('/\D/', '', post('mobile'));
        $village  = mb_substr(trim(post('village')), 0, 80);
        $landmark = mb_substr(trim(post('landmark')), 0, 120);

        if (mb_strlen($name) < 2)         $err = t('Please write your name.', 'कृपया अपना नाम लिखिए।');
        elseif (strlen($mobile) !== 10)   $err = t('Mobile number must be 10 digits.', 'मोबाइल नंबर 10 अंकों का लिखिए।');
        elseif (mb_strlen($village) < 2)  $err = t('Please choose your village.', 'अपना गाँव चुनिए।');
        elseif ($mobile === $b['seller_mobile'])
            $err = t('This is your own book.', 'यह तो आपकी ही किताब है।');
        else {
            $order_no = new_order_no($pdo);
            $code     = new_code();

            $daam = ($b['kind'] === 'bech' && (int)$b['price'] > 0)
                  ? '₹' . (int)$b['price']
                  : ($b['kind'] === 'muft' ? 'मुफ़्त' : 'बदलना है');

            $items_text = "पुरानी किताब: " . $b['title']
                        . ($b['author']    ? " — " . $b['author'] : '')
                        . ($b['class_sub'] ? " (" . $b['class_sub'] . ")" : '')
                        . "\nकिताब का दाम: " . $daam . "  [यह पैसा ग्राहक सीधे किताब वाले को देगा]";

            $pickup = "किताब — " . $b['seller_name'] . ", " . $b['village']
                    . ($b['landmark'] ? " (" . $b['landmark'] . ")" : '');

            $note = "उठाइए: " . $b['seller_name'] . " · " . $b['seller_mobile']
                  . " · " . $b['village'] . ($b['landmark'] ? " · " . $b['landmark'] : '')
                  . "\nकिताब कोड: " . $b['manage_code'] . " · किताब नं: " . $b['id'];

            $ins = $pdo->prepare("INSERT INTO orders
                (order_no, code, source, customer_id, customer_name, mobile, village, landmark,
                 items, goods_note, shop, sector, book_id, status)
                VALUES (?,?,'website',?,?,?,?,?,?,?,?,'kitaab',?, 'Naya')");
            $ins->execute([$order_no, $code, $me['id'] ?? null, $name, $mobile, $village, $landmark,
                           $items_text, $note, mb_substr($pickup, 0, 120), $id]);

            $pdo->prepare("UPDATE books SET asks = asks + 1 WHERE id=?")->execute([$id]);
            $done = ['order_no' => $order_no, 'code' => $code];

            $wa = t("Hello Maakit, I want this old book.", "नमस्ते Maakit, मुझे यह पुरानी किताब चाहिए।") . "\n"
                . "ऑर्डर नंबर: $order_no\n"
                . "किताब: " . $b['title'] . "\n"
                . "किताब का दाम: $daam\n"
                . "कहाँ से: " . $b['village'] . "\n"
                . "नाम: $name\nगाँव: $village\n"
                . ($landmark ? "पहचान: $landmark\n" : '')
                . "नंबर: $mobile";
            $done['wa'] = wa_link(MAAKIT_WA, $wa);
        }
    }
}

$kd    = book_kinds()[$b['kind']] ?? null;
$vill  = village_list($pdo);
$aur   = books_live($pdo, ['grp' => $b['grp']], 7);
$aur   = array_values(array_filter($aur, fn($x) => (int)$x['id'] !== $id));
$aur   = array_slice($aur, 0, 6);

include __DIR__ . '/inc/head.php';
?>
<section style="padding-bottom:20px">
<div class="wrap" style="max-width:760px">

  <p style="margin:0 0 14px"><a href="/books.php" class="help">&larr; <?= t('All books', 'सारी किताबें') ?></a></p>

<?php if ($done): ?>
  <div class="box" style="border-color:var(--ok)">
    <h2 style="margin-top:0"><?= t('Done — Maakit will call you.', 'हो गया — Maakit आपको कॉल करेगा।') ?></h2>
    <p class="lead"><?= t('We will pick the book up and tell you the delivery charge on the call.',
                          'हम किताब उठा लेंगे और कॉल पर पहुँचाने का चार्ज बता देंगे।') ?></p>
    <div class="codebox">
      <div class="l"><?= t('Your order number', 'आपका ऑर्डर नंबर') ?></div>
      <div class="c"><span style="width:auto;padding:0 16px;font-size:22px"><?= h($done['order_no']) ?></span></div>
      <div class="h"><?= t('Door code', 'घर पर बताने का कोड') ?>: <b><?= h($done['code']) ?></b></div>
    </div>
    <div style="display:flex;gap:9px;flex-wrap:wrap">
      <a class="btn btn-green" href="<?= h($done['wa']) ?>" target="_blank" rel="noopener"><?= t('Send on WhatsApp too', 'WhatsApp पर भी भेजिए') ?></a>
      <a class="btn btn-ghost" href="/track.php"><?= t('Track my order', 'मेरा ऑर्डर देखिए') ?></a>
      <a class="btn btn-ghost" href="/books.php"><?= t('More books', 'और किताबें') ?></a>
    </div>
  </div>

<?php else: ?>

  <?php if ($reported): ?>
    <div class="note" style="margin-bottom:14px">
      <?= svc_icon('shield', 18) ?>
      <?= t('Thank you — Maakit will look at this book.', 'धन्यवाद — Maakit इस किताब को देख लेगा।') ?>
    </div>
  <?php endif; ?>

  <div class="bkmain">
    <div class="bkpics">
      <div class="bkbig"><?= book_thumb($b, 72) ?></div>
      <?php if ($b['photo2']): ?>
        <img class="bksmall" src="/uploads/<?= h($b['photo2']) ?>" alt="" loading="lazy">
      <?php endif; ?>
    </div>

    <div>
      <span class="bk-kind2" style="background:<?= h($kd['col'] ?? '#7A1F1F') ?>">
        <?= svc_icon($kd['ic'] ?? 'tag', 14) ?> <?= h(book_kind_label($b['kind'])) ?>
      </span>

      <h2 style="margin:10px 0 2px"><?= h($b['title']) ?></h2>
      <?php if ($b['author']): ?>
        <p class="help" style="margin:0 0 8px"><?= h($b['author']) ?></p>
      <?php endif; ?>

      <div class="bkprice"><?= h(book_price_line($b)) ?></div>
      <?php if ($b['kind'] === 'bech'): ?>
        <p class="help" style="margin:4px 0 0">
          <?= t('You give this money to the book’s owner, at your door.',
                'यह पैसा आप किताब वाले को देंगे, अपने घर पर।') ?>
        </p>
      <?php elseif ($b['kind'] === 'badal' && $b['want']): ?>
        <p class="help" style="margin:4px 0 0">
          <?= t('They want in exchange', 'बदले में चाहिए') ?>: <b><?= h($b['want']) ?></b>
        </p>
      <?php endif; ?>

      <table class="bktab">
        <tr><td><?= t('Kind', 'किस तरह की') ?></td><td><?= h(book_group_label($b['grp'])) ?></td></tr>
        <?php if ($b['class_sub']): ?>
          <tr><td><?= t('Class / subject', 'कक्षा / विषय') ?></td><td><?= h($b['class_sub']) ?></td></tr>
        <?php endif; ?>
        <tr><td><?= t('Language', 'भाषा') ?></td><td><?= h(book_lang_label($b['lang'])) ?></td></tr>
        <tr><td><?= t('Condition', 'हालत') ?></td><td><?= h(book_halat_label($b['halat'])) ?></td></tr>
        <tr><td><?= t('Pick up from', 'कहाँ से उठेगी') ?></td><td><?= h($b['village'] ?: '—') ?></td></tr>
        <tr><td><?= t('Put up', 'कब डाली गई') ?></td><td><?= h(ago($b['created_at'])) ?></td></tr>
      </table>

      <?php if ($b['note']): ?>
        <div class="note" style="margin-top:12px"><?= nl2br(h($b['note'])) ?></div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ---------- mangwaiye ---------- -->
  <div class="box" id="ask" style="margin-top:20px">
    <h3 style="margin:0 0 4px;font-size:20px"><?= t('Get this book', 'यह किताब मँगवाइए') ?></h3>
    <p class="help" style="margin:0 0 14px">
      <?= t('Maakit will pick it up from ' . ($b['village'] ?: 'their village') . ' and bring it to your door. '
             . 'Delivery charge from ₹' . num($from) . ', told to you on the call.',
             'Maakit इसे ' . ($b['village'] ?: 'उनके गाँव') . ' से उठाकर आपके घर पहुँचा देगा। '
             . 'पहुँचाने का चार्ज ₹' . num($from) . ' से शुरू, कॉल पर बता दिया जाएगा।') ?>
    </p>

    <?php if ($err): ?><div class="err"><?= h($err) ?></div><?php endif; ?>

    <form method="post">
      <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
      <input type="hidden" name="do" value="ask">

      <div class="grid g2">
        <div class="field">
          <label><?= t('Your name', 'आपका नाम') ?> *</label>
          <input type="text" name="name" required maxlength="80" value="<?= h($me['name'] ?? '') ?>">
        </div>
        <div class="field">
          <label><?= t('Mobile number', 'मोबाइल नंबर') ?> *</label>
          <input type="tel" name="mobile" required inputmode="numeric" maxlength="10" value="<?= h($me['mobile'] ?? '') ?>">
        </div>
      </div>

      <div class="field">
        <label><?= t('Your village', 'आपका गाँव') ?> *</label>
        <select name="village" required>
          <option value=""><?= t('— choose —', '— चुनिए —') ?></option>
          <?php foreach ($vill as $v): ?>
            <option value="<?= h($v['name']) ?>" <?= ($me['village'] ?? '') === $v['name'] ? 'selected' : '' ?>>
              <?= h(vname($v)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="field">
        <label><?= t('Landmark (to find your house)', 'पहचान (घर ढूँढने के लिए)') ?></label>
        <input type="text" name="landmark" maxlength="120" value="<?= h($me['landmark'] ?? '') ?>"
               placeholder="<?= h(t('e.g. near the temple', 'जैसे: मंदिर के पास')) ?>">
      </div>

      <button class="btn btn-brand" type="submit" style="width:100%;font-size:17px;padding:13px">
        <?= t('Yes, bring it to me', 'हाँ, मेरे घर भेजिए') ?>
      </button>
      <p class="help" style="text-align:center;margin-top:10px">
        <?= t('Nothing is paid now. You pay at your door.', 'अभी कोई पैसा नहीं। पैसा घर पर ही देना है।') ?>
      </p>
    </form>
  </div>

  <!-- ---------- shikayat ---------- -->
  <details class="bkrep">
    <summary><?= t('Something wrong with this book?', 'इस किताब में कुछ गड़बड़ है?') ?></summary>
    <form method="post" style="margin-top:10px">
      <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
      <input type="hidden" name="do" value="report">
      <div class="field">
        <label><?= t('What is wrong?', 'क्या गड़बड़ है?') ?></label>
        <input type="text" name="why" maxlength="300"
               placeholder="<?= h(t('e.g. wrong photo, fake listing, already sold', 'जैसे: फ़ोटो गलत है, नकली है, बिक चुकी है')) ?>">
      </div>
      <button class="btn btn-ghost btn-sm" type="submit"><?= t('Tell Maakit', 'Maakit को बताइए') ?></button>
    </form>
  </details>

<?php endif; ?>

  <?php if ($aur): ?>
    <div class="ghead" style="margin-top:28px"><?= t('Other books like this', 'इसी तरह की दूसरी किताबें') ?></div>
    <div class="bkgrid">
      <?php foreach ($aur as $x): $k2 = book_kinds()[$x['kind']] ?? null; ?>
        <a class="bk" href="/book.php?id=<?= (int)$x['id'] ?>">
          <span class="bk-kind" style="background:<?= h($k2['col'] ?? '#7A1F1F') ?>">
            <?= h(book_kind_label($x['kind'])) ?></span>
          <div class="bk-img"><?= book_thumb($x, 42) ?></div>
          <div class="bk-t"><?= h($x['title']) ?></div>
          <div class="bk-p"><?= h(book_price_line($x)) ?></div>
          <div class="bk-m"><?= h($x['village'] ?: '') ?></div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

</div>
</section>

<style>
.bkmain{display:grid;grid-template-columns:260px 1fr;gap:22px;align-items:start}
.bkpics{display:grid;gap:9px}
.bkbig{height:280px;border-radius:16px;background:var(--soft);display:grid;place-items:center;
  overflow:hidden;color:var(--brand);border:1.5px solid var(--line)}
.bkbig img{width:100%;height:100%;object-fit:cover}
.bksmall{width:100%;height:120px;object-fit:cover;border-radius:12px;border:1.5px solid var(--line)}
.bk-kind2{color:#fff;font-size:13px;font-weight:700;padding:5px 12px;border-radius:30px;
  display:inline-flex;align-items:center;gap:5px;line-height:1}
.bkprice{font-size:30px;font-weight:800;color:var(--brand);margin-top:12px;line-height:1.1}
.bktab{width:100%;border-collapse:collapse;margin-top:16px;font-size:15px}
.bktab td{padding:8px 0;border-bottom:1px solid var(--line);vertical-align:top}
.bktab td:first-child{color:var(--muted);width:46%}
.bkrep{margin-top:16px;background:var(--surface);border:1.5px solid var(--line);border-radius:14px;padding:13px 16px}
.bkrep summary{cursor:pointer;font-size:14.5px;color:var(--muted);font-weight:600}
.bkgrid{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:12px}
.bk{position:relative;display:block;text-decoration:none;color:var(--ink);background:var(--surface);
  border:1.5px solid var(--line);border-radius:15px;padding:11px 12px 13px;overflow:hidden}
.bk:hover{border-color:var(--brand)}
.bk-kind{position:absolute;top:0;left:0;color:#fff;font-size:11px;font-weight:700;
  padding:4px 9px;border-radius:0 0 11px 0;line-height:1}
.bk-img{height:112px;margin:20px 0 9px;border-radius:10px;background:var(--soft);
  display:grid;place-items:center;overflow:hidden;color:var(--brand)}
.bk-img img{width:100%;height:100%;object-fit:cover}
.bk-t{font-weight:700;font-size:14.5px;line-height:1.3;
  display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.bk-p{font-weight:800;font-size:16px;margin-top:6px;color:var(--brand);line-height:1.25;
  display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.bk-m{font-size:12px;color:var(--muted);margin-top:2px}
@media(max-width:620px){
  .bkmain{grid-template-columns:1fr;gap:16px}
  .bkbig{height:230px}
  .bkgrid{grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
  .bk-img{height:104px}
}
</style>
<?php include __DIR__ . '/inc/foot.php'; ?>
