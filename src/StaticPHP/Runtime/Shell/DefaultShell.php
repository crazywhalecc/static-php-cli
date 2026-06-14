<?php

declare(strict_types=1);

namespace StaticPHP\Runtime\Shell;

use StaticPHP\Exception\ExecutionException;
use StaticPHP\Exception\InterruptException;
use StaticPHP\Exception\SPCInternalException;
use StaticPHP\Runtime\SystemTarget;
use StaticPHP\Util\FileSystem;

/**
 * A default shell implementation that does not support custom commands.
 * Used as a internal command caller when some place needs os-irrelevant shell.
 */
class DefaultShell extends Shell
{
    /**
     * @internal
     */
    public function exec(string $cmd): static
    {
        throw new SPCInternalException('DefaultShell does not support custom command execution.');
    }

    /**
     * Execute a cURL command to fetch data from a URL.
     */
    public function executeCurl(string $url, string $method = 'GET', array $headers = [], array $hooks = [], int $retries = 0, bool $compressed = false): false|string
    {
        foreach ($hooks as $hook) {
            $hook($method, $url, $headers);
        }
        $url_arg = escapeshellarg($url);

        $method_arg = match ($method) {
            'GET' => '',
            'HEAD' => '-I',
            default => "-X {$method}",
        };
        $header_arg = implode(' ', array_map(fn ($v) => '"-H' . $v . '"', $headers));
        $retry_arg = $retries > 0 ? "--retry {$retries}" : '';
        $compressed_arg = $compressed ? '--compressed' : '';
        $cmd = SPC_CURL_EXEC . " -sfSL --max-time 3600 {$retry_arg} {$compressed_arg} {$method_arg} {$header_arg} {$url_arg}";

        $this->logCommandInfo($cmd);
        logger()->debug("[CURL EXECUTE] {$cmd}");
        $result = $this->passthru($cmd, capture_output: true, throw_on_error: false);
        $ret = $result['code'];
        $output = $result['output'];
        if ($ret !== 0) {
            logger()->debug("[CURL ERROR] Command exited with code: {$ret}");
        }
        if ($ret === 2 || $ret === -1073741510) {
            throw new InterruptException(sprintf('Canceled fetching "%s"', $url));
        }
        if ($ret !== 0) {
            return false;
        }

        return trim($output);
    }

    /**
     * Execute a cURL command to download a file from a URL.
     */
    public function executeCurlDownload(string $url, string $path, array $headers = [], array $hooks = [], int $retries = 0): void
    {
        foreach ($hooks as $hook) {
            $hook('GET', $url, $headers);
        }
        $url_arg = escapeshellarg($url);
        $path_arg = escapeshellarg($path);

        $header_arg = implode(' ', array_map(fn ($v) => '"-H' . $v . '"', $headers));
        $retry_arg = $retries > 0 ? "--retry {$retries}" : '';
        $check = $this->console_putput ? '#' : 's';
        $cmd = clean_spaces(SPC_CURL_EXEC . " -{$check}fSL --max-time 3600 {$retry_arg} {$header_arg} -o {$path_arg} {$url_arg}");
        $this->logCommandInfo($cmd);
        logger()->debug('[CURL DOWNLOAD] ' . $cmd);
        $this->passthru($cmd, $this->console_putput, capture_output: false, throw_on_error: true);
    }

    /**
     * Execute a Git clone command to clone a repository.
     */
    public function executeGitClone(string $url, string $branch, string $path, bool $shallow = true, ?array $submodules = null, int $retries = 0): void
    {
        $path = FileSystem::convertPath($path);
        if (file_exists($path)) {
            FileSystem::removeDir($path);
        }
        $git = SPC_GIT_EXEC;
        $url_arg = escapeshellarg($url);
        $branch_arg = escapeshellarg($branch);
        $path_arg = escapeshellarg($path);
        $shallow_arg = $shallow ? '--depth 1 --single-branch' : '';
        $submodules_arg = ($submodules === null && $shallow) ? '--recursive --shallow-submodules' : ($submodules === null ? '--recursive' : '');
        $cmd = clean_spaces("{$git} clone -c http.lowSpeedLimit=1 -c http.lowSpeedTime=3600 --config core.autocrlf=false --branch {$branch_arg} {$shallow_arg} {$submodules_arg} {$url_arg} {$path_arg}");
        $this->logCommandInfo($cmd);
        logger()->debug("[GIT CLONE] {$cmd}");
        try {
            $this->passthru($cmd, $this->console_putput);
        } catch (InterruptException $e) {
            throw $e;
        } catch (\Throwable $e) {
            if ($retries > 0) {
                logger()->warning("Git clone failed, retrying... ({$retries} retries left)");
                if (is_dir($path)) {
                    FileSystem::removeDir($path);
                }
                $this->executeGitClone($url, $branch, $path, $shallow, $submodules, $retries - 1);
                return;
            }
            throw $e;
        }
        if ($submodules !== null) {
            $depth_flag = $shallow ? '--depth 1' : '';
            foreach ($submodules as $submodule) {
                $submodule = escapeshellarg($submodule);
                $submodule_cmd = clean_spaces("{$git} submodule update --init {$depth_flag} {$submodule}");
                $this->logCommandInfo($submodule_cmd);
                logger()->debug("[GIT SUBMODULE] {$submodule_cmd}");
                $this->passthru($submodule_cmd, $this->console_putput, cwd: $path);
            }
        }
    }

