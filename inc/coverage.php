<?php
// The legacy villages table remains the stable area identifier for old orders.
// New areas may be any Indian locality; city/state/PIN and readiness are explicit.
function coverage_areas(PDO $pdo, $active = true) {
    $sql = 'SELECT v.*, m.city,m.state,m.pincode,m.delivery_on,m.booking_on,m.base_fee,m.first_free FROM villages v LEFT JOIN service_area_meta m ON m.village_id=v.id';
    if ($active) $sql .= ' WHERE v.live=1';
    return $pdo->query($sql . ' ORDER BY m.state,m.city,v.name')->fetchAll();
}
function coverage_area(PDO $pdo, $name) {
    if (!is_string($name) || $name === '' || mb_strlen($name)>60) return null;
    $s=$pdo->prepare('SELECT v.*, m.city,m.state,m.pincode,m.delivery_on,m.booking_on,m.base_fee,m.first_free FROM villages v LEFT JOIN service_area_meta m ON m.village_id=v.id WHERE v.name=? AND v.live=1');
    $s->execute([$name]);
    return $s->fetch() ?: null;
}
function coverage_enabled($area, $kind='delivery') {
    return $area && (int)$area['live']===1 && (int)($area[$kind.'_on'] ?? 1)===1;
}
function coverage_selected(PDO $pdo) {
    return coverage_area($pdo, $_SESSION['service_area'] ?? '');
}
function coverage_label($area) {
    return vname($area) . (!empty($area['city']) ? ' · '.$area['city'] : '') . (!empty($area['pincode']) ? ' · '.$area['pincode'] : '');
}
function coverage_shop_allowed(PDO $pdo, $business, $area) {
    if (!$area || !$business || !coverage_enabled($area)) return false;
    $s=$pdo->prepare('SELECT 1 FROM service_area_shops WHERE village_id=? AND business_id=?');
    $s->execute([(int)$area['id'], (int)$business]);
    return (bool)$s->fetchColumn();
}
function coverage_error($kind='delivery') {
    return $kind==='booking'
        ? t('Bookings are not available in this area yet. Check service areas or request coverage.', 'इस इलाके में बुकिंग अभी उपलब्ध नहीं है। सेवा क्षेत्र देखिए या अपने इलाके के लिए अनुरोध कीजिए।')
        : t('Delivery is not available in this area yet. Check service areas or request coverage.', 'इस इलाके में डिलीवरी अभी उपलब्ध नहीं है। सेवा क्षेत्र देखिए या अपने इलाके के लिए अनुरोध कीजिए।');
}
