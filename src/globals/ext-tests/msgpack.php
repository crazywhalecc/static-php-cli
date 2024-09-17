<?php

declare(strict_types=1);

assert(function_exists('msgpack_pack'));
assert(function_exists('msgpack_unpack'));
assert(msgpack_unpack(msgpack_pack(['foo', 'bar'])) === ['foo', 'bar']);
