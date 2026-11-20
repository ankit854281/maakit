<?php
// Public "Scan to verify" page for rankers' certificates (owner, 20 Nov 2026). The QR on a certificate opens
// verify-cert.php?c=HY26-XXXXXXX; this page shows what the school's own records say (prcCert, written by
// progress_api.php?a=certs from the saved marks). Shows only name, class, rank, % and exam — no photo, no phone, no address.
declare(strict_types=1);
require __DIR__ . '/inc.php';
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; img-src 'self'; base-uri 'none'; frame-ancestors 'none'");
$c = strtoupper(trim((string)($_GET['c'] ?? '')));
$d = null; $err = '';
if (!preg_match('/^HY\d\d-[0-9A-Z]{7}$/', $c)) $err = 'This QR code is not a certificate of our school.';
else { try { $d = sch_rec_get('prcCert', $c); } catch (Throwable $e) { $err = 'The school server is busy. Please try again in a few minutes.'; } if (!$d && !$err) $err = 'No certificate with this number is in the school records.'; }
$h = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$ord = ['', '1st', '2nd', '3rd'];
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title>Certificate check · SN Mishra Singapore International School</title>
<style>
body{margin:0;font-family:Georgia,'Times New Roman',serif;background:#F5ECD6;color:#1d2733;display:flex;min-height:100vh;align-items:center;justify-content:center;padding:16px;box-sizing:border-box}
.card{max-width:440px;width:100%;background:#FFFDF6;border:3px solid #B8902F;outline:8px solid #14294A;border-radius:6px;padding:24px 22px;text-align:center}
img{width:84px;height:84px;border-radius:50%}
h1{font-size:18px;color:#14294A;margin:8px 0 2px;letter-spacing:.5px}.pl{font-size:13px;color:#6b5f4b;font-style:italic}
.ok{margin:18px 0 8px;font:bold 15px Arial,sans-serif;color:#fff;background:#1E7A43;border-radius:20px;padding:8px 14px;display:inline-block}
.no{margin:18px 0 8px;font:bold 15px Arial,sans-serif;color:#fff;background:#B3261E;border-radius:20px;padding:8px 14px;display:inline-block}
.nm{font-size:28px;color:#14294A;margin:10px 0 2px}.rk{font-size:20px;color:#7A1F2B;font-weight:bold;margin:6px 0}
table{margin:14px auto 0;font:14px Arial,sans-serif;border-collapse:collapse}td{padding:4px 10px;text-align:left}td:first-child{color:#6b5f4b}
.ft{margin-top:16px;font:12px Arial,sans-serif;color:#7a6a50}
</style></head><body><div class="card">
<img src="img/snmsis-logo.png" alt=""><h1>SN MISHRA SINGAPORE INTERNATIONAL SCHOOL</h1><div class="pl">Jagannathpur, Bhadohi (U.P.)</div>
<?php if ($d): ?>
<div class="ok">✔ Genuine certificate</div>
<div class="nm"><?= $h($d['name']) ?></div>
<div class="rk"><?= $h($ord[(int)$d['rank']] ?? $d['rank']) ?> Rank · Class <?= $h($d['cls']) ?><?= $d['section'] !== '' ? '-' . $h($d['section']) : '' ?></div>
<table><tr><td>Exam</td><td><b><?= $h($d['exam']) ?> <?= $h($d['ses']) ?></b></td></tr><tr><td>Marks</td><td><b><?= $h($d['pct']) ?>%</b></td></tr><tr><td>Certificate No.</td><td><b><?= $h($d['no']) ?></b></td></tr></table>
<div class="ft">This matches the school's own records.</div>
<?php else: ?>
<div class="no">✖ Not verified</div><p style="font:14px Arial,sans-serif"><?= $h($err) ?></p><div class="ft">If you think this is a mistake, please contact the school office.</div>
<?php endif; ?>
</div></body></html>
