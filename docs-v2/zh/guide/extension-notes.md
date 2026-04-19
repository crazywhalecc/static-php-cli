# 扩展注意事项

因为是静态编译，扩展不会 100% 完美编译，而且不同扩展对 PHP、环境都有不同的要求，这里将一一列举。

## curl

HTTP3 支持默认未启用，需在编译时添加 `--with-libs="nghttp2,nghttp3,ngtcp2"` 以启用 PHP 8.4 及以上版本的 HTTP3 支持。

使用 curl 请求 HTTPS 时，可能存在 `error:80000002:system library::No such file or directory` 错误，
解决办法详见 [FAQ - 无法使用 ssl](../faq/#无法使用-ssl)。

## phpmicro

1. phpmicro SAPI 仅支持 PHP >= 8.0 版本。

## swoole

1. swoole >= 5.0 版本仅支持 PHP >= 8.0 版本。
2. swoole 目前不支持 PHP 8.0 版本 curl 的 hook（后续有可能会修复）。
3. 编译时只包含 `swoole` 扩展时不会完整开启支持的 Swoole 数据库协程 hook，如需使用请加入对应的 `swoole-hook-xxx` 扩展。
4. swoole 在部分扩展组合下可能出现 `zend_mm_heap corrupted` 问题，暂未找到是什么原因导致的。

## swoole-hook-pgsql

swoole-hook-pgsql 不是一个扩展，而是 Swoole 的 Hook 特性。
如果你在编译时添加了 `swoole,swoole-hook-pgsql`，你将启用 Swoole 的 PostgreSQL 客户端和 `pdo_pgsql` 扩展的协程模式。

swoole-hook-pgsql 与 `pdo_pgsql` 扩展冲突。如需使用 Swoole 和 `pdo_pgsql`，请删除 pdo_pgsql 扩展，启用 `swoole` 和 `swoole-hook-pgsql` 即可。
该扩展包含了 `pdo_pgsql` 的协程环境的实现。

在 macOS 系统，`pdo_pgsql` 可能无法正常连接到 postgresql 服务器，请谨慎使用。

## swoole-hook-mysql

swoole-hook-mysql 不是一个扩展，而是 Swoole 的 Hook 特性。
如果你在编译时添加了 `swoole,swoole-hook-mysql`，你将启用 Swoole 的 `mysqlnd` 和 `pdo_mysql` 的协程模式。

## swoole-hook-sqlite

swoole-hook-sqlite 不是一个扩展，而是 Swoole 的 Hook 特性。
如果你在编译时添加了 `swoole,swoole-hook-sqlite`，你将启用 Swoole 的 `pdo_sqlite` 的协程模式（Swoole 必须为 5.1 以上）。

swoole-hook-sqlite 与 `pdo_sqlite` 扩展冲突。如需使用 Swoole 和 `pdo_sqlite`，请删除 pdo_sqlite 扩展，启用 `swoole` 和 `swoole-hook-sqlite` 即可。
该扩展包含了 `pdo_sqlite` 的协程环境的实现。

## swoole-hook-odbc

swoole-hook-odbc 不是一个扩展，而是 Swoole 的 Hook 特性。
如果你在编译时添加了 `swoole,swoole-hook-odbc`，你将启用 Swoole 的 `odbc` 扩展的协程模式。

swoole-hook-odbc 与 `pdo_odbc` 扩展冲突。如需使用 Swoole 和 `pdo_odbc`，请删除 `pdo_odbc` 扩展，启用 `swoole` 和 `swoole-hook-odbc` 即可。
该扩展包含了 `pdo_odbc` 的协程环境的实现。

## swow

1. swow 仅支持 PHP 8.0+ 版本。

## imagick

1. OpenMP 支持已被禁用，这是维护者推荐的做法，系统软件包也是如此配置。

## imap

1. 该扩展目前不支持 Kerberos。
2. 由于底层的 c-client、ext-imap 不是线程安全的。 无法在 `--enable-zts` 构建中使用它。
3. 该扩展已在 PHP 8.4 中被移除，因此我们建议您寻找替代实现，例如 [Webklex/php-imap](https://github.com/Webklex/php-imap)。

## gd

1. gd 扩展依赖了较多的额外图形库，默认情况下，直接使用 `bin/spc build gd` 不会引入和支持部分图形库，例如 `libjpeg`、`libavif` 等，
需要使用 `--with-libs` 参数补全。目前支持 `freetype,libjpeg,libavif,libwebp` 四个库的支持，所以这里可以使用以下命令来让 gd 库引入它们：

```bash
bin/spc build gd --with-libs=freetype,libjpeg,libavif,libwebp --build-cli
```

## mcrypt

1. 目前未支持，未来也不计划支持此扩展。[#32](https://github.com/crazywhalecc/static-php-cli/issues/32)

## oci8

1. oci8 是 Oracle 数据库的扩展，因为 Oracle 提供的扩展所依赖的库未提供静态编译版本（`.a`）或源代码，无法使用静态链接的方式将此扩展编译到 php 内，故无法支持。

## xdebug

1. Xdebug 只能作为共享扩展进行构建。您需要使用除了 `musl-static` 外的其他 `SPC_TARGET` 构建目标。
2. 使用 Linux/glibc 或 macOS 时，您可以使用 `--build-shared=xdebug` 将 Xdebug 编译为共享扩展。
   编译后的 `./php` 二进制文件可以通过指定 INI 文件进行配置和运行，例如 `./php -d 'zend_extension=/path/to/xdebug.so' your-code.php`。

## xml

1. xml包括 xmlreader、xmlwriter、dom、simplexml 等，添加 xml 扩展时最好同时启用这些扩展。
2. libxml 包含在 xml 扩展中。 启用 xml 相当于启用 libxml。

## glfw

1. glfw 扩展依赖 OpenGL，在 Linux 平台还依赖 X11 等环境，这些库都无法被轻易地动态链接。
2. 在 macOS 系统下，我们可以动态链接系统的 OpenGL 和一些相关的库。

## rar

1. rar 扩展目前在 macOS x86_64 环境下与 `common` 扩展集合编译 phpmicro 存在问题。

## pgsql

~~pgsql ssl 连接与 openssl 3.2.0 不兼容。相关链接：~~

- ~~<https://github.com/Homebrew/homebrew-core/issues/155651>~~
- ~~<https://github.com/Homebrew/homebrew-core/pull/155699>~~
- ~~<https://github.com/postgres/postgres/commit/c82207a548db47623a2bfa2447babdaa630302b9>~~

pgsql 16.2 修复了这个 Bug，现在正常工作了。

在 pgsql 使用 SSL 连接时，可能存在 `error:80000002:system library::No such file or directory` 错误，
解决办法详见 [FAQ - 无法使用 ssl](../faq/#无法使用-ssl)。

## openssl

使用基于 openssl 的扩展（如 curl、pgsql 等网络库）时，可能存在 `error:80000002:system library::No such file or directory` 错误，
解决办法详见 [FAQ - 无法使用 ssl](../faq/#无法使用-ssl)。

## password-argon2

1. password-argon2不是一个标准的扩展。`password_hash` 函数的 `PASSWORD_ARGON2ID` 算法需要 libsodium 或 libargon2 才能工作。
2. 使用 password-argon2 可以为此启用多线程支持。

## ffi

1. 由于 musl libc 静态链接的限制，无法加载动态库，因此无法使用 ffi。
   如果您需要使用 ffi 扩展，请参阅 [使用 GNU libc 编译 PHP](./build-with-glibc)。
2. macOS 支持 ffi 扩展，但某些内核不包含调试符号时会出现错误。
3. Windows x64 支持 ffi 扩展。

## xhprof

xhprof 扩展包含三部分：`xhprof_extension`、`xhprof_html`、`xhprof_libs`。编译的二进制中只包含 `xhprof_extension`。
如果需要使用 xhprof，请到 [pecl.php.net/package/xhprof](http://pecl.php.net/package/xhprof) 下载源码，指定 `xhprof_libs` 和 `xhprof_html` 路径来使用。

## event

event 扩展在 macOS 系统下编译后暂无法使用 `openpty` 特性。相关 Issue：

- [static-php-cli#335](https://github.com/crazywhalecc/static-php-cli/issues/335)

## parallel

parallel 扩展只支持 PHP 8.0 及以上版本，并只支持 ZTS 构建（`--enable-zts`）。

## spx

1. SPX 目前不支持 Windows，且官方仓库也不支持静态编译，static-php-cli 使用了 [修改版本](https://github.com/static-php/php-spx)。

## mimalloc

1. 从技术上讲，这不是扩展，而是一个库。
2. 在 Linux 或 macOS 上使用 `--with-libs="mimalloc"` 进行构建将覆盖默认分配器。
3. 目前，这还处于实验阶段，但建议在线程环境中使用。
