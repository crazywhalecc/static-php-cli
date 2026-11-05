<?php

declare(strict_types=1);

namespace StaticPHP\Util;

use StaticPHP\Artifact\ArtifactCache;
use StaticPHP\Config\ArtifactConfig;
use StaticPHP\Config\PackageConfig;
use StaticPHP\DI\ApplicationContext;
use StaticPHP\Exception\WrongUsageException;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Runtime\SystemTarget;
use Symfony\Component\Process\Process;

final class SharedLibraryManifest
{
    private array $manifest;

    private array $ownership;

    private array $before = [];

    private array $previous = [];

    private string $building = '';

    public function __construct()
    {
        $this->manifest = $this->read('shared-libraries.json') ?? [
            'schema' => 1, 'linkage' => 'shared', 'architecture' => SystemTarget::getTargetArch(),
            'libc' => SystemTarget::getLibc(), 'runtime_libdir' => SharedLibraryUtil::runtimeDirectory(), 'libraries' => [],
        ];
        $this->ownership = $this->read('.shared-library-ownership.json') ?? ['schema' => 1, 'libraries' => []];
        if (($this->manifest['schema'] ?? null) !== 1 || ($this->manifest['linkage'] ?? null) !== 'shared'
            || ($this->manifest['architecture'] ?? null) !== SystemTarget::getTargetArch()
            || ($this->manifest['libc'] ?? null) !== SystemTarget::getLibc()
            || ($this->manifest['runtime_libdir'] ?? null) !== SharedLibraryUtil::runtimeDirectory()
            || !is_array($this->manifest['libraries'] ?? null)
            || ($this->ownership['schema'] ?? null) !== 1 || !is_array($this->ownership['libraries'] ?? null)) {
            throw new WrongUsageException('Invalid or incompatible shared-library SDK');
        }
    }

    public function has(LibraryPackage $package): bool
    {
        return isset($this->manifest['libraries'][$package->getName()]);
    }

    public function reuse(LibraryPackage $package): void
    {
        $name = $package->getName();
        $entry = $this->manifest['libraries'][$name] ?? null;
        $owned = $this->ownership['libraries'][$name] ?? null;
        $source = $this->source($package);
        if (!is_array($entry) || !is_array($owned) || !$package->isInstalled()
            || isset($this->manifest['pending_rebuilds'][$name])
            || ($entry['source_hash'] ?? null) !== ($source['hash'] ?? null)
            || ($entry['source_hash'] ?? null) !== ($owned['source_hash'] ?? null)
            || empty($owned['files']) || empty($owned['file_hashes'])) {
            throw new WrongUsageException('LIBRARY_REUSE invalid or pending SDK entry: ' . $name);
        }
        $visited = [];
        $this->verifyRecord($name, $visited);
        $runtimeNeeded = array_merge([], ...array_map(static fn (array $details): array => $details['needed'], array_values($entry['elf'] ?? [])));
        if (isset($this->manifest['libraries']['gcc-runtime']) && in_array('libstdc++.so.6', $runtimeNeeded, true)) {
            $this->compilerRuntimes(false);
        }
        logger()->info('LIBRARY_REUSE verified ' . $name);
    }

    public function begin(LibraryPackage $package): void
    {
        $name = $this->building = $package->getName();
        $this->previous = $this->manifest['libraries'][$name] ?? [];
        $owned = $this->ownership['libraries'][$name] ?? [];
        foreach ($owned['files'] ?? [] as $relative) {
            $path = $this->path($relative);
            foreach ($this->ownership['libraries'] as $other => $record) {
                if ($other !== $name && in_array($relative, $record['files'] ?? [], true)) {
                    throw new WrongUsageException('Conflicting SDK ownership: ' . $relative);
                }
            }
            if ((is_file($path) || is_link($path)) && !unlink($path)) {
                throw new WrongUsageException('Cannot retire SDK output: ' . $relative);
            }
        }
        // Invalidate the entry before compiling so an interrupted build cannot be reused.
        foreach ($this->manifest['libraries'] as $consumer => $entry) {
            if ($consumer === $name) {
                continue;
            }
            foreach ($entry['elf'] ?? [] as $details) {
                foreach ($details['needed'] as $needed) {
                    foreach ($this->previous['elf'] ?? [] as $previousElf) {
                        if ($previousElf['soname'] === $needed) {
                            $this->manifest['pending_rebuilds'][$consumer][$needed] = $name;
                        }
                    }
                }
            }
        }
        unset($this->manifest['libraries'][$name], $this->ownership['libraries'][$name]);
        $this->save();
        $this->before = $this->snapshot();
    }

