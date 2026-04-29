# 安装 StaticPHP

## 系统要求

| 平台 | 架构 | 说明 |
|---|---|---|
| Linux | x86_64、aarch64 | 支持主流发行版（Alpine、Debian/Ubuntu、RHEL/CentOS 等） |
| macOS | x86_64 (Intel)、arm64 (Apple Silicon) | 需要 macOS 12 或更高版本 |
| Windows | x86_64 | 需要 Windows 10 Build 17063 或更高版本 |

::: tip
Linux 下，glibc 环境（Debian、Ubuntu、Arch 等）和 musl 环境（Alpine）均受支持。
`doctor` 命令会自动检测当前环境并在必要时引导安装合适的工具链。
:::

StaticPHP 有多种安装方式，选择适合你的场景：

| 方式 | 适合谁 |
|---|---|
| 预编译二进制 | 大多数用户，直接下载开箱即用 |
| 从源码安装 | 参与开发、或需要修改核心构建逻辑的开发者 |
| Vendor 模式 | 在已有 PHP 项目中集成 StaticPHP 能力 |

## 预编译二进制

spc 无须任何依赖，下载即可运行，支持 Linux、macOS 和 Windows。

> spc 本身是由 StaticPHP 构建的静态 PHP 二进制，幽默地说：我们用 StaticPHP 构建了 StaticPHP 的构建工具。

```shell
# Linux x86_64
curl -#fSL https://dl.static-php.dev/v3/spc-bin/latest/spc-linux-x86_64 -o spc
# Linux arm64
curl -#fSL https://dl.static-php.dev/v3/spc-bin/latest/spc-linux-aarch64 -o spc
# macOS x86_64 (Intel)
curl -#fSL https://dl.static-php.dev/v3/spc-bin/latest/spc-macos-x86_64 -o spc
# macOS arm64 (Apple Silicon)
curl -#fSL https://dl.static-php.dev/v3/spc-bin/latest/spc-macos-aarch64 -o spc
# Windows x86_64 (PowerShell)
curl.exe -#fSL https://dl.static-php.dev/v3/spc-bin/latest/spc-windows-x86_64.exe -o spc.exe
```

*nix 系统下载完成后需要赋予可执行权限：

```bash
chmod +x spc && ./spc --version
```

## 从源码安装

适合想参与开发、或需要修改核心注册表和构建脚本的开发者。需要系统已安装 PHP >= 8.4、Composer，以及 `mbstring,posix,pcntl,iconv,phar,zlib` 扩展。

```bash
git clone https://github.com/crazywhalecc/static-php-cli.git --branch v3
cd static-php-cli
composer install
```

如果系统还没有 PHP 和 Composer，可以用内置脚本一键安装运行环境：

::: code-group
```bash [Linux / macOS]
bin/setup-runtime
```
```powershell [Windows]
.\bin\setup-runtime.ps1
.\bin\setup-runtime.ps1 add-path   # 将 runtime/ 加入 PATH
```
:::

脚本执行完成后，会在项目目录下生成 `runtime/` 子目录，其中包含 `php` 和 `composer` 两个可执行文件。安装完成后有两种使用方式：

1. **直接通过路径调用**（无需修改环境变量）：
   ```bash
   runtime/php bin/spc --help
   runtime/php runtime/composer install
   ```

2. **将 `runtime/` 加入 PATH**（之后可直接使用 `php`、`composer`、`bin/spc`）：
   ```bash
   export PATH="/path/to/static-php/runtime:$PATH"
   # 建议写入 ~/.bashrc 或 ~/.zshrc 使其永久生效
   ```

## Vendor 模式

适合在已有 PHP 项目中直接集成 StaticPHP 能力，或通过自定义 registry 支持私有库和扩展的构建。

```bash
composer require crazywhalecc/static-php-cli
```

Vendor 模式的详细用法见 [扩展 StaticPHP](../develop/extending/)。

## 验证构建环境

> **Vendor 模式用户可跳过此步骤。**

安装完成后，运行 `doctor` 检查系统构建工具链是否就绪（cmake、make、编译器等）：

```bash
# 使用 spc 二进制
./spc doctor
# 使用源码安装
bin/spc doctor
```

如有缺失，`--auto-fix` 会尝试自动安装修复：

```bash
./spc doctor --auto-fix
```

检查通过后，继续阅读[第一次构建](./first-build)。
