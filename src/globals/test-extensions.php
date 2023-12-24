<?php

declare(strict_types=1);

# Normal test, contains `common` extension.
$extensions = 'bcmath,bz2,calendar,ctype,curl,dom,exif,fileinfo,filter,ftp,gd,gmp,iconv,xml,mbstring,mbregex,mysqlnd,openssl,pcntl,pdo,pdo_mysql,pdo_sqlite,phar,posix,redis,session,simplexml,soap,sockets,sqlite3,tokenizer,xmlwriter,xmlreader,zlib,zip';

# Normal test, contains gd extra libraries.
$additional_libs = 'libwebp,libavif,libjpeg,freetype';

# If you want to test additional extensions, add them below. (comma start)
$extensions .= ',uv';

# If you want to test additional features for extensions, add libs below. (comma start like extensions)
$additional_libs .= '';

$extensions .= 'swoole';

$additional_libs .= 'postgresql';

if (!isset($argv[1])) {
    exit("Please use 'extensions', 'cmd' or 'libs' as output type");
}
echo match ($argv[1]) {
    'extensions' => $extensions,
    'libs' => $additional_libs,
    'cmd' => $extensions . ' --with-libs=' . $additional_libs,
    default => '',
};
