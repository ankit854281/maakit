<?php
// Runs only against disposable CI databases, never production.
require_once __DIR__.'/../../config.php';
if (DB_NAME!=='maakit_jaanch') throw new RuntimeException('Only CI database allowed');
require_once __DIR__.'/../../inc/db.php';
require_once __DIR__.'/../../inc/nakal-fn.php';
function backup_fingerprint(PDO $db) {
    $result=[];
    foreach($db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
        $ident='`'.str_replace('`','``',$table).'`';
        $rows=$db->query('SELECT * FROM '.$ident)->fetchAll(PDO::FETCH_ASSOC);
        // Fingerprint bytes losslessly, including BINARY hashes that are not UTF-8.
        $encoded=array_map(fn($row)=>json_encode(array_map(fn($value)=>is_string($value)?['bytes'=>base64_encode($value)]:$value,$row),JSON_THROW_ON_ERROR),$rows);
        sort($encoded,SORT_STRING);
        $result[$table]=['rows'=>count($rows),'hash'=>hash('sha256',implode("\n",$encoded))];
    }
    ksort($result);return $result;
}
if (($argv[1]??'')==='export') {
    $pdo->exec('CREATE TABLE ci_backup_fixture(id INT PRIMARY KEY, value TEXT NULL, amount INT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    $insert=$pdo->prepare('INSERT INTO ci_backup_fixture VALUES (?,?,?)');
    for($i=1;$i<=501;$i++) $insert->execute([$i,$i%2?"हिन्दी • quote ' slash \\ newline\nend":null,$i%3?-7:0]);
    $pdo->exec('CREATE TABLE ci_backup_binary(id INT PRIMARY KEY, hash VARBINARY(32) NOT NULL) ENGINE=InnoDB');
    $pdo->prepare('INSERT INTO ci_backup_binary VALUES (?,?)')->execute([1,hex2bin('00ff80fe27225c0a')]);
    $expected=backup_fingerprint($pdo);
    [$ok,$out]=nakal_banao($pdo,'/tmp/maakit-backup-ci');
    if(!$ok) throw new RuntimeException('CI backup failed');
    file_put_contents('/tmp/maakit-backup-expected.json',json_encode($expected,JSON_THROW_ON_ERROR));
    file_put_contents('/tmp/maakit-backup-path', '/tmp/maakit-backup-ci/'.$out['naam']);
    if(glob('/tmp/maakit-backup-ci/*.part')) throw new RuntimeException('Unfinished archive published');
} elseif (($argv[1]??'')==='verify') {
    $restore=new PDO('mysql:host='.DB_HOST.';dbname=maakit_restore_jaanch;charset=utf8mb4',DB_USER,DB_PASS,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $expected=json_decode(file_get_contents('/tmp/maakit-backup-expected.json'),true,512,JSON_THROW_ON_ERROR);
    try {
        if(backup_fingerprint($restore)!==$expected) throw new RuntimeException('Restored database differs from original');
        echo "Full backup restored: all table row counts and data hashes match, including 501 Unicode/NULL/quote fixtures\n";
    } finally { $pdo->exec('DROP TABLE ci_backup_fixture');$pdo->exec('DROP TABLE ci_backup_binary'); }
} else throw new RuntimeException('Choose export or verify');
