<?php
// ============================================================
//  पैकेट — बने हुए सेट
//
//  Ankit ne kaha tha: graahak ko soochi na dekhni pade, bana hua
//  set mile -- "sau rupaye ki sabzi", "pooja ka saaman", "do dost
//  ka nashta".
//
//  Is panne par teen kaam hote hain:
//    1. Bot se packet BANWANA (ek button) -- bot dukaan ke apne
//       daam se budget ke set jod deta hai
//    2. Un packet ko dekh kar MANZOORI dena -- bina manzoori koi
//       packet graahak ko nahi dikhta
//    3. Apna packet haath se banana (jo bot nahi soch sakta --
//       pooja ka set, tyohar ka set)
//
//  Daam yahan kahin nahi bhara jata, aur jaan-boojh kar. Packet
//  ka daam dukaan ke apne daam se JODA jata hai, har baar naye
//  se. Agar daam yahan likha hota, to dukandaar daam badalne par
//  Maakit purana daam dikhata rehta -- aur beech me phansta
//  graahak ka bharosa.
// ============================================================
require_once __DIR__ . '/../inc/fn.php';
require_once __DIR__ . '/../inc/dukan.php';
require_once __DIR__ . '/../inc/packet.php';
$u = need_role('admin');
$page_title = 'पैकेट — Maakit';

$bid = (int)get('b');
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) {
    $do  = post('do');
    $bid = (int)post('b') ?: $bid;

    if ($do === 'bot') {
        $bane = pack_bot($pdo, $bid);
        $msg  = $bane
            ? $bane . ' पैकेट बना दिए। नीचे देख लीजिए, ठीक लगें तो "चालू कीजिए" दबा दीजिए।'
            : 'बॉट पैकेट नहीं बना पाया — इस दुकान में दाम भरे हुए सामान कम हैं।';
    } elseif ($do === 'live') {
        pack_manzoor($pdo, (int)post('id'), $u['id']);
        $msg = 'पैकेट चालू हो गया — ग्राहक को अब दिखेगा।';
    } elseif ($do === 'hide') {
        $pdo->prepare("UPDATE packs SET status='hidden' WHERE id=?")->execute([(int)post('id')]);
        $msg = 'पैकेट हटा दिया गया।';
    } elseif ($do === 'naya') {
        $saaman = [];
        foreach ((array)($_POST['q'] ?? []) as $iid => $q) if ((int)$q > 0) $saaman[(int)$iid] = (int)$q;
        try {
            pack_banao($pdo, $bid, post('name'), $saaman, post('note'), 'haath', $u['id']);
            $msg = 'पैकेट बन गया। अब "चालू कीजिए" दबाने पर ग्राहक को दिखेगा।';
        } catch (Throwable $e) {
            $msg = 'पैकेट नहीं बना: ' . $e->getMessage();
        }
    }
}

