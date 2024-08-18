<?php

/** @noinspection ALL */

declare(strict_types=1);

/**
 * This is GitHub Actions automatic test extension args generator.
 * You can edit $extensions, $with_libs and $base_combination.
 */

// --------------------------------- edit area ---------------------------------

$zts = false;

$no_strip = false;

$upx = true;

// If you want to test your added extensions and libs, add below (comma separated, example `bcmath,openssl`).
$extensions = match (PHP_OS_FAMILY) {
    'Linux', 'Darwin' => 'imap,swoole-hook-sqlite,swoole',
    'Windows' => 'igbinary,redis,session',
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
    exit("Please use 'extensions', 'cmd' or 'libs' as output type");
}

$trim_value = "\r\n \t,";

$final_extensions = trim(trim($extensions, $trim_value) . ',' . _getCombination($base_combination), $trim_value);
$final_libs = trim($with_libs, $trim_value);

if (PHP_OS_FAMILY === 'Windows') {
    $final_extensions_cmd = '"' . $final_extensions . '"';
} else {
    $final_extensions_cmd = $final_extensions;
}

echo match ($argv[1]) {
    'extensions' => $final_extensions,
    'libs' => $final_libs,
    'libs_cmd' => ($final_libs === '' ? '' : (' --with-libs=' . $final_libs)),
    'cmd' => $final_extensions_cmd . ($final_libs === '' ? '' : (' --with-libs=' . $final_libs)),
    'zts' => $zts ? '--enable-zts' : '',
    'no_strip' => $no_strip ? '--no-strip' : '',
    'upx' => $upx ? '--with-upx-pack' : '',
    default => '',
};
