<?php
// ============================================================================
//  Online fee payment from the demand bill — owner, 24 Nov 2026:
//  "auto fee collection upgrade karna hai and qr add karna hai demand bill me — pay karte he fee update karne ke
//   baad receipt bhi bankar chala jaye"
//
//  How it works
//  - Every demand bill (demandbill.js) carries a QR + link to school/pay.php?b=<token>. The token is signed
//    (fp_token) and names the session, the children on the bill and the "payable till" month — nothing else.
//  - pay.php shows what is payable TODAY for those children (from the Fee Receipt ledger, not the old picture),
//    opens Razorpay checkout (UPI app / card / net banking) with the SCHOOL'S OWN Razorpay keys.
//  - When Razorpay confirms (checkout signature, or the webhook pay_webhook.php if the browser closed),
//    fp_settle() makes the receipts with ofc_write_receipt() — exactly like the Fee Receipt desk: same series,
//    mode "Online", deposit bank = SCHOOL_PAY_BANK, Razorpay payment id in the remark. Idempotent per order.
//  - Order of money (same rules as the desk): a live older session's dues first (its own receipt in its own
//    series), then the old due (put on the card as "Old Due" like Add Other Fee does), then the heads month by
//    month (oldest first) up to the bill's month. A head may be part-paid.
//  - If the dues went down between opening the page and paying (cash taken at the office meanwhile), the extra
//    is NOT pushed into later months silently: the order is marked "excess" and shows in the log for the office.
//
//  Money rule: Maakit never holds this money. The Razorpay account is the SCHOOL's (school PAN / bank); the
//  keys live only in config.php as SCHOOL_RZP_*. Without them pay.php shows the amount and "pay at the office".
//
//  Kinds (prefix ofc → kept out of legacy sync): ofcPay  'order_xxx'  {ses, upto, sids, kids:[{sid,amt}], amount,
//  status created|paid|excess, pid, receipts:[{ses,no}], excess, by}
// ============================================================================
declare(strict_types=1);

// config.php constants; SCHOOL_RZP_* / SCHOOL_PAY_* may also come from the server environment (the CI test runs a fake Razorpay that way)
function fp_cfg(string $k): string { if (defined($k)) return (string)constant($k); return str_starts_with($k, 'SCHOOL_RZP_') || str_starts_with($k, 'SCHOOL_PAY_') ? (string)(getenv($k) ?: '') : ''; }
function fp_ready(): bool { return fp_cfg('SCHOOL_RZP_KEY_ID') !== '' && fp_cfg('SCHOOL_RZP_KEY_SECRET') !== ''; }
function fp_key(): string {
    $base = fp_cfg('SCHOOL_PAY_SECRET') ?: fp_cfg('UPDATE_KEY') ?: fp_cfg('SCHOOL_RZP_KEY_SECRET');
    if ($base === '') throw new RuntimeException('Set UPDATE_KEY or SCHOOL_PAY_SECRET in config.php.');
    return hash('sha256', 'school-fee-pay|' . $base, true);
}
function fp_b64(string $s): string { return rtrim(strtr(base64_encode($s), '+/', '-_'), '='); }
function fp_unb64(string $s): string { return (string)base64_decode(strtr($s, '-_', '+/'), true); }

