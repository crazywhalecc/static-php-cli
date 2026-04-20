---
outline: 'deep'
---

# PHP SAPI 构建参考

::: tip
如果你采用的是 spc 二进制方式安装，请将本章节中的所有 `spc` 替换为 `./spc` 或 `.\spc.exe`。

如果你采用的是源码安装，请将 `spc` 替换为 `bin/spc`。
:::

本页详细介绍 StaticPHP 支持的各类 PHP SAPI 的构建参数和使用方式。

## 概览

| SAPI | 构建参数 | 产物路径（Linux/macOS）| 产物路径（Windows）| 平台支持 |
|---|---|---|---|---|
| cli | `--build-cli` | `buildroot/bin/php` | `buildroot/bin/php.exe` | Linux、macOS、Windows |
| fpm | `--build-fpm` | `buildroot/bin/php-fpm` | — | Linux、macOS |
| micro | `--build-micro` | `buildroot/bin/micro.sfx` | `buildroot/bin/micro.sfx` | Linux、macOS、Windows |
| embed | `--build-embed` | `buildroot/lib/libphp.a` | `buildroot/lib/php8embed.lib` | Linux、macOS、Windows |
| frankenphp | `--build-frankenphp` | `buildroot/bin/frankenphp` | `buildroot/bin/frankenphp.exe` | Linux、macOS、Windows |

## cli

`cli` 是标准的 PHP 命令行程序，适用于在终端执行 PHP 脚本、交互式 shell 等场景。

### 构建

```bash
spc build:php "bcmath,openssl,curl" --build-cli
```

Windows 下产物为 `buildroot/bin/php.exe`，其他平台为 `buildroot/bin/php`。

