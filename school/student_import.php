<?php
// ERP student import: source IDs are authoritative; admission numbers are not unique.
// This helper never imports teacher/fee/password data and never overwrites an existing row.
declare(strict_types=1);
function sch_import_session(string $s): string {
    $s=str_replace(['–','—'],'-',trim($s));
    return preg_replace('/^(20[0-9]{2})-20([0-9]{2})$/','$1-$2',$s);
}
function sch_student_import_rows(array $in, string $active): array {
    if (($in['format'] ?? '') !== 'snmishra-students-v1') throw new InvalidArgumentException('Choose the verified student import JSON file.');
    $session = (string)($in['session'] ?? '');
    if (!preg_match('/^20\d{2}-\d{2}$/', $session) || sch_import_session($active) !== $session) throw new InvalidArgumentException('The file session must match the school session in Settings. No records were changed.');
    $rows = $in['students'] ?? null;
    if (!is_array($rows) || !array_is_list($rows) || !$rows || count($rows) > 1000) throw new InvalidArgumentException('Import must contain 1 to 1000 student records.');
    $out=[]; $ids=[];
    $classes=['PG','LKG','UKG','1st','2nd','3rd','4th','5th','6th','7th','8th','9th','10th'];
    foreach ($rows as $index=>$s) {
        if (!is_array($s)) throw new InvalidArgumentException('Invalid student row ' . ($index+1));
        $id=(string)($s['erpId'] ?? '');
        if (!preg_match('/^[1-9][0-9]{0,11}$/',$id) || isset($ids[$id])) throw new InvalidArgumentException('Missing or repeated ERP Student ID. No records were changed.');
        $ids[$id]=true;
        if (!isset($s['name'],$s['cls'],$s['adm']) || !is_string($s['name']) || trim($s['name'])==='' || !in_array($s['cls'],$classes,true)) throw new InvalidArgumentException('Check name, class and admission number in row '.($index+1));
        $d=['id'=>'erp-snmishra-'.$id,'erpId'=>$id,'session'=>$session,'source'=>'schoolerpindia.net/school/snmishra','sample'=>false];
        foreach (['adm','name','cls','father','mother','phone','address','dob','childId','section','facilities'] as $field) {
            $value=$s[$field] ?? '';
            if (!is_string($value) || strlen($value)>2000) throw new InvalidArgumentException('Invalid student detail in row '.($index+1));
            $d[$field]=$value;
        }
        if (!empty($s['photoReference'])) {
            if (!is_string($s['photoReference']) || strlen($s['photoReference'])>500) throw new InvalidArgumentException('Invalid photo reference');
            $d['photoReference']=$s['photoReference']; // Reference only; no claim that the image is imported.
        }
        $out[]=$d;
    }
    return $out;
}
function sch_student_import_plan(array $rows, array $existing): array {
    $byId=[];foreach($existing as $e)$byId[$e['r']]=$e;
    $new=[];$same=[];$conflicts=[];$admissions=[];
    foreach($rows as $s){
        if($s['adm']!=='')$admissions[$s['adm']][]= $s['erpId'];
        $e=$byId[$s['id']]??null;
        if(!$e){$new[]=$s;continue;}
        $old=json_decode((string)$e['d'],true);
        if((int)$e['deleted']!==0 || !is_array($old)){$conflicts[]=$s['erpId'];continue;}
        $equal=true;foreach($s as $k=>$v)if(!array_key_exists($k,$old)||$old[$k]!==$v){$equal=false;break;}
        if($equal)$same[]=$s['erpId'];else $conflicts[]=$s['erpId'];
    }
    $duplicates=array_filter($admissions,fn($ids)=>count($ids)>1);
    // Manual entries with the same admission number need review, never name-based merging.
    foreach($existing as $e){
        if((int)$e['deleted']!==0 || str_starts_with($e['r'],'erp-snmishra-'))continue;
        $d=json_decode((string)$e['d'],true);$adm=(string)($d['adm']??'');
        if($adm!==''&&isset($admissions[$adm]))$conflicts[]='existing-admission:'.$adm;
    }
    return ['new'=>$new,'unchanged'=>$same,'conflicts'=>array_values(array_unique($conflicts)),'duplicateAdmissions'=>$duplicates];
}
