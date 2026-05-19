<?php

declare(strict_types=1);

namespace StaticPHP\Doctor\Item;

use Package\Artifact\llvm_compiler_rt;
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
class LlvmCompilerRtCheck
{
    public static function optionalCheck(): bool
    {
        return ApplicationContext::get(ToolchainInterface::class) instanceof ZigToolchain;
    }

    /** @noinspection PhpUnused */
    #[CheckItem('if llvm-compiler-rt is built for current target', level: 799)]
    public function checkLlvmCompilerRt(): CheckResult
    {
        $libDir = PKG_ROOT_PATH . '/zig/lib/' . SystemTarget::getCanonicalTriple();
        if (new llvm_compiler_rt()->isBuilt($libDir)) {
            return CheckResult::ok($libDir);
        }
        return CheckResult::fail('llvm-compiler-rt is not built for ' . SystemTarget::getCanonicalTriple(), 'build-llvm-compiler-rt');
    }

    #[FixItem('build-llvm-compiler-rt')]
    public function fixLlvmCompilerRt(): bool
    {
        $installer = new PackageInstaller(interactive: false);
        $installer->addInstallPackage('llvm-compiler-rt');
        $installer->run(true);
        new llvm_compiler_rt()->buildForTriple();
        $libDir = PKG_ROOT_PATH . '/zig/lib/' . SystemTarget::getCanonicalTriple();
        return new llvm_compiler_rt()->isBuilt($libDir);
    }
}
