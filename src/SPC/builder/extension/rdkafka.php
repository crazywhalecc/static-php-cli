<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\store\FileSystem;
use SPC\util\CustomExt;

#[CustomExt('rdkafka')]
class rdkafka extends Extension
{
    public function patchBeforeMake(): bool
    {
        // when compiling rdkafka with inline builds, it shows some errors, I don't know why.
        FileSystem::replaceFileStr(
            SOURCE_PATH . '/php-src/ext/rdkafka/rdkafka.c',
            "#ifdef HAS_RD_KAFKA_TRANSACTIONS\n#include \"kafka_error_exception.h\"\n#endif",
            '#include "kafka_error_exception.h"'
        );
        FileSystem::replaceFileStr(
            SOURCE_PATH . '/php-src/ext/rdkafka/kafka_error_exception.h',
            ['#ifdef HAS_RD_KAFKA_TRANSACTIONS', '#endif'],
            ''
        );
        return true;
    }

    public function getConfigureArg(): string
    {
        $pkgconf_libs = shell()->execWithResult('pkg-config --libs --static rdkafka')[1];
        $pkgconf_libs = trim(implode('', $pkgconf_libs));
        return '--with-rdkafka=' . BUILD_ROOT_PATH . ' LIBS="' . $pkgconf_libs . '"';
    }
}
