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

// Every one-time change, in order (called from api.php load / since and salary_api.php sheet).
function sch_seeds(): void {
    foreach (['sch_seed_ritu_ukg', 'sch_seed_ankit_8th', 'sch_seed_lkg_hy', 'sch_seed_mamta_syl'] as $f) { try { $f(); } catch (Throwable $e) { error_log("$f: " . $e->getMessage()); } }
}
