<?php
require_once __DIR__.'/../inc/seo.php';
function seo_check($ok) { if (!$ok) throw new RuntimeException('SEO check failed'); }
seo_check(seo_page('/index.php',['lang'=>'hi'])['canonical']==='https://maakit.in/');
seo_check(seo_page('/bazaar.php',['type'=>'Grocery / Kirana Store','lang'=>'hi'])['canonical']==='https://maakit.in/bazaar.php?type=Grocery%20%2F%20Kirana%20Store');
foreach (['/track.php','/shop.php','/admin/','/search.php'] as $path) seo_check(!seo_page($path,['code'=>'secret'])['index']);
seo_check(!seo_page('/order.php',[],'POST')['index']);
seo_check(!seo_page('/bazaar.php',['q'=>'atta'])['index']);
seo_check(!seo_page('/business.php',['id'=>['bad']])['index']);
seo_check(!seo_page('/bazaar.php',['type'=>'made up'])['index']);
seo_check(strpos(seo_page('/business.php',['id'=>'12','m'=>'secret'])['canonical'],'secret')===false);
echo "Canonical and private page SEO checks passed\n";