    public function abort(): void
    {
        if ($this->building === '') {
            return;
        }
        $after = $this->snapshot();
        foreach (array_reverse(array_keys($after)) as $relative) {
            if (!isset($this->before[$relative])) {
                $path = $this->path($relative, true);
                if (is_file($path) || is_link($path)) {
                    unlink($path);
                }
            }
        }
        $this->building = '';
    }

    public function finish(LibraryPackage $package): void
    {
        if ($package->getName() === 'imap') {
            SharedLibraryUtil::linkArchive($package, 'libc-client.a');
        }
        SharedLibraryUtil::normalizeLibraryDirectory();
        SharedLibraryUtil::removeLibtoolArchives();
        if (!$package->isInstalled()) {
            throw new WrongUsageException('Shared library build did not install its declared outputs: ' . $package->getName());
        }
        $source = $this->source($package);
        if (empty($source['hash']) || empty($source['version'])) {
            throw new WrongUsageException('Shared library source has no version or hash: ' . $package->getName());
        }
        $artifact = $package->getArtifact()?->getName();
        $metadata = $artifact === null ? [] : (ArtifactConfig::get($artifact)['metadata'] ?? []);
        $licenses = [];
        foreach ((array) ($metadata['license-files'] ?? []) as $index => $file) {
            $sourcePath = str_starts_with($file, '@/') ? ROOT_DIR . '/src/globals/licenses/' . substr($file, 2)
                : (FileSystem::isRelativePath($file) ? $package->getSourceDir() . '/' . $file : $file);
            if (!is_file($sourcePath)) {
                throw new WrongUsageException('Missing shared library license: ' . $sourcePath);
            }
            $relative = 'license/' . $package->getName() . '/' . $index . '.txt';
            FileSystem::createDir(dirname($this->path($relative)));
            FileSystem::copy($sourcePath, $this->path($relative));
            $licenses[] = $relative;
        }
        if ($licenses === []) {
            throw new WrongUsageException('Missing shared library license metadata: ' . $package->getName());
        }
        $after = $this->snapshot();
        $files = [];
        $runtime = [];
        $runtimeAssets = [];
        $elf = [];
        $hashes = [];
        foreach ($after as $relative => $signature) {
            if (($this->before[$relative] ?? null) === $signature) {
                continue;
            }
            foreach ($this->ownership['libraries'] as $name => $record) {
                if (in_array($relative, $record['files'] ?? [], true)) {
                    throw new WrongUsageException('Shared build overwrote output owned by ' . $name . ': ' . $relative);
                }
            }
            $files[] = $relative;
            $hashes[$relative] = $this->digest($this->path($relative));
            if (str_starts_with($relative, 'etc/')) {
                $runtime[] = $relative;
                $runtimeAssets[$relative] = '/etc/php-zts/' . substr($relative, 4);
            } elseif (str_starts_with($relative, 'share/')
                && !preg_match('~^share/(?:man|doc|info|aclocal|cmake|pkgconfig|bash-completion)/~', $relative)) {
                $runtime[] = $relative;
                $runtimeAssets[$relative] = '/usr/share/php-zts/' . substr($relative, 6);
            }
            if (preg_match('~^lib/.*\.so(?:\.[0-9A-Za-z_.+-]+)?$~D', $relative)) {
                $runtime[] = $relative;
                if (dirname($relative) !== 'lib') {
                    $runtimeAssets[$relative] = SharedLibraryUtil::runtimeDirectory() . '/' . substr($relative, 4);
                }
                if (!is_link($this->path($relative))) {
                    $path = $this->path($relative);
                    $process = new Process([getenv('PATCHELF') ?: 'patchelf', '--set-rpath', SharedLibraryUtil::runtimeDirectory(), $path]);
                    $process->mustRun();
                    $hashes[$relative] = $this->digest($path);
                    $elf[$relative] = $this->elf($path);
                }
            }
        }
        foreach ($licenses as $license) {
            if (!in_array($license, $runtime, true)) {
                $runtime[] = $license;
            }
        }
        if (SharedLibraryUtil::buildsShared($package) && $elf === []) {
            throw new WrongUsageException('No shared objects installed by ' . $package->getName());
        }
        $depends = array_values(array_filter(array_unique(array_merge(
            PackageConfig::get($package->getName(), 'depends', []),
            PackageConfig::get($package->getName(), 'suggests', [])
        )), fn (string $name): bool => isset($this->manifest['libraries'][$name])));
        $entry = ['version' => $this->sourceVersion($package, $source), 'source_hash' => $source['hash'],
            'license' => $metadata['license'] ?? 'Custom', 'license_files' => $licenses,
            'files' => $runtime, 'file_hashes' => array_intersect_key($hashes, array_flip($runtime)),
            'runtime_assets' => (object) $runtimeAssets, 'elf' => $elf, 'depends' => $depends, 'runtime_libdir' => SharedLibraryUtil::runtimeDirectory()];
        $this->manifest['libraries'][$package->getName()] = $entry;
        $this->ownership['libraries'][$package->getName()] = ['source_hash' => $source['hash'], 'files' => $files, 'file_hashes' => $hashes];
        $this->compilerRuntimes();
        $this->reconcilePending();
        $this->save();
        $this->building = '';
    }

