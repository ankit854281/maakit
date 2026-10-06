<?php
require_once __DIR__ . '/../inc/fn.php';
require_once __DIR__ . '/../inc/salon.php';
need_role('admin');
$page_title = 'दुकान / कारीगर — Maakit';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_ok()) {
    $id = (int)post('id');
    if (post('do') === 'status') {
        $pdo->prepare("UPDATE businesses SET status=? WHERE id=?")->execute([post('status'), $id]);
        // Manzoori ke saath hi code bana dete hain — tabhi dukandar
        // /shop.php par andar aakar apna saaman aur hisab chala sakta hai.
        if (post('status') === 'approved') {
            $st2 = $pdo->prepare("SELECT access_code FROM businesses WHERE id=?"); $st2->execute([$id]);
            if (!$st2->fetch()['access_code']) {
                $pdo->prepare("UPDATE businesses SET access_code=? WHERE id=?")->execute([make_access_code(), $id]);
            }
        }
        flash('बदल दिया गया।');
    }
    elseif (post('do') === 'hissa') {
        $pct = max(0, min(30, (float)post('commission_pct')));
        $pdo->prepare("UPDATE businesses SET commission_pct=? WHERE id=?")->execute([$pct, $id]);
        flash('हिस्सा सेव हो गया। दुकानदार को उसकी बही में अलग लाइन में दिखेगा।');
    }
    elseif (post('do') === 'salon') {
        $on = (int)post('salon_on');
        $pdo->prepare("UPDATE businesses SET salon_on=?, mode=IF(?=1,'auto','off') WHERE id=?")->execute([$on, $on, $id]);
        if ($on) {
            $st2 = $pdo->prepare("SELECT access_code FROM businesses WHERE id=?"); $st2->execute([$id]);
            if (!$st2->fetch()['access_code']) {
                $pdo->prepare("UPDATE businesses SET access_code=? WHERE id=?")->execute([make_access_code(), $id]);
            }
            $cnt = $pdo->prepare("SELECT COUNT(*) c FROM services WHERE business_id=?"); $cnt->execute([$id]);
            if ((int)$cnt->fetch()['c'] === 0) {
                $ins = $pdo->prepare("INSERT INTO services (business_id, name, price, minutes) VALUES (?,?,?,?)");
                foreach ([['बाल कटिंग',50,15],['दाढ़ी',30,8],['बाल + दाढ़ी',70,22],['बच्चों की कटिंग',40,12],['मसाज',80,15]] as $d) {
                    $ins->execute([$id, $d[0], $d[1], $d[2]]);
                }
            }
        }
        flash('बदल दिया गया।');
    }
    elseif (post('do') === 'newcode') { $pdo->prepare("UPDATE businesses SET access_code=? WHERE id=?")->execute([make_access_code(), $id]);
        $pdo->prepare("DELETE FROM shop_tokens WHERE business_id=?")->execute([$id]); flash('नया कोड बन गया, पुराना लॉगिन बंद हो गया।'); }
    elseif (post('do') === 'delete') { $pdo->prepare("DELETE FROM businesses WHERE id=?")->execute([$id]); flash('हटा दिया गया।'); }
    redirect('/admin/businesses.php?f=' . urlencode(get('f', '')));
}
$f = get('f', 'pending');
$sql = "SELECT * FROM businesses"; $args = [];
if (in_array($f, ['pending','approved','hidden'], true)) { $sql .= " WHERE status=?"; $args[] = $f; }
$sql .= " ORDER BY id DESC LIMIT 300";
$st = $pdo->prepare($sql); $st->execute($args); $rows = $st->fetchAll();
include __DIR__ . '/../inc/panel.php';
?>
<section><div class="wrap">
  <h2>दुकान / कारीगर</h2>
  <div class="chips" style="margin-bottom:16px">
    <?php foreach (['pending'=>'जाँच बाकी','approved'=>'दिख रहे','hidden'=>'छुपाए','all'=>'सब'] as $k=>$v): ?>
      <a class="chip <?= $f===$k?'on':'' ?>" href="?f=<?= h($k) ?>"><?= h($v) ?></a>
    <?php endforeach; ?>
  </div>
  <?php if (!$rows): ?><div class="box">यहाँ कुछ नहीं है।</div><?php endif; ?>
  <?php foreach ($rows as $b): $c = cat_by_slug($b['category']); ?>
    <div class="box" style="margin-bottom:12px">
      <div class="biz">
        <span class="ph"><?= $b['photo'] ? '<img src="/uploads/' . h($b['photo']) . '" alt="">' : cat_icon($c['icon'] ?? 'anya', 28) ?></span>
        <div style="flex:1;min-width:0">
          <h3 style="margin:0"><?= h($b['name']) ?> <span class="tag tag-off"><?= h($b['status']) ?></span></h3>
          <div class="meta"><?= h($c['name'] ?? '') ?> · <?= h($b['village']) ?> · <a href="tel:+91<?= h($b['mobile']) ?>"><?= h($b['mobile']) ?></a></div>
          <?php if ($b['work']): ?><div><?= h($b['work']) ?></div><?php endif; ?>
          <?php if ($b['about']): ?><div class="meta"><?= h(mb_strimwidth($b['about'], 0, 160, '…')) ?></div><?php endif; ?>
        </div>
      </div>
      <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px">
        <?php foreach (['approved'=>'दिखाइए','hidden'=>'छुपाइए','pending'=>'जाँच में रखिए'] as $s=>$lbl): ?>
          <form method="post"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="status"><input type="hidden" name="id" value="<?= (int)$b['id'] ?>"><input type="hidden" name="status" value="<?= h($s) ?>"><button class="btn <?= $s==='approved'?'btn-green':'btn-brand' ?> btn-sm"><?= h($lbl) ?></button></form>
        <?php endforeach; ?>
        <form method="post"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="salon"><input type="hidden" name="id" value="<?= (int)$b['id'] ?>"><input type="hidden" name="salon_on" value="<?= $b['salon_on'] ? 0 : 1 ?>"><button class="btn btn-brand btn-sm">सीट बुकिंग <?= $b['salon_on'] ? 'बंद कीजिए' : 'चालू कीजिए' ?></button></form>
        <?php if ($b['status'] === 'approved'): ?>
        <form method="post" onsubmit="return confirm('नया कोड बनाने पर पुराना लॉगिन बंद हो जाएगा।')"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="newcode"><input type="hidden" name="id" value="<?= (int)$b['id'] ?>"><button class="btn btn-sm" style="background:#EFEAE0">नया कोड</button></form>
        <?php endif; ?>
        <a class="btn btn-brand btn-sm" href="/business.php?id=<?= (int)$b['id'] ?>" target="_blank">देखिए</a>
        <form method="post" onsubmit="return confirm('पक्का हटाना है?')"><input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="delete"><input type="hidden" name="id" value="<?= (int)$b['id'] ?>"><button class="btn btn-sm" style="background:#EFEAE0">हटाइए</button></form>
      </div>
      <?php if ($b['status'] === 'approved' && $b['access_code']):
        // Dukandar ko bhejne wala sandesh. Nai/parlour ko seat booking
        // ki line bhi jaati hai, baaki dukaanon ko nahi.
        $kaam = $b['salon_on']
          ? "• सीट बुकिंग — कौन इंतज़ार में है, किसे बिठाना है\n• अपना सामान और दाम\n• अपना हिसाब — रोज़ कितना बिका"
          : "• अपना सामान और अपना दाम डालिए\n• अपने ऑर्डर देखिए\n• अपना हिसाब — रोज़ कितना बिका, कितना लेना है\n• अपना UPI — पैसा सीधा आपके खाते में";
        $sandesh = "नमस्ते " . $b['name'] . " जी,\nMaakit पर आपकी दुकान का पेज चालू हो गया है।\n\n"
          . "अपना पेज खोलिए: https://maakit.in/login.php\nमोबाइल: " . $b['mobile'] . "\nकोड: " . $b['access_code'] . "\n\n"
          . "वहाँ आप ये कर सकते हैं —\n" . $kaam
          . "\n\nएक बार खोलने के बाद 90 दिन तक इसी फ़ोन पर सीधा खुलेगा। कोड किसी को न बताइए।";
      ?>
        <div class="note" style="margin-top:10px">
          दुकानदार को यह भेजिए — लिंक: <b>maakit.in/login.php</b> · मोबाइल: <b><?= h($b['mobile']) ?></b> · कोड: <b><?= h($b['access_code']) ?></b>
          <div style="margin-top:8px"><a class="btn btn-green btn-sm" target="_blank" rel="noopener"
             href="<?= h(wa_link($b['mobile'], $sandesh)) ?>">कोड WhatsApp पर भेजिए</a></div>
          <form method="post" style="display:flex;gap:8px;align-items:flex-end;margin-top:12px">
            <input type="hidden" name="csrf" value="<?= h(csrf()) ?>"><input type="hidden" name="do" value="hissa">
            <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
            <div style="max-width:120px"><label>Maakit का हिस्सा %</label>
              <input type="number" name="commission_pct" value="<?= h(rtrim(rtrim(number_format((float)$b['commission_pct'], 1), '0'), '.')) ?>" min="0" max="30" step="0.5"></div>
            <button class="btn btn-sm" style="background:#EFEAE0">सेव</button>
            <span class="help" style="flex:1">0 रखिए तो कुछ नहीं कटेगा। दुकानदार को उसकी बही में अलग लाइन दिखती है — छिपाकर कुछ नहीं।</span>
          </form>
        </div>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div></section>
<?php include __DIR__ . '/../inc/foot.php'; ?>
