<?php

declare(strict_types=1);

namespace Package\Extension;

use Package\Target\php;
use StaticPHP\Attribute\Package\BeforeStage;
use StaticPHP\Attribute\Package\CustomPhpConfigureArg;
use StaticPHP\Attribute\Package\Extension;
use StaticPHP\Attribute\Package\Validate;
use StaticPHP\Exception\WrongUsageException;
use StaticPHP\Package\PackageBuilder;
use StaticPHP\Package\PackageInstaller;
use StaticPHP\Package\PhpExtensionPackage;
use StaticPHP\Util\FileSystem;

#[Extension('imap')]
class imap extends PhpExtensionPackage
{
    #[Validate]
    public function validate(PackageBuilder $builder): void
    {
        if ($builder->getOption('enable-zts')) {
            throw new WrongUsageException('ext-imap is not thread safe, do not build it with ZTS builds');
        }
    }

    #[BeforeStage('php', [php::class, 'buildconfForUnix'], 'ext-imap')]
    public function patchBeforeBuildconf(PackageInstaller $installer): void
    {
        if ($installer->getLibraryPackage('openssl')) {
            // sometimes imap with openssl does not contain zlib (required by openssl)
            // we need to add it manually
            FileSystem::replaceFileStr("{$this->getSourceDir()}/config.m4", 'TST_LIBS="$DLIBS $IMAP_SHARED_LIBADD"', 'TST_LIBS="$DLIBS $IMAP_SHARED_LIBADD -lz"');
        }
        // c-client is built with PASSWDTYPE=nul so libcrypt is not referenced.
        FileSystem::replaceFileStr(
            "{$this->getSourceDir()}/config.m4",
            "    PHP_CHECK_LIBRARY(crypt, crypt,\n    [\n      PHP_ADD_LIBRARY(crypt,, IMAP_SHARED_LIBADD)\n      AC_DEFINE(HAVE_LIBCRYPT,1,[ ])\n    ])",
            '    dnl Skipped: crypt check not needed (c-client built with PASSWDTYPE=nul)'
        );
    }

    #[CustomPhpConfigureArg('Darwin')]
    #[CustomPhpConfigureArg('Linux')]
    public function getUnixConfigureArg(PackageInstaller $installer, PackageBuilder $builder): string
    {
        $arg = "--with-imap={$builder->getBuildRootPath()}";
        if (($ssl = $installer->getLibraryPackage('openssl')) !== null) {
            $arg .= " --with-imap-ssl={$ssl->getBuildRootPath()}";
        }
        return $arg;
    }
}
