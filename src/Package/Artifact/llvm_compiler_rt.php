<?php

declare(strict_types=1);

namespace Package\Artifact;

use StaticPHP\Artifact\ArtifactDownloader;
use StaticPHP\Artifact\Downloader\DownloadResult;
use StaticPHP\Artifact\Downloader\Type\CheckUpdateResult;
use StaticPHP\Artifact\Downloader\Type\GitHubTokenSetupTrait;
use StaticPHP\Attribute\Artifact\CustomSource;
use StaticPHP\Attribute\Artifact\CustomSourceCheckUpdate;

class llvm_compiler_rt
{
    use GitHubTokenSetupTrait;

    #[CustomSource('llvm-compiler-rt')]
    public function downSource(ArtifactDownloader $downloader): DownloadResult
    {
        $version = zig::getLlvmVersion();
        $filename = "compiler-rt-{$version}.src.tar.xz";
        $url = "https://github.com/llvm/llvm-project/releases/download/llvmorg-{$version}/{$filename}";
        default_shell()->executeCurlDownload(
            $url,
            DOWNLOAD_PATH . DIRECTORY_SEPARATOR . $filename,
            headers: $this->getGitHubTokenHeaders(),
            retries: $downloader->getRetry(),
        );
        return DownloadResult::archive($filename, ['url' => $url], version: $version);
    }

    #[CustomSourceCheckUpdate('llvm-compiler-rt')]
    public function checkUpdateSource(?string $old_version): CheckUpdateResult
    {
        $version = zig::getLlvmVersion();
        return new CheckUpdateResult(old: $old_version, new: $version, needUpdate: $old_version !== $version);
    }
}
