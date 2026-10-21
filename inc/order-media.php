<?php
// Shared storage only; each application enforces its own order/job permissions.
function order_media_name($name) {
    return is_string($name) && preg_match('/^(list|bill|pod)-[0-9]{6}-[a-f0-9]{8,32}\.(jpg|png|webp)$/D', $name);
}
function order_media_directory($scope = 'orders', $create = false) {
    if (!in_array($scope, ['orders','sarathi'], true)) return null;
    $root = realpath(__DIR__.'/..');
    $base = dirname($root).'/maakit-order-media';
    $path = $base.'/'.$scope;
    if ($create && !is_dir($path) && !@mkdir($path, 0700, true)) return null;
    $real = realpath($path);
    if (!$real || $real === $root || str_starts_with($real, $root.DIRECTORY_SEPARATOR)) return null;
    return $real;
}
function order_media_path($name, $scope = 'orders') {
    if (!order_media_name($name)) return null;
    $private = order_media_directory($scope);
    // Existing images stay in place; Apache routes their old URLs through ACLs.
    $legacy = __DIR__.($scope === 'sarathi' ? '/../smartsarathi/uploads' : '/../uploads');
    foreach ([$private, realpath($legacy)] as $dir) {
        if (!$dir) continue;
        $file = realpath($dir.'/'.$name);
        if ($file && dirname($file) === $dir && is_file($file)) return $file;
    }
    return null;
}
function order_photo_url($name) { return '/order-photo.php?file='.rawurlencode((string)$name); }
function order_media_grant(array $order) {
    $grants = array_filter($_SESSION['order_media'] ?? [], fn($g) => ($g['until'] ?? 0) > time());
    $grants[(int)$order['id']] = ['until'=>time()+1800, 'proof'=>hash('sha256',$order['mobile'].':'.$order['code'])];
    $_SESSION['order_media'] = array_slice($grants, -30, null, true);
}
function order_media_guest_allowed(array $order) {
    $g = $_SESSION['order_media'][(int)$order['id']] ?? [];
    return ($g['until'] ?? 0) > time() && hash_equals(hash('sha256',$order['mobile'].':'.$order['code']), (string)($g['proof'] ?? ''));
}
function order_media_stream($file) {
    $info = @getimagesize($file);
    if (!$info || !in_array($info['mime'], ['image/jpeg','image/png','image/webp'], true)) {
        http_response_code(404); exit;
    }
    header('Cache-Control: private, no-store, max-age=0');
    header('Expires: 0');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
    header('Content-Type: '.$info['mime']);
    header('Content-Length: '.filesize($file));
    header('Content-Disposition: inline');
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    readfile($file); exit;
}
