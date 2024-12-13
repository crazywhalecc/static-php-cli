<?php

declare(strict_types=1);

assert(function_exists('swoole_cpu_num'));
assert(function_exists('swoole_string'));
assert(class_exists('Swoole\Coroutine'));
assert(class_exists('Swoole\Coroutine\Http2\Client'));
assert(class_exists('Swoole\Coroutine\WaitGroup'));
assert(class_exists('Swoole\Http2\Request'));
assert(constant('SWOOLE_VERSION'));
