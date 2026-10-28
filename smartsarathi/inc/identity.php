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
    if (mb_strlen($name)<2 || mb_strlen($name)>80 || !isset(sr_profile_vehicles()[$type]) || !preg_match('/^[A-Z0-9-]{6,30}$/D',$number)) throw new RuntimeException('Invalid profile');
    $pdo->beginTransaction();
    try {
        $pdo->prepare('SELECT id FROM sr_accounts WHERE id=? FOR UPDATE')->execute([$a['id']]);
        $p=sr_profile($pdo,(int)$a['id']);
        if ($p && $p['status']!=='rejected') throw new RuntimeException('Profile already submitted');
        $old=$p ? [$p['photo'],$p['licence_photo']] : [];
        $pdo->prepare("INSERT INTO sr_rider_profiles (account_id,name,vehicle_type,vehicle_number,photo,licence_photo) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name),vehicle_type=VALUES(vehicle_type),vehicle_number=VALUES(vehicle_number),photo=VALUES(photo),licence_photo=VALUES(licence_photo),status='pending',revision=revision+1,review_note=NULL,reviewed_by=NULL,reviewed_at=NULL")
            ->execute([$a['id'],$name,$type,$number,$photo,$licence]);
        $pdo->commit();
        foreach($old as $file) if(preg_match('/^[a-f0-9]{48}\.jpg$/D',$file)) @unlink(sr_identity_dir().'/'.$file);
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
            foreach (['photo','licence_photo'] as $field) {
                if (!preg_match('/^[a-f0-9]{48}\.jpg$/D', $p[$field]) || !is_file(sr_identity_dir().'/'.$p[$field])) throw new RuntimeException('Document missing');
            }
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