完整选项参见 [build:php — SAPI 选择](./cli-reference#sapi-selection) 和 [build:php — 通用构建选项](./cli-reference#common-build-options)。

### 使用

```bash
# 查看版本和已加载扩展
./buildroot/bin/php -v
./buildroot/bin/php -m

# 执行脚本
./buildroot/bin/php your-script.php

# 交互模式
./buildroot/bin/php -a
```

### php.ini 路径

静态编译的 PHP cli 按以下顺序搜索 `php.ini`：

1. 命令行参数 `-c /path/to/php.ini` 指定的路径
2. `PHP_INI_PATH` 环境变量指定的路径
3. 编译时通过 `--with-config-file-path` 指定的目录（默认为 `/usr/local/etc/php`）

可以通过 `./buildroot/bin/php --ini` 查看 PHP 实际使用的 ini 路径。

### 硬编码 INI

使用 `-I` 参数可以在编译时将 INI 配置硬编码到二进制中，无需额外的 `php.ini` 文件：

```bash
spc build:php "bcmath,pcntl" --build-cli -I "memory_limit=4G" -I "disable_functions=system,exec"
```

硬编码 INI 适用于 `cli`、`micro`、`embed` SAPI。

## fpm

`fpm`（FastCGI Process Manager）与 Nginx、Apache 等 Web 服务器配合使用，适用于传统的 Web 应用部署场景。

::: warning
`fpm` 不支持 Windows 平台。
:::

### 构建

```bash
spc build:php "bcmath,openssl,curl,pdo_mysql" --build-fpm
```

产物为 `buildroot/bin/php-fpm`。

完整选项参见 [build:php — SAPI 选择](./cli-reference#sapi-selection) 和 [build:php — 通用构建选项](./cli-reference#common-build-options)。

### 使用

将 `buildroot/bin/php-fpm` 拷贝到服务器，像普通的 `php-fpm` 一样使用：

```bash
# 查看版本
./buildroot/bin/php-fpm -v

# 指定配置文件启动
./buildroot/bin/php-fpm -c /path/to/php.ini -y /path/to/php-fpm.conf

# 测试配置文件
./buildroot/bin/php-fpm -t
```

### 与 Nginx 配合使用示例

```nginx
server {
    listen 80;
    root /var/www/html;
    index index.php;

    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

`php-fpm.conf` 示例：

```ini
[global]
pid = /var/run/php-fpm.pid
error_log = /var/log/php-fpm.log

[www]
listen = 127.0.0.1:9000
pm = dynamic
pm.max_children = 5
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 3
```

## micro

`micro` 是基于 [phpmicro](https://github.com/easysoft/phpmicro) 的自包含可执行文件 SAPI。通过 `spc micro:combine` 命令，可以将 `micro.sfx` 与 PHP 代码合并为一个独立的可执行文件，无需在目标机器上安装 PHP。

### 构建

```bash
spc build:php "bcmath,phar,openssl,curl" --build-micro
```

产物为 `buildroot/bin/micro.sfx`。

完整选项参见 [build:php — SAPI 选择](./cli-reference#sapi-selection)、[build:php — 通用构建选项](./cli-reference#common-build-options) 和 [build:php — micro 专用选项](./cli-reference#micro-options)。

### 打包应用

使用 `micro:combine` 命令将 PHP 脚本或 phar 文件打包：

```bash
# 打包 PHP 脚本
echo "<?php echo 'Hello, World!' . PHP_EOL;" > hello.php
spc micro:combine hello.php --output=hello
./hello

# 打包 phar 文件
spc micro:combine your-app.phar --output=your-app
./your-app
```

### 注入 INI 配置

打包时可以通过命令行参数或 ini 文件注入运行时配置：

```bash
# 通过命令行参数注入（-I 是 --with-ini-set 的简写）
spc micro:combine your-app.phar --output=your-app -I "memory_limit=512M" -I "curl.cainfo=/etc/ssl/certs/ca-certificates.crt"

# 通过 ini 文件注入（-N 是 --with-ini-file 的简写）
spc micro:combine your-app.phar --output=your-app -N /path/to/custom.ini
```

::: tip
`-I` 注入的 INI 是运行时配置，通过在 `micro.sfx` 末尾追加特殊结构实现。这与编译时用 `-I` 硬编码 INI 不同，两者可以共存。
:::

### 伪装为 cli SAPI

部分框架会检查 `PHP_SAPI` 的值，并限制在非 `cli` 环境下运行。`micro` 的 `PHP_SAPI` 默认值为 `micro`，可通过编译参数让其伪装为 `cli`：

```bash
spc build:php "bcmath,phar" --build-micro --with-micro-fake-cli
```

### 指定自定义 micro.sfx 路径

```bash
spc micro:combine your-app.phar --output=your-app --with-micro=/path/to/your/micro.sfx
```

### 关于 phar 的路径问题

将应用打包为 phar 后，phar 内部使用相对路径可能与预期不符。请参考[开发者文档 - Phar 目录问题](../develop/structure)了解详情。

## embed

`embed` SAPI 将 PHP 编译为静态库（Linux/macOS 下为 `libphp.a`，Windows 下为 `php8embed.lib`），可嵌入到其他 C/C++ 程序中运行 PHP 代码。

### 构建

```bash
spc build:php "bcmath,openssl" --build-embed
```

产物：
- Linux/macOS：`buildroot/lib/libphp.a`，头文件在 `buildroot/include/`
- Windows：`buildroot/lib/php8embed.lib`，头文件在 `buildroot/include/`

完整选项参见 [build:php — SAPI 选择](./cli-reference#sapi-selection)、[build:php — 通用构建选项](./cli-reference#common-build-options) 和 [build:php — embed 专用选项](./cli-reference#embed-options)。

::: tip
如何将 `libphp.a` / `php8embed.lib` 链接到你自己的项目（包括编译器选择、`dev:shell` 使用方式和完整 C 示例），将在开发者文档中专门介绍。
:::

## frankenphp

`frankenphp` 是基于 [FrankenPHP](https://github.com/php/frankenphp) 的现代 PHP 应用服务器，内置 Caddy，支持 HTTP/2、HTTP/3、自动 HTTPS 等特性。

::: tip
StaticPHP 构建出的 `frankenphp` 是单个完全自包含的可执行文件。这与 FrankenPHP 官方提供的发行版不同，官方版本为动态链接二进制，需要单独安装 PHP。
:::

::: warning
FrankenPHP 必须启用线程安全模式，构建时务必加上 `--enable-zts`。
:::

### 构建

```bash
spc build:php "bcmath,openssl,curl,pdo_mysql" --build-frankenphp --enable-zts
```

Linux/macOS 下产物为 `buildroot/bin/frankenphp`，Windows 下为 `buildroot/bin/frankenphp.exe`。

完整选项参见 [build:php — SAPI 选择](./cli-reference#sapi-selection)、[build:php — 通用构建选项](./cli-reference#common-build-options) 和 [build:php — frankenphp 专用选项](./cli-reference#frankenphp-options)。

### 使用

```bash
# 查看版本
./buildroot/bin/frankenphp version

# 以 PHP 内置服务器模式运行（用于开发调试）
./buildroot/bin/frankenphp php-server

# 运行 Worker 模式
./buildroot/bin/frankenphp run --config /path/to/Caddyfile
```

更多用法请参考 [FrankenPHP 官方文档](https://frankenphp.dev/docs/)。

## 动态扩展加载

静态 PHP 二进制是否能够通过 `dl()` 在运行时加载扩展，取决于其链接方式。

**macOS** — 构建产物始终动态链接系统库，支持通过 `dl()` 或 `php.ini` 在运行时加载 `.so` 扩展。

**Linux** — StaticPHP 默认构建目标为 `native-native-musl`：完全静态链接 musl libc 的二进制。由于运行时不存在动态链接器，`dl()` 被禁用，FFI 扩展无法使用，也无法加载任何外部 `.so` 扩展。

如需在 Linux 上支持动态扩展加载，请在构建前设置 `SPC_TARGET` 环境变量：

```bash
SPC_TARGET=native-native-gnu.2.17 spc build:php "bcmath,openssl" --build-cli
```

如果你采用的是源码安装，也可以在 `config/env.ini` 中设置 `SPC_TARGET=native-native-gnu.2.17`，将其作为所有构建的默认值。

这将使用 Zig 工具链构建出一个准静态二进制，动态链接 glibc 2.17，可运行于大多数现代 GNU/Linux 发行版，无需 Docker，也无需额外的交叉编译工具链。该产物支持 `dl()`、FFI 和运行时加载 `.so` 扩展，但无法运行于 Alpine Linux 等基于 musl 的系统。

**Windows** — Windows 上的 PHP 扩展均以 `.dll` 形式分发，且依赖官方动态构建的 PHP 中附带的 DLL 文件。StaticPHP 构建的静态 PHP 可执行文件不包含这些 DLL，因此 Windows 不支持动态扩展加载，所有扩展必须在构建时静态编译进去。

