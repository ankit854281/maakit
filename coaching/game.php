<?php
// Coaching Saathi — Game Zone (student app ka paid hissa).
// Engine wahi hai jo school ke student app me hai: school/game_lib.php
// (maths class ke hisaab se, English ke 366 shabd, XP / level / streak / chest).
// Teacher ko kuch likhna nahi padta. Sahi jawab phone par pehle kabhi nahi jaata,
// samay server ginta hai (20 sec + 5 sec chhoot).
require __DIR__ . '/inc.php';
require_once __DIR__ . '/../school/game_lib.php';

const CS_GAME_ROUNDS = 4;      // din me itne round

$s = cs_session();
if (!$s || $s['kind'] !== 'family') cs_go('index.php');
$sid = (int)($_GET['s'] ?? $_POST['s'] ?? 0);
$K = cs_row("SELECT st.*, c.naam cnaam, c.color, c.ctype FROM cs_student st JOIN cs_coaching c ON c.id=st.coaching_id
             WHERE st.id=? AND st.mobile=? AND st.status='active'", [$sid, $s['mobile']]);
if (!$K) cs_go('me.php');
if (!cs_access($K)['open']) cs_go('me.php?s=' . $sid);
$cid = (int)$K['coaching_id'];
$today = date('Y-m-d'); $now = time();
$self = 'game.php?s=' . $sid;

/** Coaching ki class (jaise "10th", "B.Com", khaali) → school engine ki class */
function cs_game_cls(array $k): string {
    $c = strtoupper(trim((string)$k['cls']));
    if (in_array($c, ['PG', 'LKG', 'UKG', 'NURSERY'], true)) return $c === 'NURSERY' ? 'PG' : $c;
    if (preg_match('/\d+/', $c, $m) && (int)$m[0] >= 1 && (int)$m[0] <= 12) return (string)min(10, (int)$m[0]);
    return $k['ctype'] === 'tuition' ? '6' : '10';          // SSC / computer / hobby: bade sawaal
}
function cs_game_q(array $it, string $cls): array {
    return $it['src'] === 'word' ? gm_word((string)$it['ref'], gm_band($cls)) : gm_math($cls, (string)$it['ref']);
}
function cs_rounds(int $sid, int $cid): array {
    $out = [];
    foreach (cs_all('SELECT * FROM cs_game_round WHERE student_id=? AND coaching_id=? AND d>=CURDATE()-INTERVAL 400 DAY ORDER BY d, n', [$sid, $cid]) as $r)
        $out[] = ['id' => (int)$r['id'], 'sid' => (string)$sid, 'date' => $r['d'], 'n' => (int)$r['n'], 'mode' => 'play', 'items' => json_decode($r['items'], true) ?: []];
    return $out;
}
function cs_open_round(array $rounds, string $today, int $now): ?array {
    foreach (array_reverse($rounds) as $r) {
        if ($r['date'] !== $today) break;
        foreach ($r['items'] as $it) if (gm_item_result($it, $now) === null) return $r;
    }
    return null;
}
function cs_save_round(int $id, array $items): void { cs_q('UPDATE cs_game_round SET items=? WHERE id=?', [json_encode($items), $id]); }

$cls = cs_game_cls($K);
$band = gm_band($cls);

// ---------------- POST ----------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!cs_csrf_ok()) cs_go($self);
    $a = cs_post('a');
    if ($a === 'start') {
        $rounds = cs_rounds($sid, $cid);
        if (!cs_open_round($rounds, $today, $now)) {
            $todayN = count(array_filter($rounds, fn($r) => $r['date'] === $today));
            if ($todayN < CS_GAME_ROUNDS) {
                // Pehle wo sawaal jo 1, 3, 7 din pehle galat hue the (wapas aate hain, tab tak yaad pakki ho)
                $items = [];
                $back = array_map(fn($d) => date('Y-m-d', strtotime("-$d days")), GM_REVIEW_DAYS);
                foreach ($rounds as $r) {
                    if (!in_array($r['date'], $back, true)) continue;
                    foreach ($r['items'] as $it) if (gm_item_result($it, $now) === false && count($items) < 2)
                        $items[] = ['src' => $it['src'], 'ref' => $it['ref'], 'review' => 1];
                }
                for ($i = count($items); $i < GM_ROUND; $i++) {
                    $src = ($band === 0 || $i % 5 < 3) ? 'math' : 'word';
                    $items[] = ['src' => $src, 'ref' => "cs|$sid|$today|" . ($todayN + 1) . "|$i|" . bin2hex(random_bytes(3))];
                }
                try {
                    cs_q('INSERT INTO cs_game_round (coaching_id,student_id,d,n,items) VALUES (?,?,?,?,?)', [$cid, $sid, $today, $todayN + 1, json_encode($items)]);
                } catch (Throwable $e) { /* do tab ek saath dabaye — ek hi round bane */ }
            }
        }
        cs_go($self . '&v=play');
    }
    if ($a === 'ans') {
        $rid = (int)cs_post('r'); $idx = (int)cs_post('i'); $choice = (int)cs_post('c', '-1');
        $db = cs_db(); $db->beginTransaction();
        $row = cs_row('SELECT * FROM cs_game_round WHERE id=? AND student_id=? AND coaching_id=? AND d=? FOR UPDATE', [$rid, $sid, $cid, $today]);
        $items = $row ? (json_decode($row['items'], true) ?: []) : [];
        $cur = null;
        foreach ($items as $i => $it) if (gm_item_result($it, $now) === null) { $cur = $i; break; }
        if ($row && $cur === $idx && !empty($items[$idx]['servedAt'])) {
            $q = cs_game_q($items[$idx], $cls);
            $items[$idx]['choice'] = $choice >= 0 && $choice <= 3 ? $choice : -1;
            $items[$idx]['correct'] = $items[$idx]['choice'] === (int)$q['ans'] && $now <= (int)$items[$idx]['servedAt'] + GM_SECONDS + GM_GRACE;
            cs_save_round($rid, $items);
        }
        $db->commit();
        cs_go($self . '&v=fb&r=' . $rid . '&i=' . $idx);
    }
    cs_go($self);
}

