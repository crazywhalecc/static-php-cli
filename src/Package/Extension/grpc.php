<?php

declare(strict_types=1);

namespace Package\Extension;

use Package\Target\php;
use StaticPHP\Attribute\Package\BeforeStage;
use StaticPHP\Attribute\Package\Extension;
use StaticPHP\Exception\EnvironmentException;
use StaticPHP\Package\PhpExtensionPackage;
use StaticPHP\Runtime\SystemTarget;
use StaticPHP\Util\FileSystem;

#[Extension('grpc')]
class grpc extends PhpExtensionPackage
{
    #[BeforeStage('php', [php::class, 'buildconfForUnix'], 'ext-grpc')]
    public function patchBeforeBuildconf(): void
    {
        if (SystemTarget::getTargetOS() === 'Windows') {
            throw new EnvironmentException('grpc extension does not support windows yet');
        }

        // Fix deprecated PHP API usage in call.c
        FileSystem::replaceFileStr(
            "{$this->getSourceDir()}/src/php/ext/grpc/call.c",
            'zend_exception_get_default(TSRMLS_C),',
            'zend_ce_exception,',
        );

        // custom config.m4 content for grpc extension, to prevent building libgrpc.a again
        $config_m4 = <<<'M4'
PHP_ARG_ENABLE(grpc, [whether to enable grpc support], [AS_HELP_STRING([--enable-grpc], [Enable grpc support])])

if test "$PHP_GRPC" != "no"; then
  PHP_ADD_INCLUDE(PHP_EXT_SRCDIR()/include)
  PHP_ADD_INCLUDE(PHP_EXT_SRCDIR()/src/php/ext/grpc)
  GRPC_LIBDIR=@@build_lib_path@@
  PHP_ADD_LIBPATH($GRPC_LIBDIR)
  PHP_ADD_LIBRARY(grpc,,GRPC_SHARED_LIBADD)
  LIBS="-lpthread $LIBS"
  PHP_ADD_LIBRARY(pthread)

  case $host in
    *darwin*)
      PHP_ADD_LIBRARY(c++,1,GRPC_SHARED_LIBADD)
      ;;
    *)
      PHP_ADD_LIBRARY(stdc++,1,GRPC_SHARED_LIBADD)
      PHP_ADD_LIBRARY(rt,,GRPC_SHARED_LIBADD)
      PHP_ADD_LIBRARY(rt)
      ;;
    esac

  PHP_NEW_EXTENSION(grpc, @grpc_c_files@, $ext_shared, , -DGRPC_POSIX_FORK_ALLOW_PTHREAD_ATFORK=1)
  PHP_SUBST(GRPC_SHARED_LIBADD)
  PHP_INSTALL_HEADERS([ext/grpc], [php_grpc.h])
fi
M4;
        $replace = get_pack_replace();
        // load grpc c files from src/php/ext/grpc
        $c_files = glob("{$this->getSourceDir()}/src/php/ext/grpc/*.c");
        $replace['@grpc_c_files@'] = implode(" \\\n    ", array_map(fn ($f) => 'src/php/ext/grpc/' . basename($f), $c_files));
        $config_m4 = str_replace(array_keys($replace), array_values($replace), $config_m4);
        file_put_contents("{$this->getSourceDir()}/config.m4", $config_m4);

        copy("{$this->getSourceDir()}/src/php/ext/grpc/php_grpc.h", "{$this->getSourceDir()}/php_grpc.h");
    }
}
