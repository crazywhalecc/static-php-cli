<?php

/** @noinspection PhpMissingClassConstantTypeInspection */

declare(strict_types=1);

namespace StaticPHP\Command;

/**
 * Return codes for command execution.
 *
 * This enum defines standard return codes that can be used by commands to indicate
 * the result of their execution. It includes codes for success, various types of errors,
 * and an interrupt signal.
 */
trait ReturnCode
{
    public const int OK = 0;

    public const SUCCESS = 0; // alias of OK

    public const int INTERNAL_ERROR = 1; // unsorted or internal error

    /** @deprecated Use specified error code instead */
    public const FAILURE = 1;

    public const int USER_ERROR = 2; // wrong usage or user error

    public const int ENVIRONMENT_ERROR = 3; // environment not suitable for operation

    public const int VALIDATION_ERROR = 4; // validation failed

    public const int FILE_SYSTEM_ERROR = 5; // file system related error

    public const int DOWNLOAD_ERROR = 6; // network related error

    public const int BUILD_ERROR = 7; // build process error

    public const int PATCH_ERROR = 8; // patching process error

    public const int INTERRUPT_SIGNAL = 130; // process interrupted by user (e.g., Ctrl+C)
}
