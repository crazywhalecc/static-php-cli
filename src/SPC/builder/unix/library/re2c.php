<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\util\executor\UnixCMakeExecutor;

trait re2c
{
    protected function build(): void
    {
        UnixCMakeExecutor::create($this)
            ->addConfigureArgs(
                '-DRE2C_BUILD_TESTS=OFF',
                '-DRE2C_BUILD_EXAMPLES=OFF',
                '-DRE2C_BUILD_DOCS=OFF',
                '-DRE2C_BUILD_RE2D=OFF',
                '-DRE2C_BUILD_RE2GO=OFF',
                '-DRE2C_BUILD_RE2HS=OFF',
                '-DRE2C_BUILD_RE2JAVA=OFF',
                '-DRE2C_BUILD_RE2JS=OFF',
                '-DRE2C_BUILD_RE2OCAML=OFF',
                '-DRE2C_BUILD_RE2PY=OFF',
                '-DRE2C_BUILD_RE2RUST=OFF',
                '-DRE2C_BUILD_RE2SWIFT=OFF',
                '-DRE2C_BUILD_RE2V=OFF',
                '-DRE2C_BUILD_RE2ZIG=OFF',
            )
            ->build();
    }
}
