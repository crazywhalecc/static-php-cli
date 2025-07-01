<?php

declare(strict_types=1);

namespace SPC\doctor\item;

use SPC\doctor\AsCheckItem;
use SPC\doctor\AsFixItem;
use SPC\doctor\CheckResult;
use SPC\doctor\OptionalCheck;
use SPC\exception\FileSystemException;
use SPC\exception\WrongUsageException;
use SPC\store\PackageManager;
use SPC\store\pkg\Zig;
use SPC\toolchain\ZigToolchain;

#[OptionalCheck([self::class, 'optionalCheck'])]
class ZigCheck
{
    public static function optionalCheck(): bool
    {
        return getenv('SPC_TOOLCHAIN') === ZigToolchain::class;
    }

    /** @noinspection PhpUnused */
    #[AsCheckItem('if zig is installed', level: 800)]
    public function checkZig(): CheckResult
    {
        if (Zig::isInstalled()) {
            return CheckResult::ok();
        }
        return CheckResult::fail('zig is not installed', 'install-zig');
    }

    /** @noinspection PhpUnused */
    /**
     * @throws FileSystemException
     * @throws WrongUsageException
     */
    #[AsFixItem('install-zig')]
    public function fixMuslCrossMake(): bool
    {
        $arch = arch2gnu(php_uname('m'));
        $os = match (PHP_OS_FAMILY) {
            'Windows' => 'win',
            'Darwin' => 'macos',
            'BSD' => 'freebsd',
            default => 'linux',
        };
        PackageManager::installPackage("zig-{$arch}-{$os}");
        return Zig::isInstalled();
    }
}