/** Signed link token for one bill: session, children, payable-till month (1..12). */
function fp_token(string $ses, array $sids, int $upto): string {
    $body = fp_b64($ses . '~' . $upto . '~' . implode(',', $sids));
    return $body . '.' . fp_b64(substr(hash_hmac('sha256', $body, fp_key(), true), 0, 12));
}
function fp_untoken(string $t): ?array {
    if (!preg_match('/^([A-Za-z0-9_-]{4,900})\.([A-Za-z0-9_-]{16})$/', $t, $m)) return null;
    if (!hash_equals(fp_b64(substr(hash_hmac('sha256', $m[1], fp_key(), true), 0, 12)), $m[2])) return null;
    $p = explode('~', fp_unb64($m[1]), 3); if (count($p) !== 3) return null;
    $ses = ofc_ses($p[0]); $upto = (int)$p[1]; $sids = array_values(array_filter(explode(',', $p[2]), 'strlen'));
    if ($ses === '' || $upto < 1 || $upto > 12 || !$sids || count($sids) > 12) return null;
    return ['ses' => $ses, 'upto' => $upto, 'sids' => $sids];
}
/** Signed receipt link (parent can open / print the receipt later). */
function fp_rtoken(string $ses, string $no): string { $b = fp_b64($ses . '~' . $no); return $b . '.' . fp_b64(substr(hash_hmac('sha256', 'r|' . $b, fp_key(), true), 0, 12)); }
function fp_unrtoken(string $t): ?array {
    if (!preg_match('/^([A-Za-z0-9_-]{4,200})\.([A-Za-z0-9_-]{16})$/', $t, $m)) return null;
    if (!hash_equals(fp_b64(substr(hash_hmac('sha256', 'r|' . $m[1], fp_key(), true), 0, 12)), $m[2])) return null;
    $p = explode('~', fp_unb64($m[1]), 2); return count($p) === 2 ? ['ses' => $p[0], 'no' => $p[1]] : null;
}
/** Full https link to the pay page, from the page that is asking (demandbill_api.php lives next to pay.php). */
function fp_url(string $token): string {
    $base = fp_cfg('SCHOOL_PAY_BASE');
    if ($base === '') { $host = (string)($_SERVER['HTTP_HOST'] ?? 'maakit.in'); if (!preg_match('/^[a-z0-9.:-]+$/i', $host)) $host = 'maakit.in';
        $https = ($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https' || !preg_match('/^(127\.0\.0\.1|localhost)(:\d+)?$/', $host);
        $base = ($https ? 'https://' : 'http://') . $host . rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/school/x.php'))), '/') . '/pay.php'; }
    return $base . '?b=' . $token;
}
function fp_ord(int $m): int { return ($m + 8) % 12; }

/**
 * What can be paid for ONE child, oldest first, capped at $max paise ($max = PHP_INT_MAX → everything payable).
 * Returns ['name','cls','adm','parts' => [[ses, alloc[{i,amt}], lines[[head, month, amt]], addOld]], 'amt', 'stop' => reason or ''].
 * Pure reading — nothing is written. fp_settle() writes the same plan.
 */
function fp_plan(string $ses, string $sid, int $upto, int $max = PHP_INT_MAX): array {
    $L = sch_rec_get('ofcLed', $ses . '|' . $sid);
    $st = sch_rec_get('student', $sid) ?? [];
    $out = ['sid' => $sid, 'name' => (string)($L['name'] ?? $st['name'] ?? ''), 'cls' => (string)($L['cls'] ?? $st['cls'] ?? ''), 'adm' => (string)($L['adm'] ?? $st['adm'] ?? ''),
            'father' => (string)($L['father'] ?? $st['father'] ?? ''), 'parts' => [], 'amt' => 0, 'stop' => ''];
    if (!ofc_live($ses)) { $out['stop'] = 'Online fee for ' . $ses . ' is not open yet. Please pay at the school office.'; return $out; }
    if (!$L) { $out['stop'] = 'Fee card not found. Please pay at the school office.'; return $out; }
    $left = $max;
    $take = function (array $heads, ?int $uptoOrd) use (&$left): array {
        usort($heads, fn($a, $b) => [str_starts_with((string)$a['name'], 'Old Due') ? 0 : 1, fp_ord((int)$a['m']), $a['i']] <=> [str_starts_with((string)$b['name'], 'Old Due') ? 0 : 1, fp_ord((int)$b['m']), $b['i']]);   // old due first, then month by month
        $alloc = []; $lines = [];
        foreach ($heads as $h) {
            if ($left <= 0) break;
            if (!empty($h['na']) || ($uptoOrd !== null && fp_ord((int)$h['m']) > $uptoOrd)) continue;
            $bal = (int)$h['amt'] - (int)$h['paid']; if ($bal <= 0) continue;
            $x = min($bal, $left); $left -= $x;
            $alloc[] = ['i' => (int)$h['i'], 'amt' => $x]; $old = str_starts_with((string)$h['name'], 'Old Due');
            $lines[] = [$old ? 'Previous balance (old due)' : str_replace(['Tution', 'Sefety'], ['Tuition', 'Safety'], (string)$h['name']), $old ? '' : (OFC_MONTHS[(int)$h['m']] ?? ''), $x];
        }
        return [$alloc, $lines];
    };
    // 1) an older session still open here with dues → paid there first, in its own receipt series
    $ps = ofc_prev($ses); $PL = ofc_live($ps) ? sch_rec_get('ofcLed', $ps . '|' . $sid) : null; $prevBal = 0;
    if ($PL && ($prevBal = max(0, ofc_totals($PL)['balance'])) > 0) {
        if (ofc_old_due($ps, $sid, $PL) > 0) { $out['stop'] = 'Old dues of ' . $ps . ' must be cleared at the school office first.'; return $out; }
        [$a, $l] = $take($PL['heads'], null);
        if ($a) { $out['parts'][] = ['ses' => $ps, 'alloc' => $a, 'lines' => $l, 'addOld' => 0]; $out['amt'] += array_sum(array_column($a, 'amt')); }
        if ($left <= 0 || array_sum(array_column($a, 'amt')) < $prevBal) return $out;   // money ran out inside the old session
    }
    // 2) old due not yet on this card (old ERP "Old Fee"): put on the card as "Old Due" (April), like Add Other Fee
    $od = ofc_old_due($ses, $sid, $L) - ($PL ? $prevBal : 0);
    $addOld = 0; $heads = $L['heads'];
    if ($od > 0) {
        if ($PL) { $out['stop'] = 'Old dues must be cleared at the school office first.'; return $out; }
        $addOld = $od; $heads[] = ['i' => max(array_merge([0], array_column($heads, 'i'))) + 1, 'm' => 4, 'name' => 'Old Due', 'amt' => $od, 'paid' => 0, 'na' => false];
    }
    // 3) this session's heads, month by month, up to the bill's month
    [$a, $l] = $take($heads, fp_ord($upto));
    if ($a) { $out['parts'][] = ['ses' => $ses, 'alloc' => $a, 'lines' => $l, 'addOld' => $addOld]; $out['amt'] += array_sum(array_column($a, 'amt')); }
    return $out;
}

/** Plan for every child on the bill. */
function fp_bill(array $T): array {
    $kids = []; $tot = 0;
    foreach ($T['sids'] as $sid) { $k = fp_plan($T['ses'], $sid, $T['upto']); $kids[] = $k; $tot += $k['amt']; }
    return ['kids' => $kids, 'amount' => $tot];
}

/** Bank the online receipts go to (must be one of the Fee Receipt banks): SCHOOL_PAY_BANK, else the first bank that takes "Online". */
function fp_bank(): string {
    $banks = (sch_rec_get('settings', 'main')['ofcBanks'] ?? null) ?: [['name' => 'UBI-…8120', 'modes' => ['Cash', 'NEFT', 'UPI']], ['name' => 'HDFC BANK-3039', 'modes' => ['Cash', 'Cheque', 'UPI', 'Online']]];
    $want = fp_cfg('SCHOOL_PAY_BANK');
    foreach ($banks as $b) if ($want !== '' && $b['name'] === $want) return $b['name'];
    foreach ($banks as $b) if (in_array('Online', (array)($b['modes'] ?? []), true)) return $b['name'];
    return (string)($banks[0]['name'] ?? 'Online');
}

/** Razorpay API with the school's keys. */
function fp_rzp(string $method, string $path, array $body = []): ?array {
    $url = (fp_cfg('SCHOOL_RZP_API') ?: 'https://api.razorpay.com/v1/') . $path;   // SCHOOL_RZP_API only for the CI fake
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_USERPWD => fp_cfg('SCHOOL_RZP_KEY_ID') . ':' . fp_cfg('SCHOOL_RZP_KEY_SECRET'),
        CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_TIMEOUT => 20, CURLOPT_SSL_VERIFYPEER => true]
        + ($body ? [CURLOPT_POSTFIELDS => json_encode($body)] : []) + (str_starts_with($url, 'http://127.0.0.1') ? [CURLOPT_PROXY => ''] : []));
    $out = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    $j = is_string($out) ? json_decode($out, true) : null;
    if ($code < 200 || $code >= 300 || !is_array($j)) { error_log('school pay razorpay ' . $path . ' ' . $code . ' ' . substr((string)$out, 0, 300)); return null; }
    return $j;
}

