<?php
function t($en,$hi){return $en;} function vname($a){return $a['name'];}
require_once __DIR__.'/../inc/coverage.php';
function coverage_check($ok,$msg){if(!$ok)throw new RuntimeException($msg);}
$p=new PDO('sqlite::memory:');$p->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$p->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
$p->exec('CREATE TABLE villages(id INTEGER,name TEXT,live INTEGER); CREATE TABLE service_area_meta(village_id INTEGER,city TEXT,state TEXT,pincode TEXT,delivery_on INTEGER,booking_on INTEGER,base_fee INTEGER,first_free INTEGER); CREATE TABLE service_area_shops(village_id INTEGER,business_id INTEGER)');
$p->exec("INSERT INTO villages VALUES(1,'Area A',1),(2,'Area B',1),(3,'Paused',0); INSERT INTO service_area_meta VALUES(1,'City','State','221403',1,0,50,0),(2,'Other city','State','221404',0,1,70,0); INSERT INTO service_area_shops VALUES(1,7),(2,8)");
$a=coverage_area($p,'Area A');coverage_check(coverage_enabled($a),'Active delivery');coverage_check(!coverage_enabled($a,'booking'),'Booking disabled independently');coverage_check(!coverage_area($p,'Paused'),'Paused excluded');coverage_check(!coverage_area($p,'Made up'),'Unknown excluded');coverage_check(!coverage_area($p,['bad']),'Array input rejected');coverage_check(count(coverage_areas($p))===2,'Active areas');coverage_check(count(coverage_areas($p,false))===3,'Admin includes paused');coverage_check(coverage_shop_allowed($p,7,$a),'Mapped shop allowed');coverage_check(!coverage_shop_allowed($p,8,$a),'Cross-area shop blocked');coverage_check(!coverage_shop_allowed($p,8,coverage_area($p,'Area B')),'Delivery disabled blocks shop');coverage_check(strpos(coverage_label($a),'221403')!==false,'PIN displayed');
$_SESSION['service_area']='Paused';coverage_check(!coverage_selected($p),'Stale paused selection excluded');
echo "Coverage and shop boundary checks passed\n";
