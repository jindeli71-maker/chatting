<?php
// Simple profanity filter utilities

/**
 * Replace each occurrence of a bad word with asterisks of the same length.
 *
 * @param string $text
 * @return string
 */
function censor_bad_words($text) {
    // list of words that should be censored. All checks are case‑insensitive.
    // You can extend this array with additional terms as needed.
    $badWords = [
        'fuck',
        'shit',
        'bitch',
        'asshole',
        'damn',
        // add more words here
    ];

    foreach ($badWords as $word) {
        // word boundary to avoid partial matches, case insensitive
        $pattern = '/\b' . preg_quote($word, '/') . '\b/i';
        $text = preg_replace_callback($pattern, function ($m) {
            // preserve the length of the matched word
            return str_repeat('*', mb_strlen($m[0]));
        }, $text);
    }

    return $text;
}
