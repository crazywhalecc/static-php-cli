<?php

declare(strict_types=1);

namespace SPC\doctor\item;

use SPC\doctor\AsCheckItem;
use SPC\doctor\AsFixItem;
use SPC\doctor\CheckResult;
use SPC\doctor\OptionalCheck;
use SPC\store\PackageManager;
use SPC\util\PkgConfigUtil;

#[OptionalCheck([self::class, 'optionalCheck'])]
class PkgConfigCheck
{
    public static function optionalCheck(): bool
    {
        return PHP_OS_FAMILY !== 'Windows';
    }

    /** @noinspection PhpUnused */
    #[AsCheckItem('if pkg-config is installed or built', level: 800)]
    public function checkPkgConfig(): CheckResult
    {
        if (!($pkgconf = PkgConfigUtil::findPkgConfig())) {
            return CheckResult::fail('pkg-config is not installed', 'install-pkgconfig');
        }
        return CheckResult::ok($pkgconf);
    }

    #[AsCheckItem('if pkg-config is working', level: 799)]
    public function checkPkgConfigFunctional(): CheckResult
    {
        $pkgconf = PkgConfigUtil::findPkgConfig();
        // check if pkg-config is functional
        [$ret, $output] = shell()->execWithResult("{$pkgconf} --version", false);
        if ($ret === 0) {
            return CheckResult::ok(implode(' ', $output));
        }
        return CheckResult::fail('pkg-config is not functional', 'install-pkgconfig');
    }

    #[AsFixItem('install-pkgconfig')]
    public function installPkgConfig(): bool
    {
        PackageManager::installPackage('pkg-config');
        return true;
    }
}
