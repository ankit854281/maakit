<?php
// ============================================================
// One-time data changes the owner asked for, done on the server so nobody has to tap through the ERP.
// Each runs ONCE (flag in `salSeed`, a "sal" kind that never goes to phones) the first time anyone's
// app loads data (api.php load/since) or the Salary app opens its sheet, and is written as uid `system`
// with a line in the Activity Log.
// ============================================================
declare(strict_types=1);
require_once __DIR__ . '/salary_lib.php';

// Shared: make one person a class teacher. The teacher = the active teacher with that name, else the same person brought
// back under the OLD ID from settings.leftTeachers (old attendance / salary keep matching), else a new teacher T<n>.
// A salary entry that was closed (until / inactive) is opened again; a NEW teacher's salary starts from $fromYm (salary not
// set — the Manager sets it). Only that class's `ct` changes; the previous class teacher stays on the staff. Runs once per
// flag and waits while classes/main does not exist yet.
function sch_seed_class_teacher(string $flag, string $match, string $newName, string $role, string $cls, string $fromYm, string $why): void {
    if (sch_rec_get('salSeed', $flag)) return;
    $classes = sch_rec_get('classes', 'main');
    if (!is_array($classes) || !array_filter($classes, fn($c) => ($c['c'] ?? '') === $cls)) return;
    $set = sch_rec_get('settings', 'main') ?? []; $left = (array)($set['leftTeachers'] ?? []);
    $teachers = sal_rows('teacher'); $done = []; $tid = null;
    foreach ($teachers as $k => $t) if (sal_name_same((string)($t['name'] ?? ''), $match)) { $tid = (string)$k; break; }
    if ($tid === null) {
        foreach ($left as $k => $n) if (sal_name_same((string)$n, $match)) { $tid = (string)$k; break; }
        if ($tid !== null) {
            sch_rec_put('teacher', $tid, ['name' => (string)$left[$tid], 'role' => $role, 'phone' => ''], 'system');
            unset($left[$tid]); $set['leftTeachers'] = $left; sch_rec_put('settings', 'main', $set, 'system');
            $done[] = "$newName back on the staff (old ID $tid)";
        } else {
            $n = 1; while (isset($teachers['T' . $n]) || isset($left['T' . $n])) $n++; $tid = 'T' . $n;
            sch_rec_put('teacher', $tid, ['name' => $newName, 'role' => $role, 'phone' => ''], 'system');
            if (!sch_rec_get('salStaff', 't:' . $tid)) sch_rec_put('salStaff', 't:' . $tid, ['monthly' => null, 'paidLeave' => 1, 'active' => true, 'from' => $fromYm . '-01'], 'system');
            $done[] = "$newName added as a teacher ($tid) — set the PIN in Settings and the salary in the Salary app";
        }
    }
    $ss = sch_rec_get('salStaff', 't:' . $tid);
    if ($ss && (($ss['until'] ?? '') !== '' || ($ss['active'] ?? true) === false)) { unset($ss['until']); $ss['active'] = true; sch_rec_put('salStaff', 't:' . $tid, $ss, 'system'); $done[] = 'salary entry active again'; }
    $all = sal_rows('teacher'); $old = '';
    foreach ($classes as &$c) if (($c['c'] ?? '') === $cls) { $old = (string)($c['ct'] ?? ''); $c['ct'] = $tid; } unset($c);
    if ($old !== $tid) { sch_rec_put('classes', 'main', array_values($classes), 'system'); $done[] = "$cls class teacher: " . (string)($all[$old]['name'] ?? ($left[$old] ?? ($old ?: 'none'))) . ' → ' . (string)($all[$tid]['name'] ?? $newName); }
    sch_rec_put('salSeed', $flag, ['at' => sch_now_ms(), 'tid' => $tid, 'done' => $done], 'system');
    sch_log(['uid' => 'system', 'role' => 'system', 'label' => 'System'], 'Settings', "Owner request ($why): " . (implode('; ', $done) ?: "$newName was already $cls class teacher"));
}

// Owner, 18 Nov 2026: "Ritu Ma'am fir se aa gayi hain, UKG class teacher bana do, add kar do, Rati Ma'am ko hata do".
// UKG → Ritu (that removes Rati Ma'am from UKG; she stays on the staff).
function sch_seed_ritu_ukg(): void {
    sch_seed_class_teacher('ritu-ukg-2026-11', 'Ritu Singh', "Ritu Singh Ma'am", 'UKG Class Teacher', 'UKG', '2026-11', '18 Nov 2026');
}

// Owner, 18 Nov 2026: "Ankit Tripathi Sir ko add, class 8th ka" — new teacher, Class 8th class teacher, salary from November.
function sch_seed_ankit_8th(): void {
    sch_seed_class_teacher('ankit-8th-2026-11', 'Ankit Tripathi', 'Ankit Tripathi Sir', 'Class 8th Class Teacher', '8th', '2026-11', '18 Nov 2026');
}

