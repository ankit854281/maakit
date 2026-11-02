<?php
declare(strict_types=1);
require_once __DIR__ . '/madhav_core.php';
function check(bool $ok, string $message): void {
    if (!$ok) { fwrite(STDERR, "FAIL: $message\n"); exit(1); }
    echo "PASS: $message\n";
}
$verses = madhav_knowledge();
check(count($verses) === 4, 'four sample verses load');
check(madhav_select('मेरा मन परेशान है', $verses)['id'] === '6.26', 'Hindi mind retrieval');
check(madhav_select('I want success at work', $verses)['id'] === '2.47', 'English work retrieval');
check(madhav_select('मुझे गुस्सा आता है', $verses)['id'] === '12.13', 'compassion retrieval');
check(madhav_crisis('मैं आत्महत्या करना चाहता हूँ'), 'Hindi safety trigger');
check(madhav_crisis('I want to die'), 'English safety trigger');
$r = madhav_reply(['question' => 'कर्म क्या है?', 'language' => 'hi', 'history' => []]);
check($r['citation']['id'] === '2.47' && $r['mode'] === 'curated', 'grounded fallback with server citation');
check(count($r['suggestions']) === 3, 'three follow-up suggestions');
$question = $r['suggestions'][0];
$r2 = madhav_reply(['question' => 'कर्म क्या है?', 'language' => 'hi', 'history' => [['q' => $question]]]);
check(!in_array($question, $r2['suggestions'], true), 'used question not immediately repeated');
$r3 = madhav_reply(['question' => 'xyzzy cloud server', 'language' => 'en', 'history' => []]);
check($r3['citation'] === null && $r3['mode'] === 'limited', 'unknown question has no invented citation');
$r4 = madhav_reply(['question' => 'I want to die', 'language' => 'en', 'history' => []], static function () {throw new RuntimeException('provider should not run');});
check($r4['mode'] === 'safety' && $r4['citation'] === null, 'crisis bypasses AI');
$r5 = madhav_reply(['question' => 'work and goals', 'language' => 'en', 'history' => []], static fn () => 'Focus on a small practical action.');
check($r5['mode'] === 'ai' && $r5['citation']['id'] === '2.47', 'model hook with server-selected citation');
$r6 = madhav_reply(['question' => 'work', 'language' => 'en', 'history' => []], static function () {throw new RuntimeException('failed');});
check($r6['mode'] === 'curated', 'AI provider failure safely falls back');
try { madhav_reply(['question' => str_repeat('a', 501)]); check(false, 'input validation'); }
catch (InvalidArgumentException $e) { check(true, 'input length validation'); }
echo "All MADHAV unit checks passed.\n";
