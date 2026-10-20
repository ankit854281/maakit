<?php
// ============================================================
//  किसने क्या किया — staff का काम, गिना हुआ
//
//  Ankit ne kaha: "jo staff work karenge unka update ho, verify ho".
//
//  IS PANNE KA EK HI NIYAM, AUR WAHI ISKI JAAN HAI:
//
//      Yahan koi apna kaam KHUD DARJ NAHI KARTA.
//
//  Kisi se nahi poocha jata ki "aaj kitni dukaan jodi". Panna
//  database se khud ginta hai. Jo kaam sach me hua, wahi dikhta
//  hai -- kehne par nahi, record par.
//
//  Agar staff se bhara jata, to teen cheezein hotin:
//    - bharne ka kaam alag se roz karna padta (aur chhoot jata)
//    - jhooth likhne ki gunjaish rehti
//    - jhagde ke din dono ke paas apna-apna kagaz hota
//
//  Ab in teeno ki jagah ek hi sach hai.
//
//  DO TARAH KI GINTI, AUR DONO ALAG HAIN:
//
//    KIYA   -- kitna kaam daala (dukaan jodi, order daala)
//    CHALA  -- usme se kitna sach me kaam aaya (dukaan manzoor
//              hui aur usne daam bhare; order pahuncha)
//
//  Dono dikhana zaroori hai. Sirf "kiya" dikhane se aadmi ginti
//  badhane lagta hai -- bees adhoori dukaanein jod dega. "Chala"
//  wali ginti usse rokti hai.
// ============================================================
require_once __DIR__ . '/../inc/fn.php';
need_role('admin');
$page_title = t('Who did what — Maakit', 'किसने क्या किया — Maakit');

// ---------- kitne din ka ----
$din = (int)get('din', 7);
if (!in_array($din, [1, 7, 30], true)) $din = 7;
$se  = date('Y-m-d', strtotime("-" . ($din - 1) . " day"));

$ek = function (string $sql, array $args = []) use ($pdo) {
    try { $st = $pdo->prepare($sql); $st->execute($args); return (int)$st->fetchColumn(); }
    catch (Throwable $e) { error_log('staff.php: ' . $e->getMessage()); return 0; }
};