// ---------------- pages ----------------
$rounds = cs_rounds($sid, $cid);
$st = gm_stats($rounds, (string)$sid, $now, $today);
$v = cs_get('v');
cs_head('Game Zone — ' . $K['naam'], ['color' => $K['color']]);
echo '<div class="top"><a href="me.php?s=', $sid, '">‹</a><b>Game Zone</b><span class="tag" style="background:#fff;color:var(--c)">', (int)$st['xp'], ' XP</span></div><div class="wrap">';

if ($v === 'play' && ($R = cs_open_round($rounds, $today, $now))) {
    $idx = null;
    foreach ($R['items'] as $i => $it) if (gm_item_result($it, $now) === null) { $idx = $i; break; }
    if (empty($R['items'][$idx]['servedAt'])) {                      // samay abhi se shuru (padhne se pehle nahi)
        $R['items'][$idx]['servedAt'] = $now;
        cs_save_round($R['id'], $R['items']);
    }
    $it = $R['items'][$idx];
    $q = cs_game_q($it, $cls);
    $left = max(0, (int)$it['servedAt'] + GM_SECONDS - $now);
    echo '<div class="card"><div class="row" style="padding-top:0"><div class="grow"><b>Sawaal ', $idx + 1, ' / ', count($R['items']), '</b>',
         !empty($it['review']) ? ' <span class="tag">Phir se — pichhli baar galat tha</span>' : '', '</div><b id="t" style="font-size:20px">', $left, '</b></div>',
         '<p style="font-size:20px;font-weight:600;margin:14px 0">', h($q['q']), '</p><form method="post" id="f">', cs_csrf_field(),
         '<input type="hidden" name="a" value="ans"><input type="hidden" name="s" value="', $sid, '"><input type="hidden" name="r" value="', $R['id'], '"><input type="hidden" name="i" value="', $idx, '">';
    foreach ($q['opts'] as $k => $o) echo '<button class="plan" name="c" value="', $k, '" style="font-size:18px">', h($o), '</button>';
    echo '</form></div><script>var n=', $left, ',t=document.getElementById("t"),f=document.getElementById("f");',
         'var x=setInterval(function(){n--;t.textContent=Math.max(0,n);if(n<=0){clearInterval(x);var i=document.createElement("input");i.type="hidden";i.name="c";i.value="-1";f.appendChild(i);f.submit()}},1000);',
         'f.addEventListener("submit",function(){clearInterval(x)})</script>';
} elseif ($v === 'fb' && ($R = cs_row('SELECT * FROM cs_game_round WHERE id=? AND student_id=? AND coaching_id=?', [(int)cs_get('r'), $sid, $cid]))) {
    $items = json_decode($R['items'], true) ?: []; $i = (int)cs_get('i');
    $it = $items[$i] ?? null;
    $res = $it ? gm_item_result($it, $now) : null;
    if ($res === null) cs_go($self . '&v=play');
    $q = cs_game_q($it, $cls);
    echo '<div class="card"><div class="flash', $res ? '' : ' bad', '" style="font-size:18px">', $res ? 'Sahi! +10 XP' : (($it['choice'] ?? -1) < 0 ? 'Samay khatam!' : 'Galat — koi baat nahi'), '</div>',
         '<p style="margin:0">', h($q['q']), '</p><p><b>Sahi jawab: ', h($q['opts'][(int)$q['ans']]), '</b></p>',
         '<p>', h($q['fun']), '</p><p class="note">Niyam: ', h($q['rule']), '</p>',
         '<p><span class="tag green">', h($q['word'][0]), ' = ', h($q['word'][1]), '</span></p>';
    $done = true;
    foreach ($items as $x) if (gm_item_result($x, $now) === null) $done = false;
    if ($done) {
        $xp = gm_round_xp(['items' => $items, 'mode' => 'play'], $now);
        $right = count(array_filter($items, fn($x) => gm_item_result($x, $now) === true));
        echo '<div class="flash">Round khatam: ', $right, '/', count($items), ' sahi · +', $xp, ' XP</div>';
        $before = $st['right'] - $right;
        if (intdiv($st['right'], GM_CHEST_EVERY) > intdiv(max(0, $before), GM_CHEST_EVERY)) {
            [$em, $nm] = gm_chest_reward((string)$sid, intdiv($st['right'], GM_CHEST_EVERY));
            echo '<div class="card lock"><div class="big">Chest khula! ', h($em), '</div><p>', h($nm), ' mila</p></div>';
        }
        echo '<a class="btn full" href="', h($self), '">Game Zone</a>';
    } else {
        echo '<a class="btn full" href="', h($self), '&v=play">Agla sawaal</a>';
    }
    echo '</div>';
} else {
    // ---- home ----
    $W = gm_words(); $w = $W[((int)date('z')) % count($W)];
    $todayN = count(array_filter($rounds, fn($r) => $r['date'] === $today));
    $open = cs_open_round($rounds, $today, $now);
    echo '<h1>', h($K['naam']), '</h1><div class="grid">',
         '<div class="kpi"><small>Level ', (int)$st['level'], '</small><strong style="font-size:16px">', h(gm_level_name((int)$st['level'])), '</strong>',
         '<div style="background:#E4E7EB;border-radius:6px;height:8px;margin-top:6px"><div style="background:var(--c);height:8px;border-radius:6px;width:', (int)min(100, $st['levelXp'] * 100 / max(1, $st['levelNeed'])), '%"></div></div></div>',
         '<div class="kpi"><small>Streak</small><strong>', (int)$st['streak'], ' din</strong></div>',
         '<div class="kpi"><small>Sahi jawab</small><strong>', (int)$st['right'], '</strong></div>',
         '<div class="kpi"><small>Agla chest</small><strong>', (int)$st['toChest'], ' sahi baad</strong></div></div>';
    echo '<div class="card"><b>Aaj ka shabd</b><p style="font-size:22px;font-weight:700;margin:6px 0">', h($w['w']), ' <span style="font-weight:500">= ', h($w['h']), '</span></p>',
         '<p class="note" style="margin:0">', h($w['m']), '</p><p style="margin:6px 0 0">', h($w['e']), '</p></div>';
    echo '<div class="card">';
    if ($open) echo '<a class="btn full" style="margin:0" href="', h($self), '&v=play">Round jaari rakhiye</a>';
    elseif ($todayN < CS_GAME_ROUNDS)
        echo '<form method="post">', cs_csrf_field(), '<input type="hidden" name="a" value="start"><input type="hidden" name="s" value="', $sid, '">',
             '<button class="btn full" style="margin:0;font-size:18px">', $todayN ? 'Ek aur round khelo' : 'Aaj ka round khelo', '</button></form>',
             '<p class="note">5 sawaal · har sawaal 20 second · aaj ', CS_GAME_ROUNDS - $todayN, ' round baaki</p>';
    else echo '<p><b>Aaj ke saare round ho gaye!</b> Kal phir aaiye — streak mat todiye.</p>';
    echo '</div>';
    // ---- coaching ki weekly league (sirf naam ka pehla hissa) ----
    $wkStart = date('Y-m-d', strtotime('monday this week'));
    $xpBy = [];
    foreach (cs_all('SELECT student_id, items FROM cs_game_round WHERE coaching_id=? AND d>=?', [$cid, $wkStart]) as $r)
        $xpBy[(int)$r['student_id']] = ($xpBy[(int)$r['student_id']] ?? 0) + gm_round_xp(['items' => json_decode($r['items'], true) ?: [], 'mode' => 'play'], $now);
    arsort($xpBy);
    $top = array_slice($xpBy, 0, 10, true);
    if ($top) {
        $names = [];
        $ids = array_keys($top);
        foreach (cs_all('SELECT id, naam FROM cs_student WHERE coaching_id=? AND id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')', array_merge([$cid], $ids)) as $n)
            $names[(int)$n['id']] = explode(' ', trim($n['naam']))[0];
        echo '<div class="card"><b>Is hafte ki league — ', h($K['cnaam']), '</b>';
        $pos = 0;
        foreach ($top as $id => $x) {
            $pos++;
            echo '<div class="row"', $id === $sid ? ' style="font-weight:700"' : '', '><span style="width:24px">', $pos, '</span><div class="grow">', h($names[$id] ?? '—'), $id === $sid ? ' (aap)' : '', '</div><span class="amt">', $x, ' XP</span></div>';
        }
        echo '</div>';
    }
}
echo '</div>';
cs_foot();
