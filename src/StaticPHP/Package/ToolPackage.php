<?php

declare(strict_types=1);

namespace StaticPHP\Package;

use StaticPHP\Config\PackageConfig;
use StaticPHP\Util\FileSystem;
use StaticPHP\Util\GlobalPathTrait;

/**
 * Represents a build-time tool package.
 *
 * Tool packages are NOT link-time dependencies. They provide executables
 * that are needed during the build process (compilers, code generators,
 * assemblers, etc.) and are installed into PKG_ROOT_PATH.
 *
 * Tool packages do NOT produce static-libs, headers, or pkg-config files.
 * They are resolved and installed independently from the library dependency graph.
 *
 * YAML config schema (config/pkg/tool/<name>.yml):
 *
 *   nasm:
 *     type: tool
 *     tool:
 *       provides: [nasm.exe, ndisasm.exe]       # executables this tool installs
 *       binary-subdir: ''                        # subdirectory under install root (default: '')
 *       min-version: '2.16'                      # minimum required version (optional)
 *     artifact:
 *       binary:
 *         windows-x86_64:
 *           type: url
 *           url: 'https://...'
 *           extract:
 *             nasm.exe: '{php_sdk_path}/bin/nasm.exe'
 */
class ToolPackage extends Package
{
    use GlobalPathTrait;

    /**
     * Get the install root directory for this tool.
     *
     * Defaults to PKG_ROOT_PATH. Override via 'tool.install-root' in YAML
     * or via the TOOL_INSTALL_ROOT_{NAME} environment variable.
     */
    public function getInstallRoot(): string
    {
        $env_var = 'TOOL_INSTALL_ROOT_' . strtoupper(str_replace('-', '_', $this->name));
        if ($root = getenv($env_var)) {
            return $root;
        }
        $config_root = $this->getToolConfig()['install-root'] ?? null;
        if ($config_root !== null) {
            return FileSystem::replacePathVariable((string) $config_root);
        }
        return PKG_ROOT_PATH;
    }

    /**
     * Get the directory where this tool's binaries reside.
     *
     * This is {install-root}/{binary-subdir}. If binary-subdir is not
     * configured, returns the install root directly.
     */
    public function getBinaryDir(): string
    {
        $subdir = $this->getToolConfig()['binary-subdir'] ?? '';
        if ($subdir === '') {
            return $this->getInstallRoot();
        }
        return $this->getInstallRoot() . DIRECTORY_SEPARATOR . $subdir;
    }

    /**
     * Get the list of executables this tool provides.
     *
     * Reads from YAML 'tool.provides' field. Each entry is a bare filename
     * (e.g. 'nasm.exe'), resolved relative to getBinaryDir().
     *
     * @return string[] Bare executable names (not full paths)
     */
    public function getProvides(): array
    {
        return $this->getToolConfig()['provides'] ?? [];
    }

    /**
     * Get the full path to a specific binary provided by this tool.
     *
     * @param  string $name Bare executable name (must be listed in tool.provides).
     *                      If empty, defaults to the first entry in provides.
     * @return string Full absolute path to the binary
     */
    public function getBinary(string $name = ''): string
    {
        $provides = $this->getProvides();
        if ($name === '') {
            $name = $provides[0] ?? throw new \RuntimeException("Tool '{$this->name}' has no 'tool.provides' configured.");
        }
        if (!in_array($name, $provides, true)) {
            throw new \RuntimeException("Binary '{$name}' is not listed in tool.provides for '{$this->name}'. Available: " . implode(', ', $provides));
        }
        return $this->getBinaryDir() . DIRECTORY_SEPARATOR . $name;
    }

    /**
     * Check whether this tool is installed (all provided binaries exist on disk).
     */
    public function isInstalled(): bool
    {
        return array_all($this->getProvides(), fn ($binary) => file_exists($this->getBinary($binary)));
    }

    /**
     * Get the minimum required version for this tool, if specified.
     *
     * Returns null if no version constraint is configured.
     */
    public function getMinVersion(): ?string
    {
        $version = $this->getToolConfig()['min-version'] ?? null;
        return $version !== null ? (string) $version : null;
    }

    /**
     * Tools install to PKG_ROOT_PATH (or the configured install-root),
     * not BUILD_ROOT_PATH.
     */
    public function getInstallTarget(): string
    {
        return $this->getBinaryDir();
    }

    /**
     * Get the 'tool' sub-config for this package.
     *
     * Returns the nested array under the 'tool' key in the package YAML,
     * or an empty array if not configured.
     *
     * @return array<string, mixed>
     */
    private function getToolConfig(): array
    {
        $config = PackageConfig::get($this->name);
        if (!is_array($config) || !isset($config['tool']) || !is_array($config['tool'])) {
            return [];
        }
        return $config['tool'];
    }
}
