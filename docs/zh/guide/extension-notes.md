# 扩展注意事项

因为是静态编译，扩展不会 100% 完美编译，而且不同扩展对 PHP、环境都有不同的要求，这里将一一列举。

## curl

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

## swow

1. swow 仅支持 PHP 8.0 ~ 8.4 版本。

## imap

1. 该扩展目前不支持 Kerberos。
2. 由于底层的 c-client、ext-imap 不是线程安全的。 无法在 `--enable-zts` 构建中使用它。
3. 由于该扩展可能会从未来的 PHP 中删除，因此我们建议您寻找替代实现，例如 [Webklex/php-imap](https://github.com/Webklex/php-imap)。

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

1. Xdebug 是一个 Zend 扩展，Xdebug 的功能依赖于 PHP 的 Zend 引擎和底层代码，如果要将其静态编译到 PHP 中，可能需要巨量的 patch 代码，这是不可行的。
2. macOS 平台可以通过在相同平台编译的 PHP 下编译一个 xdebug 扩展，并提取其中的 `xdebug.so` 文件，再在 static-php-cli 中使用 `--no-strip` 参数保留调试符号表，同时加入 `ffi` 扩展。
   编译的 `./php` 二进制可以通过指定 INI 配置并运行，例如`./php -d 'zend_extension=xdebug.so' your-code.php`。

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

1. password-argon2不是一个标准的扩展，它是 `password_hash` 函数的额外算法。
2. 在Linux系统，password-argon2 的依赖库 `libargon2` 与 `libsodium` 库冲突。

## ffi

1. 因为 Linux 系统的限制，虽然可以成功编译 ffi 扩展，但无法使用它加载其他 `so` 扩展。Linux 支持加载 so 扩展的前提是非静态编译，但动态编译和本项目的目的冲突。
2. macOS 支持 ffi 扩展，但是部分内核下不包含调试符号时会出现错误。
3. Windows 支持 ffi 扩展。

## xhprof

xhprof 扩展包含三部分：`xhprof_extension`、`xhprof_html`、`xhprof_libs`。编译的二进制中只包含 `xhprof_extension`。
如果需要使用 xhprof，请到 [pecl.php.net/package/xhprof](http://pecl.php.net/package/xhprof) 下载源码，指定 `xhprof_libs` 和 `xhprof_html` 路径来使用。

## event

event 扩展在 macOS 系统下编译后暂无法使用 `openpty` 特性。相关 Issue：

- [static-php-cli#335](https://github.com/crazywhalecc/static-php-cli/issues/335)

## parallel

parallel 扩展只支持 PHP 8.0 及以上版本，并只支持 ZTS 构建（`--enable-zts`）。

## spx

1. [SPX 扩展](https://github.com/NoiseByNorthwest/php-spx) 只支持非线程模式。
2. SPX 目前不支持 Windows，且官方仓库也不支持静态编译，static-php-cli 使用了 [修改版本](https://github.com/static-php/php-spx)。
