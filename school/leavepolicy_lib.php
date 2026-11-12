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
//   • Owner, 12 Nov 2026 (same day): NO leave is approved by itself — every leave
//     waits for the Principal (autoDays default 0). The Principal may set
//     autoDays > 0 later; then on-time short leave is approved on arrival.
//     Late leave is marked late and counts as without permission until approved.
//   • Salary: paid leave (per staff, default 1 a month) covers approved leave and
//     half days only. A day marked absent in Staff Attendance with no approved
//     leave is "without permission" and is ALWAYS cut.
//   • A teacher with zero absence in the month gets the full-attendance bonus
//     (default ₹300) added to that month's salary by itself.
//   • Every teacher accepts these rules once in the Teacher App (kind tchAccept,
//     r = tid, keeps the version). A new version asks everyone again.
// ============================================================================
declare(strict_types=1);

const LP_DEFAULT = ['ver' => 4, 'noticeHour' => 7, 'autoDays' => 0, 'bonus' => 300, 'warnAfter' => 2,
                    'noticeDays' => 2, 'emergency' => 1, 'maxLeave' => 4];
const LP_NO_LEAVE_DAYS = [1 => 'Monday', 6 => 'Saturday'];            // date('w') => name — leave is never granted on these days
const LP_PROGRAM_TYPES = ['Exam', 'Event', 'PTM', 'Activity'];      // calendar entries on which no leave is granted

function lp_policy(): array {
    $s = (array)(sch_rec_get('tchPolicy', 'main') ?? []);
    $p = LP_DEFAULT;
    foreach (['ver' => [1, 999], 'noticeHour' => [0, 23], 'autoDays' => [0, 10], 'bonus' => [0, 5000], 'warnAfter' => [1, 10],
              'noticeDays' => [1, 15], 'emergency' => [0, 5], 'maxLeave' => [1, 15]] as $k => [$lo, $hi])
        if (isset($s[$k]) && is_numeric($s[$k])) $p[$k] = max($lo, min($hi, (int)$s[$k]));
    if ((int)($s['ver'] ?? 0) < LP_DEFAULT['ver']) $p['ver'] = LP_DEFAULT['ver'];   // a newer rule book in the code asks everyone again
    return $p;
}

/** The staff rules as every teacher reads and accepts them (formal English; numbered; one rule each). */
function lp_rules(?array $p = null): array {
    $p ??= lp_policy();
    $h = sprintf('%d:00 a.m.', $p['noticeHour']); $nd = $p['noticeDays']; $em = $p['emergency']; $mx = $p['maxLeave'];
    return [
        'LEAVE — Leave shall be applied for only through the Teacher App (My account → Apply for leave). Requests made verbally, by phone call or on WhatsApp shall not be accepted.',
        "LEAVE — Planned leave, including leave around holidays, shall be applied for at least $nd days in advance (for example, leave for Thursday must be applied for by Tuesday). Leave is valid only after the Principal approves it. Remaining absent before approval is absence without permission.",
        "LEAVE — Emergency leave (applied for less than $nd days in advance) is limited to $em in a month and must be applied for before $h on the same day. Any further emergency request in that month shall be rejected automatically by the system.",
        'LEAVE — No leave shall be granted on a Monday or a Saturday, or on any day of a school function, activity, programme, examination or Parent-Teacher Meeting. Such requests shall be rejected automatically by the system.',
        'SALARY — One (1) paid leave is allowed in a month. Approved leave and half days beyond this shall be deducted from salary at the daily rate.',
        'SALARY — Absence without approved leave shall be deducted from salary in every case. Paid leave cannot be adjusted against it.',
        "SALARY — If a teacher's total leave in a month exceeds $mx days, salary for that month shall be paid only for the days actually worked. Sundays, holidays and paid leave of that month shall not be paid.",
        'SALARY — A teacher with full attendance in a month (no leave, no half day) shall receive a full-attendance bonus of ₹' . number_format($p['bonus']) . ' in that month\'s salary.',
        "DISCIPLINE — More than {$p['warnAfter']} days of absence without permission in a month shall result in a written warning. Repetition may lead to termination of service.",
        'CONDUCT — Every teacher shall attend only to his or her own duties. Gossip, groupism, misbehaviour or unnecessary conversation within the school premises is strictly prohibited.',
        'CONDUCT — No teacher is permitted to discuss school matters, other staff members, or any student\'s results or fees with parents or outside persons without the Principal\'s permission.',
        'CONDUCT — Teachers shall speak only in English within the school premises with students, staff and parents (Hindi and Sanskrit periods excepted).',
        'CONDUCT — Teachers shall come to school in the prescribed school uniform / dress code on every working day.',
        'WORK — All daily work shall be completed in the Teacher App on the same day: attendance before the cut-off time, class work and homework diary for every period, homework and copy checking, daily check, weekly lesson plan, syllabus progress and marks entry. Work not entered in the app shall be treated as not done.',
        'WORK — Other teaching duties — arrangement periods, remedial classes, examinations, competitions and any duty given by the Principal — are compulsory and shall be completed on time.',
        'RESULTS — Class teachers and subject teachers are responsible for the results of their classes: at least 80% of the students of every class and subject must score above 75%. Results below this standard shall be reviewed with the teacher concerned.',
        'REVIEW — A teacher\'s evaluation, annual increment and continuation of service depend entirely on the work record in the Teacher App, the growth of his or her students (attendance, reading and learning levels, results), attendance and conduct.',
        'These rules apply equally to every teacher. Acceptance in the Teacher App is binding. Whenever the rules change, the app shall ask every teacher to accept them again.',
    ];
}

