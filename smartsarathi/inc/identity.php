<?php
require_once __DIR__.'/firebase.php';
function sr_account(PDO $pdo): ?array {
    if (!sr_firebase_enabled() || empty($_SESSION['sr_account']) || (int)($_SESSION['sr_auth_until'] ?? 0) <= time()) return null;
    $q = $pdo->prepare('SELECT * FROM sr_accounts WHERE id=? AND active=1');
    $q->execute([(int)$_SESSION['sr_account']]); $a = $q->fetch();
    return $a && (int)$a['session_version'] === (int)($_SESSION['sr_account_version'] ?? 0) ? $a : null;
}
function sr_account_require(PDO $pdo): array {
    $a = sr_account($pdo); if (!$a) redirect('/'); return $a;
}
function sr_identity_admin(PDO $pdo): bool {
    $a = sr_account($pdo);
    $mobiles = defined('SR_FIREBASE_ADMIN_MOBILES') ? SR_FIREBASE_ADMIN_MOBILES : [];
    return $a && is_array($mobiles) && in_array($a['mobile'], $mobiles, true);
}
function sr_profile(PDO $pdo, int $id): ?array {
    $q=$pdo->prepare('SELECT * FROM sr_rider_profiles WHERE account_id=?'); $q->execute([$id]); return $q->fetch() ?: null;
}
function sr_identity_signin(PDO $pdo, array $identity): void {
    // UID and phone are BOTH immutable identities. Never merge a recycled phone/changed UID silently.
    $pdo->beginTransaction();
    try {
        $q=$pdo->prepare('SELECT * FROM sr_accounts WHERE firebase_uid=? OR mobile=? FOR UPDATE');
        $q->execute([$identity['uid'], $identity['mobile']]); $rows=$q->fetchAll();
        if (!$rows) {
            $pdo->prepare('INSERT INTO sr_accounts (firebase_uid,mobile) VALUES (?,?)')->execute([$identity['uid'],$identity['mobile']]);
            $id=(int)$pdo->lastInsertId(); $version=1;
        } else {
            if (count($rows)!==1 || $rows[0]['firebase_uid']!==$identity['uid'] || $rows[0]['mobile']!==$identity['mobile'] || !(int)$rows[0]['active']) throw new RuntimeException('Identity conflict or disabled account');
            $id=(int)$rows[0]['id']; $version=(int)$rows[0]['session_version'];
        }
        $pdo->commit();
    } catch (Throwable $e) { if($pdo->inTransaction())$pdo->rollBack(); throw $e; }
    // Drop legacy admin/rider sessions; mode never grants authority.
    $lang=$_SESSION['sr_lang'] ?? 'hi';
    $_SESSION=[]; session_regenerate_id(true); $_SESSION['sr_lang']=$lang;
    $_SESSION['sr_account']=$id; $_SESSION['sr_account_version']=$version;
    $_SESSION['sr_auth_until']=time()+12*3600; $_SESSION['sr_mode']='customer';
}
function sr_identity_rider(PDO $pdo): ?array {
    $a=sr_account($pdo);
    if (!$a || ($_SESSION['sr_mode'] ?? '')!=='rider') return null;
    $q=$pdo->prepare("SELECT r.* FROM sr_rider_profiles p JOIN riders r ON r.id=p.rider_id WHERE p.account_id=? AND p.status='approved' AND r.active=1");
    $q->execute([$a['id']]); return $q->fetch() ?: null;
}
/**
 * Owner rule (29 Oct 2026): service people get entry FIRST, profile later.
 * If the admin already added this mobile in the admin panel (riders table),
 * the verified OTP login links to that rider and is approved straight away.
 * Photos/licence can be added later from the account page.
 */
