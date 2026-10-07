<?php
require_once __DIR__.'/inc/fn.php';
$tab='ghar';$page_title=t('Choose your location — Maakit','अपना इलाका चुनिए — Maakit');$err='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!csrf_ok())$err=t('Reload and try again.','पेज दोबारा खोलिए।');
 elseif(post('do')==='clear'){unset($_SESSION['service_area']);redirect('/location.php');}
 elseif($area=coverage_area($pdo,post('area'))){$_SESSION['service_area']=$area['name'];redirect('/');}
 else $err=t('This area is not active. Request coverage below.','यह इलाका अभी चालू नहीं है। नीचे सेवा के लिए अनुरोध करें।');
}
$q=isset($_GET['q'])&&is_string($_GET['q'])?mb_substr(trim($_GET['q']),0,80):'';
$all=coverage_areas($pdo);$areas=array_filter($all,fn($a)=>$q===''||stripos(coverage_label($a).' '.$a['state'],$q)!==false);
$selected=coverage_selected($pdo);
include __DIR__.'/inc/head.php';
?>
<section><div class="wrap" style="max-width:760px">
<h1><?=t('Choose your location','अपना इलाका चुनिए')?></h1><p class="lead"><?=t('Browse Maakit across India. Orders and bookings are accepted only in active service areas.','भारत में Maakit देखें। ऑर्डर और बुकिंग केवल चालू सेवा क्षेत्रों में लिए जाते हैं।')?></p>
<?php if($err):?><div class="err"><?=h($err)?></div><?php endif;?>
<form method="get" class="searchbox" style="margin-top:0"><input name="q" value="<?=h($q)?>" placeholder="<?=h(t('City, locality or PIN code','शहर, इलाका या PIN code'))?>" aria-label="<?=h(t('Search areas','इलाका खोजें'))?>"><button class="btn btn-brand btn-sm"><?=t('Search','खोजिए')?></button></form>
<?php if($selected):?><div class="box"><?=t('Selected: ','चुना हुआ: ')?><?=h(coverage_label($selected))?><form method="post"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><button class="btn btn-sm" name="do" value="clear"><?=t('Browse all locations','सभी इलाके देखें')?></button></form></div><?php endif;?>
<?php foreach($areas as $a):?><form method="post" class="box"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="area" value="<?=h($a['name'])?>"><h2><?=h(coverage_label($a))?></h2><p><?=h($a['state']??'')?></p><p class="meta"><?=coverage_enabled($a)?t('Delivery requests available','डिलीवरी अनुरोध उपलब्ध'):t('Delivery paused','डिलीवरी बंद')?> · <?=coverage_enabled($a,'booking')?t('Booking requests available','बुकिंग अनुरोध उपलब्ध'):t('Bookings paused','बुकिंग बंद')?></p><button class="btn btn-brand btn-sm"><?=t('Select this area','यह इलाका चुनें')?></button></form><?php endforeach;?>
<?php if(!$areas):?><div class="box"><?=t('No active area matched. Register your interest; this is not an order.','कोई चालू इलाका नहीं मिला। सेवा के लिए अनुरोध भेजें; यह ऑर्डर नहीं है।')?></div><?php endif;?>
<a class="btn btn-green" href="/area.php"><?=t('Request service in my area','मेरे इलाके में सेवा का अनुरोध')?></a>
</div></section><?php include __DIR__.'/inc/foot.php';?>
