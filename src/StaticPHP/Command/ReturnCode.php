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

    public const SUCCESS = 0; // alias

    public const FAILURE = 1; // generic failure

    // 64-69: reserved for standard errors
    public const int USER_ERROR = 64; // wrong usage, bad arguments

    public const int VALIDATION_ERROR = 65; // invalid input or config values

    public const int ENVIRONMENT_ERROR = 69; // required tools/env not available

    // 70+: application-specific errors
    public const int INTERNAL_ERROR = 70; // internal logic error or unexpected state

    public const int BUILD_ERROR = 72; // build / compile process failed

    public const int PATCH_ERROR = 73; // patching or modifying files failed

    public const int FILE_SYSTEM_ERROR = 74; // filesystem / IO error

    public const int DOWNLOAD_ERROR = 75; // network / remote resource error

    // 128+: reserved for standard signals and interrupts
    public const int INTERRUPT_SIGNAL = 130; // SIGINT (Ctrl+C)
}
