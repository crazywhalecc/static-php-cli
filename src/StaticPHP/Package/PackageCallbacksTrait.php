<?php

declare(strict_types=1);

namespace StaticPHP\Package;

use StaticPHP\DI\ApplicationContext;
use StaticPHP\Util\FileSystem;

/**
 * Trait for handling package callbacks (info, validate, etc.)
 */
trait PackageCallbacksTrait
{
    protected mixed $info_callback = null;

    protected mixed $validate_callback = null;

    protected mixed $patch_before_build_callbacks = null;

    public function setInfoCallback(callable $callback): void
    {
        $this->info_callback = $callback;
    }

    /**
     * Get package info by invoking the info callback.
     *
     * @return array<string, mixed> Package information
     */
    public function getPackageInfo(): array
    {
        if ($this->info_callback === null) {
            return [];
        }

        // Use CallbackInvoker with current package as context
        $result = ApplicationContext::invoke($this->info_callback, [
            Package::class => $this,
            static::class => $this,
        ]);

        return is_array($result) ? $result : [];
    }

    public function setValidateCallback(callable $callback): void
    {
        $this->validate_callback = $callback;
    }

    public function addPatchBeforeBuildCallback(callable $callback): void
    {
        $this->patch_before_build_callbacks[] = $callback;
    }

    public function patchBeforeBuild(): void
    {
        if (file_exists("{$this->getSourceDir()}/.spc-patched")) {
            return;
        }
        if ($this->patch_before_build_callbacks === null) {
            return;
        }
        // Use CallbackInvoker with current package as context
        foreach ($this->patch_before_build_callbacks as $callback) {
            $ret = ApplicationContext::invoke($callback, [
                Package::class => $this,
                static::class => $this,
            ]);
            if ($ret === true) {
                FileSystem::writeFile("{$this->getSourceDir()}/.spc-patched", 'PATCHED!!!');
            }
        }
    }

    /**
     * Validate the package by invoking the validate callback.
     */
    public function validatePackage(): void
    {
        if ($this->validate_callback === null) {
            return;
        }

        // Use CallbackInvoker with current package as context
        ApplicationContext::invoke($this->validate_callback, [
            Package::class => $this,
            static::class => $this,
        ]);
    }
}