$dukaanein = $pdo->query("SELECT id, name, village FROM businesses
                           WHERE status='approved' AND items_on=1 ORDER BY name")->fetchAll();
$b = null; $items = []; $packs = [];
if ($bid) {
    $st = $pdo->prepare("SELECT * FROM businesses WHERE id=? AND status='approved'");
    $st->execute([$bid]);
    $b = $st->fetch();
    if ($b) { $items = dukan_items($pdo, $bid, true); $packs = pack_list($pdo, $bid); }
}

include __DIR__ . '/../inc/panel.php';
?>
<section>
<div class="wrap">
  <h2>पैकेट</h2>
  <p class="lead">
    गाँव में ग्राहक 40 चीज़ों की सूची में से नहीं चुनता। वो कहता है
    <b>“सौ रुपये की सब्ज़ी भेज दो।”</b> पैकेट वही है — बना हुआ सेट, एक टैप में।
    दाम इसी दुकान के अपने दामों से जुड़ता है, हर बार नए सिरे से।
  </p>

  <?php if ($msg): ?><div class="ok"><?= h($msg) ?></div><?php endif; ?>

  <form method="get" style="margin:14px 0">
    <label>दुकान चुनिए<br>
      <select name="b" onchange="this.form.submit()" style="max-width:420px">
        <option value="">— दुकान —</option>
        <?php foreach ($dukaanein as $d): ?>
          <option value="<?= (int)$d['id'] ?>"<?= $bid === (int)$d['id'] ? ' selected' : '' ?>>
            <?= h($d['name']) ?> — <?= h($d['village']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
  </form>

  <?php if (!$b): ?>
    <p class="note">दुकान चुनने पर उसके पैकेट यहीं दिखेंगे।</p>
  <?php elseif (!$items): ?>
    <p class="note">इस दुकान में दाम भरा हुआ कोई सामान नहीं है। पहले
      <a href="/admin/daam.php">दाम</a> भरवाइए — बिना दाम का सामान पैकेट में नहीं जा सकता।</p>
  <?php else: ?>

    <h3>बॉट से बनवाइए</h3>
    <p class="note">बॉट इसी दुकान के अपने दामों से 100 और 200 रुपये के सेट जोड़ देगा।
      बजट से ज़्यादा का पैकेट कभी नहीं बनाएगा। चालू आप करेंगे, बॉट नहीं।</p>
    <form method="post" style="margin-bottom:22px">
      <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
      <input type="hidden" name="do" value="bot">
      <input type="hidden" name="b" value="<?= $bid ?>">
      <button class="btn">बॉट से पैकेट बनवाइए</button>
    </form>

    <h3>इस दुकान के पैकेट</h3>
    <?php if (!$packs): ?>
      <p class="note">अभी कोई पैकेट नहीं है।</p>
    <?php endif; ?>
    <?php foreach ($packs as $p): ?>
      <div class="box" style="margin-bottom:10px">
        <div style="display:flex;gap:10px;align-items:baseline;flex-wrap:wrap">
          <b style="font-size:17px"><?= h($p['name']) ?></b>
          <span style="font-weight:800;color:var(--brand)">₹<?= (int)$p['daam'] ?></span>
          <span class="meta">
            <?= $p['status'] === 'live' ? 'चालू' : ($p['status'] === 'hidden' ? 'हटाया हुआ' : 'मंज़ूरी बाक़ी') ?>
            <?= $p['banaya_by'] ? '· हाथ से' : '· बॉट ने बनाया' ?>
            <?= (int)$p['mange'] ? '· ' . (int)$p['mange'] . ' बार मँगाया गया' : '' ?>
          </span>
        </div>
        <?php if (!$p['poora']): ?>
          <div class="note" style="color:#B45309">इसमें कोई सामान ख़त्म है या उसका दाम नहीं भरा —
            ग्राहक को यह पैकेट अभी नहीं दिखेगा। आधा पैकेट दिखाना धोखा होगा।</div>
        <?php endif; ?>
        <ul style="margin:8px 0 10px">
          <?php foreach ($p['saaman'] as $s): ?>
            <li><?= h($s['name']) ?> × <?= max(1, (int)$s['qty']) ?>
              <span class="meta">₹<?= (int)$s['price'] ?><?= (int)$s['price'] <= 0 ? ' — दाम नहीं भरा' : '' ?></span></li>
          <?php endforeach; ?>
        </ul>
        <form method="post" style="display:inline">
          <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
          <input type="hidden" name="b" value="<?= $bid ?>">
          <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
          <?php if ($p['status'] !== 'live'): ?>
            <input type="hidden" name="do" value="live">
            <button class="btn">चालू कीजिए</button>
          <?php else: ?>
            <input type="hidden" name="do" value="hide">
            <button class="btn btn-ghost">हटा दीजिए</button>
          <?php endif; ?>
        </form>
      </div>
    <?php endforeach; ?>

    <h3 style="margin-top:24px">अपना पैकेट बनाइए</h3>
    <p class="note">जो बॉट नहीं सोच सकता — पूजा का सामान, त्योहार का सेट, दो दोस्त का नाश्ता।
      गिनती भरिए, नाम दीजिए।</p>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
      <input type="hidden" name="do" value="naya">
      <input type="hidden" name="b" value="<?= $bid ?>">
      <label>पैकेट का नाम<br><input name="name" maxlength="80" required
             placeholder="जैसे: पूजा का सामान" style="max-width:420px"></label>
      <label style="display:block;margin-top:8px">एक लाइन (क्यों/किसके लिए)<br>
        <input name="note" maxlength="160" placeholder="जैसे: छठ पूजा के लिए ज़रूरी सामान"
               style="max-width:520px"></label>
      <div class="box" style="margin:12px 0">
        <?php foreach ($items as $it): ?>
          <div style="border-top:1px solid var(--line);padding:8px 0;display:flex;gap:10px;align-items:center">
            <div style="flex:1;min-width:0"><b><?= h($it['name']) ?></b>
              <span class="meta"><?= h($it['unit']) ?> · ₹<?= (int)$it['price'] ?></span></div>
            <input type="number" name="q[<?= (int)$it['id'] ?>]" value="0" min="0" max="50"
                   inputmode="numeric" style="width:66px;flex:none"
                   aria-label="<?= h($it['name']) ?> की गिनती">
          </div>
        <?php endforeach; ?>
      </div>
      <button class="btn">पैकेट बनाइए</button>
    </form>

  <?php endif; ?>
</div>
</section>
<?php include __DIR__ . '/../inc/foot.php'; ?>
