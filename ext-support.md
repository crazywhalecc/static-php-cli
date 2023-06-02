# Extension List

> - yes: supported and tested
> - untested: supported, but not tested
> - empty: not supported yet
> - no with issue link: not supported yet due to issue
> - partial with issue link: supported but not perfect due to issue

|                 | Linux                                                               | macOS                                                          | Windows |
|-----------------|---------------------------------------------------------------------|----------------------------------------------------------------|---------|
| apcu            | yes, untested                                                       | yes, untested                                                  |         |
| bcmath          | yes                                                                 | yes                                                            |         |
| bz2             | yes                                                                 | yes                                                            |         |
| calendar        | yes                                                                 | yes                                                            |         |
| ctype           | yes                                                                 | yes                                                            |         |
| curl            | yes                                                                 | yes                                                            |         |
| dba             | yes                                                                 | yes                                                            |         | 
| dom             | yes                                                                 | yes                                                            |         |
| enchant         |                                                                     |                                                                |         |
| event           | yes                                                                 | yes                                                            |         |
| exif            | yes                                                                 | yes                                                            |         |
| ffi             |                                                                     | yes, [docs]()                                                  |         |
| filter          | yes                                                                 | yes                                                            |         |
| fileinfo        | yes                                                                 | yes                                                            |         |
| ftp             | yes                                                                 | yes                                                            |         |
| gd              | yes                                                                 | yes                                                            |         |
| gettext         |                                                                     |                                                                |         |
| gmp             | yes                                                                 | yes                                                            |         |
| iconv           | yes                                                                 | yes                                                            |         |
| imagick         | yes                                                                 | yes                                                            |         |
| inotify         | yes                                                                 | yes                                                            |         |
| intl            | [partial](https://github.com/crazywhalecc/static-php-cli/issues/66) | yes                                                            |         |
| mbstring        | yes                                                                 | yes                                                            |         |
| mbregex         | yes                                                                 | yes                                                            |         |
| mcrypt          |                                                                     | [no](https://github.com/crazywhalecc/static-php-cli/issues/32) |         |
| mongodb         | yes                                                                 | yes                                                            |         |
| mysqli          | yes                                                                 | yes                                                            |         |
| mysqlnd         | yes                                                                 | yes                                                            |         |
| openssl         | yes                                                                 | yes                                                            |         |
| password-argon2 |                                                                     |                                                                |         |
| pcntl           | yes                                                                 | yes                                                            |         |
| pdo             | yes                                                                 | yes                                                            |         |
| pdo_mysql       | yes                                                                 | yes                                                            |         |
| pdo_sqlite      | yes                                                                 | yes                                                            |         |
| pdo_pgsql       |                                                                     |                                                                |         |
| phar            | yes                                                                 | yes                                                            |         |
| posix           | yes                                                                 | yes                                                            |         |
| protobuf        | yes                                                                 | yes                                                            |         |
| readline        | yes, untested                                                       | yes, untested                                                  |         |
| redis           | yes                                                                 | yes                                                            |         |
| session         | yes                                                                 | yes                                                            |         |
| shmop           | yes                                                                 | yes                                                            |         |
| simplexml       | yes                                                                 | yes                                                            |         |
| soap            | yes                                                                 | yes                                                            |         |
| sockets         | yes                                                                 | yes                                                            |         |
| sodium          | yes                                                                 | yes                                                            |         |
| sqlite3         | yes                                                                 | yes                                                            |         |
| ssh2            | yes, untested                                                       | yes, untested                                                  |         |
| swow            | yes                                                                 | yes                                                            |         |
| swoole          | [partial](https://github.com/crazywhalecc/static-php-cli/issues/51) | yes                                                            |         |
| tokenizer       | yes                                                                 | yes                                                            |         |
| xlswriter       | yes                                                                 | yes                                                            |         |
| xml             | yes                                                                 | yes                                                            |         |
| xmlreader       | yes, untested                                                       | yes, untested                                                  |         |
| xmlwriter       | yes, untested                                                       | yes, untested                                                  |         |
| zip             | yes, untested                                                       | yes, untested                                                  |         |
| zlib            | yes                                                                 | yes                                                            |         |
| zstd            | yes                                                                 | yes                                                            |         |

## Additional Requirements

- phpmicro requires PHP >= 8.0
- swoole >= 5.0 requires PHP >= 8.0
- swow requires PHP >= 8.0

## Typical Extension List Example

Here are some extension list example for different use.

- For general use: `"bcmath,bz2,calendar,ctype,curl,dom,exif,fileinfo,ftp,filter,gd,iconv,xml,mbstring,mysqlnd,openssl,pcntl,pdo,pdo_mysql,pdo_sqlite,phar,posix,redis,simplexml,soap,sockets,sqlite3,tokenizer,xmlwriter,xmlreader,zlib,zip"`
- For static-php-cli self: `"posix,pcntl,phar,tokenizer,iconv,zlib"`
- For static-php-cli self (with dev dependencies): `"posix,pcntl,phar,tokenizer,iconv,zlib,xml,dom,xmlwriter,xmlreader,fileinfo"`
- Minimum, with no extension: `""`

Compile with all supported extensions (exclude `swow`, `swoole`, because these will change the default behavior of php):

```bash
bin/spc build --build-all bcmath,bz2,calendar,ctype,curl,dba,dom,exif,fileinfo,filter,ftp,gd,gmp,iconv,mbregex,mbstring,mongodb,mysqli,mysqlnd,openssl,pcntl,pdo,pdo_mysql,pdo_sqlite,phar,posix,protobuf,redis,session,shmop,simplexml,soap,sockets,sqlite3,tokenizer,xml,xmlreader,xmlwriter,yaml,zip,zlib,zstd --with-libs=libjpeg,freetype,libwebp,libavif --debug
```

## Additional Libraries

Some extensions have soft dependencies, you can enable extra features by adding these libs using `--with-libs`.

For example, to compile with gd extension, with `libwebp, libgif, libavif, libjpeg, freetype` extra features:

```bash
bin/spc build gd --with-libs=libjpeg,freetype,libwebp,libavif --build-cli
```

> If you don't add them, your compilation will not enable these features.

## Limitations

- swow and swoole cannot be compiled at the same time.
- openssl needs manual configuration for ssl certificate. (TODO: I will write a wiki about it)
- some extensions need system configuration, e.g. `curl` and `openssl` will search ssl certificate on your system.

## Bugs and TODOs

See [#32](https://github.com/crazywhalecc/static-php-cli/issues/32).
