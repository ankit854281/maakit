<?php
declare(strict_types=1);
namespace Maakit\Api;
use PDO;
use Throwable;
require_once __DIR__.'/companyDelivery.php';
function order_safety_codes(PDO $db,array $auth,array $b): array {
 require_roles($auth,['CUSTOMER']);$id=valid_uuid($b['order_id']??null);$db->beginTransaction();try{$auth=live_identity($db,$auth['claims'],true);require_roles($auth,['CUSTOMER']);$o=query($db,'SELECT status FROM mk_orders WHERE id=? AND customer_id=? FOR UPDATE',[$id,$auth['user_id']])->fetch();if(!$o)throw new ApiError(404,'NOT_FOUND','Order not found.');if(!in_array($o['status'],['AWAITING_VENDOR','CONFIRMED','PACKING','READY'],true))throw new ApiError(409,'INVALID_TRANSITION','Set safety codes before pickup.');$pick=(string)random_int(100000,999999);$drop=(string)random_int(100000,999999);query($db,'INSERT INTO mk_order_safety(order_id,pickup_hash,drop_hash,expires_at) VALUES(?,?,?,DATE_ADD(UTC_TIMESTAMP(6),INTERVAL 24 HOUR)) ON DUPLICATE KEY UPDATE pickup_hash=VALUES(pickup_hash),drop_hash=VALUES(drop_hash),expires_at=VALUES(expires_at),pickup_attempts=0,drop_attempts=0',[$id,password_hash($pick,PASSWORD_DEFAULT),password_hash($drop,PASSWORD_DEFAULT)]);$db->commit();return ['pickup_otp'=>$pick,'drop_otp'=>$drop,'expires_in_hours'=>24];}catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
/** Called inside rider_progress's locked transaction before stock or order mutations. */
function order_safety_check(PDO $db,string $id,string $operation,array $body): void {
 $s=query($db,'SELECT *,expires_at>UTC_TIMESTAMP(6) AS fresh FROM mk_order_safety WHERE order_id=? FOR UPDATE',[$id])->fetch();if(!$s)return;
 if(!$s['fresh'])throw new ApiError(409,'OTP_EXPIRED','Ask the customer or support to review the delivery.');$prefix=$operation==='pickup'?'pickup':'drop';if((int)$s[$prefix.'_attempts']>=5)throw new ApiError(429,'OTP_LOCKED','Too many attempts. Contact support.');$code=$body['otp']??null;if(!is_string($code)||!preg_match('/^[0-9]{6}$/D',$code)||!password_verify($code,$s[$prefix.'_hash'])){query($db,$operation==='pickup'?'UPDATE mk_order_safety SET pickup_attempts=pickup_attempts+1 WHERE order_id=?':'UPDATE mk_order_safety SET drop_attempts=drop_attempts+1 WHERE order_id=?',[$id]);$db->commit();throw new ApiError(400,'INVALID_OTP','Check the code with the sender or customer.');}
 if($operation==='complete'){[$raw,$mime,$sha]=company_proof($body['proof_base64']??null);query($db,'UPDATE mk_order_safety SET proof_blob=?,proof_mime=?,proof_sha=? WHERE order_id=?',[$raw,$mime,$sha,$id]);}
}
function order_safety_proof(PDO $db,array $auth,string $id): array {require_roles($auth,['CUSTOMER']);$id=valid_uuid($id);require_ownership($db,$auth,'order',$id);$s=query($db,"SELECT s.proof_blob,s.proof_mime,s.proof_sha FROM mk_order_safety s JOIN mk_orders o ON o.id=s.order_id WHERE o.id=? AND o.customer_id=? AND o.status='DELIVERED' AND s.proof_blob IS NOT NULL",[$id,$auth['user_id']])->fetch();if(!$s)throw new ApiError(404,'NOT_FOUND','Delivery proof is not available yet.');return ['base64'=>base64_encode($s['proof_blob']),'mime'=>$s['proof_mime'],'sha256'=>$s['proof_sha']];}
