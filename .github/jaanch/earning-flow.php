<?php
require_once __DIR__ . '/../../config.php';
if (DB_NAME !== 'maakit_jaanch') throw new RuntimeException('Only the CI database is allowed');
require_once __DIR__ . '/../../inc/fn.php';
require_once __DIR__ . '/../../inc/earning.php';
function money_check($ok,$message) { if (!$ok) throw new RuntimeException($message); }
$jar=tempnam(sys_get_temp_dir(),'mk-money-'); $uid=0; $today=date('Y-m-d');
function money_request($path,$post=null,$status=200) {
    global $jar;
    $ch=curl_init('http://127.0.0.1:8099'.$path);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEJAR=>$jar,CURLOPT_COOKIEFILE=>$jar,CURLOPT_TIMEOUT=>20,CURLOPT_PROXY=>'']);
    if ($post !== null) curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($post)]);
    $html=curl_exec($ch);$code=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
    money_check($html !== false && $code===$status && !preg_match('/Fatal error|Warning:|Uncaught/',$html),'Money page failed: '.$path);
    return $html;
}
try {
    money_request('/admin/summary.php',null,302);
    $password=bin2hex(random_bytes(16));
    $pdo->prepare("INSERT INTO users(name,username,password,role) VALUES ('Money test','jaanch-money',?,'admin')")->execute([password_hash($password,PASSWORD_DEFAULT)]);
    $uid=(int)$pdo->lastInsertId();
    $html=money_request('/login.php?as=team');preg_match('/name="csrf" value="([^"]+)"/',$html,$m);
    money_request('/login.php?as=team',['csrf'=>$m[1],'do'=>'team','username'=>'jaanch-money','password'=>$password],302);
    $html=money_request('/admin/summary.php?lang=hi');
    money_check(strpos($html,'दिन का खर्च') !== false && strpos($html,'हिसाब अधूरा') !== false,'Admin gets costs and incomplete balance');
    preg_match('/name="csrf" value="([^"]+)"/',$html,$m);
    $post=['csrf'=>$m[1],'cost_date'=>$today,'fuel'=>'20','staff'=>'30','other'=>'10','note'=>'CI only'];
    money_request('/admin/summary.php',$post,302);
    $post['fuel']='25';money_request('/admin/summary.php',$post,302);
    $st=$pdo->prepare('SELECT * FROM operating_costs WHERE cost_date=?');$st->execute([$today]);$cost=$st->fetch();
    money_check((int)$cost['fuel']===25 && (int)$cost['staff']===30 && (int)$cost['updated_by']===$uid,'Cost corrections replace totals, keep owner');
    $post['fuel']='-1';money_request('/admin/summary.php',$post);
    $post['fuel']='999';$post['csrf']='wrong';money_request('/admin/summary.php',$post);
    $st->execute([$today]);money_check((int)$st->fetch()['fuel']===25,'Invalid or forged cost cannot be saved');
    money_request('/admin/dash.php');
    $days=earning_days($pdo,$today,$today);money_check($days[0]['expense']===65,'MariaDB cost report aggregates correctly');
    echo "Authenticated daily cost entry and reporting checks passed\n";
} finally {
    if ($uid) {
        $pdo->prepare('DELETE FROM operating_costs WHERE updated_by=?')->execute([$uid]);
        $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$uid]);
    }
    unlink($jar);
}
