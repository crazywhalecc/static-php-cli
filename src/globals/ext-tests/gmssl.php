<?php

declare(strict_types=1);

assert(function_exists('gmssl_rand_bytes'));
assert(function_exists('gmssl_sm3'));

assert(bin2hex(gmssl_sm3('123456')) === '207cf410532f92a47dee245ce9b11ff71f578ebd763eb3bbea44ebd043d018fb');
