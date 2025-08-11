<?php

declare(strict_types=1);

namespace SPC\exception;

use SPC\builder\BuilderBase;
use SPC\builder\freebsd\BSDBuilder;
use SPC\builder\freebsd\library\BSDLibraryBase;
use SPC\builder\LibraryBase;
use SPC\builder\linux\library\LinuxLibraryBase;
use SPC\builder\linux\LinuxBuilder;
use SPC\builder\macos\library\MacOSLibraryBase;
use SPC\builder\macos\MacOSBuilder;
use SPC\builder\windows\library\WindowsLibraryBase;
use SPC\builder\windows\WindowsBuilder;

/**
 * Base class for SPC exceptions.
 *
 * This class serves as the base for all exceptions thrown by the SPC framework.
 * It extends the built-in PHP Exception class, allowing for custom exception handling
 * and categorization of SPC-related errors.
 */
abstract class SPCException extends \Exception
{
    private ?array $library_info = null;

    private ?array $extension_info = null;

    private ?array $build_php_info = null;

    private array $extra_log_files = [];

    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->loadStackTraceInfo();
    }

    public function bindExtensionInfo(array $extension_info): void
    {
        $this->extension_info = $extension_info;
    }

    public function addExtraLogFile(string $key, string $filename): void
    {
        $this->extra_log_files[$key] = $filename;
    }

    /**
     * Returns an array containing information about the SPC module.
     *
     * This method can be overridden by subclasses to provide specific module information.
     *
     * @return null|array{
     *     library_name: string,
     *     library_class: string,
     *     os: string,
     *     file: null|string,
     *     line: null|int,
     * } an array containing module information
     */
    public function getLibraryInfo(): ?array
    {
        return $this->library_info;
    }

    /**
     * Returns an array containing information about the PHP build process.
     *
     * @return null|array{
     *     builder_class: string,
     *     builder_options: array<string, mixed>,
     *     builder_function: string,
     *     os: string,
     *     file: null|string,
     *     line: null|int,
     * } an array containing PHP build information
     */
    public function getBuildPHPInfo(): ?array
    {
        return $this->build_php_info;
    }

    /**
     * Returns an array containing information about the SPC extension.
     *
     * This method can be overridden by subclasses to provide specific extension information.
     *
     * @return null|array{
     *     extension_name: string,
     *     extension_class: string,
     *     file: null|string,
     *     line: null|int,
     * } an array containing extension information
     */
    public function getExtensionInfo(): ?array
    {
        return $this->extension_info;
    }

    public function getExtraLogFiles(): array
    {
        return $this->extra_log_files;
    }

    private function loadStackTraceInfo(): void
    {
        $trace = $this->getTrace();
        foreach ($trace as $frame) {
            if (!isset($frame['class'])) {
                continue;
            }

            // Check if the class is a subclass of LibraryBase
            if (!$this->library_info && is_a($frame['class'], LibraryBase::class, true)) {
                try {
                    $reflection = new \ReflectionClass($frame['class']);
                    if ($reflection->hasConstant('NAME')) {
                        $name = $reflection->getConstant('NAME');
                        if ($name !== 'unknown') {
                            $this->library_info = [
                                'library_name' => $name,
                                'library_class' => $frame['class'],
                                'os' => match (true) {
                                    is_a($frame['class'], BSDLibraryBase::class, true) => 'BSD',
                                    is_a($frame['class'], LinuxLibraryBase::class, true) => 'Linux',
                                    is_a($frame['class'], MacOSLibraryBase::class, true) => 'macOS',
                                    is_a($frame['class'], WindowsLibraryBase::class, true) => 'Windows',
                                    default => 'Unknown',
                                },
                                'file' => $frame['file'] ?? null,
                                'line' => $frame['line'] ?? null,
                            ];
                            continue;
                        }
                    }
                } catch (\ReflectionException) {
                    continue;
                }
            }

            // Check if the class is a subclass of BuilderBase and the method is buildPHP
            if (!$this->build_php_info && is_a($frame['class'], BuilderBase::class, true)) {
                $options = ExceptionHandler::$bind_builder?->getOptions() ?? [];
                $os = match (get_class(ExceptionHandler::$bind_builder ?? $frame['class'])) {
                    BSDBuilder::class => 'BSD',
                    LinuxBuilder::class => 'Linux',
                    MacOSBuilder::class => 'macOS',
                    WindowsBuilder::class => 'Windows',
                    default => 'Unknown',
                };
                $this->build_php_info = [
                    'builder_class' => $frame['class'],
                    'builder_options' => $options,
                    'builder_function' => $frame['function'],
                    'os' => $os,
                    'file' => $frame['file'] ?? null,
                    'line' => $frame['line'] ?? null,
                ];
            }
        }
    }
}
