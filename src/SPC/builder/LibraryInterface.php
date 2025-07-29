<?php

declare(strict_types=1);

namespace SPC\builder;

/**
 * Interface for library implementations
 *
 * This interface defines the basic contract that all library classes must implement.
 * It provides a common way to identify and work with different library types.
 */
interface LibraryInterface
{
    /**
     * Get the name of the library
     *
     * @return string The library name
     */
    public function getName(): string;
}