    /**
     * Execute a tar command to extract an archive.
     *
     * @param string $archive_path Path to the archive file
     * @param string $target_path  Path to extract to
     * @param string $compression  Compression type: 'gz', 'bz2', 'xz', or 'none'
     * @param int    $strip        Number of leading components to strip (default: 1)
     */
    public function executeTarExtract(string $archive_path, string $target_path, string $compression, int $strip = 1): bool
    {
        $archive_arg = escapeshellarg(FileSystem::convertPath($archive_path));
        $target_arg = escapeshellarg(FileSystem::convertPath($target_path));

        $compression_flag = match ($compression) {
            'gz' => '-z',
            'bz2' => '-j',
            'xz' => '-J',
            'none' => '',
            default => throw new SPCInternalException("Unknown compression type: {$compression}"),
        };

        $mute = $this->console_putput ? '' : ' 2>/dev/null';
        $tar = SystemTarget::isUnix() ? 'tar' : '"C:\Windows\system32\tar.exe"';
        $cmd = "{$tar} {$compression_flag}xf {$archive_arg} --strip-components {$strip} -C {$target_arg}{$mute}";

        $this->logCommandInfo($cmd);
        logger()->debug("[TAR EXTRACT] {$cmd}");
        $this->passthruTolerateSymlinks($cmd);
        return true;
    }

    /**
     * Execute an unzip command to extract a zip archive.
     *
     * @param string $zip_path    Path to the zip file
     * @param string $target_path Path to extract to
     */
    public function executeUnzip(string $zip_path, string $target_path): void
    {
        $zip_arg = escapeshellarg(FileSystem::convertPath($zip_path));
        $target_arg = escapeshellarg(FileSystem::convertPath($target_path));

        $mute = $this->console_putput ? '' : ' > /dev/null';
        $cmd = "unzip {$zip_arg} -d {$target_arg}{$mute}";

        $this->logCommandInfo($cmd);
        logger()->debug("[UNZIP] {$cmd}");
        $this->passthru($cmd, $this->console_putput);
    }

    /**
     * Execute a 7za command to extract an archive (Windows).
     *
     * @param string $archive_path Path to the archive file
     * @param string $target_path  Path to extract to
     */
    public function execute7zExtract(string $archive_path, string $target_path): bool
    {
        $sdk_path = getenv('PHP_SDK_PATH');
        if ($sdk_path === false) {
            throw new SPCInternalException('PHP_SDK_PATH environment variable is not set');
        }

        $_7z = escapeshellarg(FileSystem::convertPath($sdk_path . '/bin/7za.exe'));
        $archive_arg = escapeshellarg(FileSystem::convertPath($archive_path));
        $target_arg = escapeshellarg(FileSystem::convertPath($target_path));

        $mute = $this->console_putput ? '' : ' > NUL';

        $run = function ($cmd) {
            $this->logCommandInfo($cmd);
            logger()->debug("[7Z EXTRACT] {$cmd}");
            $this->passthruTolerateSymlinks($cmd);
        };

        $extname = FileSystem::extname($archive_path);
        $tar = SystemTarget::isUnix() ? 'tar' : '"C:\Windows\system32\tar.exe"';

        match ($extname) {
            'tar' => $this->executeTarExtract($archive_path, $target_path, 'none'),
            'gz', 'tgz', 'xz', 'txz', 'bz2' => $run("{$_7z} x -so {$archive_arg} | {$tar} -f - -x -C {$target_arg} --strip-components 1"),
            default => $run("{$_7z} x {$archive_arg} -o{$target_arg} -y{$mute}"),
        };

        return true;
    }

    /**
     * Run an extraction command, tolerating symbolic links that the host cannot create.
     *
     * Windows tar.exe (bsdtar) cannot create the symbolic links some archives ship (e.g. zstd's
     * tests/cli-tests/bin/unzstd -> zstd), failing each with "Can't create '...': Invalid argument"
     * and exiting non-zero. Those entries are never needed to build, so on Windows we swallow a
     * failure whose only errors are such symlink creations and continue. Any other failure still throws.
     */
    private function passthruTolerateSymlinks(string $cmd): void
    {
        // Symlink creation only fails on a Windows host; elsewhere extraction handles symlinks fine.
        if (PHP_OS_FAMILY !== 'Windows') {
            $this->passthru($cmd, $this->console_putput);
            return;
        }

        $result = $this->passthru($cmd, $this->console_putput, capture_output: true, throw_on_error: false);
        if ($result['code'] === 0) {
            return;
        }
        if ($this->isSymlinkOnlyExtractFailure($result['output'])) {
            logger()->warning('Some symbolic links could not be created during extraction and were skipped (not supported on this Windows host). This is harmless for building.');
            return;
        }
        throw new ExecutionException(
            cmd: $cmd,
            message: "Command exited with non-zero code: {$result['code']}",
            code: $result['code'],
            cd: $this->cd,
            env: $this->env,
        );
    }

    /**
     * Decide whether an extraction failure was caused solely by symbolic links that could not be
     * created on Windows. Returns true only when at least one such error is present and no other
     * error-looking output is found, so genuine extraction failures still propagate.
     */
    private function isSymlinkOnlyExtractFailure(string $output): bool
    {
        $saw_symlink_error = false;
        foreach (preg_split('/\r\n|\r|\n/', $output) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            // bsdtar's trailing summary line; not an error on its own.
            if (str_contains($line, 'Error exit delayed from previous errors')) {
                continue;
            }
            // The symlink (or other unsupported special file) that Windows refused to create.
            if (str_contains($line, "Can't create") && str_contains($line, 'Invalid argument')) {
                $saw_symlink_error = true;
                continue;
            }
            // Any other error-looking line means this was not a clean symlink-only failure.
            if (preg_match('/\berror\b|cannot|can\'t|failed|denied|no space|not permitted/i', $line)) {
                return false;
            }
        }
        return $saw_symlink_error;
    }
}
