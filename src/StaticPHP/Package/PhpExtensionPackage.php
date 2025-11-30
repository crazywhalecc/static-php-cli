<?php

declare(strict_types=1);

namespace StaticPHP\Package;

use StaticPHP\Config\PackageConfig;
use StaticPHP\DI\ApplicationContext;
use StaticPHP\Exception\WrongUsageException;
use StaticPHP\Runtime\SystemTarget;

/**
 * Represents a PHP extension package.
 */
class PhpExtensionPackage extends Package
{
    /**
     * @var array <string, callable> Callbacks for custom PHP configure arguments per OS
     */
    protected array $custom_php_configure_arg_callbacks = [];

    protected bool $build_shared = false;

    protected bool $build_static = false;

    protected bool $build_with_php = false;

    /**
     * @param string $name Name of the php extension
     * @param string $type Type of the package, defaults to 'php-extension'
     */
    public function __construct(string $name, string $type = 'php-extension', protected array $extension_config = [])
    {
        // Ensure the package name starts with 'ext-'
        if (!str_starts_with($name, 'ext-')) {
            $name = "ext-{$name}";
        }
        if ($this->extension_config === []) {
            $this->extension_config = PackageConfig::get($name, 'php-extension', []);
        }
        parent::__construct($name, $type);
    }

    public function addCustomPhpConfigureArgCallback(string $os, callable $fn): void
    {
        if ($os === '') {
            foreach (['Linux', 'Windows', 'Darwin'] as $supported_os) {
                $this->custom_php_configure_arg_callbacks[$supported_os] = $fn;
            }
        } else {
            $this->custom_php_configure_arg_callbacks[$os] = $fn;
        }
    }

    public function getPhpConfigureArg(string $os, bool $shared): string
    {
        if (isset($this->custom_php_configure_arg_callbacks[$os])) {
            $callback = $this->custom_php_configure_arg_callbacks[$os];
            return ApplicationContext::invoke($callback, ['shared' => $shared, static::class => $this, Package::class => $this]);
        }
        $escapedPath = str_replace("'", '', escapeshellarg(BUILD_ROOT_PATH)) !== BUILD_ROOT_PATH || str_contains(BUILD_ROOT_PATH, ' ') ? escapeshellarg(BUILD_ROOT_PATH) : BUILD_ROOT_PATH;
        $name = str_replace('_', '-', substr($this->getName(), 4));
        $ext_config = PackageConfig::get($name, 'php-extension', []);

        $arg_type = match (SystemTarget::getTargetOS()) {
            'Windows' => $ext_config['arg-type@windows'] ?? $ext_config['arg-type'] ?? 'enable',
            'Darwin' => $ext_config['arg-type@macos'] ?? $ext_config['arg-type@unix'] ?? $ext_config['arg-type'] ?? 'enable',
            'Linux' => $ext_config['arg-type@linux'] ?? $ext_config['arg-type@unix'] ?? $ext_config['arg-type'] ?? 'enable',
            default => $ext_config['arg-type'] ?? 'enable',
        };

        return match ($arg_type) {
            'enable' => $shared ? "--enable-{$name}=shared" : "--enable-{$name}",
            'enable-path' => $shared ? "--enable-{$name}=shared,{$escapedPath}" : "--enable-{$name}={$escapedPath}",
            'with' => $shared ? "--with-{$name}=shared" : "--with-{$name}",
            'with-path' => $shared ? "--with-{$name}=shared,{$escapedPath}" : "--with-{$name}={$escapedPath}",
            default => throw new WrongUsageException("Unknown argument type '{$arg_type}' for PHP extension '{$name}'"),
        };
    }

    public function setBuildShared(bool $build_shared = true): void
    {
        $this->build_shared = $build_shared;
    }

    public function setBuildStatic(bool $build_static = true): void
    {
        $this->build_static = $build_static;
    }

    public function setBuildWithPhp(bool $build_with_php = true): void
    {
        $this->build_with_php = $build_with_php;
    }

    public function isBuildShared(): bool
    {
        return $this->build_shared;
    }

    public function isBuildStatic(): bool
    {
        return $this->build_static;
    }

    public function isBuildWithPhp(): bool
    {
        return $this->build_with_php;
    }
}
