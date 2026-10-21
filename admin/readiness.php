<?php
require_once __DIR__.'/../inc/fn.php';
need_role('admin');
require_once __DIR__.'/../inc/email-otp.php';
require_once __DIR__.'/../inc/sarathi-bridge.php';
$setupError='';
if($_SERVER['REQUEST_METHOD']==='POST' && csrf_ok()) {
    $name=post('grievance_name');$email=post('grievance_email');$hours=post('grievance_hours');
    if(mb_strlen($name)>100 || ($email!=='' && !filter_var($email,FILTER_VALIDATE_EMAIL)) || ($hours!=='' && (!ctype_digit($hours)||(int)$hours<1||(int)$hours>720))) {
        $setupError=t('Check the contact name, email and response hours.','संपर्क नाम, email और जवाब के घंटे जाँचिए।');
    } else {
        $set=$pdo->prepare('INSERT INTO settings(k,v) VALUES (?,?) ON DUPLICATE KEY UPDATE v=VALUES(v)');
        foreach(['grievance_name'=>$name,'grievance_email'=>$email,'grievance_hours'=>$hours] as $key=>$value)$set->execute([$key,$value]);
        redirect('/admin/readiness.php');
    }
}
$contactName=(string)setg($pdo,'grievance_name','');$contactEmail=(string)setg($pdo,'grievance_email','');$contactHours=(string)setg($pdo,'grievance_hours','');
$defaultAdmin=false;
foreach($pdo->query("SELECT password FROM users WHERE role='admin' AND active=1") as $account) if(password_verify('maakit123',$account['password'])) $defaultAdmin=true;
$page_title=t('Launch readiness — Maakit','Launch की तैयारी — Maakit');
$counts=$pdo->query("SELECT COUNT(*) shops, COALESCE(SUM(items_on=1),0) catalogues FROM businesses WHERE status='approved'")->fetch();
$goods=$pdo->query("SELECT COUNT(*) total,COALESCE(SUM(i.price>0),0) priced,COALESCE(SUM(i.photo IS NOT NULL AND i.photo<>''),0) pictured,COALESCE(SUM(i.price>0 AND i.stock='hai' AND b.items_on=1),0) orderable FROM shop_items i JOIN businesses b ON b.id=i.business_id WHERE b.status='approved' AND i.active=1")->fetch();
$drivers=(int)$pdo->query("SELECT COUNT(*) FROM users WHERE role IN ('delivery','rider') AND active=1")->fetchColumn();
$requests=(int)$pdo->query("SELECT COUNT(*) FROM support_tickets WHERE status IN ('new','reviewing')")->fetchColumn();
$areas=coverage_areas($pdo,false);
$mapped=$pdo->query("SELECT s.village_id,COUNT(*) shops FROM service_area_shops s JOIN businesses b ON b.id=s.business_id WHERE b.status='approved' GROUP BY s.village_id")->fetchAll(PDO::FETCH_KEY_PAIR);
$areaDrivers=$pdo->query("SELECT d.village_id,COUNT(*) drivers FROM service_area_drivers d JOIN users u ON u.id=d.user_id WHERE u.role IN ('delivery','rider') AND u.active=1 GROUP BY d.village_id")->fetchAll(PDO::FETCH_KEY_PAIR);
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
<div class="box">
<h2><?=t('Login, dispatch and contact setup','लॉगिन, डिलीवरी और संपर्क setup')?></h2>
<p><?=t('Email OTP','Email OTP')?>: <?=mk_email_enabled()?t('Configured — inbox delivery still needs testing','जुड़ा है — inbox में पहुँचने की जाँच बाकी'):t('Sender setup pending','भेजने वाले mailbox का setup बाकी')?></p>
<p><?=t('SMS / WhatsApp OTP','SMS / WhatsApp OTP')?>: <?=f2s_enabled()?t('Configured — real delivery needs testing','जुड़ा है — असली संदेश की जाँच बाकी'):t('Provider setup pending','Provider setup बाकी')?></p>
<p>Smart Sarathi: <?=sb_ready($pdo)?t('Bridge configured — complete a real delivery','Bridge जुड़ा है — असली delivery जाँचिए'):t('Bridge URL/key missing','Bridge URL/key बाकी')?></p>
<?php if($defaultAdmin):?><p role="alert"><?=t('An active admin still uses the original installation password. Change it before launch.','एक चालू admin पर अब भी installation वाला पासवर्ड है। Launch से पहले बदलें।')?></p><?php endif;?>
<?php if($setupError):?><p role="alert"><?=h($setupError)?></p><?php endif;?>
<form method="post"><input type="hidden" name="csrf" value="<?=h(csrf())?>">
<label><?=t('Public complaints contact name','शिकायत संपर्क का सार्वजनिक नाम')?><input name="grievance_name" maxlength="100" value="<?=h($contactName)?>"></label>
<label><?=t('Public complaints email','शिकायत का सार्वजनिक email')?><input type="email" name="grievance_email" maxlength="254" value="<?=h($contactEmail)?>"></label>
<label><?=t('Acknowledgement target (hours)','शिकायत मिलने की पुष्टि का समय (घंटे)')?><input type="number" name="grievance_hours" min="1" max="720" value="<?=h($contactHours)?>"></label>
<p><?=t('Save only details and response times your team has agreed to publish. Legal review and actual response handling remain necessary.','केवल टीम से पक्के नाम, email और समय प्रकाशित करें। कानूनी समीक्षा और असली शिकायत का जवाब देना फिर भी ज़रूरी है।')?></p>
<button class="btn btn-brand"><?=t('Save public contact','सार्वजनिक संपर्क सेव करें')?></button></form>
</div>
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
 if(coverage_enabled($area)&&empty($areaDrivers[$area['id']]))$issues[]=t('No active delivery staff linked to this area','इस इलाके में चालू delivery staff नहीं जुड़ा');
