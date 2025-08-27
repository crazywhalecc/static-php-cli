<?php

declare(strict_types=1);

namespace SPC\doctor\item;

use SPC\doctor\AsCheckItem;
use SPC\doctor\AsFixItem;
use SPC\doctor\CheckResult;
use SPC\doctor\OptionalCheck;
use SPC\store\PackageManager;
use SPC\store\pkg\PkgConfig;

#[OptionalCheck([self::class, 'optionalCheck'])]
class PkgConfigCheck
{
    public static function optionalCheck(): bool
    {
        return PHP_OS_FAMILY !== 'Windows';
    }

    /** @noinspection PhpUnused */
    #[AsCheckItem('if pkg-config is installed', level: 800)]
    public function checkPkgConfig(): CheckResult
    {
        if (PkgConfig::isInstalled()) {
            return CheckResult::ok();
        }
        return CheckResult::fail('pkg-config is not installed', 'install-pkgconfig');
    }

    #[AsFixItem('install-pkgconfig')]
    public function installPkgConfig(): bool
    {
        $arch = arch2gnu(php_uname('m'));
        $os = match (PHP_OS_FAMILY) {
            'Windows' => 'win',
            'Darwin' => 'macos',
            'BSD' => 'freebsd',
            default => 'linux',
        };
        PackageManager::installPackage("pkg-config-{$arch}-{$os}");
        return PkgConfig::isInstalled();
    }
}
