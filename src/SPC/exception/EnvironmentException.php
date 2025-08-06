<?php

declare(strict_types=1);

namespace SPC\exception;

/**
 * EnvironmentException is thrown when there is an issue with the environment setup,
 * such as missing dependencies or incorrect configurations.
 */
class EnvironmentException extends SPCException
{
    public function __construct(string $message, private readonly ?string $solution = null)
    {
        parent::__construct($message);
    }

    /**
     * Returns the solution for the environment issue.
     */
    public function getSolution(): ?string
    {
        return $this->solution;
    }
}