    private function verifyRecord(string $name, array &$visited): void
    {
        if (isset($visited[$name])) {
            return;
        }
        $visited[$name] = true;
        $entry = $this->manifest['libraries'][$name] ?? null;
        $owned = $this->ownership['libraries'][$name] ?? null;
        if (!is_array($entry) || !is_array($owned) || empty($owned['files'])
            || ($entry['source_hash'] ?? null) !== ($owned['source_hash'] ?? null)
            || isset($this->manifest['pending_rebuilds'][$name])) {
            throw new WrongUsageException('LIBRARY_REUSE incomplete or pending dependency: ' . $name);
        }
        foreach ($owned['files'] as $relative) {
            $path = $this->path($relative);
            if ((!is_file($path) && !is_link($path)) || $this->digest($path) !== ($owned['file_hashes'][$relative] ?? null)) {
                throw new WrongUsageException('LIBRARY_REUSE changed or missing output: ' . $relative);
            }
        }
        foreach ($entry['elf'] ?? [] as $relative => $details) {
            if ($this->elf($this->path($relative)) !== $details) {
                throw new WrongUsageException('LIBRARY_REUSE stale ELF metadata: ' . $relative);
            }
            foreach ($details['needed'] as $needed) {
                foreach ($this->manifest['libraries'] as $provider => $candidate) {
                    foreach ($candidate['elf'] ?? [] as $providerElf) {
                        if ($providerElf['soname'] === $needed) {
                            $this->verifyRecord($provider, $visited);
                        }
                    }
                }
            }
        }
        foreach ($entry['depends'] ?? [] as $dependency) {
            $this->verifyRecord($dependency, $visited);
        }
    }