// ============================================================
// LKG Half Yearly 2026-27 marks from the class register (owner, 19 Nov 2026: "Ye marks add dijiye please" — two photos of
// the LKG register: Hindi, English, Math, EVS, Drawing; Pre test, Copy, SEA, hf for 30 children). Saved as Term-1 (the
// Half Yearly) like the 5th-class register. Register Pre test is out of 20 → halved to the card's 10 (same as 5th);
// Copy → Notebook /5, SEA /5, hf → Term Exam /80, Ab → AB, blank stays blank.
// Safe: a child already having ANY mark in a subject keeps it (nothing typed by a teacher is overwritten); children are
// matched by name inside LKG (exact words first, then the only close match); register names that are not found or
// match more than one child (two "Shivansh Rai") are skipped and named in the Activity Log for the class teacher.
// ============================================================
const SCH_LKG_HY_SUBJ = ['Hindi', 'English', 'Math', 'EVS', 'Drawing'];
const SCH_LKG_HY = [
    [1, 'Abhinav Rai', [[8, 5, 5, 61], [8, 5, 5, 73], [8, 5, 5, 'AB'], [7, 5, 5, 79], [5, 5, 5, 65]]],
    [2, 'Ananya Pandey', [[6, 5, 5, 67], [8, 5, 5, 75], [10, 5, 5, 67], [9, 5, 5, 80], [7, 5, 5, 65]]],
    [3, 'Ansh Prajapati', [[8, 5, 5, 75], [8, 5, 5, 76], [10, 5, 5, 71], [8, 5, 5, 80], [8, 5, 5, 70]]],
    [4, 'Arushi Chauhan', [[8, 5, 5, 77], [7, 5, 5, 77], [10, 5, 5, 77], [7, 5, 5, 78], [6, 5, 5, 65]]],
    [5, 'Arnay Rai', [[6, 5, 5, 70], [7, 5, 5, 75], [5, 5, 5, 57], [6, 5, 5, 'AB'], [5, 5, 5, 60]]],
    [6, 'Divas Arya', [[8, 5, 5, 78], [8, 5, 5, 66], [8, 5, 5, 55], [5, 5, 5, 'AB'], [7, 5, 5, 'AB']]],
    [7, 'Kritika', [[7, 5, 5, 75], [6, 5, 5, 76], [9, 5, 5, 80], [5, 5, 5, 'AB'], [9, 5, 5, 65]]],
    [8, 'Ruhi Yadav', [[7, 5, 5, 64], [8, 5, 5, 80], [7, 5, 5, 63], [5, 5, 5, 80], [6, 5, 5, 70]]],
    [9, 'Nikki Gautam', [[7, 5, 5, 80], [10, 5, 5, 80], [10, 5, 5, 80], [7, 5, 5, 'AB'], [5, 5, 5, 70]]],
    [10, 'Manshvi Raghuvanshi', [[9, 5, 5, 80], [9, 5, 5, 80], [10, 5, 5, 80], [9, 5, 5, 80], [7, 5, 5, 55]]],
    [11, 'Shiva Saroj', [[12, 5, 5, 75], [14, 5, 5, 75], [5, 5, 5, 65], [7, 5, 5, 80], [9, 5, 5, 70]]],
    [12, 'Parth Pandey', [[9, 5, 5, 77], [9, 5, 5, 77], [9, 5, 5, 74], [9, 5, 5, 'AB'], [7, 5, 5, 70]]],
    [13, 'Rishabh Pal', [[6, 5, 5, 75], [7, 5, 5, 74], [4, 5, 5, 65], [5, 5, 5, 66], [8, 5, 5, 65]]],
    [14, 'Suyam Tiwari', [[16, 5, 5, 80], [9, 5, 5, 79], [9, 5, 5, 80], [9, 5, 5, 80], [9, 5, 5, 70]]],
    [15, 'Shivansh Rai', [[7, 5, 5, 65], [7, 5, 5, 62], [10, 5, 5, 55], [4, 5, 5, 'AB'], [6, 5, 5, 60]]],
    [16, 'Shreyam Bind', [[4, 5, 5, 'AB'], [8, 5, 5, 'AB'], [8, 5, 5, 'AB'], [6, 5, 5, 'AB'], [7, 5, 5, 'AB']]],
    [17, 'Yansh Pasi', [[8, 5, 5, 77], [10, 5, 5, 80], [9, 5, 5, 80], [8, 5, 5, 80], [8, 5, 5, 55]]],
    [18, 'Ananya Tiwari', [[7, 5, 5, 'AB'], [7, 5, 5, 'AB'], [5, 5, 5, 59], [5, 5, 5, 46], [5, 5, 5, 55]]],
    [19, 'Vaishnavi Bind', [[10, 5, 5, 'AB'], [7, 5, 5, 'AB'], [8, 5, 5, 'AB'], [12, 5, 5, 'AB'], [7, 5, 5, 'AB']]],
    [20, 'Diksha Kumari', [[7, 5, 5, 75], [8, 5, 5, 80], [10, 5, 5, 78], [7, 5, 5, 80], [6, 5, 5, 70]]],
    [21, 'Ananya Maurya', [[7, 5, 5, 77], [9, 5, 5, 'AB'], [9, 5, 5, 55], [7, 5, 5, 43], [7, 5, 5, 70]]],
    [22, 'Pragya Sharma', [[8, 5, 5, 77], [8, 5, 5, 77], [8, 5, 5, 75], [8, 5, 5, 62], [5, 5, 5, 70]]],
    [23, 'Shivansh Singh', [[11, 5, 5, 75], [13, 5, 5, 77], [9, 5, 5, 66], [6, 5, 5, 61], [8, 5, 5, 70]]],
    [24, 'Siddharth Arya', [[6, 5, 5, 76], [9, 5, 5, 76], [8, 5, 5, 59], [7, 5, 5, 38], [9, 5, 5, 60]]],
    [25, 'Arnav Sharma', [[7, 5, 5, 72], [8, 5, 5, 72], [6, 5, 5, 75], [7, 5, 5, 'AB'], [7, 5, 5, 70]]],
    [26, 'Parishu Saroj', [[11, 5, 5, 67], [7, 5, 5, 76], [7, 5, 5, 64], [10, 5, 5, 61], [6, 5, 5, 55]]],
    [27, 'Samarth Jaiswal', [[7, 5, 5, ''], [8, 5, 5, 80], [8, 5, 5, 65], [8, 5, 5, 47], [8, 5, 5, 55]]],
    [28, 'Shivansh Rai', [[7, 5, 5, 65], [12, 5, 5, 77], [7, 5, 5, 65], [7, 5, 5, 'AB'], [7, 5, 5, 60]]],
    [29, 'Kritika Rai', [[5, 5, 5, 72], [12, 5, 5, 70], [6, 5, 5, 75], [6, 5, 5, 'AB'], [5, 5, 5, 70]]],
    [30, 'Kavya', [[6, 5, 5, 71], [13, 5, 5, 77], [7, 5, 5, 59], [6, 5, 5, 'AB'], [6, 5, 5, 'AB']]],
];
const SCH_LKG_HY_CHECK = [
    '5|EVS' => 'hf: number struck, red Ab written',
    '6|EVS' => 'hf: number struck, red Ab written',
    '7|EVS' => 'hf: number struck, red Ab written',
    '9|EVS' => 'hf: "46" and red Ab both written',
    '12|EVS' => 'hf: "79" and red Ab both written',
    '13|EVS' => 'hf: 66 or 68',
    '18|EVS' => 'hf: 46 written over red Ab',
    '24|Math' => 'hf: 59 or 51',
    '27|Hindi' => 'hf blank in register'
];
function sch_seed_lkg_hy_subject(string $reg, array $alloc): string {
    $k = fn($s) => preg_replace('/[^a-z]/', '', strtolower($s));
    $syn = ['math' => ['math', 'maths', 'mathematics', 'mathsandmental', 'numbers'], 'evs' => ['evs', 'environmentalstudies', 'environment', 'environmentalscience', 'science'],
            'drawing' => ['drawing', 'art', 'artcraft', 'artandcraft', 'drawingcolouring', 'drawingandcolouring'], 'hindi' => ['hindi'], 'english' => ['english']][$k($reg)] ?? [$k($reg)];
    foreach ($alloc as $s) if (in_array($k($s), $syn, true)) return $s;
    foreach ($alloc as $s) foreach ($syn as $x) if (str_starts_with($k($s), $x)) return $s;
    return $reg;
}
function sch_seed_lkg_hy(): void {
    $flag = 'lkg-halfyearly-2026-27';
    if (sch_rec_get('salSeed', $flag)) return;
    $kids = array_filter(sal_rows('student'), fn($s) => ($s['cls'] ?? '') === 'LKG');
    if (count($kids) < 5) return;                                   // LKG children not in the ERP yet — try on a later load
    $ses = '2026-27'; $exam = 'Term-1';
    $alloc = []; foreach ((array)sch_rec_get('alloc', 'main') as $al) if (($al['c'] ?? '') === 'LKG' && ($al['s'] ?? '') !== '' && !in_array($al['s'], $alloc, true)) $alloc[] = (string)$al['s'];
    $subj = array_map(fn($s) => sch_seed_lkg_hy_subject($s, $alloc), SCH_LKG_HY_SUBJ);
    // match register names → LKG children
    $nameCount = array_count_values(array_map(fn($r) => strtolower(trim($r[1])), SCH_LKG_HY));
    $words = fn($n) => (function ($w) { sort($w); return implode(' ', $w); })(sal_name_words($n));
    $claimed = []; $match = []; $skip = [];
    foreach (SCH_LKG_HY as [$n, $name]) {                              // pass 1: same words exactly
        if ($nameCount[strtolower(trim($name))] > 1) { $skip[$n] = "$n. $name (two children with this name in the register)"; continue; }
        $hit = array_keys(array_filter($kids, fn($s) => $words((string)($s['name'] ?? '')) === $words($name)));
        if (count($hit) === 1 && !isset($claimed[$hit[0]])) { $match[$n] = $hit[0]; $claimed[$hit[0]] = 1; }
        elseif (count($hit) > 1) $skip[$n] = "$n. $name (more than one child of this name in the ERP)";
    }
    foreach (SCH_LKG_HY as [$n, $name]) {                              // pass 2: the only close match among children left
        if (isset($match[$n]) || isset($skip[$n])) continue;
        $hit = array_keys(array_filter($kids, fn($s, $id) => !isset($claimed[$id]) && sal_name_same((string)($s['name'] ?? ''), $name), ARRAY_FILTER_USE_BOTH));
        if (!$hit) {                                                     // pass 3: first name spelt a little differently (Suyam / Swayam), rest the same
            $rw = sal_name_words($name);
            $hit = array_keys(array_filter($kids, function ($s, $id) use ($claimed, $rw) {
                if (isset($claimed[$id])) return false; $kw = sal_name_words((string)($s['name'] ?? ''));
                if (!$kw || !$rw || levenshtein($kw[0], $rw[0]) > 2 || min(strlen($kw[0]), strlen($rw[0])) < 4) return false;
                $a = array_slice($kw, 1); $b = array_slice($rw, 1); return !array_diff($a, $b) || !array_diff($b, $a);
            }, ARRAY_FILTER_USE_BOTH));
        }
        if (count($hit) === 1) { $match[$n] = $hit[0]; $claimed[$hit[0]] = 1; } else $skip[$n] = "$n. $name (" . (count($hit) ? 'more than one close name' : 'not found in ERP LKG') . ')';
    }
    $half = fn($v) => $v === 'AB' || $v === '' ? $v : round($v / 2, 2) + 0;
    $filled = 0; $kept = 0; $kidsDone = [];
    foreach (SCH_LKG_HY_SUBJ as $i => $reg) {
        $key = "$ses|$exam|LKG|{$subj[$i]}"; $rec = sch_rec_get('prcMarks', $key) ?? ['subject' => $subj[$i], 'rows' => []];
        $rows = (array)($rec['rows'] ?? []); $changed = false;
        foreach (SCH_LKG_HY as [$n, $name, $m]) {
            if (!isset($match[$n])) continue; $sid = $match[$n];
            $old = (array)($rows[$sid] ?? []);
            if (array_filter($old, fn($x) => (string)$x !== '')) { $kept++; continue; }
            [$pt, $cp, $sea, $hf] = $m[$i];
            $rows[$sid] = ['pt' => $half($pt), 'nb' => $cp, 'sea' => $sea, 'te' => $hf]; $filled++; $changed = true; $kidsDone[$sid] = 1;
        }
        if ($changed) sch_rec_put('prcMarks', $key, ['subject' => $subj[$i], 'rows' => $rows, 'by' => 'System (LKG register, owner 19 Nov 2026)', 'at' => sch_now_ms()], 'system');
    }
    $checks = []; foreach (SCH_LKG_HY_CHECK as $k => $t) { [$n, $s] = explode('|', $k); $nm = ''; foreach (SCH_LKG_HY as $r) if ($r[0] === (int)$n) $nm = $r[1]; $checks[] = "$n. $nm $s — $t"; }
    sch_rec_put('salSeed', $flag, ['at' => sch_now_ms(), 'matched' => $match, 'skipped' => array_values($skip), 'filled' => $filled, 'kept' => $kept, 'subjects' => $subj], 'system');
    $sys = ['uid' => 'system', 'role' => 'system', 'label' => 'System'];          // log lines are cut at 1000 characters — one line per part
    sch_log($sys, 'Progress Report', 'LKG Half Yearly (Term-1) marks from the register: ' . count($kidsDone) . ' children, ' . $filled . ' subject entries filled (' . implode(', ', $subj) . ')' . ($kept ? "; $kept already entered — kept" : ''));
    foreach (array_chunk(array_values($skip), 8) as $part) sch_log($sys, 'Progress Report', 'LKG Half Yearly — NOT entered, fill by hand: ' . implode('; ', $part));
    sch_log($sys, 'Progress Report', 'LKG Half Yearly — please check against the register: ' . implode('; ', $checks));
}

