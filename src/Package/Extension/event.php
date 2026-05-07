<?php

declare(strict_types=1);

namespace Package\Extension;

use Package\Target\php;
use StaticPHP\Attribute\Package\BeforeStage;
use StaticPHP\Attribute\Package\CustomPhpConfigureArg;
use StaticPHP\Attribute\Package\Extension;
use StaticPHP\Attribute\PatchDescription;
use StaticPHP\Package\PackageInstaller;
use StaticPHP\Package\PhpExtensionPackage;
use StaticPHP\Runtime\SystemTarget;
use StaticPHP\Util\FileSystem;

#[Extension('event')]
class event extends PhpExtensionPackage
{
    #[CustomPhpConfigureArg('Darwin')]
    #[CustomPhpConfigureArg('Linux')]
    public function getUnixConfigureArg(PackageInstaller $installer): string
    {
        $arg = "--with-event-core --with-event-extra --with-event-libevent-dir={$this->getBuilder()->getBuildRootPath()}";
        if ($installer->getLibraryPackage('openssl')) {
            $arg .= " --with-event-openssl={$this->getBuilder()->getBuildRootPath()}";
        }
        if ($installer->getPhpExtensionPackage('ext-sockets')) {
            $arg .= ' --enable-event-sockets';
        } else {
            $arg .= ' --disable-event-sockets';
        }
        return $arg;
    }

    #[BeforeStage('php', [php::class, 'makeForUnix'], 'ext-event')]
    #[PatchDescription('Prevent event extension compile error on macOS')]
    public function patchBeforeMake(PackageInstaller $installer): void
    {
        // Prevent event extension compile error on macOS
        if (SystemTarget::getTargetOS() === 'Darwin') {
            $php_src = $installer->getTargetPackage('php')->getSourceDir();
            FileSystem::replaceFileRegex("{$php_src}/main/php_config.h", '/^#define HAVE_OPENPTY 1$/m', '');
        }
    }
}
