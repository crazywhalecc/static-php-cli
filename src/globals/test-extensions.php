<?php

/** @noinspection ALL */

declare(strict_types=1);

/**
 * This is GitHub Actions automatic test extension args generator.
 * You can edit $test_php_version, $test_os, $zts, $no_strip, $upx, $prefer_pre_built, $extensions, $with_libs and $base_combination.
 */

// --------------------------------- edit area ---------------------------------

// test php version (8.1 ~ 8.4 available, multiple for matrix)
$test_php_version = [
    '8.1',
    '8.2',
    '8.3',
    '8.4',
    // '8.5',
    // 'git',
];

// test os (macos-13, macos-14, macos-15, ubuntu-latest, windows-latest are available)
$test_os = [
    'macos-13', // bin/spc for x86_64
    // 'macos-14',  // bin/spc for arm64
    'macos-15', // bin/spc for arm64
    'ubuntu-latest', // bin/spc-alpine-docker for x86_64
    'ubuntu-22.04', // bin/spc-gnu-docker for x86_64
    'ubuntu-24.04', // bin/spc for x86_64
    'ubuntu-22.04-arm', // bin/spc-gnu-docker for arm64
    'ubuntu-24.04-arm', // bin/spc for arm64
    // 'windows-latest', // .\bin\spc.ps1
];

// whether enable thread safe
$zts = true;

$no_strip = false;

// compress with upx
$upx = false;

// whether to test frankenphp build, only available for macos and linux
$frankenphp = false;

// prefer downloading pre-built packages to speed up the build process
$prefer_pre_built = false;

// If you want to test your added extensions and libs, add below (comma separated, example `bcmath,openssl`).
$extensions = match (PHP_OS_FAMILY) {
    'Linux', 'Darwin' => 'swoole,swoole-hook-mysql,swoole-hook-pgsql,swoole-hook-sqlite,swoole-hook-odbc,apcu,bcmath,bz2,calendar,ctype,curl,dba,dom,event,exif,fileinfo,filter,ftp,gd,gmp,iconv,imagick,intl,mbregex,mbstring,mysqli,mysqlnd,opcache,openssl,pcntl,pdo,pdo_mysql,pgsql,phar,posix,protobuf,readline,redis,session,shmop,simplexml,soap,sockets,sodium,sqlite3,swoole,sysvmsg,sysvsem,sysvshm,tokenizer,xml,xmlreader,xmlwriter,xsl,zip,zlib',
    'Windows' => 'bcmath,bz2,calendar,ctype,curl,dom,exif,fileinfo,filter,ftp,iconv,xml,mbstring,mbregex,mysqlnd,openssl,pdo,pdo_mysql,pdo_sqlite,phar,session,simplexml,soap,sockets,sqlite3,tokenizer,xmlwriter,xmlreader,zlib,zip',
};

// If you want to test shared extensions, add them below (comma separated, example `bcmath,openssl`).
$shared_extensions = match (PHP_OS_FAMILY) {
    'Linux' => '',
    'Darwin' => '',
    'Windows' => '',
};

// If you want to test lib-suggests for all extensions and libraries, set it to true.
$with_suggested_libs = true;

// If you want to test extra libs for extensions, add them below (comma separated, example `libwebp,libavif`). Unnecessary, when $with_suggested_libs is true.
$with_libs = match (PHP_OS_FAMILY) {
    'Linux', 'Darwin' => '',
    'Windows' => '',
};

// Please change your test base combination. We recommend testing with `common`.
// You can use `common`, `bulk`, `minimal` or `none`.
// note: combination is only available for *nix platform. Windows must use `none` combination
$base_combination = match (PHP_OS_FAMILY) {
    'Linux', 'Darwin' => 'none',
    'Windows' => 'none',
};

// -------------------------- code area, do not modify --------------------------

/**
 * get combination for tests, do not modify it if not necessary.
 */
function _getCombination(string $type = 'common'): string
{
    return match ($type) {
        'common' => 'bcmath,bz2,calendar,ctype,curl,dom,exif,fileinfo,filter,ftp,gd,gmp,iconv,xml,mbstring,mbregex,' .
            'mysqlnd,openssl,pcntl,pdo,pdo_mysql,pdo_sqlite,phar,posix,redis,session,simplexml,soap,sockets,' .
            'sqlite3,tokenizer,xmlwriter,xmlreader,zlib,zip',
        'bulk' => 'apcu,bcmath,bz2,calendar,ctype,curl,dba,dom,event,exif,fileinfo,filter,ftp,gd,gmp,iconv,imagick,imap,' .
            'intl,mbregex,mbstring,mysqli,mysqlnd,opcache,openssl,pcntl,pdo,pdo_mysql,pdo_pgsql,pdo_sqlite,pgsql,phar,' .
            'posix,protobuf,readline,redis,session,shmop,simplexml,soap,sockets,sodium,sqlite3,swoole,sysvmsg,sysvsem,' .
            'sysvshm,tokenizer,xml,xmlreader,xmlwriter,xsl,zip,zlib',
        'minimal' => 'pcntl,posix,mbstring,tokenizer,phar',
        default => '', // none
    };
}

if (!isset($argv[1])) {
    exit("Please use 'extensions', 'cmd', 'os', 'php' or 'libs' as output type");
}

$trim_value = "\r\n \t,";

