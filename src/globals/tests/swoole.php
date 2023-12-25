<?php

declare(strict_types=1);

assert(function_exists('swoole_cpu_num'));
assert(function_exists('swoole_string'));
assert(class_exists('Swoole\Coroutine'));
assert(class_exists('Swoole\Coroutine\Http2\Client'));
assert(class_exists('Swoole\Coroutine\Redis'));
assert(class_exists('Swoole\Coroutine\WaitGroup'));
assert(class_exists('Swoole\Http2\Request'));
assert(constant('SWOOLE_VERSION'));

$command = 'ls -1 ' . __DIR__;
$list = swoole_string(shell_exec($command))->trim()->lower()->split(PHP_EOL)
    ->remove('calendar.php')
    ->remove('zlib.php')->toArray();
assert(in_array('swoole.phpt', $list));