    private function compilerRuntimes(bool $allowUpdate = true): void
    {
        $names = ['libgcc_s.so.1', 'libstdc++.so.6', 'libatomic.so.1', 'libgomp.so.1', 'libitm.so.1', 'libquadmath.so.0', 'libssp.so.0'];
        $needed = [];
        foreach ($this->manifest['libraries'] as $entry) {
            foreach ($entry['elf'] ?? [] as $details) {
                $needed = array_merge($needed, array_intersect($names, $details['needed']));
            }
        }
        if ($needed === []) {
            return;
        }
        $compiler = getenv('CXX') ?: 'g++';
        $sources = [];
        foreach ($names as $name) {
            $process = new Process([$compiler, '-print-file-name=' . $name]);
            $process->mustRun();
            $path = realpath(trim($process->getOutput()));
            if ($path !== false && is_file($path)) {
                $sources[$name] = $path;
            } elseif (in_array($name, $needed, true)) {
                throw new WrongUsageException('Compiler cannot supply runtime: ' . $name);
            }
        }
        $versionProcess = new Process([$compiler, '-dumpfullversion']);
        $versionProcess->mustRun();
        $version = trim($versionProcess->getOutput());
        $identity = [];
        foreach ($sources as $name => $path) {
            $identity[$name] = hash_file('sha256', $path);
        }
        $sourceHash = hash('sha256', json_encode($identity, JSON_THROW_ON_ERROR));
        if (($this->manifest['libraries']['gcc-runtime']['source_hash'] ?? null) === $sourceHash) {
            $owned = $this->ownership['libraries']['gcc-runtime'] ?? [];
            if (empty($owned['files']) || ($owned['source_hash'] ?? null) !== $sourceHash) {
                throw new WrongUsageException('Incomplete compiler runtime ownership');
            }
            foreach ($owned['files'] ?? [] as $relative) {
                if ($this->digest($this->path($relative)) !== ($owned['file_hashes'][$relative] ?? null)) {
                    throw new WrongUsageException('Changed compiler runtime output: ' . $relative);
                }
            }
            return;
        }
        if (!$allowUpdate) {
            throw new WrongUsageException('Compiler runtime differs from the reusable SDK');
        }
        if (isset($this->manifest['libraries']['gcc-runtime'])) {
            foreach (array_reverse($this->ownership['libraries']['gcc-runtime']['files'] ?? []) as $relative) {
                unlink($this->path($relative));
            }
            unset($this->manifest['libraries']['gcc-runtime'], $this->ownership['libraries']['gcc-runtime']);
        }
        $compilerPath = str_contains($compiler, '/') ? realpath($compiler) : null;
        $prefix = $compilerPath ? dirname($compilerPath, 2) : '/usr';
        $licenseDirectory = null;
        foreach ([$prefix . '/share/licenses/gcc', '/opt/gcc/share/licenses/gcc', '/usr/share/licenses/gcc'] as $candidate) {
            if (is_file($candidate . '/COPYING3') && is_file($candidate . '/COPYING.RUNTIME')) {
                $licenseDirectory = $candidate;
                break;
            }
        }
        if ($licenseDirectory === null) {
            throw new WrongUsageException('Compiler runtime requires COPYING3 and COPYING.RUNTIME licenses');
        }
        $files = $licenses = $hashes = $elf = [];
        foreach ($sources as $name => $path) {
            $relative = 'lib/' . basename($path);
            $target = $this->path($relative);
            if (file_exists($target) || is_link($target)) {
                throw new WrongUsageException('Unowned compiler runtime output: ' . $relative);
            }
            FileSystem::copy($path, $target);
            (new Process([getenv('PATCHELF') ?: 'patchelf', '--set-rpath', SharedLibraryUtil::runtimeDirectory(), $target]))->mustRun();
            $files[] = $relative;
            $hashes[$relative] = $this->digest($target);
            $elf[$relative] = $this->elf($target);
            if (basename($path) !== $name) {
                $link = 'lib/' . $name;
                if (!symlink(basename($path), $this->path($link))) {
                    throw new WrongUsageException('Cannot deploy compiler runtime SONAME: ' . $name);
                }
                $files[] = $link;
                $hashes[$link] = $this->digest($this->path($link));
            }
        }
        foreach (['COPYING3', 'COPYING.RUNTIME'] as $name) {
            $relative = 'license/gcc-runtime/' . $name;
            FileSystem::createDir(dirname($this->path($relative)));
            FileSystem::copy($licenseDirectory . '/' . $name, $this->path($relative));
            $licenses[] = $files[] = $relative;
            $hashes[$relative] = $this->digest($this->path($relative));
        }
        $this->manifest['libraries']['gcc-runtime'] = ['version' => $version, 'source_hash' => $sourceHash,
            'license' => 'GPL-3.0-or-later WITH GCC-exception-3.1', 'license_files' => $licenses,
            'files' => $files, 'file_hashes' => $hashes, 'elf' => $elf, 'depends' => [],
            'runtime_libdir' => SharedLibraryUtil::runtimeDirectory()];
        $this->ownership['libraries']['gcc-runtime'] = ['source_hash' => $sourceHash, 'files' => $files, 'file_hashes' => $hashes];
    }

