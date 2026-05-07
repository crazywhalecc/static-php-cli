# 开发简介

本章节将介绍 StaticPHP 的开发流程，以及了解 StaticPHP 内部工作原理所需的基础知识。

## 概述

StaticPHP 是一个静态二进制的构建工具，核心功能是管理构建流程，包括下载和配置 PHP 源码、处理扩展依赖、调用底层构建系统（如 Docker 或本地编译器）等。

从开发的角度来看，StaticPHP 本身是一个开放的框架，它提供了静态构建包括 PHP 在内的各种开源工具的能力。项目主要由 [@crazywhalecc](https://github.com/crazywhalecc) 和 [@henderkes](https://github.com/henderkes) 维护，由广大社区成员贡献代码、完善构建脚本和修复问题。

你可以将 StaticPHP 当作一个典型的 PHP 开发的 CLI 项目来看待，它使用了 [symfony/console](https://symfony.com/doc/current/components/console.html) 来构建命令行界面。

## 开发环境

要开始开发 StaticPHP，你需要设置一个 PHP 开发环境，安装必要的依赖，并了解项目的构建流程。

StaticPHP 的开发环境要求如下：

- PHP 8.4 或更高版本
- Composer
- Git
- PHP 扩展：`curl,dom,filter,mbstring,openssl,pcntl,phar,posix,sodium,tokenizer,xml,xmlwriter`

> 这些 PHP 扩展是 StaticPHP 的 `dev` 环境依赖。

以下是一些基本步骤：

1. 克隆项目代码：

    ```bash
    git clone https://github.com/crazywhalecc/static-php-cli.git
    cd static-php-cli
    ```
2. 安装 PHP 依赖：

    ```bash
    composer install
    ```

3. 运行测试：

    ```bash
    bin/spc --version
    ```

------------------------------

你可以继续阅读 [项目结构](./structure) 来深入了解 StaticPHP 的框架结构。
