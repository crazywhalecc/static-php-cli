<?php

declare(strict_types=1);

assert(function_exists('bzcompress'));
assert(function_exists('bzdecompress'));

$input = str_repeat('The quick brown fox jumps over the lazy dog. ', 10);
$compressed = bzcompress($input);
assert(is_string($compressed));
assert(strlen($compressed) < strlen($input));

$uncompressed = bzdecompress($compressed);
assert(is_string($uncompressed));
assert($uncompressed === $input);
