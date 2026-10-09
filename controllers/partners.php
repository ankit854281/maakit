<?php
// Partner credentials are created inactive; approval never claims an account by phone.
declare(strict_types=1);
namespace Maakit\Api;
use PDO;
require_once __DIR__.'/../middleware/legacy.php';
const DOCUMENT_LIMIT=5242880;
function document_metadata(array $f): array {
    if (!isset($f['error'],$f['tmp_name'],$f['size']) || !is_int($f['error']) || $f['error']!==UPLOAD_ERR_OK || !is_string($f['tmp_name']) || !is_int($f['size'])) throw new \InvalidArgumentException('Upload failed. Choose the file again.');
    $size=filesize($f['tmp_name']);
    if (!$size || $size>DOCUMENT_LIMIT || $size!==$f['size']) throw new \InvalidArgumentException('Each document must be no larger than 5 MB.');
    $mime=(new \finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    if (!in_array($mime,['image/jpeg','image/png','application/pdf'],true)) throw new \InvalidArgumentException('Use JPG, PNG or PDF documents.');
    if ($mime!=='application/pdf' && !@getimagesize($f['tmp_name'])) throw new \InvalidArgumentException('The image cannot be read. Choose another image.');
    if ($mime==='application/pdf' && file_get_contents($f['tmp_name'],false,null,0,5)!=='%PDF-') throw new \InvalidArgumentException('The PDF cannot be read. Choose another PDF.');
    return ['mime'=>$mime,'size_bytes'=>$size];
}
function document_directory(): string {
    $base=private_directory(settings()['private_dir']);$dir=$base.'/partner-documents';
    if (!is_dir($dir) && !mkdir($dir,0700)) throw new \RuntimeException('Document storage unavailable');
    if (is_link($dir) || realpath($dir)!==$dir) throw new \RuntimeException('Unsafe document storage');
    return $dir;
}
function receive_documents(string $kind,array $files): array {
    $saved=[];
    try {
        foreach (($kind==='VENDOR'?['gst_document'=>'GST','pan_document'=>'PAN']:['dl_document'=>'DL','rc_document'=>'RC']) as $field=>$type) {
            $f=$files[$field]??null;
            if ($f===null || (is_array($f)&&($f['error']??null)===UPLOAD_ERR_NO_FILE)) continue;
            if (!is_array($f) || !is_string($f['tmp_name']??null) || !is_uploaded_file($f['tmp_name'])) throw new \InvalidArgumentException('Choose a valid document.');
            $metadata=document_metadata($f);$key=bin2hex(random_bytes(32));$path=document_directory().'/'.$key;
            if (!move_uploaded_file($f['tmp_name'],$path)) throw new \RuntimeException('Document storage unavailable');
            $saved[]=$metadata+['kind'=>$type,'storage_key'=>$key];
            if (!chmod($path,0600)) throw new \RuntimeException('Cannot protect document');
        }
        return $saved;
    } catch (\Throwable $e) { remove_documents($saved);throw $e; }
}
function remove_documents(array $files): void {
    foreach ($files as $f) if (preg_match('/^[a-f0-9]{64}$/D',$f['storage_key'])) @unlink(document_directory().'/'.$f['storage_key']);
}
function review_partner(PDO $db,int $applicationId,string $decision,int $adminId): bool {
    if (!in_array($decision,['APPROVED','REJECTED'],true)) throw new \InvalidArgumentException('Invalid decision');
    $db->beginTransaction();
    try {
        $admin=query($db,"SELECT id FROM users WHERE id=? AND role='admin' AND active=1 FOR UPDATE",[$adminId])->fetch();
        if (!$admin) throw new ApiError(403,'FORBIDDEN','Admin login required.');
        $a=query($db,'SELECT * FROM mk_partner_applications WHERE id=? FOR UPDATE',[$applicationId])->fetch();
        if (!$a || $a['status']!=='PENDING') { $db->commit();return false; }
        if ($decision==='APPROVED') {
            if (!$a['login_user_id']) throw new \InvalidArgumentException('This older application has no login account. Ask the applicant to submit the new form.');
            $u=query($db,'SELECT * FROM users WHERE id=? FOR UPDATE',[$a['login_user_id']])->fetch();
            $role=strtolower($a['kind']);
            // Never elevate an existing unrelated/admin account or overwrite its credentials.
            if (!$u || $u['role']!==$role || (int)$u['active']!==0) throw new \InvalidArgumentException('The linked account needs manual review.');
            $types=query($db,'SELECT kind FROM mk_partner_documents WHERE application_id=?',[$a['id']])->fetchAll(PDO::FETCH_COLUMN);
            if ($a['kind']==='RIDER' && array_diff(['DL','RC'],$types)) throw new \InvalidArgumentException('Review both driving licence and RC documents before approval.');
            if ($a['kind']==='VENDOR' && $a['market']!=='B2C' && !array_intersect(['GST','PAN'],$types)) throw new \InvalidArgumentException('Review a GST or PAN document before B2B approval.');
            if (query($db,"SELECT id FROM mk_legacy_identities WHERE source='STAFF' AND legacy_id=?",[$u['id']])->fetch()) throw new \InvalidArgumentException('The account is already mapped. Please review it.');
            $uuid=uuid4();$roleId=query($db,'SELECT id FROM mk_roles WHERE code=?',[$a['kind']])->fetchColumn();
            if (!$roleId) throw new \RuntimeException('Role configuration missing');
            query($db,"INSERT INTO mk_users(id,display_name,kyc_status) VALUES(?,?,'VERIFIED')",[$uuid,$a['applicant_name']]);
            query($db,"INSERT INTO mk_legacy_identities(id,source,legacy_id,user_id,role_code) VALUES(?,'STAFF',?,?,?)",[uuid4(),$u['id'],$uuid,$a['kind']]);
            query($db,'INSERT INTO mk_user_roles(id,user_id,role_id) VALUES(?,?,?)',[uuid4(),$uuid,$roleId]);
            if ($a['kind']==='VENDOR') {
                $vendor=uuid4();query($db,"INSERT INTO mk_vendors(id,owner_user_id,legal_name,status) VALUES(?,?,?,'ACTIVE')",[$vendor,$uuid,$a['business_name']]);
                query($db,'INSERT INTO mk_vendor_members(vendor_id,user_id) VALUES(?,?)',[$vendor,$uuid]);
            } else query($db,"INSERT INTO mk_riders(id,user_id,kyc_status,active,vehicle_type) VALUES(?,?,'VERIFIED',1,?)",[uuid4(),$uuid,strtoupper($a['vehicle_type'])]);
            query($db,'UPDATE users SET role=?,active=1 WHERE id=?',[$role,$u['id']]);
        }
        query($db,"UPDATE mk_partner_applications SET status=?,reviewed_by=?,reviewed_at=NOW() WHERE id=? AND status='PENDING'",[$decision,$adminId,$applicationId]);
        $db->commit();return true;
    } catch (\Throwable $e) { if ($db->inTransaction()) $db->rollBack();throw $e; }
}
