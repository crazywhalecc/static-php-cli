<?php

declare(strict_types=1);

$info = gd_info();
$true = ($true ?? true) && ($info['PNG Support'] ?? false);
exit($true ? 0 : 1);
