<?php

declare(strict_types=1);

$str = 'This is bz2 extension test';
assert(function_exists('bzdecompress'));
assert(function_exists('bzcompress'));
assert(bzdecompress(bzcompress($str, 9)) === $str);
