<?php

declare(strict_types=1);

assert(function_exists('lz4_compress'));
assert(function_exists('lz4_uncompress'));

$input = str_repeat('The quick brown fox jumps over the lazy dog. ', 10);
$compressed = lz4_compress($input);
assert(is_string($compressed));
assert(strlen($compressed) < strlen($input));

$uncompressed = lz4_uncompress($compressed);
assert(is_string($uncompressed));
assert($uncompressed === $input);
