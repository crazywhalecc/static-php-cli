<?php

declare(strict_types=1);

namespace Package\Artifact;

use StaticPHP\Artifact\Artifact;
use StaticPHP\Artifact\ArtifactDownloader;
use StaticPHP\Artifact\Downloader\DownloadResult;
use StaticPHP\Artifact\Downloader\Type\CheckUpdateResult;
use StaticPHP\Artifact\Downloader\Type\GitHubTokenSetupTrait;
use StaticPHP\Attribute\Artifact\CustomBinary;
use StaticPHP\Attribute\Artifact\CustomSource;
use StaticPHP\Attribute\Artifact\CustomSourceCheckUpdate;
use StaticPHP\Exception\DownloaderException;
use StaticPHP\Runtime\SystemTarget;
use StaticPHP\Toolchain\ToolchainManager;

class llvm_tools
{
    use GitHubTokenSetupTrait;

    #[CustomSource('llvm-tools')]
    public function downSource(ArtifactDownloader $downloader): DownloadResult
    {
        $version = $this->clangVersion();
        $filename = "llvm-project-{$version}.src.tar.xz";
        $url = "https://github.com/llvm/llvm-project/releases/download/llvmorg-{$version}/{$filename}";
        default_shell()->executeCurlDownload(
            $url,
            DOWNLOAD_PATH . DIRECTORY_SEPARATOR . $filename,
            headers: $this->getGitHubTokenHeaders(),
            retries: $downloader->getRetry(),
        );
        return DownloadResult::archive($filename, ['url' => $url], version: $version);
    }

    #[CustomBinary('llvm-tools', [
        'linux-x86_64',
        'linux-aarch64',
    ])]
    public function downBinary(ArtifactDownloader $downloader, Artifact $artifact): DownloadResult
    {
        // The tools run on the build host, so the host architecture selects the archive, not
        // SPC_TARGET; they are static musl executables and run on any Linux distribution.
        $clang = $this->clangVersion();
        // Pre-built archives: llvm-tools-{host-arch}-linux-musl-{clang-version}.txz plus a .sha256 sidecar.
        $filename = 'llvm-tools-' . GNU_ARCH . "-linux-musl-{$clang}.txz";
        $base_url = $artifact->getBinaryDownloadConfig()['base-url'] ?? null;
        if (!is_string($base_url) || $base_url === '') {
            throw new DownloaderException('llvm-tools: binary config for ' . SystemTarget::getCurrentPlatformString() . ' does not define a base-url');
        }
        $url = $base_url . $filename;
        $path = DOWNLOAD_PATH . DIRECTORY_SEPARATOR . $filename;
        default_shell()->executeCurlDownload($url, $path, retries: $downloader->getRetry());

        // Published next to the archive by the llvm-tools workflow of static-php/hosted.
        $checksum = default_shell()->executeCurl("{$url}.sha256", retries: $downloader->getRetry());
        $expected = is_string($checksum) ? strtolower((string) strtok(trim($checksum), " \t")) : '';
        if (preg_match('/^[0-9a-f]{64}$/', $expected) !== 1) {
            throw new DownloaderException("Cannot fetch the checksum of {$filename} from {$url}.sha256");
        }
        if (($actual = hash_file('sha256', $path)) !== $expected) {
            unlink($path);
            throw new DownloaderException("Hash mismatch for downloaded {$filename}. Expected {$expected}, got {$actual}");
        }
        return DownloadResult::archive($filename, ['url' => $url], extract: '{pkg_root_path}/llvm-tools', verified: true, version: $clang);
    }

    #[CustomSourceCheckUpdate('llvm-tools')]
    public function checkUpdateSource(?string $old_version): CheckUpdateResult
    {
        $version = $this->clangVersion();
        return new CheckUpdateResult(old: $old_version, new: $version, needUpdate: $old_version !== $version);
    }

    /**
     * The clang version of the active toolchain, used as the key for the LLVM assets these
     * artifacts download. Resolved through the toolchain so an artifact no longer has to know
     * anything about the zig package.
     */
    private function clangVersion(): string
    {
        return ToolchainManager::getToolchain()?->getCompilerVersion()
            ?? throw new DownloaderException('Cannot determine the clang version of the active toolchain; install the zig toolchain first (`bin/spc doctor`)');
    }
}
