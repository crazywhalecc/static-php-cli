<?php

declare(strict_types=1);

namespace Package\Extension;

use Package\Target\php;
use StaticPHP\Attribute\Package\BeforeStage;
use StaticPHP\Attribute\Package\CustomPhpConfigureArg;
use StaticPHP\Attribute\Package\Extension;
use StaticPHP\Attribute\PatchDescription;
use StaticPHP\Package\PackageBuilder;
use StaticPHP\Package\PhpExtensionPackage;
use StaticPHP\Util\FileSystem;

#[Extension('imagick')]
class imagick extends PhpExtensionPackage
{
    #[CustomPhpConfigureArg('Darwin')]
    #[CustomPhpConfigureArg('Linux')]
    public function getUnixConfigureArg(bool $shared, PackageBuilder $builder): string
    {
        $disable_omp = ' ac_cv_func_omp_pause_resource_all=no';
        return '--with-imagick=' . ($shared ? 'shared,' : '') . $builder->getBuildRootPath() . $disable_omp;
    }

    #[CustomPhpConfigureArg('Windows')]
    public function getWindowsConfigureArg(bool $shared): string
    {
        // config.w32 uses PHP_IMAGICK as an extra search path for CORE_RL_*.lib; the static
        // ImageMagick libs are installed flat in buildroot/lib (headers in buildroot/include/imagemagick).
        return '--with-imagick=' . BUILD_LIB_PATH;
    }

    #[BeforeStage('php', [php::class, 'buildconfForWindows'], 'ext-imagick')]
    #[PatchDescription('Add the Win32 system libraries the static ImageMagick stack needs')]
    public function patchConfigW32ForWindows(): void
    {
        $config = $this->getSourceDir() . '/config.w32';

        // Idempotency guard (the source dir may be patched in place and reused across builds).
        if (str_contains(FileSystem::readFile($config), 'LIBS_IMAGICK')) {
            return;
        }

        // The static ImageMagick stack needs several Win32 system libraries (GDI/GDI+, WIC, urlmon, ...)
        // that aren't already pulled in by the other extensions. (imagick itself builds as plain C:
        // ImageMagick is built with a 32-bit channel mask, see imagemagick.php buildWin, so the
        // MagickCore headers don't require a C++ translation unit.)
        FileSystem::replaceFileStr(
            $config,
            "AC_DEFINE('HAVE_IMAGICK', 1);",
            'ADD_FLAG("LIBS_IMAGICK", "gdi32.lib gdiplus.lib urlmon.lib msimg32.lib oleaut32.lib windowscodecs.lib iphlpapi.lib");' . "\n\t\t" .
            "AC_DEFINE('HAVE_IMAGICK', 1);"
        );
    }
}
