# static-php-cli
Compile A Statically Linked PHP With Swoole and other Extensions. [English README](README-en.md)

编译纯静态的 PHP Binary 二进制文件，带有各种扩展，让 PHP-cli 应用变得更便携！

同时可以使用 micro 二进制文件，将 PHP 源码和 PHP 二进制构建为一个文件分发！

注：只能编译 CLI 模式，暂不支持 CGI 和 FPM 模式。

[![License](https://img.shields.io/badge/License-MIT-blue.svg)]()

## 编译环境需求

是的，本项目采用 PHP 编写，编译前需要一个 PHP 环境，比较滑稽。
但本项目默认可通过自身构建的 micro 和 static-php 二进制运行，其他只需要包含 tokenizer 扩展和 PHP 版本大于等于 8.0 即可。

- Linux
    - 支持架构: aarch64, amd64
    - 支持发行版: alpine, ubuntu, centos
    - 依赖工具: make, bison, flex, pkg-config, git, autoconf, automake, tar, unzip, gzip, bzip2, cmake
- macOS
    - 支持架构: arm64, x86_64
    - 依赖工具: make, bison, flex, pkg-config, git, autoconf, automake, tar, unzip, xz, gzip, bzip2, cmake
- Windows
    - 支持架构: x86_64
    - 依赖工具: (TODO)
- PHP
    - 支持版本: 8.0, 8.1, 8.2

## 使用（WIP）

> 你正在看的是重构后的 static-php-cli 编译项目，新项目还未完全重构，所以还有大量的扩展没有完成。
> 你可以阅读使用 bash 编写的仅为 Linux 系统使用的静态编译脚本和 Docker，详见 bash-version 分支。 旧版本未来将会切换为次要版本，提供有限支持。

未来会提供一个直接可使用的 phar 包和一个 phpmicro 打包的二进制文件，你可以直接从 Release 中获取并使用：

```bash
chmod +x spc
# 拉取所有依赖库
./spc fetch --all
# 构建包含 bcmath,openssl,swoole 扩展的 php-cli 和 micro.sfx
./spc build "bcmath,openssl,swoole" --build-all
```

## 项目支持情况（WIP）

- [X] 基础结构编写（采用 symfony/console`）
- [X] 错误处理
- [X] macOS 支持
- [ ] Windows 支持
- [ ] Linux 支持
- [X] PHP 7.4 支持

## 支持的扩展情况（WIP）

[扩展支持列表](/ext-support.md)

## 开源协议

本项目依据旧版本惯例采用 MIT License 开源，新版本采用了部分项目的源代码做参考，特别感谢：

- [dixyes/lwmbs](https://github.com/dixyes/lwmbs)（木兰宽松许可证）
- [swoole/swoole-cli](https://github.com/swoole/swoole-cli)（Apache 2.0 LICENSE+SWOOLE-CLI LICENSE）

因本项目的特殊性，使用项目编译过程中会使用很多其他开源项目，例如 curl、protobuf 等，它们都有各自的开源协议。
请在编译完成后，使用命令 `dump-license`(TODO) 导出项目使用项目的开源协议，并遵守对应项目的 LICENSE。
