<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\builder\windows\WindowsBuilder;
use SPC\exception\ValidationException;
use SPC\store\FileSystem;
use SPC\util\CustomExt;
use SPC\util\GlobalEnvManager;
use SPC\util\SPCConfigUtil;

#[CustomExt('grpc')]
class grpc extends Extension
{
    public function patchBeforeBuildconf(): bool
    {
        if ($this->builder instanceof WindowsBuilder) {
            throw new ValidationException('grpc extension does not support windows yet');
        }

        // Fix deprecated PHP API usage in call.c
        FileSystem::replaceFileStr(
            "{$this->source_dir}/src/php/ext/grpc/call.c",
            'zend_exception_get_default(TSRMLS_C),',
            'zend_ce_exception,',
        );

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
        $c_files = glob($this->source_dir . '/src/php/ext/grpc/*.c');
        $replace['@grpc_c_files@'] = implode(" \\\n    ", array_map(fn ($f) => 'src/php/ext/grpc/' . basename($f), $c_files));
        $config_m4 = str_replace(array_keys($replace), array_values($replace), $config_m4);
        file_put_contents($this->source_dir . '/config.m4', $config_m4);

        copy($this->source_dir . '/src/php/ext/grpc/php_grpc.h', $this->source_dir . '/php_grpc.h');
        return true;
    }

    public function patchBeforeConfigure(): bool
    {
        $util = new SPCConfigUtil($this->builder, ['libs_only_deps' => true]);
        $config = $util->getExtensionConfig($this);
        $libs = $config['libs'];
        FileSystem::replaceFileStr(SOURCE_PATH . '/php-src/configure', '-lgrpc', $libs);
        return true;
    }

    public function patchBeforeMake(): bool
    {
        parent::patchBeforeMake();
        GlobalEnvManager::putenv('SPC_CMD_VAR_PHP_MAKE_EXTRA_CFLAGS=' . getenv('SPC_CMD_VAR_PHP_MAKE_EXTRA_CFLAGS') . ' -Wno-strict-prototypes');
        return true;
    }

    protected function getSharedExtensionEnv(): array
    {
        $env = parent::getSharedExtensionEnv();
        $env['CPPFLAGS'] = $env['CXXFLAGS'] . ' -Wno-attributes';
        return $env;
    }
}
