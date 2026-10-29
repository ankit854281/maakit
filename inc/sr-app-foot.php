<?php // Smart Sarathi app mode — website footer/tabbar ki jagah app ka neeche wala menu ?>
<nav class="sra-nav" aria-label="<?= h(t('Main', 'मुख्य')) ?>"><div>
  <a href="/smartsarathi/ghar.php"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linejoin="round" aria-hidden="true"><path d="M3 11l9-7 9 7v9H3z"/></svg><?= h(t('Home', 'होम')) ?></a>
  <a href="/ride.php#mine"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="4" y="4" width="16" height="16" rx="3"/><path d="M8 9h8M8 13h8M8 17h5"/></svg><?= h(t('My bookings', 'मेरी बुकिंग')) ?></a>
  <a href="/smartsarathi/account.php"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.4 3.6-8 8-8s8 3.6 8 8"/></svg><?= h(t('Account', 'खाता')) ?></a>
</div></nav>
</body>
</html>
