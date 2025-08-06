<?php

declare(strict_types=1);

namespace SPC\Tests\util;

use PHPUnit\Framework\TestCase;

/**
 * Base test class for util tests with output suppression
 */
abstract class TestBase extends TestCase
{
    protected $outputBuffer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->suppressOutput();
    }

    protected function tearDown(): void
    {
        $this->restoreOutput();
        parent::tearDown();
    }

    /**
     * Suppress output during tests
     */
    protected function suppressOutput(): void
    {
        // Start output buffering to capture PHP output
        $this->outputBuffer = ob_start();
    }

    /**
     * Restore output after tests
     */
    protected function restoreOutput(): void
    {
        // Clean output buffer
        if ($this->outputBuffer) {
            ob_end_clean();
        }
    }

    /**
     * Create a UnixShell instance with debug disabled to suppress logs
     */
    protected function createUnixShell(): \SPC\util\shell\UnixShell
    {
        return new \SPC\util\shell\UnixShell(false);
    }

    /**
     * Create a WindowsCmd instance with debug disabled to suppress logs
     */
    protected function createWindowsCmd(): \SPC\util\shell\WindowsCmd
    {
        return new \SPC\util\shell\WindowsCmd(false);
    }

    /**
     * Run a test with output suppression
     */
    protected function runWithOutputSuppression(callable $callback)
    {
        $this->suppressOutput();
        try {
            return $callback();
        } finally {
            $this->restoreOutput();
        }
    }

    /**
     * Execute a command with output suppression
     */
    protected function execWithSuppression(string $command): array
    {
        $this->suppressOutput();
        try {
            exec($command, $output, $returnCode);
            return [$returnCode, $output];
        } finally {
            $this->restoreOutput();
        }
    }

    /**
     * Execute a command with output redirected to /dev/null
     */
    protected function execSilently(string $command): array
    {
        $command .= ' 2>/dev/null 1>/dev/null';
        exec($command, $output, $returnCode);
        return [$returnCode, $output];
    }
}
