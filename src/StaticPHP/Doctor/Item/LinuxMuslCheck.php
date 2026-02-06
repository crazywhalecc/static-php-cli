<?php

declare(strict_types=1);

namespace StaticPHP\Doctor\Item;

use StaticPHP\Artifact\ArtifactCache;
use StaticPHP\Artifact\ArtifactDownloader;
use StaticPHP\Artifact\ArtifactExtractor;
use StaticPHP\Attribute\Doctor\CheckItem;
use StaticPHP\Attribute\Doctor\FixItem;
use StaticPHP\Attribute\Doctor\OptionalCheck;
use StaticPHP\DI\ApplicationContext;
use StaticPHP\Doctor\CheckResult;
use StaticPHP\Runtime\Shell\Shell;
use StaticPHP\Toolchain\Interface\ToolchainInterface;
use StaticPHP\Toolchain\MuslToolchain;
use StaticPHP\Toolchain\ZigToolchain;
use StaticPHP\Util\FileSystem;
use StaticPHP\Util\InteractiveTerm;
use StaticPHP\Util\SourcePatcher;
use StaticPHP\Util\System\LinuxUtil;

#[OptionalCheck([self::class, 'optionalCheck'])]
class LinuxMuslCheck
{
    public static function optionalCheck(): bool
    {
        $toolchain = ApplicationContext::get(ToolchainInterface::class);
        return $toolchain instanceof MuslToolchain || $toolchain instanceof ZigToolchain && !LinuxUtil::isMuslDist() && !str_contains(getenv('SPC_TARGET') ?: '', 'gnu');
    }

    /** @noinspection PhpUnused */
    #[CheckItem('if musl-wrapper is installed', limit_os: 'Linux', level: 800)]
    public function checkMusl(): ?CheckResult
    {
        $musl_wrapper_lib = sprintf('/lib/ld-musl-%s.so.1', php_uname('m'));
        if (file_exists($musl_wrapper_lib) && (file_exists('/usr/local/musl/lib/libc.a') || getenv('SPC_TOOLCHAIN') === ZigToolchain::class)) {
            return null;
        }
        return CheckResult::fail('musl-wrapper is not installed on your system', 'fix-musl-wrapper');
    }

    #[CheckItem('if musl-cross-make is installed', limit_os: 'Linux', level: 799)]
    public function checkMuslCrossMake(): ?CheckResult
    {
        if (getenv('SPC_TOOLCHAIN') === ZigToolchain::class && !LinuxUtil::isMuslDist()) {
            return null;
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
        $downloader = new ArtifactDownloader();
        $downloader->add('musl-wrapper')->download(false);
        $extractor = new ArtifactExtractor(ApplicationContext::get(ArtifactCache::class));
        $extractor->extract('musl-wrapper');

        // Apply CVE-2025-26519 patch and install musl wrapper
        SourcePatcher::patchFile('musl-1.2.5_CVE-2025-26519_0001.patch', SOURCE_PATH . '/musl-wrapper');
        SourcePatcher::patchFile('musl-1.2.5_CVE-2025-26519_0002.patch', SOURCE_PATH . '/musl-wrapper');

        $prefix = '';
        if (get_current_user() !== 'root') {
            $prefix = 'sudo ';
            logger()->warning('Current user is not root, using sudo for running command');
        }
        shell()->cd(SOURCE_PATH . '/musl-wrapper')
            ->exec('CC=gcc CXX=g++ AR=ar LD=ld ./configure --disable-gcc-wrapper')
            ->exec('CC=gcc CXX=g++ AR=ar LD=ld make -j')
            ->exec("CC=gcc CXX=g++ AR=ar LD=ld {$prefix}make install");
        return true;
    }

    #[FixItem('fix-musl-cross-make')]
    public function fixMuslCrossMake(): bool
    {
        // sudo
        $prefix = '';
        if (get_current_user() !== 'root') {
            $prefix = 'sudo ';
            logger()->warning('Current user is not root, using sudo for running command');
        }
        Shell::passthruCallback(function () {
            InteractiveTerm::advance();
        });
        $downloader = new ArtifactDownloader();
        $extractor = new ArtifactExtractor(ApplicationContext::get(ArtifactCache::class));
        $downloader->add('musl-toolchain')->download(false);
        $extractor->extract('musl-toolchain');
        $pkg_root = PKG_ROOT_PATH . '/musl-toolchain';
        shell()->exec("{$prefix}cp -rf {$pkg_root}/* /usr/local/musl");
        FileSystem::removeDir($pkg_root);
        return true;
    }
}
