<?php
require_once __DIR__ . '/../../config.php';
if (DB_NAME !== 'maakit_jaanch') throw new RuntimeException('Only the CI database is allowed');
require_once __DIR__ . '/../../inc/fn.php';
require_once __DIR__ . '/../../inc/earning.php';
function money_check($ok,$message) { if (!$ok) throw new RuntimeException($message); }
$jar=tempnam(sys_get_temp_dir(),'mk-money-'); $uid=0; $oid=0; $cid=0; $tid=0; $driverid=0; $areaid=0; $billfile=tempnam(sys_get_temp_dir(),'mk-bill-'); $billname=null; $today=date('Y-m-d');
function money_request($path,$post=null,$status=200) {
    global $jar;
    $ch=curl_init('http://127.0.0.1:8099'.$path);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEJAR=>$jar,CURLOPT_COOKIEFILE=>$jar,CURLOPT_TIMEOUT=>20,CURLOPT_PROXY=>'']);
    if ($post !== null) curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>(isset($post['bill']) && $post['bill'] instanceof CURLFile ? $post : http_build_query($post))]);
    $html=curl_exec($ch);$code=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
    money_check($html !== false && $code===$status && !preg_match('/Fatal error|Warning:|Uncaught/',$html),'Money page failed: '.$path);
    return $html;
}
try {
    money_request('/admin/summary.php',null,302);
    $password=bin2hex(random_bytes(16));
    $pdo->prepare("INSERT INTO users(name,username,password,role) VALUES ('Money test','jaanch-money',?,'admin')")->execute([password_hash($password,PASSWORD_DEFAULT)]);
    $uid=(int)$pdo->lastInsertId();
    $html=money_request('/login.php?as=team');preg_match('/name="csrf" value="([^"]+)"/',$html,$m);
    money_request('/login.php?as=team',['csrf'=>$m[1],'do'=>'team','username'=>'jaanch-money','password'=>$password],302);
    $html=money_request('/admin/summary.php?lang=hi');
    money_check(strpos($html,'दिन का खर्च') !== false && strpos($html,'हिसाब अधूरा') !== false,'Admin gets costs and incomplete balance');
    preg_match('/name="csrf" value="([^"]+)"/',$html,$m);
    $post=['csrf'=>$m[1],'cost_date'=>$today,'fuel'=>'20','staff'=>'30','other'=>'10','note'=>'CI only'];
    money_request('/admin/summary.php',$post,302);
    $post['fuel']='25';money_request('/admin/summary.php',$post,302);
    $st=$pdo->prepare('SELECT * FROM operating_costs WHERE cost_date=?');$st->execute([$today]);$cost=$st->fetch();
    money_check((int)$cost['fuel']===25 && (int)$cost['staff']===30 && (int)$cost['updated_by']===$uid,'Cost corrections replace totals, keep owner');
    $post['fuel']='-1';money_request('/admin/summary.php',$post);
    $post['fuel']='999';$post['csrf']='wrong';money_request('/admin/summary.php',$post);
    $st->execute([$today]);money_check((int)$st->fetch()['fuel']===25,'Invalid or forged cost cannot be saved');
    money_request('/admin/dash.php');
    $ready=money_request('/admin/readiness.php?lang=en');
    money_check(strpos($ready,'Launch readiness')!==false && strpos($ready,'Items missing prices')!==false && strpos($ready,'WhatsApp OTP')!==false,'Admin readiness shows real counts and external checks');
    $days=earning_days($pdo,$today,$today);money_check($days[0]['expense']===65,'MariaDB cost report aggregates correctly');
    $pdo->prepare("INSERT INTO orders(order_no,code,customer_name,mobile,village,items,status) VALUES ('CI-DELIVERY','1234','CI Customer','9000000088','CI Area','test goods','Cancel')")->execute();$oid=(int)$pdo->lastInsertId();
    money_request('/delivery/',['csrf'=>$m[1],'id'=>$oid,'status'=>'Pickup'],302);
    $state=$pdo->prepare('SELECT status FROM orders WHERE id=?');$state->execute([$oid]);money_check($state->fetchColumn()==='Cancel','Cancelled order cannot be picked up');
    money_request('/delivery/',['csrf'=>$m[1],'id'=>$oid,'status'=>'Delivered','code'=>'1234'],302);
    $state->execute([$oid]);money_check($state->fetchColumn()==='Cancel','Cancelled order cannot be delivered');
    $pdo->prepare("UPDATE orders SET status='Assign' WHERE id=?")->execute([$oid]);
    money_request('/delivery/',['csrf'=>$m[1],'id'=>$oid,'status'=>'Delivered','code'=>'1234'],302);$state->execute([$oid]);money_check($state->fetchColumn()==='Assign','Delivery requires pickup');
    money_request('/delivery/',['csrf'=>$m[1],'id'=>$oid,'status'=>'Pickup'],302);$state->execute([$oid]);money_check($state->fetchColumn()==='Pickup','Pickup succeeds');
    $image=imagecreatetruecolor(2,2);imagepng($image,$billfile);imagedestroy($image);
    $billpost=['csrf'=>$m[1],'id'=>$oid,'status'=>'bill','amount'=>'250','bill'=>new CURLFile($billfile,'image/png','bill.png')];
    foreach(['-1','1.5','1e3','1000001'] as $bad_amount){$billpost['amount']=$bad_amount;money_request('/delivery/',$billpost,302);}
    $billstate=$pdo->prepare('SELECT goods_amount,bill_photo FROM orders WHERE id=?');$billstate->execute([$oid]);$row=$billstate->fetch();money_check(empty($row['bill_photo']),'Invalid bill amount cannot store photo');
    $billpost['amount']='250';money_request('/delivery/',$billpost,302);$billstate->execute([$oid]);$row=$billstate->fetch();$billname=$row['bill_photo'];money_check((int)$row['goods_amount']===250 && !empty($billname),'Valid bill stores shop amount and photo');
    money_request('/delivery/',['csrf'=>$m[1],'id'=>$oid,'status'=>'Delivered','code'=>'9999'],302);$state->execute([$oid]);money_check($state->fetchColumn()==='Pickup','Wrong delivery code rejected');
    money_request('/delivery/',['csrf'=>$m[1],'id'=>$oid,'status'=>'Delivered','code'=>'1234'],302);$state->execute([$oid]);money_check($state->fetchColumn()==='Delivered','Correct code completes delivery');
    money_request('/delivery/',['csrf'=>$m[1],'id'=>$oid,'status'=>'Pickup'],302);$state->execute([$oid]);money_check($state->fetchColumn()==='Delivered','Completed order cannot regress to pickup');
    $billpost['amount']='999';money_request('/delivery/',$billpost,302);$billstate->execute([$oid]);$row=$billstate->fetch();money_check((int)$row['goods_amount']===250 && $row['bill_photo']===$billname,'Completed order bill cannot change');
    $shipment=['csrf'=>$m[1],'carrier'=>'CI Courier','tracking_no'=>'CI-AWB-123'];
    money_request('/admin/shipment.php?id='.$oid,$shipment,302);
    $row=$pdo->query('SELECT * FROM courier_tracking WHERE order_id='.(int)$oid)->fetch();money_check($row && $row['tracking_no']==='CI-AWB-123' && (int)$row['updated_by']===$uid,'Courier details save with staff owner');
    $html=money_request('/track.php?no=CI-DELIVERY&m=9000000088&lang=en');money_check(strpos($html,'CI-AWB-123')!==false && strpos($html,'not a live courier status')!==false,'Customer sees manual courier reference');
    $shipment['csrf']='invalid';$shipment['tracking_no']='FORGED';money_request('/admin/shipment.php?id='.$oid,$shipment);
    $pdo->prepare("UPDATE orders SET status='Cancel' WHERE id=?")->execute([$oid]);$shipment['csrf']=$m[1];money_request('/admin/shipment.php?id='.$oid,$shipment);
    money_check($pdo->query('SELECT tracking_no FROM courier_tracking WHERE order_id='.(int)$oid)->fetchColumn()==='CI-AWB-123','CSRF/cancelled shipment edits rejected');
    $pdo->prepare("INSERT INTO users(name,username,password,role) VALUES ('CI Area Driver','jaanch-area-driver',?,'delivery')")->execute([password_hash($password,PASSWORD_DEFAULT)]);$driverid=(int)$pdo->lastInsertId();
    $area=['csrf'=>$m[1],'name'=>'CI Dispatch Area','city'=>'CI City','state'=>'CI State','pincode'=>'221403','base_fee'=>'40','live'=>'1','delivery_on'=>'1','drivers'=>[$uid]];
    money_request('/admin/coverage.php',$area);money_check(!$pdo->query("SELECT id FROM villages WHERE name='CI Dispatch Area'")->fetchColumn(),'Admin cannot be linked as delivery driver');
    $area['drivers']=[$driverid];money_request('/admin/coverage.php',$area,302);$areaid=(int)$pdo->query("SELECT id FROM villages WHERE name='CI Dispatch Area'")->fetchColumn();
    money_check($areaid && count(coverage_drivers($pdo,'CI Dispatch Area'))===1,'Area roster saves active delivery staff');
    $pdo->prepare("UPDATE orders SET village='CI Dispatch Area',status='Naya',delivery_user=NULL WHERE id=?")->execute([$oid]);
    $assign=['csrf'=>$m[1],'do'=>'assign','id'=>$oid,'delivery_user'=>$uid];money_request('/bpo/',$assign,302);$state->execute([$oid]);money_check($state->fetchColumn()==='Naya','Non-driver assignment blocked');
    $assign['delivery_user']=$driverid;$bad=$assign;$bad['csrf']='invalid';money_request('/bpo/',$bad);$state->execute([$oid]);money_check($state->fetchColumn()==='Naya','Forged assignment blocked');
    $pdo->prepare("UPDATE orders SET village='CI Other Area' WHERE id=?")->execute([$oid]);money_request('/bpo/',$assign,302);$state->execute([$oid]);money_check($state->fetchColumn()==='Naya','Cross-area assignment blocked');
    $pdo->prepare("UPDATE orders SET village='CI Dispatch Area' WHERE id=?")->execute([$oid]);$pdo->prepare('UPDATE users SET active=0 WHERE id=?')->execute([$driverid]);money_request('/bpo/',$assign,302);$state->execute([$oid]);money_check($state->fetchColumn()==='Naya','Inactive driver blocked');
    $pdo->prepare('UPDATE users SET active=1 WHERE id=?')->execute([$driverid]);money_request('/bpo/',$assign,302);$state->execute([$oid]);money_check($state->fetchColumn()==='Assign','Area-linked driver receives order');
    foreach(['Pickup','Delivered','Cancel'] as $locked){$pdo->prepare('UPDATE orders SET status=?,delivery_user=NULL WHERE id=?')->execute([$locked,$oid]);money_request('/bpo/',$assign,302);$row=$pdo->query('SELECT status,delivery_user FROM orders WHERE id='.(int)$oid)->fetch();money_check($row['status']===$locked && $row['delivery_user']===null,'Locked fulfilment cannot be reassigned');}
    $html=money_request('/admin/readiness.php?lang=en');money_check(strpos($html,'Linked active delivery staff')!==false,'Readiness shows per-area staff count');
    $statuspost=['csrf'=>$m[1],'do'=>'status','id'=>$oid,'status'=>'Naya'];money_request('/bpo/',$statuspost,302);$state->execute([$oid]);money_check($state->fetchColumn()==='Cancel','Staff cannot reopen cancelled order');
    $pdo->prepare("UPDATE orders SET status='Naya',delivery_user=NULL WHERE id=?")->execute([$oid]);
    foreach(['Delivered','Paisa jama','Assign','unknown'] as $invalid){$statuspost['status']=$invalid;money_request('/bpo/',$statuspost,302);$state->execute([$oid]);money_check($state->fetchColumn()==='Naya','Staff cannot skip fulfilment or submit unknown state');}
    $amountpost=['csrf'=>$m[1],'do'=>'amount','id'=>$oid,'goods_amount'=>'0','delivery_charge'=>'0','payment'=>'मैं खुद दुकान को UPI करूँगा'];money_request('/bpo/',$amountpost,302);
    $billstate->execute([$oid]);money_check((int)$billstate->fetch()['goods_amount']===0,'Staff explicit zero amount retained');
    foreach(['-1','1.5','1000001'] as $invalid){$bad=$amountpost;$bad['goods_amount']=$invalid;money_request('/bpo/',$bad,302);}$bad=$amountpost;$bad['payment']='एडवांस लिया';money_request('/bpo/',$bad,302);$billstate->execute([$oid]);money_check((int)$billstate->fetch()['goods_amount']===0,'Invalid staff amounts/payment rejected');
    $amountpost['goods_amount']='';money_request('/bpo/',$amountpost,302);$billstate->execute([$oid]);money_check($billstate->fetch()['goods_amount']===null,'Staff blank amount stays unknown');
    $statuspost['status']='Confirm';money_request('/bpo/',$statuspost,302);$state->execute([$oid]);money_check($state->fetchColumn()==='Confirm','Staff confirms fresh order');
    money_request('/bpo/',$assign,302);$statuspost['status']='Pickup';money_request('/bpo/',$statuspost,302);$statuspost['status']='Delivered';money_request('/bpo/',$statuspost,302);$state->execute([$oid]);money_check($state->fetchColumn()==='Delivered','Assigned staff fulfilment advances in order');
    $statuspost['status']='Confirm';money_request('/bpo/',$statuspost,302);$amountpost['goods_amount']='999';money_request('/bpo/',$amountpost,302);$state->execute([$oid]);$billstate->execute([$oid]);money_check($state->fetchColumn()==='Delivered' && $billstate->fetch()['goods_amount']===null,'Completed status and bill protected');
    $html=money_request('/bpo/new.php?lang=en');preg_match('/name="submit_key" value="([^"]+)"/',$html,$bm);
    $newpost=['csrf'=>$m[1],'submit_key'=>$bm[1],'name'=>'CI BPO Customer','mobile'=>'9000000066','village'=>'CI Dispatch Area','items'=>'CI goods','market'=>(string)array_key_first(markets()),'weight'=>'0','size'=>'0','source'=>'call','payment'=>'मैं खुद दुकान को UPI करूँगा','delivery_charge'=>'0'];
    $bad=$newpost;$bad['village']='CI Nonexistent';money_request('/bpo/new.php',$bad);$bad=$newpost;$bad['payment']='एडवांस लिया';money_request('/bpo/new.php',$bad);$bad=$newpost;$bad['delivery_charge']='-1';money_request('/bpo/new.php',$bad);
    money_check((int)$pdo->query("SELECT COUNT(*) FROM orders WHERE mobile='9000000066'")->fetchColumn()===0,'Invalid BPO new orders rejected');
    money_request('/bpo/new.php',$newpost);money_request('/bpo/new.php',$newpost);money_check((int)$pdo->query("SELECT COUNT(*) FROM orders WHERE mobile='9000000066'")->fetchColumn()===1,'BPO double-submit creates one order');
    $pdo->prepare("INSERT INTO customers(name,mobile,password,village,landmark) VALUES ('CI Support','9000000077',?,'','')")->execute([password_hash($password,PASSWORD_DEFAULT)]);$cid=(int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO support_tickets(customer_id,reference_no,kind,message) VALUES (?,'CI-DELIVERY','return','CI return test request')")->execute([$cid]);$tid=(int)$pdo->lastInsertId();
    $resolution=['csrf'=>$m[1],'id'=>$tid,'status'=>'reviewing','reply'=>'Return reviewed with shop','decision'=>'approved','refund_amount'=>'','refund_reference'=>''];
    money_request('/admin/support.php',$resolution,302);
    $check=$pdo->prepare('SELECT * FROM support_resolution WHERE ticket_id=?');$check->execute([$tid]);money_check($check->fetch()['decision']==='approved','Admin approves request');
    $bad=$resolution;$bad['decision']='rejected';$bad['reply']='';money_request('/admin/support.php',$bad);$check->execute([$tid]);money_check($check->fetch()['decision']==='approved','Rejection requires reason');
    $bad=$resolution;$bad['refund_amount']='-5';$bad['refund_reference']='SHOP-123';money_request('/admin/support.php',$bad);$check->execute([$tid]);money_check($check->fetch()['refund_amount']===null,'Invalid refund rejected');
    $resolution['refund_amount']='100';$resolution['refund_reference']='SHOP-123';$resolution['status']='resolved';money_request('/admin/support.php',$resolution,302);$check->execute([$tid]);$r=$check->fetch();money_check((int)$r['refund_amount']===100 && $r['refund_reference']==='SHOP-123','Direct shop refund recorded');
    $resolution['refund_amount']='200';money_request('/admin/support.php',$resolution);$check->execute([$tid]);money_check((int)$check->fetch()['refund_amount']===100,'Recorded paid refund cannot be overwritten');
    $resolution['refund_amount']='100';$resolution['return_stage']='scheduled';$resolution['return_date']='2026-02-30';
    money_request('/admin/support.php',$resolution);$check->execute([$tid]);money_check($check->fetch()['return_stage']==='not_required','Invalid pickup date rejected');
    $resolution['return_date']='2026-10-10';$bad=$resolution;$bad['decision']='pending';money_request('/admin/support.php',$bad);
    money_request('/admin/support.php',$resolution,302);$check->execute([$tid]);$r=$check->fetch();money_check($r['return_stage']==='scheduled' && $r['return_date']==='2026-10-10','Approved return pickup scheduled');
    $resolution['return_stage']='collected';money_request('/admin/support.php',$resolution,302);
    $bad=$resolution;$bad['return_stage']='scheduled';money_request('/admin/support.php',$bad);$check->execute([$tid]);money_check($check->fetch()['return_stage']==='collected','Return cannot regress');
    $resolution['return_stage']='received';money_request('/admin/support.php',$resolution,302);
    $saved_jar=$jar;$jar=tempnam(sys_get_temp_dir(),'mk-support-customer-');
    try{
      $html=money_request('/account.php?lang=en');preg_match('/name="csrf" value="([^"]+)"/',$html,$cm);
      money_request('/account.php',['csrf'=>$cm[1],'do'=>'login','mobile'=>'9000000077','password'=>$password],302);
      $html=money_request('/support.php?lang=en');money_check(strpos($html,'SHOP-123')!==false && strpos($html,'Approved')!==false,'Customer sees decision and refund reference');money_check(strpos($html,'Returned to shop')!==false && strpos($html,'2026-10-10')!==false,'Customer sees return progress and pickup date');
    }finally{unlink($jar);$jar=$saved_jar;}
    echo "Authenticated daily cost entry and reporting checks passed\n";
} finally {
    $pdo->exec("DELETE FROM orders WHERE mobile='9000000066'");
    if ($areaid) { $pdo->prepare('DELETE FROM service_area_drivers WHERE village_id=?')->execute([$areaid]);$pdo->prepare('DELETE FROM service_area_meta WHERE village_id=?')->execute([$areaid]);$pdo->prepare('DELETE FROM villages WHERE id=?')->execute([$areaid]); }
    if ($driverid) $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$driverid]);
    if ($tid) { $pdo->prepare('DELETE FROM support_resolution WHERE ticket_id=?')->execute([$tid]);$pdo->prepare('DELETE FROM support_tickets WHERE id=?')->execute([$tid]); }
    if ($cid) $pdo->prepare('DELETE FROM customers WHERE id=?')->execute([$cid]);
    if ($billname) drop_photo($billname);
    if (is_file($billfile)) unlink($billfile);
    if ($oid) $pdo->prepare('DELETE FROM courier_tracking WHERE order_id=?')->execute([$oid]);
    if ($oid) $pdo->prepare('DELETE FROM orders WHERE id=?')->execute([$oid]);
    if ($uid) {
        $pdo->prepare('DELETE FROM operating_costs WHERE updated_by=?')->execute([$uid]);
        $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$uid]);
    }
    unlink($jar);
}
