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
    - 支持版本: 7.4, 8.0, 8.1, 8.2

## 使用（WIP）

> 你正在看的是重构后的 static-php-cli 编译项目，新项目还未完全重构，所以还有大量的扩展没有完成。
> 你可以阅读使用 bash 编写的仅为 Linux 系统使用的静态编译脚本和 Docker，详见 bash-version 分支。 旧版本未来将会切换为次要版本，提供有限支持。

未来会提供一个直接可使用的 phar 包和一个 phpmicro 打包的二进制文件，你可以直接从 Release 中获取并使用：

### 编译

```bash
chmod +x spc
# 拉取所有依赖库
./spc fetch --all
# 构建包含 bcmath,openssl,tokenizer,sqlite3,pdo_sqlite,ftp,curl 扩展的 php-cli 和 micro.sfx
./spc build "bcmath,openssl,tokenizer,sqlite3,pdo_sqlite,ftp,curl" --build-all
```

### 使用 php-cli

采用参数 `--build-all` 或不添加 `--build-micro` 参数时，最后编译结果会输出一个 `./php` 的二进制文件，此文件可分发、可直接使用。
该文件编译后会存放在 `source/php-src/sapi/cli/` 目录中，拷贝出来即可。

```bash
./php -v
./php -m
./php your_code.php
```

### 使用 micro.sfx

采用项目参数 `--build-all` 或 `--build-micro` 时，最后编译结果会输出一个 `./micro.sfx` 的文件，此文件需要配合你的 PHP 源码使用。
该文件编译后会存放在 `source/php-src/sapi/micro/` 目录中，拷贝出来即可。

使用时应准备好你的项目源码文件，可以是单个 PHP 文件，也可以是 Phar 文件。

```bash
echo "<?php echo 'Hello world' . PHP_EOL;" > code.php
cat micro.sfx code.php > single-app && chmod +x single-app
./single-app

# 如果打包 PHAR 文件，仅需把 code.php 更换为 phar 文件路径即可
```

> 有些情况下的 phar 文件可能无法在 micro 环境下运行。

## 项目支持情况

- [X] 基础结构编写（采用 `symfony/console`）
- [X] 错误处理
- [X] macOS 支持
- [ ] Windows 支持
- [X] Linux 支持
- [X] PHP 7.4 支持

## 支持的扩展情况

[扩展支持列表](/ext-support.md)

## 贡献

目前支持的扩展较少，如果缺少你需要的扩展，可发起 Issue。如果你对本项目较熟悉，也欢迎为本项目发起 Pull Request。

贡献基本原则如下：

- 项目采用了 php-cs-fixer、phpstan 作为代码规范工具，贡献前请对更新的代码执行 `composer analyze` 和 `composer cs-fix`。
- 涉及到其他开源库的部分应提供对应库的协议，同时对配置文件在修改后采用命令 `sort-config` 排序。有关排序的命令，见文档。
- 应遵循命名规范，例如扩展名称应采取 PHP 内注册的扩展名本身，外部库名应遵循项目本身的名称，内部逻辑的函数、类名、变量等应遵循驼峰、下划线等格式，禁止同一模块混用。
- 涉及编译外部库的命令和 Patch 时应注意兼容不同操作系统。

## 开源协议

本项目依据旧版本惯例采用 MIT License 开源，新版本采用了部分项目的源代码做参考，特别感谢：

- [dixyes/lwmbs](https://github.com/dixyes/lwmbs)（木兰宽松许可证）
- [swoole/swoole-cli](https://github.com/swoole/swoole-cli)（Apache 2.0 LICENSE+SWOOLE-CLI LICENSE）

因本项目的特殊性，使用项目编译过程中会使用很多其他开源项目，例如 curl、protobuf 等，它们都有各自的开源协议。
请在编译完成后，使用命令 `dump-license`(TODO) 导出项目使用项目的开源协议，并遵守对应项目的 LICENSE。
