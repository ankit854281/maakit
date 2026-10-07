<?php
require_once __DIR__.'/../inc/fn.php';
need_role('admin');
$page_title=t('Launch readiness — Maakit','Launch की तैयारी — Maakit');
$counts=$pdo->query("SELECT COUNT(*) shops, COALESCE(SUM(items_on=1),0) catalogues FROM businesses WHERE status='approved'")->fetch();
$goods=$pdo->query("SELECT COUNT(*) total,COALESCE(SUM(i.price>0),0) priced,COALESCE(SUM(i.photo IS NOT NULL AND i.photo<>''),0) pictured,COALESCE(SUM(i.price>0 AND i.stock='hai' AND b.items_on=1),0) orderable FROM shop_items i JOIN businesses b ON b.id=i.business_id WHERE b.status='approved' AND i.active=1")->fetch();
$drivers=(int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='delivery' AND active=1")->fetchColumn();
$requests=(int)$pdo->query("SELECT COUNT(*) FROM support_tickets WHERE status IN ('new','reviewing')")->fetchColumn();
$areas=coverage_areas($pdo,false);
$mapped=$pdo->query("SELECT s.village_id,COUNT(*) shops FROM service_area_shops s JOIN businesses b ON b.id=s.business_id WHERE b.status='approved' GROUP BY s.village_id")->fetchAll(PDO::FETCH_KEY_PAIR);
$cards=[
 [t('Approved shops','स्वीकृत दुकानें'),(int)$counts['shops'],'/admin/businesses.php'],
 [t('Enabled shop catalogues','चालू दुकान catalogue'),(int)$counts['catalogues'],'/admin/businesses.php'],
 [t('Active shop items','चालू दुकान सामान'),(int)$goods['total'],'/admin/businesses.php'],
 [t('Items missing prices','बिना दाम का सामान'),(int)$goods['total']-(int)$goods['priced'],'/admin/businesses.php'],
 [t('Items missing photos','बिना फ़ोटो का सामान'),(int)$goods['total']-(int)$goods['pictured'],'/admin/businesses.php'],
 [t('Currently orderable items','अभी मँगाने योग्य सामान'),(int)$goods['orderable'],'/bazaar.php'],
 [t('Active delivery staff accounts','चालू delivery staff खाते'),$drivers,'/admin/users.php'],
 [t('Support requests awaiting action','सहायता के लंबित अनुरोध'),$requests,'/admin/support.php'],
];
include __DIR__.'/../inc/panel.php';
?>
<section><div class="wrap">
<h1><?=t('Launch readiness','Launch की तैयारी')?></h1>
<p class="lead"><?=t('Live database counts help you decide the next task. Account counts do not prove staff availability, photos are not independently verified, and active areas do not prove nationwide delivery.','Database की गिनती से अगला काम तय करें। खाता होने से staff की उपलब्धता साबित नहीं होती, फ़ोटो की स्वतंत्र जाँच नहीं हुई है और चालू इलाके का मतलब पूरे भारत में डिलीवरी नहीं है।')?></p>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px">
<?php foreach($cards as $card):?><a class="box" href="<?=h($card[2])?>"><b style="font-size:28px"><?=h($card[1])?></b><div><?=h($card[0])?></div></a><?php endforeach;?>
</div>
<h2><?=t('Area-by-area checks','हर इलाके की जाँच')?></h2>
<?php foreach($areas as $area):
 $issues=[];
 if(!(int)$area['live'])$issues[]=t('Paused','बंद');
 if(empty($area['city'])||empty($area['state'])||empty($area['pincode']))$issues[]=t('City/state/PIN incomplete','शहर/राज्य/PIN अधूरा');
 if($area['base_fee']===null)$issues[]=t('Uses legacy rates: review delivery charge','पुराने रेट लागू: delivery charge जाँचें');
 if(coverage_enabled($area)&&empty($mapped[$area['id']]))$issues[]=t('No approved serving shop linked','सेवा देने वाली स्वीकृत दुकान नहीं जुड़ी');
 if(coverage_enabled($area)&&!$drivers)$issues[]=t('No active delivery staff account','चालू delivery staff खाता नहीं है');
?>
<article class="box"><b><?=h(coverage_label($area))?></b><p><?=t('Delivery','डिलीवरी')?>: <?=h(coverage_enabled($area)?t('Enabled','चालू'):t('Disabled','बंद'))?> · <?=t('Bookings','बुकिंग')?>: <?=h(coverage_enabled($area,'booking')?t('Enabled','चालू'):t('Disabled','बंद'))?> · <?=t('Linked approved shops','जुड़ी स्वीकृत दुकानें')?>: <?=(int)($mapped[$area['id']]??0)?></p>
<p><?=h($issues?implode(' · ',$issues):t('Listed setup fields are complete. Verify staff, shop opening hours and a real delivery before launch.','सूची के setup fields पूरे हैं। Launch से पहले staff, दुकान का समय और असली delivery जाँचें।'))?></p><a class="btn btn-line" href="/admin/coverage.php?id=<?=(int)$area['id']?>"><?=t('Review area','इलाका जाँचिए')?></a></article>
<?php endforeach;?>
<?php if(!$areas):?><div class="box"><?=t('Add your first verified service area.','पहला सत्यापित सेवा क्षेत्र जोड़िए।')?></div><?php endif;?>
<h2><?=t('External setup and real-world checks','बाहरी setup और असली जाँच')?></h2>
<div class="box"><ol>
<li><?=t('Run the private updater, then complete one real order and booking on a phone. This page cannot verify deployment or a real delivery.','Private updater चलाएँ, फिर फोन पर एक असली order और booking करें। यह पेज deployment या असली delivery सत्यापित नहीं करता।')?></li>
<li><?=t('Verify each shop’s photos, prices, opening hours and available goods with its owner.','हर दुकान के मालिक से फ़ोटो, दाम, खुलने का समय और सामान की उपलब्धता जाँचें।')?></li>
<li><?=t('Confirm delivery partners and service providers for each active area. Staff counts here are global, not area assignments.','हर चालू इलाके के delivery partners और service providers पक्के करें। यहाँ staff की गिनती कुल है, इलाके के अनुसार assignment नहीं।')?></li>
<li><?=t('WhatsApp OTP, courier setup, refund procedures, load tests and backup restore tests still need separate verification. Their readiness is not scored here.','WhatsApp OTP, courier setup, refund प्रक्रिया, load test और backup restore test अलग सत्यापित करने हैं। उनकी तैयारी का score यहाँ नहीं दिया गया है।')?></li>
</ol></div>
</div></section>
<?php include __DIR__.'/../inc/foot.php'; ?>
