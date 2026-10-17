<?php
// ============================================================
// School office — fee hisaab (Accountant + Reception)
//
// Bakaya (dues) kaise banta hai — saal April se March:
//   pichhla bakaya (opening, accountant bharta hai)
// + saal ki fees (exam, digital, sports, health — session ke pehle mahine)
// + har mahine ki fees × session shuru se ab tak ke mahine (is mahine samet)
// − chhoot (concession, rupaye me, saal bhar ki)
// − jama ki gayi raseedein (radd ki hui nahi)
// "Free" scheme wale bachche (jaise company/FD scheme) par mahine aur saal
// ki fees nahi lagti; sirf pichhla bakaya.
//
// Ye file sirf hisaab karti hai — kuchh likhti nahi.
// ============================================================
declare(strict_types=1);

const OF_MODES    = ['Cash', 'UPI', 'Cheque', 'Bank transfer', 'DD'];
const OF_OUTCOMES = ['Promised to pay', 'Not answered', 'Switched off', 'Call back later', 'Will visit school', 'Already paid', 'Wrong number'];

function of_names(): array {
    $s = sch_rec_get('settings', 'main') ?? [];
    $a = trim((string)($s['accountantName'] ?? '')); $r = trim((string)($s['receptionName'] ?? ''));
    return ['accountant' => $a !== '' ? $a : 'Akash Tiwari', 'reception' => $r !== '' ? $r : 'Shiva Rai'];
}

function of_session(): array {
    $s = sch_rec_get('settings', 'main') ?? [];
    $start = (string)($s['sessionStart'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) $start = (date('n') >= 4 ? date('Y') : (string)((int)date('Y') - 1)) . '-04-01';
    return ['start' => $start, 'session' => (string)($s['session'] ?? ''), 'school' => (string)($s['school'] ?? 'SN Mishra Singapore International School'), 'place' => (string)($s['place'] ?? 'Bhadohi')];
}

// Session shuru se aaj tak kitne mahine ki fees lag chuki (is mahine samet), 0..12
function of_months(string $start, string $today): int {
    if ($today < $start) return 0;
    $m = ((int)substr($today, 0, 4) - (int)substr($start, 0, 4)) * 12 + (int)substr($today, 5, 2) - (int)substr($start, 5, 2) + 1;
    return max(0, min(12, $m));
}

// app.js ka feeFor() — dono jagah ek hi hisaab (badlein to dono badlein)
function of_fee_for(array $s, array $family, ?array $F): ?array {
    if (!$F) return null;
    $cls = (string)($s['cls'] ?? '');
    $mon = $F['monthly'] ?? [];
    $comp = isset($mon[$cls]) && $mon[$cls] !== null && $mon[$cls] !== '' ? (int)$mon[$cls] : null;
    usort($family, fn($a, $b) => ((int)($mon[$b['cls'] ?? ''] ?? 0) <=> (int)($mon[$a['cls'] ?? ''] ?? 0)) ?: strcmp((string)($a['adm'] ?? ''), (string)($b['adm'] ?? '')));
    $pos = 0; foreach ($family as $i => $x) if (($x['id'] ?? '') === ($s['id'] ?? '')) $pos = $i;
    $third = count($family) >= 3 && $pos >= 2;
    $disc = $third && $comp !== null ? (int)round($comp * (float)($F['thirdChildPct'] ?? 0) / 100) : 0;
    $km = $s['km'] ?? null; $bus = 0;
    if ($km !== null && $km !== '' && (float)$km > 0) {
        $bus = null;
        foreach (($F['transport'] ?? []) as $t) if (($t['upto'] ?? null) === null || (float)$km <= (float)$t['upto']) { $bus = isset($t['amt']) && $t['amt'] !== null ? (int)$t['amt'] : null; break; }
    }
    $yearly = 0;
    foreach (($F['yearly'] ?? []) as $y) if (!in_array($cls, $y['skip'] ?? [], true)) $yearly += (int)($y['amt'] ?? 0);
    $month = $comp === null || $bus === null ? null : $comp - $disc + $bus;
    return ['comp' => $comp, 'disc' => $disc, 'third' => $third, 'bus' => $bus, 'month' => $month, 'yearly' => $yearly];
}

function of_phone_key(array $s): string { $p = sch_phone10((string)($s['phone'] ?? '')); return $p !== '' ? $p : 'sid:' . ($s['id'] ?? ''); }

// Ek bachche ka poora hisaab
function of_dues(array $s, array $family, ?array $F, array $set, array $pays, string $start, string $today): array {
    $f = of_fee_for($s, $family, $F) ?? ['comp' => null, 'disc' => 0, 'third' => false, 'bus' => 0, 'month' => null, 'yearly' => 0];
    $free = !empty($set['free']);
    $custom = isset($set['custom']) && $set['custom'] !== null && $set['custom'] !== '' ? (int)$set['custom'] : null;
    $monthly = $free ? 0 : ($custom ?? $f['month']);
    $months = of_months($start, $today);
    $yearly = $free || $months === 0 ? 0 : (int)$f['yearly'];
    $opening = (int)($set['opening'] ?? 0);
    $conc = (int)($set['concession'] ?? 0);
    $paid = 0; $last = null;
    foreach ($pays as $p) {
        if (($p['sid'] ?? '') !== ($s['id'] ?? '') || !empty($p['cancelled']) || (string)($p['date'] ?? '') < $start) continue;
        $paid += (int)$p['amt']; if ($last === null || $p['date'] > $last) $last = $p['date'];
    }
    $charged = $opening + $yearly + (int)($monthly ?? 0) * $months;
    return ['monthly' => $monthly, 'custom' => $custom !== null, 'months' => $months, 'yearly' => $yearly, 'opening' => $opening,
            'concession' => $conc, 'paid' => $paid, 'due' => $charged - $conc - $paid, 'free' => $free, 'scheme' => (string)($set['scheme'] ?? ''),
            'missing' => !$free && $monthly === null, 'third' => $f['third'], 'bus' => $f['bus'], 'lastPaid' => $last];
}

// Rupaye shabdon me (raseed ke liye) — Indian tareeke se: lakh, crore
function of_words(int $n): string {
    $o = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
    $t = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
    $two = fn(int $x) => $x < 20 ? $o[$x] : trim($t[intdiv($x, 10)] . ' ' . $o[$x % 10]);
    $three = fn(int $x) => trim(($x >= 100 ? $o[intdiv($x, 100)] . ' Hundred ' : '') . $two($x % 100));
    if ($n === 0) return 'Zero';
    $parts = [];
    foreach ([[10000000, 'Crore'], [100000, 'Lakh'], [1000, 'Thousand']] as [$v, $w]) if ($n >= $v) { $parts[] = $two(intdiv($n, $v)) . ' ' . $w; $n %= $v; }
    if ($n) $parts[] = $three($n);
    return implode(' ', $parts);
}