// ============================================================
// Mamta Ma'am's Hindi + Sanskrit syllabus 2026-27 from her register (owner, 23 Nov 2026: "Ye syllabus add karige mamta maam
// ka hindi or sanskrit" — 9 photos: Hindi 6th–10th with Vyakaran, Sanskrit 6th–8th; re-done 25 Nov 2026 "mamta maam ka
// syllabus add nahi kyu kiya" — the 23 Nov push never reached GitHub). Chapters go into `syl` with `book` and `ex` (exam ids)
// like the Teacher App's Planner → Syllabus. Register sections → exams: UT-1 → ut1+hy, UT-2 → ut2+hy, H.Y → hy,
// UT-3 → ut3+ann, UT-4 → ut4+ann, Annual → ann; 6th Vyakaran note says Annual = 14 to 27, so its UT-3 is ut3 only.
// Key = class|subject of Mamta's timetable subject (exact, else a combined one like "Hindi/Sanskrit"). A chapter already
// there (same name + book) keeps its done date and only gains the exam tags; nothing is removed. Runs once (salSeed flag).
const SCH_MAMTA_SYL_JSON = <<<'JSON'
{"10th|Hindi": [["क्षितिज", [["ut1", ["नेताजी का चश्मा (गद्य)", "सूरदास के पद (पद्य)"]], ["ut2", ["बालगोबिन भगत (गद्य)", "राम-लक्ष्मण-परशुराम संवाद (पद्य)"]], ["hy", ["लखनवी अंदाज़ (गद्य)", "आत्मकथ्य (पद्य)"]], ["ut3", ["उत्साह, अट नहीं रही है (पद्य)", "यह दंतुरित मुस्कान (पद्य)", "एक कहानी यह भी (गद्य)"]], ["ut4", ["नौबतखाने में इबादत (गद्य)", "संस्कृति (गद्य)", "संगतकार (पद्य)"]]]], ["कृतिका", [["hy", ["माता का अँचल"]], ["ann", ["साना-साना हाथ जोड़ि", "मैं क्यों लिखता हूँ?"]]]], ["व्याकरण", [["ut1", ["अलंकार", "रचना के आधार पर वाक्य भेद"]], ["ut2", ["वाच्य तथा वाच्य परिवर्तन", "पद-परिचय"]], ["hy", ["संवाद-लेखन", "ई-मेल लेखन", "स्ववृत्त-लेखन"]], ["ut3", ["विज्ञापन-लेखन", "संदेश लेखन"]], ["ut4", ["पत्र-लेखन", "निबंध: साइबर सुरक्षा: जागरूकता ही समाधान", "निबंध: स्वस्थ जीवन शैली", "निबंध: एक यादगार पारिवारिक अवकाश"]]]]], "9th|Hindi": [["हिंदी पाठ्यपुस्तक", [["ut1", ["दो बैलों की कथा (गद्य)", "रैदास के पद (पद्य)"]], ["ut2", ["क्या लिखूँ? (गद्य)", "राम-लक्ष्मण-परशुराम संवाद (पद्य)"]], ["hy", ["संवदीन / संवाधीन (गद्य)", "भारती, जय विजय करे! (पद्य)"]], ["ut3", ["ऐसी भी बातें होती हैं (गद्य)", "झाँसी की रानी (पद्य)"]], ["ut4", ["आखिरी चट्टान तक (गद्य)", "घर की याद (पद्य)"]], ["ann", ["रीढ़ की हड्डी (गद्य)", "मैं और मेरा देश (गद्य)"]]]], ["व्याकरण", [["ut1", ["उपसर्ग, प्रत्यय", "समास"]], ["ut2", ["अर्थ की दृष्टि से वाक्य भेद", "अलंकार"]], ["ann", ["अनुच्छेद-लेखन", "पत्र-लेखन", "लघु कथा", "सूचना-लेखन", "ई-मेल लेखन"]]]]], "8th|Hindi": [["हिंदी पाठ्यपुस्तक", [["ut1", ["किसको नमन करूँ मैं", "पूस की रात"]], ["ut2", ["बड़ा मीठा लगा", "मांडले जेल से"]], ["hy", ["रहीम के दोहे", "शास्त्री जी", "अकबरी लोटा"]], ["ut3", ["अँधेर नगरी चौपट राजा", "मुक्ति की आकांक्षा"]], ["ut4", ["किसान के बेटे", "दो कविताएँ"]], ["ann", ["हिमालय की बेटियाँ", "शहीदों की मजारों पर", "नेता जी सुभाषचंद्र बोस"]]]], ["व्याकरण", [["ut1", ["भाषा, बोली, लिपि, व्याकरण", "वर्ण-विचार", "शब्द-विचार"]], ["ut2", ["संधि", "समास", "उपसर्ग"]], ["hy", ["प्रत्यय", "शब्द-भंडार", "संज्ञा", "लिंग", "वचन", "निबंध: मेरे प्रिय नेता लालबहादुर शास्त्री", "निबंध: परिश्रम का महत्व", "निबंध: मेले का दृश्य"]], ["ut3", ["कारक", "सर्वनाम", "विशेषण", "क्रिया"]], ["ut4", ["काल", "वाच्य", "अव्यय"]], ["ann", ["विराम-चिह्न", "मुहावरे", "अलंकार", "पत्र-लेखन", "निबंध-लेखन"]]]]], "7th|Hindi": [["हिंदी पाठ्यपुस्तक", [["ut1", ["माँ! कह एक कहानी", "चतुर तोता"]], ["ut2", ["शिक्षाप्रद दोहे", "भाग्य विधाता कौन"]], ["hy", ["हार की खुशी", "सुदामा चरित"]], ["ut3", ["मनभावन सावन", "संगीत स्वामी हरिदास"]], ["ut4", ["ग्राम शोभा", "शिक्षा के लिए मेरा संघर्ष"]], ["ann", ["सूर्य की शक्ति और स्वास्थ्य", "प्रशंसा का चक्कर", "मीरा के पद"]]]], ["व्याकरण", [["ut1", ["भाषा, बोली, लिपि और व्याकरण", "वर्ण-विचार", "शब्द-विचार"]], ["ut2", ["संधि", "समास", "उपसर्ग-प्रत्यय"]], ["hy", ["शब्द-भंडार", "संज्ञा", "लिंग", "वचन", "निबंध: मेरी माता", "निबंध: सच्ची मित्रता", "निबंध: परिश्रम का महत्व"]], ["ut3", ["कारक", "सर्वनाम", "विशेषण"]], ["ut4", ["क्रिया", "काल", "अव्यय"]], ["ann", ["विराम-चिह्न", "मुहावरे", "पत्र-लेखन", "निबंध"]]]]], "6th|Hindi": [["हिंदी पाठ्यपुस्तक", [["ut1", ["मातृभूमि", "किक"]], ["ut2", ["बादल चले गए वे", "हार की जीत"]], ["hy", ["कबीरदास के दोहे", "पहली बूँद", "जलाओ दीये"]], ["ut3", ["कत्थक नृत्य", "मेरे तो गिरधर गोपाल"]], ["ut4", ["नमक का दरोगा", "हल्दी घाटी का युद्ध"]], ["ann", ["अविस्मरणीय यात्रा", "धरती से सितारों तक", "मैं और मेरा देश"]]]], ["व्याकरण", [["ut1", ["भाषा, बोली, लिपि और व्याकरण", "वर्ण-विचार", "शब्द-विचार"]], ["ut2", ["संधि", "समास", "उपसर्ग-प्रत्यय"]], ["hy", ["शब्द-भंडार", "संज्ञा", "लिंग", "वचन", "निबंध: मेरी मातृभूमि", "निबंध: वर्षा ऋतु", "निबंध: परोपकार"]], ["ut3", ["कारक", "सर्वनाम", "विशेषण"]], ["ut4", ["क्रिया", "काल", "अव्यय"]], ["ann", ["विराम-चिह्न", "मुहावरे एवं लोकोक्तियाँ", "अपठित गद्यांश", "अपठित पद्यांश", "पत्र-लेखन", "निबंध-लेखन"]]]]], "8th|Sanskrit": [["संस्कृत", [["ut1", ["वन्दना", "स्वर्ग यात्रा", "चतुरः वानरः"]], ["ut2", ["मतिः एव बलात् गरीयसी", "ईश्वरभक्तः अमात्यः"]], ["hy", ["शठे शाठ्यं समाचरेत्", "सप्त भगिन्यः", "रक्तदानम्"]], ["ut3", ["उपदेशो हि मूर्खाणाम्", "नीतिश्लोकाः"]], ["ut4", ["व्यायामः", "सत्यप्रिया धेनुः"]], ["ann", ["कल्पना चावला", "विद्यालयस्य क्रीडा-दिवसः", "सुभाषितानि", "परिशिष्टम्"]]]]], "7th|Sanskrit": [["संस्कृत", [["ut1", ["वन्दना", "अभ्यासस्य महत्त्वम्", "सूर्यः"]], ["ut2", ["आयुर्वेदः", "अस्माकं देशः"]], ["hy", ["बिलस्य वाणी न कदापि मे श्रुता", "दुर्जनः परिहर्तव्यः", "होलिकोत्सवः"]], ["ut3", ["साइना नेहवालः", "महाराणा प्रतापः"]], ["ut4", ["महापुरुषौ", "श्रीकृष्णः"]], ["ann", ["गीतामृतम्", "सर्वोत्तमाः वृक्षाः", "गौतम बुद्धः", "परिशिष्टम्"]]]]], "6th|Sanskrit": [["संस्कृत", [["ut1", ["वन्दना", "संस्कृत व्याकरणम्", "प्रभातवेला"]], ["ut2", ["भारतवर्षम्", "मम दिनचर्या"]], ["hy", ["एकलव्यस्य गुरुदक्षिणा", "मातुः आदेशः"]], ["ut3", ["हिमालयः", "वीर सुभाषः"]], ["ut4", ["सुभाषितानि", "प्रदूषणम्"]], ["ann", ["ऋतुराज-वसन्तः", "नीतिश्लोकाः", "नृपस्य गजः", "परिशिष्टम्"]]]]]}
JSON;
function sch_seed_mamta_syl(): void {
    $flag = 'mamta-syllabus-2026-27';
    if (sch_rec_get('salSeed', $flag)) return;
    $alloc = (array)sch_rec_get('alloc', 'main');
    if (!$alloc) return;
    $tid = null;
    foreach (sal_rows('teacher') as $k => $t) if (sal_name_same((string)($t['name'] ?? ''), 'Mamta')) { $tid = (string)$k; break; }
    $tid ??= 'MA';
    $map = ['ut1' => ['ut1', 'hy'], 'ut2' => ['ut2', 'hy'], 'hy' => ['hy'], 'ut3' => ['ut3', 'ann'], 'ut4' => ['ut4', 'ann'], 'ann' => ['ann']];
    $done = [];
    foreach (json_decode(SCH_MAMTA_SYL_JSON, true) as $key => $books) {
        [$cls, $sub] = explode('|', $key);
        $subs = []; $any = [];
        foreach ($alloc as $a) if (($a['c'] ?? '') === $cls) { $any[] = (string)($a['s'] ?? ''); if (($a['t'] ?? '') === $tid) $subs[] = (string)($a['s'] ?? ''); }
        $pick = null;
        foreach ([$subs, $any] as $list) {
            if ($pick === null) foreach ($list as $s) if (strcasecmp($s, $sub) === 0) { $pick = $s; break; }
            if ($pick === null) foreach ($list as $s) if (in_array(strtolower($sub), array_map('trim', explode('/', strtolower($s))), true)) { $pick = $s; break; }
        }
        $pick ??= $sub;
        $r = "$cls|$pick";
        $y = sch_rec_get('syl', $r); if (!is_array($y)) $y = ['chapters' => [], 'targetCount' => '', 'targetDate' => ''];
        $y['chapters'] = array_values((array)($y['chapters'] ?? []));
        $add = 0; $upd = 0;
        foreach ($books as [$book, $parts]) foreach ($parts as [$sec, $names]) foreach ($names as $n) {
            $ex = ($key === '6th|Hindi' && $book === 'व्याकरण' && $sec === 'ut3') ? ['ut3'] : $map[$sec];
            $hit = false;
            foreach ($y['chapters'] as &$ch) if (mb_strtolower(trim((string)($ch['n'] ?? ''))) === mb_strtolower($n) && (string)($ch['book'] ?? '') === $book) {
                $ch['ex'] = array_values(array_unique(array_merge((array)($ch['ex'] ?? []), $ex))); $hit = true; $upd++; break;
            }
            unset($ch);
            if (!$hit) { $y['chapters'][] = ['n' => $n, 'done' => null, 'topics' => [], 'book' => $book, 'ex' => $ex]; $add++; }
        }
        sch_rec_put('syl', $r, $y, 'system');
        $done[] = "$cls $pick: $add added" . ($upd ? ", $upd updated" : '');
    }
    sch_rec_put('salSeed', $flag, ['at' => sch_now_ms(), 'tid' => $tid, 'done' => $done], 'system');
    sch_log(['uid' => 'system', 'role' => 'system', 'label' => 'System'], 'Syllabus', "Mamta Ma'am's Hindi/Sanskrit syllabus from her register (owner 23 Nov 2026): " . implode('; ', $done));
}

