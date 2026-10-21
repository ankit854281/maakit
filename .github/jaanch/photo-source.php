<?php
// URL-only regression: never downloads a file or reads/writes production data.
require_once __DIR__ . '/../../inc/photo-fetch.php';
function photo_source_check($ok, $message) {
    if (!$ok) throw new RuntimeException($message);
}
$thumb = 'https://thumb.wikimedia.org/wikipedia/commons/thumb/9/9f/Red_Onion_on_White.JPG/960px-Red_Onion_on_White.JPG?utm_source=commons.wikimedia.org';
$original = 'https://upload.wikimedia.org/wikipedia/commons/9/9f/Red_Onion_on_White.JPG';
photo_source_check(pf_source_url($thumb) === $original, 'New Commons thumbnail resolves to the allowlisted original');
photo_source_check(!pf_url_ok($thumb), 'Thumbnail host is not added to server download allowlist');
photo_source_check(pf_url_ok(pf_source_url($thumb)), 'Canonical Commons original is allowed');
photo_source_check(pf_source_url($original) === $original, 'Existing originals remain unchanged');
$encoded = 'https://thumb.wikimedia.org/wikipedia/commons/thumb/a/aa/Bananas_%28white_background%29.jpg/960px-Bananas_%28white_background%29.jpg';
photo_source_check(pf_source_url($encoded) === 'https://upload.wikimedia.org/wikipedia/commons/a/aa/Bananas_%28white_background%29.jpg', 'Encoded real file names are preserved');
foreach ([
    str_replace('https:', 'http:', $thumb),
    str_replace('thumb.wikimedia.org', 'thumb.wikimedia.org:443', $thumb),
    str_replace('thumb.wikimedia.org', 'user:pass@thumb.wikimedia.org', $thumb),
    str_replace('/commons/thumb/', '/en/thumb/', $thumb),
    str_replace('Red_Onion_on_White.JPG/', 'a%2fb.jpg/', $thumb),
    str_replace('Red_Onion_on_White.JPG/', 'a%5cb.jpg/', $thumb),
    str_replace('Red_Onion_on_White.JPG/', 'a%00b.jpg/', $thumb),
    str_replace('Red_Onion_on_White.JPG/', 'script.php/', $thumb),
    str_replace('thumb.wikimedia.org', 'thumb.wikimedia.org.evil.example', $thumb),
    'https://127.0.0.1/test.jpg',
    'file:///etc/passwd',
] as $bad) {
    photo_source_check(!pf_url_ok(pf_source_url($bad)), 'Malformed or non-library URL refused: ' . $bad);
}
photo_source_check(pf_url_ok(pf_source_url('https://images.openfoodfacts.org/images/products/1/front.jpg')), 'Open Food Facts still works');
photo_source_check(pf_license_ok('CC BY-SA 3.0') && !pf_license_ok('CC BY-NC 4.0') && !pf_license_ok('CC BY-ND 4.0'), 'Commercial licence guard stays enforced');
echo "Photo source URL regression passed\n";
