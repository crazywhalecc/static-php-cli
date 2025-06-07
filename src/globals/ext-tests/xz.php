<?php

declare(strict_types=1);

assert(function_exists('xzencode'));
assert(function_exists('xzdecode'));

$input = str_repeat('The quick brown fox jumps over the lazy dog. ', 10);
$compressed = xzencode($input);
assert(is_string($compressed));
assert(strlen($compressed) < strlen($input));

$uncompressed = xzdecode($compressed);
assert(is_string($uncompressed));
assert($uncompressed === $input);