// Middle-school textbook records for session 2026-27. Chapter headings are index data; topics are teaching summaries.
const SCH_MIDDLE_SYLLABUS_JSON = <<<'JSON'
{
  "6th|maths": [
    {"n":"Number System","book":"R.S. Aggarwal Mathematics","bookChapter":1,"topics":["Place value and numeration","Comparing and rounding numbers"],"sourceUrl":"https://www.stjohnsranchi.org/images/Syllabus/6/Syllabus%20Class%20VI%20%282025-26%29.pdf"},
    {"n":"Mathematical Patterns","book":"R.S. Aggarwal Mathematics","bookChapter":2,"topics":["Patterns in sequences","Figurate numbers"],"sourceUrl":"https://www.stjohnsranchi.org/images/Syllabus/6/Syllabus%20Class%20VI%20%282025-26%29.pdf"},
    {"n":"Whole Numbers","book":"R.S. Aggarwal Mathematics","bookChapter":3,"topics":["Number line","Properties of whole-number operations"],"sourceUrl":"https://www.stjohnsranchi.org/images/Syllabus/6/Syllabus%20Class%20VI%20%282025-26%29.pdf"},
    {"n":"Prime Time","book":"R.S. Aggarwal Mathematics","bookChapter":4,"topics":["Factors and multiples","Prime factorisation","HCF and LCM"],"sourceUrl":"https://www.stjohnsranchi.org/images/Syllabus/6/Syllabus%20Class%20VI%20%282025-26%29.pdf"},
    {"n":"Number Play","book":"R.S. Aggarwal Mathematics","bookChapter":5,"topics":["Digit patterns","Number puzzles"],"sourceUrl":"https://www.stjohnsranchi.org/images/Syllabus/6/Syllabus%20Class%20VI%20%282025-26%29.pdf"},
    {"n":"Integers","book":"R.S. Aggarwal Mathematics","bookChapter":6,"topics":["Positive and negative numbers","Addition and subtraction on a number line"],"sourceUrl":"https://www.stjohnsranchi.org/images/Syllabus/6/Syllabus%20Class%20VI%20%282025-26%29.pdf"},
    {"n":"Fractions","book":"R.S. Aggarwal Mathematics","bookChapter":7,"topics":["Equivalent fractions","Comparison and operations"],"sourceUrl":"https://www.stjohnsranchi.org/images/Syllabus/6/Syllabus%20Class%20VI%20%282025-26%29.pdf"},
    {"n":"Decimals","book":"R.S. Aggarwal Mathematics","bookChapter":8,"topics":["Decimal place value","Comparing and calculating with decimals"],"sourceUrl":"https://www.stjohnsranchi.org/images/Syllabus/6/Syllabus%20Class%20VI%20%282025-26%29.pdf"},
    {"n":"Lines and Angles","book":"R.S. Aggarwal Mathematics","bookChapter":9,"topics":["Lines, rays and segments","Classifying and measuring angles"],"sourceUrl":"https://www.stjohnsranchi.org/images/Syllabus/6/Syllabus%20Class%20VI%20%282025-26%29.pdf"},
    {"n":"Constructions","book":"R.S. Aggarwal Mathematics","bookChapter":10,"topics":["Compass and ruler constructions","Geometric figures"],"sourceUrl":"https://www.stjohnsranchi.org/images/Syllabus/6/Syllabus%20Class%20VI%20%282025-26%29.pdf"},
    {"n":"Symmetry","book":"R.S. Aggarwal Mathematics","bookChapter":11,"topics":["Lines of symmetry","Symmetric shapes"],"sourceUrl":"https://www.stjohnsranchi.org/images/Syllabus/6/Syllabus%20Class%20VI%20%282025-26%29.pdf"},
    {"n":"Perimeter and Area","book":"R.S. Aggarwal Mathematics","bookChapter":12,"topics":["Boundary length","Area of rectangles and compound figures"],"sourceUrl":"https://www.stjohnsranchi.org/images/Syllabus/6/Syllabus%20Class%20VI%20%282025-26%29.pdf"},
    {"n":"Data Handling","book":"R.S. Aggarwal Mathematics","bookChapter":13,"topics":["Organising data","Reading and drawing graphs"],"sourceUrl":"https://www.stjohnsranchi.org/images/Syllabus/6/Syllabus%20Class%20VI%20%282025-26%29.pdf"}
  ],
  "6th|science": [
    {"n":"The Wonderful World of Science","book":"NCERT Curiosity","bookChapter":1,"pf":"1","pt":"8","topics":["Asking testable questions","Observation and investigation"],"sourceUrl":"https://ncert.nic.in/textbook/pdf/fecu101.pdf"},
    {"n":"Diversity in the Living World","book":"NCERT Curiosity","bookChapter":2,"pf":"9","pt":"34","topics":["Grouping plants and animals","Habitats and adaptation"],"sourceUrl":"https://ncert.nic.in/textbook/pdf/fecu102.pdf"},
    {"n":"Mindful Eating: A Path to a Healthy Body","book":"NCERT Curiosity","bookChapter":3,"pf":"35","pt":"60","topics":["Food groups and nutrients","Balanced and varied diets"],"sourceUrl":"https://ncert.nic.in/textbook/pdf/fecu103.pdf"},
    {"n":"Exploring Magnets","book":"NCERT Curiosity","bookChapter":4,"pf":"61","pt":"78","topics":["Magnetic materials","Magnetic poles and direction"],"sourceUrl":"https://ncert.nic.in/textbook/pdf/fecu104.pdf"},
    {"n":"Measurement of Length and Motion","book":"NCERT Curiosity","bookChapter":5,"pf":"79","pt":"100","topics":["Standard units and measurement","Types of motion"],"sourceUrl":"https://ncert.nic.in/textbook/pdf/fecu105.pdf"},
    {"n":"Materials Around Us","book":"NCERT Curiosity","bookChapter":6,"pf":"101","pt":"122","topics":["Grouping by physical properties","Solubility and transparency"],"sourceUrl":"https://ncert.nic.in/textbook/pdf/fecu106.pdf","aliases":["Material Around Us"]},
    {"n":"Temperature and its Measurement","book":"NCERT Curiosity","bookChapter":7,"pf":"123","pt":"142","topics":["Temperature scales","Clinical and laboratory thermometers"],"sourceUrl":"https://ncert.nic.in/textbook/pdf/fecu107.pdf"},
    {"n":"A Journey through States of Water","book":"NCERT Curiosity","bookChapter":8,"pf":"143","pt":"162","topics":["Changes of state","Evaporation, condensation and the water cycle"],"sourceUrl":"https://ncert.nic.in/textbook/pdf/fecu108.pdf"},
    {"n":"Methods of Separation in Everyday Life","book":"NCERT Curiosity","bookChapter":9,"pf":"163","pt":"182","topics":["Sieving and filtration","Settling and decanting","Evaporation"],"sourceUrl":"https://ncert.nic.in/textbook/pdf/fecu109.pdf"},
    {"n":"Living Creatures: Exploring their Characteristics","book":"NCERT Curiosity","bookChapter":10,"pf":"183","pt":"206","topics":["Features of living things","Germination, growth and life cycles"],"sourceUrl":"https://ncert.nic.in/textbook/pdf/fecu110.pdf"},
    {"n":"Nature’s Treasures","book":"NCERT Curiosity","bookChapter":11,"pf":"207","pt":"230","topics":["Air, water and soil","Natural resources and conservation"],"sourceUrl":"https://ncert.nic.in/textbook/pdf/fecu111.pdf"},
    {"n":"Beyond Earth","book":"NCERT Curiosity","bookChapter":12,"pf":"231","topics":["Stars and constellations","The Solar System"],"sourceUrl":"https://ncert.nic.in/textbook/pdf/fecu112.pdf","aliases":["Beyond the Earth"]}
  ],
  "7th|maths": [
    {"n":"Large Numbers Around Us","book":"R.S. Aggarwal Mathematics — Book 1","bookChapter":1,"topics":["Place value","Comparing and estimating large numbers"],"sourceUrl":"https://www.apsvijaynagar.com/pdf/CLASS-VII-ANNUAL-SYLLABUS-2026-27.pdf","aliases":["Large Number Around Us"]},
    {"n":"Arithmetic Expressions","book":"R.S. Aggarwal Mathematics — Book 1","bookChapter":2,"topics":["Operations with brackets","Order of operations"],"sourceUrl":"https://www.apsvijaynagar.com/pdf/CLASS-VII-ANNUAL-SYLLABUS-2026-27.pdf"},
    {"n":"Decimal Numbers — A Peek Beyond the Point","book":"R.S. Aggarwal Mathematics — Book 1","bookChapter":3,"topics":["Decimal place value","Comparing and representing decimals"],"sourceUrl":"https://www.apsvijaynagar.com/pdf/CLASS-VII-ANNUAL-SYLLABUS-2026-27.pdf","aliases":["Decimals","A Peek Beyond the Point","Decimals Numbers — A Peek Beyond the Point"]},
    {"n":"Expressions Using Letter Numbers","book":"R.S. Aggarwal Mathematics — Book 1","bookChapter":4,"topics":["Variables and terms","Writing and simplifying expressions"],"sourceUrl":"https://www.apsvijaynagar.com/pdf/CLASS-VII-ANNUAL-SYLLABUS-2026-27.pdf"},
    {"n":"Parallel and Intersecting Lines","book":"R.S. Aggarwal Mathematics — Book 1","bookChapter":5,"topics":["Angle pairs","Parallel lines and transversals"],"sourceUrl":"https://www.apsvijaynagar.com/pdf/CLASS-VII-ANNUAL-SYLLABUS-2026-27.pdf"},
    {"n":"Number Play","book":"R.S. Aggarwal Mathematics — Book 1","bookChapter":6,"topics":["Number patterns","Digit puzzles"],"sourceUrl":"https://www.apsvijaynagar.com/pdf/CLASS-VII-ANNUAL-SYLLABUS-2026-27.pdf"},
    {"n":"Triangles and Their Constructions","book":"R.S. Aggarwal Mathematics — Book 1","bookChapter":7,"topics":["Triangle properties","Constructing triangles from given measurements"],"sourceUrl":"https://www.apsvijaynagar.com/pdf/CLASS-VII-ANNUAL-SYLLABUS-2026-27.pdf","aliases":["Triangles and Their Construction","A Tale of Three Intersecting Lines"]},
    {"n":"Working with Fractions","book":"R.S. Aggarwal Mathematics — Book 1","bookChapter":8,"topics":["Fraction operations","Fraction word problems"],"sourceUrl":"https://www.apsvijaynagar.com/pdf/CLASS-VII-ANNUAL-SYLLABUS-2026-27.pdf"},
    {"n":"Congruence (Geometric Twins)","book":"R.S. Aggarwal Mathematics — Book 2","bookChapter":1,"topics":["Matching sides and angles","Triangle congruence"],"sourceUrl":"https://www.apsvijaynagar.com/pdf/CLASS-VII-ANNUAL-SYLLABUS-2026-27.pdf","aliases":["Congruence","Geometric Twins"]},
    {"n":"Operations on Integers","book":"R.S. Aggarwal Mathematics — Book 2","bookChapter":2,"topics":["Adding, subtracting, multiplying and dividing integers","Sign rules"],"sourceUrl":"https://www.apsvijaynagar.com/pdf/CLASS-VII-ANNUAL-SYLLABUS-2026-27.pdf","aliases":["Operations on Integer","Operations with Integers"]},
    {"n":"HCF and LCM","book":"R.S. Aggarwal Mathematics — Book 2","bookChapter":3,"topics":["Factorisation","Common factors and common multiples"],"sourceUrl":"https://www.apsvijaynagar.com/pdf/CLASS-VII-ANNUAL-SYLLABUS-2026-27.pdf","aliases":["Finding Common Ground"]},
    {"n":"Multiplication and Division of Decimals","book":"R.S. Aggarwal Mathematics — Book 2","bookChapter":4,"topics":["Decimal products and quotients","Place-value reasoning"],"sourceUrl":"https://www.apsvijaynagar.com/pdf/CLASS-VII-ANNUAL-SYLLABUS-2026-27.pdf","aliases":["Another Peek Beyond the Point"]},
    {"n":"Connecting the Dots","book":"R.S. Aggarwal Mathematics — Book 2","bookChapter":5,"topics":["Locating points","Reading and making graphs"],"sourceUrl":"https://www.apsvijaynagar.com/pdf/CLASS-VII-ANNUAL-SYLLABUS-2026-27.pdf"},
    {"n":"Constructions and Tilings","book":"R.S. Aggarwal Mathematics — Book 2","bookChapter":6,"topics":["Geometric construction","Tessellations"],"sourceUrl":"https://www.apsvijaynagar.com/pdf/CLASS-VII-ANNUAL-SYLLABUS-2026-27.pdf"},
    {"n":"Finding the Unknown","book":"R.S. Aggarwal Mathematics — Book 2","bookChapter":7,"topics":["Identifying the unknown","Solving simple equations"],"sourceUrl":"https://www.apsvijaynagar.com/pdf/CLASS-VII-ANNUAL-SYLLABUS-2026-27.pdf"}
  ],
  "7th|science": [
    {"n":"The Ever-Evolving World of Science","book":"NCERT Curiosity","bookChapter":1,"pf":"1","pt":"6","topics":["Scientific evidence and inquiry","How explanations change"],"sourceUrl":"https://ncert.nic.in/textbook/pdf/gecu101.pdf"},
    {"n":"Exploring Substances: Acidic, Basic, and Neutral","book":"NCERT Curiosity","bookChapter":2,"pf":"7","pt":"22","topics":["Acids and bases","Indicators","Neutralisation"],"sourceUrl":"https://ncert.nic.in/textbook/pdf/gecu102.pdf","aliases":["Substances: Acidic, Basic and Neutral"]},
    {"n":"Electricity: Circuits and their Components","book":"NCERT Curiosity","bookChapter":3,"pf":"23","pt":"40","topics":["Cells and circuit symbols","Open and closed circuits","Conductors and insulators"],"sourceUrl":"https://ncert.nic.in/textbook/pdf/gecu103.pdf"},
    {"n":"The World of Metals and Non-metals","book":"NCERT Curiosity","bookChapter":4,"pf":"41","pt":"56","topics":["Physical properties of metals and non-metals","Reactions and uses"],"sourceUrl":"https://ncert.nic.in/textbook/pdf/gecu104.pdf"},
    {"n":"Changes Around Us: Physical and Chemical","book":"NCERT Curiosity","bookChapter":5,"pf":"57","pt":"72","topics":["Separating physical and chemical changes","Rusting and combustion"],"sourceUrl":"https://ncert.nic.in/textbook/pdf/gecu105.pdf"},
    {"n":"Adolescence: A Stage of Growth and Change","book":"NCERT Curiosity","bookChapter":6,"pf":"73","pt":"88","topics":["Changes during adolescence","Nutrition, hygiene and well-being"],"sourceUrl":"https://ncert.nic.in/textbook/pdf/gecu106.pdf"},
    {"n":"Heat Transfer in Nature","book":"NCERT Curiosity","bookChapter":7,"pf":"89","pt":"104","topics":["Conduction, convection and radiation","Heat transfer in daily life"],"sourceUrl":"https://ncert.nic.in/textbook/pdf/gecu107.pdf"},
    {"n":"Measurement of Time and Motion","book":"NCERT Curiosity","bookChapter":8,"pf":"105","pt":"120","topics":["Time measurement","Speed and distance–time graphs"],"sourceUrl":"https://ncert.nic.in/textbook/pdf/gecu108.pdf"},
    {"n":"Life Processes in Animals","book":"NCERT Curiosity","bookChapter":9,"pf":"121","pt":"136","topics":["Nutrition, digestion and respiration","Movement of substances in the body"],"sourceUrl":"https://ncert.nic.in/textbook/pdf/gecu109.pdf"},
    {"n":"Life Processes in Plants","book":"NCERT Curiosity","bookChapter":10,"pf":"137","pt":"152","topics":["Photosynthesis and respiration","Transport of water and nutrients"],"sourceUrl":"https://ncert.nic.in/textbook/pdf/gecu110.pdf"},
    {"n":"Light: Shadows and Reflections","book":"NCERT Curiosity","bookChapter":11,"pf":"153","pt":"168","topics":["Straight-line travel of light","Shadows and mirror reflections"],"sourceUrl":"https://ncert.nic.in/textbook/pdf/gecu111.pdf","aliases":["Light: Shadows and Reflection"]},
    {"n":"Earth, Moon, and the Sun","book":"NCERT Curiosity","bookChapter":12,"pf":"169","topics":["Earth’s rotation and revolution","Moon phases and eclipses"],"sourceUrl":"https://ncert.nic.in/textbook/pdf/gecu112.pdf","aliases":["The Earth, The Moon and the Sun","The Earth, Moon and the Sun"]}
  ],
  "8th|maths": [
    {"n":"Squares and Square Roots","book":"R.S. Aggarwal Mathematics — Book 1","bookChapter":1,"topics":["Square numbers","Methods for finding square roots"],"sourceUrl":"https://www.thestudypath.com/class-8/rs-aggarwal-solutions/"},
    {"n":"Cubes and Cube Roots","book":"R.S. Aggarwal Mathematics — Book 1","bookChapter":2,"topics":["Cube numbers","Prime factorisation for cube roots"],"sourceUrl":"https://www.thestudypath.com/class-8/rs-aggarwal-solutions/"},
    {"n":"Exponents","book":"R.S. Aggarwal Mathematics — Book 1","bookChapter":3,"topics":["Laws of powers","Standard form"],"sourceUrl":"https://www.thestudypath.com/class-8/rs-aggarwal-solutions/","aliases":["Power Play"]},
    {"n":"The Story of Numbers","book":"R.S. Aggarwal Mathematics — Book 1","bookChapter":4,"topics":["Numerals and place value","Comparing numeral systems"],"sourceUrl":"https://www.thestudypath.com/class-8/rs-aggarwal-solutions/","aliases":["A Story of Numbers"]},
    {"n":"Quadrilaterals","book":"R.S. Aggarwal Mathematics — Book 1","bookChapter":5,"topics":["Angle sums","Properties of common quadrilaterals"],"sourceUrl":"https://www.thestudypath.com/class-8/rs-aggarwal-solutions/"},
    {"n":"Number Play","book":"R.S. Aggarwal Mathematics — Book 1","bookChapter":6,"topics":["Divisibility tests","Number patterns"],"sourceUrl":"https://www.thestudypath.com/class-8/rs-aggarwal-solutions/"},
    {"n":"Operations on Algebraic Expressions","book":"R.S. Aggarwal Mathematics — Book 1","bookChapter":7,"topics":["Combining terms","Multiplying expressions","Algebraic identities"],"sourceUrl":"https://www.thestudypath.com/class-8/rs-aggarwal-solutions/","aliases":["Operations on Algebraic Expression","We Distribute, Yet Things Multiply"]},
    {"n":"Proportional Reasoning (Ratio and Proportion)","book":"R.S. Aggarwal Mathematics — Book 1","bookChapter":8,"topics":["Equivalent ratios","Solving proportions"],"sourceUrl":"https://www.thestudypath.com/class-8/rs-aggarwal-solutions/","aliases":["Proportional Reasoning 1"]},
    {"n":"Fractions in Disguise","book":"R.S. Aggarwal Mathematics — Book 2","bookChapter":1,"topics":["Fractions, decimals and percentages","Comparing representations"],"sourceUrl":"https://www.thestudypath.com/class-8/rs-aggarwal-solutions/"},
    {"n":"The Baudhayana–Pythagoras Theorem","book":"R.S. Aggarwal Mathematics — Book 2","bookChapter":2,"topics":["Right triangles","Finding a missing side"],"sourceUrl":"https://www.thestudypath.com/class-8/rs-aggarwal-solutions/"},
    {"n":"Proportional Reasoning — 2","book":"R.S. Aggarwal Mathematics — Book 2","bookChapter":3,"topics":["Direct and inverse variation","Applying proportions"],"sourceUrl":"https://www.thestudypath.com/class-8/rs-aggarwal-solutions/"},
    {"n":"Exploring Some Geometric Themes","book":"R.S. Aggarwal Mathematics — Book 2","bookChapter":4,"topics":["Angle and line relationships","Geometric reasoning"],"sourceUrl":"https://www.thestudypath.com/class-8/rs-aggarwal-solutions/"},
    {"n":"Tales by Dots and Lines","book":"R.S. Aggarwal Mathematics — Book 2","bookChapter":5,"topics":["Recognising dot and line patterns","Explaining geometric relationships"],"sourceUrl":"https://www.thestudypath.com/class-8/rs-aggarwal-solutions/"},
    {"n":"Topics in Algebra","book":"R.S. Aggarwal Mathematics — Book 2","bookChapter":6,"topics":["Representing patterns with algebra","Working with expressions"],"sourceUrl":"https://www.thestudypath.com/class-8/rs-aggarwal-solutions/","aliases":["Algebra Play"]},
    {"n":"Area","book":"R.S. Aggarwal Mathematics — Book 2","bookChapter":7,"topics":["Area of polygons","Breaking compound shapes into simpler figures"],"sourceUrl":"https://www.thestudypath.com/class-8/rs-aggarwal-solutions/"}
  ],
  "8th|science": [
    {"n":"Exploring the Investigative World of Science","book":"NCERT Curiosity","bookChapter":1,"pf":"1","pt":"7","topics":["Planning investigations","Comparing observations and explanations"],"sourceUrl":"https://ncert.nic.in/textbook/pdf/hecu101.pdf"},
    {"n":"The Invisible Living World: Beyond Our Naked Eye","book":"NCERT Curiosity","bookChapter":2,"pf":"8","pt":"27","topics":["Microscopes and cells","Microorganisms in food, health and the environment"],"sourceUrl":"https://ncert.nic.in/textbook/pdf/hecu102.pdf"},
    {"n":"Health: The Ultimate Treasure","book":"NCERT Curiosity","bookChapter":3,"pf":"28","pt":"45","topics":["Factors influencing health","Patterns of disease and prevention"],"sourceUrl":"https://ncert.nic.in/textbook/pdf/hecu103.pdf"},
    {"n":"Electricity: Magnetic and Heating Effects","book":"NCERT Curiosity","bookChapter":4,"pf":"46","pt":"61","topics":["Magnetic effect of current","Electromagnets","Heating effect"],"sourceUrl":"https://ncert.nic.in/textbook/pdf/hecu104.pdf"},
    {"n":"Exploring Forces","book":"NCERT Curiosity","bookChapter":5,"pf":"62","pt":"79","topics":["Contact and non-contact forces","Effects of force on motion and shape"],"sourceUrl":"https://ncert.nic.in/textbook/pdf/hecu105.pdf"},
    {"n":"Pressure, Winds, Storms, and Cyclones","book":"NCERT Curiosity","bookChapter":6,"pf":"80","pt":"97","topics":["Pressure in fluids","Wind and storm formation","Cyclone safety"],"sourceUrl":"https://ncert.nic.in/textbook/pdf/hecu106.pdf"},
    {"n":"Particulate Nature of Matter","book":"NCERT Curiosity","bookChapter":7,"pf":"98","pt":"115","topics":["Particle model","Spacing and movement in solids, liquids and gases"],"sourceUrl":"https://ncert.nic.in/textbook/pdf/hecu107.pdf"},
    {"n":"Nature of Matter: Elements, Compounds, and Mixtures","book":"NCERT Curiosity","bookChapter":8,"pf":"116","pt":"133","topics":["Classification of materials","Distinguishing mixtures and compounds"],"sourceUrl":"https://ncert.nic.in/textbook/pdf/hecu108.pdf","aliases":["Elements, Compounds and Mixtures"]},
    {"n":"The Amazing World of Solutes, Solvents, and Solutions","book":"NCERT Curiosity","bookChapter":9,"pf":"134","pt":"151","topics":["Dissolving and solubility","Saturated solutions","Factors affecting solubility"],"sourceUrl":"https://ncert.nic.in/textbook/pdf/hecu109.pdf"},
    {"n":"Light: Mirrors and Lenses","book":"NCERT Curiosity","bookChapter":10,"pf":"152","pt":"169","topics":["Images in curved mirrors","Converging and diverging lenses"],"sourceUrl":"https://ncert.nic.in/textbook/pdf/hecu110.pdf"},
    {"n":"Keeping Time with the Skies","book":"NCERT Curiosity","bookChapter":11,"pf":"170","pt":"189","topics":["Cycles in the sky","Timekeeping using astronomical observations"],"sourceUrl":"https://ncert.nic.in/textbook/pdf/hecu111.pdf"},
    {"n":"How Nature Works in Harmony","book":"NCERT Curiosity","bookChapter":12,"pf":"190","pt":"209","topics":["Interactions in ecosystems","Interdependence and ecological balance"],"sourceUrl":"https://ncert.nic.in/textbook/pdf/hecu112.pdf"},
    {"n":"Our Home: Earth, a Unique Life Sustaining Planet","book":"NCERT Curiosity","bookChapter":13,"pf":"210","topics":["Conditions supporting life","Earth’s resources and threats to habitability"],"sourceUrl":"https://ncert.nic.in/textbook/pdf/hecu113.pdf"}
  ]
}
JSON;

