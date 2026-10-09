<?php
// Isolated CI database only: real multipart -> manual approval -> login -> JWT -> logout.
ob_start();
require_once __DIR__.'/../../config.php';
if(DB_NAME!=='maakit_jaanch')throw new RuntimeException('CI only');
require_once __DIR__.'/../../inc/fn.php';
require_once __DIR__.'/../../controllers/partners.php';
use function Maakit\Api\{query,review_partner,document_metadata,document_directory};
function partner_check($ok,$message){if(!$ok)throw new RuntimeException($message);}
$jar=tempnam(sys_get_temp_dir(),'mk-partner');$guest=tempnam(sys_get_temp_dir(),'mk-guest');$temp=tempnam(sys_get_temp_dir(),'mk-doc');
file_put_contents($temp,"%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\n%%EOF\n");$fileSize=filesize($temp);
$users=[];$applications=[];$uuidUsers=[];$documents=[];
function partner_http(string $path,$data=null,int $expected=200,array $headers=[]): array {
 global $jar;
 $c=curl_init('http://127.0.0.1:8099'.$path);$responseHeaders='';
 curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEFILE=>$jar,CURLOPT_COOKIEJAR=>$jar,CURLOPT_TIMEOUT=>20,CURLOPT_PROXY=>'',CURLOPT_HTTPHEADER=>$headers,CURLOPT_HEADERFUNCTION=>static function($c,$h)use(&$responseHeaders){$responseHeaders.=$h;return strlen($h);}]);
 if($data!==null)curl_setopt_array($c,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$data]);
 $body=curl_exec($c);$code=curl_getinfo($c,CURLINFO_HTTP_CODE);curl_close($c);
 partner_check($code===$expected && is_string($body) && !preg_match('/Fatal error|Warning:|Uncaught|SQLSTATE/',$body),$path.' expected '.$expected.' got '.$code);
 return [$body,$responseHeaders];
}
function partner_csrf(string $body): string {preg_match('/(?:name="csrf" value|data-csrf)="([^"]+)"/',$body,$m);partner_check(isset($m[1]),'CSRF field');return html_entity_decode($m[1],ENT_QUOTES,'UTF-8');}
function partner_user(string $role,string $mobile,int $active=0): int {global $pdo,$users;$pdo->prepare('INSERT INTO users(name,username,password,role,mobile,active) VALUES(?,?,?,?,?,?)')->execute(['CI Partner',$mobile,password_hash('CI-Secure-1234',PASSWORD_DEFAULT),$role,$mobile,$active]);$id=(int)$pdo->lastInsertId();$users[]=$id;return $id;}
try {
 $admin=partner_user('admin','9000081000',1);
 // File contents, size, and origin are validated independently of client names and MIME hints.
 partner_check(document_metadata(['error'=>UPLOAD_ERR_OK,'tmp_name'=>$temp,'size'=>$fileSize])['mime']==='application/pdf','real PDF allowed');
 foreach ([['size'=>5242881],['error'=>UPLOAD_ERR_INI_SIZE],['tmp_name'=>[$temp]]] as $bad) {
  try {document_metadata($bad+['error'=>UPLOAD_ERR_OK,'tmp_name'=>$temp,'size'=>$fileSize]);throw new RuntimeException('invalid document accepted');}catch(InvalidArgumentException $e){}
 }
 $bad=tempnam(sys_get_temp_dir(),'mk-bad');file_put_contents($bad,'<?php echo "not a document";');
 try{document_metadata(['error'=>UPLOAD_ERR_OK,'tmp_name'=>$bad,'size'=>filesize($bad)]);throw new RuntimeException('PHP accepted');}catch(InvalidArgumentException $e){}finally{unlink($bad);}
 $savedCount=count(glob(document_directory().'/*'));
 // Multipart failures leave neither private files nor login records.
 $csrf=partner_csrf(partner_http('/public/partner-apply.php?kind=rider&lang=en')[0]);
 $fields=['csrf'=>$csrf,'applicant_name'=>'CI Rider','mobile'=>'9000081001','credential'=>'CI-Secure-1234','vehicle_type'=>'Bike','dl_number'=>'UP0123456789','rc_number'=>'UP65AB1234'];
 partner_http('/public/partner-apply.php?kind=rider',$fields,200);
 partner_check(!query($pdo,'SELECT id FROM users WHERE username=?',[$fields['mobile']])->fetch(),'missing documents cannot create user');
 $fields['dl_document']=new CURLFile($temp,'image/jpeg','misleading.jpg');$fields['rc_document']=new CURLFile($temp,'application/pdf','rc.pdf');
 partner_http('/public/partner-apply.php?kind=rider',$fields,302);
 $a=query($pdo,'SELECT * FROM mk_partner_applications WHERE mobile=?',[$fields['mobile']])->fetch();partner_check($a&&$a['status']==='PENDING','rider saved pending');
 $applications[]=$a['id'];$users[]=$a['login_user_id'];
 partner_check((int)query($pdo,'SELECT active FROM users WHERE id=?',[$a['login_user_id']])->fetchColumn()===0,'pending user inactive');
 $docRows=query($pdo,'SELECT * FROM mk_partner_documents WHERE application_id=?',[$a['id']])->fetchAll();partner_check(count($docRows)===2 && $docRows[0]['mime']==='application/pdf','server ignores forged extension/MIME');
 $documents=array_merge($documents,array_column($docRows,'storage_key'));
 $csrf=partner_csrf(partner_http('/login.php?as=team&lang=en')[0]);
 partner_http('/login.php',http_build_query(['csrf'=>$csrf,'do'=>'team','username'=>$fields['mobile'],'password'=>$fields['credential']]),200);
 partner_http('/public/partner-dashboard.php',null,302);
 partner_http('/public/admin/document.php?id='.$docRows[0]['id'],null,403);
 // Submit a B2B seller with private PAN and a chosen mobile+code login.
 $csrf=partner_csrf(partner_http('/public/partner-apply.php?kind=vendor&lang=en')[0]);
 $vendor=['csrf'=>$csrf,'applicant_name'=>'CI Seller','mobile'=>'9000081002','credential'=>'CI-Secure-1234','business_name'=>'CI business','market'=>'B2B','shop_category'=>'Grocery','pan'=>'ABCDE1234F','pan_document'=>new CURLFile($temp,'application/pdf','pan.pdf')];
 partner_http('/public/partner-apply.php?kind=vendor',$vendor,302);
 $v=query($pdo,'SELECT * FROM mk_partner_applications WHERE mobile=?',[$vendor['mobile']])->fetch();$applications[]=$v['id'];$users[]=$v['login_user_id'];
 $documents=array_merge($documents,query($pdo,'SELECT storage_key FROM mk_partner_documents WHERE application_id=?',[$v['id']])->fetchAll(PDO::FETCH_COLUMN));
 // Approval HTTP endpoint authenticates against the live admin record and requires CSRF.
 $csrf=partner_csrf(partner_http('/login.php?as=team&lang=en')[0]);
 partner_http('/login.php',http_build_query(['csrf'=>$csrf,'do'=>'team','username'=>'9000081000','password'=>'CI-Secure-1234']),302);
 $csrf=partner_csrf(partner_http('/public/admin/approvals.php?lang=en')[0]);
 partner_http('/public/admin/approvals.php',http_build_query(['id'=>$a['id'],'decision'=>'APPROVED']),403);
 partner_check(query($pdo,'SELECT status FROM mk_partner_applications WHERE id=?',[$a['id']])->fetchColumn()==='PENDING','CSRF cannot approve');
 foreach([$a,$v] as $application){
  partner_http('/public/admin/approvals.php',http_build_query(['csrf'=>$csrf,'id'=>$application['id'],'decision'=>'APPROVED']));
  $uid=query($pdo,"SELECT user_id FROM mk_legacy_identities WHERE source='STAFF' AND legacy_id=?",[$application['login_user_id']])->fetchColumn();partner_check((bool)$uid,'approved identity mapped');$uuidUsers[]=$uid;
  partner_check(!review_partner($pdo,(int)$application['id'],'APPROVED',$admin),'repeat approval no-op');
 }
 partner_check((int)query($pdo,'SELECT COUNT(*) FROM mk_riders WHERE user_id=?',[$uuidUsers[0]])->fetchColumn()===1,'single verified rider profile');
 partner_check((int)query($pdo,'SELECT COUNT(*) FROM mk_vendors WHERE owner_user_id=?',[$uuidUsers[1]])->fetchColumn()===1,'single vendor profile');
 partner_check(partner_http('/public/admin/document.php?id='.$docRows[0]['id'])[0]===file_get_contents($temp),'admin receives exact private document');
 query($pdo,'UPDATE users SET active=0 WHERE id=?',[$admin]);partner_http('/public/admin/document.php?id='.$docRows[0]['id'],null,403);partner_http('/public/admin/approvals.php',null,403);query($pdo,'UPDATE users SET active=1 WHERE id=?',[$admin]);
 // Reject leaves credentials disabled and cannot be turned into approve by replay.
 $rejected=partner_user('rider','9000081003');
 query($pdo,"INSERT INTO mk_partner_applications(kind,applicant_name,mobile,login_user_id) VALUES('RIDER','CI reject','9000081003',?)",[$rejected]);$rejectId=(int)$pdo->lastInsertId();$applications[]=$rejectId;
 partner_check(review_partner($pdo,$rejectId,'REJECTED',$admin),'reject saved');partner_check(!review_partner($pdo,$rejectId,'APPROVED',$admin),'rejected cannot be reapproved');partner_check((int)query($pdo,'SELECT active FROM users WHERE id=?',[$rejected])->fetchColumn()===0,'reject inactive');
 // Old applications require reapplication, without guessing an existing identity from phone.
 query($pdo,"INSERT INTO mk_partner_applications(kind,applicant_name,mobile) VALUES('VENDOR','CI old','9000081004')");$old=(int)$pdo->lastInsertId();$applications[]=$old;
 try{review_partner($pdo,$old,'APPROVED',$admin);throw new RuntimeException('old application claimed');}catch(InvalidArgumentException $e){}
 partner_check(query($pdo,'SELECT status FROM mk_partner_applications WHERE id=?',[$old])->fetchColumn()==='PENDING','old application rolled back');
 // Real vendor and rider logins now reach their dashboards and get scoped Bearer tokens.
 foreach([['vendor',$vendor],['rider',$fields]] as [$role,$registration]){
  $csrf=partner_csrf(partner_http('/logout.php?lang=en')[0]);partner_http('/logout.php',http_build_query(['csrf'=>$csrf]),302);
  $csrf=partner_csrf(partner_http('/public/auth.php?role='.$role.'&lang=en')[0]);
  $login=$role==='vendor'?['do'=>'dukan','mobile'=>$registration['mobile'],'code'=>$registration['credential']]:['do'=>'team','username'=>$registration['mobile'],'password'=>$registration['credential']];
  partner_http('/login.php',http_build_query($login+['csrf'=>$csrf]),302);
  partner_check(str_contains(partner_http('/public/partner-dashboard.php?lang=en')[0],ucfirst($role==='vendor'?'seller':'rider').' dashboard'),'role dashboard accessible');
  $csrf=partner_csrf(partner_http('/public/auth.php?role='.$role.'&lang=en')[0]);
  partner_http('/api/v1/session.php',http_build_query(['context'=>'staff']),403);
  [$json,$headers]=partner_http('/api/v1/session.php',http_build_query(['context'=>'staff','requested_role'=>$role]),200,['X-CSRF-Token: '.$csrf]);
  $data=json_decode($json,true,32,JSON_THROW_ON_ERROR);$token=$data['data']['access_token'];
  partner_check(str_contains(strtolower($headers),'httponly')&&str_contains(strtolower($headers),'samesite=strict'),'HttpOnly Strict token cookie');
  $api=partner_http('/api/v1/index.php?action=dispatch_view',null,200,['Authorization: Bearer '.$token]);partner_check(isset(json_decode($api[0],true)['data']),'Bearer token authorizes own dashboard');
  partner_http('/api/v1/index.php?action=dispatch_view',null,401); // cookie alone insufficient
  partner_http('/api/v1/session.php',http_build_query(['context'=>'staff','requested_role'=>'admin']),403,['X-CSRF-Token: '.$csrf]);
  $logoutCsrf=partner_csrf(partner_http('/logout.php?lang=en')[0]);partner_http('/logout.php',http_build_query(['csrf'=>$logoutCsrf]),302);
  partner_http('/api/v1/index.php?action=dispatch_view',null,401,['Authorization: Bearer '.$token]);
 }
 echo "Partner multipart, private documents, CSRF, approval/rejection, role mapping, actual login, JWT cookies and revocation passed\n";
} finally {
 foreach($uuidUsers as $uid){
  $sessions=query($pdo,'SELECT id FROM mk_sessions WHERE user_id=?',[$uid])->fetchAll(PDO::FETCH_COLUMN);
  foreach($sessions as $sid)query($pdo,'DELETE FROM mk_session_origins WHERE session_id=?',[$sid]);
  foreach(['mk_sessions','mk_legacy_identities','mk_user_roles','mk_vendor_members','mk_riders'] as $table)query($pdo,'DELETE FROM '.$table.' WHERE user_id=?',[$uid]);
  query($pdo,'DELETE FROM mk_vendors WHERE owner_user_id=?',[$uid]);query($pdo,'DELETE FROM mk_users WHERE id=?',[$uid]);
 }
 foreach($applications as $id){query($pdo,'DELETE FROM mk_partner_documents WHERE application_id=?',[$id]);query($pdo,'DELETE FROM mk_partner_applications WHERE id=?',[$id]);}
 foreach($users as $id)query($pdo,'DELETE FROM users WHERE id=?',[$id]);
 foreach($documents as $key)@unlink(document_directory().'/'.$key);
 @unlink($jar);@unlink($guest);@unlink($temp);
}
