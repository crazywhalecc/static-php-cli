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
        $release = PHP_OS_FAMILY === 'Linux' ? LinuxUtil::getOSRelease() : null;
        $distro = $release !== null ? (' ' . $release['dist']) : '';
        $known_distro = $release === null
            || in_array($release['dist'], LinuxUtil::getSupportedDistros())
            || count(array_intersect(explode(' ', $release['family']), LinuxUtil::getSupportedDistros())) > 0;
        return CheckResult::ok(PHP_OS_FAMILY . ' ' . php_uname('m') . $distro . ', supported' . ($known_distro ? '' : ' (but not tested on this distro)'));
    }
}
