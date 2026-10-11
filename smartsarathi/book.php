<?php
// ============================================================
//  सारथी — पार्सल भेजिए (grahak ke liye pick & drop)
//
//  Grahak seedha yahan se kaam bookh karta hai. Ye wahi "jobs"
//  table me jaata hai jahan Maakit (API) ke kaam jaate hain —
//  client = "सीधे ग्राहक (वेबसाइट)" (apne aap banta hai). Fee wahi
//  jo admin me us client ki rakhi hai; 0 ho to "फ़ोन पर बताएँगे".
//
//  Do code milte hain:
//    pick_otp — uthate waqt rider ko bataiye (bhejne wala)
//    drop_code — paane wala rider ko batata hai, tabhi "pahuncha"
// ============================================================
require_once __DIR__ . '/inc/public.php';
require_once __DIR__ . '/inc/client.php';

$err = ''; $job = null;
$client = sr_direct_client($pdo);
$fee = (int)$client['fee'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok()) {
        $err = t('Page expired — please submit again.', 'पेज पुराना हो गया — दोबारा भेजिए।');
    } elseif (post('website') !== '') {
        $err = t('Please try again.', 'दोबारा कोशिश कीजिए।');
    } else {
        $pm = preg_replace('/\D/', '', (string)post('pick_mobile'));
        $same = post('same') === '1';
        if (!preg_match('/^[6-9]\d{9}$/D', $pm)) $err = t('Your mobile number must be 10 digits.', 'अपना मोबाइल नंबर 10 अंकों का लिखिए।');
        elseif (mb_strlen(trim((string)post('pick_address'))) < 3) $err = t('Write the pickup address.', 'सामान कहाँ से उठाना है, लिखिए।');
        elseif (mb_strlen(trim((string)post('items'))) < 2) $err = t('Write what is being sent.', 'क्या भेजना है, लिखिए।');
        elseif (!sr_public_limit('book', 8)) $err = t('Too many bookings from this phone. Please call us.', 'इस फ़ोन से बहुत बुकिंग हुई। हमें फ़ोन कीजिए।');
        else {
            // ek hi form do baar dabane par ek hi kaam (ref = form ka token)
            $ref = 'W-' . substr(preg_replace('/[^A-Za-z0-9]/', '', (string)post('form_id')), 0, 20);
            if (strlen($ref) < 10) $ref = 'W-' . bin2hex(random_bytes(8));
            [$ok, $out] = job_create($pdo, $client, [
                'ref' => $ref,
                'pick_name' => trim((string)post('pick_name')), 'pick_mobile' => $pm, 'pick_address' => trim((string)post('pick_address')),
                'drop_name' => $same ? trim((string)post('pick_name')) : trim((string)post('drop_name')),
                'drop_mobile' => $same ? $pm : (string)post('drop_mobile'),
                'drop_address' => trim((string)post('drop_address')), 'drop_village' => trim((string)post('drop_village')),
                'items' => trim((string)post('items')), 'note' => trim((string)post('note')),
            ]);
            if ($ok) {
                $st = $pdo->prepare("SELECT * FROM jobs WHERE client_id=? AND client_ref=?"); $st->execute([(int)$client['id'], $ref]);
                $job = $st->fetch();
                $_SESSION['sr_track'] = ['no' => $job['job_no'], 'm' => $pm];
            } else {
                $map = ['drop_name' => t('Write the receiver’s name.', 'पाने वाले का नाम लिखिए।'), 'drop_mobile' => t('Receiver’s mobile must be 10 digits.', 'पाने वाले का मोबाइल 10 अंकों का लिखिए।'), 'drop_address' => t('Write the delivery address.', 'सामान कहाँ पहुँचाना है, लिखिए।')];
                $err = t('Please check the form.', 'फ़ॉर्म जाँचिए।');
                foreach ($map as $k => $m) if (strpos((string)$out, $k) === 0) { $err = $m; break; }
            }
        }
    }
}

