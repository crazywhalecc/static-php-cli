<?php

declare(strict_types=1);

assert(function_exists('gzcompress'));
assert(gzdecode(gzencode('aaa')) === 'aaa');
