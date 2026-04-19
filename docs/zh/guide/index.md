# 构建指南

## StaticPHP 是什么

StaticPHP 是一个构建工具，能够将 PHP 解释器与你所需的扩展一起编译成一个独立的二进制文件，无需在目标系统上预先安装 PHP 或任何依赖库。
构建产物可以直接分发和运行，适用于 Linux、macOS 和 Windows 平台。

## 为什么要构建静态 PHP

普通 PHP 安装依赖系统环境：你需要先安装 PHP、再装扩展、再处理各个发行版之间的差异。
将 PHP 构建为静态二进制之后，这些问题都不再存在——你得到的是一个单文件可执行程序，在任何相同架构的系统上开箱即用。

典型使用场景：

- **部署命令行工具**：把 PHP 工具（如 Composer、PHPStan、自研 CLI）打包后直接分发，用户无需安装 PHP。
- **容器和嵌入式环境**：用最小体积的静态 PHP 替代臃肿的基础镜像。
- **服务端应用**：构建包含 FPM 或 FrankenPHP SAPI 的静态二进制，部署更简单，不依赖宿主机环境。

## phpmicro：把 PHP 和你的代码打包成一个文件

[phpmicro](https://github.com/easysoft/phpmicro) 是一个第三方 PHP SAPI，StaticPHP 对其提供原生支持。
它能将 PHP 解释器本身和你的 `.php` 源文件（或 `.phar` 打包文件）合并成单个自解压可执行文件（`sfx`）。

```
micro.sfx + your-app.phar = your-app  （可直接运行，无任何依赖）
```

这特别适合分发 PHP 编写的命令行工具：用户拿到的只是一个普通的可执行文件，完全感知不到背后是 PHP。

## 改善你的项目分发与部署

**取代臃肿的 Docker 基础镜像**

官方 `php:8.x` 镜像动辄数百 MB，大多数情况下只是为了提供一个 PHP 运行环境。
改用静态 PHP 二进制配合极简基础镜像（甚至 `FROM scratch`），镜像体积可以压缩到个位数 MB，启动速度也更快。

**构建可分发的 PHP CLI 工具**

用 [symfony/console](https://symfony.com/doc/current/components/console.html) 或 [Laravel Zero](https://laravel-zero.com) 写好你的 CLI 程序，
再用 [Box](https://github.com/box-project/box) 打包成 `.phar`，最后通过 phpmicro 合并为单文件可执行程序。
最终产物可以直接分发，用户无需安装任何 PHP 环境，和 Go、Rust 工具的体验完全一致。

**基于 FrankenPHP 构建单文件 Web 应用**

[FrankenPHP](https://frankenphp.dev) 是一个现代 PHP 应用服务器，内置 HTTP/2、HTTP/3 和 HTTPS 自动管理。
StaticPHP 支持将 FrankenPHP 连同所需扩展一起静态编译，
最终产物是一个包含完整 Web 服务器的单一可执行文件，无需 Nginx、PHP-FPM，直接部署即可运行。

## 接下来

- [安装 SPC](./installation) — 安装 StaticPHP 构建工具
- [第一次构建](./first-build) — 完整流程演示：从下载源码到得到可执行文件
- [命令行参考](./cli-reference) — 所有命令与选项速查