sr_public_head('book', t('Send a parcel', 'पार्सल भेजिए'), t('PICK UP · DROP · SAME DAY', 'उठाना · पहुँचाना · उसी दिन'),
    t('From your door,', 'आपके दरवाज़े से,'), t('to theirs.', 'उनके दरवाज़े तक।'),
    t('Tiffin, documents, medicine, shop goods — a Sarathi rider picks up and delivers. Codes at pickup and delivery keep it safe.', 'टिफ़िन, काग़ज़, दवा, दुकान का सामान — सारथी राइडर उठाकर पहुँचाता है। उठाते और पहुँचाते समय कोड से पक्की सुरक्षा।'));
?>
  <?php if ($job): ?>
    <div class="sr-card sp-done">
      <span class="sp-tick" aria-hidden="true">✓</span>
      <h2><?= h(t('Booked!', 'बुक हो गया!')) ?> <span class="sp-no"><?= h($job['job_no']) ?></span></h2>
      <p><?= $job['rider_id'] ? h(t('A rider has been assigned and will call you.', 'राइडर मिल गया है, वो आपको फ़ोन करेगा।')) : h(t('We are finding a rider now. You will get a call.', 'राइडर ढूँढ रहे हैं। आपको फ़ोन आएगा।')) ?></p>
      <div class="sp-codes">
        <div><small><?= h(t('Tell the rider at PICKUP', 'उठाते समय राइडर को बताइए')) ?></small><b><?= h($job['pick_otp']) ?></b></div>
        <div><small><?= h(t('Receiver tells at DELIVERY', 'पाने वाला पहुँचने पर बताए')) ?></small><b><?= h($job['drop_code']) ?></b></div>
      </div>
      <p class="sp-small"><?= h(t('Send the delivery code to the receiver. Do not share codes with anyone else.', 'पहुँचाने वाला कोड पाने वाले को भेज दीजिए। किसी और को कोड मत बताइए।')) ?>
        <?= $fee > 0 ? ' ' . h(t('Delivery charge', 'डिलीवरी चार्ज')) . ': ₹' . $fee : ' ' . h(t('The delivery charge will be told on the call.', 'डिलीवरी चार्ज फ़ोन पर बताया जाएगा।')) ?></p>
      <a class="sr-btn sr-btn-go" href="<?= h(u('/track.php?no=' . urlencode($job['job_no']) . '&m=' . urlencode($job['pick_mobile']))) ?>"><?= h(t('Track this parcel', 'पार्सल देखिए')) ?></a>
      <a class="sp-link" href="<?= h(u('/book.php')) ?>"><?= h(t('Book another', 'एक और बुक कीजिए')) ?> →</a>
    </div>
  <?php else: ?>
    <div class="sr-brand"><div class="sr-logo"><?= h(t('Send a parcel', 'पार्सल भेजिए')) ?></div>
      <p><?= $fee > 0 ? h(t('Delivery from', 'डिलीवरी')) . ' ₹' . $fee : h(t('Charge confirmed on call before pickup.', 'उठाने से पहले फ़ोन पर चार्ज पक्का होगा।')) ?></p></div>
    <?php if ($err): ?><div class="sr-err"><?= h($err) ?></div><?php endif; ?>
    <form method="post" class="sr-card sp-book">
      <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
      <input type="hidden" name="form_id" value="<?= h(post('form_id') ?: bin2hex(random_bytes(8))) ?>">
      <input type="text" name="website" value="" tabindex="-1" autocomplete="off" class="sp-hp" aria-hidden="true">

      <div class="sp-leg"><span class="sp-pin a">A</span><b><?= h(t('Pick up from', 'कहाँ से उठाना है')) ?></b></div>
      <div class="sp-two">
        <div><label class="sr-lab" for="pn"><?= h(t('Your name', 'आपका नाम')) ?></label>
          <input class="sr-in" id="pn" name="pick_name" value="<?= h(post('pick_name')) ?>" autocomplete="name" required></div>
        <div><label class="sr-lab" for="pm"><?= h(t('Your mobile', 'आपका मोबाइल')) ?></label>
          <input class="sr-in" id="pm" name="pick_mobile" type="tel" inputmode="numeric" value="<?= h(post('pick_mobile')) ?>" autocomplete="tel-national" required></div>
      </div>
      <label class="sr-lab" for="pa"><?= h(t('Pickup address', 'उठाने का पता')) ?></label>
      <input class="sr-in" id="pa" name="pick_address" value="<?= h(post('pick_address')) ?>" placeholder="<?= h(t('House, mohalla, village, landmark', 'घर, मोहल्ला, गाँव, पहचान')) ?>" required>

      <div class="sp-leg"><span class="sp-pin b">B</span><b><?= h(t('Deliver to', 'कहाँ पहुँचाना है')) ?></b></div>
      <label class="sp-agree"><input type="checkbox" name="same" value="1" data-same <?= post('same') === '1' ? 'checked' : '' ?>> <span><?= h(t('I will receive it myself', 'मैं ख़ुद लूँगा / लूँगी')) ?></span></label>
      <div class="sp-two" data-recv>
        <div><label class="sr-lab" for="dn"><?= h(t('Receiver’s name', 'पाने वाले का नाम')) ?></label>
          <input class="sr-in" id="dn" name="drop_name" value="<?= h(post('drop_name')) ?>"></div>
        <div><label class="sr-lab" for="dm"><?= h(t('Receiver’s mobile', 'पाने वाले का मोबाइल')) ?></label>
          <input class="sr-in" id="dm" name="drop_mobile" type="tel" inputmode="numeric" value="<?= h(post('drop_mobile')) ?>"></div>
      </div>
      <label class="sr-lab" for="da"><?= h(t('Delivery address', 'पहुँचाने का पता')) ?></label>
      <input class="sr-in" id="da" name="drop_address" value="<?= h(post('drop_address')) ?>" placeholder="<?= h(t('House, mohalla, landmark', 'घर, मोहल्ला, पहचान')) ?>" required>
      <label class="sr-lab" for="dv"><?= h(t('Village / town', 'गाँव / कस्बा')) ?></label>
      <input class="sr-in" id="dv" name="drop_village" value="<?= h(post('drop_village')) ?>">

      <div class="sp-leg"><span class="sp-pin c">📦</span><b><?= h(t('What are you sending?', 'क्या भेजना है?')) ?></b></div>
      <div class="sp-chips" data-quick>
        <?php foreach ([t('Tiffin', 'टिफ़िन'), t('Documents', 'काग़ज़ात'), t('Medicine', 'दवा'), t('Shop goods', 'दुकान का सामान'), t('Clothes', 'कपड़े'), t('Keys', 'चाबी')] as $q): ?>
          <button type="button" data-q="<?= h($q) ?>"><?= h($q) ?></button>
        <?php endforeach; ?>
      </div>
      <input class="sr-in" id="it" name="items" value="<?= h(post('items')) ?>" placeholder="<?= h(t('e.g. 1 tiffin box', 'जैसे 1 टिफ़िन')) ?>" required>
      <label class="sr-lab" for="nt"><?= h(t('Note for the rider (optional)', 'राइडर के लिए बात (मर्ज़ी से)')) ?></label>
      <input class="sr-in" id="nt" name="note" value="<?= h(post('note')) ?>" maxlength="300" placeholder="<?= h(t('e.g. fragile, call before coming', 'जैसे टूटने वाला है, आने से पहले फ़ोन करें')) ?>">
      <button class="sr-btn sr-btn-go" type="submit"><?= h(t('Book pickup', 'उठवाइए')) ?></button>
      <p class="sp-small"><?= h(t('No alcohol, cash, gold or illegal items.', 'शराब, नगद, सोना या ग़ैरक़ानूनी सामान नहीं भेजा जाता।')) ?></p>
    </form>
    <script>
    (function () {
      var s = document.querySelector('[data-same]'), r = document.querySelector('[data-recv]');
      function sync() { r.hidden = s.checked; r.querySelectorAll('input').forEach(function (i) { i.required = !s.checked; }); }
      s.addEventListener('change', sync); sync();
      var it = document.getElementById('it');
      document.querySelectorAll('[data-q]').forEach(function (b) { b.addEventListener('click', function () { it.value = it.value ? it.value + ', ' + b.dataset.q : b.dataset.q; it.focus(); }); });
    })();
    </script>
  <?php endif; ?>
<?php sr_public_foot();
