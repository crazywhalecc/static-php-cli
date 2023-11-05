<?php

declare(strict_types=1);

namespace SPC\doctor\item;

use SPC\builder\linux\SystemUtil;
use SPC\builder\traits\UnixSystemUtilTrait;
use SPC\doctor\AsCheckItem;
use SPC\doctor\CheckResult;

class OSCheckList
{
    use UnixSystemUtilTrait;

    #[AsCheckItem('if current OS are supported', level: 999)]
    public function checkOS(): ?CheckResult
    {
        if (!in_array(PHP_OS_FAMILY, ['Darwin', 'Linux', 'BSD'])) {
            return CheckResult::fail('Current OS is not supported');
        }
        $distro = PHP_OS_FAMILY === 'Linux' ? (' ' . SystemUtil::getOSRelease()['dist']) : '';
        return CheckResult::ok(PHP_OS_FAMILY . ' ' . php_uname('m') . $distro . ', supported');
    }
}
