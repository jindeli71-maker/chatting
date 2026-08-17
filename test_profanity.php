<?php
require_once 'includes/filter.php';

// Quick diagnostic to ensure profanity filter works as expected.
header('Content-Type: text/plain');

$tests = [
    'hello world' => 'hello world',
    'fuck' => '****',
    'FUCK' => '****',
    'This is shitty' => 'This is shitty', // only whole words
    'you are an asshole' => 'you are an *******',
    'damn it' => '**** it',
    'no badwords here' => 'no badwords here',
];

foreach ($tests as $input => $expected) {
    $out = censor_bad_words($input);
    $status = ($out === $expected) ? 'PASS' : 'FAIL';
    echo "$status: '$input' -> '$out' (expected '$expected')\n";
}
