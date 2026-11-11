<?php
require_once __DIR__ . '/../inc/fn.php';
need_role('admin');
$page_title = 'Finance & Accounts — MAAKIT';
include __DIR__ . '/../inc/panel.php';
?>
<section><div class="wrap">
<h2>Finance & Accounts</h2>
<p>Owner-only accounting workspace. Operational earnings remain in Muneem and daily operating costs remain in the existing Accounts report.</p>
<div class="box">
<p><a href="/admin/finance-vault.php">Expense Register &amp; Vouchers</a></p>
<p><a href="/admin/founder-work.php">Founder Time &amp; Work Ledger</a></p>
<p><a href="/admin/summary.php">Existing Operating Accounts</a></p>
<p><a href="/muneem.php">Existing Muneem Dashboard</a></p>
</div>
<p>Do not double-count daily operating expenses in the finance register. No bank verification or tax treatment is implied by an unverified receipt.</p>
</div></section>