function sr_identity_autolink(PDO $pdo, array $a): void {
    if (sr_profile($pdo,(int)$a['id'])) return;
    $q=$pdo->prepare('SELECT * FROM riders WHERE RIGHT(mobile,10)=? AND active=1 LIMIT 1');
    $q->execute([$a['mobile']]); $r=$q->fetch();
    if(!$r) return;
    $type=$r['vtype'] ?? (function_exists('sr_vtype_guess') ? sr_vtype_guess($r['vehicle'] ?? '') : null);
    $type=$type ?: 'bike';
    if(!isset(sr_profile_vehicles()[$type])) $type='bike';
    $pdo->beginTransaction();
    try {
        $pdo->prepare('SELECT id FROM sr_accounts WHERE id=? FOR UPDATE')->execute([$a['id']]);
        if(!sr_profile($pdo,(int)$a['id'])) {
            $taken=$pdo->prepare('SELECT 1 FROM sr_rider_profiles WHERE rider_id=?'); $taken->execute([(int)$r['id']]);
            if(!$taken->fetchColumn()) {
                $pdo->prepare("INSERT INTO sr_rider_profiles (account_id,rider_id,name,vehicle_type,vehicle_number,photo,licence_photo,status,reviewed_by,reviewed_at) VALUES (?,?,?,?,?,'','','approved',?,NOW())")
                    ->execute([$a['id'],(int)$r['id'],$r['name'],$type,(string)($r['rc_number'] ?? ''),$a['id']]);
                $pdo->prepare("INSERT INTO sr_identity_audit (account_id,actor_id,action,revision,note) VALUES (?,?,'auto_linked',1,'Pre-added by admin in riders list')")->execute([$a['id'],$a['id']]);
            }
        }
        $pdo->commit();
    } catch(Throwable $e) { if($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
}
function sr_profile_docs_missing(?array $p): bool {
    return $p && (!preg_match('/^[a-f0-9]{48}\.jpg$/D',(string)$p['photo']) || !preg_match('/^[a-f0-9]{48}\.jpg$/D',(string)$p['licence_photo']));
}
/** Add / replace photo and licence later without changing approval status. */
function sr_profile_add_docs(PDO $pdo, array $a, ?string $photo, ?string $licence): void {
    if(!$photo && !$licence) return;
    $pdo->beginTransaction();
    try {
        $q=$pdo->prepare('SELECT * FROM sr_rider_profiles WHERE account_id=? FOR UPDATE');
        $q->execute([$a['id']]); $p=$q->fetch();
        if(!$p) throw new RuntimeException('No profile');
        $pdo->prepare('UPDATE sr_rider_profiles SET photo=COALESCE(?,photo),licence_photo=COALESCE(?,licence_photo) WHERE account_id=?')->execute([$photo,$licence,$a['id']]);
        $pdo->commit();
    } catch(Throwable $e) { if($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
    // Cleanup after commit must never turn a successful save into an upload rollback.
    sr_profile_remove_old(array_filter([$photo ? $p['photo'] : null,$licence ? $p['licence_photo'] : null]));
}
function sr_profile_remove_old(array $files): void {
    foreach($files as $file) if(preg_match('/^[a-f0-9]{48}\.jpg$/D',(string)$file)) {
        try { @unlink(sr_identity_dir().'/'.$file); }
        catch(Throwable $e) { sr_identity_log('photo_cleanup',$e); }
    }
}
/** Diagnostic codes only: no phone, token, SQL text or document path in logs. */
function sr_identity_log(string $action, Throwable $e): void {
    error_log('Smart Sarathi '.$action.': '.get_class($e).' code='.preg_replace('/[^A-Za-z0-9_-]/','',(string)$e->getCode()));
}

function sr_identity_rider_allowed(PDO $pdo, int $rid): bool {
    if (!sr_firebase_enabled()) return true;
    $q=$pdo->prepare("SELECT 1 FROM sr_rider_profiles p JOIN sr_accounts a ON a.id=p.account_id JOIN riders r ON r.id=p.rider_id WHERE p.rider_id=? AND p.status='approved' AND a.active=1 AND r.active=1");
    $q->execute([$rid]); return (bool)$q->fetchColumn();
}
function sr_profile_vehicles(): array {
    return ['bike'=>t('Motorcycle / scooter','बाइक / स्कूटी'), 'ev'=>t('Electric scooter','इलेक्ट्रिक स्कूटी'),
        'auto'=>t('Auto','ऑटो'), 'erick'=>t('E-rickshaw','ई-रिक्शा'), 'car'=>t('Car','कार')];
}
function sr_profile_photo(string $field): string {
    $f=$_FILES[$field] ?? [];
    if (($f['error'] ?? -1)!==UPLOAD_ERR_OK || ($f['size'] ?? 0)>5*1024*1024 || !is_uploaded_file($f['tmp_name'])) throw new RuntimeException('Upload failed');
    $info=@getimagesize($f['tmp_name']);
    if (!$info || !in_array($info['mime'],['image/jpeg','image/png','image/webp'],true) || $info[0]*$info[1]>20000000) throw new RuntimeException('Invalid image');
    $src=@imagecreatefromstring(file_get_contents($f['tmp_name']));
    if (!$src) throw new RuntimeException('Invalid image');
    $scale=min(1,1600/max($info[0],$info[1])); $w=max(1,(int)($info[0]*$scale)); $h=max(1,(int)($info[1]*$scale));
    $dst=imagecreatetruecolor($w,$h); imagefill($dst,0,0,imagecolorallocate($dst,255,255,255));
    imagecopyresampled($dst,$src,0,0,0,0,$w,$h,$info[0],$info[1]);
    $name=bin2hex(random_bytes(24)).'.jpg'; $path=sr_identity_dir().'/'.$name;
    $ok=imagejpeg($dst,$path,85); imagedestroy($src); imagedestroy($dst);
    if (!$ok) throw new RuntimeException('Image storage failed'); chmod($path,0600); return $name;
}
function sr_profile_submit(PDO $pdo, array $a, array $input, string $photo, string $licence): void {
    $name=trim((string)($input['name'] ?? '')); $type=(string)($input['vehicle_type'] ?? '');
    $number=strtoupper(preg_replace('/\s+/', '', (string)($input['vehicle_number'] ?? '')));
    if (mb_strlen($name)<2 || mb_strlen($name)>80 || !isset(sr_profile_vehicles()[$type]) || !preg_match('/^[A-Z0-9-]{0,30}$/D',$number)) throw new RuntimeException('Invalid profile');
    $pdo->beginTransaction();
    try {
        $pdo->prepare('SELECT id FROM sr_accounts WHERE id=? FOR UPDATE')->execute([$a['id']]);
        $p=sr_profile($pdo,(int)$a['id']);
        if ($p && $p['status']!=='rejected') throw new RuntimeException('Profile already submitted');
        $old=$p ? [$p['photo'],$p['licence_photo']] : [];
        $pdo->prepare("INSERT INTO sr_rider_profiles (account_id,name,vehicle_type,vehicle_number,photo,licence_photo) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name),vehicle_type=VALUES(vehicle_type),vehicle_number=VALUES(vehicle_number),photo=VALUES(photo),licence_photo=VALUES(licence_photo),status='pending',revision=revision+1,review_note=NULL,reviewed_by=NULL,reviewed_at=NULL")
            ->execute([$a['id'],$name,$type,$number,$photo,$licence]);
        $pdo->commit();
        sr_profile_remove_old($old);
    } catch(Throwable $e) {if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
function sr_profile_review(PDO $pdo, int $id, int $revision, string $decision, string $note): void {
    if (!sr_identity_admin($pdo) || !in_array($decision,['approved','rejected','suspended'],true)) throw new RuntimeException('Forbidden');
    $actor=sr_account($pdo); $note=mb_substr(trim($note),0,300);
    if ($decision!=='approved' && $note==='') throw new RuntimeException('Reason required');
    sr_jobs_upgrade($pdo);
    $pdo->beginTransaction();
    try {
        $q=$pdo->prepare('SELECT p.*,a.mobile,a.active AS account_active FROM sr_rider_profiles p JOIN sr_accounts a ON a.id=p.account_id WHERE p.account_id=? FOR UPDATE');$q->execute([$id]);$p=$q->fetch();
        if(!$p || !(int)$p['account_active'] || (int)$p['revision']!==$revision || ($decision==='suspended' ? $p['status']!=='approved' : $p['status']!=='pending')) throw new RuntimeException('Review changed');
        $rid=$p['rider_id'];
        if($decision==='approved') {
            // Owner rule: photos/licence are optional at approval; rider can add them later.
            $q=$pdo->prepare('SELECT * FROM riders WHERE mobile=? FOR UPDATE');$q->execute([$p['mobile']]);$r=$q->fetch();
            if($r && !(int)$r['active']) throw new RuntimeException('Rider disabled: resolve with office first');
            if($r) $rid=(int)$r['id'];
            else {
                $pdo->prepare('INSERT INTO riders (name,mobile,pass_hash) VALUES (?,?,?)')->execute([$p['name'],$p['mobile'],password_hash(bin2hex(random_bytes(32)),PASSWORD_DEFAULT)]);
                $rid=(int)$pdo->lastInsertId();
            }
            // Preserve rates, jobs and earnings. Approval grants both work categories.
            $pdo->prepare('UPDATE riders SET name=?,vehicle=?,rc_number=?,vtype=?,ride_ok=1,do_ride=1,do_delivery=1,ready_until=NULL WHERE id=?')->execute([$p['name'],$p['vehicle_type'],$p['vehicle_number'],$p['vehicle_type'],$rid]);
        }
        if($decision==='suspended' && $rid) $pdo->prepare('UPDATE riders SET ready_until=NULL WHERE id=?')->execute([$rid]);
        $pdo->prepare('UPDATE sr_rider_profiles SET status=?,rider_id=?,review_note=?,reviewed_by=?,reviewed_at=NOW(),revision=revision+1 WHERE account_id=?')->execute([$decision,$rid,$note?:null,$actor['id'],$id]);
        $pdo->prepare('INSERT INTO sr_identity_audit (account_id,actor_id,action,revision,note) VALUES (?,?,?,?,?)')->execute([$id,$actor['id'],$decision,$revision,$note?:null]);
        $pdo->commit();
    } catch(Throwable $e) {if($pdo->inTransaction())$pdo->rollBack();throw $e;}
}
function sr_profile_status(string $status): string {
    return ['pending'=>t('Awaiting approval','मंज़ूरी बाकी'), 'approved'=>t('Approved','मंज़ूर'),
        'rejected'=>t('Changes required','सुधार चाहिए'), 'suspended'=>t('Suspended — contact office','रोक लगाई गई — दफ़्तर से बात कीजिए')][$status] ?? '';
}
