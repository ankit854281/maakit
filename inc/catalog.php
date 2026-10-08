<?php
require_once __DIR__ . '/search.php';
require_once __DIR__ . '/dukan.php';

function catalog_groups() {
    return [
        'food' => ['Food & daily needs', 'राशन, खाना और रोज़मर्रा'],
        'fashion' => ['Clothes & footwear', 'कपड़े, जूते और फैशन'],
        'health' => ['Health & medical', 'दवा और स्वास्थ्य'],
        'beauty' => ['Beauty & salon', 'सौंदर्य और सैलून'],
        'electronics' => ['Electronics & repairs', 'मोबाइल, इलेक्ट्रॉनिक्स और मरम्मत'],
        'construction' => ['Building & hardware', 'निर्माण और हार्डवेयर'],
        'home' => ['Home & household', 'घर और घरेलू सामान'],
        'vehicles' => ['Vehicles & workshops', 'गाड़ी, पार्ट्स और गैराज'],
        'education' => ['Study, sports & hobbies', 'पढ़ाई, खेल और शौक'],
        'farming' => ['Farming & animal care', 'खेती और पशु देखभाल'],
        'events' => ['Events & puja', 'शादी, कार्यक्रम और पूजा'],
        'services' => ['Other local shops & services', 'अन्य स्थानीय दुकानें और सेवाएँ'],
    ];
}

function catalog_meta() {
    static $meta;
    if ($meta === null) $meta = json_decode(file_get_contents(__DIR__ . '/catalog-meta.json'), true) ?: [];
    return $meta;
}

function catalog_label($type, $fallback = '') {
    $meta = catalog_meta();
    return t($type, $meta[$type]['hi'] ?? ($fallback ?: $type));
}

function catalog_sub_label($name) {
    $hi = [
        'Atta & Flour'=>'आटा और बेसन', 'Rice'=>'चावल', 'Pulses'=>'दालें',
        'Spices'=>'मसाले', 'Edible Oil'=>'खाने का तेल', 'Beverages'=>'पेय पदार्थ',
        'Breakfast & Spreads'=>'नाश्ता और स्प्रेड', 'Snacks & Packaged Food'=>'नमकीन और पैक खाना',
        'Vegetables'=>'सब्जियाँ', 'Fruits'=>'फल', 'Leafy & Herbs'=>'हरी पत्तेदार सब्जियाँ',
        'Milk & Milk Products'=>'दूध और दूध का सामान', 'Bread & Buns'=>'ब्रेड और बन',
        'Cakes & Pastries'=>'केक और पेस्ट्री', 'Traditional Sweets'=>'मिठाइयाँ',
        'Namkeen & Chaat'=>'नमकीन और चाट', 'Meat & Poultry'=>'मांस और मुर्गा',
        'Nuts & Seeds'=>'मेवा और बीज', "Men's Wear"=>'पुरुषों के कपड़े',
        "Women's Wear"=>'महिलाओं के कपड़े', 'Kids Wear'=>'बच्चों के कपड़े',
        'Fabric'=>'कपड़े का थान', 'Shoes & Sandals'=>'जूते और चप्पल',
        'Gold & Silver Jewellery'=>'सोने और चाँदी के गहने', 'Skin Care'=>'त्वचा की देखभाल',
        'Hair Care'=>'बालों की देखभाल', 'Makeup'=>'मेकअप', 'OTC & First Aid'=>'प्राथमिक उपचार सामग्री',
        'Mobile Phones & Accessories'=>'मोबाइल और एक्सेसरी', 'Computers & Accessories'=>'कंप्यूटर और एक्सेसरी',
        'Large Appliances'=>'बड़े घरेलू उपकरण', 'Electrical Goods'=>'बिजली का सामान',
        'Tools & Fasteners'=>'औजार और नट बोल्ट', 'Construction Materials'=>'निर्माण सामग्री',
        'Paint & Painting Tools'=>'पेंट और रंगाई के औजार', 'Bathroom & Plumbing'=>'बाथरूम और नल सामग्री',
        'Tiles & Flooring'=>'टाइल्स और फर्श', 'Home Furniture'=>'घर का फर्नीचर',
        'Decor & Furnishing'=>'सजावट और फर्निशिंग', 'Cookware & Utensils'=>'बर्तन और खाना बनाने का सामान',
        'Stationery'=>'स्टेशनरी', 'Toys & Games'=>'खिलौने और खेल', 'Sports Equipment'=>'खेल के उपकरण',
        'Musical Instruments'=>'संगीत वाद्य', 'Bike & Car Parts'=>'बाइक और कार के पार्ट्स',
        'Tyres & Wheels'=>'टायर और पहिए', 'Batteries'=>'बैटरी', 'Online Services'=>'ऑनलाइन सेवाएँ',
        'Photo & Video'=>'फोटो और वीडियो', 'Laundry Services'=>'धुलाई सेवा', 'Courier Services'=>'कूरियर सेवा',
        'Cleaning Supplies'=>'सफाई सामग्री', 'Baby Products'=>'शिशु सामान', 'Bags & Luggage'=>'बैग और सूटकेस',
        'Seeds & Farm Inputs'=>'बीज खाद और कृषि सामग्री', 'Plants & Gardening'=>'पौधे और बागवानी',
        'Pet Products'=>'पालतू पशु सामान', 'Pooja Products'=>'पूजा सामग्री', 'Event Services'=>'कार्यक्रम सेवा',
        'Travel Services'=>'यात्रा सेवा', 'Hotel Services'=>'होटल सेवा', 'Office Products'=>'ऑफिस का सामान',
        'Packaging Supplies'=>'पैकिंग सामग्री', 'Water Purifiers'=>'वाटर प्यूरीफायर',
        'Mobile Repair'=>'मोबाइल मरम्मत', 'Computer Repair'=>'कंप्यूटर मरम्मत',
        'Electrical Repair'=>'बिजली उपकरण मरम्मत', 'Appliance Repair'=>'घरेलू उपकरण मरम्मत',
        'Plumbing'=>'नल और प्लम्बिंग', 'Security Equipment'=>'सुरक्षा उपकरण',
        'Solar Products'=>'सोलर सामान', 'Watches'=>'घड़ियाँ', 'Fragrance'=>'इत्र और परफ्यूम',
    ];
    return t($name, $hi[$name] ?? catalog_label($name));
}

