<?php

declare(strict_types=1);

namespace SPC\exception;

/**
 * BuildFailureException is thrown when a build process failed with other reasons.
 *
 * This exception indicates that the build operation did not complete successfully,
 * which may be due to various reasons such as missing built-files, incorrect configurations, etc.
 */
class BuildFailureException extends SPCException {}
