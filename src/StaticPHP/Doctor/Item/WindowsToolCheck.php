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

    #[CheckItem('if msys2-build-essentials is installed', limit_os: 'Windows', level: 996)]
    public function checkMsys2(): ?CheckResult
    {
        $marker = PKG_ROOT_PATH . '\msys2-build-essentials\.spc-msys2-initialized';
        if (!file_exists($marker)) {
            return CheckResult::fail('msys2-build-essentials not installed', 'install-msys2-build-essentials');
        }
        return CheckResult::ok(PKG_ROOT_PATH . '\msys2-build-essentials\msys64');
    }

    #[CheckItem('if 7za.exe is installed', limit_os: 'Windows', level: 999)]
    public function check7zaWin(): ?CheckResult
    {
        $path = FileSystem::convertPath(PKG_ROOT_PATH . '\bin\7za.exe');
        if (!file_exists($path)) {
            return CheckResult::fail('7za.exe not found', 'install-7za-win');
        }
        return CheckResult::ok($path);
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
        $installer->run(true);
        GlobalEnvManager::addPathIfNotExists(PKG_ROOT_PATH . '\strawberry-perl');
        return true;
    }

    #[FixItem('install-msys2-build-essentials')]
    public function installMsys2(): bool
    {
        $installer = new PackageInstaller(interactive: false);
        $installer->addInstallPackage('msys2-build-essentials');
        $installer->run(true);
        return true;
    }

    #[FixItem('install-7za-win')]
    public function install7zaWin(): bool
    {
        $installer = new PackageInstaller(interactive: false);
        $installer->addInstallPackage('7za-win');
        $installer->run(true);
        return true;
    }

    #[FixItem('install-nasm')]
    public function installNasm(): bool
    {
        $installer = new PackageInstaller(interactive: false);
        $installer->addInstallPackage('nasm');
        $installer->run(true);
        return true;
    }

    #[FixItem('install-vswhere')]
    public function installVSWhere(): bool
    {
        $installer = new PackageInstaller(interactive: false);
        $installer->addInstallPackage('vswhere');
        $installer->run(true);
        return true;
    }
}
