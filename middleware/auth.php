<?php
declare(strict_types=1);
namespace Maakit\Api;
use PDO;
use Throwable;
require_once __DIR__.'/common.php';
require_once __DIR__.'/origin.php';
/** Strict, narrow RS256 verifier. No remote key discovery; no role claims trusted. */
function b64url(string $value): string {
    if ($value==='' || !preg_match('/^[A-Za-z0-9_-]+$/D',$value)) throw new ApiError(401,'UNAUTHENTICATED','Please sign in again.');
    $decoded=base64_decode(strtr($value,'-_','+/').str_repeat('=',(4-strlen($value)%4)%4),true);
    if ($decoded===false || rtrim(strtr(base64_encode($decoded),'+/','-_'),'=')!==$value) throw new ApiError(401,'UNAUTHENTICATED','Please sign in again.');
    return $decoded;
}
function jwt_claims(string $header,array $keys,string $issuer,string $audience,?int $clock=null): array {
    $now=$clock??time();
    if ($issuer==='' || $audience==='' || !$keys) throw new \RuntimeException('JWT verification configuration unavailable.');
    if (strlen($header)>8192 || !preg_match('/^Bearer ([A-Za-z0-9_-]+)\.([A-Za-z0-9_-]+)\.([A-Za-z0-9_-]+)$/D',$header,$m)) throw new ApiError(401,'UNAUTHENTICATED','A valid access token is required.');
    try {
        $h=json_decode(b64url($m[1]),true,8,JSON_THROW_ON_ERROR);$c=json_decode(b64url($m[2]),true,16,JSON_THROW_ON_ERROR);
        if (!is_array($h)||!is_array($c)||($h['alg']??null)!=='RS256'||($h['typ']??null)!=='JWT'||!is_string($h['kid']??null)
            || !preg_match('/^[A-Za-z0-9_-]{1,64}$/D',$h['kid']) || isset($h['jku'])||isset($h['x5u'])||isset($h['crit'])||!isset($keys[$h['kid']])) throw new \RuntimeException('Header rejected');
        $key=openssl_pkey_get_public($keys[$h['kid']]);$details=$key?openssl_pkey_get_details($key):false;
        if (!$details || $details['type']!==OPENSSL_KEYTYPE_RSA || $details['bits']<2048) throw new \RuntimeException('Key rejected');
        // Exactly 1 is valid; -1 / false are errors, NOT truthy success.
        if (openssl_verify($m[1].'.'.$m[2],b64url($m[3]),$key,OPENSSL_ALGO_SHA256)!==1) throw new \RuntimeException('Signature rejected');
        if (($c['iss']??null)!==$issuer || !(($c['aud']??null)===$audience || (is_array($c['aud']??null)&&in_array($audience,$c['aud'],true)))) throw new \RuntimeException('Audience rejected');
        foreach (['sub','sid','jti'] as $field) valid_uuid($c[$field]??null,$field);
        if (!is_int($c['iat']??null)||!is_int($c['exp']??null)||!is_int($c['session_version']??null)||$c['session_version']<0
            || $c['exp']<=$c['iat']||$c['exp']-$c['iat']>900||$c['iat']>$now+5||$c['exp']<=$now-5||$now-$c['iat']>905
            || (isset($c['nbf'])&&(!is_int($c['nbf'])||$c['nbf']>$now+5))) throw new \RuntimeException('Claims rejected');
        $c['sub']=strtolower($c['sub']);$c['sid']=strtolower($c['sid']);return $c;
    } catch (Throwable $e) { throw new ApiError(401,'UNAUTHENTICATED','Access token is invalid or expired.'); }
}
function authenticate(PDO $db,string $header,array $keys,string $issuer,string $audience): array {
    return live_identity($db,jwt_claims($header,$keys,$issuer,$audience));
}
function live_identity(PDO $db,array $claims,bool $lock=false): array {
    $user=query($db,"SELECT u.id,u.session_version FROM mk_users u JOIN mk_sessions s ON s.user_id=u.id
        WHERE u.id=? AND s.id=? AND u.status='ACTIVE' AND s.revoked_at IS NULL AND s.expires_at>UTC_TIMESTAMP(6)
        AND u.session_version=? AND s.session_version=u.session_version".($lock?' FOR UPDATE':''),[$claims['sub'],$claims['sid'],$claims['session_version']])->fetch();
    if (!$user) throw new ApiError(401,'SESSION_REVOKED','Please sign in again.');
    validate_legacy_origin($db,$claims['sid'],$lock);
    $roles=query($db,'SELECT r.code FROM mk_roles r JOIN mk_user_roles ur ON ur.role_id=r.id WHERE ur.user_id=?',[$user['id']])->fetchAll(PDO::FETCH_COLUMN);
    $permissions=query($db,'SELECT DISTINCT p.code FROM mk_permissions p WHERE p.id IN
        (SELECT rp.permission_id FROM mk_role_permissions rp JOIN mk_user_roles ur ON ur.role_id=rp.role_id WHERE ur.user_id=?
        UNION SELECT up.permission_id FROM mk_user_permissions up WHERE up.user_id=?)',[$user['id'],$user['id']])->fetchAll(PDO::FETCH_COLUMN);
    return ['user_id'=>$user['id'],'roles'=>$roles,'permissions'=>$permissions,'claims'=>['sub'=>$user['id'],'sid'=>$claims['sid'],'session_version'=>(int)$user['session_version']]];
}
function require_roles(array $auth,array $roles): void { if (!array_intersect($auth['roles'],$roles)) throw new ApiError(403,'FORBIDDEN','Your account cannot perform this action.'); }
function require_permission(array $auth,string $permission): void { if (!in_array($permission,$auth['permissions'],true)) throw new ApiError(403,'FORBIDDEN','Permission required.'); }
