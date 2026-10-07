<?php
// Canonicals use the fixed public origin, never a client-provided Host or token.
function seo_page($uri, array $query = [], $method = 'GET') {
    $path=parse_url($uri,PHP_URL_PATH) ?: '/';
    if ($path === '/index.php') $path='/';
    $public=['/','/order.php','/bazaar.php','/directory.php','/sewa.php','/books.php','/business.php','/area.php','/transport.php','/register-business.php','/location.php','/support.php'];
    if (!empty($_SESSION['service_area']) && in_array($path,['/','/bazaar.php','/directory.php','/location.php'],true)) return ['index'=>false,'canonical'=>null];
    if ($method !== 'GET' || !in_array($path,$public,true) || !empty($query['q'])) return ['index'=>false,'canonical'=>null];
    $params=[];
    if ($path==='/business.php') {
        if (!isset($query['id']) || !is_scalar($query['id']) || !ctype_digit((string)$query['id']) || (int)$query['id']<1) return ['index'=>false,'canonical'=>null];
        $params['id']=(int)$query['id'];
    }
    if ($path==='/bazaar.php') {
        $meta=json_decode(file_get_contents(__DIR__.'/catalog-meta.json'),true);
        foreach (['group','type','sub','p'] as $key) {
            $v=$query[$key] ?? '';
            if (!is_scalar($v) || strlen((string)$v)>120) return ['index'=>false,'canonical'=>null];
            if ($v==='') continue;
            if ($key==='type' && !isset($meta[$v])) return ['index'=>false,'canonical'=>null];
            if ($key==='group' && !in_array($v,array_column($meta,'group'),true)) return ['index'=>false,'canonical'=>null];
            if ($key==='p') { if (!ctype_digit((string)$v)) return ['index'=>false,'canonical'=>null]; if ((int)$v<=1) continue; $v=(int)$v; }
            if ($key==='sub' && empty($query['type'])) return ['index'=>false,'canonical'=>null];
            $params[$key]=$v;
        }
    }
    if ($path==='/sewa.php' && !empty($query['s'])) {
        if (!is_string($query['s']) || !preg_match('/^[a-z]{1,30}$/',$query['s'])) return ['index'=>false,'canonical'=>null];
        $params['s']=$query['s'];
    }
    return ['index'=>true,'canonical'=>'https://maakit.in'.$path.($params?'?'.http_build_query($params,'','&',PHP_QUERY_RFC3986):'')];
}
