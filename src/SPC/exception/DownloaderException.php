<?php

declare(strict_types=1);

namespace SPC\exception;

/**
 * Exception thrown when an error occurs during the downloading process.
 *
 * This exception is used to indicate that a download operation has failed,
 * typically due to network issues, invalid URLs, or other related problems.
 */
class DownloaderException extends SPCException {}
