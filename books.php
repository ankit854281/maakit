<?php
require_once __DIR__ . '/inc/fn.php';
require_once __DIR__ . '/inc/icons.php';
require_once __DIR__ . '/inc/books.php';

$page_title = t('Old books — buy, sell, exchange | Maakit', 'पुरानी किताबें — लीजिए, दीजिए, बदलिए | Maakit');
$tab = 'kitaab';

$fl = [
    'kind'    => get('kind'),
    'grp'     => get('grp'),
    'lang'    => get('lang'),
    'village' => get('v'),
    'q'       => get('q'),
];
$rows  = books_live($pdo, $fl, 90);
$kul   = books_count($pdo, 'live');
$vills = book_villages($pdo);
$from  = book_charge_from($pdo);

/* filter ke link banane ke liye */
function bq(array $add = []) {
    $q = array_filter(array_merge([
        'kind' => get('kind'), 'grp' => get('grp'), 'lang' => get('lang'),
        'v' => get('v'), 'q' => get('q'),
    ], $add), fn($x) => $x !== '' && $x !== null);
    return '/books.php' . ($q ? '?' . http_build_query($q) : '');
}

include __DIR__ . '/inc/head.php';
?>
<section style="padding-bottom:18px">
<div class="wrap">

  <div class="pghead">
    <span class="bigic"><?= svc_icon('books', 34) ?></span>
    <h1><?= t('Old books', 'पुरानी किताबें') ?>
      <span><?= t('Buy · Give free · Exchange — in your own village', 'लीजिए · मुफ़्त दीजिए · बदलिए — अपने ही गाँव में') ?></span></h1>
    <p>
      <?= t(
        "Someone's book finished, someone's book is needed. Put yours up here, or take one. Maakit brings it to your door — you pay only for the delivery, from ₹" . num($from) . ".",
        'किसी की किताब पढ़ी जा चुकी, किसी को वही चाहिए। अपनी यहाँ डाल दीजिए, या किसी की ले लीजिए। Maakit घर तक पहुँचा देगा — आप सिर्फ़ पहुँचाने का चार्ज देंगे, ₹' . num($from) . ' से शुरू।') ?>
    </p>
  </div>

  <div style="display:flex;gap:9px;flex-wrap:wrap;margin-bottom:18px">
    <a class="btn btn-brand" href="/book-add.php"><?= svc_icon('plus', 17) ?> <?= t('Put up my book', 'अपनी किताब डालिए') ?></a>
    <a class="btn btn-ghost" href="/book-mine.php"><?= t('My books', 'मेरी किताबें') ?></a>
  </div>

  <!-- khoj -->
  <form method="get" class="searchbox" style="margin:0 0 12px">
    <?php foreach (['kind','grp','lang','v'] as $k): if (get($k) !== ''): ?>
      <input type="hidden" name="<?= h($k) ?>" value="<?= h(get($k)) ?>">
    <?php endif; endforeach; ?>
    <input type="search" name="q" value="<?= h(get('q')) ?>"
           placeholder="<?= h(t('Book name, class or subject…', 'किताब का नाम, कक्षा या विषय…')) ?>">
    <button class="btn btn-brand btn-sm" type="submit"><?= svc_icon('search', 16) ?></button>
  </form>

  <!-- bechna / muft / badalna -->
  <div class="chips">
    <a class="chip <?= get('kind') === '' ? 'on' : '' ?>" href="<?= h(bq(['kind' => ''])) ?>"><?= t('All', 'सब') ?></a>
    <?php foreach (book_kinds() as $k => $v): ?>
      <a class="chip <?= get('kind') === $k ? 'on' : '' ?>" href="<?= h(bq(['kind' => $k])) ?>">
        <?= svc_icon($v['ic'], 15) ?> <?= h(t($v['l'][0], $v['l'][1])) ?></a>
    <?php endforeach; ?>
  </div>

  <!-- kis tarah ki kitaab -->
  <div class="chips">
    <a class="chip <?= get('grp') === '' ? 'on' : '' ?>" href="<?= h(bq(['grp' => ''])) ?>"><?= t('Every kind', 'हर तरह की') ?></a>
    <?php foreach (book_groups() as $k => $l): ?>
      <a class="chip <?= get('grp') === $k ? 'on' : '' ?>" href="<?= h(bq(['grp' => $k])) ?>"><?= h(t($l[0], $l[1])) ?></a>
    <?php endforeach; ?>
  </div>

  <?php if ($vills): ?>
  <div class="chips">
    <a class="chip <?= get('v') === '' ? 'on' : '' ?>" href="<?= h(bq(['v' => ''])) ?>"><?= t('Every village', 'हर गाँव') ?></a>
    <?php foreach ($vills as $v): ?>
      <a class="chip <?= get('v') === $v['village'] ? 'on' : '' ?>" href="<?= h(bq(['v' => $v['village']])) ?>">
        <?= h($v['village']) ?> <span style="opacity:.6">(<?= num($v['c']) ?>)</span></a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

</div>
</section>

