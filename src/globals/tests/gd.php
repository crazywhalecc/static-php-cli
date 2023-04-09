<?php

declare(strict_types=1);

$info = gd_info();
// jpeg will be supported later
$true = true; // $info['JPEG Support'] ?? false;
$true = $true ? ($info['PNG Support'] ?? false) : false;
exit($true ? 0 : 1);
