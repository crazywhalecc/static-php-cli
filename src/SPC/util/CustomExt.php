<?php

declare(strict_types=1);

namespace SPC\util;

use SPC\builder\Extension;

/**
 * Custom extension attribute and manager
 *
 * This class provides functionality to register and manage custom PHP extensions
 * that can be used during the build process.
 */
#[\Attribute(\Attribute::IS_REPEATABLE | \Attribute::TARGET_CLASS)]
class CustomExt
{
    /**
     * Constructor for custom extension attribute
     *
     * @param string $ext_name The extension name
     */
    public function __construct(public string $ext_name) {}
}
