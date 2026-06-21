<?php

declare(strict_types=1);

namespace Package\Artifact;

use StaticPHP\Artifact\ArtifactDownloader;
use StaticPHP\Artifact\Downloader\DownloadResult;
use StaticPHP\Attribute\Artifact\AfterBinaryExtract;
use StaticPHP\Attribute\Artifact\BinaryExtract;
use StaticPHP\Attribute\Artifact\CustomBinary;
use StaticPHP\Exception\DownloaderException;
use StaticPHP\Util\FileSystem;
use StaticPHP\Util\GlobalEnvManager;

class msys2_build_essentials
{
    // MSYS subsystem packages required for autotools-based builds.
    private const REQUIRED_PACKAGES = ['make', 'autoconf', 'automake', 'libtool', 'pkgconf', 'perl', 'bison', 're2c'];

    #[CustomBinary('msys2-build-essentials', ['windows-x86_64'])]
    public function downBinary(ArtifactDownloader $downloader): DownloadResult
    {
        // MSYS2 nightly self-extracting archive; running it with `-y -oTARGET` extracts to TARGET\msys64\.
        $url = 'https://github.com/msys2/msys2-installer/releases/download/nightly-x86_64/msys2-base-x86_64-latest.sfx.exe';
        $filename = 'msys2-base-x86_64-latest.sfx.exe';
        $path = DOWNLOAD_PATH . DIRECTORY_SEPARATOR . $filename;

        default_shell()->executeCurlDownload($url, $path, retries: $downloader->getRetry());

        return DownloadResult::file(
            $filename,
            ['url' => $url, 'version' => 'nightly'],
            version: 'nightly',
            extract: '{pkg_root_path}/msys2-build-essentials',
        );
    }

    #[BinaryExtract('msys2-build-essentials', ['windows-x86_64'])]
    public function extractBinary(string $source_file, string $target_path): void
    {
        $target_path = FileSystem::convertPath($target_path);
        $source_file = FileSystem::convertPath($source_file);

        // Guard: skip re-extraction if already initialized (marker written at end of this method).
        $marker = "{$target_path}\\.spc-msys2-initialized";
        if (file_exists($marker)) {
            return;
        }

        if (!is_dir($target_path)) {
            FileSystem::createDir($target_path);
        }

        cmd()->exec("\"{$source_file}\" -y -o\"{$target_path}\"");

        $msys2_bin = "{$target_path}\\msys64\\usr\\bin";
        if (!file_exists("{$msys2_bin}\\bash.exe")) {
            throw new DownloaderException("MSYS2 extraction failed: bash.exe not found at {$msys2_bin}\\bash.exe");
        }

        // Add MSYS2 usr\bin to PATH so pacman.exe can load msys-2.0.dll.
        GlobalEnvManager::addPathIfNotExists($msys2_bin);
        GlobalEnvManager::putenv('CHERE_INVOKING=yes');
        GlobalEnvManager::putenv('MSYSTEM=MSYS');

        // Disable PGP signature checking: pacman-key --init requires a pseudo-TTY which is unavailable
        // from PHP. Patching pacman.conf is the standard approach for CI pipelines.
        $pacman_conf = "{$target_path}\\msys64\\etc\\pacman.conf";
        FileSystem::replaceFileRegex($pacman_conf, '/^SigLevel\s*=.*$/m', 'SigLevel = Never');

        $pacman = "{$target_path}\\msys64\\usr\\bin\\pacman.exe";

        // Two-pass update as recommended by MSYS2 CI docs.
        cmd()->exec("\"{$pacman}\" --noconfirm -Syuu");
        cmd()->exec("\"{$pacman}\" --noconfirm -Syuu");

        $pkgs = implode(' ', self::REQUIRED_PACKAGES);
        cmd()->exec("\"{$pacman}\" --noconfirm -S --needed {$pkgs}");

        FileSystem::writeFile($marker, date('Y-m-d H:i:s'));
    }

    #[AfterBinaryExtract('msys2-build-essentials', ['windows-x86_64'])]
    public function afterExtract(string $target_path): void
    {
        $target_path = FileSystem::convertPath($target_path);
        $msys2_root = "{$target_path}\\msys64";

        GlobalEnvManager::putenv("SPC_MSYS2_PATH={$msys2_root}");
        GlobalEnvManager::addPathIfNotExists("{$msys2_root}\\usr\\bin");
    }
}
