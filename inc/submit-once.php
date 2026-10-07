<?php
// Same-session double-tap/POST-refresh protection. PHP session locking keeps
// concurrent requests serial. This is not cross-device or crash deduplication.
function submit_once_form($scope) {
    $all = $_SESSION['submit_once'] ?? [];
    foreach ($all as $key=>$entry) {
        if ($entry['at'] < time()-3600) unset($all[$key]);
    }
    $key = $_POST['submit_key'] ?? '';
    $valid = is_string($key) && isset($all[$key]) && $all[$key]['scope'] === $scope;
    $done = $valid ? ($all[$key]['done'] ?? null) : null;
    $err = '';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!$valid || !csrf_ok())) {
        $done = null;
        $err = t('This form has expired. Review the details and send it again.', 'फॉर्म की अवधि समाप्त हो गई। जानकारी जाँचकर दोबारा भेजिए।');
    }
    if (!$valid) {
        $key = bin2hex(random_bytes(16));
        $all[$key] = ['scope'=>$scope,'at'=>time()];
    }
    $_SESSION['submit_once'] = array_slice($all,-50,null,true);
    return [$key,$done,$err];
}
function submit_once_complete($key,$done) {
    $_SESSION['submit_once'][$key]['done'] = $done;
}