// ---------- kaun kaun hai ----
$log = [];
try {
    $log = $pdo->query("SELECT id, name, username, role, mobile, active
                          FROM users
                         WHERE role IN ('bpo','delivery','rider','admin') AND active=1
                      ORDER BY FIELD(role,'bpo','delivery','rider','admin'), name")->fetchAll();
} catch (Throwable $e) { $log = []; }

foreach ($log as &$u) {
    $id = (int)$u['id'];

    // --- call sunkar order daalna (BPO) ---
    $u['order_daale']  = $ek("SELECT COUNT(*) FROM orders WHERE staff_user=? AND DATE(created_at)>=?", [$id, $se]);
    $u['order_pahuche']= $ek("SELECT COUNT(*) FROM orders WHERE staff_user=? AND DATE(created_at)>=? AND status='Delivered'", [$id, $se]);
    $u['order_radd']   = $ek("SELECT COUNT(*) FROM orders WHERE staff_user=? AND DATE(created_at)>=? AND status='Cancel'", [$id, $se]);

    // --- dukaan jodna (field) ---
    // "chali" ka matlab: manzoor bhi hui AUR usme daam bhi bhare.
    // Bina daam ki dukaan graahak ke liye hai hi nahi.
    $u['dukaan_jodi']  = $ek("SELECT COUNT(*) FROM businesses WHERE added_by=? AND DATE(created_at)>=?", [$id, $se]);
    $u['dukaan_chali'] = $ek("SELECT COUNT(*) FROM businesses b
                               WHERE b.added_by=? AND DATE(b.created_at)>=? AND b.status='approved'
                                 AND EXISTS (SELECT 1 FROM shop_items i
                                              WHERE i.business_id=b.id AND i.active=1 AND i.price>0)", [$id, $se]);

    // --- pahunchana (sarathi) ---
    $u['pahunchaye']   = $ek("SELECT COUNT(*) FROM orders WHERE delivery_user=? AND status='Delivered' AND DATE(delivered_at)>=?", [$id, $se]);
    $u['haath_me']     = $ek("SELECT COUNT(*) FROM orders WHERE delivery_user=? AND status IN ('Assign','Pickup')", [$id]);
    $u['dikkat']       = $ek("SELECT COUNT(*) FROM sarathi_samasya WHERE rider_user=? AND DATE(created_at)>=?", [$id, $se]);

    // --- manzoori (maalik) ---
    $u['manzoori']     = $ek("SELECT COUNT(*) FROM businesses WHERE approved_by=? AND DATE(approved_at)>=?", [$id, $se]);

    $u['kuch_kiya'] = $u['order_daale'] + $u['dukaan_jodi'] + $u['pahunchaye'] + $u['manzoori'];
}
unset($u);

// ---------- jo kisi ke naam par nahi ----
// Graahak ne khud website se order kiya -- wo kisi staff ka kaam
// nahi hai, aur use kisi ke khate me daalna jhooth hoga.
$khud_ke   = $ek("SELECT COUNT(*) FROM orders WHERE staff_user IS NULL AND DATE(created_at)>=?", [$se]);
$kul_order = $ek("SELECT COUNT(*) FROM orders WHERE DATE(created_at)>=?", [$se]);

include __DIR__ . '/../inc/panel.php';
?>
<style>
  .st-lead{color:var(--mut,#6B5D55);margin:0 0 16px}
  .st-din{display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap}
  .st-din a{padding:9px 15px;border-radius:9px;background:#EFEAE0;color:inherit;
            text-decoration:none;font-weight:700;font-size:15px}
  .st-din a.on{background:var(--brand,#7A1F1F);color:#fff}
  .st-p{background:#fff;border:1px solid var(--line,#E3D8C6);border-radius:14px;
        padding:15px;margin-bottom:11px}
  .st-p-h{display:flex;align-items:baseline;gap:9px;flex-wrap:wrap;margin-bottom:10px}
  .st-p-h b{font-size:17px}
  .st-tag{font-size:12.5px;background:#EFEAE0;color:var(--mut,#6B5D55);
          border-radius:6px;padding:3px 8px;font-weight:700}
  .st-n{display:flex;gap:18px;flex-wrap:wrap}
  .st-n div{min-width:84px}
  .st-n b{display:block;font-size:25px;line-height:1.1;color:var(--brand,#7A1F1F)}
  .st-n small{color:var(--mut,#6B5D55);font-size:13px}
  .st-n .ok b{color:var(--ok,#1F6B3A)}
  .st-n .warn b{color:var(--warn,#A33427)}
  .st-khali{color:var(--mut,#6B5D55);font-size:14.5px;margin:0}
  .st-note{background:#FDF6E3;border:1px solid var(--gold,#E0A526);
           border-radius:13px;padding:15px 17px;margin:20px 0}
</style>

<section><div class="wrap" style="max-width:860px">

  <h1><?= h(t('Who did what', 'किसने क्या किया')) ?></h1>
  <p class="st-lead"><?= h(t(
     'Nobody fills this in. The page counts it from the real records — so what is shown here actually happened.',
     'ये कोई भरता नहीं है। पन्ना ख़ुद रिकॉर्ड से गिनता है — यानी जो यहाँ दिख रहा है, वो सच में हुआ है।')) ?></p>

  <div class="st-din">
    <?php foreach ([1 => t('Today', 'आज'), 7 => t('7 days', '7 दिन'), 30 => t('30 days', '30 दिन')] as $d => $l): ?>
      <a class="<?= $din === $d ? 'on' : '' ?>" href="/admin/staff.php?din=<?= $d ?>"><?= h($l) ?></a>
    <?php endforeach; ?>
  </div>

  <?php if (!$log): ?>
    <div class="st-p"><b><?= h(t('No staff yet', 'अभी कोई staff नहीं')) ?></b>
      <p class="st-khali" style="margin-top:6px">
        <a href="/admin/users.php"><?= h(t('Add your team', 'अपनी टीम जोड़िए')) ?></a></p></div>
  <?php else: foreach ($log as $u): ?>
    <div class="st-p">
      <div class="st-p-h">
        <b><?= h($u['name']) ?></b>
        <span class="st-tag"><?= h([
            'bpo' => t('Call / orders', 'कॉल / ऑर्डर'),
            'delivery' => t('Sarathi', 'सारथी'),
            'rider' => t('Sarathi', 'सारथी'),
            'admin' => t('Office', 'दफ़्तर'),
        ][$u['role']] ?? $u['role']) ?></span>
        <?php if ($u['mobile']): ?><span class="st-tag"><?= h($u['mobile']) ?></span><?php endif; ?>
      </div>

      <?php if (!$u['kuch_kiya'] && !$u['haath_me']): ?>
        <p class="st-khali"><?= h(t('Nothing recorded in this period.', 'इस दौरान कुछ दर्ज नहीं हुआ।')) ?></p>
      <?php else: ?>
        <div class="st-n">
          <?php if ($u['order_daale']): ?>
            <div><b><?= $u['order_daale'] ?></b><small><?= h(t('orders entered', 'ऑर्डर डाले')) ?></small></div>
            <div class="ok"><b><?= $u['order_pahuche'] ?></b><small><?= h(t('of them delivered', 'उनमें से पहुँचे')) ?></small></div>
            <?php if ($u['order_radd']): ?>
              <div class="warn"><b><?= $u['order_radd'] ?></b><small><?= h(t('cancelled', 'रद्द हुए')) ?></small></div>
            <?php endif; ?>
          <?php endif; ?>

          <?php if ($u['dukaan_jodi']): ?>
            <div><b><?= $u['dukaan_jodi'] ?></b><small><?= h(t('shops added', 'दुकानें जोड़ीं')) ?></small></div>
            <div class="ok"><b><?= $u['dukaan_chali'] ?></b><small><?= h(t('of them running', 'उनमें से चल रहीं')) ?></small></div>
          <?php endif; ?>

          <?php if ($u['pahunchaye'] || $u['haath_me']): ?>
            <div class="ok"><b><?= $u['pahunchaye'] ?></b><small><?= h(t('delivered', 'पहुँचाए')) ?></small></div>
            <?php if ($u['haath_me']): ?>
              <div><b><?= $u['haath_me'] ?></b><small><?= h(t('in hand now', 'अभी हाथ में')) ?></small></div>
            <?php endif; ?>
            <?php if ($u['dikkat']): ?>
              <div class="warn"><b><?= $u['dikkat'] ?></b><small><?= h(t('problems raised', 'दिक्कतें बताईं')) ?></small></div>
            <?php endif; ?>
          <?php endif; ?>

          <?php if ($u['manzoori']): ?>
            <div><b><?= $u['manzoori'] ?></b><small><?= h(t('shops approved', 'दुकानें मंज़ूर कीं')) ?></small></div>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  <?php endforeach; endif; ?>

  <div class="st-note">
    <b><?= h(t('Read this before judging anyone by the numbers',
               'इन आँकड़ों से किसी को आँकने से पहले ये पढ़ लीजिए')) ?></b>
    <p style="margin:8px 0 0">
      <?= h(t('“Added” and “running” are different on purpose. Twenty half-finished shops are worth less than three running ones. If you only look at the first number, people will start chasing the number instead of the work.',
              '"जोड़ीं" और "चल रहीं" जान-बूझकर अलग रखे हैं। बीस अधूरी दुकानों से तीन चलती दुकानें बेहतर हैं। अगर आप सिर्फ़ पहला नंबर देखेंगे, तो लोग काम की जगह नंबर बढ़ाने लगेंगे।')) ?>
    </p>
    <?php if ($kul_order): ?>
      <p style="margin:10px 0 0">
        <?= h(t('In this period', 'इस दौरान')) ?> <b><?= $khud_ke ?></b>/<?= $kul_order ?>
        <?= h(t('orders came from customers themselves on the website — those are nobody’s credit, and are left out.',
                'ऑर्डर ग्राहक ने ख़ुद वेबसाइट से किए — वो किसी के खाते में नहीं जाते, इसलिए छोड़ दिए गए हैं।')) ?>
      </p>
    <?php endif; ?>
    <p style="margin:10px 0 0">
      <?= h(t('Records before today have no name on them — that is honest, not a bug. Do not fill in guesses.',
              'आज से पहले के रिकॉर्ड पर कोई नाम नहीं है — ये सच है, ख़राबी नहीं। अंदाज़े से मत भरिए।')) ?>
    </p>
  </div>

</div></section>
<?php include __DIR__ . '/../inc/foot.php'; ?>
