<?php
declare(strict_types=1);
namespace Maakit\Api;
use PDO;
use RuntimeException;
final class ApiError extends RuntimeException {
    public int $status; public string $apiCode;
    public function __construct(int $status,string $code,string $message) { parent::__construct($message);$this->status=$status;$this->apiCode=$code; }
}
function uuid4(): string {
    $bytes=random_bytes(16);$bytes[6]=chr((ord($bytes[6])&15)|64);$bytes[8]=chr((ord($bytes[8])&63)|128);
    $h=bin2hex($bytes);return substr($h,0,8).'-'.substr($h,8,4).'-'.substr($h,12,4).'-'.substr($h,16,4).'-'.substr($h,20);
}
function valid_uuid($value,string $field='id'): string {
    if (!is_string($value) || !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/iD',$value)) throw new ApiError(400,'INVALID_ID',"Invalid $field.");
    return strtolower($value);
}
function query(PDO $db,string $sql,array $args=[]): \PDOStatement { $s=$db->prepare($sql);$s->execute($args);return $s; }
function json_data(array $data): string { return json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); }
function money($value): int {
    if (!is_string($value) && !is_int($value)) throw new ApiError(409,'INVALID_PRICE','Price needs shop confirmation.');
    $s=(string)$value;if (!preg_match('/^(0|[1-9][0-9]{0,12})$/D',$s) || strlen($s)>13 || (int)$s>1000000000000) throw new ApiError(409,'INVALID_PRICE','Price needs shop confirmation.');
    return (int)$s;
}
function sum_money(int $a,int $b): int { if ($a>1000000000000-$b) throw new ApiError(422,'ORDER_TOO_LARGE','Reduce your order quantity.');return $a+$b; }
