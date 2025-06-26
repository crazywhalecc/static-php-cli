<?php

declare(strict_types=1);

namespace SPC\store\pkg;

use SPC\store\CurlHook;
use SPC\store\Downloader;
use SPC\store\FileSystem;
use SPC\store\LockFile;

class Zig extends CustomPackage
{
    public function getSupportName(): array
    {
        return [
            'zig-x86_64-linux',
            'zig-aarch64-linux',
            'zig-x86_64-macos',
            'zig-aarch64-macos',
            'zig-x86_64-win',
        ];
    }

    public function fetch(string $name, bool $force = false, ?array $config = null): void
    {
        $pkgroot = PKG_ROOT_PATH;
        $zig_exec = match (PHP_OS_FAMILY) {
            'Windows' => "{$pkgroot}/{$name}/bin/zig.exe",
            default => "{$pkgroot}/{$name}/bin/zig",
        };

        if (file_exists($zig_exec) && !$force) {
            return;
        }

        $parts = explode('-', $name);
        $arch = $parts[1];
        $os = $parts[2];

        $zig_arch = match ($arch) {
            'x86_64', 'aarch64' => $arch,
            default => throw new \InvalidArgumentException('Unsupported architecture: ' . $arch),
        };

        $zig_os = match ($os) {
            'linux' => 'linux',
            'macos' => 'macos',
            'win' => 'windows',
            default => throw new \InvalidArgumentException('Unsupported OS: ' . $os),
        };

        $index_json = json_decode(Downloader::curlExec('https://ziglang.org/download/index.json', hooks: [[CurlHook::class, 'setupGithubToken']]), true);

        $latest_version = null;
        foreach ($index_json as $version => $data) {
            $latest_version = $version;
            break;
        }

        if (!$latest_version) {
            throw new \RuntimeException('Could not determine latest Zig version');
        }

        logger()->info("Installing Zig version {$latest_version}");

        $platform_key = "{$zig_arch}-{$zig_os}";
        if (!isset($index_json[$latest_version][$platform_key])) {
            throw new \RuntimeException("No download available for {$platform_key} in Zig version {$latest_version}");
        }

        $download_info = $index_json[$latest_version][$platform_key];
        $url = $download_info['tarball'];
        $filename = basename($url);

        $config = [
            'type' => 'url',
            'url' => $url,
            'filename' => $filename,
        ];

        Downloader::downloadPackage($name, $config, $force);
    }

    public function extract(string $name): void
    {
        $pkgroot = PKG_ROOT_PATH;
        $zig_bin_dir = "{$pkgroot}/{$name}";
        $zig_exec = match (PHP_OS_FAMILY) {
            'Windows' => "{$zig_bin_dir}/zig.exe",
            default => "{$zig_bin_dir}/zig",
        };

        if (file_exists($zig_exec)) {
            if (!file_exists("{$zig_bin_dir}/zig-cc")) {
                $this->createZigCcScript($zig_bin_dir);
                return;
            }
            return;
        }

        $lock = json_decode(FileSystem::readFile(LockFile::LOCK_FILE), true);
        $source_type = $lock[$name]['source_type'];
        $filename = DOWNLOAD_PATH . '/' . ($lock[$name]['filename'] ?? $lock[$name]['dirname']);
        $extract = "{$pkgroot}/{$name}";


        FileSystem::extractPackage($name, $source_type, $filename, $extract);

        $this->createZigCcScript($zig_bin_dir);
    }