function catalog_url(array $params = []) {
    $params = array_filter($params, fn($v) => $v !== '' && $v !== null);
    return '/bazaar.php' . ($params ? '?' . http_build_query($params) : '');
}

function catalog_load(PDO $pdo) {
    return $pdo->query('SELECT c.*, t.name_hi AS type_hi FROM catalog_items c
        LEFT JOIN catalog_types t ON t.slug=c.shop_type ORDER BY c.sort_no,c.id')->fetchAll();
}

function catalog_filter(array $items, $group, $type, $sub, $query) {
    $meta = catalog_meta();
    return array_values(array_filter($items, function ($item) use ($meta, $group, $type, $sub, $query) {
        $shop = $item['shop_type'];
        if ($group !== '' && ($meta[$shop]['group'] ?? 'services') !== $group) return false;
        if ($type !== '' && $shop !== $type) return false;
        if ($sub !== '' && $item['sub_cat'] !== $sub) return false;
        return $query === '' || market_matches($query, implode(' ', [
            $shop, $meta[$shop]['hi'] ?? '', $item['type_hi'] ?? '', catalog_picker_aliases($shop),
            $item['name_en'], $item['name_hi'] ?? '', $item['sub_cat'] ?? '',
        ]));
    }));
}

// Only actual shop-owned prices. Catalogue examples never become offers.
function catalog_offers(PDO $pdo, array $ids, $area = null) {
    if (!$ids) return [];
    $marks = implode(',', array_fill(0, count($ids), '?'));
    $scope=$area ? ' AND EXISTS (SELECT 1 FROM service_area_shops a WHERE a.business_id=b.id AND a.village_id=?)' : '';
    $st = $pdo->prepare("SELECT s.id,s.cat_id,s.name,s.unit,s.price,s.photo,s.stock,
        b.id AS business_id,b.name AS shop_name,b.village,b.shop_open,b.open_time,b.close_time
        FROM shop_items s JOIN businesses b ON b.id=s.business_id
        WHERE s.cat_id IN ($marks) AND s.active=1 AND s.price>0
          AND b.status='approved' AND b.items_on=1 $scope
        ORDER BY s.price,s.id");
    $st->execute($area ? array_merge($ids,[(int)$area['id']]) : $ids);
    $out = [];
    foreach ($st->fetchAll() as $offer) {
        $offer['open_now'] = dukan_khuli($offer);
        $out[(int)$offer['cat_id']][] = $offer;
    }
    return $out;
}

function catalog_shops(PDO $pdo, $type, $area = null) {
    if ($type === '') return [];
    $scope=$area ? ' AND EXISTS (SELECT 1 FROM service_area_shops a WHERE a.business_id=b.id AND a.village_id=?)' : '';
    $st = $pdo->prepare("SELECT b.id,b.name,b.village,b.address,b.photo,b.shop_open,
        b.open_time,b.close_time,b.items_on,
        (SELECT COUNT(*) FROM shop_items s WHERE s.business_id=b.id
          AND s.active=1 AND s.price>0 AND s.stock='hai') AS available_count
        FROM businesses b WHERE b.status='approved'
          AND (b.shop_type=? OR EXISTS (
            SELECT 1 FROM shop_items s JOIN catalog_items c ON c.id=s.cat_id
            WHERE s.business_id=b.id AND s.active=1 AND c.shop_type=?)) $scope
        ORDER BY b.village,b.name,b.id");
    $st->execute($area ? [$type,$type,(int)$area['id']] : [$type,$type]);
    $out = $st->fetchAll();
    foreach ($out as &$shop) {
        $shop['open_now'] = dukan_khuli($shop);
        if (!(int)$shop['items_on']) $shop['available_count'] = 0;
    }
    unset($shop);
    return $out;
}

function catalog_picker_aliases($type) {
    return ['Grocery / Kirana Store'=>'kirana rashan ration', 'Paint Store'=>'paint pent rang brush asian berger nerolac dulux रंग ब्रश एशियन', 'Medical Store'=>'dawa dawai dava', 'Mobile Store'=>'mobile phone', 'Sweet Shop'=>'mithai nashta nasta', 'Hardware Shop'=>'hardware aujar tools'][$type] ?? '';
}
