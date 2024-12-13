# 指南

static-php-cli 是一个用于构建静态编译的 PHP 二进制的工具，目前支持 Linux 和 macOS 系统。

在指南章节中，你将了解到如何使用 static-php-cli 构建独立的 php 程序。

- [Action 构建](./action-build)
- [本地构建](./manual-build)
- [扩展列表](./extensions)

## 编译环境

下面是架构支持情况，:gear: 代表支持 GitHub Action 构建，:computer: 代表支持本地构建，空 代表暂不支持。

|         | x86_64            | aarch64           |
|---------|-------------------|-------------------|
| macOS   | :gear: :computer: | :gear: :computer: |
| Linux   | :gear: :computer: | :gear: :computer: |
| Windows | :gear: :computer: |                   |
| FreeBSD | :computer:        | :computer:        |

其中，Linux 目前仅在 Ubuntu、Debian、Alpine 发行版测试通过，其他发行版未进行测试，不能保证编译成功。
对于未经过测试的发行版，可以使用 Docker 等方式本地编译，避免环境导致的问题。

macOS 下支持 x86_64 和 Arm 两种架构，但在其中一个架构上编译的二进制无法直接在另一个架构上使用。
Rosetta 2 不能保证 Arm 架构编译的程序可以完全运行在 x86_64 环境下。

Windows 目前只支持 x86_64 架构，不支持 32 位 x86、不支持 arm64 架构。

## PHP 支持版本

目前，static-php-cli 对 PHP 8.1 ~ 8.4 版本是支持的，对于 PHP 8.0 及更早版本理论上支持，只需下载时选择早期版本即可。
但由于部分扩展和特殊组件已对早期版本的 PHP 停止了支持，所以 static-php-cli 不会明确支持早期版本。
我们推荐你编译尽可能新的 PHP 版本，以获得更好的体验。
