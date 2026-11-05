<?php
// Standalone, synthetic HTTP tests. No config.php, database or real credentials.
require_once __DIR__ . '/../../school/learning_lib.php';
require_once __DIR__ . '/../../school/scan_lib.php';
function gs_check(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException('Gemini scan: ' . $message);
    echo "PASS Gemini scan: $message\n";
}
$names = ['GEMINI_API_KEY', 'GEMINI_MODEL', 'GEMINI_TEST_URL', 'SCHOOL_AI_API_KEY', 'SCHOOL_AI_MODEL', 'SCHOOL_AI_VISION_MODEL', 'SCHOOL_AI_TEST_URL', 'NO_PROXY', 'no_proxy'];
$before = [];
foreach ($names as $name) { $before[$name] = getenv($name); putenv($name . '='); }
putenv('NO_PROXY=127.0.0.1');
putenv('no_proxy=127.0.0.1');
$socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
if (!$socket) throw new RuntimeException($error);
$address = stream_socket_get_name($socket, false);
fclose($socket);
$null = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
$server = proc_open([PHP_BINARY, '-S', $address, '-t', __DIR__ . '/scan-fake'],
    [0 => ['file', $null, 'r'], 1 => STDOUT, 2 => STDERR], $pipes);
try {
    for ($i = 0; $i < 50; $i++) {
        $ready = @stream_socket_client('tcp://' . $address, $errno, $error, 0.2);
        if ($ready) { fclose($ready); break; }
        usleep(100000);
    }
    if (!$ready) throw new RuntimeException('Synthetic local server is unreachable: ' . $error);
    $url = 'http://' . $address . '/gemini.php';
    putenv('GEMINI_TEST_URL=' . $url);
    gs_check(!sc_ai_ready(), 'no keys uses phone');
    putenv('GEMINI_API_KEY=test-gemini');
    gs_check(sc_ai_ready() && !sc_ai_ready('page'), 'Gemini ready only for book modes');
    $images = [['image/jpeg', "\xFF\xD8\xFF" . str_repeat('x', 250)]];
    $chapters = sc_ai_read('chapters', '9th', 'Science', $images);
    gs_check($chapters === ['chapters' => [['name' => 'Trees', 'page' => '12', 'topics' => ['Roots']]], 'uncertain' => false], 'chapters retain cleaned pages and topics');
    $plan = sc_ai_read('lplan', '9th', 'Science', $images);
    gs_check($plan['topics'] === ['Roots', 'Leaves'] && $plan['goal'] === 'Learn trees' && $plan['uncertain'], 'lesson plan uses existing cleaner');
    $notes = sc_ai_read('notes', '9th', 'Science', $images);
    gs_check($notes['chapter'] === 'Trees' && $notes['summary'] === 'Tree notes' && $notes['words'][0]['m'] === 'जड़', 'notes use existing cleaner');
    foreach (['429', '500', 'invalid', 'blocked', 'incomplete', 'truncated'] as $case) {
        putenv('GEMINI_TEST_URL=' . $url . '?case=' . $case);
        $caught = false;
        try { sc_ai_read('chapters', '9th', 'Science', $images); }
        catch (RuntimeException $e) {
            $caught = true;
            if ($case === '429') gs_check($e->getCode() === 429 && $e->getMessage() === SC_GEMINI_LIMIT, '429 carries phone fallback message');
        }
        gs_check($caught, "$case rejected for phone fallback");
    }
    $caught = false;
    try { sc_ai_read('page', '9th', 'Science', $images); } catch (RuntimeException $e) { $caught = true; }
    gs_check($caught, 'page mode never sent to Gemini');
    putenv('SCHOOL_AI_API_KEY=test-openai');
    putenv('SCHOOL_AI_MODEL=test-model');
    putenv('SCHOOL_AI_TEST_URL=' . $url . '?case=openai');
    gs_check(sc_ai_read('chapters', '9th', 'Science', $images)['chapters'][0]['name'] === 'OpenAI chosen', 'OpenAI key takes precedence');
} finally {
    if (is_resource($server)) { proc_terminate($server); proc_close($server); }
    foreach ($before as $name => $value) putenv($value === false ? $name : $name . '=' . $value);
}
