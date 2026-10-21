<?php
// Library attribution must survive the shared header's ticker query.
require_once __DIR__.'/../../config.php';
if (DB_NAME !== 'maakit_jaanch') throw new RuntimeException('CI database only');
require_once __DIR__.'/../../inc/db.php';
$id = 0;
try {
    $catId = (int)$pdo->query("SELECT id FROM catalog_items ORDER BY id LIMIT 1")->fetchColumn();
    if (!$catId) throw new RuntimeException('Photo credits: catalogue fixture missing');
    $credit = 'PhotoCreditFixture'.bin2hex(random_bytes(6));
    $page = 'https://commons.wikimedia.org/wiki/File:PhotoCreditFixture.jpg';
    $st = $pdo->prepare("INSERT INTO catalog_photos (cat_id,file,sort_no,source,credit,license,source_url) VALUES (?, ?, 9999, 'commons', ?, 'CC BY-SA 3.0', ?)");
    $st->execute([$catId, 'cph_credit_fixture.jpg', $credit, $page]);
    $id = (int)$pdo->lastInsertId();
    foreach (['en','hi'] as $lang) {
        $ch = curl_init('http://127.0.0.1:8099/photo-credits.php?lang='.$lang);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>20,CURLOPT_PROXY=>'']);
        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($status !== 200 || !is_string($body) || !str_contains($body, $credit) || !str_contains($body, $page) || !str_contains($body, 'CC BY-SA 3.0')) {
            throw new RuntimeException('Photo credits: saved attribution missing after shared header ('.$lang.')');
        }
    }
    echo "Photo credits: saved library attribution visible in both languages\n";
} finally {
    if ($id) $pdo->prepare("DELETE FROM catalog_photos WHERE id=?")->execute([$id]);
}
