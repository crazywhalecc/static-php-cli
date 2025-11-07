# 常见问题

这里将会编写一些你容易遇到的问题。目前有很多，但是我需要花时间来整理一下。

## php.ini 的路径是什么？

在 Linux、macOS 和 FreeBSD 上，`php.ini` 的路径是 `/usr/local/etc/php/php.ini`。
在 Windows 中，路径是 `C:\windows\php.ini` 或 `php.exe` 所在的当前目录。
可以在 *nix 系统中使用手动构建选项 `--with-config-file-path` 来更改查找 `php.ini` 的目录。

此外，在 Linux、macOS 和 FreeBSD 上，`/usr/local/etc/php/conf.d` 目录中的 `.ini` 文件也会被加载。
在 Windows 中，该路径默认为空。
可以使用手动构建选项 `--with-config-file-scan-dir` 更改该目录。

PHP 默认也会从 [其他标准位置](https://www.php.net/manual/zh/configuration.file.php) 中搜索 `php.ini`。

## 静态编译的 PHP 可以安装扩展吗？

因为传统架构下的 PHP 安装扩展的原理是使用 `.so` 类型的动态链接的库方式安装新扩展，而使用本项目编译的静态链接的 PHP。但是静态链接在不同操作系统有不同的定义。

首先，对于 Linux 系统，静态链接的二进制文件不会链接系统的动态链接库。纯静态链接的二进制文件（`-all-static`）无法加载动态库，因此无法添加新扩展。
同时，在纯静态模式下，你也不能使用 `ffi` 等扩展来加载外部 `.so` 模块。

你可以使用命令 `ldd buildroot/bin/php` 来检查你在 Linux 下构建的二进制文件是否为纯静态链接。

如果你 [构建基于 GNU libc 的 PHP](../guide/build-with-glibc)，你可以使用 `ffi` 扩展来加载外部 `.so` 模块，并加载具有相同 ABI 的 `.so` 扩展。

例如，你可以使用以下命令构建一个与 glibc 动态链接的静态 PHP 二进制文件，支持 FFI 扩展并加载相同 PHP 版本和相同 TS 类型的 `xdebug.so` 扩展：

```bash
bin/spc-gnu-docker download --for-extensions=ffi,xml --with-php=8.4
bin/spc-gnu-docker build ffi,xml --build-cli --debug

buildroot/bin/php -d "zend_extension=/path/to/php{PHP_VER}-{ts/nts}/xdebug.so" --ri xdebug
```

对于 macOS 平台，macOS 下的几乎所有二进制文件都无法真正纯静态链接，几乎所有二进制文件都会链接 macOS 系统库：`/usr/lib/libresolv.9.dylib` 和 `/usr/lib/libSystem.B.dylib`。
因此，在 macOS 上，你可以**直接**使用 SPC 构建具有动态链接扩展的静态编译 PHP 二进制文件：

1. 使用 `--build-shared=XXX` 选项构建共享扩展 `xxx.so`。例如：`bin/spc build bcmath,zlib --build-shared=xdebug --build-cli`
2. 你将获得 `buildroot/modules/xdebug.so` 和 `buildroot/bin/php`。
3. `xdebug.so` 文件可用于版本和线程安全相同的 php。

对于 Windows 平台，由于官方构建的扩展（如 `php_yaml.dll`）强制使用了 `php8.dll` 动态库作为链接，静态构建的 PHP 不包含任何系统库以外的动态库，
所以 Windows 下无法加载官方构建的动态扩展。 由于 static-php-cli 还暂未支持构建动态扩展，所以目前还没有让 static-php 加载动态扩展的方法。

不过，Windows 可以正常使用 `FFI` 扩展加载其他的 dll 文件并调用。

## 可以支持 Oracle 数据库扩展吗？

部分依赖库闭源的扩展，如 `oci8`、`sourceguardian` 等，它们没有提供纯静态编译的依赖库文件（`.a`），仅提供了动态依赖库文件（`.so`），
这些扩展无法使用源码的形式编译到 static-php-cli 中，所以本项目可能永远也不会支持这些扩展。不过，理论上你可以根据上面的问题在 macOS 和 Linux 下接入和使用这类扩展。

如果你对此类扩展有需求，或者大部分人都对这些闭源扩展使用有需求，
可以看看有关 [standalone-php-cli](https://github.com/crazywhalecc/static-php-cli/discussions/58) 的讨论。欢迎留言。

## 支持 Windows 吗？

该项目目前支持 Windows，但支持的扩展数量较少。Windows 支持并不完美。主要有以下问题：

1. Windows 的编译过程与 *nix 不同，使用的工具链也不同。用于编译每个扩展依赖库的编译工具也几乎完全不同。
2. Windows 版本的需求也会根据所有使用本项目的人的需求推进。如果很多人需要，我会尽快支持相关扩展。

## 我可以使用 micro 保护我的源代码吗？

不可以。micro.sfx 本质上是将 php 和 php 代码合并为一个文件，没有编译或加密 PHP 代码的过程。

首先，php-src 是 PHP 代码的官方解释器，市场上没有与主流分支兼容的 PHP 编译器。
我在网上看到一个名为 BPC（Binary PHP Compiler？）的项目可以将 PHP 编译为二进制，但有很多限制。

加密和保护代码的方向与编译不同。编译后，也可以通过逆向工程等方法获得代码。真正的保护仍然通过打包和加密代码等手段进行。

因此，本项目（static-php-cli）和相关项目（lwmbs、swoole-cli）都提供了 php-src 源代码的便捷编译工具。
本项目和相关项目引用的 phpmicro 只是 PHP 的 sapi 接口封装，而不是 PHP 代码的编译工具。
PHP 代码的编译器是一个完全不同的项目，因此不考虑额外的情况。
如果你对加密感兴趣，可以考虑使用现有的加密技术，如 Swoole Compiler、Source Guardian 等。

## 无法使用 ssl

**更新：该问题已在最新版本的 static-php-cli 中修复，现在默认读取系统的证书文件。如果你仍然遇到问题，请尝试下面的解决方案。**

使用 curl、pgsql 等请求 HTTPS 网站或建立 SSL 连接时，可能会出现 `error:80000002:system library::No such file or directory` 错误。
此错误是由于静态编译的 PHP 未通过 `php.ini` 指定 `openssl.cafile` 导致的。

你可以通过在使用 PHP 前指定 `php.ini` 并在 INI 中添加 `openssl.cafile=/path/to/your-cert.pem` 来解决此问题。

对于 Linux 系统，你可以从 curl 官方网站下载 [cacert.pem](https://curl.se/docs/caextract.html) 文件，也可以使用系统自带的证书文件。
有关不同发行版的证书位置，请参考 [Golang 文档](https://go.dev/src/crypto/x509/root_linux.go)。

> INI 配置 `openssl.cafile` 不能使用 `ini_set()` 函数动态设置，因为 `openssl.cafile` 是 `PHP_INI_SYSTEM` 类型的配置，只能在 `php.ini` 文件中设置。

## 为什么不支持旧版本的 PHP？

因为旧版本的 PHP 有很多问题，如安全问题、性能问题和功能问题。此外，许多旧版本的 PHP 与最新的依赖库不兼容，这也是不支持旧版本 PHP 的原因之一。

你可以使用 static-php-cli 早期编译的旧版本，如 PHP 8.0，但不会明确支持早期版本。
