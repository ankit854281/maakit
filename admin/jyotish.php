<?php
// ============================================================
// Maakit Jyotish — log kya dekh aur poochh rahe hain (sirf admin)
//
// Data jyotish/track.php aur jyotish/ai.php likhte hain (table jy_events).
// Naam, poori janm-tareekh aur mood kabhi save nahi hote. Sawaal ka text
// 90 din, baaki ginti 180 din rehti hai (jy_cleanup).
// ============================================================
require_once __DIR__ . '/../inc/fn.php';
require_once __DIR__ . '/../jyotish/ai_lib.php';
$u = need_role('admin');
$page_title = t('Jyotish usage', 'ज्योतिष — कौन क्या देख रहा') . ' — Maakit';

$days = (int)get('days', '30');
if (!in_array($days, [1, 7, 30, 90], true)) $days = 30;
$topicF = get('topic', '');
$since = date('Y-m-d H:i:s', strtotime('-' . $days . ' days'));
$ready = true;
try { $pdo->query('SELECT 1 FROM jy_events LIMIT 1'); jy_cleanup($pdo); } catch (Throwable $e) { $ready = false; }

$TOPICS = ['career' => t('Job & career', 'नौकरी / करियर'), 'marriage' => t('Marriage & love', 'शादी / रिश्ता'), 'money' => t('Money & business', 'पैसा / व्यापार'),
    'education' => t('Studies & exams', 'पढ़ाई / परीक्षा'), 'health' => t('Health', 'सेहत'), 'family' => t('Children & family', 'बच्चे / परिवार'),
    'property' => t('House, land, vehicle', 'घर / ज़मीन / गाड़ी'), 'travel' => t('Travel & abroad', 'यात्रा / विदेश'), 'remedy' => t('Remedies', 'उपाय / पूजा'),
    'planets' => t('Dasha & planets', 'दशा / ग्रह'), 'today' => t('Today / rashifal', 'आज / राशिफल'), 'other' => t('Other', 'बाकी')];
$CATS = ['sleep' => t('Sleep', 'नींद'), 'breath' => t('Breathing', 'साँस'), 'move' => t('Walk & movement', 'चलना'), 'focus' => t('Focus', 'एक काम'),
    'connect' => t('Call someone', 'अपनों से बात'), 'gratitude' => t('Gratitude', 'आभार'), 'activate' => t('Small task', 'छोटा काम'), 'learn' => t('Learning', 'सीखना'), 'seva' => t('Seva', 'सेवा')];
$RASHI = ['Mesha','Vrishabha','Mithuna','Karka','Simha','Kanya','Tula','Vrishchika','Dhanu','Makara','Kumbha','Meena'];

$one = function (string $sql, array $a = []) use ($pdo) { $s = $pdo->prepare($sql); $s->execute($a); return $s->fetchColumn(); };
$all = function (string $sql, array $a = []) use ($pdo) { $s = $pdo->prepare($sql); $s->execute($a); return $s->fetchAll(); };