function lp_accepted(string $tid): ?array {
    $a = sch_rec_get('tchAccept', $tid);
    return is_array($a) && (int)($a['ver'] ?? 0) === lp_policy()['ver'] ? $a : null;
}

/** Days of school programmes (calendar Exam / Event / PTM / Activity, and exams) between two dates => [date => name]. */
function lp_program_days(string $from, string $to): array {
    require_once __DIR__ . '/salary_lib.php';
    $out = [];
    $mark = function (string $a, string $b, string $name) use ($from, $to, &$out) {
        $b = $b ?: $a; if ($b < $from || $a > $to) return;      // event entirely outside the leave
        $a = max($a, $from); $b = min($b, $to);
        for ($d = $a, $i = 0; $d <= $b && $i < 120; $i++, $d = date('Y-m-d', strtotime($d . ' +1 day'))) $out[$d] ??= $name;
    };
    foreach (sal_rows('cal') as $e) if (in_array($e['type'] ?? '', LP_PROGRAM_TYPES, true) && !empty($e['date'])) $mark((string)$e['date'], (string)($e['to'] ?? ''), (string)($e['title'] ?? $e['type']));
    foreach ((array)(sch_rec_get('exams', 'main') ?? []) as $e) if (is_array($e) && !empty($e['date'])) {
        $end = (string)($e['to'] ?? ''); if ($end === '' && !empty($e['sheet'])) { $ds = array_column((array)$e['sheet'], 'date'); sort($ds); $end = (string)end($ds); }
        $mark((string)$e['date'], $end, (string)($e['name'] ?? 'Examination'));
    }
    return $out;
}

/**
 * A teacher's new leave request (status Pending) just reached the server. Stamp it, check it against the rules,
 * reject it by itself when a rule forbids it (with the reason), mark emergency / late. $nowTs = server time.
 */
function lp_on_leave(array $d, string $tid, ?int $nowTs = null): array {
    require_once __DIR__ . '/salary_lib.php';
    $nowTs ??= time();
    $p = lp_policy(); $today = date('Y-m-d', $nowTs); $from = (string)($d['from'] ?? ''); $to = (string)($d['to'] ?? $from);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) || $to < $from) return $d;
    $d['appliedAt'] = $d['appliedAt'] ?? $nowTs * 1000;
    $reject = function (string $why) use (&$d, $nowTs) { $d['status'] = 'Rejected'; $d['decidedAt'] = $nowTs * 1000; $d['decidedBy'] = 'Auto (staff rules)'; $d['rejectReason'] = $why; return $d; };
    // Rule: no leave on Monday / Saturday
    for ($x = $from, $i = 0; $x <= $to && $i < 62; $i++, $x = date('Y-m-d', strtotime($x . ' +1 day'))) {
        $w = (int)date('w', strtotime($x));
        if (isset(LP_NO_LEAVE_DAYS[$w])) return $reject('Leave is not granted on a ' . LP_NO_LEAVE_DAYS[$w] . ' (' . date('d M Y', strtotime($x)) . ').');
    }
    // Rule: no leave on a school programme day
    $prog = lp_program_days($from, $to);
    if ($prog) { $x = array_key_first($prog); return $reject('Leave is not granted on a school programme day: ' . $prog[$x] . ' (' . date('d M Y', strtotime($x)) . ').'); }
    // Notice: planned (≥ noticeDays ahead) or emergency
    $ahead = (int)floor((strtotime($from) - strtotime($today)) / 86400);
    $d['emergency'] = $ahead < $p['noticeDays'];
    $d['late'] = $from < $today || ($from === $today && (int)date('G', $nowTs) >= $p['noticeHour']);
    if ($d['emergency']) {
        $used = 0; $ym = substr($from, 0, 7);
        foreach (sal_rows('leave') as $l) if (($l['tid'] ?? '') === $tid && !empty($l['emergency']) && ($l['status'] ?? '') !== 'Rejected' && substr((string)($l['from'] ?? ''), 0, 7) === $ym) $used++;
        if ($used >= $p['emergency']) return $reject("Emergency leave limit reached ({$p['emergency']} in a month). Planned leave must be applied for {$p['noticeDays']} days in advance.");
        if ($d['late']) return $reject("Emergency leave must be applied for before {$p['noticeHour']}:00 a.m. on the same day.");
    }
    $days = (int)round((strtotime($to) - strtotime($from)) / 86400) + 1;
    if (!$d['emergency'] && $p['autoDays'] > 0 && $days <= $p['autoDays']) {
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
