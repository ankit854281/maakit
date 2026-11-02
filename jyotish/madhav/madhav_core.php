<?php
declare(strict_types=1);

/**
 * MADHAV knowledge and reply functions.
 * No database, user-tracking, astrology, or implicit reuse of Jyotish AI keys.
 * Original short paraphrases; scholar/content review still required.
 */
function madhav_knowledge(): array {
    $raw = file_get_contents(__DIR__ . '/knowledge.json');
    $decoded = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($decoded) || !isset($decoded['verses']) || !is_array($decoded['verses'])) {
        throw new RuntimeException('Knowledge data unavailable');
    }
    return $decoded['verses'];
}
function madhav_lower(string $text): string {
    return function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
}
function madhav_length(string $text): int {
    return function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
}
function madhav_crisis(string $q): bool {
    $q = madhav_lower($q);
    $phrases = ['suicide', 'kill myself', 'end my life', 'want to die', 'self harm',
      'आत्महत्या', 'खुदकुशी', 'ख़ुदकुशी', 'जीना नहीं', 'मरना चाहता',
      'मरना चाहती', 'मर जाऊँ', 'marna chahta', 'jeena nahi'];
    foreach ($phrases as $phrase) if (str_contains($q, $phrase)) return true;
    return false;
}
function madhav_select(string $question, array $verses): ?array {
    $text = madhav_lower($question);
    $best = null; $score = 0;
    foreach ($verses as $verse) {
        $points = 0;
        foreach ($verse['keywords'] ?? [] as $keyword) {
            if (str_contains($text, madhav_lower((string)$keyword))) {
                $points += max(1, min(3, (int)floor(madhav_length((string)$keyword) / 4)));
            }
        }
        if ($points > $score) { $score = $points; $best = $verse; }
    }
    return $best;
}
function madhav_suggestions(?array $verse, string $lang, array $history): array {
    $base = $verse ? ($verse[$lang === 'en' ? 'suggest_en' : 'suggest_hi'] ?? []) :
      ($lang === 'en' ? ['How can I calm my mind?', 'What is karma yoga?', 'How can I practice kindness?'] :
                        ['मन को शांत कैसे करूँ?', 'कर्मयोग क्या है?', 'करुणा कैसे बढ़ाऊँ?']);
    $used = [];
    foreach (array_slice($history, -8) as $entry) {
        if (is_array($entry) && isset($entry['q']) && is_string($entry['q'])) {
            $used[] = madhav_lower(trim($entry['q']));
        }
    }
    $fresh = [];
    foreach ($base as $item) {
        if (!in_array(madhav_lower($item), $used, true) && !in_array($item, $fresh, true)) $fresh[] = $item;
    }
    return array_slice(count($fresh) ? $fresh : $base, 0, 3);
}
function madhav_validate(array $in): array {
    $q = $in['question'] ?? null;
    if (!is_string($q) || trim($q) === '' || madhav_length($q) > 500) {
        throw new InvalidArgumentException('Invalid question');
    }
    $lang = $in['language'] ?? 'hi';
    if (!in_array($lang, ['hi', 'en'], true)) throw new InvalidArgumentException('Invalid language');
    $history = $in['history'] ?? [];
    if (!is_array($history) || !array_is_list($history) || count($history) > 8) {
        throw new InvalidArgumentException('Invalid history');
    }
    foreach ($history as $item) {
        if (!is_array($item) || !is_string($item['q'] ?? null) || madhav_length($item['q']) > 500) {
            throw new InvalidArgumentException('Invalid history item');
        }
    }
    return ['question' => trim($q), 'language' => $lang, 'history' => $history];
}
function madhav_reply(array $in, ?callable $model = null): array {
    $data = madhav_validate($in);
    $q = $data['question']; $lang = $data['language']; $history = $data['history'];
    if (madhav_crisis($q)) {
        return [
          'answer' => $lang === 'hi' ?
            'पार्थ, अगर तुम्हें खुद को नुकसान पहुँचाने का विचार आ रहा है, तो अभी किसी भरोसेमंद व्यक्ति के साथ रहो। भारत में तत्काल खतरे पर 112 और मानसिक स्वास्थ्य सहायता के लिए Tele-MANAS 14416 पर संपर्क कर सकते हो।' :
            'If you might hurt yourself, stay with someone you trust and seek immediate support. In India call 112 for an emergency or Tele-MANAS 14416 for mental health support.',
          'mode' => 'safety', 'citation' => null, 'suggestions' => []
        ];
    }
    $verse = madhav_select($q, madhav_knowledge());
    $suggestions = madhav_suggestions($verse, $lang, array_merge($history, [['q' => $q]]));
    if (!$verse) return [
        'answer' => $lang === 'hi' ? 'पार्थ, इस विषय के लिए अभी हमारे सत्यापित ज्ञान-संग्रह में संदर्भ नहीं है। मैं श्लोक गढ़कर नहीं बताऊँगा।' :
            'Parth, our reviewed knowledge set does not yet cover this question. I will not invent a scripture reference.',
        'mode' => 'limited', 'citation' => null, 'suggestions' => $suggestions
    ];
    $fallback = $lang === 'hi' ? 'पार्थ, ' . $verse['hi'] : 'Parth, ' . $verse['en'];
    $answer = $fallback; $mode = 'curated';
    if ($model !== null) {
        try {
            $candidate = $model($q, $verse, $lang, $history);
            if (is_string($candidate) && trim($candidate) !== '' && madhav_length($candidate) <= 1800) {
                $answer = trim($candidate); $mode = 'ai';
            }
        } catch (Throwable $ignored) {
            // Safe, clearly labelled curated fallback; provider error not sent to the client.
        }
    }
    return ['answer' => $answer, 'mode' => $mode,
        'citation' => ['id' => $verse['id'], 'sanskrit' => $verse['sanskrit'],
                       'meaning' => $verse[$lang], 'review_status' => 'pending_scholar_review'],
        'suggestions' => $suggestions];
}
