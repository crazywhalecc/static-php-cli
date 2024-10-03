<?php

declare(strict_types=1);

// mock global functions

namespace SPC\store;

function f_exec(string $command, mixed &$output, mixed &$result_code): bool
{
    if (str_contains($command, 'https://api.github.com/repos/AOMediaCodec/libavif/releases')) {
        $output = explode("\n", gzdecode(file_get_contents(__DIR__ . '/../assets/github_api_AOMediaCodec_libavif_releases.json.gz')));
        $result_code = 0;
        return true;
    }
    if (str_contains($command, 'https://api.github.com/repos/AOMediaCodec/libavif/tarball/v1.1.1')) {
        $output = explode("\n", "HTTP/1.1 200 OK\r\nContent-Disposition: attachment; filename=AOMediaCodec-libavif-v1.1.1-0-gbb24db0.tar.gz\r\n\r\n");
        $result_code = 0;
        return true;
    }
    $result_code = -2;
    $output = null;
    return false;
}
