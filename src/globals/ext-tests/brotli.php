<?php

declare(strict_types=1);

assert(function_exists('brotli_compress'));
assert(function_exists('brotli_uncompress'));

$input = str_repeat('The quick brown fox jumps over the lazy dog. ', 10);
$compressed = brotli_compress($input);
assert(is_string($compressed));
assert(strlen($compressed) < strlen($input));

$uncompressed = brotli_uncompress($compressed);
assert(is_string($uncompressed));
assert($uncompressed === $input);
