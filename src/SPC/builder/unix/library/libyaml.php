<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\exception\RuntimeException;
use SPC\store\FileSystem;

trait libyaml
{
    /**
     * @throws RuntimeException
     */
    protected function build()
    {
        // prepare cmake/config.h.in
        if (!is_file(SOURCE_PATH . '/libyaml/cmake/config.h.in')) {
            f_mkdir(SOURCE_PATH . '/libyaml/cmake');
            file_put_contents(
                SOURCE_PATH . '/libyaml/cmake/config.h.in',
                <<<'EOF'
#define YAML_VERSION_MAJOR @YAML_VERSION_MAJOR@
#define YAML_VERSION_MINOR @YAML_VERSION_MINOR@
#define YAML_VERSION_PATCH @YAML_VERSION_PATCH@
#define YAML_VERSION_STRING "@YAML_VERSION_STRING@"
EOF
            );
        }

        // prepare yamlConfig.cmake.in
        if (!is_file(SOURCE_PATH . '/libyaml/yamlConfig.cmake.in')) {
            file_put_contents(
                SOURCE_PATH . '/libyaml/yamlConfig.cmake.in',
                <<<'EOF'
# Config file for the yaml library.
#
# It defines the following variables:
#   yaml_LIBRARIES    - libraries to link against

@PACKAGE_INIT@

set_and_check(yaml_TARGETS "@PACKAGE_CONFIG_DIR_CONFIG@/yamlTargets.cmake")

if(NOT yaml_TARGETS_IMPORTED)
  set(yaml_TARGETS_IMPORTED 1)
  include(${yaml_TARGETS})
endif()

set(yaml_LIBRARIES yaml)

EOF
            );
        }

        [$lib, $include, $destdir] = SEPARATED_PATH;

        FileSystem::resetDir($this->source_dir . '/build');
        shell()->cd($this->source_dir . '/build')
            ->exec(
                "{$this->builder->configure_env} cmake " .
                // '--debug-find ' .
                '-DCMAKE_BUILD_TYPE=Release ' .
                '-DBUILD_TESTING=OFF ' .
                '-DBUILD_SHARED_LIBS=OFF ' .
                '-DCMAKE_INSTALL_PREFIX=/ ' .
                "-DCMAKE_INSTALL_LIBDIR={$lib} " .
                "-DCMAKE_INSTALL_INCLUDEDIR={$include} " .
                "-DCMAKE_TOOLCHAIN_FILE={$this->builder->cmake_toolchain_file} " .
                '..'
            )
            ->exec("make -j{$this->builder->concurrency}")
            ->exec("make install DESTDIR={$destdir}");
    }
}
