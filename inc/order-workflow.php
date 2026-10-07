<?php
// Manual staff transitions must preserve delivery order and terminal states.
function staff_status_sources($target) {
    return ['Confirm'=>['Naya'],'Pickup'=>['Assign'],'Delivered'=>['Pickup'],
        'Paisa jama'=>['Delivered'],'Cancel'=>['Naya','Confirm','Assign']][$target]??[];
}
function staff_order_amount($value) {
    if($value==='') return null;
    if(!is_string($value)||!preg_match('/^[0-9]{1,7}$/',$value)||(int)$value>1000000) return false;
    return (int)$value;
}
function staff_goods_payments() {
    return ['डिलीवरी पर कैश'=>t('Cash for goods on delivery','सामान के लिए डिलीवरी पर कैश'),
        'मैं खुद दुकान को UPI करूँगा'=>t('UPI directly to the shop','UPI सीधे दुकान को')];
}
