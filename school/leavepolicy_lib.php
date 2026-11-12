<?php
// ============================================================================
//  Staff leave policy (12 Nov 2026, owner: "long term solution — sab automated
//  karo, kisi ki jarurat na pade"). Used by api.php (sync of 'leave'),
//  teacher_api.php (policy / accept / leavereport) and salary_lib.php (cut + bonus).
//
//  The rules (record tchPolicy/main overrides the defaults; Principal edits them
//  in the Teacher App → Staff → Leave policy):
//   • Leave is asked in the app. A leave asked for TOMORROW or later, or for
//     today before the notice hour (default 07:00), is ON TIME.
//   • On-time leave of up to autoDays days (default 2) is approved by itself the
//     moment it reaches the server — the arrangement fills those bells by itself.
//     Longer leave waits for the Principal. Late leave stays Pending and is
//     marked late; the Principal may still approve it.
//   • Salary: paid leave (per staff, default 1 a month) covers approved leave and
//     half days only. A day marked absent in Staff Attendance with no approved
//     leave is "without permission" and is ALWAYS cut.
//   • A teacher with zero absence in the month gets the full-attendance bonus
//     (default ₹300) added to that month's salary by itself.
//   • Every teacher accepts these rules once in the Teacher App (kind tchAccept,
//     r = tid, keeps the version). A new version asks everyone again.
// ============================================================================
declare(strict_types=1);

const LP_DEFAULT = ['ver' => 1, 'noticeHour' => 7, 'autoDays' => 2, 'bonus' => 300, 'warnAfter' => 2];

function lp_policy(): array {
    $s = (array)(sch_rec_get('tchPolicy', 'main') ?? []);
    $p = LP_DEFAULT;
    foreach (['ver' => [1, 999], 'noticeHour' => [0, 23], 'autoDays' => [0, 10], 'bonus' => [0, 5000], 'warnAfter' => [1, 10]] as $k => [$lo, $hi])
        if (isset($s[$k]) && is_numeric($s[$k])) $p[$k] = max($lo, min($hi, (int)$s[$k]));
    return $p;
}

/** The rules as the teacher reads and accepts them (Hinglish, one line each). */
function lp_rules(?array $p = null): array {
    $p ??= lp_policy();
    $h = sprintf('%d:00 baje', $p['noticeHour']);
    return [
        'Chhutti sirf Teacher App se maangi jayegi (My account → Apply for leave). Phone / WhatsApp par boli gayi chhutti nahi maani jayegi.',
        "Kal ya usse aage ki chhutti, ya aaj ki chhutti subah $h se pehle maangi gayi to wo time par maani jayegi" . ($p['autoDays'] > 0 ? " — {$p['autoDays']} din tak ki aisi chhutti apne aap approve ho jayegi aur aapke bell ka arrangement apne aap lag jayega." : '.'),
        ($p['autoDays'] > 0 ? "{$p['autoDays']} din se lambi chhutti Principal approve karenge. " : '') . "Aaj ki chhutti $h ke baad maangi gayi to wo 'late' hai — Principal ke approve karne tak bina ijazat ki maani jayegi.",
        'Har mahine 1 paid leave milti hai (Principal kisi staff ki zyada rakh sakte hain). Approved chhutti aur half day isi me se kat-te hain; usse zyada din ki salary katti hai.',
        'Bina approved chhutti ke school na aana (Staff Attendance me absent) = bina ijazat chhutti. Us din ki salary hamesha katti hai — paid leave se nahi bharti.',
        'Mahine me ek bhi din absent nahi (na chhutti, na half day) to ₹' . number_format($p['bonus']) . ' full-attendance bonus us mahine ki salary me apne aap judega.',
        "Ek mahine me {$p['warnAfter']} baar se zyada bina ijazat chhutti par likhit warning di jayegi; baar-baar hone par naukri par faisla liya ja sakta hai.",
        'Saal ka increment attendance record dekh kar tay hoga.',
        'Ye niyam school ke sabhi teachers par barabar lagte hain. Niyam badalne par app aapse dobara sehmati maangega.',
    ];
}

function lp_accepted(string $tid): ?array {
    $a = sch_rec_get('tchAccept', $tid);
    return is_array($a) && (int)($a['ver'] ?? 0) === lp_policy()['ver'] ? $a : null;
}

/**
 * A teacher's new leave request (status Pending) just reached the server: stamp when it came,
 * decide on time / late, and approve it by itself when the policy allows. $nowTs = server time.
 */
function lp_on_leave(array $d, string $tid, ?int $nowTs = null): array {
    $nowTs ??= time();
    $p = lp_policy(); $today = date('Y-m-d', $nowTs); $from = (string)($d['from'] ?? ''); $to = (string)($d['to'] ?? $from);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) return $d;
    $d['appliedAt'] = $d['appliedAt'] ?? $nowTs * 1000;
    $onTime = $from > $today || ($from === $today && (int)date('G', $nowTs) < $p['noticeHour']);
    $days = (int)round((strtotime($to) - strtotime($from)) / 86400) + 1;
    $d['late'] = !$onTime;
    if ($onTime && $p['autoDays'] > 0 && $days >= 1 && $days <= $p['autoDays']) {
        $d['status'] = 'Approved'; $d['decidedAt'] = $nowTs * 1000; $d['decidedBy'] = 'Auto (leave policy)'; $d['auto'] = true;
    }
    return $d;
}

/** One month, every teacher: approved leave, late/pending leave, absent without permission, half days, bonus. */
function lp_month(string $ym): array {
    require_once __DIR__ . '/salary_lib.php';
    $p = lp_policy();
    $work = sal_workdays($ym, sal_rows('cal')); $att = sal_rows('staffAtt', $ym); $leaves = sal_rows('leave');
    $acc = sal_rows('tchAccept'); $left = (array)((sch_rec_get('settings', 'main') ?? [])['leftTeachers'] ?? []);
    $today = date('Y-m-d'); $past = array_values(array_filter($work, fn($d) => $d <= $today));
    $out = [];
    foreach (sal_rows('teacher') as $tid => $t) {
        if (isset($left[$tid])) continue;
        $a = sal_absent_days((string)$tid, $past, $att, $leaves);
        $late = 0; $pending = 0; $auto = 0;
        foreach ($leaves as $l) {
            if (($l['tid'] ?? '') !== (string)$tid || substr((string)($l['from'] ?? ''), 0, 7) > $ym || substr((string)($l['to'] ?? ''), 0, 7) < $ym) continue;
            if (!empty($l['late'])) $late++;
            if (($l['status'] ?? '') === 'Pending') $pending++;
            if (!empty($l['auto'])) $auto++;
        }
        $out[] = ['tid' => (string)$tid, 'name' => (string)($t['name'] ?? $tid), 'leave' => $a['leave'], 'half' => $a['half'], 'unapproved' => $a['absent'],
            'late' => $late, 'pending' => $pending, 'auto' => $auto, 'total' => $a['total'],
            'bonus' => $a['total'] == 0 && $past ? $p['bonus'] : 0, 'warn' => $a['absent'] > $p['warnAfter'],
            'accepted' => (int)(($acc[(string)$tid] ?? [])['ver'] ?? 0) === $p['ver'], 'acceptedAt' => ($acc[(string)$tid] ?? [])['at'] ?? null];
    }
    usort($out, fn($x, $y) => [$y['unapproved'], $y['total'], $x['name']] <=> [$x['unapproved'], $x['total'], $y['name']]);
    return ['ym' => $ym, 'daysSoFar' => count($past), 'workDays' => count($work), 'policy' => $p, 'rows' => $out];
}
