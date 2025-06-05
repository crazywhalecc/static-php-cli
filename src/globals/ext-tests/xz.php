<?php

declare(strict_types=1);

$str = 'Data you would like compressed.';
assert(function_exists('xzencode'));
assert(function_exists('xzdecode'));
assert(xzdecode(xzencode($str)) === $str);
