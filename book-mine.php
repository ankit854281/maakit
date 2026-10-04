<?php
require_once __DIR__ . '/inc/fn.php';
require_once __DIR__ . '/inc/icons.php';
require_once __DIR__ . '/inc/books.php';

$page_title = t('My books — Maakit', 'मेरी किताबें — Maakit');
$tab = 'kitaab';
$me  = cust();
$err = ''; $msg = '';

/* kis-kis kitaab par is baar haq hai */
$mine = [];
$mob  = preg_replace('/\D/', '', post('mobile', get('m')));
$code = strtoupper(trim(post('code', get('c'))));

function book_mine_list(PDO $pdo, $mob, $code, $me) {
    if ($me) {                                   // login hai to code ki zaroorat nahi
        $st = $pdo->prepare("SELECT * FROM books WHERE customer_id=? AND status<>'hidden' ORDER BY id DESC");
        $st->execute([(int)$me['id']]);
        return $st->fetchAll();
    }
    if (strlen($mob) === 10 && $code !== '') {
        $st = $pdo->prepare("SELECT * FROM books WHERE seller_mobile=? AND manage_code=? AND status<>'hidden' ORDER BY id DESC");
        $st->execute([$mob, $code]);
        return $st->fetchAll();
    }
    return [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) {
    $do = post('do');

    if ($do === 'find') {
        $mine = book_mine_list($pdo, $mob, $code, $me);
        if (!$mine && !$me) $err = t('No book found with this number and code. Please check both.',
                                     'इस नंबर और कोड से कोई किताब नहीं मिली। दोनों एक बार देख लीजिए।');
    }
    elseif ($do === 'sold' || $do === 'hide' || $do === 'live') {
        $bid = (int)post('id');
        $ok  = false;
        if ($me) {
            $st = $pdo->prepare("SELECT id FROM books WHERE id=? AND customer_id=?");
            $st->execute([$bid, (int)$me['id']]);
            $ok = (bool)$st->fetch();
        } else {
            $st = $pdo->prepare("SELECT id FROM books WHERE id=? AND seller_mobile=? AND manage_code=?");
            $st->execute([$bid, $mob, $code]);
            $ok = (bool)$st->fetch();
        }
        if ($ok) {
            $to = ['sold' => 'sold', 'hide' => 'hidden', 'live' => 'live'][$do];
            $pdo->prepare("UPDATE books SET status=? WHERE id=?")->execute([$to, $bid]);
            $msg = ['sold' => t('Marked sold. It is off the list now.', 'बिक गई — लिस्ट से हट गई।'),
                    'hide' => t('Removed.', 'हटा दी गई।'),
                    'live' => t('Back on the list.', 'वापस लिस्ट में आ गई।')][$do];
        } else {
            $err = t('Could not do that. Please check the number and code.',
                     'यह नहीं हो पाया। नंबर और कोड एक बार देख लीजिए।');
        }
        $mine = book_mine_list($pdo, $mob, $code, $me);
    }
} elseif ($me || (strlen($mob) === 10 && $code !== '')) {
    $mine = book_mine_list($pdo, $mob, $code, $me);
}

include __DIR__ . '/inc/head.php';
?>
<section>
<div class="wrap" style="max-width:700px">

  <div class="pghead">
    <span class="bigic"><?= svc_icon('book', 34) ?></span>
    <h1><?= t('My books', 'मेरी किताबें') ?>
      <span><?= t('Mark sold, or take one down', 'बिक गई करिए, या हटा दीजिए') ?></span></h1>
  </div>

  <?php if ($msg): ?><div class="ok"><?= h($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="err"><?= h($err) ?></div><?php endif; ?>

  <?php if (!$me): ?>
    <form method="post" class="box" style="margin-bottom:18px">
      <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
      <input type="hidden" name="do" value="find">
      <p class="help" style="margin:0 0 12px">
        <?= t('Put the mobile number you used, and the book code you got when you put the book up.',
              'वही मोबाइल नंबर डालिए जो किताब डालते समय दिया था, और वह किताब कोड जो तब मिला था।') ?>
      </p>
      <div class="grid g2">
        <div class="field">
          <label><?= t('Mobile number', 'मोबाइल नंबर') ?></label>
          <input type="tel" name="mobile" required inputmode="numeric" maxlength="10" value="<?= h($mob) ?>">
        </div>
        <div class="field">
          <label><?= t('Book code', 'किताब कोड') ?></label>
          <input type="text" name="code" required maxlength="10" value="<?= h($code) ?>"
                 style="text-transform:uppercase" placeholder="ABC123">
        </div>
      </div>
      <button class="btn btn-brand" type="submit"><?= t('Show my books', 'मेरी किताबें दिखाइए') ?></button>
      <p class="help" style="margin-top:10px">
        <?= t('Code lost? Call Maakit', 'कोड खो गया? Maakit को कॉल कीजिए') ?> —
        <a href="tel:<?= MAAKIT_PHONE ?>"><?= h(MAAKIT_NUMBER_SHOW) ?></a>
      </p>
    </form>
  <?php endif; ?>

  <?php if ($mine): ?>
    <?php foreach ($mine as $b): $kd = book_kinds()[$b['kind']] ?? null; ?>
      <div class="mybk <?= $b['status'] === 'sold' ? 'sold' : '' ?>">
        <div class="mybk-i"><?= book_thumb($b, 34) ?></div>
        <div style="flex:1;min-width:0">
          <b style="font-size:16px;line-height:1.3;display:block"><?= h($b['title']) ?></b>
          <div class="help" style="margin-top:2px">
            <?= h(book_kind_label($b['kind'])) ?> · <?= h(book_price_line($b)) ?>
            <?php if ($b['status'] === 'sold'): ?>
              · <span style="color:var(--ok);font-weight:700"><?= t('Sold', 'बिक गई') ?></span>
            <?php endif; ?>
          </div>
          <div class="help" style="margin-top:4px;font-size:13px">
            <?= svc_icon('user', 13) ?> <?= num((int)$b['views']) ?> <?= t('saw it', 'ने देखी') ?>
            · <?= num((int)$b['asks']) ?> <?= t('asked for it', 'ने माँगी') ?>
          </div>
          <div style="display:flex;gap:7px;margin-top:9px;flex-wrap:wrap">
            <a class="btn btn-ghost btn-sm" href="/book.php?id=<?= (int)$b['id'] ?>"><?= t('See', 'देखिए') ?></a>
            <?php if ($b['status'] === 'live'): ?>
              <form method="post" style="display:inline">
                <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
                <input type="hidden" name="do" value="sold">
                <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                <input type="hidden" name="mobile" value="<?= h($mob) ?>">
                <input type="hidden" name="code" value="<?= h($code) ?>">
                <button class="btn btn-green btn-sm" type="submit"><?= t('Sold', 'बिक गई') ?></button>
              </form>
            <?php else: ?>
              <form method="post" style="display:inline">
                <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
                <input type="hidden" name="do" value="live">
                <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                <input type="hidden" name="mobile" value="<?= h($mob) ?>">
                <input type="hidden" name="code" value="<?= h($code) ?>">
                <button class="btn btn-ghost btn-sm" type="submit"><?= t('Put it back', 'वापस लगाइए') ?></button>
              </form>
            <?php endif; ?>
            <form method="post" style="display:inline"
                  onsubmit="return confirm('<?= h(t('Remove this book?', 'यह किताब हटा दें?')) ?>')">
              <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
              <input type="hidden" name="do" value="hide">
              <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
              <input type="hidden" name="mobile" value="<?= h($mob) ?>">
              <input type="hidden" name="code" value="<?= h($code) ?>">
              <button class="btn btn-ghost btn-sm" type="submit" style="color:#B02A2A"><?= t('Remove', 'हटाइए') ?></button>
            </form>
          </div>
        </div>
      </div>
    <?php endforeach; ?>

  <?php elseif ($me): ?>
    <div class="empty">
      <div class="e"><?= svc_icon('books', 48) ?></div>
      <b style="font-size:17px"><?= t('You have not put up any book yet.', 'आपने अभी कोई किताब नहीं डाली।') ?></b>
      <p><a class="btn btn-brand" style="margin-top:12px" href="/book-add.php"><?= t('Put up a book', 'किताब डालिए') ?></a></p>
    </div>
  <?php endif; ?>

  <p style="margin-top:22px"><a href="/books.php" class="help">&larr; <?= t('All books', 'सारी किताबें') ?></a></p>

</div>
</section>

<style>
.mybk{display:flex;gap:13px;background:var(--surface);border:1.5px solid var(--line);
  border-radius:15px;padding:13px 15px;margin-bottom:11px}
.mybk.sold{opacity:.62}
.mybk-i{width:62px;height:76px;flex-shrink:0;border-radius:10px;background:var(--soft);
  display:grid;place-items:center;overflow:hidden;color:var(--brand)}
.mybk-i img{width:100%;height:100%;object-fit:cover}
</style>
<?php include __DIR__ . '/inc/foot.php'; ?>
