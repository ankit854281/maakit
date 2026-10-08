<?php
declare(strict_types=1);
namespace Maakit\Api;
use PDO;
use PDOException;
use RuntimeException;
/** Existing config.php stays untouched; env overrides allow a separate API database. */
function database(array $env = []): PDO {
    $get = static function(string $name, ?string $legacy = null) use ($env): ?string {
        $v = $env[$name] ?? getenv($name);
        if ($v !== false && $v !== null && $v !== '') return (string)$v;
        return $legacy !== null && defined($legacy) ? (string)constant($legacy) : null;
    };
    if (!isset($env['MAAKIT_DB_NAME']) && !getenv('MAAKIT_DB_NAME') && is_file(dirname(__DIR__).'/config.php')) {
        require_once dirname(__DIR__).'/config.php';
    }
    $host = $get('MAAKIT_DB_HOST', 'DB_HOST') ?? 'localhost';
    $port = $get('MAAKIT_DB_PORT') ?? '3306';
    // Existing cPanel DB_HOST may use host:port.
    if (preg_match('/^([^:;]+):([0-9]{1,5})$/D', $host, $m)) { $host=$m[1]; $port=$m[2]; }
    $name=$get('MAAKIT_DB_NAME','DB_NAME'); $user=$get('MAAKIT_DB_USER','DB_USER'); $pass=$get('MAAKIT_DB_PASS','DB_PASS');
    if (!$name || !$user || $pass===null || !preg_match('/^[A-Za-z0-9_.-]+$/D',$host) || !preg_match('/^[A-Za-z0-9_.-]+$/D',$name) || !ctype_digit($port) || (int)$port<1 || (int)$port>65535) {
        throw new RuntimeException('Database configuration unavailable.');
    }
    $options=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_PERSISTENT=>false,PDO::ATTR_STRINGIFY_FETCHES=>true,
        PDO::ATTR_TIMEOUT=>5,PDO::MYSQL_ATTR_MULTI_STATEMENTS=>false];
    $ca=$get('MAAKIT_DB_SSL_CA');
    if (!in_array($host,['localhost','127.0.0.1'],true)) {
        if (!$ca || !is_readable($ca)) throw new RuntimeException('Remote database requires a verified CA.');
        $options[PDO::MYSQL_ATTR_SSL_CA]=$ca; $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT]=true;
    }
    try {
        $pdo=new PDO("mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4",$user,$pass,$options);
        $pdo->exec("SET time_zone='+00:00'"); $pdo->exec('SET SESSION innodb_lock_wait_timeout=3');
        $pdo->exec("SET SESSION sql_mode='STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
        return $pdo;
    } catch (PDOException $e) {
        // Do not return DSN, username, password or original DB exception to clients/logs.
        throw new RuntimeException('Database connection unavailable.');
    }
}