function sch_middle_syl_name(string $value): string {
    $value = preg_replace('/^\s*(?:ch(?:apter)?\.?\s*\d+\s*[:.\-–]?\s*|\d+\s*[.\-–:)]+\s*)/iu', '', $value);
    return preg_replace('/[^a-z0-9]+/', '', strtolower($value));
}

function sch_middle_syl_book_matches(string $book, string $subject): bool {
    $book = sch_middle_syl_name($book);
    return $subject === 'science'
        ? str_contains($book, 'curiosity')
        : (str_contains($book, 'aggarwal') || str_contains($book, 'agarwal') || str_contains($book, 'agrawal'));
}

// Ignore books or editions that conflict with the current records. Match by heading without renaming
// existing rows because chapter text and older teaching records can reference a chapter by that heading.
function sch_middle_syl_merge(array $current, array $reference, string $subject): ?array {
    $chapters = array_values((array)($current['chapters'] ?? []));
    $matches = [];
    $refByName = [];
    foreach ($reference as $i => $row) {
        foreach (array_merge([$row['n']], $row['aliases'] ?? []) as $name) {
            $refByName[sch_middle_syl_name($name)] = $i;
        }
    }

    foreach ($chapters as $i => $row) {
        $name = sch_middle_syl_name((string)($row['n'] ?? ''));
        $book = trim((string)($row['book'] ?? ''));
        if ($book !== '' && !sch_middle_syl_book_matches($book, $subject)
            && !in_array(sch_middle_syl_name($book), ['book1', 'book2', 'part1', 'part2', 'mainbook'], true)) return null;
        if (!isset($refByName[$name])) return null;
        $ref = $refByName[$name];
        if (isset($matches[$ref])) return null;
        if (preg_match('/(?:book|part)\s*[-:]?\s*([12])\b/i', $book, $part)
            && !str_ends_with($reference[$ref]['book'], 'Book ' . $part[1])) return null;
        $matches[$ref] = $i;
    }

    // Bookless Science rows may be from a different publisher. Require corroborating headings
    // before attaching NCERT pages to an existing syllabus without an identifiable book.
    if ($subject === 'science' && count($chapters) === 1
        && !sch_middle_syl_book_matches((string)($chapters[0]['book'] ?? ''), $subject)) return null;

    $updated = [];
    foreach ($reference as $i => $row) {
        unset($row['aliases']);
        $existing = isset($matches[$i]) ? $chapters[$matches[$i]] : ['done' => null];
        foreach ($row as $field => $value) {
            if (empty($existing[$field])) $existing[$field] = $value;
        }
        $updated[] = $existing;
    }
    $current['chapters'] = $updated;
    if (empty($current['targetCount'])) $current['targetCount'] = (string)count($reference);
    $current['targetDate'] ??= '';
    return $current;
}

