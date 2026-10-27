<?php
declare(strict_types=1);
const AC_ROUND_POINTS=['gate','corridors','toilets','classes','discipline'];
function ac_round_checks($input): array {
    if(!is_array($input))throw new InvalidArgumentException('Complete the round checklist.');$out=[];
    foreach(AC_ROUND_POINTS as $point){$value=$input[$point]??'';if(!in_array($value,['ok','issue','not checked'],true))throw new InvalidArgumentException('Check each round item.');$out[$point]=$value;}return $out;
}
function ac_media_dir(): string {
    $base=(string)(getenv('SCHOOL_MEDIA_DIR')?:(defined('SCHOOL_MEDIA_DIR')?constant('SCHOOL_MEDIA_DIR'):dirname(__DIR__,2).'/school-private-media'));
    $dir=$base.'/academics';if(!is_dir($dir)&&!mkdir($dir,0700,true)&&!is_dir($dir))throw new RuntimeException('Private photo storage unavailable');
    $real=realpath($dir);$root=realpath(dirname(__DIR__));if(!$real||!$root||str_starts_with(strtolower(str_replace('\\','/',$real)).'/',strtolower(str_replace('\\','/',$root)).'/'))throw new RuntimeException('Round photos must be outside the website root');return $real;
}
function ac_round_photo($url): ?array {
    if($url===null||$url==='')return null;if(!is_string($url)||strlen($url)>1800000||!preg_match('#^data:image/(?:jpeg|png|webp);base64,([A-Za-z0-9+/=]+)$#',$url,$m))throw new InvalidArgumentException('Choose a small JPG, PNG or WebP round photo.');
    $raw=base64_decode($m[1],true);$size=$raw!==false?@getimagesizefromstring($raw):false;if(!$size||$size[0]>2400||$size[1]>2400)throw new InvalidArgumentException('Retake a clear photo without people or private records.');
    $img=@imagecreatefromstring($raw);if(!$img)throw new InvalidArgumentException('The photo could not be read.');$id=bin2hex(random_bytes(16));$path=ac_media_dir().'/'.$id.'.jpg';$ok=imagejpeg($img,$path,80);imagedestroy($img);if(!$ok)throw new RuntimeException('Photo could not be saved');chmod($path,0600);return ['id'=>$id,'file'=>$id.'.jpg','mime'=>'image/jpeg'];
}
