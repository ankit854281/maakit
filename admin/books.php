<?php
require_once __DIR__ . '/../inc/fn.php';
require_once __DIR__ . '/../inc/icons.php';
require_once __DIR__ . '/../inc/books.php';
$u = need_role('admin');
$page_title = 'पुरानी किताबें — Maakit';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) {
    $do  = post('do');
    $id  = (int)post('id');
    $back = '/admin/books.php?v=' . urlencode(post('v', 'live'));

    if ($do === 'hide') {
        $pdo->prepare("UPDATE books SET status='hidden' WHERE id=?")->execute([$id]);
        flash('किताब हटा दी गई।');
    } elseif ($do === 'live') {
        $pdo->prepare("UPDATE books SET status='live', reports=0, report_note=NULL WHERE id=?")->execute([$id]);
        flash('किताब वापस लगा दी गई।');
    } elseif ($do === 'sold') {
        $pdo->prepare("UPDATE books SET status='sold' WHERE id=?")->execute([$id]);
        flash('“बिक गई” कर दिया।');
    } elseif ($do === 'clearflag') {
        $pdo->prepare("UPDATE books SET reports=0, report_note=NULL WHERE id=?")->execute([$id]);
        flash('शिकायत हटा दी गई — किताब ठीक है।');
    } elseif ($do === 'wipe') {
        $st = $pdo->prepare("SELECT photo, photo2 FROM books WHERE id=?"); $st->execute([$id]);
        if ($r = $st->fetch()) { drop_photo($r['photo']); drop_photo($r['photo2']); }
        $pdo->prepare("DELETE FROM books WHERE id=?")->execute([$id]);
        flash('किताब पूरी तरह मिटा दी गई।');
    } elseif ($do === 'charge') {
        $c = max(0, (int)post('book_charge_from'));
        $pdo->prepare("INSERT INTO settings (k,v) VALUES ('book_charge_from',?)
                       ON DUPLICATE KEY UPDATE v=VALUES(v)")->execute([(string)$c]);
        flash('किताब की डिलीवरी ₹' . $c . ' से शुरू — बदल दिया गया।');
    }
    redirect($back);
}

$v   = get('v', 'live');
$q   = trim(get('q'));
$par = [];

if ($v === 'flag')      { $sql = "SELECT * FROM books WHERE reports > 0"; }
elseif ($v === 'sold')  { $sql = "SELECT * FROM books WHERE status='sold'"; }
elseif ($v === 'hidden'){ $sql = "SELECT * FROM books WHERE status='hidden'"; }
elseif ($v === 'all')   { $sql = "SELECT * FROM books WHERE 1"; }
else                    { $v = 'live'; $sql = "SELECT * FROM books WHERE status='live'"; }

if ($q !== '') {
    $sql .= " AND (title LIKE ? OR author LIKE ? OR seller_name LIKE ? OR seller_mobile LIKE ? OR village LIKE ?)";
    $like = "%$q%";
    array_push($par, $like, $like, $like, $like, $like);
}
$sql .= " ORDER BY reports DESC, id DESC LIMIT 300";
$st = $pdo->prepare($sql); $st->execute($par); $rows = $st->fetchAll();

$n_live = books_count($pdo, 'live');
$n_sold = books_count($pdo, 'sold');
$n_hid  = books_count($pdo, 'hidden');
$n_flag = (int)$pdo->query("SELECT COUNT(*) c FROM books WHERE reports > 0")->fetch()['c'];
$n_aaj  = (int)$pdo->query("SELECT COUNT(*) c FROM books WHERE created_at >= CURDATE()")->fetch()['c'];
$n_ask  = (int)$pdo->query("SELECT COALESCE(SUM(asks),0) c FROM books")->fetch()['c'];
$charge = book_charge_from($pdo);

include __DIR__ . '/../inc/panel.php';
$fl = flash();
?>
<section>
<div class="wrap">
  <h2>पुरानी किताबें</h2>
  <p class="lead">लोग अपनी किताब खुद डालते हैं और वह तुरंत दिख जाती है। यहाँ से आप कोई भी किताब हटा सकते हैं।</p>

  <?php if ($fl): ?><div class="ok"><?= h($fl) ?></div><?php endif; ?>

  <?php if ($n_flag): ?>
    <div class="err" style="display:flex;gap:10px;align-items:center">
      <?= svc_icon('shield', 20) ?>
      <span><b><?= $n_flag ?></b> किताब की शिकायत आई है —
        <a href="?v=flag">अभी देख लीजिए</a></span>
    </div>
  <?php endif; ?>

  <div class="kpis" style="margin-bottom:16px">
    <div class="kpi"><div class="k"><?= svc_icon('books', 14) ?> अभी लगी हैं</div>
      <div class="v"><?= $n_live ?></div><div class="d">लोगों को दिख रही हैं</div></div>
    <div class="kpi"><div class="k"><?= svc_icon('plus', 14) ?> आज डाली गईं</div>
      <div class="v"><?= $n_aaj ?></div><div class="d">आज की नई किताबें</div></div>
    <div class="kpi"><div class="k"><?= svc_icon('box', 14) ?> कुल माँगी गईं</div>
      <div class="v"><?= $n_ask ?></div><div class="d">इतने ऑर्डर आए</div></div>
    <div class="kpi"><div class="k"><?= svc_icon('tag', 14) ?> बिक गईं</div>
      <div class="v"><?= $n_sold ?></div><div class="d">काम हो गया</div></div>
  </div>

  <div class="chips" style="margin-bottom:12px">
    <a class="chip <?= $v === 'live' ? 'on' : '' ?>"   href="?v=live">लगी हुई (<?= $n_live ?>)</a>
    <a class="chip <?= $v === 'flag' ? 'on' : '' ?>"   href="?v=flag">शिकायत (<?= $n_flag ?>)</a>
    <a class="chip <?= $v === 'sold' ? 'on' : '' ?>"   href="?v=sold">बिक गईं (<?= $n_sold ?>)</a>
    <a class="chip <?= $v === 'hidden' ? 'on' : '' ?>" href="?v=hidden">हटाई गईं (<?= $n_hid ?>)</a>
    <a class="chip <?= $v === 'all' ? 'on' : '' ?>"    href="?v=all">सारी</a>
  </div>

  <form method="get" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px">
    <input type="hidden" name="v" value="<?= h($v) ?>">
    <input type="text" name="q" value="<?= h($q) ?>" placeholder="किताब, नाम, नंबर या गाँव से ढूंढिए"
           style="flex:1;min-width:180px">
    <button class="btn btn-brand btn-sm" type="submit">ढूंढिए</button>
    <?php if ($q): ?><a class="btn btn-ghost btn-sm" href="?v=<?= h($v) ?>">साफ़</a><?php endif; ?>
  </form>

  <?php if (!$rows): ?>
    <div class="empty"><div class="e"><?= svc_icon('books', 46) ?></div>
      <b>यहाँ कोई किताब नहीं है।</b></div>
  <?php else: ?>

  <?php foreach ($rows as $b): ?>
    <div class="abk <?= $b['reports'] ? 'flag' : '' ?>">
      <div class="abk-i"><?= book_thumb($b, 30) ?></div>

      <div style="flex:1;min-width:0">
        <div style="display:flex;gap:8px;align-items:baseline;flex-wrap:wrap">
          <b style="font-size:16.5px"><?= h($b['title']) ?></b>
          <span class="tag tag-off"><?= h(book_kind_label($b['kind'])) ?></span>
          <?php if ($b['status'] === 'sold'): ?><span class="tag tag-gold">बिक गई</span><?php endif; ?>
          <?php if ($b['status'] === 'hidden'): ?><span class="tag tag-off">हटाई हुई</span><?php endif; ?>
          <?php if ($b['reports']): ?><span class="tag" style="background:#B02A2A;color:#fff"><?= (int)$b['reports'] ?> शिकायत</span><?php endif; ?>
        </div>

        <div class="help" style="margin-top:3px">
          <?= h(book_price_line($b)) ?> · <?= h(book_group_label($b['grp'])) ?>
          <?php if ($b['class_sub']): ?> · <?= h($b['class_sub']) ?><?php endif; ?>
          · <?= h(book_halat_label($b['halat'])) ?>
        </div>

        <?php if ($b['report_note']): ?>
          <div class="note" style="margin-top:7px;background:#FDE2E2;color:#8A1F1F">
            <b>शिकायत:</b> <?= h($b['report_note']) ?>
          </div>
        <?php endif; ?>

        <?php if ($b['note']): ?>
          <div class="help" style="margin-top:5px">“<?= h($b['note']) ?>”</div>
        <?php endif; ?>

        <div class="abk-s">
          <?= svc_icon('user', 14) ?> <b><?= h($b['seller_name']) ?></b>
          · <a href="tel:+91<?= h($b['seller_mobile']) ?>"><?= h($b['seller_mobile']) ?></a>
          · <a href="<?= h(wa_link('91' . $b['seller_mobile'], 'नमस्ते ' . $b['seller_name'] . ' जी, Maakit से। आपकी किताब “' . $b['title'] . '” के बारे में बात करनी थी।')) ?>"
               target="_blank" rel="noopener">WhatsApp</a>
          · <?= h($b['village']) ?><?php if ($b['landmark']): ?> (<?= h($b['landmark']) ?>)<?php endif; ?>
          <br>
          कोड: <b><?= h($b['manage_code']) ?></b>
          · <?= (int)$b['views'] ?> ने देखी · <?= (int)$b['asks'] ?> ने माँगी
          · <?= h(ago($b['created_at'])) ?>
        </div>

        <div style="display:flex;gap:7px;margin-top:10px;flex-wrap:wrap">
          <a class="btn btn-ghost btn-sm" href="/book.php?id=<?= (int)$b['id'] ?>" target="_blank" rel="noopener">खोलिए</a>

          <?php if ($b['reports']): ?>
            <form method="post" style="display:inline">
              <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
              <input type="hidden" name="do" value="clearflag">
              <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
              <input type="hidden" name="v" value="<?= h($v) ?>">
              <button class="btn btn-green btn-sm" type="submit">ठीक है</button>
            </form>
          <?php endif; ?>

          <?php if ($b['status'] === 'live'): ?>
            <form method="post" style="display:inline">
              <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
              <input type="hidden" name="do" value="sold">
              <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
              <input type="hidden" name="v" value="<?= h($v) ?>">
              <button class="btn btn-ghost btn-sm" type="submit">बिक गई</button>
            </form>
            <form method="post" style="display:inline">
              <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
              <input type="hidden" name="do" value="hide">
              <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
              <input type="hidden" name="v" value="<?= h($v) ?>">
              <button class="btn btn-ghost btn-sm" type="submit" style="color:#B02A2A">हटाइए</button>
            </form>
          <?php else: ?>
            <form method="post" style="display:inline">
              <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
              <input type="hidden" name="do" value="live">
              <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
              <input type="hidden" name="v" value="<?= h($v) ?>">
              <button class="btn btn-ghost btn-sm" type="submit">वापस लगाइए</button>
            </form>
          <?php endif; ?>

          <form method="post" style="display:inline"
                onsubmit="return confirm('यह किताब हमेशा के लिए मिटा दें? वापस नहीं आएगी।')">
            <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
            <input type="hidden" name="do" value="wipe">
            <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
            <input type="hidden" name="v" value="<?= h($v) ?>">
            <button class="btn btn-ghost btn-sm" type="submit" style="color:#B02A2A;opacity:.75">मिटाइए</button>
          </form>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
  <?php endif; ?>

  <div class="box" style="margin-top:24px">
    <b style="font-size:17px">किताब की डिलीवरी का शुरुआती चार्ज</b>
    <p class="help" style="margin:6px 0 12px">
      किताब वाले पेज पर लोगों को यही दिखता है — “पहुँचाने का चार्ज ₹<?= $charge ?> से शुरू”।
      असली चार्ज तो आप कॉल पर गाँव देखकर बताएँगे।
    </p>
    <form method="post" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap">
      <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
      <input type="hidden" name="do" value="charge">
      <input type="hidden" name="v" value="<?= h($v) ?>">
      <div class="field" style="margin:0;min-width:140px">
        <label>₹ से शुरू</label>
        <input type="number" name="book_charge_from" min="0" max="500" value="<?= $charge ?>">
      </div>
      <button class="btn btn-brand btn-sm" type="submit">बदल दीजिए</button>
    </form>
  </div>

</div>
</section>

<style>
.abk{display:flex;gap:13px;background:var(--surface);border:1.5px solid var(--line);
  border-radius:15px;padding:14px 16px;margin-bottom:11px}
.abk.flag{border-color:#B02A2A;background:#FFF8F8}
.abk-i{width:60px;height:74px;flex-shrink:0;border-radius:10px;background:var(--soft);
  display:grid;place-items:center;overflow:hidden;color:var(--brand)}
.abk-i img{width:100%;height:100%;object-fit:cover}
.abk-s{font-size:13.5px;color:var(--muted);margin-top:8px;line-height:1.7}
.abk-s a{color:var(--brand)}
@media(max-width:520px){.abk{flex-direction:column}.abk-i{width:72px;height:88px}}
</style>
<?php include __DIR__ . '/../inc/foot.php'; ?>
