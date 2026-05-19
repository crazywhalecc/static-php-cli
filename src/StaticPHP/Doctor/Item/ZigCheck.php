<?php

declare(strict_types=1);

namespace StaticPHP\Doctor\Item;

use StaticPHP\Attribute\Doctor\CheckItem;
use StaticPHP\Attribute\Doctor\FixItem;
use StaticPHP\Attribute\Doctor\OptionalCheck;
use StaticPHP\DI\ApplicationContext;
use StaticPHP\Doctor\CheckResult;
use StaticPHP\Package\PackageInstaller;
use StaticPHP\Runtime\SystemTarget;
use StaticPHP\Toolchain\Interface\ToolchainInterface;
use StaticPHP\Toolchain\ZigToolchain;

#[OptionalCheck([self::class, 'optionalCheck'])]
class ZigCheck
{
    public static function optionalCheck(): bool
    {
        return ApplicationContext::get(ToolchainInterface::class) instanceof ZigToolchain;
    }

    /** @noinspection PhpUnused */
    #[CheckItem('if zig is installed', level: 800)]
    public function checkZig(): CheckResult
    {
        if (new PackageInstaller()->addInstallPackage('zig')->isPackageInstalled('zig')) {
            return CheckResult::ok(PKG_ROOT_PATH . '/zig/zig');
        }
        return CheckResult::fail('zig is not installed', 'install-zig');
    }

    #[FixItem('install-zig')]
    public function installZig(): bool
    {
        $installer = new PackageInstaller(interactive: false);
        $installer->addInstallPackage('zig');
        $installer->run(true);
        return $installer->isPackageInstalled('zig');
    }

    /** @noinspection PhpUnused */
    #[CheckItem('if llvm compiler-rt bits are built', limit_os: 'Linux', level: 799)]
    public function checkCompilerRtBits(): ?CheckResult
    {
        // Skip if zig is not installed yet (zig check runs at level 800)
        if (!new PackageInstaller()->addInstallPackage('zig')->isPackageInstalled('zig')) {
            return null;
        }
        $libDir = PKG_ROOT_PATH . '/zig/lib/' . SystemTarget::getCanonicalTriple();
        if (file_exists("{$libDir}/libclang_rt.profile.a")
            && file_exists("{$libDir}/clang_rt.crtbegin.o")
            && file_exists("{$libDir}/clang_rt.crtend.o")
        ) {
            return CheckResult::ok("{$libDir}/libclang_rt.profile.a");
        }
        return CheckResult::fail('llvm compiler-rt bits are not built for ' . SystemTarget::getCanonicalTriple(), 'build-llvm-compiler-rt');
    }

    #[FixItem('build-llvm-compiler-rt')]
    public function fixCompilerRtBits(): bool
    {
        $installer = new PackageInstaller(interactive: false);
        $installer->addInstallPackage('llvm-compiler-rt');
        $installer->run(true);
        new \Package\Artifact\llvm_compiler_rt()->buildForCurrentTarget();
        $libDir = PKG_ROOT_PATH . '/zig/lib/' . SystemTarget::getCanonicalTriple();
        return file_exists("{$libDir}/libclang_rt.profile.a")
            && file_exists("{$libDir}/clang_rt.crtbegin.o")
            && file_exists("{$libDir}/clang_rt.crtend.o");
    }
}
