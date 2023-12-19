<?php

declare(strict_types=1);

# If you want to test new extensions here, just modify it.
$extensions = 'password-argon2,apcu,bcmath,bz2,calendar,ctype,curl,dba,dom,event,exif,fileinfo,filter,ftp,gd,gmp,iconv,imagick,imap,intl,ldap,mbregex,mbstring,mysqli,mysqlnd,opcache,openssl,pcntl,pdo,pdo_mysql,pdo_pgsql,pdo_sqlite,pgsql,phar,posix,protobuf,readline,redis,session,shmop,simplexml,soap,sockets,sqlite3,swoole,sysvmsg,sysvsem,sysvshm,tokenizer,xml,xmlreader,xmlwriter,xsl,zip,zlib';

if (PHP_OS_FAMILY === 'Darwin') {
    $extensions .= ',sodium';
}

echo $extensions;
