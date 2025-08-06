<?php

declare(strict_types=1);

namespace SPC\exception;

/**
 * PatchException is thrown when there is an issue applying a patch,
 * such as a failure in the patch process or conflicts during patching.
 */
class PatchException extends SPCException
{
    public function __construct(private readonly string $patch_module, $message, $code = 0, ?\Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Returns the name of the patch module that caused the exception.
     */
    public function getPatchModule(): string
    {
        return $this->patch_module;
    }
}
