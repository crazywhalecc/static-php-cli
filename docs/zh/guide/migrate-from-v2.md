# 从 v2 迁移

StaticPHP v3 是一次完整的重写。核心构建流程（`download → build → combine`）保持不变，但部分命令、选项和配置字段已发生变化。本页列出了切换前所有需要更新的内容。

::: info 范围说明
本指南仅涵盖面向用户的 CLI 命令、选项、`craft.yml` 字段和 `env.ini` 变量名称。不涵盖内部 PHP API。
:::

## 文档地址变更

官方文档站点已迁移：

- **v3 文档（当前）**：[https://static-php.dev](https://static-php.dev) — 主站现在托管 v3 文档。
- **v2 文档（归档）**：[https://static-php.github.io/v2-docs/](https://static-php.github.io/v2-docs/) — v2 文档已归档保留，供参考。

请更新你保存的书签或内部链接。

## `spc` 二进制下载地址变更

nightly `spc` 自包含二进制文件已迁移到新路径：

| | 地址 |
|---|---|
| **v2** | `https://dl.static-php.dev/static-php-cli/spc-bin/nightly/` |
| **v3** | `https://dl.static-php.dev/v3/spc-bin/nightly/` |

请更新所有直接下载 `spc` 二进制的 CI 脚本或初始化命令，例如：

```bash
# v2
curl -o spc https://dl.static-php.dev/static-php-cli/spc-bin/nightly/spc-linux-x86_64

# v3
curl -o spc https://dl.static-php.dev/v3/spc-bin/nightly/spc-linux-x86_64
```

## 已移除的命令

| v2 命令 | v3 替代方案 | 说明 |
|---|---|---|
| `del-download` | `spc reset` | `reset` 支持 `--with-pkgroot` 和 `--with-download` 以进行更细粒度的控制 |
| `del-download --all` | `spc reset --with-download` | 删除下载缓存目录 |

## 已移除的选项

### `--with-added-patch` / `-P`（build 命令）

该选项允许在特定构建阶段注入外部 PHP patch 脚本。**v3 已完全移除此功能。**

目前没有直接的替代方案。如果你依赖此功能，请考虑以下方式：

- 将你的 patch 贡献到 StaticPHP 的上游仓库。
- 对于项目专用的 patch，可以使用自定义 registry 并编写 Package 类。详情参见[编写 Package 类](/zh/develop/extending/package-classes)。

::: tip 未来计划
未来版本可能会提供用于轻量级 patch 的单文件 hook API。
:::

### Windows 专有：`--with-sdk-binary-dir` 和 `--vs-ver`

这两个选项已不再被命令行接受。请改为设置 `PHP_SDK_PATH` 环境变量，指向你的 PHP SDK binary tools 目录。Visual Studio 版本现在由工具链配置统一管理。

## 已重命名 / 已弃用的选项

以下选项已重命名。部分旧名称仍作为弃用别名被接受，但建议尽快更新脚本。

| v2 选项 | v3 选项 | 状态 |
|---|---|---|
| `--prefer-pre-built` | `--prefer-binary` / `-p` | 旧名称保留为弃用别名 |
| `--with-libs=<list>` | `--with-packages=<list>` | — |
| `--with-suggested-libs` / `-L` | `--with-suggests` | 旧 `-L` / `-E` 已移除 |
| `--with-suggested-exts` / `-E` | `--with-suggests` | 已合并为单一标志 |

### 示例

```bash
# v2
spc build curl,gd --build-cli --with-libs="openssl" -L -E

# v3
spc build curl,gd --build-cli --with-packages="openssl" --with-suggests
```

## `build` 命令行为变化

`build` 命令（别名：`build:php`）仍然可用。但 v3 新增了**专用的单目标构建命令**，无需再传入 SAPI 选择标志：

| v2 | v3 等价命令 |
|---|---|
| `spc build exts --build-cli` | `spc build:php-cli exts` |
| `spc build exts --build-fpm` | `spc build:php-fpm exts` |
| `spc build exts --build-cgi` | `spc build:php-cgi exts` |
| `spc build exts --build-micro` | `spc build:php-micro exts` |
| `spc build exts --build-embed` | `spc build:php-embed exts` |
| `spc build exts --build-frankenphp` | `spc build:frankenphp exts` |

如果需要在一次构建中同时编译多个 SAPI，请继续使用 `build:php`（`--build-*` 标志在该命令下仍然有效）。

### 构建命令自动下载依赖

v3 中，所有 `build:*` 命令在构建前会自动下载缺失的依赖包，不再需要单独执行 `spc download`：

```bash
# v2 — 需要两步
spc download --for-extensions=curl,gd
spc build curl,gd --build-cli

# v3 — 一步即可
spc build:php-cli curl,gd
```

如需跳过自动下载（例如在 CI 中源码已预先缓存），可传入 `--no-download`：

```bash
spc build:php-cli curl,gd --no-download
```

## `download` 命令选项变化

| v2 | v3 | 说明 |
|---|---|---|
| `--prefer-pre-built` | `--prefer-binary` / `-p` | 弃用别名保留 |
| `--with-libs` | `--for-libs` | 与包过滤分开 |
| *（无等价）* | `--for-packages` | 统一包过滤器 |
| *（无等价）* | `--parallel` / `-P` | 并行下载 |
| *（无等价）* | `--retry` / `-R` | 失败重试 |

## 已移除的 dev 命令

以下开发辅助命令已被移除或合并：

| v2 命令 | v3 替代方案 |
|---|---|
| `dev:extensions` / `list-ext` | `spc dev:info <package>` |
| `dev:ext-version` / `dev:ext-ver` | `spc dev:info <package>` |
| `dev:lib-version` / `dev:lib-ver` | `spc dev:info <package>` |
| `dev:php-version` / `dev:php-ver` | `spc dev:info php-src` |
| `dev:gen-ext-dep-docs` + `dev:gen-lib-dep-docs` | `spc dev:gen-deps-data` |

## 已重命名的 dev 命令

| v2 | v3 | 说明 |
|---|---|---|
| `dev:sort-config` / `sort-config` | `dev:lint-config` | 旧别名仍可用 |

## v3 新增命令

以下命令为 v3 新增，v2 中没有对应命令：

| 命令 | 说明 |
|---|---|
| `spc reset` | 清理 `buildroot/` 和 `source/` 目录 |
| `spc check-update` | 检查 artifact 的最新版本 |
| `spc build:php-cli` | 构建 CLI SAPI（无需标志） |
| `spc build:php-fpm` | 构建 PHP-FPM（无需标志） |
| `spc build:php-cgi` | 构建 PHP CGI（无需标志） |
| `spc build:php-micro` | 构建 phpmicro（无需标志） |
| `spc build:php-embed` | 构建 embed SAPI（无需标志） |
| `spc build:frankenphp` | 构建 FrankenPHP（无需标志） |
| `spc dev:shell` | 进入带构建环境的交互式 shell |
| `spc dev:is-installed` | 检查某个包是否已正确安装 |
| `spc dev:dump-stages` | 将所有包的构建阶段导出为 JSON |
| `spc dev:dump-capabilities` | 导出包的可构建/可安装能力 |
| `spc dev:info` | 显示某个包的配置信息 |

## `craft.yml` 变化

### 已移除：`build-options.with-added-patch`

`build-options` 下的 `with-added-patch` 键不再被解析，将被静默忽略。请从你的 `craft.yml` 中移除它：

```yaml
# v2 — 请删除此块
build-options:
  with-added-patch:
    - my-patch.php
```

### `libs` → `packages`（两者均可用）

顶层 `libs` 字段仍然有效。v3 中推荐使用 `packages`，它是 `libs` 的超集，还涵盖其他工具类包：

```yaml
# v2
libs: nghttp2,liblz4

# v3（推荐）
packages: nghttp2,liblz4
```

## `env.ini` 变量重命名

如果你在 `config/env.ini` 中进行了自定义，或在 CI 中导出了环境变量，请更新以下变量名：

| v2 变量名 | v3 变量名 |
|---|---|
| `SPC_LINUX_DEFAULT_CC` | `SPC_DEFAULT_CC` |
| `SPC_LINUX_DEFAULT_CXX` | `SPC_DEFAULT_CXX` |
| `SPC_LINUX_DEFAULT_AR` | `SPC_DEFAULT_AR` |
| `SPC_LINUX_DEFAULT_LD` | `SPC_DEFAULT_LD` |
| `SPC_LIBC` | `SPC_TARGET` |

`SPC_TARGET` 使用新的格式，将架构与 libc 编码在一个字符串中，例如：

| v2 | v3 |
|---|---|
| `SPC_LIBC=musl` | `SPC_TARGET=x86_64-linux-musl` |
| `SPC_LIBC=gnu` | `SPC_TARGET=x86_64-linux-gnu.2.17` |

v3 还新增了若干日志相关变量（`SPC_ENABLE_LOG_FILE`、`SPC_LOGS_DIR`、`SPC_PRESERVE_LOGS`）。详情参见[环境变量](/zh/guide/env-vars)。
