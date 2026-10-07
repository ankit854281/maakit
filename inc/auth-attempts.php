<?php
// Shared DB windows survive new cookies. These are password-attempt limits,
// not mobile verification. Hashes avoid storing usernames/IPs in this log.
function auth_attempt_keys($identity) {
    return [hash('sha256',mb_strtolower(trim($identity))),hash('sha256',(string)($_SERVER['REMOTE_ADDR']??''))];
}
function auth_attempt_blocked(PDO $pdo,$scope,$identity) {
    [$who,$ip]=auth_attempt_keys($identity);$since=gmdate('Y-m-d H:i:s',time()-900);
    $q=$pdo->prepare('SELECT COUNT(*) FROM auth_failures WHERE scope=? AND identity_hash=? AND created_at>?');$q->execute([$scope,$who,$since]);
    if((int)$q->fetchColumn()>=6)return true;
    if(empty($_SERVER['REMOTE_ADDR']))return false;
    $q=$pdo->prepare('SELECT COUNT(*) FROM auth_failures WHERE scope=? AND ip_hash=? AND created_at>?');$q->execute([$scope,$ip,$since]);
    return (int)$q->fetchColumn()>=30;
}
function auth_attempt_failed(PDO $pdo,$scope,$identity) {
    [$who,$ip]=auth_attempt_keys($identity);
    $pdo->prepare('INSERT INTO auth_failures(scope,identity_hash,ip_hash,created_at) VALUES (?,?,?,?)')->execute([$scope,$who,$ip,gmdate('Y-m-d H:i:s')]);
    // Keep the log bounded; no credential or password is recorded.
    $pdo->prepare('DELETE FROM auth_failures WHERE created_at<?')->execute([gmdate('Y-m-d H:i:s',time()-86400)]);
}
function auth_attempt_error() {
    return t('Too many wrong attempts. Try again in 15 minutes, or call us.','बहुत बार ग़लत पासवर्ड। 15 मिनट बाद कोशिश कीजिए, या हमें कॉल कीजिए।');
}
