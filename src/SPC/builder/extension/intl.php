<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\store\FileSystem;
use SPC\util\CustomExt;

#[CustomExt('intl')]
class intl extends Extension
{
    public function patchBeforeBuildconf(): bool
    {
        // TODO: remove the following line when https://github.com/php/php-src/pull/14002 will be released
        FileSystem::replaceFileStr(SOURCE_PATH . '/php-src/ext/intl/config.m4', 'PHP_CXX_COMPILE_STDCXX(11', 'PHP_CXX_COMPILE_STDCXX(17');
        // Also need to use clang++ -std=c++17 to force override the default C++ standard
        if (is_string($env = getenv('CXX')) && !str_contains($env, 'std=c++17')) {
            f_putenv('CXX=' . $env . ' -std=c++17');
        } else {
            f_putenv('CXX=clang++ -std=c++17');
        }
        return true;
    }
}
