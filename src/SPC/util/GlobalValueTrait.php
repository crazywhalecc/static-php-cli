<?php

declare(strict_types=1);

namespace SPC\util;

trait GlobalValueTrait
{
    public function getBinDir(): string
    {
        return BUILD_BIN_PATH;
    }

    public function getIncludeDir(): string
    {
        return BUILD_INCLUDE_PATH;
    }

    public function getBuildRootPath(): string
    {
        return BUILD_ROOT_PATH;
    }

    public function getLibDir(): string
    {
        return BUILD_LIB_PATH;
    }
}
