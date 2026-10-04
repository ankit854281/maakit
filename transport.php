<?php
require_once __DIR__ . '/inc/fn.php';
require_once __DIR__ . '/inc/icons.php';
require_once __DIR__ . '/inc/salon.php';        // shop login ke liye
require_once __DIR__ . '/inc/transport.php';

$tab = 'kaam';
$villages = village_list($pdo);
$VT = vtypes();
$kind = (get('k') === 'g' || post('kind') === 'g') ? 'g' : ((get('k') === 'p' || post('kind') === 'p') ? 'p' : '');
$err = ''; $done = null;

// ---------- gaadi wale ka apna panel (login ho to) ----------
$biz = transport_login_business($pdo);
$me  = $biz ? transport_by_business($pdo, $biz['id']) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) {
    $do = post('do');

    // ---- login ----
    if ($do === 'login') {
        $mob  = preg_replace('/\D/', '', post('mobile'));
        $code = strtoupper(trim(post('code')));
        $tryk = 'trlog'; $_SESSION[$tryk] = $_SESSION[$tryk] ?? ['n'=>0,'t'=>time()];
        if (time() - $_SESSION[$tryk]['t'] > 900) $_SESSION[$tryk] = ['n'=>0,'t'=>time()];
        if ($_SESSION[$tryk]['n'] >= 5) {
            $err = t('Too many tries. Wait 15 minutes or call us.', 'बहुत बार कोशिश हो गई। 15 मिनट बाद या हमें कॉल कीजिए।');
        } else {
            $s = $pdo->prepare("SELECT * FROM transports WHERE mobile=? AND access_code=? AND status<>'hidden'");
            $s->execute([$mob, $code]);
            if ($r = $s->fetch()) {
                shop_start_session($pdo, (int)$r['business_id']);
                redirect('/transport.php');
            }
            $_SESSION[$tryk]['n']++;
            $err = t('Number or code is wrong.', 'नंबर या कोड ग़लत है।');
        }
    }

    elseif ($do === 'logout') { shop_logout($pdo); redirect('/transport.php'); }

    // ---- aaj khaali hai ya nahi ----
    elseif ($do === 'avail' && $me) {
        $a = post('v') === '1' ? 1 : 0;
        $pdo->prepare("UPDATE transports SET available=?, avail_updated=NOW() WHERE id=?")->execute([$a, $me['id']]);
        flash($a ? t('Marked available. You will show on the booking page.', 'खाली दर्ज हो गया। बुकिंग पेज पर दिखेंगे।')
                 : t('Marked busy. You will not show today.', 'व्यस्त दर्ज हो गया। आज नहीं दिखेंगे।'));
        redirect('/transport.php');
    }

    // ---- rate / jaankari badliye ----
    elseif ($do === 'save' && $me) {
        $ph = save_item_photo('photo', 'veh', 700);
        $pdo->prepare("UPDATE transports SET rate_km=?, rate_min=?, rate_day=?, rate_wait=?, rate_trip=?,
                       outstation=?, night=?, note=?, seats=?, ac=?, vnumber=?, mobile2=?,
                       capacity=?, loading=?, ins_exp=?, fit_exp=?" . ($ph ? ", photo=?" : "") . " WHERE id=?")
            ->execute(array_merge([
                (int)post('rate_km') ?: null, (int)post('rate_min') ?: null,
                (int)post('rate_day') ?: null, (int)post('rate_wait') ?: null, (int)post('rate_trip') ?: null,
                post('outstation') ? 1 : 0, post('night') ? 1 : 0,
                mb_substr(trim(post('note')), 0, 255), (int)post('seats'),
                post('ac') ? 1 : 0, mb_substr(strtoupper(trim(post('vnumber'))), 0, 20),
                preg_replace('/\D/', '', post('mobile2')) ?: null,
                (float)post('capacity') ?: null, post('loading') ? 1 : 0,
                post('ins_exp') ?: null, post('fit_exp') ?: null,
            ], $ph ? [$ph] : [], [$me['id']]));
        if ($ph && $me['photo']) drop_photo($me['photo']);
        flash(t('Saved.', 'सेव हो गया।'));
        redirect('/transport.php');
    }

    // ---- naya registration ----
    elseif ($do === 'reg') {
        $owner = trim(post('owner'));
        $mob   = preg_replace('/\D/', '', post('mobile'));
        $vt    = post('vtype');
        $vnum  = strtoupper(trim(post('vnumber')));
        $vill  = post('village');
        $decl  = post('declare') ? 1 : 0;

        if (mb_strlen($owner) < 2)            { $err = t('Please write your name.', 'अपना नाम लिखिए।'); }
        elseif (strlen($mob) !== 10)          { $err = t('Mobile number must be 10 digits.', 'मोबाइल नंबर 10 अंकों का लिखिए।'); }
        elseif (!isset($VT[$vt]))             { $err = t('Please choose your vehicle.', 'अपनी गाड़ी चुनिए।'); }
        elseif (mb_strlen($vnum) < 6)         { $err = t('Please write the full vehicle number.', 'गाड़ी का पूरा नंबर लिखिए।'); }
        elseif (!$decl)                       { $err = t('Please tick the papers declaration.', 'कागज़ वाली बात पर टिक कीजिए।'); }
        else {
            $c = $pdo->prepare("SELECT id FROM transports WHERE mobile=? OR vnumber=?");
            $c->execute([$mob, $vnum]);
            if ($c->fetch()) {
                $err = t('This number or vehicle is already registered. Sign in below.', 'यह नंबर या गाड़ी पहले से दर्ज है। नीचे लॉगिन कीजिए।');
            } else {
                $bname = trim(post('bname')) ?: ($owner . ' — ' . vtype_label($vt));
                $photo = save_item_photo('photo', 'veh', 700);
                $code  = make_access_code();

                $pdo->prepare("INSERT INTO businesses (name, owner, category, work, mobile, village, address, about, photo, status)
                               VALUES (?,?,'gaadi',?,?,?,?,?,?, 'pending')")
                    ->execute([mb_substr($bname, 0, 120), $owner,
                               vtype_label($vt) . ($vnum ? ' · ' . $vnum : ''),
                               $mob, $vill, mb_substr(trim(post('address')), 0, 200),
                               mb_substr(trim(post('about')), 0, 500), $photo]);
                $bid = (int)$pdo->lastInsertId();

                $pdo->prepare("INSERT INTO transports
                    (business_id, owner, mobile, village, vtype, vnumber, seats, ac, photo,
                     rate_km, rate_min, rate_day, rate_trip, outstation, night, note,
                     goods, passenger, capacity, loading,
                     dl_ok, rc_ok, ins_ok, permit_ok, ins_exp, fit_exp, access_code, available, avail_updated, status)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1,NOW(),'pending')")
                    ->execute([$bid, $owner, $mob, $vill, $vt, $vnum,
                        (int)post('seats') ?: ($VT[$vt]['seats'] ?? 0),
                        post('ac') ? 1 : 0, $photo,
                        (int)post('rate_km') ?: null, (int)post('rate_min') ?: null,
                        (int)post('rate_day') ?: null, (int)post('rate_trip') ?: null,
                        post('outstation') ? 1 : 0, post('night') ? 1 : 0,
                        mb_substr(trim(post('note')), 0, 255),
                        vtype_is_goods($vt) ? 1 : 0, vtype_is_goods($vt) ? 0 : 1,
                        (float)post('capacity') ?: (vtype_cap($vt) ?: null),
                        post('loading') ? 1 : 0,
                        post('dl_ok') ? 1 : 0, post('rc_ok') ? 1 : 0,
                        post('ins_ok') ? 1 : 0, post('permit_ok') ? 1 : 0,
                        post('ins_exp') ?: null, post('fit_exp') ?: null, $code]);

                $done = ['code' => $code, 'mobile' => $mob, 'name' => $bname];
            }
        }
    }
}

$page_title = $me ? t('My vehicle — Maakit', 'मेरी गाड़ी — Maakit')
                  : t('Register your vehicle — Maakit', 'अपनी गाड़ी जोड़िए — Maakit');
include __DIR__ . '/inc/head.php';
?>
<div class="wrap" style="max-width:700px">

<?php if ($done): ?>
  <!-- ================= registration ho gaya ================= -->
  <div class="box" style="margin-top:18px;text-align:center">
    <div class="okmark"><?= svc_icon('shield', 34) ?></div>
    <h2 style="margin:10px 0 4px"><?= t('Vehicle submitted', 'गाड़ी दर्ज हो गई') ?></h2>
    <p class="lead" style="margin-bottom:0"><?= t(
      'Maakit will check your papers and call you. After that your vehicle starts showing on the booking page.',
      'Maakit आपके कागज़ देखकर आपको कॉल करेगा। उसके बाद आपकी गाड़ी बुकिंग पेज पर दिखने लगेगी।') ?></p>

    <div class="codebox">
      <div class="l"><?= t('YOUR LOGIN CODE', 'आपका लॉगिन कोड') ?></div>
      <div style="font-size:30px;font-weight:800;letter-spacing:3px;margin:8px 0"><?= h($done['code']) ?></div>
      <div class="h"><?= t('Write this down. With your mobile number, this is how you sign in to mark your vehicle free or busy each day.',
                           'इसे लिख लीजिए। अपने मोबाइल नंबर और इसी कोड से लॉगिन करके रोज़ बता सकते हैं कि गाड़ी खाली है या नहीं।') ?></div>
    </div>

    <div style="display:grid;gap:9px;margin-top:6px">
      <a class="btn btn-green" href="<?= h(wa_link(MAAKIT_WA, t('Hello Maakit, I registered my vehicle: ', 'नमस्ते Maakit, मैंने अपनी गाड़ी दर्ज की है: ') . $done['name'])) ?>" target="_blank" rel="noopener"><?= t('Tell us on WhatsApp', 'WhatsApp पर बताइए') ?></a>
      <a class="btn btn-brand" href="/transport.php"><?= t('Sign in to my panel', 'अपने पैनल में जाइए') ?></a>
    </div>
  </div>

<?php elseif ($me):
  $vt = $me['vtype']; $fresh = avail_fresh($me);
  list($pc, $pl) = papers_badge($me); ?>
  <!-- ================= gaadi wale ka panel ================= -->
  <div class="prof" style="align-items:flex-start">
    <span class="av" style="border-radius:16px"><?= svc_icon(vtype_icon($vt), 28) ?></span>
    <div>
      <b><?= h($biz['name']) ?></b>
      <i><?= h(vtype_label($vt)) ?><?= $me['vnumber'] ? ' · ' . h($me['vnumber']) : '' ?><?= $me['goods'] && $me['capacity'] ? ' · ' . h(cap_line($me)) : '' ?></i>
      <div style="margin-top:5px">
        <span class="tag <?= $me['status'] === 'approved' ? 'tag-live' : 'tag-off' ?>">
          <?= $me['status'] === 'approved' ? t('Live on the site', 'साइट पर दिख रहे हैं') : t('Waiting for approval', 'मंज़ूरी का इंतज़ार') ?></span>
        <span class="tag <?= h($pc) ?>"><?= h($pl) ?></span>
        <?php if ($me['trips']): ?><span class="tag tag-gold"><?= num($me['trips']) ?> <?= t('trips', 'ट्रिप') ?></span><?php endif; ?>
      </div>
    </div>
    <form method="post" style="margin-left:auto">
      <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="logout">
      <button class="btn btn-sm btn-line" style="color:var(--brand);border-color:var(--line)"><?= t('Sign out', 'लॉग आउट') ?></button>
    </form>
  </div>

  <?php if ($err): ?><div class="err"><?= h($err) ?></div><?php endif; ?>

  <!-- aaj khaali ho ya nahi -->
  <div class="box" style="margin-bottom:14px">
    <h3 style="margin:0 0 4px;font-size:18px"><?= t('Are you free today?', 'आज गाड़ी खाली है?') ?></h3>
    <p class="help" style="margin:0 0 12px"><?= t(
      'Only vehicles marked free today show on the booking page. Tap this every morning.',
      'बुकिंग पेज पर वही गाड़ियाँ दिखती हैं जो आज खाली हैं। हर सुबह एक बार दबा दीजिए।') ?></p>
    <div style="display:flex;gap:9px;flex-wrap:wrap">
      <form method="post" style="flex:1;min-width:140px">
        <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="avail"><input type="hidden" name="v" value="1">
        <button class="btn <?= ($me['available'] && $fresh) ? 'btn-green' : 'btn-line' ?>" style="width:100%<?= ($me['available'] && $fresh) ? '' : ';color:var(--brand);border-color:var(--line)' ?>">
          <?= t('Yes, I am free', 'हाँ, खाली हूँ') ?></button>
      </form>
      <form method="post" style="flex:1;min-width:140px">
        <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="avail"><input type="hidden" name="v" value="0">
        <button class="btn <?= !$me['available'] ? 'btn-brand' : 'btn-line' ?>" style="width:100%<?= !$me['available'] ? '' : ';color:var(--brand);border-color:var(--line)' ?>">
          <?= t('No, busy today', 'नहीं, आज व्यस्त हूँ') ?></button>
      </form>
    </div>
    <p class="help" style="margin-top:10px">
      <?php if (!$me['avail_updated']): ?><?= t('Not set yet.', 'अभी बताया नहीं है।') ?>
      <?php elseif (!$fresh): ?><b style="color:var(--bad)"><?= t('Last told ', 'आख़िरी बार ') . ago($me['avail_updated']) ?>.
        <?= t('Please tell us today too, or you will not show.', 'आज भी बता दीजिए, वरना नहीं दिखेंगे।') ?></b>
      <?php else: ?><?= t('Told ', 'बताया ') . ago($me['avail_updated']) ?><?php endif; ?>
    </p>
  </div>

  <!-- rate aur jaankari -->
  <form method="post" enctype="multipart/form-data" class="box" style="margin-bottom:14px">
    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="save">
    <h3 style="margin:0 0 4px;font-size:18px"><?= t('My rates', 'मेरा रेट') ?></h3>
    <p class="help" style="margin:0 0 12px"><?= t(
      'Customers see these before they book. Keep them honest — fights over price are the fastest way to lose work.',
      'बुकिंग से पहले ग्राहक यही देखता है। सही-सही रखिए — दाम पर झगड़ा होने से काम सबसे जल्दी छूटता है।') ?></p>
    <div class="grid g2">
      <div class="field"><label><?= t('Per km ₹', 'प्रति किलोमीटर ₹') ?></label>
        <input type="number" name="rate_km" value="<?= h($me['rate_km']) ?>" placeholder="14"></div>
      <div class="field"><label><?= t('Minimum fare ₹', 'कम से कम किराया ₹') ?></label>
        <input type="number" name="rate_min" value="<?= h($me['rate_min']) ?>" placeholder="500"></div>
      <div class="field"><label><?= t('Full day ₹', 'पूरे दिन का ₹') ?></label>
        <input type="number" name="rate_day" value="<?= h($me['rate_day']) ?>" placeholder="2500"></div>
      <div class="field"><label><?= t('Waiting per hour ₹', 'रुकने का प्रति घंटा ₹') ?></label>
        <input type="number" name="rate_wait" value="<?= h($me['rate_wait']) ?>" placeholder="100"></div>
      <?php if ($me['goods']): ?>
        <div class="field"><label><?= t('Per trip ₹', 'एक फेरे का ₹') ?></label>
          <input type="number" name="rate_trip" value="<?= h($me['rate_trip']) ?>" placeholder="1200"></div>
        <div class="field"><label><?= t('How much it carries (tonne)', 'कितना माल जाता है (टन)') ?></label>
          <input type="number" step="0.25" name="capacity" value="<?= h($me['capacity']) ?>" placeholder="3"></div>
      <?php endif; ?>
    </div>
    <div class="grid g2">
      <?php if (!$me['goods']): ?>
      <div class="field"><label><?= t('Seats', 'कितनी सीट') ?></label>
        <input type="number" name="seats" value="<?= (int)$me['seats'] ?>" min="0" max="60"></div>
      <?php endif; ?>
      <div class="field"><label><?= t('Vehicle number', 'गाड़ी का नंबर') ?></label>
        <input type="text" name="vnumber" value="<?= h($me['vnumber']) ?>" placeholder="UP 65 AB 1234"></div>
      <div class="field"><label><?= t('Second number', 'दूसरा नंबर') ?> <i class="opt">(<?= t('optional', 'चाहें तो') ?>)</i></label>
        <input type="tel" name="mobile2" value="<?= h($me['mobile2']) ?>" maxlength="10" inputmode="numeric"></div>
      <div class="field"><label><?= t('Vehicle photo', 'गाड़ी की फ़ोटो') ?></label>
        <input type="file" name="photo" accept="image/*"></div>
    </div>
    <div class="pickers" style="margin-bottom:14px">
      <?php if ($me['goods']): ?>
        <label class="pick"><input type="checkbox" name="loading" value="1" <?= $me['loading'] ? 'checked' : '' ?>><span><?= t('I arrange loading labour', 'मज़दूर भी लगवा देता हूँ') ?></span></label>
      <?php else: ?>
        <label class="pick"><input type="checkbox" name="ac" value="1" <?= $me['ac'] ? 'checked' : '' ?>><span>AC</span></label>
      <?php endif; ?>
      <label class="pick"><input type="checkbox" name="outstation" value="1" <?= $me['outstation'] ? 'checked' : '' ?>><span><?= t('Outstation', 'बाहर भी जाता हूँ') ?></span></label>
      <label class="pick"><input type="checkbox" name="night" value="1" <?= $me['night'] ? 'checked' : '' ?>><span><?= t('Night trips', 'रात में भी') ?></span></label>
    </div>
    <div class="grid g2">
      <div class="field"><label><?= t('Insurance valid till', 'बीमा कब तक') ?></label>
        <input type="date" name="ins_exp" value="<?= h($me['ins_exp']) ?>"></div>
      <div class="field"><label><?= t('Fitness valid till', 'फिटनेस कब तक') ?></label>
        <input type="date" name="fit_exp" value="<?= h($me['fit_exp']) ?>"></div>
    </div>
    <div class="field"><label><?= t('Anything to tell customers', 'ग्राहक को कुछ बताना है') ?></label>
      <input type="text" name="note" value="<?= h($me['note']) ?>" maxlength="255"
             placeholder="<?= h(t('e.g. Varanasi and Prayagraj daily, driver speaks Bhojpuri', 'जैसे: वाराणसी-प्रयागराज रोज़, गाड़ी साफ़ रखता हूँ')) ?>"></div>
    <button class="btn btn-brand" style="width:100%"><?= t('Save', 'सेव कीजिए') ?></button>
  </form>

  <?php if ($me['ins_exp'] || $me['fit_exp']):
    $soon = [];
    foreach ([['ins_exp', t('Insurance', 'बीमा')], ['fit_exp', t('Fitness', 'फिटनेस')]] as list($k, $lbl)) {
      if ($me[$k] && strtotime($me[$k]) < time() + 30 * 86400) {
        $soon[] = $lbl . ' — ' . (strtotime($me[$k]) < time()
          ? t('expired', 'खत्म हो चुका') : t('expires ', 'खत्म ') . dt_fmt($me[$k]));
      }
    }
    if ($soon): ?>
      <div class="err"><b><?= t('Renew your papers', 'कागज़ नया करवा लीजिए') ?>:</b><br><?= h(implode(' · ', $soon)) ?><br>
        <span style="font-size:13.5px"><?= t('Maakit cannot send trips on expired papers.', 'खत्म कागज़ पर Maakit ट्रिप नहीं भेज सकता।') ?></span></div>
  <?php endif; endif; ?>

  <div class="box" style="margin-bottom:30px">
    <b><?= t('How work reaches you', 'काम आपके पास कैसे आएगा') ?></b>
    <ol class="help" style="margin:8px 0 0;padding-left:18px;line-height:1.85">
      <li><?= t('Customer picks your vehicle on the booking page and sends a request.', 'ग्राहक बुकिंग पेज पर आपकी गाड़ी चुनकर बुकिंग भेजता है।') ?></li>
      <li><?= t('Maakit calls you to check you are free and confirms the fare.', 'Maakit आपको कॉल करके पूछता है और किराया तय करता है।') ?></li>
      <li><?= t('Maakit gives you the customer’s number and location. You do the trip.', 'Maakit आपको ग्राहक का नंबर और जगह देता है। ट्रिप आप करते हैं।') ?></li>
      <li><?= t('Money is between you and the customer. Maakit’s share is agreed on the call.', 'पैसा आपके और ग्राहक के बीच। Maakit का हिस्सा कॉल पर तय होता है।') ?></li>
    </ol>
  </div>

<?php else: ?>
  <!-- ================= registration / login ================= -->
  <div class="pghead">
    <span class="bigic"><?= svc_icon('ride', 36) ?></span>
    <h1><?= t('Put your vehicle to work', 'अपनी गाड़ी जोड़िए') ?>
      <span><?= t('Passengers or goods — Bolero, tempo, auto, chhota hathi, dala, truck, tractor', 'सवारी हो या माल — बोलेरो, टेम्पो, ऑटो, छोटा हाथी, डाला, ट्रक, ट्रैक्टर') ?></span></h1>
    <p><?= t(
      'Your vehicle stands idle, people in the village have no ride. Register once — when someone books, Maakit calls you.',
      'आपकी गाड़ी खड़ी रहती है और गाँव में लोगों को सवारी नहीं मिलती। एक बार दर्ज कीजिए — जब कोई बुकिंग करेगा, Maakit आपको कॉल करेगा।') ?></p>
  </div>

  <div class="promise">
    <span><?= svc_icon('rupee', 18) ?> <?= t('Free to list', 'जोड़ने का कोई पैसा नहीं') ?></span>
    <span><?= svc_icon('clock', 18) ?> <?= t('You decide when you are free', 'खाली कब हैं, आप तय करेंगे') ?></span>
    <span><?= svc_icon('shield', 18) ?> <?= t('Fare fixed before the trip', 'किराया ट्रिप से पहले तय') ?></span>
  </div>

  <?php if ($err): ?><div class="err"><?= h($err) ?></div><?php endif; ?>

  <form method="post" enctype="multipart/form-data" class="box" id="regf">
    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="reg">

    <h3 style="margin:0 0 12px;font-size:18px"><?= t('Your vehicle', 'आपकी गाड़ी') ?></h3>
    <div class="field"><label for="vtype"><?= t('Which vehicle', 'कौन सी गाड़ी') ?></label>
      <select id="vtype" name="vtype" required>
        <option value="">— <?= t('Choose', 'चुनिए') ?> —</option>
        <optgroup label="<?= h(t('Passengers', 'सवारी')) ?>">
          <?php foreach (vtypes_of('p') as $k => $v): ?>
            <option value="<?= h($k) ?>" data-seats="<?= (int)$v['seats'] ?>" data-g="0" data-cap=""
              <?= post('vtype') === $k ? 'selected' : '' ?>><?= h(is_hi() ? $v['hi'] : $v['en']) ?></option>
          <?php endforeach; ?>
        </optgroup>
        <optgroup label="<?= h(t('Goods', 'माल ढुलाई')) ?>">
          <?php foreach (vtypes_of('g') as $k => $v): ?>
            <option value="<?= h($k) ?>" data-seats="<?= (int)$v['seats'] ?>" data-g="1" data-cap="<?= h($v['cap'] ?? '') ?>"
              <?= post('vtype') === $k ? 'selected' : '' ?>><?= h(is_hi() ? $v['hi'] : $v['en']) ?></option>
          <?php endforeach; ?>
        </optgroup>
      </select></div>
    <div class="grid g2">
      <div class="field"><label for="vnumber"><?= t('Vehicle number', 'गाड़ी का नंबर') ?></label>
        <input type="text" id="vnumber" name="vnumber" value="<?= h(post('vnumber')) ?>" required
               placeholder="UP 65 AB 1234" autocapitalize="characters"></div>
      <div class="field only-p"><label for="seats"><?= t('Seats', 'कितनी सीट') ?></label>
        <input type="number" id="seats" name="seats" value="<?= h(post('seats')) ?>" min="0" max="60"></div>
      <div class="field only-g" style="display:none"><label for="capacity"><?= t('How much it carries (tonne)', 'कितना माल जाता है (टन)') ?></label>
        <input type="number" step="0.25" id="capacity" name="capacity" value="<?= h(post('capacity')) ?>" min="0" max="40" placeholder="3"></div>
    </div>
    <div class="field"><label for="photo"><?= t('Photo of your vehicle', 'गाड़ी की फ़ोटो') ?>
      <i class="opt">(<?= t('helps a lot', 'बहुत फ़ायदा करती है') ?>)</i></label>
      <input type="file" id="photo" name="photo" accept="image/*"></div>
    <div class="pickers" style="margin-bottom:16px">
      <label class="pick only-p"><input type="checkbox" name="ac" value="1"><span>AC</span></label>
      <label class="pick only-g" style="display:none"><input type="checkbox" name="loading" value="1"><span><?= t('I arrange loading labour', 'मज़दूर भी लगवा देता हूँ') ?></span></label>
      <label class="pick"><input type="checkbox" name="outstation" value="1" checked><span><?= t('Outstation', 'बाहर भी जाता हूँ') ?></span></label>
      <label class="pick"><input type="checkbox" name="night" value="1"><span><?= t('Night trips', 'रात में भी') ?></span></label>
    </div>

    <hr class="sep">
    <h3 style="margin:0 0 4px;font-size:18px"><?= t('Your rate', 'आपका रेट') ?></h3>
    <p class="help" style="margin:0 0 12px"><?= t('Leave blank if you prefer to talk on the phone.', 'अभी न बताना हो तो खाली छोड़ दीजिए — कॉल पर तय कर लेंगे।') ?></p>
    <div class="grid g2">
      <div class="field"><label><?= t('Per km ₹', 'प्रति किलोमीटर ₹') ?></label><input type="number" name="rate_km" value="<?= h(post('rate_km')) ?>" placeholder="14"></div>
      <div class="field"><label><?= t('Minimum fare ₹', 'कम से कम किराया ₹') ?></label><input type="number" name="rate_min" value="<?= h(post('rate_min')) ?>" placeholder="500"></div>
      <div class="field only-g" style="display:none"><label><?= t('Per trip ₹', 'एक फेरे का ₹') ?></label><input type="number" name="rate_trip" value="<?= h(post('rate_trip')) ?>" placeholder="1200"></div>
      <div class="field"><label><?= t('Full day ₹', 'पूरे दिन का ₹') ?></label><input type="number" name="rate_day" value="<?= h(post('rate_day')) ?>" placeholder="2500"></div>
      <div class="field"><label><?= t('Anything to add', 'कुछ और बताना है') ?></label><input type="text" name="note" value="<?= h(post('note')) ?>" maxlength="255"></div>
    </div>

    <hr class="sep">
    <h3 style="margin:0 0 12px;font-size:18px"><?= t('About you', 'आपके बारे में') ?></h3>
    <div class="grid g2">
      <div class="field"><label for="owner"><?= t('Your name', 'आपका नाम') ?></label>
        <input type="text" id="owner" name="owner" value="<?= h(post('owner')) ?>" required></div>
      <div class="field"><label for="mobile"><?= t('Mobile number', 'मोबाइल नंबर') ?></label>
        <input type="tel" id="mobile" name="mobile" value="<?= h(post('mobile')) ?>" maxlength="10" inputmode="numeric" required></div>
    </div>
    <div class="field"><label for="bname"><?= t('Name to show customers', 'ग्राहक को कौन सा नाम दिखे') ?>
      <i class="opt">(<?= t('optional', 'चाहें तो') ?>)</i></label>
      <input type="text" id="bname" name="bname" value="<?= h(post('bname')) ?>"
             placeholder="<?= h(t('e.g. Ramesh Travels', 'जैसे: रमेश ट्रैवल्स')) ?>"></div>
    <div class="field"><label for="village"><?= t('Your village / town', 'आपका गाँव / कस्बा') ?></label>
      <input type="text" id="village" name="village" value="<?= h(post('village')) ?>" list="vl" required>
      <datalist id="vl"><?php foreach ($villages as $v): ?><option value="<?= h(vname($v)) ?>"><?php endforeach; ?></datalist></div>
    <div class="field"><label for="address"><?= t('Where the vehicle stands', 'गाड़ी कहाँ खड़ी रहती है') ?></label>
      <input type="text" id="address" name="address" value="<?= h(post('address')) ?>"
             placeholder="<?= h(t('near the chauraha, behind the temple…', 'चौराहे के पास, मंदिर के पीछे…')) ?>"></div>

    <hr class="sep">
    <h3 style="margin:0 0 4px;font-size:18px"><?= t('Your papers', 'आपके कागज़') ?></h3>
    <p class="help" style="margin:0 0 12px"><?= t(
      'Carrying passengers for money needs proper papers. Maakit will ask to see them before sending you work.',
      'किराए पर सवारी ले जाने के लिए सही कागज़ ज़रूरी हैं। काम भेजने से पहले Maakit इन्हें देखेगा।') ?></p>
    <div class="pickers" style="margin-bottom:12px">
      <label class="pick"><input type="checkbox" name="dl_ok" value="1"><span><?= t('Valid driving licence', 'ड्राइविंग लाइसेंस है') ?></span></label>
      <label class="pick"><input type="checkbox" name="rc_ok" value="1"><span><?= t('RC in my name', 'RC मेरे नाम है') ?></span></label>
      <label class="pick"><input type="checkbox" name="ins_ok" value="1"><span><?= t('Insurance valid', 'बीमा चालू है') ?></span></label>
      <label class="pick"><input type="checkbox" name="permit_ok" value="1"><span><?= t('Commercial permit', 'कमर्शियल परमिट है') ?></span></label>
    </div>
    <div class="grid g2">
      <div class="field"><label><?= t('Insurance valid till', 'बीमा कब तक') ?></label><input type="date" name="ins_exp" value="<?= h(post('ins_exp')) ?>"></div>
      <div class="field"><label><?= t('Fitness valid till', 'फिटनेस कब तक') ?></label><input type="date" name="fit_exp" value="<?= h(post('fit_exp')) ?>"></div>
    </div>

    <label class="dcl">
      <input type="checkbox" name="declare" value="1" required>
      <span><?= t(
        'Everything I have written is true. My papers are valid and I will show them to Maakit. I am responsible for the safety of my passengers and their goods.',
        'मैंने जो लिखा है वह सही है। मेरे कागज़ सही हैं और मैं उन्हें Maakit को दिखाऊँगा। सवारी और सामान की सुरक्षा की ज़िम्मेदारी मेरी है।') ?></span>
    </label>

    <button type="submit" class="btn btn-brand" style="width:100%;font-size:17px"><?= t('Register my vehicle', 'मेरी गाड़ी जोड़िए') ?></button>
    <p class="help" style="text-align:center;margin:10px 0 0"><?= t('We call you within a day.', 'एक दिन के अंदर हम आपको कॉल करेंगे।') ?></p>
  </form>

  <!-- pehle se jude hain to login -->
  <h3 class="ghead"><?= t('Already registered?', 'पहले से जुड़े हैं?') ?></h3>
  <form method="post" class="box" style="margin-bottom:30px">
    <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="login">
    <div class="grid g2">
      <div class="field"><label for="lm"><?= t('Mobile number', 'मोबाइल नंबर') ?></label>
        <input type="tel" id="lm" name="mobile" maxlength="10" inputmode="numeric" required></div>
      <div class="field"><label for="lc"><?= t('Your code', 'आपका कोड') ?></label>
        <input type="text" id="lc" name="code" placeholder="ABC-1234" autocapitalize="characters" required></div>
    </div>
    <button class="btn btn-brand" style="width:100%"><?= t('Sign in', 'लॉगिन कीजिए') ?></button>
    <p class="help" style="text-align:center;margin-top:10px"><?= t('Lost your code?', 'कोड खो गया?') ?>
      <a href="tel:<?= MAAKIT_PHONE ?>"><?= t('Call us', 'हमें कॉल कीजिए') ?></a></p>
  </form>
<?php endif; ?>

</div>
<script>
(function(){
  var vt = document.getElementById('vtype'), se = document.getElementById('seats'), cp = document.getElementById('capacity');
  function flip(){
    if (!vt) return;
    var o = vt.options[vt.selectedIndex];
    if (!o || !o.value) return;
    var g = o.getAttribute('data-g') === '1';
    document.querySelectorAll('.only-p').forEach(function(e){ e.style.display = g ? 'none' : ''; });
    document.querySelectorAll('.only-g').forEach(function(e){ e.style.display = g ? '' : 'none'; });
    var sd = o.getAttribute('data-seats'), cd = o.getAttribute('data-cap');
    if (!g && se && !se.value && sd) se.value = sd;
    if (g && cp && !cp.value && cd) cp.value = cd;
  }
  if (vt) { vt.addEventListener('change', flip); flip(); }
  ['mobile','lm'].forEach(function(id){
    var e = document.getElementById(id);
    if (e) e.addEventListener('input', function(){ this.value = this.value.replace(/\D/g,'').slice(0,10); });
  });
})();
</script>
<?php include __DIR__ . '/inc/foot.php'; ?>
