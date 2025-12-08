<?php

declare(strict_types=1);

namespace Package\Artifact;

use StaticPHP\Artifact\ArtifactDownloader;
use StaticPHP\Artifact\Downloader\DownloadResult;
use StaticPHP\Attribute\Artifact\AfterBinaryExtract;
use StaticPHP\Attribute\Artifact\CustomBinary;
use StaticPHP\Exception\DownloaderException;
use StaticPHP\Runtime\SystemTarget;
use StaticPHP\Util\GlobalEnvManager;
use StaticPHP\Util\System\LinuxUtil;

class go_xcaddy
{
    #[CustomBinary('go-xcaddy', [
        'linux-x86_64',
        'linux-aarch64',
        'macos-x86_64',
        'macos-aarch64',
    ])]
    public function downBinary(ArtifactDownloader $downloader): DownloadResult
    {
        $pkgroot = PKG_ROOT_PATH;
        $name = SystemTarget::getCurrentPlatformString();
        $arch = match (explode('-', $name)[1]) {
            'x86_64' => 'amd64',
            'aarch64' => 'arm64',
            default => throw new DownloaderException('Unsupported architecture: ' . $name),
        };
        $os = match (explode('-', $name)[0]) {
            'linux' => 'linux',
            'macos' => 'darwin',
            default => throw new DownloaderException('Unsupported OS: ' . $name),
        };

        // get version and hash
        [$version] = explode("\n", default_shell()->executeCurl('https://go.dev/VERSION?m=text') ?: '');
        if ($version === '') {
            throw new DownloaderException('Failed to get latest Go version from https://go.dev/VERSION?m=text');
        }
        $page = default_shell()->executeCurl('https://go.dev/dl/');
        if ($page === '' || $page === false) {
            throw new DownloaderException('Failed to get Go download page from https://go.dev/dl/');
        }

        $version_regex = str_replace('.', '\.', $version);
        $pattern = "/href=\"\\/dl\\/{$version_regex}\\.{$os}-{$arch}\\.tar\\.gz\">.*?<tt>([a-f0-9]{64})<\\/tt>/s";
        if (preg_match($pattern, $page, $matches)) {
            $hash = $matches[1];
        } else {
            throw new DownloaderException("Failed to find download hash for Go {$version} {$os}-{$arch}");
        }

        $url = "https://go.dev/dl/{$version}.{$os}-{$arch}.tar.gz";
        $path = DOWNLOAD_PATH . DIRECTORY_SEPARATOR . "{$version}.{$os}-{$arch}.tar.gz";
        default_shell()->executeCurlDownload($url, $path, retries: $downloader->getRetry());
        // verify hash
        $file_hash = hash_file('sha256', $path);
        if ($file_hash !== $hash) {
            throw new DownloaderException("Hash mismatch for downloaded go-xcaddy binary. Expected {$hash}, got {$file_hash}");
        }
        return DownloadResult::archive(basename($path), ['url' => $url, 'version' => $version], extract: "{$pkgroot}/go-xcaddy", verified: true, version: $version);
    }

    #[AfterBinaryExtract('go-xcaddy', [
        'linux-x86_64',
        'linux-aarch64',
        'macos-x86_64',
        'macos-aarch64',
    ])]
    public function afterExtract(string $target_path): void
    {
        if (file_exists("{$target_path}/bin/go") && file_exists("{$target_path}/bin/xcaddy")) {
            return;
        }

        $sanitizedPath = getenv('PATH');
        if (PHP_OS_FAMILY === 'Linux' && !LinuxUtil::isMuslDist()) {
            $sanitizedPath = preg_replace('#(:?/?[^:]*musl[^:]*)#', '', $sanitizedPath);
            $sanitizedPath = preg_replace('#^:|:$|::#', ':', $sanitizedPath); // clean up colons
        }

        shell()->appendEnv([
            'PATH' => "{$target_path}/bin:{$sanitizedPath}",
            'GOROOT' => "{$target_path}",
            'GOBIN' => "{$target_path}/bin",
            'GOPATH' => "{$target_path}/go",
        ])->exec('CC=cc go install github.com/caddyserver/xcaddy/cmd/xcaddy@latest');
        GlobalEnvManager::addPathIfNotExists("{$target_path}/bin");
    }
}
