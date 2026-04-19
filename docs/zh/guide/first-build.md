# 第一次构建

本页通过完整的示例演示如何从零开始构建一个静态 PHP 二进制。

::: tip
如果你采用的是 spc 二进制方式安装，请将本章节中的所有 `spc` 替换为 `./spc` 或 `.\spc.exe`。

如果你采用的是源码安装，请将 `spc` 替换为 `bin/spc`。
:::

## 两种构建方式

StaticPHP 提供两种构建方式，根据使用场景选择：

| 方式           | 适合场景                     |
|--------------|--------------------------|
| `craft` 一键构建 | 日常使用、快速上手                |
| 分步构建         | CI/CD 流水线、需要拆分下载与编译阶段的场景 |

## 方式一：`craft` 一键构建（推荐）

`craft` 命令读取一个 `craft.yml` 配置文件，自动完成依赖下载、库编译、PHP 构建的全流程。

### 编写 craft.yml

在当前目录创建 `craft.yml`，声明要编译的 PHP 版本、扩展和目标 SAPI：

```yaml
php-version: 8.4
extensions: bcmath,posix,phar,zlib,openssl,curl,fileinfo,tokenizer
sapi:
  - cli
  - micro
```

不想手动编写？试试[命令行生成器](./cli-generator)自动生成配置。

### 开始构建

```bash
spc craft
```

构建过程依次执行：下载依赖 → 编译依赖库 → 编译 PHP。全程无需人工干预。

如需查看详细日志，加上 `-v`、`-vv` 或 `-vvv` 参数：

```bash
spc craft -v
```

### 查看产物

构建成功后，产物均位于 `buildroot/bin/`：

| SAPI       | 产物路径                                                 |
|------------|------------------------------------------------------|
| cli        | `buildroot/bin/php`（Windows：`buildroot/bin/php.exe`） |
| fpm        | `buildroot/bin/php-fpm`                              |
| micro      | `buildroot/bin/micro.sfx`                            |
| embed      | `buildroot/lib/libphp.a`                             |
| frankenphp | `buildroot/bin/frankenphp`                           |

验证一下 cli 是否可用：

```bash
./buildroot/bin/php -v
./buildroot/bin/php -m
```

## 方式二：分步构建

分步方式适合需要将下载与编译拆分为独立阶段的场景，例如在 CI 中缓存下载内容以加速后续构建。

### 第一步：下载依赖

v3 版本中，你可以省略这一步骤，直接构建想要的内容，StaticPHP 会自动下载所需的依赖库和扩展源码。

但如果你想提前下载，或在网络环境较差的情况下分阶段构建，可以使用 `download` 命令：

```bash
# 按扩展列表下载（推荐，只下载实际需要的内容）
spc download --for-extensions="bcmath,posix,phar,zlib,openssl,curl,fileinfo,tokenizer" --with-php=8.4

# 按依赖包列表下载
spc download "curl,openssl" --with-php=8.4
```

下载内容缓存在 `downloads/` 目录，重复构建时会直接复用。

```bash
# 网络较慢时，可增大并发数和重试次数
spc download --for-extensions=bcmath,openssl,curl --parallel 10 --retry=3

# 优先使用预编译的二进制依赖，跳过源码编译（大幅加速构建）
spc download --for-extensions=bcmath,openssl,curl --prefer-binary
```

### 第二步：构建 PHP

```bash
# 构建 cli SAPI
spc build:php bcmath,phar,zlib,openssl,curl,fileinfo,tokenizer --build-cli

# 同时构建多个 SAPI
spc build:php bcmath,phar,zlib,openssl,curl --build-cli --build-micro
```



#### 常用构建选项

| 选项                   | 说明                                   |
|----------------------|--------------------------------------|
| `--build-cli`        | 构建 cli SAPI                          |
| `--build-fpm`        | 构建 php-fpm（不支持 Windows）              |
| `--build-micro`      | 构建 micro.sfx                         |
| `--build-embed`      | 构建嵌入式 SAPI                           |
| `--build-frankenphp` | 构建 FrankenPHP                        |
| `--enable-zts`       | 启用线程安全（ZTS）版本                        |
| `--no-strip`         | 保留调试符号，不精简二进制                        |
| `-I key=value`       | 硬编译 INI 选项到 PHP 中                    |
| `--with-upx-pack`    | 用 UPX 压缩产物（需先 `spc install-pkg upx`） |

硬编译 INI 的例子——预设更大的内存限制，并禁用 `system` 函数：

```bash
spc build:php bcmath,pcntl,posix --build-cli -I "memory_limit=4G" -I "disable_functions=system"
```

## 打包 micro 应用

构建 `micro.sfx` 后，用 `micro:combine` 将你的 PHP 代码打包进去，生成一个完全独立的可执行文件：

```bash
echo "<?php echo 'Hello, World!' . PHP_EOL;" > hello.php
spc micro:combine hello.php --output=hello
./hello
```

也支持打包 `.phar` 文件，以及注入 INI 配置：

```bash
# 打包 phar
spc micro:combine your-app.phar --output=your-app

# 打包时注入 INI
spc micro:combine your-app.phar --output=your-app -I "memory_limit=512M"

# 从 ini 文件注入配置
spc micro:combine your-app.phar --output=your-app -N /path/to/custom.ini
```

## 调试与重新构建

构建失败，或想查看详细过程，使用 `-v` / `-vv` / `-vvv`：

- `-v` 将显示 `INFO` 级别的日志，包含执行到的模块和执行的编译命令等。
- `-vv` 将显示 `DEBUG` 级别的日志，包含所有 StaticPHP 中调试级别的日志。
- `-vvv` 将显示 `DEBUG` 级别的日志，并将其他 shell 命令执行的 STDOUT 输出到终端。

```bash
spc build:php bcmath,openssl --build-cli -vv
```

或者，你也可以查看 `log/spc.shell.log` 和 `log/spc.output.log` 获取终端输出和 StaticPHP 日志。

如需清理编译中间产物、从头重新构建（不重新下载），使用 `reset`：

```bash
spc reset
# 然后重新构建
spc build:php bcmath,openssl --build-cli
```

::: tip
`reset` 只清理 `buildroot/` 和 `source/` 目录，不会删除 `downloads/` 缓存。
如需同时清理下载缓存，加上 `--with-download` 参数。
:::

如果问题持续无法解决，欢迎提交 [Issue](https://github.com/crazywhalecc/static-php-cli/issues)，并附上 `craft.yml`（如有）和 `log/` 目录的压缩包。

## 接下来

- [命令行参考](./cli-reference) — 所有命令与选项的完整说明
- [扩展列表](./extensions) — 查看支持的扩展及其依赖关系
- [常见问题](./troubleshooting) — 构建失败时的排查指南
