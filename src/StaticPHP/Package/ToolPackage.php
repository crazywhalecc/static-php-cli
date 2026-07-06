<?php

declare(strict_types=1);

namespace StaticPHP\Package;

use StaticPHP\Config\PackageConfig;
use StaticPHP\Runtime\SystemTarget;
use StaticPHP\Util\FileSystem;

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
 *
 * Fields nested under 'tool' support the same '@windows'/'@unix'/'@macos'/'@linux' suffix
 * overrides as top-level package fields (e.g. 'provides@windows' overrides 'provides' when
 * building on Windows), useful when a tool provides differently-named binaries per OS
 * (e.g. upx vs upx.exe).
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
    /**
     * Get the build root ('--prefix') for a tool that builds from source.
     *
     * Unlike LibraryPackage (which installs into BUILD_ROOT_PATH), tool packages that build
     * from source (e.g. pkg-config, via UnixAutoconfExecutor/UnixCMakeExecutor) install into
     * their own install root (PKG_ROOT_PATH by default), consistent with pre-built tool binaries.
     */
    public function getBuildRootPath(): string
    {
        return $this->getInstallRoot();
    }

    /**
     * Tool packages don't produce headers for other packages to consume. Kept self-contained
     * under the tool's own install root so a from-source build never accidentally picks up
     * unrelated headers from BUILD_ROOT_PATH.
     */
    public function getIncludeDir(): string
    {
        return $this->getInstallRoot() . DIRECTORY_SEPARATOR . 'include';
    }

    /**
     * Tool packages don't produce libraries for other packages to consume. Kept self-contained
     * under the tool's own install root (see getIncludeDir()).
     */
    public function getLibDir(): string
    {
        return $this->getInstallRoot() . DIRECTORY_SEPARATOR . 'lib';
    }

    /**
     * Where this tool's own executables live, i.e. {install-root}/{binary-subdir}.
     */
    public function getBinDir(): string
    {
        return $this->getBinaryDir();
    }

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
        $config_root = $this->getToolField('install-root');
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
        $subdir = $this->getToolField('binary-subdir') ?? '';
        if ($subdir === '') {
            return $this->getInstallRoot();
        }
        return $this->getInstallRoot() . DIRECTORY_SEPARATOR . $subdir;
    }

    /**
     * Get the list of executables this tool provides.
     *
     * Reads from YAML 'tool.provides' field (with '@windows'/'@unix'/'@macos'/'@linux'
     * suffix override support). Each entry is a bare filename (e.g. 'nasm.exe'), resolved
     * relative to getBinaryDir().
     *
     * @return string[] Bare executable names (not full paths)
     */
    public function getProvides(): array
    {
        return $this->getToolField('provides') ?? [];
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
     * Get the version currently installed on disk, as recorded by ToolVersionRegistry when this
     * tool's binary was last (re-)installed via PackageInstaller::installBinary().
     *
     * Returns null if the tool was never installed through the package installer (e.g. installed
     * manually, or not installed at all), or if its artifact doesn't expose a version string.
     * This reflects what's actually on disk, unlike the download cache which only reflects the
     * last download and may be stale or cleared.
     */
    public function getInstalledVersion(): ?string
    {
        return ToolVersionRegistry::get($this->name);
    }

    /**
     * Get the minimum required version for this tool, if specified.
     *
     * Returns null if no version constraint is configured.
     */
    public function getMinVersion(): ?string
    {
        $version = $this->getToolField('min-version');
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

    /**
     * Get a field from the nested 'tool' config block, honoring the same
     * '@windows'/'@unix'/'@macos'/'@linux'/'@bsd'/'@freebsd' suffix override priority
     * that PackageConfig::get() applies to top-level fields. This lets a tool declare a
     * per-OS override, e.g. 'provides' + 'provides@windows', without needing platform-
     * specific package config files.
     */
    private function getToolField(string $field): mixed
    {
        $tool = $this->getToolConfig();
        $suffixes = match (SystemTarget::getTargetOS()) {
            'Windows' => ['@windows', ''],
            'Darwin' => ['@macos', '@unix', ''],
            'Linux' => ['@linux', '@unix', ''],
            'BSD' => ['@freebsd', '@bsd', '@unix', ''],
        };
        foreach ($suffixes as $suffix) {
            $key = "{$field}{$suffix}";
            if (isset($tool[$key])) {
                return $tool[$key];
            }
        }
        return null;
    }
}