    private function reconcilePending(): void
    {
        $owners = [];
        foreach ($this->manifest['libraries'] as $name => $entry) {
            foreach ($entry['elf'] as $details) {
                if ($details['soname'] !== null) {
                    if (isset($owners[$details['soname']]) && $owners[$details['soname']] !== $name) {
                        throw new WrongUsageException('Conflicting SDK SONAME: ' . $details['soname']);
                    }
                    $owners[$details['soname']] = $name;
                }
            }
        }
        $retired = [];
        foreach ($this->previous['elf'] ?? [] as $details) {
            if ($details['soname'] !== null && !isset($owners[$details['soname']])) {
                $retired[$details['soname']] = true;
            }
        }
        foreach ($this->manifest['libraries'] as $name => $entry) {
            $pending = $this->manifest['pending_rebuilds'][$name] ?? [];
            foreach ($entry['elf'] as $details) {
                foreach ($details['needed'] as $needed) {
                    if (isset($retired[$needed])) {
                        $pending[$needed] = $this->building;
                    }
                }
            }
            $needed = array_merge(...array_map(static fn (array $details): array => $details['needed'], array_values($entry['elf'])));
            foreach ($pending as $soname => $provider) {
                if (!in_array($soname, $needed, true) || isset($owners[$soname])) {
                    unset($pending[$soname]);
                }
            }
            if ($pending === []) {
                unset($this->manifest['pending_rebuilds'][$name]);
            } else {
                $this->manifest['pending_rebuilds'][$name] = $pending;
            }
        }
        if (empty($this->manifest['pending_rebuilds'])) {
            unset($this->manifest['pending_rebuilds']);
        }
    }

    private function sourceVersion(LibraryPackage $package, array $source): string
    {
        $version = $source['version'];
        if (preg_match('/^(?:v|[A-Za-z][A-Za-z0-9_.-]*?[-_]v?)?([0-9][A-Za-z0-9.+~_-]*)$/D', $version, $match)) {
            return $match[1];
        }
        if (($source['cache_type'] ?? null) === 'git') {
            $directory = $source['dirname'] ?? '';
            $download = FileSystem::isRelativePath($directory) ? DOWNLOAD_PATH . '/' . $directory : $directory;
            foreach ([$download, $package->getSourceDir()] as $repository) {
                if (!is_dir($repository . '/.git')) {
                    continue;
                }
                $process = new Process(['git', '-C', $repository, 'log', '-1', '--format=%ct']);
                $process->mustRun();
                $timestamp = trim($process->getOutput());
                if (ctype_digit($timestamp)) {
                    return '0.0.0+git' . gmdate('YmdHis', (int) $timestamp) . '.' . substr($source['hash'], 0, 12);
                }
            }
        }
        throw new WrongUsageException('Cannot determine a reproducible library version: ' . $package->getName());
    }