function sch_seed_middle_syllabus(): void {
    if (date('Y-m-d') < '2026-04-01' || date('Y-m-d') >= '2027-04-01') return;
    $flag = 'middle-maths-science-2026-27';
    $previous = sch_rec_get('salSeed', $flag);
    $reference = json_decode(SCH_MIDDLE_SYLLABUS_JSON, true, 512, JSON_THROW_ON_ERROR);
    if (count($previous['subjects'] ?? []) === count($reference)) return;

    $aliases = ['mathematics' => 'maths', 'maths' => 'maths', 'math' => 'maths', 'science' => 'science', 'generalscience' => 'science'];
    $subjects = [];
    foreach ((array)sch_rec_get('alloc', 'main') as $period) {
        $class = (string)($period['c'] ?? '');
        $name = trim((string)($period['s'] ?? ''));
        $label = sch_middle_syl_name($name);
        $kind = $aliases[$label] ?? '';
        $key = "$class|$kind";
        if (!isset($reference[$key]) || isset($previous['subjects'][$key])) continue;
        $subjects[$key][$name] = true;
    }
    if (!$subjects) return;

    $db = sch_db();
    $db->beginTransaction();
    try {
        // Lock the import marker first, including the first request, so two devices cannot both seed.
        $marker = $db->prepare("INSERT INTO sch_records (k,r,d,deleted,ver,by_uid,updated_at)
            VALUES ('salSeed', ?, '{}', 0, 0, 'system', NOW()) ON DUPLICATE KEY UPDATE r=VALUES(r)");
        $marker->execute([$flag]);
        $lock = $db->prepare('SELECT d FROM sch_records WHERE k=? AND r=? AND deleted=0 FOR UPDATE');
        $lock->execute(['salSeed', $flag]);
        $state = json_decode((string)$lock->fetchColumn(), true, 512, JSON_THROW_ON_ERROR)['subjects'] ?? [];
        $log = [];
        foreach ($subjects as $key => $names) {
            if (isset($state[$key])) continue;
            [$class, $kind] = explode('|', $key, 2);
            if (count($names) !== 1) {
                $log[] = "$class $kind: skipped, multiple timetable labels";
                $state[$key] = 'multiple timetable labels';
                continue;
            }
            $name = array_key_first($names);
            $record = "$class|$name";
            $lock->execute(['syl', $record]);
            $stored = $lock->fetchColumn();
            $current = $stored === false ? [] : json_decode((string)$stored, true, 512, JSON_THROW_ON_ERROR);
            $merged = sch_middle_syl_merge($current, $reference[$key], $kind);
            if ($merged === null) {
                $log[] = "$class $name: skipped, saved chapters or edition do not match";
                $state[$key] = 'saved chapters or edition do not match';
                continue;
            }
            if ($merged !== $current) sch_rec_put('syl', $record, $merged, 'system');
            $n = count($merged['chapters']);
            $state[$key] = $n;
            $log[] = "$class $name: $n chapter records";
        }
        if ($log) {
            sch_rec_put('salSeed', $flag, ['at' => sch_now_ms(), 'subjects' => $state], 'system');
            sch_log(['uid' => 'system', 'role' => 'system', 'label' => 'System'], 'Syllabus', '2026-27 textbook import: ' . implode('; ', $log));
        }
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
}

// Every one-time change, in order (called from api.php load / since and salary_api.php sheet).
function sch_seeds(): void {
    foreach (['sch_seed_ritu_ukg', 'sch_seed_ankit_8th', 'sch_seed_lkg_hy', 'sch_seed_mamta_syl', 'sch_seed_middle_syllabus'] as $f) { try { $f(); } catch (Throwable $e) { error_log("$f: " . $e->getMessage()); } }
}
