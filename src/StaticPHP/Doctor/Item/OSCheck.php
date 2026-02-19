<?php

declare(strict_types=1);

namespace StaticPHP\Doctor\Item;

use StaticPHP\Attribute\Doctor\CheckItem;
use StaticPHP\Doctor\CheckResult;
use StaticPHP\Util\System\LinuxUtil;

class OSCheck
{
    #[CheckItem('if current OS is supported', level: 1000)]
    public function checkOS(): ?CheckResult
    {
        if (!in_array(PHP_OS_FAMILY, ['Darwin', 'Linux', 'Windows'])) {
            return CheckResult::fail('Current OS is not supported: ' . PHP_OS_FAMILY);
        }
        $distro = PHP_OS_FAMILY === 'Linux' ? (' ' . LinuxUtil::getOSRelease()['dist']) : '';
        $known_distro = PHP_OS_FAMILY !== 'Linux' || in_array(LinuxUtil::getOSRelease()['dist'], LinuxUtil::getSupportedDistros());
        return CheckResult::ok(PHP_OS_FAMILY . ' ' . php_uname('m') . $distro . ', supported' . ($known_distro ? '' : ' (but not tested on this distro)'));
    }
}
