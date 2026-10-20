<?php
// ============================================================
//  tab: आज की कहानी
//
//  Jo dukaandaar roz apne WhatsApp Status par "aaj ka maal aa
//  gaya" daalta hai, uska saamaan bikta hai. Par Status sirf
//  uske apne number walon tak jata hai.
//
//  Ye wahi cheez hai -- par har us aadmi tak pahunchti hai jo
//  Maakit kholta hai, chahe uske paas dukaan ka number ho ya na
//  ho. Aur neeche seedha "मँगाइए" ka button hota hai.
//
//  Panna jaanbujh kar khali rakha gaya hai: EK photo, EK line,
//  ek button. Dukaandaar graahak ke beech khada hai; usse jitna
//  kam maangenge, utna zyada wo daalega.
// ============================================================
$meri   = story_dukaan_ki($pdo, $bid);
$hadd   = story_hadd($pdo);
$aaj_ki = story_aaj_ki_ginti($pdo, $bid);
$bachi  = max(0, $hadd - $aaj_ki);
$ghante = story_ghante($pdo);
?>
<div class="sp-head">
  <div>
    <h1><?= t("Today's story", 'आज की कहानी') ?></h1>
    <p><?= t('One photo, one line. Everyone who opens Maakit will see it.',
             'एक फ़ोटो, एक लाइन। जो भी Maakit खोलेगा उसे दिखेगी।') ?></p>
  </div>
</div>

<div class="sp-card">
  <b><?= t('What is this for?', 'ये किस काम की है?') ?></b>
  <p class="sp-note">
    <?= t('Just like the photo you put on your WhatsApp Status — but this one also reaches people who do not have your number. It stays for ',
          'जैसे आप अपने WhatsApp Status पर फ़ोटो डालते हैं, वैसे ही — पर ये उन तक भी पहुँचती है जिनके पास आपका नंबर नहीं है। ये ') ?>
    <b><?= (int)$ghante ?> <?= t('hours', 'घंटे') ?></b><?= t(', then it goes on its own — because today’s stock is not tomorrow’s truth.',
          ' दिखती है, फिर अपने आप चली जाती है — क्योंकि आज का माल कल का सच नहीं रहता।') ?>
  </p>
</div>

<?php if ($bachi > 0): ?>
  <form class="sp-card" method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
    <input type="hidden" name="do" value="kahani">
    <input type="hidden" name="tab" value="kahani">

    <label class="sp-field"><span><?= t('Photo of today’s stock', 'आज के माल की फ़ोटो') ?></span>
      <input type="file" name="photo" accept="image/*" capture="environment">
      <small><?= t('Or skip it — a line alone works too.', 'या छोड़ दीजिए — अकेली लाइन से भी चलेगा।') ?></small></label>

    <label class="sp-field"><span><?= t('One line', 'एक लाइन') ?></span>
      <input type="text" name="baat" maxlength="140"
             placeholder="<?= h(t('e.g. Fresh peas came in today', 'जैसे: आज ताज़ा मटर आया है')) ?>">
      <small><?= t('Say what came in today and what the price is. Short lines work best.',
            'आज क्या आया और क्या भाव — इतना ही लिखिए। छोटी बात ज़्यादा चलती है।') ?></small></label>

    <button class="sp-btn sp-btn-go" type="submit" style="margin-top:14px">
      <?= t('Put it up', 'लगा दीजिए') ?>
    </button>
    <p class="sp-note">
      <?= t('Left today', 'आज बाकी') ?>: <b><?= (int)$bachi ?></b> / <?= (int)$hadd ?>
    </p>
  </form>
<?php else: ?>
  <div class="sp-card">
    <b><?= t('Today’s limit is done', 'आज की हद पूरी हो गई') ?> (<?= (int)$hadd ?>)</b>
    <p class="sp-note">
      <?= t('Put one up again tomorrow. The limit is there so every shop gets seen, not just one.',
            'कल फिर डालिए। हद इसलिए है कि हर दुकान दिखे, सिर्फ़ एक नहीं।') ?>
    </p>
  </div>
<?php endif; ?>

<div class="sp-card-h"><b><?= t('Your stories', 'आपकी कहानियाँ') ?></b></div>

<?php if (!$meri): ?>
  <div class="sp-card">
    <b><?= t('Nothing yet', 'अभी कोई नहीं') ?></b>
    <p class="sp-note">
      <?= t('Put up the first one — it takes less than a minute.',
            'पहली लगा दीजिए — एक मिनट भी नहीं लगेगा।') ?>
    </p>
  </div>
<?php else: foreach ($meri as $s):
  $chalu = !$s['hidden'] && strtotime($s['dikhe_tak']) > time();
?>
  <div class="sp-card" style="<?= $chalu ? 'border-left:4px solid var(--ok,#1F6B3A)' : 'opacity:.72' ?>">
    <div style="display:flex;gap:12px;align-items:flex-start">
      <?php if ($s['photo']): ?>
        <img src="/uploads/<?= h($s['photo']) ?>" alt=""
             style="width:62px;height:62px;border-radius:11px;object-fit:cover;flex:none">
      <?php endif; ?>
      <div style="flex:1;min-width:0">
        <?php if ($s['baat']): ?><b><?= h($s['baat']) ?></b><?php endif; ?>
        <p class="sp-note">
          <?= h(date('d M, g:i a', strtotime($s['created_at']))) ?> ·
          <?php if ($s['hidden']): ?>
            <?= t('removed', 'हटाई गई') ?>
          <?php elseif ($chalu): ?>
            <b style="color:var(--ok,#1F6B3A)"><?= t('showing now', 'अभी दिख रही है') ?></b>
          <?php else: ?>
            <?= t('time over', 'समय पूरा') ?>
          <?php endif; ?>
        </p>
        <p class="sp-note">
          <?= t('Seen', 'देखी गई') ?> <b><?= (int)$s['views'] ?></b> ·
          <?= t('orders from it', 'इससे ऑर्डर') ?> <b><?= (int)$s['orders'] ?></b>
        </p>
      </div>
      <?php if ($chalu): ?>
        <form method="post" style="flex:none">
          <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
          <input type="hidden" name="do" value="kahani_hatao">
          <input type="hidden" name="sid" value="<?= (int)$s['id'] ?>">
          <input type="hidden" name="tab" value="kahani">
          <button class="sp-btn" type="submit"><?= t('Remove', 'हटाइए') ?></button>
        </form>
      <?php endif; ?>
    </div>
  </div>
<?php endforeach; endif; ?>
