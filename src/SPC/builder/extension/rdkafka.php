<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\store\FileSystem;
use SPC\util\CustomExt;
use SPC\util\SPCConfigUtil;

#[CustomExt('rdkafka')]
class rdkafka extends Extension
{
    public function patchBeforeBuildconf(): bool
    {
        FileSystem::replaceFileStr("{$this->source_dir}/config.m4", "-L\$RDKAFKA_DIR/\$PHP_LIBDIR -lm\n", "-L\$RDKAFKA_DIR/\$PHP_LIBDIR -lm \$RDKAFKA_LIBS\n");
        FileSystem::replaceFileStr("{$this->source_dir}/config.m4", "-L\$RDKAFKA_DIR/\$PHP_LIBDIR -lm\"\n", '-L$RDKAFKA_DIR/$PHP_LIBDIR -lm $RDKAFKA_LIBS"');
        FileSystem::replaceFileStr("{$this->source_dir}/config.m4", 'PHP_CHECK_LIBRARY($LIBNAME,$LIBSYMBOL,', 'AC_CHECK_LIB([$LIBNAME], [$LIBSYMBOL],');
        return true;
    }

    public function patchBeforeMake(): bool
    {
        parent::patchBeforeMake();
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

    public function getUnixConfigureArg(bool $shared = false): string
    {
        $pkgconf_libs = (new SPCConfigUtil($this->builder, ['no_php' => true, 'libs_only_deps' => true]))->getExtensionConfig($this);
        return '--with-rdkafka=' . ($shared ? 'shared,' : '') . BUILD_ROOT_PATH . " RDKAFKA_LIBS=\"{$pkgconf_libs['libs']}\"";
    }
}
