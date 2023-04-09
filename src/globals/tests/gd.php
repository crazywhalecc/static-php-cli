<?php

declare(strict_types=1);

$info = gd_info();
$true = $info['JPEG Support'] ?? true; // JPEG support needs libjpeg library, and I will add it later. TODO
$true = $true ? ($info['PNG Support'] ?? false) : false;
exit($true ? 0 : 1);
