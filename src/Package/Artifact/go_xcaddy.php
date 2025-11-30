<?php

declare(strict_types=1);

namespace Package\Artifact;

use SPC\util\GlobalEnvManager;
use StaticPHP\Artifact\ArtifactDownloader;
use StaticPHP\Artifact\Downloader\DownloadResult;
use StaticPHP\Attribute\Artifact\AfterBinaryExtract;
use StaticPHP\Attribute\Artifact\CustomBinary;
use StaticPHP\Exception\ValidationException;
use StaticPHP\Runtime\SystemTarget;
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
            default => throw new ValidationException('Unsupported architecture: ' . $name),
        };
        $os = match (explode('-', $name)[0]) {
            'linux' => 'linux',
            'macos' => 'darwin',
            default => throw new ValidationException('Unsupported OS: ' . $name),
        };
        $hash = match ("{$os}-{$arch}") {
            'linux-amd64' => '2852af0cb20a13139b3448992e69b868e50ed0f8a1e5940ee1de9e19a123b613',
            'linux-arm64' => '05de75d6994a2783699815ee553bd5a9327d8b79991de36e38b66862782f54ae',
            'darwin-amd64' => '5bd60e823037062c2307c71e8111809865116714d6f6b410597cf5075dfd80ef',
            'darwin-arm64' => '544932844156d8172f7a28f77f2ac9c15a23046698b6243f633b0a0b00c0749c',
        };
        $go_version = '1.25.0';
        $url = "https://go.dev/dl/go{$go_version}.{$os}-{$arch}.tar.gz";
        $path = DOWNLOAD_PATH . DIRECTORY_SEPARATOR . "go{$go_version}.{$os}-{$arch}.tar.gz";
        default_shell()->executeCurlDownload($url, $path, retries: $downloader->getRetry());
        // verify hash
        $file_hash = hash_file('sha256', $path);
        if ($file_hash !== $hash) {
            throw new ValidationException("Hash mismatch for downloaded go-xcaddy binary. Expected {$hash}, got {$file_hash}");
        }
        return DownloadResult::archive(basename($path), ['url' => $url, 'version' => $go_version], extract: "{$pkgroot}/go-xcaddy", verified: true, version: $go_version);
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
