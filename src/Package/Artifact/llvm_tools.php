<?php

declare(strict_types=1);

namespace Package\Artifact;

use StaticPHP\Artifact\ArtifactDownloader;
use StaticPHP\Artifact\Downloader\DownloadResult;
use StaticPHP\Artifact\Downloader\Type\CheckUpdateResult;
use StaticPHP\Artifact\Downloader\Type\GitHubRelease;
use StaticPHP\Artifact\Downloader\Type\GitHubTokenSetupTrait;
use StaticPHP\Attribute\Artifact\CustomBinary;
use StaticPHP\Attribute\Artifact\CustomSource;
use StaticPHP\Attribute\Artifact\CustomSourceCheckUpdate;
use StaticPHP\Runtime\SystemTarget;

class llvm_tools
{
    use GitHubTokenSetupTrait;

    #[CustomSource('llvm-tools')]
    public function downSource(ArtifactDownloader $downloader): DownloadResult
    {
        $version = zig::getLlvmVersion();
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
    public function downBinary(ArtifactDownloader $downloader): DownloadResult
    {
        $asset = 'llvm-tools-' . SystemTarget::getTargetArch() . '-linux-musl-' . zig::getLlvmVersion() . '.txz';
        return new GitHubRelease()->download('llvm-tools', [
            'type' => 'ghrel',
            'repo' => 'static-php/hosted',
            'match' => preg_quote($asset, '|'),
            'extract' => '{pkg_root_path}/llvm-tools',
        ], $downloader);
    }

    #[CustomSourceCheckUpdate('llvm-tools')]
    public function checkUpdateSource(?string $old_version): CheckUpdateResult
    {
        $version = zig::getLlvmVersion();
        return new CheckUpdateResult(old: $old_version, new: $version, needUpdate: $old_version !== $version);
    }
}
