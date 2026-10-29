<?php
// स्मार्ट सारथी — पहली बार लॉगिन के बाद: आप कौन हैं? (ग्राहक / पार्टनर)
require_once __DIR__ . '/inc/public.php';
require_once __DIR__ . '/inc/identity.php';
header('Cache-Control: private, no-store');
$a = sr_account_require($pdo);
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_ok() || !in_array(post('role'), ['customer', 'partner'], true)) $err = t('Page expired. Try again.', 'पेज पुराना हो गया। दोबारा कोशिश कीजिए।');
    else { sr_set_role($pdo, $a, (string)post('role')); redirect(sr_home_path($pdo)); }
}
sr_customer_head(t('Welcome to Smart Sarathi', 'स्मार्ट सारथी में स्वागत है'), t('How will you use the app? You can switch later from Account.', 'आप ऐप कैसे इस्तेमाल करेंगे? बाद में खाते से बदल सकते हैं।'));
?>
<style>
.rl{display:grid;gap:12px}
.rl button{width:100%;display:flex;align-items:center;gap:14px;text-align:left;background:#fff;border:2px solid #E3E8EF;border-radius:20px;padding:18px 16px;min-height:104px;cursor:pointer;font:inherit;color:#0B1F3A}
.rl button:hover,.rl button:focus-visible{border-color:#0B1F3A}
.rl .ic{width:58px;height:58px;border-radius:16px;display:grid;place-items:center;flex:none}
.rl b{display:block;font:800 19px 'Noto Sans Devanagari',sans-serif}
.rl small{display:block;font:500 14px/1.4 'Noto Sans Devanagari',sans-serif;color:#4B5A6B;margin-top:2px}
</style>
<?php if ($err): ?><p role="alert"><?= h($err) ?></p><?php endif; ?>
<form method="post" class="rl">
  <input type="hidden" name="csrf" value="<?= h(csrf()) ?>">
  <button name="role" value="customer" type="submit">
    <span class="ic" style="background:#E6FAF3"><svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#0B1F3A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/></svg></span>
    <span><b><?= h(t('Customer', 'ग्राहक')) ?></b><small><?= h(t('I need a ride or a service.', 'मुझे सवारी या कोई सेवा चाहिए।')) ?></small></span>
  </button>
  <button name="role" value="partner" type="submit">
    <span class="ic" style="background:#FDF1E3"><svg width="30" height="30" viewBox="0 0 48 48" fill="none" stroke="#0B1F3A" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="32" r="7"/><circle cx="37" cy="32" r="7"/><path d="M11 32l9-12h9l8 12M24 20l-3-6h-5M29 20h6"/></svg></span>
    <span><b><?= h(t('Partner / Driver', 'पार्टनर / ड्राइवर')) ?></b><small><?= h(t('I want to earn by driving or delivering.', 'मैं गाड़ी चलाकर या डिलीवरी करके कमाना चाहता हूँ।')) ?></small></span>
  </button>
</form>
<?php sr_customer_foot(); ?>
