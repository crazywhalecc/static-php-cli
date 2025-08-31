# Extension Notes

Because it is a static compilation, extensions will not compile 100% perfectly, 
and different extensions have different requirements for PHP and the environment, 
which will be listed one by one here.

## curl

HTTP3 support is not enabled by default, compile with `--with-libs="nghttp2,nghttp3,ngtcp2"` to enable HTTP3 support for PHP >= 8.4.

When using curl to request HTTPS, there may be an `error:80000002:system library::No such file or directory` error.
For details on the solution, see [FAQ - Unable to use ssl](../faq/#unable-to-use-ssl).

## phpmicro

1. Only PHP >= 8.0 is supported.

## swoole

1. swoole >= 5.0 Only PHP >= 8.0 is supported.
2. swoole Currently, curl hooks are not supported for PHP 8.0.x (which may be fixed in the future).
3. When compiling, if only `swoole` extension is included, the supported Swoole database coroutine hook will not be fully enabled. 
   If you need to use it, please add the corresponding `swoole-hook-xxx` extension.
4. The `zend_mm_heap corrupted` problem may occur in swoole under some extension combinations. The cause has not yet been found.

## swoole-hook-pgsql

swoole-hook-pgsql is not an extension, it's a Hook feature of Swoole.
If you use `swoole,swoole-hook-pgsql`, you will enable Swoole's PostgreSQL client and the coroutine mode of the `pdo_pgsql` extension.

swoole-hook-pgsql conflicts with the `pdo_pgsql` extension. If you want to use Swoole and `pdo_pgsql`, please delete the pdo_pgsql extension and enable `swoole` and `swoole-hook-pgsql`.
This extension contains an implementation of the coroutine environment for `pdo_pgsql`.

On macOS systems, `pdo_pgsql` may not be able to connect to the postgresql server normally, please use it with caution.

## swoole-hook-mysql

swoole-hook-mysql is not an extension, it's a Hook feature of Swoole.
If you use `swoole,swoole-hook-mysql`, you will enable the coroutine mode of Swoole's `mysqlnd` and `pdo_mysql`.

## swoole-hook-sqlite

swoole-hook-sqlite is not an extension, it's a Hook feature of Swoole.
If you use `swoole,swoole-hook-sqlite`, you will enable the coroutine mode of Swoole's `pdo_sqlite` (Swoole must be 5.1 or above).

swoole-hook-sqlite conflicts with the `pdo_sqlite` extension. If you want to use Swoole and `pdo_sqlite`, please delete the pdo_sqlite extension and enable `swoole` and `swoole-hook-sqlite`.
This extension contains an implementation of the coroutine environment for `pdo_sqlite`.

## swoole-hook-odbc

swoole-hook-odbc is not an extension, it's a Hook feature of Swoole.
If you use `swoole,swoole-hook-odbc`, you will enable the coroutine mode of Swoole's `odbc` extension.

swoole-hook-odbc conflicts with the `pdo_odbc` extension. If you want to use Swoole and `pdo_odbc`, please delete the `pdo_odbc` extension and enable `swoole` and `swoole-hook-odbc`.
This extension contains an implementation of the coroutine environment for `pdo_odbc`.

## swow

1. Only PHP 8.0+ is supported.

## imagick

1. OpenMP support is disabled, this is recommended by the maintainers and also the case system packages.

## imap

1. Kerberos is not supported
2. ext-imap is not thread safe due to the underlying c-client. It's not possible to use it in `--enable-zts` builds.
3. The extension was dropped from php 8.4, we recommend you look for an alternative implementation, such as [Webklex/php-imap](https://github.com/Webklex/php-imap)

## gd

1. gd Extension relies on more additional Graphics library. By default, 
using `bin/spc build gd` directly will not support some Graphics library, such as `libjpeg`, `libavif`, etc.
Currently, it supports four libraries: `freetype,libjpeg,libavif,libwebp`. 
Therefore, the following command can be used to introduce them into the gd library:

```bash
bin/spc build gd --with-libs=freetype,libjpeg,libavif,libwebp --build-cli
```

## mcrypt

1. Currently not supported, and this extension will not be supported in the future. [#32](https://github.com/crazywhalecc/static-php-cli/issues/32)

## oci8

1. oci8 is an extension of the Oracle database, because the library on which the extension provided by Oracle does not provide a statically compiled version (`.a`) or source code, 
and this extension cannot be compiled into php by static linking, so it cannot be supported.

## xdebug

1. Xdebug is only buildable as a shared extension. On Linux, you'll need to use a SPC_TARGET like `native-native -dynamic` or `native-native-gnu`.
2. When using Linux/glibc or macOS, you can compile Xdebug as a shared extension using --build-shared="xdebug". 
   The compiled `./php` binary can be configured and run by specifying the INI, eg `./php -d 'zend_extension=/path/to/xdebug.so' your-code.php`.

## xml

1. xml includes xml, xmlreader, xmlwriter, xsl, dom, simplexml, etc. 
    When adding xml extensions, it is best to enable these extensions at the same time.
2. libxml is included in xml extension. Enabling xml is equivalent to enabling libxml.

## glfw

1. glfw depends on OpenGL, and linux environment also needs X11, which cannot be linked statically.
2. macOS platform, we can compile and link system builtin OpenGL and related libraries dynamically.

## rar

1. The rar extension currently has a problem when compiling phpmicro with the `common` extension collection in the macOS x86_64 environment.

## pgsql

~~pgsql ssl connection is not compatible with openssl 3.2.0. See:~~

- ~~<https://github.com/Homebrew/homebrew-core/issues/155651>~~
- ~~<https://github.com/Homebrew/homebrew-core/pull/155699>~~
- ~~<https://github.com/postgres/postgres/commit/c82207a548db47623a2bfa2447babdaa630302b9>~~

pgsql 16.2 has fixed this bug, now it's working.

When pgsql uses SSL connection, there may be `error:80000002:system library::No such file or directory` error,
For details on the solution, see [FAQ - Unable to use ssl](../faq/#unable-to-use-ssl).

## openssl

When using openssl-based extensions (such as curl, pgsql and other network libraries),
there may be an `error:80000002:system library::No such file or directory` error.
For details on the solution, see [FAQ - Unable to use ssl](../faq/#unable-to-use-ssl).

## password-argon2

1. password-argon2 is not a standard extension. The algorithm `PASSWORD_ARGON2ID` for the `password_hash` function needs libsodium or libargon2 to work.
2. using password-argon2 enables multithread support for this.

## ffi

1. Due to the limitation of musl libc's static linkage, you cannot use ffi because dynamic libraries cannot be loaded. 
   If you need to use the ffi extension, see [Compile PHP with GNU libc](./build-with-glibc).
2. macOS supports the ffi extension, but errors will occur when some kernels do not contain debugging symbols.
3. Windows x64 supports the ffi extension.

## xhprof

The xhprof extension consists of three parts: `xhprof_extension`, `xhprof_html`, `xhprof_libs`. 
Only `xhprof_extension` is included in the compiled binary.
If you need to use xhprof,
please download the source code from [pecl.php.net/package/xhprof](http://pecl.php.net/package/xhprof) and specify the `xhprof_libs` and `xhprof_html` paths for use.

## event

If you enable event extension on macOS, the `openpty` will be disabled due to issue:

- [static-php-cli#335](https://github.com/crazywhalecc/static-php-cli/issues/335)

## parallel

Parallel is only supported on PHP 8.0 ZTS and above.

## spx

1. SPX does not support Windows, and the official repository does not support static compilation. static-php-cli uses a [modified version](https://github.com/static-php/php-spx).

## mimalloc

1. This is not technically an extension, but a library.
2. Building with `--with-libs="mimalloc"` on Linux or macOS will override the default allocator.
3. This is experimental for now, but is recommended in threaded environments.
