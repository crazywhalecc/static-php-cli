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
use StaticPHP\Util\SPCConfigUtil;

#[Extension('rdkafka')]
class rdkafka extends PhpExtensionPackage
{
    #[BeforeStage('php', [php::class, 'buildconfForUnix'], 'ext-rdkafka')]
    #[PatchDescription('Patch rdkafka extension config.m4 to support pkg-config and fix library check')]
    public function patchBeforeBuildconf(): bool
    {
        FileSystem::replaceFileStr("{$this->getSourceDir()}/config.m4", "-L\$RDKAFKA_DIR/\$PHP_LIBDIR -lm\n", "-L\$RDKAFKA_DIR/\$PHP_LIBDIR -lm \$RDKAFKA_LIBS\n");
        FileSystem::replaceFileStr("{$this->getSourceDir()}/config.m4", "-L\$RDKAFKA_DIR/\$PHP_LIBDIR -lm\"\n", '-L$RDKAFKA_DIR/$PHP_LIBDIR -lm $RDKAFKA_LIBS"');
        FileSystem::replaceFileStr("{$this->getSourceDir()}/config.m4", 'PHP_CHECK_LIBRARY($LIBNAME,$LIBSYMBOL,', 'AC_CHECK_LIB([$LIBNAME], [$LIBSYMBOL],');
        return true;
    }

    #[BeforeStage('php', [php::class, 'makeForUnix'], 'ext-rdkafka')]
    #[PatchDescription('Patch rdkafka extension source code to fix build errors with inline builds')]
    public function patchBeforeMake(): bool
    {
        // when compiling rdkafka with inline builds, it shows some errors, I don't know why.
        FileSystem::replaceFileStr(
            "{$this->getSourceDir()}/rdkafka.c",
            "#ifdef HAS_RD_KAFKA_TRANSACTIONS\n#include \"kafka_error_exception.h\"\n#endif",
            '#include "kafka_error_exception.h"'
        );
        FileSystem::replaceFileStr(
            "{$this->getSourceDir()}/kafka_error_exception.h",
            ['#ifdef HAS_RD_KAFKA_TRANSACTIONS', '#endif'],
            ''
        );
        return true;
    }

    #[CustomPhpConfigureArg('Darwin')]
    #[CustomPhpConfigureArg('Linux')]
    public function getUnixConfigureArg(bool $shared, PackageBuilder $builder): string
    {
        $pkgconf_libs = new SPCConfigUtil(['no_php' => true, 'libs_only_deps' => true])->getExtensionConfig($this);
        return '--with-rdkafka=' . ($shared ? 'shared,' : '') . $builder->getBuildRootPath() . " RDKAFKA_LIBS=\"{$pkgconf_libs['libs']}\"";
    }
}
