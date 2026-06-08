<?php

declare(strict_types=1);

assert(class_exists('Decimal\Decimal'));
assert(method_exists('Decimal\Decimal', 'valueOf'));
assert(0.1 + 0.2 !== 0.3);
$result = Decimal\Decimal::valueOf('0.1') + Decimal\Decimal::valueOf('0.2');
$expected = Decimal\Decimal::valueOf('0.3');
assert($result == $expected);
