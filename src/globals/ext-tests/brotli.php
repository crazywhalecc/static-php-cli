<?php

declare(strict_types=1);

$str = 'brotli_compress ( string $data, int $level = BROTLI_COMPRESS_LEVEL_DEFAULT, int $mode = BROTLI_GENERIC, string|null $dict = null ): string|false';
assert(function_exists('brotli_compress'));
assert(function_exists('brotli_uncompress'));
assert(brotli_uncompress(brotli_compress($str)) === $str);
