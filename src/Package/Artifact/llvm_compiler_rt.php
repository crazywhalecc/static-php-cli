<?php

declare(strict_types=1);

namespace Package\Artifact;

use StaticPHP\Artifact\ArtifactDownloader;
use StaticPHP\Artifact\Downloader\DownloadResult;
use StaticPHP\Artifact\Downloader\Type\CheckUpdateResult;
use StaticPHP\Artifact\Downloader\Type\GitHubTokenSetupTrait;
use StaticPHP\Attribute\Artifact\CustomSource;
use StaticPHP\Attribute\Artifact\CustomSourceCheckUpdate;
use StaticPHP\Exception\DownloaderException;
use StaticPHP\Toolchain\ToolchainManager;
use StaticPHP\Toolchain\ZigToolchain;

class llvm_compiler_rt
{
    use GitHubTokenSetupTrait;

    #[CustomSource('llvm-compiler-rt')]
    public function downSource(ArtifactDownloader $downloader): DownloadResult
    {
        $clang = $this->clangVersion();
        $filename = "compiler-rt-{$clang}.src.tar.xz";
        $url = sprintf('https://dl.static-php.dev/v3/deps/compiler-rt/compiler-rt-%s.src.tar.xz', $clang);
        default_shell()->executeCurlDownload(
            $url,
            DOWNLOAD_PATH . DIRECTORY_SEPARATOR . $filename,
            headers: $this->getGitHubTokenHeaders(),
            retries: $downloader->getRetry(),
        );
        return DownloadResult::archive($filename, ['url' => $url], version: $clang);
    }

    #[CustomSourceCheckUpdate('llvm-compiler-rt')]
    public function checkUpdateSource(?string $old_version): CheckUpdateResult
    {
        $version = $this->clangVersion();
        return new CheckUpdateResult(old: $old_version, new: $version, needUpdate: $old_version !== $version);
    }

    private function clangVersion(): string
    {
        if (!($toolchain = ToolchainManager::getToolchain()) instanceof ZigToolchain) {
            throw new DownloaderException('Cannot determine the toolchain type.');
        }
        return $toolchain->getCompilerVersion()
            ?? throw new DownloaderException('Cannot determine the compiler version of zig, please check your zig installation.');
    }
}
