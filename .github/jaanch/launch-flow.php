<?php
require_once __DIR__.'/../../config.php';
if(DB_NAME!=='maakit_jaanch')throw new RuntimeException('CI database only');
require_once __DIR__.'/../../inc/fn.php';
require_once __DIR__.'/../../inc/sarathi-bridge.php';
function lc($ok,$why){if(!$ok)throw new RuntimeException('Launch: '.$why);}
$jars=[];$ids=[];$customers=[];$shops=[];$users=[];$files=[];
function lh($path,$jar,$post=null,$expected=200,$cookie=''){
 $ch=curl_init('http://127.0.0.1:8099'.$path);
 curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_COOKIEFILE=>$jar,CURLOPT_COOKIEJAR=>$jar,CURLOPT_TIMEOUT=>20,CURLOPT_PROXY=>'']);
 if($cookie!=='')curl_setopt($ch,CURLOPT_COOKIE,$cookie);
 if($post!==null)curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($post)]);
 $raw=curl_exec($ch);$status=curl_getinfo($ch,CURLINFO_HTTP_CODE);$hs=curl_getinfo($ch,CURLINFO_HEADER_SIZE);curl_close($ch);
 lc($raw!==false && $status===$expected,'HTTP '.$path.' status '.$status);
 return [substr($raw,$hs),substr($raw,0,$hs)];
}
function lt($body){lc(preg_match('/name="csrf" value="([^"]+)"/',$body,$m)===1,'CSRF');return $m[1];}
function lj(){global $jars;$j=tempnam(sys_get_temp_dir(),'mk-launch-');$jars[]=$j;return $j;}
try{
 $tag=bin2hex(random_bytes(4));$pass=bin2hex(random_bytes(12));$hash=password_hash($pass,PASSWORD_DEFAULT);
 $anon=lj();[$body,$hd]=lh('/book.php?s=gaadi',$anon,null,301);lc(strpos($hd,'Location: /sewa.php?s=safar')!==false,'legacy vehicle redirect');
 [$en]=lh('/?lang=en',$anon);lc(strpos($en,'property="og:locale" content="en_IN"')!==false,'English locale');
 [$persisted]=lh('/',$anon);lc(strpos($persisted,'property="og:locale" content="en_IN"')!==false,'cookie-selected locale');
 [$rider]=lh('/public/auth.php?role=rider',$anon);lc(strpos($rider,'data-role-login')===false && strpos($rider,'href="/smartsarathi/"')!==false,'rider uses own login');
 $dir=order_media_directory('orders',true);lc((bool)$dir,'private media directory');
 $png=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=');
 for($i=0;$i<2;$i++){
  $mobile='9'.str_pad((string)random_int(0,999999999),9,'0',STR_PAD_LEFT);
  $pdo->prepare('INSERT INTO customers(name,mobile,password) VALUES (?,?,?)')->execute(['CI launch '.$tag,$mobile,$hash]);$cid=(int)$pdo->lastInsertId();$customers[]=$cid;
  $pdo->prepare("INSERT INTO businesses(name,category,mobile,status) VALUES (?,?,?,'approved')")->execute(['CI launch '.$tag,'kirana',$mobile]);$bid=(int)$pdo->lastInsertId();$shops[]=$bid;
  $name='list-'.date('ymd').'-'.bin2hex(random_bytes(16)).'.png';$files[]=$dir.'/'.$name;file_put_contents($dir.'/'.$name,$png);
  $pdo->prepare('INSERT INTO orders(order_no,code,customer_name,mobile,village,items,customer_id,business_id,photo,status) VALUES (?,?,?,?,?,?,?,?,?,?)')
      ->execute(['LC-'.$tag.'-'.$i,'2345','CI launch',$mobile,'CI area','CI item',$cid,$bid,$name,'Assign']);$oid=(int)$pdo->lastInsertId();$ids[]=$oid;
 }
 $q=$pdo->prepare('SELECT * FROM orders WHERE id=?');$q->execute([$ids[0]]);$o=$q->fetch();$q->execute([$ids[1]]);$other=$q->fetch();
 lh(order_photo_url($o['photo']),$anon,null,404);
 lh('/order-photo.php?file=..%2Fconfig.php',$anon,null,404);
 lh('/track.php?no='.$o['order_no'].'&m='.$other['mobile'],$anon);
 lh(order_photo_url($o['photo']),$anon,null,404);
 lh('/track.php?no='.$o['order_no'].'&m='.$o['mobile'],$anon);
 [$image,$headers]=lh(order_photo_url($o['photo']),$anon);lc($image===$png && stripos($headers,'no-store')!==false,'proven guest private image');
 lh(order_photo_url($other['photo']),$anon,null,404);
 $customer=lj();[$form]=lh('/account.php?lang=en',$customer);
 lh('/account.php',$customer,['csrf'=>lt($form),'do'=>'login','mobile'=>$o['mobile'],'password'=>$pass],302);
 lh(order_photo_url($o['photo']),$customer);lh(order_photo_url($other['photo']),$customer,null,404);
 $pdo->prepare('UPDATE customers SET active=0 WHERE id=?')->execute([$customers[0]]);lh(order_photo_url($o['photo']),$customer,null,404);
 $shop=lj();$token=bin2hex(random_bytes(24));$pdo->prepare('INSERT INTO shop_tokens(business_id,token,expires) VALUES (?,?,DATE_ADD(NOW(),INTERVAL 1 HOUR))')->execute([$shops[0],$token]);
 lh(order_photo_url($o['photo']),$shop,null,200,'mk_shop='.$token);lh(order_photo_url($other['photo']),$shop,null,404,'mk_shop='.$token);
 $pdo->prepare("UPDATE businesses SET status='hidden' WHERE id=?")->execute([$shops[0]]);lh(order_photo_url($o['photo']),$shop,null,404,'mk_shop='.$token);
 $pdo->prepare('INSERT INTO users(name,username,password,role) VALUES (?,?,?,?)')->execute(['CI launch','lc-'.$tag,$hash,'delivery']);$uid=(int)$pdo->lastInsertId();$users[]=$uid;
 $pdo->prepare('UPDATE orders SET delivery_user=? WHERE id=?')->execute([$uid,$ids[0]]);
 $staff=lj();[$form]=lh('/login.php?as=team',$staff);lh('/login.php',$staff,['csrf'=>lt($form),'do'=>'team','username'=>'lc-'.$tag,'password'=>$pass],302);
 lh(order_photo_url($o['photo']),$staff);lh(order_photo_url($other['photo']),$staff,null,404);
 $pdo->prepare('UPDATE users SET active=0 WHERE id=?')->execute([$uid]);lh(order_photo_url($o['photo']),$staff,null,404);
 lc(sb_geo_pair(0,0)==='0,0' && sb_geo_pair(null,82)===null && sb_geo_pair(91,82)===null,'coordinate validation');
 $job=['status'=>'picked','rider'=>['name'=>'CI Sarathi'],'rider_location'=>['lat'=>25.395,'lng'=>82.568,'at'=>time()]];
 $o=sb_apply_job($pdo,$o,$job);lc($o['status']==='Pickup','bridge pickup');
 $pdo->prepare('UPDATE orders SET sarathi_job=?,sarathi_checked=NOW() WHERE id=?')->execute(['CI-'.$tag,$ids[0]]);
 [$json]=lh('/api.php?a=where&no='.$o['order_no'].'&m='.$o['mobile'],$anon);$live=json_decode($json,true);
 lc($live['live'] && $live['rider']==='CI Sarathi' && abs($live['lat']-25.395)<0.000001,'Sarathi GPS powers goods tracking');
 $pdo->prepare('UPDATE order_sarathi_locations SET captured_at=? WHERE order_id=?')->execute([time()-181,$ids[0]]);
 [$json]=lh('/api.php?a=where&no='.$o['order_no'].'&m='.$o['mobile'],$anon);lc(!json_decode($json,true)['live'],'stale GPS hidden');
 $old=$job;$old['status']='assigned';$o=sb_apply_job($pdo,$o,$old);lc($o['status']==='Pickup','no status regression');
 $job['status']='delivered';$o=sb_apply_job($pdo,$o,$job);lc($o['status']==='Delivered','delivery state');
 $q=$pdo->prepare('SELECT lat FROM order_sarathi_locations WHERE order_id=?');$q->execute([$ids[0]]);lc($q->fetchColumn()===null,'terminal GPS cleared');
 lc(sb_job_location(['status'=>'assigned','rider_location'=>['lat'=>91,'lng'=>0,'at'=>time()]])===null,'invalid GPS rejected');
 echo "Launch: customer/shop/rider media isolation, guest proof, disabled identities, locale, booking redirect and Sarathi tracking passed\n";
}finally{
 foreach($ids as $id){$pdo->prepare('DELETE FROM order_sarathi_locations WHERE order_id=?')->execute([$id]);$pdo->prepare('DELETE FROM orders WHERE id=?')->execute([$id]);}
 foreach($shops as $id){$pdo->prepare('DELETE FROM shop_tokens WHERE business_id=?')->execute([$id]);$pdo->prepare('DELETE FROM businesses WHERE id=?')->execute([$id]);}
 foreach($customers as $id)$pdo->prepare('DELETE FROM customers WHERE id=?')->execute([$id]);
 foreach($users as $id)$pdo->prepare('DELETE FROM users WHERE id=?')->execute([$id]);
 foreach($files as $file)@unlink($file);foreach($jars as $jar)@unlink($jar);
}
