# 指南

static-php-cli 是一个用于构建静态编译的 PHP 二进制的工具，目前支持 Linux 和 macOS 系统。

在指南章节中，你将了解到如何使用 static-php-cli 构建独立的 php 程序。

- [本地构建](./manual-build)
- [Action 构建](./action-build)
- [扩展列表](./extensions)

## 编译环境

下面是架构支持情况，:gear: 代表支持 GitHub Action 构建，:computer: 代表支持本地构建，空 代表暂不支持。

|         | x86_64            | aarch64           |
|---------|-------------------|-------------------|
| macOS   | :gear: :computer: | :gear: :computer: |
| Linux   | :gear: :computer: | :gear: :computer: |
| Windows | :gear: :computer: |                   |
| FreeBSD | :computer:        | :computer:        |

当前支持编译的 PHP 版本：

> :warning: 部分支持，对于新的测试版和旧版本可能存在问题。
>
> :heavy_check_mark: 支持
>
> :x: 不支持

| PHP Version | Status             | Comment                                                 |
|-------------|--------------------|---------------------------------------------------------|
| 7.2         | :x:                |                                                         |
| 7.3         | :x:                | phpmicro 和许多扩展不支持 7.3、7.4 版本                            |
| 7.4         | :x:                | phpmicro 和许多扩展不支持 7.3、7.4 版本                            |
| 8.0         | :warning:          | PHP 官方已停止 8.0 的维护，我们不再处理 8.0 相关的 backport 支持            |
| 8.1         | :warning:          | PHP 官方仅对 8.1 提供安全更新，在 8.5 发布后我们不再处理 8.1 相关的 backport 支持 |
| 8.2         | :heavy_check_mark: |                                                         |
| 8.3         | :heavy_check_mark: |                                                         |
| 8.4         | :heavy_check_mark: |                                                         |
| 8.5 (beta)  | :warning:          | PHP 8.5 目前处于 beta 阶段                                    |

> 这个表格的支持状态是 static-php-cli 对构建对应版本的支持情况，不是 PHP 官方对该版本的支持情况。

## PHP 支持版本

目前，static-php-cli 对 PHP 8.2 ~ 8.5 版本是支持的，对于 PHP 8.1 及更早版本理论上支持，只需下载时选择早期版本即可。
但由于部分扩展和特殊组件已对早期版本的 PHP 停止了支持，所以 static-php-cli 不会明确支持早期版本。
我们推荐你编译尽可能新的 PHP 版本，以获得更好的体验。
