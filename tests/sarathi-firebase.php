<?php
require_once __DIR__.'/../smartsarathi/inc/firebase.php';
function ck($ok,$label){if(!$ok)throw new RuntimeException($label);}
function b64($s){return rtrim(strtr(base64_encode($s),'+/','-_'),'=');}
$key=openssl_pkey_new(['private_key_bits'=>2048,'private_key_type'=>OPENSSL_KEYTYPE_RSA]);
ck((bool)$key,'RSA available');$pub=openssl_pkey_get_details($key)['key'];
$now=time();$p=['sub'=>'uid-1','aud'=>'sarathi-test','iss'=>'https://securetoken.google.com/sarathi-test','iat'=>$now,'exp'=>$now+3600,'auth_time'=>$now,'phone_number'=>'+919876543210','firebase'=>['sign_in_provider'=>'phone']];
function tok($p,$h=['alg'=>'RS256','kid'=>'test']){global $key;$body=b64(json_encode($h)).'.'.b64(json_encode($p));openssl_sign($body,$sig,$key,OPENSSL_ALGO_SHA256);return $body.'.'.b64($sig);}
function reject($token,$label){global $pub,$now;try{sr_verify_phone_token($token,'sarathi-test',['test'=>$pub],$now);}catch(Throwable $e){return;}throw new RuntimeException('Accepted '.$label);}
ck(sr_verify_phone_token(tok($p),'sarathi-test',['test'=>$pub],$now)===['uid'=>'uid-1','mobile'=>'9876543210'],'Valid phone token');
foreach(['aud'=>'another-project','iss'=>'https://attacker.invalid','sub'=>'','exp'=>$now,'iat'=>$now+100,'auth_time'=>$now-301,'phone_number'=>'+12025550123','firebase'=>['sign_in_provider'=>'password']] as $field=>$bad){$d=$p;$d[$field]=$bad;reject(tok($d),$field);}
foreach(['exp','iat','auth_time'] as $f){$d=$p;unset($d[$f]);reject(tok($d),'missing '.$f);}
reject(tok($p,['alg'=>'none','kid'=>'test']),'none algorithm');
reject(tok($p,['alg'=>'RS256','kid'=>'attacker']),'unknown key');
$valid=tok($p);$parts=explode('.',$valid);$parts[1]=b64(json_encode(array_merge($p,['sub'=>'admin'])));reject(implode('.',$parts),'modified payload');
reject('', 'empty token');
echo "Firebase token security checks passed\n";