if ($ready) {
    $people = (int)$one("SELECT COUNT(DISTINCT device) FROM jy_events WHERE created >= ? AND device <> ''", [$since]);
    $count = [];
    foreach ($all('SELECT kind, COUNT(*) c FROM jy_events WHERE created >= ? GROUP BY kind', [$since]) as $r) $count[$r['kind']] = (int)$r['c'];
    $daily = $all("SELECT DATE(created) d, COUNT(DISTINCT CASE WHEN device <> '' THEN device END) people, SUM(kind='kundli') kundli, SUM(kind='milan') milan, SUM(kind='ai_q') qs, SUM(kind='upaya_done') upaya
                   FROM jy_events WHERE created >= ? GROUP BY DATE(created) ORDER BY d DESC LIMIT 31", [$since]);
    $topics = $all("SELECT topic, COUNT(*) c FROM jy_events WHERE kind='ai_q' AND created >= ? GROUP BY topic ORDER BY c DESC", [$since]);
    $qsql = "SELECT created, lang, topic, q FROM jy_events WHERE kind='ai_q' AND q IS NOT NULL AND created >= ?" . ($topicF !== '' ? ' AND topic = ?' : '') . ' ORDER BY id DESC LIMIT 150';
    $questions = $all($qsql, $topicF !== '' ? [$since, $topicF] : [$since]);
    $cities = $all("SELECT city, COUNT(*) c FROM jy_events WHERE kind='kundli' AND city <> '' AND created >= ? GROUP BY city ORDER BY c DESC LIMIT 12", [$since]);
    $rashis = $all("SELECT rashi, COUNT(*) c FROM jy_events WHERE kind='kundli' AND rashi IS NOT NULL AND created >= ? GROUP BY rashi ORDER BY c DESC", [$since]);
    $ages = $all("SELECT CASE WHEN YEAR(NOW())-birth_year < 18 THEN 'under 18' WHEN YEAR(NOW())-birth_year < 25 THEN '18–24' WHEN YEAR(NOW())-birth_year < 35 THEN '25–34'
                  WHEN YEAR(NOW())-birth_year < 50 THEN '35–49' ELSE '50+' END band, COUNT(*) c FROM jy_events WHERE kind='kundli' AND birth_year IS NOT NULL AND created >= ? GROUP BY band ORDER BY band", [$since]);
    $genders = $all("SELECT gender, COUNT(*) c FROM jy_events WHERE kind='kundli' AND gender <> '' AND created >= ? GROUP BY gender ORDER BY c DESC", [$since]);
    $langs = $all("SELECT lang, COUNT(*) c FROM jy_events WHERE lang <> '' AND kind IN ('ai_q','card') AND created >= ? GROUP BY lang ORDER BY c DESC", [$since]);
    $upayas = $all("SELECT cat, COUNT(*) c FROM jy_events WHERE kind='upaya_done' AND cat <> '' AND created >= ? GROUP BY cat ORDER BY c DESC", [$since]);
    $milanAvg = $one("SELECT ROUND(AVG(score),1) FROM jy_events WHERE kind='milan' AND score IS NOT NULL AND created >= ?", [$since]);
}
$tbl = function (array $head, array $rows) {
    echo '<div class="tablewrap"><table><tr>';
    foreach ($head as $hd) echo '<th>' . h($hd) . '</th>';
    echo '</tr>';
    if (!$rows) echo '<tr><td colspan="' . count($head) . '" class="meta">' . h(t('Nothing yet', 'अभी कुछ नहीं')) . '</td></tr>';
    foreach ($rows as $r) { echo '<tr>'; foreach ($r as $c) echo '<td>' . h($c) . '</td>'; echo '</tr>'; }
    echo '</table></div>';
};
include __DIR__ . '/../inc/panel.php';
?>
<section><div class="wrap">
  <h2><?= t('Maakit Jyotish: what people check and ask', 'Maakit Jyotish: लोग क्या देख और पूछ रहे हैं') ?></h2>
  <p class="lead"><?= t('No names, exact birth dates or moods are saved. Question text is kept 90 days, counts 180 days. People who chose "Don\'t send" are not counted.', 'नाम, पूरी जन्म-तारीख और मूड कभी सेव नहीं होते। सवाल का text 90 दिन, गिनती 180 दिन रहती है। जिन्होंने "मत भेजिए" चुना, वे नहीं गिने जाते।') ?></p>
  <p><?php foreach ([1 => t('Today', 'आज'), 7 => t('7 days', '7 दिन'), 30 => t('30 days', '30 दिन'), 90 => t('90 days', '90 दिन')] as $d => $lbl): ?>
    <a class="btn <?= $d === $days ? 'btn-brand' : '' ?>" href="?days=<?= $d ?>"><?= h($lbl) ?></a>
  <?php endforeach; ?> · <a href="/jyotish/" target="_blank" rel="noopener"><?= t('Open Maakit Jyotish', 'Maakit Jyotish खोलिए') ?></a></p>

<?php if (!$ready): ?>
  <div class="err"><?= t('The Jyotish table is not ready yet. Run the website update once (update.php) and open this page again.', 'ज्योतिष की table अभी नहीं बनी। एक बार website update (update.php) चलाइए, फिर यह पन्ना खोलिए।') ?></div>
<?php else: ?>
  <div class="box">
    <b><?= $people ?></b> <?= t('phones used it', 'फ़ोन से खोला गया') ?> ·
    <b><?= $count['kundli'] ?? 0 ?></b> <?= t('kundlis', 'कुंडली') ?> ·
    <b><?= $count['milan'] ?? 0 ?></b> <?= t('milans', 'मिलान') ?><?= $milanAvg !== null && $milanAvg !== false ? ' (' . t('avg', 'औसत') . ' ' . h($milanAvg) . '/36)' : '' ?> ·
    <b><?= $count['ai_q'] ?? 0 ?></b> <?= t('AI questions', 'AI से सवाल') ?> ·
    <b><?= $count['card'] ?? 0 ?></b> <?= t('daily cards', 'आज के कार्ड') ?> ·
    <b><?= $count['upaya_done'] ?? 0 ?></b> <?= t('upayas done', 'उपाय पूरे किए') ?> ·
    <b><?= $count['share'] ?? 0 ?></b> <?= t('WhatsApp shares', 'WhatsApp पर भेजे') ?>
    <?php $helpN = ($count['crisis'] ?? 0) + ($count['help'] ?? 0); if ($helpN): ?>
      <p class="help"><?= t('The help screen (Tele-MANAS 14416) was shown', 'मदद का पन्ना (Tele-MANAS 14416) दिखाया गया') ?> <b><?= (int)$helpN ?></b> <?= t('times. The words are never saved.', 'बार। शब्द कभी सेव नहीं होते।') ?></p>
    <?php endif; ?>
  </div>

  <div class="grid g2" style="margin-top:20px">
    <div class="box"><h3 style="margin-top:0"><?= t('What people ask about', 'लोग किस बारे में पूछ रहे हैं') ?></h3>
      <?php $tbl([t('Topic', 'विषय'), t('Questions', 'सवाल')], array_map(fn($r) => [$TOPICS[$r['topic']] ?? $r['topic'], (int)$r['c']], $topics)); ?></div>
    <div class="box"><h3 style="margin-top:0"><?= t('Day by day', 'दिन के हिसाब से') ?></h3>
      <?php $tbl([t('Date', 'तारीख'), t('Phones', 'फ़ोन'), t('Kundli', 'कुंडली'), t('Milan', 'मिलान'), t('Questions', 'सवाल'), t('Upaya', 'उपाय')],
        array_map(fn($r) => [date('d-m-Y', strtotime($r['d'])), (int)$r['people'], (int)$r['kundli'], (int)$r['milan'], (int)$r['qs'], (int)$r['upaya']], $daily)); ?></div>
  </div>

  <div class="box" style="margin-top:20px"><h3 style="margin-top:0"><?= t('Latest questions', 'ताज़ा सवाल') ?></h3>
    <p><a href="?days=<?= $days ?>" class="btn <?= $topicF === '' ? 'btn-brand' : '' ?>"><?= t('All', 'सभी') ?></a>
    <?php foreach ($TOPICS as $k => $lbl): ?><a href="?days=<?= $days ?>&amp;topic=<?= h($k) ?>" class="btn <?= $topicF === $k ? 'btn-brand' : '' ?>"><?= h($lbl) ?></a> <?php endforeach; ?></p>
    <?php $tbl([t('When', 'कब'), t('Topic', 'विषय'), t('Language', 'भाषा'), t('Question', 'सवाल')],
      array_map(fn($r) => [date('d-m H:i', strtotime($r['created'])), $TOPICS[$r['topic']] ?? $r['topic'], $r['lang'], $r['q']], $questions)); ?>
  </div>

  <div class="grid g2" style="margin-top:20px">
    <div class="box"><h3 style="margin-top:0"><?= t('Birth places (from kundlis)', 'जन्म स्थान (कुंडली से)') ?></h3>
      <?php $tbl([t('Place', 'जगह'), t('Kundlis', 'कुंडली')], array_map(fn($r) => [$r['city'], (int)$r['c']], $cities)); ?></div>
    <div class="box"><h3 style="margin-top:0"><?= t('Age groups', 'उम्र') ?></h3>
      <?php $tbl([t('Age', 'उम्र'), t('Kundlis', 'कुंडली')], array_map(fn($r) => [$r['band'], (int)$r['c']], $ages)); ?>
      <h3><?= t('Gender', 'लिंग') ?></h3>
      <?php $tbl([t('Gender', 'लिंग'), t('Kundlis', 'कुंडली')], array_map(fn($r) => [$r['gender'], (int)$r['c']], $genders)); ?></div>
    <div class="box"><h3 style="margin-top:0"><?= t('Moon signs', 'चंद्र राशि') ?></h3>
      <?php $tbl([t('Rashi', 'राशि'), t('Kundlis', 'कुंडली')], array_map(fn($r) => [$RASHI[(int)$r['rashi']] ?? '?', (int)$r['c']], $rashis)); ?></div>
    <div class="box"><h3 style="margin-top:0"><?= t('Upayas people finished', 'कौन से उपाय पूरे हुए') ?></h3>
      <?php $tbl([t('Kind', 'तरह'), t('Done', 'पूरे')], array_map(fn($r) => [$CATS[$r['cat']] ?? $r['cat'], (int)$r['c']], $upayas)); ?>
      <h3><?= t('Language chosen', 'चुनी गई भाषा') ?></h3>
      <?php $tbl([t('Language', 'भाषा'), t('Uses', 'बार')], array_map(fn($r) => [$r['lang'], (int)$r['c']], $langs)); ?></div>
  </div>
<?php endif; ?>
</div></section>
<?php include __DIR__ . '/../inc/foot.php'; ?>
