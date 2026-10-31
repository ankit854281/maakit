<?php
// ============================================================
// Coaching Saathi V2–V5 ke saajhe helper (inc.php ke aakhir me judta hai)
// Wahi niyam: coaching_id hamesha session se, har query me coaching_id=?
// ============================================================

const CS_DAYS = [1 => 'Somvar', 2 => 'Mangal', 3 => 'Budh', 4 => 'Guru', 5 => 'Shukra', 6 => 'Shani', 7 => 'Ravi'];
const CS_NOTE_MAX = 5 * 1024 * 1024;   // 5 MB

// ---------------- coaching ka extra (logo, band) ----------------
function cs_meta(int $cid): array {
    static $m = [];
    if (!isset($m[$cid])) {
        try { $r = cs_row('SELECT * FROM cs_coaching_meta WHERE coaching_id=?', [$cid]); }
        catch (PDOException $e) { $r = null; }   // sql/048 abhi nahi chali: coaching chalti rahe
        $m[$cid] = $r ?: ['coaching_id' => $cid, 'logo' => null, 'blocked' => 0, 'admin_note' => ''];
    }
    return $m[$cid];
}
function cs_blocked(int $cid): bool { return (int)cs_meta($cid)['blocked'] === 1; }
/** Header ka logo: photo ho to photo, warna naam ka pehla akshar */
function cs_logo_html(array $c): string {
    $m = cs_meta((int)$c['id']);
    if ($m['logo']) return '<img class="logo" src="' . h(cs_path_url('files.php?logo=' . (int)$c['id'] . '&v=' . substr((string)$m['logo'], 0, 8))) . '" alt="">';
    return '<span class="logo">' . h(mb_strtoupper(mb_substr((string)$c['naam'], 0, 1))) . '</span>';
}
function cs_path_url(string $rel): string { return '/coaching/' . ltrim($rel, '/'); }

// ---------------- file rakhna (public_html ke bahar) ----------------
function cs_files_dir(): string {
    static $d = null;
    if ($d !== null) return $d;
    $try = [];
    if (cs_cfg('CS_FILES_DIR') !== '') $try[] = rtrim((string)cs_cfg('CS_FILES_DIR'), '/');
    $try[] = dirname(__DIR__, 2) . '/cs-files';   // /home/<user>/cs-files — website se bahar
    $try[] = __DIR__ . '/files';                   // aakhri raasta: .htaccess se band
    foreach ($try as $t) {
        if (!is_dir($t)) @mkdir($t, 0750, true);
        if (is_dir($t) && is_writable($t)) {
            if (str_starts_with($t, __DIR__) && !is_file($t . '/.htaccess')) @file_put_contents($t . '/.htaccess', "Require all denied\nDeny from all\n");
            return $d = $t;
        }
    }
    throw new RuntimeException('File rakhne ki jagah nahi mili (CS_FILES_DIR)');
}
/** Upload jaanch kar rakho. kinds: 'image' | 'note' (pdf ya photo). ['name','mime','orig'] ya ['err'=>...] */
function cs_save_upload(string $field, string $kinds): array {
    $f = $_FILES[$field] ?? null;
    if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return ['err' => 'none'];
    if ($f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) return ['err' => 'File nahi aayi — dobara koshish kariye.'];
    if ($f['size'] > CS_NOTE_MAX) return ['err' => 'File 5 MB se badi hai.'];
    $head = (string)file_get_contents($f['tmp_name'], false, null, 0, 8);
    $img = @getimagesize($f['tmp_name']);
    $orig = mb_substr(preg_replace('/[^\w.\- ()\x{0900}-\x{097F}]/u', '', basename((string)$f['name'])), 0, 120) ?: 'file';
    $name = bin2hex(random_bytes(16));
    if ($img && in_array($img[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true) && function_exists('imagecreatefromstring')) {
        // Photo dobara bana kar rakho (andar chhupa kuch bhi saath nahi aata)
        $src = @imagecreatefromstring((string)file_get_contents($f['tmp_name']));
        if (!$src) return ['err' => 'Photo khul nahi rahi.'];
        $max = $kinds === 'image' ? 256 : 1600;
        $w = imagesx($src); $hh = imagesy($src); $r = min(1, $max / max($w, $hh));
        $dst = imagecreatetruecolor(max(1, (int)($w * $r)), max(1, (int)($hh * $r)));
        imagealphablending($dst, false); imagesavealpha($dst, true);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, imagesx($dst), imagesy($dst), $w, $hh);
        $ok = $kinds === 'image' ? imagepng($dst, cs_files_dir() . '/' . $name . '.bin') : imagejpeg($dst, cs_files_dir() . '/' . $name . '.bin', 82);
        if (!$ok) return ['err' => 'Photo save nahi hui.'];
        return ['name' => $name, 'mime' => $kinds === 'image' ? 'image/png' : 'image/jpeg', 'orig' => $orig];
    }
    if ($kinds === 'note' && str_starts_with($head, '%PDF-')) {
        if (!move_uploaded_file($f['tmp_name'], cs_files_dir() . '/' . $name . '.bin')) return ['err' => 'File save nahi hui.'];
        return ['name' => $name, 'mime' => 'application/pdf', 'orig' => $orig];
    }
    return ['err' => $kinds === 'image' ? 'Sirf JPG / PNG photo.' : 'Sirf PDF ya photo (JPG / PNG).'];
}
function cs_send_file(string $name, string $mime, string $dl = ''): void {
    $p = cs_files_dir() . '/' . preg_replace('/[^a-f0-9]/', '', $name) . '.bin';
    if (!is_file($p)) { http_response_code(404); exit('Nahi mila'); }
    header('Content-Type: ' . $mime);
    header('X-Content-Type-Options: nosniff');
    header("Content-Security-Policy: default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox");
    header('Content-Length: ' . filesize($p));
    header('Content-Disposition: ' . ($dl !== '' ? 'inline; filename="' . str_replace('"', '', $dl) . '"' : 'inline'));
    readfile($p);
    exit;
}

