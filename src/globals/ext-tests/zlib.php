<?php

declare(strict_types=1);

assert(function_exists('gzcompress'));
assert(function_exists('gzdecode'));

$input = str_repeat('The quick brown fox jumps over the lazy dog. ', 10);
$compressed = gzcompress($input);
assert(is_string($compressed));
assert(strlen($compressed) < strlen($input));

$uncompressed = gzdecode($compressed);
assert(is_string($uncompressed));
assert($uncompressed === $input);