<section style="padding-top:6px">
<div class="wrap">

  <?php if (!$rows): ?>
    <div class="empty">
      <div class="e"><?= svc_icon('books', 52) ?></div>
      <?php if ($kul > 0): ?>
        <b style="font-size:18px"><?= t('Nothing here with this filter.', 'इस छाँट में कुछ नहीं मिला।') ?></b>
        <p><a href="/books.php"><?= t('See all books', 'सारी किताबें देखिए') ?></a></p>
      <?php else: ?>
        <b style="font-size:18px"><?= t('No books yet — yours can be the first.', 'अभी कोई किताब नहीं — पहली आपकी हो सकती है।') ?></b>
        <p class="help" style="max-width:430px;margin:8px auto 16px">
          <?= t('Old school books, novels, competition books — anything lying at home that someone else needs.',
                'स्कूल की पुरानी किताब, उपन्यास, कॉम्पिटिशन की किताब — घर में पड़ी कोई भी किताब, जो किसी और के काम आ जाए।') ?>
        </p>
        <a class="btn btn-brand" href="/book-add.php"><?= t('Put up the first book', 'पहली किताब डालिए') ?></a>
      <?php endif; ?>
    </div>
  <?php else: ?>

    <div style="display:flex;justify-content:space-between;align-items:baseline;flex-wrap:wrap;gap:6px;margin-bottom:12px">
      <b style="font-size:18px"><?= num(count($rows)) ?> <?= t('books', 'किताबें') ?></b>
      <span class="help"><?= t('Newest first', 'सबसे नई पहले') ?></span>
    </div>

    <div class="bkgrid">
      <?php foreach ($rows as $b): $kd = book_kinds()[$b['kind']] ?? null; ?>
        <a class="bk" href="/book.php?id=<?= (int)$b['id'] ?>">
          <span class="bk-kind" style="background:<?= h($kd['col'] ?? '#7A1F1F') ?>">
            <?= svc_icon($kd['ic'] ?? 'tag', 13) ?> <?= h(book_kind_label($b['kind'])) ?>
          </span>
          <div class="bk-img"><?= book_thumb($b, 46) ?></div>
          <div class="bk-t"><?= h($b['title']) ?></div>
          <?php if ($b['class_sub']): ?><div class="bk-s"><?= h($b['class_sub']) ?></div><?php endif; ?>
          <div class="bk-p"><?= h(book_price_line($b)) ?></div>
          <div class="bk-m">
            <?= h(book_halat_label($b['halat'])) ?>
            <?php if ($b['village']): ?> · <?= h($b['village']) ?><?php endif; ?>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <div class="box" style="margin-top:26px">
    <b style="font-size:17px"><?= t('How it works', 'कैसे चलता है') ?></b>
    <div class="steps3" style="margin-top:12px">
      <div><span class="n">1</span><b><?= t('Someone puts a book up', 'कोई अपनी किताब डालता है') ?></b>
        <p><?= t('Free. Photo and price, that’s all.', 'फ़्री। बस फ़ोटो और दाम।') ?></p></div>
      <div><span class="n">2</span><b><?= t('You ask for it', 'आप मँगवाते हैं') ?></b>
        <p><?= t('Maakit picks it up from their village and brings it to you.',
                 'Maakit उनके गाँव से उठाकर आप तक पहुँचा देता है।') ?></p></div>
      <div><span class="n">3</span><b><?= t('You pay at the door', 'घर पर पैसा देते हैं') ?></b>
        <p><?= t('The book’s price goes to them, the delivery charge to Maakit — from ₹' . num($from) . '.',
                  'किताब का दाम उनका, पहुँचाने का चार्ज Maakit का — ₹' . num($from) . ' से शुरू।') ?></p></div>
    </div>
  </div>

</div>
</section>

<style>
/* phone par chip ek hi lakeer me — page lamba na ho */
@media(max-width:560px){
  .wrap > .chips{flex-wrap:nowrap;overflow-x:auto;padding-bottom:4px;scrollbar-width:none}
  .wrap > .chips::-webkit-scrollbar{display:none}
  .wrap > .chips .chip{flex:0 0 auto}
}
/* ---- kitaab ki jaali ---- */
.bkgrid{display:grid;grid-template-columns:repeat(auto-fill,minmax(158px,1fr));gap:13px}
.bk{position:relative;display:block;text-decoration:none;color:var(--ink);background:var(--surface);
  border:1.5px solid var(--line);border-radius:15px;padding:11px 12px 13px;overflow:hidden}
.bk:hover{border-color:var(--brand)}
.bk-kind{position:absolute;top:0;left:0;color:#fff;font-size:11.5px;font-weight:700;
  padding:4px 9px 4px 8px;border-radius:0 0 11px 0;display:inline-flex;align-items:center;gap:4px;line-height:1}
.bk-img{height:124px;margin:22px 0 10px;border-radius:10px;background:var(--soft);
  display:grid;place-items:center;overflow:hidden;color:var(--brand)}
.bk-img img{width:100%;height:100%;object-fit:cover}
.bk-t{font-weight:700;font-size:15px;line-height:1.3;
  display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.bk-s{font-size:12.5px;color:var(--muted);margin-top:2px;
  display:-webkit-box;-webkit-line-clamp:1;-webkit-box-orient:vertical;overflow:hidden}
.bk-p{font-weight:800;font-size:17px;margin-top:7px;color:var(--brand);line-height:1.25;
  display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.bk-m{font-size:12px;color:var(--muted);margin-top:3px}
@media(max-width:420px){.bkgrid{grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.bk-img{height:108px}}
</style>
<?php include __DIR__ . '/inc/foot.php'; ?>
