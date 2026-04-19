# 对 PHP 源码的修改

由于 static-php-cli 在静态编译过程中为了实现良好的兼容性、性能和安全性，对 PHP 源码进行了一些修改。下面是目前对 PHP 源码修改的说明。

## micro 相关补丁

基于 phpmicro 项目提供的补丁，static-php-cli 对 PHP 源码进行了一些修改，以适应静态编译的需求。[补丁列表](https://github.com/easysoft/phpmicro/tree/master/patches) 包含：

目前 static-php-cli 在编译时用到的补丁有：

- static_opcache
- static_extensions_win32
- cli_checks
- disable_huge_page
- vcruntime140
- win32
- zend_stream
- cli_static
- macos_iconv
- phar

## PHP <= 8.1 libxml 补丁

因为 PHP 官方仅对 8.1 进行安全更新，旧版本停止更新，所以 static-php-cli 对 PHP 8.1 及以下版本应用了在新版本 PHP 中已经应用的 libxml 编译补丁。

## gd 扩展 Windows 补丁

在 Windows 下编译 gd 扩展需要大幅改动 `config.w32` 文件，static-php-cli 对 gd 扩展进行了一些修改，使其在 Windows 下编译更加方便。

## yaml 扩展 Windows 补丁

yaml 扩展在 Windows 下编译需要修改 `config.w32` 文件，static-php-cli 对 yaml 扩展进行了一些修改，使其在 Windows 下编译更加方便。

## static-php-cli 版本信息插入

static-php-cli 在编译时会在 PHP 版本信息中插入 static-php-cli 的版本信息，以便于识别。

## 加入硬编码 INI 的选项

在使用 `-I` 参数硬编码 INI 到静态 PHP 的功能中，static-php-cli 会修改 PHP 源码以插入硬编码内容。

## Linux 系统修复补丁

部分编译环境可能缺少一些头文件或库，static-php-cli 会在编译时自动修复这些问题，如：

- HAVE_STRLCAT missing problem
- HAVE_STRLCPY missing problem

## Windows 系统下 Fiber 问题修复补丁

在 Windows 下编译 PHP 时，Fiber 扩展会出现一些问题，static-php-cli 会在编译时自动修复这些问题（修改 php-src 的 `config.w32`）。
