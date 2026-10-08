<?php
// Session cart holds catalogue identity/specifications, never retailer prices.
function request_cart_rows(PDO $pdo, array $cart) {
    $rows=[];
    foreach (array_slice($cart,0,50,true) as $key=>$entry) {
        if (!is_array($entry)) continue;
        $st=$pdo->prepare('SELECT * FROM catalog_items WHERE id=? AND is_sewa=0');
        $st->execute([(int)($entry['id']??0)]); $item=$st->fetch();
        if (!$item) continue;
        $rows[$key]=['id'=>(int)$item['id'],'name'=>t($item['name_en'],$item['name_hi']?:$item['name_en']),
            'qty'=>max(1,min(99,(int)($entry['qty']??1))),
            'pack'=>mb_substr((string)(($entry['pack']??'')?:$item['unit_hint']),0,120),
            'urgent'=>!empty($entry['urgent'])];
    }
    return $rows;
}
function request_cart_note(array $rows) {
    if (!$rows) return '';
    $text=t('Maakit sourcing request:', 'Maakit से सामान की माँग:');
    foreach($rows as $r) $text.="\n".$r['name'].' — '.$r['qty'].' × '.$r['pack'].($r['urgent']?' · '.t('Urgent','जल्दी चाहिए'):'');
    return $text."\n".t('Please confirm the final price and possible delivery time before purchase.','खरीदने से पहले अंतिम दाम और सम्भव डिलीवरी समय पक्का कीजिए।');
}
