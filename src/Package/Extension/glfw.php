<?php

declare(strict_types=1);

namespace Package\Extension;

use Package\Target\php;
use StaticPHP\Attribute\Package\BeforeStage;
use StaticPHP\Attribute\Package\CustomPhpConfigureArg;
use StaticPHP\Attribute\Package\Extension;
use StaticPHP\Attribute\PatchDescription;
use StaticPHP\Package\PhpExtensionPackage;
use StaticPHP\Runtime\SystemTarget;
use StaticPHP\Util\FileSystem;

#[Extension('glfw')]
class glfw extends PhpExtensionPackage
{
    #[BeforeStage('php', [php::class, 'buildconfForUnix'], 'ext-glfw')]
    #[PatchDescription('Patch glfw extension before buildconf')]
    public function patchBeforeBuildconf(): void
    {
        if (!file_exists(SOURCE_PATH . '/php-src/ext/glfw')) {
            FileSystem::copyDir($this->getSourceDir(), SOURCE_PATH . '/php-src/ext/glfw');
        }
    }

    #[BeforeStage('php', [php::class, 'configureForUnix'], 'ext-glfw')]
    #[PatchDescription('Patch glfw extension before configure')]
    public function patchBeforeConfigure(): void
    {
        FileSystem::replaceFileStr(
            SOURCE_PATH . '/php-src/configure',
            '-lglfw ',
            '-lglfw3 '
        );

        // add X11 shared libs for linux
        if (SystemTarget::getTargetOS() === 'Linux') {
            $extra_libs = getenv('SPC_EXTRA_LIBS') ?: '';
            $extra_libs .= ' -lX11 -lXrandr -lXinerama -lXcursor -lXi';
            putenv('SPC_EXTRA_LIBS=' . trim($extra_libs));
            $extra_cflags = getenv('SPC_CMD_VAR_PHP_MAKE_EXTRA_CFLAGS') ?: '';

            $extra_cflags .= ' -idirafter /usr/include';
            putenv('SPC_CMD_VAR_PHP_MAKE_EXTRA_CFLAGS=' . trim($extra_cflags));
            $extra_ldflags = getenv('SPC_CMD_VAR_PHP_MAKE_EXTRA_LDFLAGS') ?: '';
            $extra_ldflags .= ' -L/usr/lib/' . SystemTarget::getTargetArch() . '-linux-gnu ';
            putenv('SPC_CMD_VAR_PHP_MAKE_EXTRA_LDFLAGS=' . $extra_ldflags);
        }
    }

    #[CustomPhpConfigureArg('Darwin')]
    #[CustomPhpConfigureArg('Linux')]
    public function getUnixConfigureArg(bool $shared = false): string
    {
        return '--enable-glfw --with-glfw-dir=' . BUILD_ROOT_PATH;
    }
}
