<?php

declare(strict_types=1);

assert(function_exists('gd_info'));
$info = gd_info();
assert($info['PNG Support'] ?? false);
assert($info['GIF Create Support'] ?? false);
assert($info['GIF Read Support'] ?? false);