    private function source(LibraryPackage $package): array
    {
        return ApplicationContext::get(ArtifactCache::class)->getSourceInfo($package->getArtifact()?->getName() ?? $package->getName()) ?? [];
    }

    private function snapshot(): array
    {
        $result = [];
        if (!is_dir(BUILD_ROOT_PATH)) {
            return $result;
        }
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(BUILD_ROOT_PATH, \FilesystemIterator::SKIP_DOTS)) as $file) {
            $relative = substr($file->getPathname(), strlen(BUILD_ROOT_PATH) + 1);
            if (in_array($relative, ['shared-libraries.json', '.shared-library-ownership.json'], true)
                || str_starts_with($relative, '.shared-library-')) {
                continue;
            }
            if ($file->isFile() || $file->isLink()) {
                $result[$relative] = [$this->digest($file->getPathname()), $file->getInode(), $file->getMTime(), $file->getPerms()];
            }
        }
        ksort($result);
        return $result;
    }

    private function digest(string $path): string
    {
        return is_link($path) ? hash('sha256', 'symlink:' . readlink($path)) : hash_file('sha256', $path);
    }

    private function path(string $relative, bool $allowDangling = false): string
    {
        if ($relative === '' || str_starts_with($relative, '/') || str_contains($relative, '\\')
            || in_array('..', explode('/', $relative), true) || in_array('.', explode('/', $relative), true)) {
            throw new WrongUsageException('Unsafe shared SDK path: ' . $relative);
        }
        $path = BUILD_ROOT_PATH . '/' . $relative;
        $parent = realpath(dirname($path));
        $root = realpath(BUILD_ROOT_PATH);
        if ($parent !== false && $root !== false && $parent !== $root && !str_starts_with($parent, $root . '/')) {
            throw new WrongUsageException('Escaping shared SDK path: ' . $relative);
        }
        if (!$allowDangling && is_link($path) && (($target = realpath($path)) === false || !str_starts_with($target, $root . '/'))) {
            throw new WrongUsageException('Escaping or broken shared SDK symlink: ' . $relative);
        }
        return $path;
    }

    private function elf(string $path): array
    {
        $process = new Process([getenv('READELF') ?: 'readelf', '--wide', '--dynamic', $path]);
        $process->mustRun();
        $output = $process->getOutput();
        preg_match('/\(SONAME\).*\[([^\]]+)\]/', $output, $soname);
        preg_match_all('/\(NEEDED\).*\[([^\]]+)\]/', $output, $needed);
        preg_match('/\(RUNPATH\).*\[([^\]]*)\]/', $output, $runpath);
        preg_match('/\(RPATH\).*\[([^\]]*)\]/', $output, $rpath);
        return ['soname' => $soname[1] ?? null, 'needed' => $needed[1],
            'runpath' => isset($runpath[1]) ? explode(':', $runpath[1]) : [],
            'rpath' => isset($rpath[1]) ? explode(':', $rpath[1]) : []];
    }

    private function read(string $name): ?array
    {
        if (!is_file(BUILD_ROOT_PATH . '/' . $name)) {
            return null;
        }
        return json_decode(FileSystem::readFile(BUILD_ROOT_PATH . '/' . $name), true, flags: JSON_THROW_ON_ERROR);
    }

    private function save(): void
    {
        FileSystem::createDir(BUILD_ROOT_PATH);
        foreach (['shared-libraries.json' => $this->manifest, '.shared-library-ownership.json' => $this->ownership] as $name => $data) {
            $data['libraries'] = (object) $data['libraries'];
            $temporary = tempnam(BUILD_ROOT_PATH, '.shared-library-');
            try {
                if (file_put_contents($temporary, json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n") === false
                    || !rename($temporary, BUILD_ROOT_PATH . '/' . $name)) {
                    throw new WrongUsageException('Cannot persist shared SDK manifest');
                }
            } finally {
                if (is_file($temporary)) {
                    unlink($temporary);
                }
            }
        }
    }
}
