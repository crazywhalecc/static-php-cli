<?php

declare(strict_types=1);

namespace StaticPHP\Doctor\Item;

use StaticPHP\Attribute\Doctor\CheckItem;
use StaticPHP\Attribute\Doctor\FixItem;
use StaticPHP\Attribute\Doctor\OptionalCheck;
use StaticPHP\Doctor\CheckResult;
use StaticPHP\Toolchain\MuslToolchain;
use StaticPHP\Toolchain\ZigToolchain;
use StaticPHP\Util\FileSystem;
use StaticPHP\Util\System\LinuxUtil;

#[OptionalCheck([self::class, 'optionalCheck'])]
class LinuxMuslCheck
{
    public static function optionalCheck(): bool
    {
        return getenv('SPC_TOOLCHAIN') === MuslToolchain::class ||
            (getenv('SPC_TOOLCHAIN') === ZigToolchain::class && !LinuxUtil::isMuslDist());
    }

    /** @noinspection PhpUnused */
    #[CheckItem('if musl-wrapper is installed', limit_os: 'Linux', level: 800)]
    public function checkMusl(): CheckResult
    {
        $musl_wrapper_lib = sprintf('/lib/ld-musl-%s.so.1', php_uname('m'));
        if (file_exists($musl_wrapper_lib) && (file_exists('/usr/local/musl/lib/libc.a') || getenv('SPC_TOOLCHAIN') === ZigToolchain::class)) {
            return CheckResult::ok();
        }
        return CheckResult::fail('musl-wrapper is not installed on your system', 'fix-musl-wrapper');
    }

    #[CheckItem('if musl-cross-make is installed', limit_os: 'Linux', level: 799)]
    public function checkMuslCrossMake(): CheckResult
    {
        if (getenv('SPC_TOOLCHAIN') === ZigToolchain::class && !LinuxUtil::isMuslDist()) {
            return CheckResult::ok();
        }
        $arch = arch2gnu(php_uname('m'));
        $cross_compile_lib = "/usr/local/musl/{$arch}-linux-musl/lib/libc.a";
        $cross_compile_gcc = "/usr/local/musl/bin/{$arch}-linux-musl-gcc";
        if (file_exists($cross_compile_lib) && file_exists($cross_compile_gcc)) {
            return CheckResult::ok();
        }
        return CheckResult::fail('musl-cross-make is not installed on your system', 'fix-musl-cross-make');
    }

    #[FixItem('fix-musl-wrapper')]
    public function fixMusl(): bool
    {
        // TODO: implement musl-wrapper installation
        // This should:
        // 1. Download musl source using Downloader::downloadSource()
        // 2. Extract the source using FileSystem::extractSource()
        // 3. Apply CVE patches using SourcePatcher::patchFile()
        // 4. Build and install musl wrapper
        // 5. Add path using putenv instead of editing /etc/profile
        return false;
    }

    #[FixItem('fix-musl-cross-make')]
    public function fixMuslCrossMake(): bool
    {
        // TODO: implement musl-cross-make installation
        // This should:
        // 1. Install musl-toolchain package using PackageManager::installPackage()
        // 2. Copy toolchain files to /usr/local/musl
        return false;
    }
}