// ---------------- staff ke batch (teacher: sirf apne) ----------------
function my_batches(): array {
    $me = cs_me();
    $sql = "SELECT b.*, c.naam course, c.fee_type, c.amount, u.naam teacher,
              (SELECT COUNT(*) FROM cs_enroll e JOIN cs_student s ON s.id=e.student_id AND s.coaching_id=e.coaching_id
                 WHERE e.batch_id=b.id AND e.coaching_id=b.coaching_id AND e.status='active' AND s.status='active') n
            FROM cs_batch b JOIN cs_course c ON c.id=b.course_id AND c.coaching_id=b.coaching_id
            LEFT JOIN cs_user u ON u.id=b.teacher_id AND u.coaching_id=b.coaching_id
            WHERE b.coaching_id=? AND b.active=1";
    $p = [$me['cid']];
    if ($me['role'] !== 'owner') { $sql .= ' AND b.teacher_id=?'; $p[] = $me['uid']; }
    return cs_all($sql . ' ORDER BY b.naam', $p);
}
function batch_ok(int $bid): ?array { foreach (my_batches() as $b) if ((int)$b['id'] === $bid) return $b; return null; }
/** Student jo ye login dekh sakta hai (teacher: sirf apne batch ka) */
function student_ok(int $sid): ?array {
    $me = cs_me();
    $s = cs_row('SELECT * FROM cs_student WHERE id=? AND coaching_id=?', [$sid, $me['cid']]);
    if (!$s || $me['role'] === 'owner') return $s;
    $ok = cs_val("SELECT 1 FROM cs_enroll e JOIN cs_batch b ON b.id=e.batch_id AND b.coaching_id=e.coaching_id
                  WHERE e.student_id=? AND e.coaching_id=? AND e.status='active' AND b.teacher_id=?", [$sid, $me['cid'], $me['uid']]);
    return $ok ? $s : null;
}

// ---------------- bachche ke batch ----------------
function cs_student_batches(int $cid, int $sid): array {
    return array_map('intval', array_column(cs_all("SELECT e.batch_id FROM cs_enroll e JOIN cs_batch b ON b.id=e.batch_id AND b.coaching_id=e.coaching_id
        WHERE e.coaching_id=? AND e.student_id=? AND e.status='active' AND b.active=1", [$cid, $sid]), 'batch_id'));
}
function cs_in(array $ids): string { return $ids ? implode(',', array_fill(0, count($ids), '?')) : 'NULL'; }

// ---------------- quiz / mock ----------------
/** Quiz abhi khula hai? */
function cs_quiz_open(array $q): bool {
    $now = date('Y-m-d H:i:s');
    return (int)$q['published'] === 1 && (!$q['opens_at'] || $q['opens_at'] <= $now) && (!$q['closes_at'] || $q['closes_at'] >= $now);
}
/** Mock: kitne second baaki (start se) */
function cs_mock_left(array $q, array $att): int {
    return (int)$q['total_min'] * 60 + 15 - (time() - strtotime($att['started_at']));
}
/** Koshish ka hisaab dobara gino (server ke jawab se, kabhi browser se nahi) */
function cs_quiz_score(array $q, array $qs, array $answers): array {
    $r = 0; $w = 0;
    foreach ($qs as $x) {
        $c = $answers[(string)$x['id']]['c'] ?? '';
        if ($c === '') continue;
        if ($c === $x['ans']) $r++; else $w++;
    }
    $neg = $q['kind'] === 'mock' ? (float)$q['neg'] : 0.0;
    return ['right' => $r, 'wrong' => $w, 'score' => round($r - $w * $neg, 2)];
}
/** Rank list: [student_id => ['rank','score','naam']] — sirf poori ki hui koshish */
function cs_quiz_ranks(int $cid, int $quizId): array {
    $rows = cs_all("SELECT a.student_id, a.score, a.right_n, a.wrong_n, s.naam FROM cs_quiz_att a JOIN cs_student s ON s.id=a.student_id AND s.coaching_id=a.coaching_id
                    WHERE a.coaching_id=? AND a.quiz_id=? AND a.finished_at IS NOT NULL ORDER BY a.score DESC, a.wrong_n, a.finished_at", [$cid, $quizId]);
    $out = []; $rank = 0; $prev = null;
    foreach ($rows as $i => $r) {
        if ($prev === null || (float)$r['score'] !== $prev) $rank = $i + 1;
        $prev = (float)$r['score'];
        $out[(int)$r['student_id']] = ['rank' => $rank, 'score' => (float)$r['score'], 'naam' => $r['naam'], 'right' => (int)$r['right_n'], 'wrong' => (int)$r['wrong_n']];
    }
    return $out;
}

// ---------------- risk score (V5) ----------------
/** Har active bachche ka 0–100 khatra + kaaran. Sirf owner ko fees wala hissa. */
function cs_risk_scores(int $cid, bool $withFees): array {
    $out = [];
    $add = function (int $sid, int $pts, string $why) use (&$out) {
        if (!isset($out[$sid])) $out[$sid] = ['score' => 0, 'why' => []];
        $out[$sid]['score'] = min(100, $out[$sid]['score'] + $pts); $out[$sid]['why'][] = $why;
    };
    foreach (cs_absent3($cid) as $sid) $add((int)$sid, 40, '3 din se nahi aaya');
    foreach (cs_all("SELECT a.student_id, SUM(a.status='P') p, COUNT(*) n FROM cs_attendance a JOIN cs_student s ON s.id=a.student_id AND s.coaching_id=a.coaching_id
                     WHERE a.coaching_id=? AND s.status='active' AND a.d>=CURDATE()-INTERVAL 30 DAY GROUP BY a.student_id HAVING n>=5", [$cid]) as $r) {
        $pc = (int)round($r['p'] * 100 / $r['n']);
        if ($pc < 75) $add((int)$r['student_id'], $pc < 50 ? 25 : 15, 'Haaziri ' . $pc . '%');
    }
    if ($withFees) foreach (cs_fee_late($cid) as $sid => $amt) $add((int)$sid, 30, 'Fees ' . rs($amt) . ' 15 din+');
    // Marks gir rahe: aakhri test % pichhle tests ke ausat se 15+ kam
    $rows = cs_all("SELECT m.student_id, m.marks, t.max_marks FROM cs_mark m JOIN cs_test t ON t.id=m.test_id AND t.coaching_id=m.coaching_id
                    JOIN cs_student s ON s.id=m.student_id AND s.coaching_id=m.coaching_id
                    WHERE m.coaching_id=? AND s.status='active' AND m.absent=0 AND m.marks IS NOT NULL AND t.max_marks>0 ORDER BY m.student_id, t.d DESC, t.id DESC", [$cid]);
    $by = [];
    foreach ($rows as $r) $by[(int)$r['student_id']][] = $r['marks'] * 100 / $r['max_marks'];
    foreach ($by as $sid => $p) {
        if (count($p) < 2) continue;
        $last = $p[0]; $avg = array_sum(array_slice($p, 1, 4)) / count(array_slice($p, 1, 4));
        if ($avg - $last >= 15) $add($sid, 20, 'Marks gire (' . round($last) . '% vs ' . round($avg) . '%)');
    }
    uasort($out, fn($a, $b) => $b['score'] <=> $a['score']);
    return $out;
}

// ---------------- salary (V5) ----------------
/** Mahine ka hisaab: kaam ke din = din − Ravivar; absent = A + H/2; katauti = salary/kaam ke din × (absent − paid leave) */
function cs_salary_calc(int $cid, int $uid, string $month): array {
    $rate = cs_row('SELECT * FROM cs_salary_rate WHERE coaching_id=? AND user_id=?', [$cid, $uid]) ?: ['monthly' => 0, 'paid_leave' => 1];
    $days = (int)date('t', strtotime($month . '-01')); $work = 0;
    for ($i = 1; $i <= $days; $i++) if (date('N', strtotime($month . '-' . sprintf('%02d', $i))) !== '7') $work++;
    $abs = (float)cs_val("SELECT COALESCE(SUM(CASE status WHEN 'A' THEN 1 WHEN 'H' THEN 0.5 ELSE 0 END),0) FROM cs_staff_att
                          WHERE coaching_id=? AND user_id=? AND d BETWEEN ? AND LAST_DAY(?)", [$cid, $uid, $month . '-01', $month . '-01']);
    $cutDays = max(0, $abs - (float)$rate['paid_leave']);
    $cut = $work ? (int)round((int)$rate['monthly'] / $work * $cutDays) : 0;
    return ['monthly' => (int)$rate['monthly'], 'paid_leave' => (float)$rate['paid_leave'], 'work' => $work, 'absent' => $abs, 'cut' => $cut, 'net' => max(0, (int)$rate['monthly'] - $cut)];
}

// ---------------- Maakit admin ----------------
function cs_slug(string $naam, string $city): string {
    $b = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $naam . ' ' . $city), '-'));
    $b = rtrim(substr($b !== '' ? $b : 'coaching', 0, 30), '-') ?: 'coaching';
    $slug = $b;
    for ($i = 2; cs_val('SELECT 1 FROM cs_coaching WHERE slug=?', [$slug]); $i++) $slug = $b . '-' . $i;
    return $slug;
}

function cs_admin_key(): string { return (string)(cs_cfg('CS_ADMIN_KEY') ?: cs_cfg('UPDATE_KEY')); }
function cs_is_admin(): bool { $s = cs_session(); return $s && $s['kind'] === 'admin'; }

// ---------------- sawaal ek saath chipkana ----------------
/** Teacher ka likha text -> sawaal. Har sawaal ke beech khaali line:
 *    Section: Maths            (chahein to)
 *    2 + 2 kitna hota hai?
 *    A) 3
 *    B) 4
 *    C) 5
 *    D) 6
 *    Ans: B
 *  Galat block chhod diye jaate hain; ['qs'=>[...], 'bad'=>ginti] */
function cs_parse_questions(string $txt): array {
    $txt = str_replace(["\r\n", "\r"], "\n", $txt);
    $blocks = preg_split('/\n\s*\n/', trim($txt));
    $out = []; $bad = 0; $section = '';
    foreach ($blocks as $bl) {
        $q = ''; $opt = []; $ans = '';
        foreach (explode("\n", trim($bl)) as $ln) {
            $ln = trim($ln);
            if ($ln === '') continue;
            if (preg_match('/^(section|bhag|vishay)\s*[:\-]\s*(.+)$/iu', $ln, $m)) { $section = mb_substr(trim($m[2]), 0, 40); continue; }
            if (preg_match('/^\(?([a-dA-D])[\).:\-]\s*(.+)$/u', $ln, $m) && $q !== '') { $opt[strtolower($m[1])] = mb_substr(trim($m[2]), 0, 300); continue; }
            if (preg_match('/^(ans|answer|uttar|jawab|sahi)\s*[:\-]?\s*\(?([a-dA-D])\)?\s*$/iu', $ln, $m)) { $ans = strtolower($m[2]); continue; }
            $ln = preg_replace('/^(q|prashn|sawaal)?\s*\d*\s*[\).:\-]\s*/iu', '', $ln);
            $q .= ($q === '' ? '' : "\n") . $ln;
        }
        if ($q === '' && !$opt) continue;
        if ($q === '' || !isset($opt['a'], $opt['b']) || $ans === '' || !isset($opt[$ans])) { $bad++; continue; }
        $out[] = ['section' => $section, 'q' => mb_substr($q, 0, 2000), 'a' => $opt['a'], 'b' => $opt['b'], 'c' => $opt['c'] ?? '', 'd' => $opt['d'] ?? '', 'ans' => $ans];
    }
    return ['qs' => $out, 'bad' => $bad];
}
