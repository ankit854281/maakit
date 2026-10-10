<?php
require_once __DIR__.'/../inc/fn.php';
header('Cache-Control: no-store');header('Referrer-Policy: no-referrer');
$page_title=t('Sarathi parcel tracking','सारथी parcel tracking');include __DIR__.'/../inc/head.php';
?>
<main class="wrap" style="max-width:720px"><h1><?= h($page_title) ?></h1><p><?= t('This private link shows delivery status and the latest shared location during an active delivery.','यह private link delivery की स्थिति और active delivery में साझा की गई नई location दिखाता है।') ?></p><p id="company-track-status" role="status"></p><div id="company-track-map"></div><p><?= t('GPS older than one minute is hidden. The link expires after 48 hours.','एक मिनट से पुराना GPS नहीं दिखेगा। Link 48 घंटे में बंद हो जाएगा।') ?></p></main>
<script src="/assets/company-tracking.js" defer></script><?php include __DIR__.'/../inc/foot.php'; ?>