$final_extensions = trim(trim($extensions, $trim_value) . ',' . _getCombination($base_combination), $trim_value);
$download_extensions = trim($final_extensions . ',' . $shared_extensions, $trim_value);
$final_libs = trim($with_libs, $trim_value);

if (PHP_OS_FAMILY === 'Windows') {
    $final_extensions_cmd = '"' . $final_extensions . '"';
} else {
    $final_extensions_cmd = $final_extensions;
}

function quote2(string $param): string
{
    global $argv;
    if (str_starts_with($argv[2], 'windows-')) {
        return '"' . $param . '"';
    }
    return $param;
}

// generate download command
if ($argv[1] === 'download_cmd') {
    $down_cmd = 'download ';
    $down_cmd .= '--for-extensions=' . quote2($download_extensions) . ' ';
    $down_cmd .= '--for-libs=' . quote2($final_libs) . ' ';
    $down_cmd .= '--with-php=' . quote2($argv[3]) . ' ';
    $down_cmd .= '--ignore-cache-sources=php-src ';
    $down_cmd .= '--debug ';
    $down_cmd .= '--retry=5 ';
    $down_cmd .= '--shallow-clone ';
    $down_cmd .= $prefer_pre_built ? '--prefer-pre-built ' : '';
}

if ($argv[1] === 'doctor_cmd') {
    $doctor_cmd = 'doctor --auto-fix --debug';
}
if ($argv[1] === 'install_upx_cmd') {
    $install_upx_cmd = 'install-pkg upx --debug';
}

$prefix = match ($argv[2] ?? null) {
    'windows-latest', 'windows-2022', 'windows-2019', 'windows-2025' => 'powershell.exe -file .\bin\spc.ps1 ',
    'ubuntu-latest' => 'bin/spc-alpine-docker ',
    'ubuntu-24.04', 'ubuntu-24.04-arm' => './bin/spc ',
    'ubuntu-22.04', 'ubuntu-22.04-arm' => 'bin/spc-gnu-docker ',
    default => 'bin/spc ',
};

// shared_extension build
if ($shared_extensions) {
    switch ($argv[2] ?? null) {
        case 'ubuntu-22.04':
        case 'ubuntu-22.04-arm':
            $shared_cmd = ' --build-shared=' . quote2($shared_extensions) . ' ';
            break;
        case 'ubuntu-24.04':
        case 'ubuntu-24.04-arm':
            break;
        case 'macos-13':
        case 'macos-14':
        case 'macos-15':
            $shared_cmd = ' --build-shared=' . quote2($shared_extensions) . ' ';
            $no_strip = true;
            break;
        default:
            $shared_cmd = '';
            break;
    }
} else {
    $shared_cmd = '';
}

// generate build command
if ($argv[1] === 'build_cmd' || $argv[1] === 'build_embed_cmd') {
    $build_cmd = 'build ';
    $build_cmd .= quote2($final_extensions) . ' ';
    $build_cmd .= $shared_cmd;
    $build_cmd .= $with_suggested_libs ? '--with-suggested-libs ' : '';
    $build_cmd .= $zts ? '--enable-zts ' : '';
    $build_cmd .= $no_strip ? '--no-strip ' : '';
    $build_cmd .= $upx ? '--with-upx-pack ' : '';
    $build_cmd .= $final_libs === '' ? '' : ('--with-libs=' . quote2($final_libs) . ' ');
    $build_cmd .= str_starts_with($argv[2], 'windows-') ? '' : '--build-fpm ';
    $build_cmd .= '--debug ';
}

echo match ($argv[1]) {
    'os' => json_encode($test_os),
    'php' => json_encode($test_php_version),
    'extensions' => $final_extensions,
    'libs' => $final_libs,
    'libs_cmd' => ($final_libs === '' ? '' : (' --with-libs=' . $final_libs)),
    'cmd' => $final_extensions_cmd . ($final_libs === '' ? '' : (' --with-libs=' . $final_libs)),
    'zts' => $zts ? '--enable-zts' : '',
    'no_strip' => $no_strip ? '--no-strip' : '',
    'upx' => $upx ? '--with-upx-pack' : '',
    'prefer_pre_built' => $prefer_pre_built ? '--prefer-pre-built' : '',
    'download_cmd' => $down_cmd,
    'install_upx_cmd' => $install_upx_cmd,
    'doctor_cmd' => $doctor_cmd,
    'build_cmd' => $build_cmd,
    'build_embed_cmd' => $build_cmd,
    default => '',
};

switch ($argv[1] ?? null) {
    case 'download_cmd':
        passthru($prefix . $down_cmd, $retcode);
        break;
    case 'build_cmd':
        passthru($prefix . $build_cmd . ' --build-cli --build-micro', $retcode);
        break;
    case 'build_embed_cmd':
        if ($frankenphp) {
            passthru("{$prefix}install-pkg go-xcaddy --debug", $retcode);
            if ($retcode !== 0) {
                break;
            }
        }
        passthru($prefix . $build_cmd . (str_starts_with($argv[2], 'windows-') ? ' --build-cli' : (' --build-embed' . ($frankenphp ? ' --build-frankenphp' : ''))), $retcode);
        break;
    case 'doctor_cmd':
        passthru($prefix . $doctor_cmd, $retcode);
        break;
    case 'install_upx_cmd':
        passthru($prefix . $install_upx_cmd, $retcode);
        break;
    default:
        $retcode = 0;
        break;
}

exit($retcode);
