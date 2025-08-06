<?php

declare(strict_types=1);

namespace SPC\exception;

/**
 * Exception thrown for incorrect usage of SPC.
 *
 * This exception is used to indicate that the SPC is being used incorrectly.
 * Such as when a command is not supported or an invalid argument is provided.
 */
class WrongUsageException extends SPCException {}
