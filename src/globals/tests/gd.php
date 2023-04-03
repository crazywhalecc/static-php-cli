<?php

declare(strict_types=1);

$info = gd_info();
$true = $info['JPEG Support'] ?? false;
$true = $true ? ($info['PNG Support'] ?? false) : false;
exit($true ? 0 : 1);