/**
 * Money came for an order: make the receipts. Safe to call twice (checkout + webhook) — the second call returns
 * the same receipts. $amount = paise Razorpay says were captured. Returns the ofcPay record.
 */
function fp_settle(string $orderId, string $pid, int $amount, string $via): array {
    $db = sch_db(); $db->beginTransaction();
    try {
        $db->query("SELECT uid FROM sch_users WHERE uid='principal' FOR UPDATE")->fetch();   // same lock as the Fee Receipt desk
        $O = sch_rec_get('ofcPay', $orderId); if (!$O) throw new RuntimeException('Unknown order ' . $orderId);
        if (in_array($O['status'], ['paid', 'excess'], true)) { $db->commit(); return $O; }
        if ($amount !== (int)$O['amount']) throw new RuntimeException('Amount mismatch for ' . $orderId . ': ' . $amount . ' vs ' . $O['amount']);
        $u = ['role' => 'system', 'label' => 'Online payment (parent)', 'uid' => 'online']; $uid = 'online';
        $today = (new DateTimeImmutable('now', new DateTimeZone('Asia/Kolkata')))->format('Y-m-d'); $bank = fp_bank(); $made = []; $excess = 0;
        foreach ($O['kids'] as $k) {
            $P = fp_plan($O['ses'], $k['sid'], (int)$O['upto'], (int)$k['amt']);
            foreach ($P['parts'] as $part) {
                $L = sch_rec_get('ofcLed', $part['ses'] . '|' . $k['sid']);
                if ($part['addOld'] > 0) {   // like feecollect_api.php addFee "Old Due"
                    $i = max(array_merge([0], array_column($L['heads'], 'i'))) + 1;
                    $L['oldMoved'] = (int)($L['oldMoved'] ?? 0) + $part['addOld'];
                    $L['heads'][] = ['i' => $i, 'm' => 4, 'name' => 'Old Due', 'amt' => $part['addOld'], 'paid' => 0, 'na' => false, 'add' => ['type' => 'Old Due', 'by' => $u['label'], 'at' => sch_now_ms()]];
                    sch_rec_put('ofcLed', $part['ses'] . '|' . $k['sid'], $L, $uid);
                    ofc_adj_log($part['ses'], $k['sid'], ['act' => 'add', 'type' => 'Old Due', 'i' => $i, 'm' => 4, 'name' => 'Old Due', 'amt' => $part['addOld'], 'remark' => 'online payment ' . $pid, 'by' => $u['label']], $uid);
                }
                $p = ofc_price($L, $part['alloc'], 0, 0, ofc_old_due($part['ses'], $k['sid'], $L));
                $r = ofc_write_receipt($u, $uid, $part['ses'], $k['sid'], $L, $part['alloc'], $p, ['date' => $today, 'mode' => 'Online', 'bank' => 'NA', 'cheque' => 'NA', 'chequeDate' => $today,
                    'depositBank' => $bank, 'depositDate' => $today, 'remark' => 'Online (Razorpay) ' . $pid . ' · auto receipt', 'online' => ['order' => $orderId, 'pid' => $pid, 'via' => $via]]);
                $made[] = ['ses' => $part['ses'], 'no' => $r['no'], 'sid' => $k['sid'], 'name' => $r['name'], 'amt' => $r['received']];
            }
            $excess += (int)$k['amt'] - (int)$P['amt'];
        }
        $O['status'] = $excess > 0 ? 'excess' : 'paid'; $O['pid'] = $pid; $O['receipts'] = $made; $O['excess'] = $excess; $O['paidAt'] = sch_now_ms(); $O['via'] = $via;
        sch_rec_put('ofcPay', $orderId, $O, $uid);
        sch_log($u, 'ofcPay', 'Online fee ₹' . number_format($amount / 100, 2) . ' (' . $pid . ') → receipts ' . implode(', ', array_column($made, 'no'))
            . ($excess > 0 ? ' · EXTRA ₹' . number_format($excess / 100, 2) . ' not adjusted (dues were already paid) — office please check' : ''));
        $db->commit();
        return $O;
    } catch (Throwable $e) { if ($db->inTransaction()) $db->rollBack(); throw $e; }
}
