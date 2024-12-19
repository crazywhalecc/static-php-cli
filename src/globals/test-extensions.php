<?php

/** @noinspection ALL */

declare(strict_types=1);

/**
 * This is GitHub Actions automatic test extension args generator.
 * You can edit $extensions, $with_libs and $base_combination.
 */

// --------------------------------- edit area ---------------------------------

// test php version
$test_php_version = [
    '8.1',
    '8.2',
    '8.3',
    '8.4',
];

// test os (macos-13, macos-14, ubuntu-latest, windows-latest are available)
$test_os = [
    'macos-14',
    // 'macos-13',
    'ubuntu-latest',
];

// whether enable thread safe
$zts = false;

$no_strip = false;

// compress with upx
$upx = false;

// prefer downloading pre-built packages to speed up the build process
$prefer_pre_built = true;

// If you want to test your added extensions and libs, add below (comma separated, example `bcmath,openssl`).
$extensions = match (PHP_OS_FAMILY) {
    'Linux', 'Darwin' => 'imagick',
    'Windows' => 'zlib',
};

// If you want to test lib-suggests feature with extension, add them below (comma separated, example `libwebp,libavif`).
$with_libs = match (PHP_OS_FAMILY) {
    'Linux', 'Darwin' => '',
    'Windows' => '',
};

// Please change your test base combination. We recommend testing with `common`.
// You can use `common`, `bulk`, `minimal` or `none`.
// note: combination is only available for *nix platform. Windows must use `none` combination
$base_combination = match (PHP_OS_FAMILY) {
    'Linux', 'Darwin' => 'minimal',
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
    $down_cmd .= '--for-extensions=' . quote2($final_extensions) . ' ';
    $down_cmd .= '--for-libs=' . quote2($final_libs) . ' ';
    $down_cmd .= '--with-php=' . quote2($argv[3]) . ' ';
    $down_cmd .= '--ignore-cache-sources=php-src ';
    $down_cmd .= '--debug ';
    $down_cmd .= '--retry=5 ';
    $down_cmd .= '--shallow-clone ';
    $down_cmd .= $prefer_pre_built ? '--prefer-pre-built ' : '';
}

// generate build command
if ($argv[1] === 'build_cmd' || $argv[1] === 'build_embed_cmd') {
    $build_cmd = 'build ';
    $build_cmd .= quote2($final_extensions) . ' ';
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
    'build_cmd' => $build_cmd,
    'build_embed_cmd' => $build_cmd,
    default => '',
};

if ($argv[1] === 'download_cmd') {
    if (str_starts_with($argv[2], 'windows-')) {
        passthru('powershell.exe -file .\bin\spc.ps1 ' . $down_cmd, $retcode);
    } else {
        passthru('./bin/spc ' . $down_cmd, $retcode);
    }
} elseif ($argv[1] === 'build_cmd') {
    if (str_starts_with($argv[2], 'windows-')) {
        passthru('powershell.exe -file .\bin\spc.ps1 ' . $build_cmd . ' --build-cli --build-micro', $retcode);
    } else {
        passthru('./bin/spc ' . $build_cmd . ' --build-cli --build-micro', $retcode);
    }
} elseif ($argv[1] === 'build_embed_cmd') {
    if (str_starts_with($argv[2], 'windows-')) {
        // windows does not accept embed SAPI
        passthru('powershell.exe -file .\bin\spc.ps1 ' . $build_cmd . ' --build-cli', $retcode);
    } else {
        passthru('./bin/spc ' . $build_cmd . ' --build-embed', $retcode);
    }
}

exit($retcode);
