<?php
declare(strict_types=1);
namespace Maakit\Api;
/** Versioned defaults contain no credentials. Root config.php stays private and untouched. */
function settings(): array {
    $value=static function(string $key,$default=null) { $v=getenv($key);return $v!==false&&$v!==''?$v:(defined($key)?constant($key):$default); };
    $privateDir=$value('MAAKIT_PRIVATE_DIR',dirname(__DIR__,2).'/maakit-private');
    // cPanel File Manager setup: credentials stay outside public_html and survive code updates.
    // An explicit MAAKIT_CARRIER_CONFIG environment variable/private constant takes precedence.
    $carrierFile=$value('MAAKIT_CARRIER_CONFIG');
    if (!$carrierFile && is_file($privateDir.'/carriers.json')) $carrierFile=$privateDir.'/carriers.json';
    return ['site_url'=>$value('MAAKIT_SITE_URL','https://maakit.in'),
        'issuer'=>$value('MAAKIT_JWT_ISSUER','https://maakit.in'),'audience'=>$value('MAAKIT_JWT_AUDIENCE','maakit-web'),
        'private_dir'=>$privateDir,
        'key_file'=>$value('MAAKIT_JWT_KEY_FILE'),'private_key_file'=>$value('MAAKIT_JWT_PRIVATE_KEY_FILE'),
        'kid'=>$value('MAAKIT_JWT_KID','maakit-php-v1'),'bridge_enabled'=>(string)$value('MAAKIT_SESSION_BRIDGE','1')==='1',
        'dispatch_batch'=>20,'offer_seconds'=>60,'carrier_config'=>$carrierFile];
}
/** Never put key material in source control/web root. Existing files are never overwritten. */
function private_directory(string $dir): string {
    $root=realpath(dirname(__DIR__));$parent=realpath(dirname($dir));
    if (!$parent || !$root || $parent===$root || str_starts_with($parent,$root.DIRECTORY_SEPARATOR)) throw new \RuntimeException('Private directory must be outside web root');
    if (!is_dir($dir) && !@mkdir($dir,0700)) throw new \RuntimeException('Cannot provision private key directory');
    $real=realpath($dir);
    if (!$real || $real===$root || str_starts_with($real,$root.DIRECTORY_SEPARATOR)) throw new \RuntimeException('Unsafe private key directory');
    return $real;
}
function read_private_file(string $file): string {
    $real=realpath($file);$root=realpath(dirname(__DIR__));
    if (!$real || !$root || str_starts_with($real,$root.DIRECTORY_SEPARATOR) || !is_readable($real) || filesize($real)>65536) throw new \RuntimeException('Private configuration file unavailable');
    $contents=file_get_contents($real);if ($contents===false) throw new \RuntimeException('Cannot read private configuration');return $contents;
}
function jwt_material(bool $signing=false): array {
    $s=settings();
    if ($s['key_file']) {
        $keys=json_decode(read_private_file($s['key_file']),true,8,JSON_THROW_ON_ERROR);
        if (!is_array($keys)||!$keys) throw new \RuntimeException('Invalid public verification keys');
        $private=$signing?read_private_file((string)$s['private_key_file']):null;
    } else {
        $dir=private_directory($s['private_dir']);$file=$dir.'/jwt-rsa.pem';
        $lock=fopen($dir.'/jwt-key.lock','c');if (!$lock || !flock($lock,LOCK_EX)) throw new \RuntimeException('Key provisioning unavailable');
        try {
            if (!is_file($file)) {
                $old=umask(0077);
                try {
                    $key=openssl_pkey_new(['private_key_type'=>OPENSSL_KEYTYPE_RSA,'private_key_bits'=>2048]);
                    if (!$key || !openssl_pkey_export($key,$pem)) throw new \RuntimeException('Key generation failed');
                    $tmp=$dir.'/jwt-'.bin2hex(random_bytes(8)).'.tmp';
                    $handle=fopen($tmp,'x');if (!$handle) throw new \RuntimeException('Key storage failed');
                    try { if (fwrite($handle,$pem)!==strlen($pem) || !fflush($handle)) throw new \RuntimeException('Key write failed'); } finally { fclose($handle); }
                    if (!rename($tmp,$file)) { @unlink($tmp);throw new \RuntimeException('Key provisioning failed'); }
                } finally { umask($old); }
            }
            $pem=read_private_file($file);$key=openssl_pkey_get_private($pem);$details=$key?openssl_pkey_get_details($key):false;
            if (!$details || $details['type']!==OPENSSL_KEYTYPE_RSA || $details['bits']<2048) throw new \RuntimeException('Signing key invalid');
            $keys=[$s['kid']=>$details['key']];$private=$signing?$pem:null;
        } finally { flock($lock,LOCK_UN);fclose($lock); }
    }
    if (!preg_match('/^[A-Za-z0-9_-]{1,64}$/D',$s['kid'])) throw new \RuntimeException('Key identifier invalid');
    if ($signing) {
        $key=openssl_pkey_get_private($private);$details=$key?openssl_pkey_get_details($key):false;
        if (!$details || $details['type']!==OPENSSL_KEYTYPE_RSA || $details['bits']<2048 || !isset($keys[$s['kid']])) throw new \RuntimeException('Signing configuration invalid');
        $configured=openssl_pkey_get_public($keys[$s['kid']]);$expected=$configured?openssl_pkey_get_details($configured):false;
        if (!$expected || $expected['rsa']['n']!==$details['rsa']['n'] || $expected['rsa']['e']!==$details['rsa']['e']) throw new \RuntimeException('Signing and verification keys differ');
    }
    return ['keys'=>$keys,'private'=>$private,'kid'=>$s['kid'],'issuer'=>$s['issuer'],'audience'=>$s['audience']];
}
