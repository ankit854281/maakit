<?php
require_once __DIR__.'/inc/public.php';
require_once __DIR__.'/inc/booking.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
function sr_chat_json(array $data,int $status=200): void { http_response_code($status); echo json_encode($data,JSON_UNESCAPED_UNICODE); exit; }
try {
    $a=sr_account($pdo); if(!$a) sr_chat_json(['ok'=>false],401);
    $no=(string)($_GET['no'] ?? $_POST['no'] ?? '');
    $r=sr_identity_rider($pdo);
    $j=sr_my_job($pdo,$a,$no);
    if(!$j && $r) { $q=$pdo->prepare('SELECT * FROM jobs WHERE job_no=? AND rider_id=?'); $q->execute([$no,$r['id']]); $j=$q->fetch(); }
    if(!$j || !in_array($j['status'],['assigned','picked'],true) || empty($j['rider_id'])) sr_chat_json(['ok'=>false,'error'=>t('Chat is available during an accepted trip.','चैट स्वीकार किए गए सफ़र के दौरान मिलती है।')],403);
    $pdo->exec("CREATE TABLE IF NOT EXISTS sr_job_messages (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,job_id INT NOT NULL,account_id BIGINT UNSIGNED NOT NULL,body VARCHAR(1000) NOT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,KEY(job_id,id),KEY(account_id,created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    if($_SERVER['REQUEST_METHOD']==='POST') {
        if(!csrf_ok()) sr_chat_json(['ok'=>false],403);
        $q=$pdo->prepare('SELECT COUNT(*) FROM sr_job_messages WHERE account_id=? AND created_at>NOW()-INTERVAL 1 HOUR'); $q->execute([$a['id']]);
        if((int)$q->fetchColumn()>=120) sr_chat_json(['ok'=>false],429);
        $body=trim((string)post('body'));
        if($body==='' || mb_strlen($body)>1000) sr_chat_json(['ok'=>false],422);
        $pdo->beginTransaction();
        // Serialize against completion/cancellation/reassignment before accepting a message.
        $q=$pdo->prepare('SELECT status,rider_id FROM jobs WHERE id=? FOR UPDATE'); $q->execute([$j['id']]); $current=$q->fetch();
        if(!$current || !in_array($current['status'],['assigned','picked'],true) || (int)$current['rider_id']!==(int)$j['rider_id']) { $pdo->rollBack(); sr_chat_json(['ok'=>false],409); }
        $pdo->prepare('INSERT INTO sr_job_messages(job_id,account_id,body) VALUES(?,?,?)')->execute([$j['id'],$a['id'],$body]);
        $pdo->commit();
    } elseif($_SERVER['REQUEST_METHOD']!=='GET') sr_chat_json(['ok'=>false],405);
    $q=$pdo->prepare('SELECT id,account_id,body,created_at FROM sr_job_messages WHERE job_id=? ORDER BY id DESC LIMIT 60'); $q->execute([$j['id']]);
    sr_chat_json(['ok'=>true,'messages'=>array_map(fn($m)=>['id'=>(int)$m['id'],'mine'=>(int)$m['account_id']===(int)$a['id'],'body'=>$m['body'],'at'=>$m['created_at']],array_reverse($q->fetchAll()))]);
} catch(Throwable $e) { if($pdo->inTransaction()) $pdo->rollBack(); error_log('Sarathi chat: '.get_class($e)); sr_chat_json(['ok'=>false],500); }
