<?php

declare(strict_types=1);

namespace StaticPHP\Util;

trait GlobalPathTrait
{
    /**
     * Get the build root path for the package.
     */
    public function getBuildRootPath(): string
    {
        return BUILD_ROOT_PATH;
    }

    /**
     * Get the include directory for the package.
     */
    public function getIncludeDir(): string
    {
        return BUILD_INCLUDE_PATH;
    }

    /**
     * Get the library directory for the package.
     */
    public function getLibDir(): string
    {
        return BUILD_LIB_PATH;
    }

    public function getBinDir(): string
    {
        return BUILD_BIN_PATH;
    }
}
