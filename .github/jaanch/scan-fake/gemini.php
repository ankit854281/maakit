<?php
// Synthetic book-photo provider. Never calls an external AI.
header('Content-Type: application/json');
$in = json_decode(file_get_contents('php://input'), true);
$case = $_GET['case'] ?? '';
if ($case === '429' || $case === '500') { http_response_code((int)$case); echo '{"error":{"message":"synthetic failure"}}'; exit; }
if ($case === 'invalid') { echo '{"candidates":[{"finishReason":"STOP","content":{"parts":[{"text":"broken JSON"}]}}]}'; exit; }
if ($case === 'blocked') { echo '{"promptFeedback":{"blockReason":"SAFETY"}}'; exit; }
if ($case === 'openai') {
    if (($_SERVER['HTTP_AUTHORIZATION'] ?? '') !== 'Bearer test-openai') { http_response_code(400); exit; }
    echo json_encode(['output' => [['content' => [['type' => 'output_text', 'text' => '{"chapters":[{"name":"OpenAI chosen","page":"1","topics":[]}],"uncertain":false}']]]]]);
    exit;
}
$schema = $in['generationConfig']['responseSchema'] ?? [];
$photo = $in['contents'][0]['parts'][1]['inline_data'] ?? [];
if (($_SERVER['HTTP_X_GOOG_API_KEY'] ?? '') !== 'test-gemini'
    || ($in['generationConfig']['responseMimeType'] ?? '') !== 'application/json'
    || str_contains(json_encode($schema), 'additionalProperties')
    || ($photo['mime_type'] ?? '') !== 'image/jpeg'
    || base64_decode($photo['data'] ?? '') !== "\xFF\xD8\xFF" . str_repeat('x', 250)
    || empty($in['systemInstruction']['parts'][0]['text'])) {
    http_response_code(400); echo '{"error":"bad request contract"}'; exit;
}
if (isset($schema['properties']['chapters'])) {
    if (!in_array('page', $schema['properties']['chapters']['items']['required'] ?? [], true)) { http_response_code(400); exit; }
    $data = ['chapters' => [['name' => '1. Trees', 'page' => '१२', 'topics' => ['Roots', 'Roots']]], 'uncertain' => false];
} elseif (isset($schema['properties']['summary'])) {
    $data = ['chapter' => '1. Trees', 'summary' => ' Tree notes ', 'points' => [' Roots '], 'words' => [['w' => 'root', 'm' => 'जड़']], 'qa' => [['q' => 'What grows?', 'a' => 'Trees.']], 'uncertain' => false];
} else {
    $data = ['topics' => ['1. Roots', 'Leaves'], 'goal' => ' Learn trees ', 'homework' => 'Read', 'uncertain' => true];
}
if ($case === 'incomplete') unset($data['uncertain']);
echo json_encode(['candidates' => [['finishReason' => $case === 'truncated' ? 'MAX_TOKENS' : 'STOP', 'content' => ['parts' => [
    ['thought' => true, 'text' => 'ignore internal thought'], ['text' => json_encode($data)]
]]]]]);