    private function createZigCcScript(string $bin_dir): void
    {

        $script_content = <<<'EOF'
#!/usr/bin/env bash

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BUILDROOT_ABS="$(realpath "$SCRIPT_DIR/../../buildroot/include" 2>/dev/null || echo "")"
PARSED_ARGS=()

while [[ $# -gt 0 ]]; do
    case "$1" in
        -isystem)
            shift
            ARG="$1"
            [[ -n "$ARG" ]] && shift || break
            ARG_ABS="$(realpath "$ARG" 2>/dev/null || echo "")"
            if [[ -n "$ARG_ABS" && "$ARG_ABS" == "$BUILDROOT_ABS" ]]; then
                PARSED_ARGS+=("-I$ARG")
            else
                PARSED_ARGS+=("-isystem" "$ARG")
            fi
            ;;
        -isystem*)
            ARG="${1#-isystem}"
            shift
            ARG_ABS="$(realpath "$ARG" 2>/dev/null || echo "")"
            if [[ -n "$ARG_ABS" && "$ARG_ABS" == "$BUILDROOT_ABS" ]]; then
                PARSED_ARGS+=("-I$ARG")
            else
                PARSED_ARGS+=("-isystem$ARG")
            fi
            ;;
        *)
            PARSED_ARGS+=("$1")
            shift
            ;;
    esac
done

SPC_TARGET_WAS_SET=1
if [ -z "${SPC_TARGET+x}" ]; then
    SPC_TARGET_WAS_SET=0
fi

UNAME_M="$(uname -m)"
UNAME_S="$(uname -s)"

case "$UNAME_M" in
    x86_64) ARCH="x86_64" ;;
    aarch64|arm64) ARCH="aarch64" ;;
    *) echo "Unsupported architecture: $UNAME_M" >&2; exit 1 ;;
esac

case "$UNAME_S" in
    Linux) OS="linux" ;;
    Darwin) OS="macos" ;;
    *) echo "Unsupported OS: $UNAME_S" >&2; exit 1 ;;
esac

SPC_TARGET="${SPC_TARGET:-$ARCH-$OS}"
SPC_LIBC="${SPC_LIBC}"
SPC_LIBC_VERSION="${SPC_LIBC_VERSION}"

if [ "$SPC_LIBC" = "glibc" ]; then
    SPC_LIBC="gnu"
fi

if [ "$SPC_TARGET_WAS_SET" -eq 0 ] && [ -z "$SPC_LIBC" ] && [ -z "$SPC_LIBC_VERSION" ]; then
    exec zig cc "${PARSED_ARGS[@]}"
elif [ -z "$SPC_LIBC" ] && [ -z "$SPC_LIBC_VERSION" ]; then
    exec zig cc -target ${SPC_TARGET} "${PARSED_ARGS[@]}"
elif [ -z "$SPC_LIBC_VERSION" ]; then
    exec zig cc -target ${SPC_TARGET}-${SPC_LIBC} -L/usr/lib64 -lstdc++ "${PARSED_ARGS[@]}"
else
    error_output=$(zig cc -target ${SPC_TARGET}-${SPC_LIBC}.${SPC_LIBC_VERSION} "${PARSED_ARGS[@]}" 2>&1 >/dev/null)
    if echo "$error_output" | grep -q "zig: error: version '.*' in target triple '${SPC_TARGET}-${SPC_LIBC}\..*' is invalid"; then
        exec zig cc -target ${SPC_TARGET}-${SPC_LIBC} -L/usr/lib64 -lstdc++ "${PARSED_ARGS[@]}"
    else
        exec zig cc -target ${SPC_TARGET}-${SPC_LIBC}.${SPC_LIBC_VERSION} -L/usr/lib64 -lstdc++ "${PARSED_ARGS[@]}"
    fi
fi

EOF;

        file_put_contents("{$bin_dir}/zig-cc", $script_content);
        chmod("{$bin_dir}/zig-cc", 0755);

        $script_content = str_replace('zig cc', 'zig c++', $script_content);
        file_put_contents("{$bin_dir}/zig-c++", $script_content);
        chmod("{$bin_dir}/zig-c++", 0755);
    }

    public static function getEnvironment(): array
    {
        $arch = arch2gnu(php_uname('m'));
        $os = match (PHP_OS_FAMILY) {
            'Linux' => 'linux',
            'Windows' => 'win',
            'Darwin' => 'macos',
            'BSD' => 'freebsd',
            default => 'linux',
        };

        $packageName = "zig-{$arch}-{$os}";
        $path = PKG_ROOT_PATH . "/{$packageName}";

        return [
            'PATH' => $path
        ];
    }
}
