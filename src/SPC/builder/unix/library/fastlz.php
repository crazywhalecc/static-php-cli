<?php

declare(strict_types=1);

namespace SPC\builder\unix\library;

use SPC\exception\BuildFailureException;

trait fastlz
{
    protected function build(): void
    {
        shell()->cd($this->source_dir)->initializeEnv($this)
            ->exec((getenv('CC') ?: 'cc') . ' -c -O3 -fPIC fastlz.c -o fastlz.o')
            ->exec((getenv('AR') ?: 'ar') . ' rcs libfastlz.a fastlz.o');

        if (!copy($this->source_dir . '/fastlz.h', BUILD_INCLUDE_PATH . '/fastlz.h')) {
            throw new BuildFailureException('Failed to copy fastlz.h, file does not exist');
        }
        if (!copy($this->source_dir . '/libfastlz.a', BUILD_LIB_PATH . '/libfastlz.a')) {
            throw new BuildFailureException('Failed to copy libfastlz.a, file does not exist');
        }
    }
}