?>
<article class="box"><b><?=h(coverage_label($area))?></b><p><?=t('Delivery','डिलीवरी')?>: <?=h(coverage_enabled($area)?t('Enabled','चालू'):t('Disabled','बंद'))?> · <?=t('Bookings','बुकिंग')?>: <?=h(coverage_enabled($area,'booking')?t('Enabled','चालू'):t('Disabled','बंद'))?> · <?=t('Linked approved shops','जुड़ी स्वीकृत दुकानें')?>: <?=(int)($mapped[$area['id']]??0)?> · <?=t('Linked active delivery staff','जुड़ा चालू delivery staff')?>: <?=(int)($areaDrivers[$area['id']]??0)?></p>
<p><?=h($issues?implode(' · ',$issues):t('Listed setup fields are complete. Verify staff, shop opening hours and a real delivery before launch.','सूची के setup fields पूरे हैं। Launch से पहले staff, दुकान का समय और असली delivery जाँचें।'))?></p><a class="btn btn-line" href="/admin/coverage.php?id=<?=(int)$area['id']?>"><?=t('Review area','इलाका जाँचिए')?></a></article>
<?php endforeach;?>
<?php if(!$areas):?><div class="box"><?=t('Add your first verified service area.','पहला सत्यापित सेवा क्षेत्र जोड़िए।')?></div><?php endif;?>
<h2><?=t('External setup and real-world checks','बाहरी setup और असली जाँच')?></h2>
<div class="box"><ol>
<li><?=t('Run the private updater, then complete one real order and booking on a phone. This page cannot verify deployment or a real delivery.','Private updater चलाएँ, फिर फोन पर एक असली order और booking करें। यह पेज deployment या असली delivery सत्यापित नहीं करता।')?></li>
<li><?=t('Verify each shop’s photos, prices, opening hours and available goods with its owner.','हर दुकान के मालिक से फ़ोटो, दाम, खुलने का समय और सामान की उपलब्धता जाँचें।')?></li>
<li><?=t('Confirm delivery partners and service providers for each active area. Area rosters show assigned staff, but shifts and availability need confirmation.','हर चालू इलाके के delivery partners और service providers पक्के करें। इलाके की सूची में जुड़े staff दिखते हैं, लेकिन shift और उपलब्धता पक्की करनी होगी।')?></li>
<li><?=t('WhatsApp OTP, courier setup, refund procedures, load tests and backup restore tests still need separate verification. Their readiness is not scored here.','WhatsApp OTP, courier setup, refund प्रक्रिया, load test और backup restore test अलग सत्यापित करने हैं। उनकी तैयारी का score यहाँ नहीं दिया गया है।')?></li>
</ol></div>
</div></section>
<?php include __DIR__.'/../inc/foot.php'; ?>
