<?php

declare(strict_types=1);

namespace Package\Extension;

use Package\Target\php;
use StaticPHP\Attribute\Package\BeforeStage;
use StaticPHP\Attribute\Package\Extension;
use StaticPHP\DI\ApplicationContext;
use StaticPHP\Package\PackageInstaller;
use StaticPHP\Package\PhpExtensionPackage;
use StaticPHP\Toolchain\Interface\ToolchainInterface;
use StaticPHP\Toolchain\ZigToolchain;
use StaticPHP\Util\FileSystem;
use StaticPHP\Util\GlobalEnvManager;

#[Extension('simdjson')]
class simdjson extends PhpExtensionPackage
{
    #[BeforeStage('php', [php::class, 'buildconfForUnix'], 'ext-simdjson')]
    #[BeforeStage('php', [php::class, 'buildconfForWindows'], 'ext-simdjson')]
    public function patchBeforeBuildconf(PackageInstaller $installer): bool
    {
        $php = $installer->getTargetPackage('php');
        $php_ver = php::getPHPVersionID();
        FileSystem::replaceFileRegex(
            "{$this->getSourceDir()}/config.m4",
            '/php_version=(`.*`)$/m',
            "php_version={$php_ver}"
        );
        FileSystem::replaceFileStr(
            "{$this->getSourceDir()}/config.m4",
            'if test -z "$PHP_CONFIG"; then',
            'if false; then'
        );
        FileSystem::replaceFileStr(
            "{$this->getSourceDir()}/config.w32",
            "'yes',",
            'PHP_SIMDJSON_SHARED,'
        );
        return true;
    }

    public function getSharedExtensionEnv(): array
    {
        $env = parent::getSharedExtensionEnv();
        if (ApplicationContext::get(ToolchainInterface::class) instanceof ZigToolchain) {
            $extra = getenv('SPC_COMPILER_EXTRA');
            if (!str_contains((string) $extra, '-lstdc++')) {
                f_putenv('SPC_COMPILER_EXTRA=' . clean_spaces($extra . ' -lstdc++'));
            }
            $env['CFLAGS'] .= ' -Xclang -target-feature -Xclang +evex512';
            $env['CXXFLAGS'] .= ' -Xclang -target-feature -Xclang +evex512';
        }
        return $env;
    }

    #[BeforeStage('php', [php::class, 'makeForUnix'], 'ext-simdjson')]
    public function patchBeforeMake(): void
    {
        if (!ApplicationContext::get(ToolchainInterface::class) instanceof ZigToolchain) {
            return;
        }
        $extra_cflags = getenv('SPC_CMD_VAR_PHP_MAKE_EXTRA_CFLAGS') ?: '';
        GlobalEnvManager::putenv('SPC_CMD_VAR_PHP_MAKE_EXTRA_CFLAGS=' . trim($extra_cflags . ' -Xclang -target-feature -Xclang +evex512'));
        $extra_cxxflags = getenv('SPC_CMD_VAR_PHP_MAKE_EXTRA_CXXFLAGS') ?: '';
        GlobalEnvManager::putenv('SPC_CMD_VAR_PHP_MAKE_EXTRA_CXXFLAGS=' . trim($extra_cxxflags . ' -Xclang -target-feature -Xclang +evex512'));
    }
}
