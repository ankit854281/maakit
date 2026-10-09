<?php
declare(strict_types=1);
namespace Maakit\Api;
use PDO;
require_once __DIR__.'/common.php';
/** Reload the legacy identity from DB; neither body fields nor JWT role claims select privileges. */
function legacy_snapshot(PDO $db,string $source,int $id,?int $tokenId=null,bool $lock=false): array {
    $suffix=$lock?' FOR UPDATE':'';
    if ($source==='CUSTOMER') {
        $row=query($db,"SELECT id,name,mobile,password,active FROM customers WHERE id=? AND active=1".$suffix,[$id])->fetch();
        if (!$row) throw new ApiError(401,'SESSION_REVOKED','Please sign in again.');
        return ['id'=>(int)$row['id'],'role'=>'CUSTOMER','name'=>$row['name'],'phone'=>$row['mobile'],'credential'=>hash('sha256','CUSTOMER:'.$id.':'.$row['password'])];
    }
    if ($source==='STAFF') {
        $row=query($db,'SELECT id,name,role,password FROM users WHERE id=? AND active=1'.$suffix,[$id])->fetch();
        $roles=['admin'=>'ADMIN','delivery'=>'RIDER','rider'=>'RIDER','vendor'=>'VENDOR','bpo'=>'BPO','designer'=>'DESIGNER'];
        if (!$row || !isset($roles[$row['role']])) throw new ApiError(401,'SESSION_REVOKED','Please sign in again.');
        return ['id'=>(int)$row['id'],'role'=>$roles[$row['role']],'name'=>$row['name'],'phone'=>null,'credential'=>hash('sha256','STAFF:'.$id.':'.$row['role'].':'.$row['password'])];
    }
    if ($source==='SHOP' && $tokenId!==null) {
        $oldZone=query($db,'SELECT @@session.time_zone')->fetchColumn();
        query($db,'SET SESSION time_zone=@@global.time_zone');
        try { $row=query($db,"SELECT b.id,b.name,b.access_code,t.id AS token_id,t.token FROM businesses b JOIN shop_tokens t ON t.business_id=b.id WHERE b.id=? AND t.id=? AND b.status='approved' AND t.expires>NOW()".$suffix,[$id,$tokenId])->fetch(); } finally { query($db,'SET SESSION time_zone=?',[$oldZone]); }
        if (!$row) throw new ApiError(401,'SESSION_REVOKED','Please sign in again.');
        return ['id'=>(int)$row['id'],'role'=>'VENDOR','name'=>$row['name'],'phone'=>null,'token_id'=>(int)$row['token_id'],
            'credential'=>hash('sha256','SHOP:'.$id.':'.$row['access_code'].':'.$row['token'])];
    }
    throw new ApiError(401,'UNAUTHENTICATED','Please sign in first.');
}
function validate_legacy_origin(PDO $db,string $sid,bool $lock=false): void {
    $origin=query($db,'SELECT o.*,i.source,i.legacy_id,i.role_code FROM mk_session_origins o JOIN mk_legacy_identities i ON i.id=o.identity_id WHERE o.session_id=?'.($lock?' FOR UPDATE':''),[$sid])->fetch();
    if (!$origin) return; // Native UUID sessions have their own live DB checks.
    $row=legacy_snapshot($db,$origin['source'],(int)$origin['legacy_id'],$origin['shop_token_id']===null?null:(int)$origin['shop_token_id'],$lock);
    if ($row['role']!==$origin['role_code'] || !hash_equals($origin['credential_hash'],$row['credential'])) throw new ApiError(401,'SESSION_REVOKED','Your login changed. Please sign in again.');
}
