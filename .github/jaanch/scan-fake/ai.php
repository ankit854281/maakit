<?php
// Nakli AI (sirf jaanch ke liye): OpenAI Responses API jaisa jawab deta hai.
$in = json_decode(file_get_contents('php://input'), true);
$img = 0; foreach ($in['input'][0]['content'] ?? [] as $c) if (($c['type'] ?? '') === 'input_image' && str_starts_with($c['image_url'] ?? '', 'data:image/')) $img++;
if (!$img || ($_SERVER['HTTP_AUTHORIZATION'] ?? '') !== 'Bearer test-key') { http_response_code(400); exit('{}'); }
$name = $in['text']['format']['name'] ?? '';
$out = $name === 'book_contents'
  ? ['chapters' => [['name' => '1. Real Numbers ........ 1', 'topics' => ['Euclid’s Division Lemma', 'HCF and LCM 5']],
                    ['name' => 'Chapter 2: Polynomials 23-31', 'topics' => []], ['name' => 'Polynomials', 'topics' => []],
                    ['name' => 'अध्याय 3 – कबीर की साखी 15', 'topics' => []], ['name' => '7', 'topics' => []]], 'uncertain' => false]
  : ['topics' => ['Polynomials — zeroes of a polynomial', 'Relationship between zeroes and coefficients 12'], 'goal' => 'Graph activity on chart paper', 'homework' => 'Ex 2.1 Q1–3', 'uncertain' => true];
header('Content-Type: application/json');
echo json_encode(['output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => json_encode($out, JSON_UNESCAPED_UNICODE)]]]]]);
