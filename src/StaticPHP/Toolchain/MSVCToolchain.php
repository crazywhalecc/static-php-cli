<?php

declare(strict_types=1);

namespace StaticPHP\Toolchain;

use StaticPHP\Exception\EnvironmentException;
use StaticPHP\Toolchain\Interface\ToolchainInterface;
use StaticPHP\Util\GlobalEnvManager;
use StaticPHP\Util\System\WindowsUtil;

class MSVCToolchain implements ToolchainInterface
{
    public function initEnv(): void
    {
        GlobalEnvManager::addPathIfNotExists(PKG_ROOT_PATH . '\bin');
        // msys2-build-essentials: add MSYS2 usr\bin to PATH so that 7za.exe, make, autoconf, etc. are available.
        // This must be done here because msys2-build-essentials is not a dependency of any library package,
        // so its path@windows entries are not automatically applied by the package installer at runtime.
        $msys2_path = getenv('SPC_MSYS2_PATH') ?: (PKG_ROOT_PATH . '\msys2-build-essentials\msys64');
        if (is_dir($msys2_path)) {
            GlobalEnvManager::putenv("SPC_MSYS2_PATH={$msys2_path}");
            GlobalEnvManager::addPathIfNotExists($msys2_path . '\usr\bin');
            GlobalEnvManager::addPathIfNotExists("{$msys2_path}\\usr\\lib\\p7zip");
        }
        // strawberry-perl
        if (is_dir(PKG_ROOT_PATH . '\strawberry-perl')) {
            GlobalEnvManager::addPathIfNotExists(PKG_ROOT_PATH . '\strawberry-perl\perl\bin');
        }
    }

    public function afterInit(): void
    {
        $count = count(getenv());
        $vs = WindowsUtil::findVisualStudio();
        if ($vs === false || !file_exists($vcvarsall = "{$vs['dir']}\\VC\\Auxiliary\\Build\\vcvarsall.bat")) {
            throw new EnvironmentException(
                'Visual Studio with C++ tools not found',
                'Please install Visual Studio with C++ tools'
            );
        }
        if (getenv('VCINSTALLDIR') === false) {
            if (file_exists(DOWNLOAD_PATH . '/.vcenv-cache') && (time() - filemtime(DOWNLOAD_PATH . '/.vcenv-cache')) < 3600) {
                $output = file(DOWNLOAD_PATH . '/.vcenv-cache', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            } else {
                exec('call "' . $vcvarsall . '" x64 > NUL && set', $output);
                file_put_contents(DOWNLOAD_PATH . '/.vcenv-cache', implode("\n", $output));
            }
            array_map(fn ($x) => putenv($x), $output);
        }
        $after = count(getenv());
        if ($after > $count) {
            logger()->debug('Applied ' . ($after - $count) . ' environment variables from Visual Studio setup');
        }
    }

    public function getCompilerInfo(): ?string
    {
        if ($vcver = getenv('VisualStudioVersion')) {
            return "Visual Studio {$vcver}";
        }
        return null;
    }

    public function isStatic(): bool
    {
        return false;
    }
}
