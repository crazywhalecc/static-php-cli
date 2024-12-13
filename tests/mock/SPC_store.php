<?php

declare(strict_types=1);

// mock global functions

namespace SPC\store;

use SPC\exception\RuntimeException;

function f_exec(string $command, mixed &$output, mixed &$result_code): bool
{
    $result_code = 0;
    if (str_contains($command, 'https://api.github.com/repos/AOMediaCodec/libavif/releases')) {
        $output = explode("\n", gzdecode(file_get_contents(__DIR__ . '/../assets/github_api_AOMediaCodec_libavif_releases.json.gz')));
        return true;
    }
    if (str_contains($command, 'https://api.github.com/repos/AOMediaCodec/libavif/tarball/v1.1.1')) {
        $output = explode("\n", "HTTP/1.1 200 OK\r\nContent-Disposition: attachment; filename=AOMediaCodec-libavif-v1.1.1-0-gbb24db0.tar.gz\r\n\r\n");
        return true;
    }
    if (str_contains($command, 'https://api.bitbucket.org/2.0/repositories/')) {
        $output = explode("\n", json_encode(['values' => [['name' => '1.0.0']], 'tag_name' => '1.0.0']));
        return true;
    }
    if (str_contains($command, 'https://bitbucket.org/')) {
        $output = explode("\n", str_contains($command, 'MATCHED') ? "HTTP/2 200 OK\r\ncontent-disposition: attachment; filename=abc.tar.gz\r\n\r\n" : "HTTP/2 200 OK\r\n\r\n");
        return true;
    }
    if (str_contains($command, 'ghreltest/ghrel')) {
        $output = explode("\n", json_encode([[
            'prerelease' => false,
            'assets' => [
                [
                    'name' => 'ghreltest.tar.gz',
                    'browser_download_url' => 'https://fakecmd.com/ghreltest.tar.gz',
                ],
            ],
        ]]));
        return true;
    }
    if (str_contains($command, 'filelist')) {
        $output = explode("\n", gzdecode(file_get_contents(__DIR__ . '/../assets/filelist.gz')));
        return true;
    }
    $result_code = -2;
    $output = null;
    return false;
}

function f_passthru(string $cmd): bool
{
    if (str_starts_with($cmd, 'git')) {
        if (str_contains($cmd, '--branch "SIGINT"')) {
            throw new RuntimeException('Interrupt', 2);
        }
        return true;
    }
    if (str_contains($cmd, 'https://fakecmd.com/curlDown')) {
        if (str_contains($cmd, 'SIGINT')) {
            throw new RuntimeException('Interrupt', 2);
        }
        return true;
    }

    // allowed commands
    $allowed = ['cp', 'copy', 'xcopy'];
    foreach ($allowed as $a) {
        if (str_starts_with($cmd, $a)) {
            \f_passthru($cmd);
            return true;
        }
    }
    throw new RuntimeException('Invalid tests');
}
