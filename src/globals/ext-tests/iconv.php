<?php

declare(strict_types=1);

assert(function_exists('iconv'));
assert(iconv('UTF-8', 'CP437', 'foo') === 'foo');
