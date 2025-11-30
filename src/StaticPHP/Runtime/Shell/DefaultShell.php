<?php

declare(strict_types=1);

namespace StaticPHP\Runtime\Shell;

use StaticPHP\Exception\InterruptException;
use StaticPHP\Exception\SPCInternalException;
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
    public function executeCurl(string $url, string $method = 'GET', array $headers = [], array $hooks = [], int $retries = 0): false|string
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
        $cmd = SPC_CURL_EXEC . " -sfSL {$retry_arg} {$method_arg} {$header_arg} {$url_arg}";

        $this->logCommandInfo($cmd);
        $result = $this->passthru($cmd, console_output: false, capture_output: true, throw_on_error: false);
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
        $cmd = clean_spaces(SPC_CURL_EXEC . " -{$check}fSL {$retry_arg} {$header_arg} -o {$path_arg} {$url_arg}");
        $this->logCommandInfo($cmd);
        logger()->debug('[CURL DOWNLOAD] ' . $cmd);
        $this->passthru($cmd, $this->console_putput, capture_output: false, throw_on_error: true);
    }

    /**
     * Execute a Git clone command to clone a repository.
     */
    public function executeGitClone(string $url, string $branch, string $path, bool $shallow = true, ?array $submodules = null): void
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
        $cmd = clean_spaces("{$git} clone --config core.autocrlf=false --branch {$branch_arg} {$shallow_arg} {$submodules_arg} {$url_arg} {$path_arg}");
        $this->logCommandInfo($cmd);
        logger()->debug("[GIT CLONE] {$cmd}");
        $this->passthru($cmd, $this->console_putput, capture_output: false, throw_on_error: true);
        if ($submodules !== null) {
            $depth_flag = $shallow ? '--depth 1' : '';
            foreach ($submodules as $submodule) {
                $submodule = escapeshellarg($submodule);
                $submodule_cmd = clean_spaces("cd {$path_arg} && {$git} submodule update --init {$depth_flag} {$submodule}");
                $this->logCommandInfo($submodule_cmd);
                logger()->debug("[GIT SUBMODULE] {$submodule_cmd}");
                $this->passthru($submodule_cmd, $this->console_putput, capture_output: false, throw_on_error: true);
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
    public function executeTarExtract(string $archive_path, string $target_path, string $compression, int $strip = 1): void
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
        $cmd = "tar {$compression_flag}xf {$archive_arg} --strip-components {$strip} -C {$target_arg}{$mute}";

        $this->logCommandInfo($cmd);
        logger()->debug("[TAR EXTRACT] {$cmd}");
        $this->passthru($cmd, $this->console_putput, capture_output: false, throw_on_error: true);
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
        $this->passthru($cmd, $this->console_putput, capture_output: false, throw_on_error: true);
    }

    /**
     * Execute a 7za command to extract an archive (Windows).
     *
     * @param string $archive_path Path to the archive file
     * @param string $target_path  Path to extract to
     * @param bool   $is_txz       Whether this is a .txz/.tar.xz file that needs double extraction
     */
    public function execute7zExtract(string $archive_path, string $target_path, bool $is_txz = false): void
    {
        $sdk_path = getenv('PHP_SDK_PATH');
        if ($sdk_path === false) {
            throw new SPCInternalException('PHP_SDK_PATH environment variable is not set');
        }

        $_7z = escapeshellarg(FileSystem::convertPath($sdk_path . '/bin/7za.exe'));
        $archive_arg = escapeshellarg(FileSystem::convertPath($archive_path));
        $target_arg = escapeshellarg(FileSystem::convertPath($target_path));

        $mute = $this->console_putput ? '' : ' > NUL';

        if ($is_txz) {
            // txz/tar.xz contains a tar file inside, extract twice
            $cmd = "{$_7z} x {$archive_arg} -so | {$_7z} x -si -ttar -o{$target_arg} -y{$mute}";
        } else {
            $cmd = "{$_7z} x {$archive_arg} -o{$target_arg} -y{$mute}";
        }

        $this->logCommandInfo($cmd);
        logger()->debug("[7Z EXTRACT] {$cmd}");
        $this->passthru($cmd, $this->console_putput, capture_output: false, throw_on_error: true);
    }
}
