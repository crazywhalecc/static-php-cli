<?php

declare(strict_types=1);

$str = 'This is bz2 extension test';
exit(bzdecompress(bzcompress($str, 9)) === $str ? 0 : 1);
