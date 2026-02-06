<?php

declare(strict_types=1);

namespace StaticPHP\Doctor\Item;

use StaticPHP\Attribute\Doctor\CheckItem;
use StaticPHP\Attribute\Doctor\FixItem;
use StaticPHP\Attribute\Doctor\OptionalCheck;
use StaticPHP\Doctor\CheckResult;
use StaticPHP\Package\PackageInstaller;
use StaticPHP\Runtime\SystemTarget;
use StaticPHP\Toolchain\ToolchainManager;
use StaticPHP\Util\FileSystem;
use StaticPHP\Util\GlobalEnvManager;
use StaticPHP\Util\System\WindowsUtil;

#[OptionalCheck([self::class, 'optional'])]
class WindowsToolCheck
{
    public static function optional(): bool
    {
        return SystemTarget::getTargetOS() === 'Windows';
    }

    #[CheckItem('if vswhere is installed', level: 999)]
    public function findVSWhere(): ?CheckResult
    {
        $installer = new PackageInstaller();
        $installer->addInstallPackage('vswhere');
        $is_installed = $installer->isPackageInstalled('vswhere');
        if ($is_installed) {
            return CheckResult::ok();
        }
        return CheckResult::fail('vswhere is not installed', 'install-vswhere');
    }

    #[CheckItem('if Visual Studio is installed', level: 998)]
    public function findVS(): ?CheckResult
    {
        $a = WindowsUtil::findVisualStudio();
        if ($a !== false) {
            return CheckResult::ok("{$a['version']} at {$a['dir']}");
        }
        return CheckResult::fail('Visual Studio with C++ tools is not installed. Please install Visual Studio with C++ tools.');
    }

    #[CheckItem('if git associated command exists', level: 997)]
    public function checkGitPatch(): ?CheckResult
    {
        if (WindowsUtil::findCommand('patch.exe') === null) {
            return CheckResult::fail('Git patch (minGW command) not found in path. You need to add "C:\Program Files\Git\usr\bin" in Path.');
        }
        return CheckResult::ok();
    }

    #[CheckItem('if php-sdk-binary-tools are downloaded', limit_os: 'Windows', level: 996)]
    public function checkSDK(): ?CheckResult
    {
        if (!file_exists(getenv('PHP_SDK_PATH') . DIRECTORY_SEPARATOR . 'phpsdk-starter.bat')) {
            return CheckResult::fail('php-sdk-binary-tools not downloaded', 'install-php-sdk');
        }
        return CheckResult::ok(getenv('PHP_SDK_PATH'));
    }

    #[CheckItem('if nasm installed', level: 995)]
    public function checkNasm(): ?CheckResult
    {
        if (($a = WindowsUtil::findCommand('nasm.exe')) === null) {
            return CheckResult::fail('nasm.exe not found in path.', 'install-nasm');
        }
        return CheckResult::ok($a);
    }

    #[CheckItem('if perl(strawberry) installed', limit_os: 'Windows', level: 994)]
    public function checkPerl(): ?CheckResult
    {
        if (($path = WindowsUtil::findCommand('perl.exe')) === null) {
            return CheckResult::fail('perl not found in path.', 'install-perl');
        }
        if (!str_contains(implode('', cmd()->execWithResult(quote($path) . ' -v', false)[1]), 'MSWin32')) {
            return CheckResult::fail($path . ' is not built for msvc.', 'install-perl');
        }
        return CheckResult::ok($path);
    }

    #[CheckItem('if environment is properly set up', level: 1)]
    public function checkenv(): ?CheckResult
    {
        // manually trigger after init
        try {
            ToolchainManager::afterInitToolchain();
        } catch (\Exception $e) {
            return CheckResult::fail('Environment setup failed: ' . $e->getMessage());
        }
        $required_cmd = ['cl.exe', 'link.exe', 'lib.exe', 'dumpbin.exe', 'msbuild.exe', 'nmake.exe'];
        foreach ($required_cmd as $cmd) {
            if (WindowsUtil::findCommand($cmd) === null) {
                return CheckResult::fail("{$cmd} not found in path. Please make sure Visual Studio with C++ tools is properly installed.");
            }
        }
        return CheckResult::ok();
    }

    #[FixItem('install-perl')]
    public function installPerl(): bool
    {
        $installer = new PackageInstaller();
        $installer->addInstallPackage('strawberry-perl');
        $installer->run(false);
        GlobalEnvManager::addPathIfNotExists(PKG_ROOT_PATH . '\strawberry-perl');
        return true;
    }

    #[FixItem('install-php-sdk')]
    public function installSDK(): bool
    {
        FileSystem::removeDir(getenv('PHP_SDK_PATH'));
        $installer = new PackageInstaller();
        $installer->addInstallPackage('php-sdk-binary-tools');
        $installer->run(false);
        return true;
    }

    #[FixItem('install-nasm')]
    public function installNasm(): bool
    {
        $installer = new PackageInstaller();
        $installer->addInstallPackage('nasm');
        $installer->run(false);
        return true;
    }

    #[FixItem('install-vswhere')]
    public function installVSWhere(): bool
    {
        $installer = new PackageInstaller();
        $installer->addInstallPackage('vswhere');
        $installer->run(false);
        return true;
    }
}
