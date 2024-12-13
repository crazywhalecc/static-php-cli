# 常见问题

这里将会编写一些你容易遇到的问题。目前有很多，但是我需要花时间来整理一下。

## php.ini 的路径是什么？

在 Linux、macOS 和 FreeBSD 上，`php.ini` 的默认路径是 `/usr/local/etc/php/php.ini`。
在 Windows 中，路径是 `C:\windows\php.ini` 或 `php.exe` 所在的当前目录。
可以在 *nix 系统中使用手动构建选项 `--with-config-file-path` 来更改查找 `php.ini` 的目录。

此外，在 Linux、macOS 和 FreeBSD 上，`/usr/local/etc/php/conf.d` 目录中的 `*.ini` 文件也会被加载。
在 Windows 中，该路径默认为空。
可以使用手动构建选项 `--with-config-file-scan-dir` 更改该目录。

PHP 默认也会从 [其他标准位置](https://www.php.net/manual/zh/configuration.file.php) 中搜索 `php.ini`。

## 静态编译的 PHP 可以安装扩展吗

因为传统架构下的 PHP 安装扩展的原理是使用 `.so` 类型的动态链接的库方式安装新扩展，而使用本项目编译的静态链接的 PHP 无法**直接**使用动态链接库安装新扩展。

对于 macOS 平台来说，macOS 下的几乎所有二进制文件都无法真正纯静态链接，几乎所有二进制文件都会链接 macOS 的系统库：`/usr/lib/libresolv.9.dylib` 和 `/usr/lib/libSystem.B.dylib`。
所以在 macOS 系统下，在特定的编译条件下可以使用静态编译的 php 二进制文件，同时使用动态链接的扩展：

1. 使用 `--no-strip` 参数，将不会对二进制文件去除调试符号等信息，以供使用 `Xdebug` 等外部 Zend 扩展。
2. 如果要编译某些 Zend 扩展，使用 Homebrew、MacPorts、源码编译的形式，在所在的操作系统安装一个普通版本的 PHP。
3. 使用 `phpize && ./configure && make` 命令编译想要使用的扩展。
4. 将扩展文件 `xxxx.so` 拷贝到外部，使用静态编译的 PHP 二进制，例如使用 Xdebug 扩展：`cd buildroot/bin/ && ./php -d "zend_extension=/path/to/xdebug.so"`。

```bash
# 构建静态 php-cli
bin/spc build ffi --build-cli --no-strip
```

对于 Linux 平台来说，目前的编译结果为纯静态链接的二进制文件，无法使用动态链接库安装新扩展。

## 可以支持 Oracle 数据库扩展吗

部分依赖库闭源的扩展，如 `oci8`、`sourceguardian` 等，它们没有提供纯静态编译的依赖库文件（`.a`），仅提供了动态依赖库文件（`.so`），
这些扩展无法使用源码的形式编译到 static-php-cli 中，所以本项目可能永远也不会支持这些扩展。不过，理论上你可以根据上面的问题在 macOS 下接入和使用这类扩展。

如果你对此类扩展有需求，或者大部分人都对这些闭源扩展使用有需求，
可以看看有关 [standalone-php-cli](https://github.com/crazywhalecc/static-php-cli/discussions/58) 的讨论。欢迎留言。

## 支持 Windows 吗

该项目目前已支持 Windows，但支持的扩展数量较少，Windows 的支持并不完美，主要有以下几个问题：

1. Windows 的编译流程与 *nix 不同，使用的工具链也不同，编译各个扩展的依赖库使用的编译工具也几乎完全不同。
2. Windows 版本的需求也会根据所有使用本项目的人的需求推进，如果有很多人需要，我会尽快支持相关扩展。

## 使用 micro 可以保护我的源码吗

不可以。micro.sfx 本质上是将 php 和 php 代码结合为一个文件，没有 PHP 代码编译或加密的过程。
首先 php-src 是 PHP 代码的官方解释器，而且现在市面上还没有一个能兼容主流分支的 PHP 编译器。
之前我在网上看到有一个项目是 BPC（Binary PHP Compiler？）可以把 PHP 编译为二进制，但是限制也是很多很多。

加密保护代码的方向和编译也不是一回事，编译过后也可以通过逆向工程等方式拿到代码，真正保护还是通过加壳、加密代码等手段进行。

所以本项目（static-php-cli）、相关项目（lwmbs、swoole-cli）都是提供一个对 php-src 源码的便捷编译工具，
本项目和相关项目引用的 phpmicro 也仅仅是 PHP 的 sapi 接口封装，而不是 PHP 代码的编译工具。
PHP 代码的编译器是完全不同的项目，因此不会考虑额外的情况。如果你对加密感兴趣，可以考虑使用现有的加密技术，如 Swoole Compiler、Source Guardian 等。

## 无法使用 ssl

使用 curl、pgsql 等 请求 HTTPS 网站或建立 SSL 连接时，可能存在 `error:80000002:system library::No such file or directory` 错误，
这个错误是由于静态编译的 PHP 未通过 `php.ini` 指定 `openssl.cafile` 导致的。

你可以在使用 PHP 前指定 `php.ini`，并在 INI 内添加 `openssl.cafile=/path/to/your-cert.pem` 来解决这个问题。

对于 Linux 系统，你可以从 curl 官方网站下载 [cacert.pem](https://curl.se/docs/caextract.html) 文件，也可以使用系统自带的证书文件。
有关不同发行版的证书位置，可参考 [Go 标准库](https://go.dev/src/crypto/x509/root_linux.go)。

> INI 配置 `openssl.cafile` 不可以使用 `ini_set()` 函数动态设置，因为 `openssl.cafile` 是一个 `PHP_INI_SYSTEM` 类型的配置，只能在 `php.ini` 文件中设置。

## 为什么不支持旧版本 PHP ？

因为旧版本的 PHP 有很多问题，比如安全问题、性能问题、功能问题等。此外，旧版本的 PHP 很多都无法与最新的依赖库兼容，这也是不支持旧版本 PHP 的原因之一。

你可以使用 static-php-cli 早期编译好的旧版本，如 PHP 8.0，但是不会明确支持早期版本。
