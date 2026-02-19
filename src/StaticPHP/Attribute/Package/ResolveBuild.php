<?php

declare(strict_types=1);

namespace StaticPHP\Attribute\Package;

/**
 * Indicates that the annotated method is responsible for initializing the build process for a target package.
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
class ResolveBuild {}
