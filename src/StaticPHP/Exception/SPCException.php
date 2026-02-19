<?php

declare(strict_types=1);

namespace StaticPHP\Exception;

use StaticPHP\Package\LibraryPackage;
use StaticPHP\Package\Package;
use StaticPHP\Package\PackageBuilder;
use StaticPHP\Package\PackageInstaller;
use StaticPHP\Package\PhpExtensionPackage;
use StaticPHP\Package\TargetPackage;

/**
 * Base class for SPC exceptions.
 *
 * This class serves as the base for all exceptions thrown by the SPC framework.
 * It extends the built-in PHP Exception class, allowing for custom exception handling
 * and categorization of SPC-related errors.
 */
abstract class SPCException extends \Exception
{
    /** @var null|array Package information */
    private ?array $package_info = null;

    /** @var null|array Package builder information */
    private ?array $package_builder_info = null;

    /** @var null|array Package installer information */
    private ?array $package_installer_info = null;

    /** @var array Stage execution call stack */
    private array $stage_stack = [];

    private array $extra_log_files = [];

    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->loadStackTraceInfo();
    }

    /**
     * Bind package information manually.
     *
     * @param array $package_info Package information array
     */
    public function bindPackageInfo(array $package_info): void
    {
        $this->package_info = $package_info;
    }

    /**
     * Add stage to the call stack.
     * This builds a call chain like: build -> configure -> compile
     *
     * @param string $stage_name Stage name being executed
     * @param array  $context    Stage context (optional)
     */
    public function addStageToStack(string $stage_name, array $context = []): void
    {
        $this->stage_stack[] = [
            'stage_name' => $stage_name,
            'context_keys' => array_keys($context),
        ];
    }

    /**
     * Legacy method for backward compatibility.
     * @deprecated Use addStageToStack() instead
     */
    public function bindStageInfo(string $stage_name, array $context = []): void
    {
        $this->addStageToStack($stage_name, $context);
    }

    public function addExtraLogFile(string $key, string $filename): void
    {
        $this->extra_log_files[$key] = $filename;
    }

    /**
     * Returns package information.
     *
     * @return null|array{
     *     package_name: string,
     *     package_type: string,
     *     package_class: string,
     *     file: null|string,
     *     line: null|int,
     * } Package information or null
     */
    public function getPackageInfo(): ?array
    {
        return $this->package_info;
    }

    /**
     * Returns package builder information.
     *
     * @return null|array{
     *     file: null|string,
     *     line: null|int,
     *     method: null|string,
     * } Package builder information or null
     */
    public function getPackageBuilderInfo(): ?array
    {
        return $this->package_builder_info;
    }

    /**
     * Returns package installer information.
     *
     * @return null|array{
     *     file: null|string,
     *     line: null|int,
     *     method: null|string,
     * } Package installer information or null
     */
    public function getPackageInstallerInfo(): ?array
    {
        return $this->package_installer_info;
    }

    /**
     * Returns the stage call stack.
     *
     * @return array<array{
     *     stage_name: string,
     *     context_keys: array<string>,
     * }> Stage call stack (empty array if no stages)
     */
    public function getStageStack(): array
    {
        return $this->stage_stack;
    }

    /**
     * Returns the innermost (actual failing) stage information.
     * Legacy method for backward compatibility.
     *
     * @return null|array{
     *     stage_name: string,
     *     context_keys: array<string>,
     * } Stage information or null
     */
    public function getStageInfo(): ?array
    {
        return empty($this->stage_stack) ? null : end($this->stage_stack);
    }

    public function getExtraLogFiles(): array
    {
        return $this->extra_log_files;
    }

    /**
     * Load stack trace information to detect Package, Builder, and Installer context.
     */
    private function loadStackTraceInfo(): void
    {
        $trace = $this->getTrace();
        foreach ($trace as $frame) {
            if (!isset($frame['class'])) {
                continue;
            }

            // Check if the class is a Package subclass
            if (!$this->package_info && is_a($frame['class'], Package::class, true)) {
                try {
                    // Try to get package information from object if available
                    if (isset($frame['object']) && $frame['object'] instanceof Package) {
                        $package = $frame['object'];
                        $package_type = match (true) {
                            $package instanceof LibraryPackage => 'library',
                            $package instanceof PhpExtensionPackage => 'php-extension',
                            /* @phpstan-ignore-next-line */
                            $package instanceof TargetPackage => 'target',
                            default => 'package',
                        };
                        $this->package_info = [
                            'package_name' => $package->name,
                            'package_type' => $package_type,
                            'package_class' => $frame['class'],
                            'file' => $frame['file'] ?? null,
                            'line' => $frame['line'] ?? null,
                        ];
                        continue;
                    }
                } catch (\Throwable) {
                    // Ignore reflection errors
                }
            }

            // Check if the class is PackageBuilder
            if (!$this->package_builder_info && is_a($frame['class'], PackageBuilder::class, true)) {
                $this->package_builder_info = [
                    'file' => $frame['file'] ?? null,
                    'line' => $frame['line'] ?? null,
                    /* @phpstan-ignore-next-line */
                    'method' => $frame['function'] ?? null,
                ];
                continue;
            }

            // Check if the class is PackageInstaller
            if (!$this->package_installer_info && is_a($frame['class'], PackageInstaller::class, true)) {
                $this->package_installer_info = [
                    'file' => $frame['file'] ?? null,
                    'line' => $frame['line'] ?? null,
                    /* @phpstan-ignore-next-line */
                    'method' => $frame['function'] ?? null,
                ];
            }
        }
    }
}
