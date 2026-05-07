<?php

declare(strict_types=1);

namespace StaticPHP\Exception;

/**
 * Exception thrown when an error occurs during the downloading process.
 *
 * This exception is used to indicate that a download operation has failed,
 * typically due to network issues, invalid URLs, or other related problems.
 */
class DownloaderException extends SPCException
{
    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null, private readonly ?string $artifact_name = null)
    {
        parent::__construct($message, $code, $previous);
    }

    public function getArtifactName(): ?string
    {
        return $this->artifact_name;
    }
}
