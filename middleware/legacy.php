<?php
declare(strict_types=1);
namespace Maakit\Api;
use PDO;
use Throwable;
require_once __DIR__.'/auth.php';
require_once __DIR__.'/../config/config.php';
/** Trusted server-side PHP session/cookie only. Guest phone numbers never claim accounts/history. */
function legacy_context(PDO $db,string $context): array {
    if (session_status()!==PHP_SESSION_ACTIVE || session_id()==='') throw new ApiError(401,'UNAUTHENTICATED','Please sign in first.');
    if ($context==='customer' && isset($_SESSION['cust']['id'])) return ['source'=>'CUSTOMER','id'=>(int)$_SESSION['cust']['id'],'token_id'=>null];
    if ($context==='staff' && isset($_SESSION['user']['id'])) return ['source'=>'STAFF','id'=>(int)$_SESSION['user']['id'],'token_id'=>null];
    if ($context==='shop' && is_string($_COOKIE['mk_shop']??null) && preg_match('/^[a-f0-9]{48}$/D',$_COOKIE['mk_shop'])) {
        $row=query($db,"SELECT t.id,t.business_id FROM shop_tokens t JOIN businesses b ON b.id=t.business_id WHERE t.token=? AND t.expires>NOW() AND b.status='approved'",[$_COOKIE['mk_shop']])->fetch();
        if ($row) return ['source'=>'SHOP','id'=>(int)$row['business_id'],'token_id'=>(int)$row['id']];
    }
    throw new ApiError(401,'UNAUTHENTICATED','Please sign in first.');
}
function bridge_session(PDO $db,string $context): array {
    if (!settings()['bridge_enabled']) throw new ApiError(503,'BRIDGE_DISABLED','Session integration is disabled.');
    $legacy=legacy_context($db,$context);$material=jwt_material(true);$binding=hash('sha256',session_id());
    $db->beginTransaction();
    try {
        // Existing identities lock the UUID user before legacy credentials, matching live_identity().
        $known=query($db,'SELECT user_id FROM mk_legacy_identities WHERE source=? AND legacy_id=?',[$legacy['source'],$legacy['id']])->fetch();
        if ($known) query($db,'SELECT id FROM mk_users WHERE id=? FOR UPDATE',[$known['user_id']]);
        $row=legacy_snapshot($db,$legacy['source'],$legacy['id'],$legacy['token_id'],true);
        $proof=$_SESSION['maakit_login_proof'][$context]??null;
        if (is_string($proof) && !hash_equals($proof,$row['credential'])) throw new ApiError(401,'SESSION_REVOKED','Your login changed. Please sign in again.');
        $identity=query($db,'SELECT * FROM mk_legacy_identities WHERE source=? AND legacy_id=? FOR UPDATE',[$legacy['source'],$legacy['id']])->fetch();
        if (!$identity) {
            $user=uuid4();$identityId=uuid4();$phone=null;
            if ($legacy['source']==='CUSTOMER') {
                $digits=preg_replace('/\D/','',(string)$row['phone']);
                if (preg_match('/^[6-9][0-9]{9}$/D',$digits)) {
                    $candidate='+91'.$digits;
                    // An existing phone does not prove that two distinct legacy identities are the same user.
                    if (!query($db,'SELECT id FROM mk_users WHERE phone_e164=?',[$candidate])->fetch()) $phone=$candidate;
                }
            }
            query($db,'INSERT INTO mk_users(id,phone_e164,display_name) VALUES(?,?,?)',[$user,$phone,$row['name']]);
            query($db,'INSERT INTO mk_legacy_identities(id,source,legacy_id,user_id,role_code) VALUES(?,?,?,?,?)',[$identityId,$legacy['source'],$legacy['id'],$user,$row['role']]);
            $identity=['id'=>$identityId,'user_id'=>$user,'role_code'=>$row['role']];
            $roleId=query($db,'SELECT id FROM mk_roles WHERE code=?',[$row['role']])->fetchColumn();
            if (!$roleId) throw new \RuntimeException('Legacy role configuration missing');
            query($db,'INSERT INTO mk_user_roles(id,user_id,role_id) VALUES(?,?,?)',[uuid4(),$user,$roleId]);
            if ($row['role']==='ADMIN') {
                // Explicit grant only to a DB-verified legacy admin. No blanket FINANCE/ADMIN bypass.
                $permission=query($db,"SELECT id FROM mk_permissions WHERE code='DISPATCH_WRITE'")->fetchColumn();
                query($db,'INSERT INTO mk_user_permissions(id,user_id,permission_id) VALUES(?,?,?)',[uuid4(),$user,$permission]);
            }
        }
        if ($identity['role_code']!==$row['role']) throw new ApiError(403,'ROLE_MAPPING_CHANGED','An administrator must review the account’s changed role.');
        $user=query($db,"SELECT id,session_version FROM mk_users WHERE id=? AND status='ACTIVE' FOR UPDATE",[$identity['user_id']])->fetch();
        if (!$user) throw new ApiError(401,'SESSION_REVOKED','Please sign in again.');
        $cached=$_SESSION['maakit_api'][$context]??null;$sid=null;
        if (is_array($cached) && is_string($cached['sid']??null)) {
            $existing=query($db,"SELECT s.id FROM mk_sessions s JOIN mk_session_origins o ON o.session_id=s.id WHERE s.id=? AND s.user_id=? AND s.revoked_at IS NULL AND s.expires_at>DATE_ADD(UTC_TIMESTAMP(6),INTERVAL 15 MINUTE) AND s.session_version=? AND o.php_session_hash=? AND o.identity_id=? AND o.credential_hash=? FOR UPDATE",[$cached['sid'],$user['id'],$user['session_version'],$binding,$identity['id'],$row['credential']])->fetch();
            if ($existing) $sid=$existing['id'];
            else query($db,'UPDATE mk_sessions s JOIN mk_session_origins o ON o.session_id=s.id SET s.revoked_at=UTC_TIMESTAMP(6) WHERE s.id=? AND s.user_id=?',[$cached['sid'],$cached['user_id']??$user['id']]);
        }
        if ($sid===null) {
            $sid=uuid4();
            query($db,'INSERT INTO mk_sessions(id,user_id,refresh_hash,session_version,expires_at) VALUES(?,?,?,?,DATE_ADD(UTC_TIMESTAMP(6),INTERVAL 30 MINUTE))',[$sid,$user['id'],random_bytes(32),$user['session_version']]);
            query($db,'INSERT INTO mk_session_origins(id,session_id,identity_id,credential_hash,php_session_hash,shop_token_id) VALUES(?,?,?,?,?,?)',[uuid4(),$sid,$identity['id'],$row['credential'],$binding,$legacy['token_id']]);
        }
        $claims=['iss'=>$material['issuer'],'aud'=>$material['audience'],'sub'=>$user['id'],'sid'=>$sid,'jti'=>uuid4(),'iat'=>time(),'exp'=>time()+900,'session_version'=>(int)$user['session_version']];
        $encode=static fn(string $s)=>rtrim(strtr(base64_encode($s),'+/','-_'),'=');
        $header=$encode(json_data(['alg'=>'RS256','typ'=>'JWT','kid'=>$material['kid']]));$payload=$encode(json_data($claims));
        if (!openssl_sign($header.'.'.$payload,$signature,$material['private'],OPENSSL_ALGO_SHA256)) throw new \RuntimeException('Signing unavailable');
        $db->commit();$_SESSION['maakit_login_proof'][$context]=$row['credential'];$_SESSION['maakit_api'][$context]=['sid'=>$sid,'user_id'=>$user['id']];
        return ['access_token'=>$header.'.'.$payload.'.'.$encode($signature),'token_type'=>'Bearer','expires_in'=>900,'user_id'=>$user['id'],'context'=>$context];
    } catch (Throwable $e) { if ($db->inTransaction()) $db->rollBack();throw $e; }
}
function revoke_legacy_sessions(PDO $db,?string $context=null): void {
    if (!headers_sent()) foreach ($context===null?['customer','staff','shop']:[$context] as $cookieContext) setcookie('mk_jwt_'.$cookieContext,'',['expires'=>time()-3600,'path'=>'/api/v1/','secure'=>!(in_array($_SERVER['SERVER_NAME']??'', ['localhost','127.0.0.1'],true) && (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS']==='off')),'httponly'=>true,'samesite'=>'Strict']);
    if ($context===null) unset($_SESSION['maakit_login_proof']);else unset($_SESSION['maakit_login_proof'][$context]);
    $cache=$_SESSION['maakit_api']??[];
    foreach ($cache as $key=>$value) {
        if ($context!==null && $context!==$key) continue;
        if (is_array($value) && is_string($value['sid']??null)) query($db,'UPDATE mk_sessions s JOIN mk_session_origins o ON o.session_id=s.id SET s.revoked_at=UTC_TIMESTAMP(6) WHERE s.id=? AND s.user_id=?',[$value['sid'],$value['user_id']??'']);
        unset($_SESSION['maakit_api'][$key]);
    }
}
