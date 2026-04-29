---
outline: 'deep'
---

# Artifact 模型

Artifact 是 StaticPHP 构建系统中的一个重要概念，代表构建包所需的源码归档文件或预构建的二进制文件。每个 Artifact 定义了下载 URL、解压方式、构建产物的文件路径等信息。Package 可以通过 `artifact` 字段引用一个或多个 Artifact 来获取构建所需的源码或二进制文件。

## Artifact 定义

下面是一个简单的包含源码的 artifact 单元对象示例（curl 源码）：

```yaml
&:
  source:
    type: ghrel
    repo: curl/curl
    match: curl.+\.tar\.xz
    prefer-stable: true
```

有两种方式定义一个 artifact 并关联到 package，一种是独立定义 artifact（如上），另一种是直接在 package 定义中内联 artifact：

::: code-group
```yaml [内联 Artifact 定义示例]
# 该文件为 package 声明
curl:
  type: target
  artifact:
    source:
      type: ghrel
      repo: curl/curl
      match: curl.+\.tar\.xz
      prefer-stable: true
```
```yaml [独立 Artifact 定义示例]
# 该文件为 artifact 声明，通常位于 config/artifact/ 目录
curl-src:
  source:
    type: ghrel
    repo: curl/curl
    match: curl.+\.tar\.xz
    prefer-stable: true
```
```yaml [Package 引用独立 Artifact 示例]
# 该文件为 package 声明
curl:
  type: target
  artifact: curl-src
```
:::

## 类型

Artifact 包含 `source`、`binary` 和 `metadata` 三个部分。

其中，`source` 代表源码，`binary` 代表预构建的二进制文件，`metadata` 则包含一些额外的信息（如许可证文件路径等）。`source` 和 `binary` 都支持直接定义下载 URL 的方式，也支持引用同名 Artifact 定义的方式（如上例所示）。

下面是一个 artifact 配置的对象格式

```yaml
&:
  source: {source-object} # (optional)
  binary:
    windows-x86_64: {source-object} # (optional)
    linux-x86_64: {source-object} # (optional)
    linux-aarch64: {source-object} # (optional)
    macos-x86_64: {source-object} # (optional)
    macos-aarch64: {source-object} # (optional)
  metadata: # (optional)
    license: "" # (optional) SPDX
    license-files: ["LICENSE"] # License files from original source dir
    source-root: "subdir" # (optional) If package source is in subdir, use this to change base
```

下面是 `source-object` 的基本格式：

```yaml
&:
  type: "url" # Download type
  # ...: Different type requires differnt keys here, read below
  extract: "path/to/dir" # (optional) Change extract dir, default: `SOURCE_PATH/{artifact-name}`
```

## Metadata

`metadata` 字段用于声明 Artifact 的附加信息，目前支持以下三个子字段：

### license

- **类型**：`string`（选填）
- **说明**：该包的开源协议标识符，遵循 [SPDX License Identifier](https://spdx.org/licenses/) 规范（如 `MIT`、`Apache-2.0`、`GPL-2.0-only`）。仅用于在构建产物的 License 汇总中标注协议类型，不影响构建逻辑。

```yaml
metadata:
  license: MIT
```

### license-files

- **类型**：`string[]`（选填）
- **说明**：License 文件的路径列表。构建完成后，框架会自动将这些文件收集到构建产物的 `license/` 目录中。路径支持两种写法：
  - **相对路径**（如 `LICENSE`、`COPYING`、`gettext-runtime/intl/COPYING.LIB`）：相对于该 Artifact 的源码根目录解析。
  - **`@/` 前缀路径**（如 `@/bzip2.txt`）：表示框架内置的 License 文件，路径解析为 `src/globals/licenses/` 目录下的文件。适用于源码包本身不附带 License 文件（或 License 文本嵌入在其他文档中）的场景，此时可将 License 文本预先放入框架内置目录并通过 `@/` 引用。

目前框架内置的 License 文件有：`bzip2.txt`、`gmp.txt`、`icu.txt`、`postgresql.txt`、`sqlite.txt`、`zlib.txt`。

```yaml
# 常见用法：从源码目录读取
metadata:
  license-files: [LICENSE]

# 多个 License 文件
metadata:
  license-files: [LICENSE, COPYING.LESSER]

# 子目录中的 License 文件
metadata:
  license-files: [gettext-runtime/intl/COPYING.LIB]

# 使用框架内置 License（源码包不含 License 文件时）
metadata:
  license-files: ['@/bzip2.txt']
```

### source-root

- **类型**：`string`（选填）
- **说明**：当 Artifact 解压后，实际的源码根目录位于解压目录的子目录中时，使用该字段指定子目录名。框架在执行构建时会将工作目录切换到该子目录，而非解压后的顶层目录。

```yaml
# krb5 的源码解压后实际根目录在 src/ 子目录下
metadata:
  source-root: src
```

## 下载来源

Artifact 支持多种下载来源类型。你可以根据实际情况选择对应包的下载来源。

| 类型 | 说明 |
|---|---|
| `url` | 直接下载固定 URL，支持 `filename`（自定义本地文件名）和 `version`（手动指定版本号）字段 |
| `git` | 从 Git 仓库克隆源码，支持 `rev`（分支/标签/commit）、`submodules`（是否拉取子模块）、`extract`（解压目标路径）等字段 |
| `ghrel` | 从 GitHub Release 的 Assets 中按正则匹配下载，必填 `repo`（`owner/repo` 格式）和 `match`（文件名正则），支持 `prefer-stable`（优先稳定版）|
| `ghtar` | 从 GitHub Release 下载源码 tarball（`/releases` API），按 `match` 正则匹配 Release 名称，支持 `prefer-stable` |
| `ghtagtar` | 从 GitHub Tag 下载源码 tarball（`/tags` API），按 `match` 正则匹配 Tag 名称，支持 `prefer-stable` |
| `filelist` | 抓取指定页面的 HTML，用 `regex` 从中提取文件名和版本号，再拼接 `url` 下载，适用于有版本列表页的官方站点（如 php.net、openssl.org）|
| `pecl` | 从 PECL（pecl.php.net）下载 PHP 扩展，指定 `name`（扩展包名），支持 `prefer-stable` |
| `pie` | 从 Packagist（repo.packagist.org）下载 PHP 扩展，指定 `repo`（`vendor/package` 格式），通过 Composer dist 获取下载链接 |
| `php-release` | 从 php.net 官方下载 PHP 源码，由 `domain` 指定镜像域名，版本由构建时的 `--with-php` 参数决定 |
| `bitbuckettag` | 从 Bitbucket Tag 下载源码 tarball，指定 `repo`（`workspace/repo` 格式），自动获取最新 Tag |
| `local` | 使用本地已有目录作为源码，指定 `dirname`（本地目录路径），适用于预先放置好源码的场景 |
| `custom` | 自定义下载逻辑，由 `src/Package/Artifact/` 下对应的 PHP 类实现，可指定 `func` 调用类中的特定方法 |

## 下载来源详情

### url

直接从固定 URL 下载文件。下载完成后自动解压到指定目录。

- **实现类**：`StaticPHP\Artifact\Downloader\Type\Url`
- **支持能力**：仅基础下载，不支持自动检查版本更新
- **必填**：`url` — 下载地址
- **选填**：
  - `filename` — 保存到本地的文件名（默认取 URL 末段路径）
  - `version` — 手动指定版本号（该类型无法自动检测版本）
  - `extract` — 解压目标目录（默认为 `SOURCE_PATH/{artifact-name}`）

```yaml
# sqlite 使用固定 URL 下载
artifact:
  source: 
    type: url
    url: 'https://www.sqlite.org/2024/sqlite-autoconf-3450200.tar.gz'
```

::: tip

在 artifact 中，以 `http://` 或 `https://` 开头的字符串会自动扩展为 `type: url` 对象，因此大多数情况下可以直接写裸 URL 字符串。

```yaml
artifact: 
  source: 'https://www.sqlite.org/2024/sqlite-autoconf-3450200.tar.gz'
```
:::

---

### git

从 Git 仓库克隆源码。支持两种模式：指定固定分支/Tag/commit（`rev`），或通过正则从所有分支中匹配版本号（`regex`）。

- **实现类**：`StaticPHP\Artifact\Downloader\Type\Git`
- **支持能力**：检查版本更新（`CheckUpdateInterface`）
- **必填**：`url` — 仓库地址
- **选填**（`rev` 和 `regex` 至少填一个）：
  - `rev` — 直接克隆指定分支、Tag 或 commit hash
  - `regex` — 对所有远程分支名执行正则匹配，自动选取版本最高的分支（需包含命名捕获组 `(?P<version>...)`）
  - `submodules` — 是否拉取 git submodule（布尔值）
  - `extract` — 克隆目标目录

```yaml
# php-glfw 使用 git 克隆 master 分支
artifact:
  source:
    type: git
    url: 'https://github.com/mario-deluna/php-glfw'
    rev: master
```

---

### ghrel

通过 GitHub Release Assets API 下载文件。适合仓库在 Release 页面上传了预编译包或源码压缩包的情况。需要指定文件名正则来匹配 Assets 中的目标文件。

- **实现类**：`StaticPHP\Artifact\Downloader\Type\GitHubRelease`
- **支持能力**：检查版本更新（`CheckUpdateInterface`）、下载完整性校验（`ValidatorInterface`，校验 SHA256）
- **必填**：
  - `repo` — 仓库路径，格式 `owner/repo`
  - `match` — 匹配 Asset 文件名的正则（不含分隔符，如 `openssl.+\.tar\.gz`）
- **选填**：
  - `prefer-stable` — 是否跳过预发布版本（默认 `true`）
  - `query` — 附加到 API URL 末尾的查询字符串（如 `?per_page=5`）
  - `extract` — 解压目标目录

```yaml
# openssl 从 GitHub Release Assets 下载
artifact:
  source:
    type: ghrel
    repo: openssl/openssl
    match: openssl.+\.tar\.gz
    prefer-stable: true
```

---

### ghtar

通过 GitHub **Releases** API 下载源码 tarball（即 Release 页面中的 Source code 包）。与 `ghrel` 的区别在于：`ghrel` 下载 Assets，`ghtar` 下载 Release 自动生成的源码 tarball。

- **实现类**：`StaticPHP\Artifact\Downloader\Type\GitHubTarball`
- **支持能力**：检查版本更新（`CheckUpdateInterface`）
- **必填**：`repo` — 仓库路径，格式 `owner/repo`
- **选填**：
  - `prefer-stable` — 是否跳过预发布版本（默认 `true`）
  - `match` — 对 `tarball_url` 进行正则过滤（不填则取第一个）
  - `query` — 附加到 API URL 末尾的查询字符串
  - `extract` — 解压目标目录

```yaml
# librdkafka 从 GitHub Release tarball 下载
artifact:
  source:
    type: ghtar
    repo: confluentinc/librdkafka
```

---

### ghtagtar

通过 GitHub **Tags** API 下载源码 tarball。与 `ghtar` 用法相同，区别仅在于使用 `/tags` 接口而非 `/releases` 接口，适合只打 Tag 而不发布 Release 的仓库。

- **实现类**：`StaticPHP\Artifact\Downloader\Type\GitHubTarball`（与 `ghtar` 共用同一实现类）
- **支持能力**：检查版本更新（`CheckUpdateInterface`）
- **必填**：`repo` — 仓库路径，格式 `owner/repo`
- **选填**：
  - `prefer-stable` — 是否跳过预发布版本（默认 `true`）
  - `match` — 对 Tag 名称进行正则过滤（不填则取最新 Tag）
  - `query` — 附加到 API URL 末尾的查询字符串
  - `extract` — 解压目标目录

```yaml
# brotli 通过 Tag 下载，只匹配 v1.x 系列
artifact:
  source:
    type: ghtagtar
    repo: google/brotli
    match: 'v1\.\d.*'

# libpng 通过 Tag 下载，匹配 v1.6.x，并增加分页参数
artifact:
  source:
    type: ghtagtar
    repo: pnggroup/libpng
    match: v1\.6\.\d+
    query: '?per_page=150'
```

---

### filelist

抓取一个 HTML 页面（通常是官方下载列表页），用正则从页面内容中提取文件名和版本号，然后自动选择最高稳定版本进行下载。预发布版本（含 alpha/beta/rc/dev/nightly/snapshot 关键词）会被自动跳过。

**适用场景**：无 GitHub、只有官网下载索引页的开源项目，如 `https://ftp.gnu.org/pub/gnu/ncurses/`。

- **实现类**：`StaticPHP\Artifact\Downloader\Type\FileList`
- **支持能力**：检查版本更新（`CheckUpdateInterface`）
- **必填**：
  - `url` — 包含文件列表的 HTML 页面地址
  - `regex` — 用于从页面中提取文件名和版本号的 PCRE 正则（需包含命名捕获组 `(?<file>...)` 和 `(?<version>...)`）
- **选填**：
  - `extract` — 解压目标目录
  - `download-url` — 自定义下载 URL 模板，支持 `{file}` 和 `{version}` 占位符（默认直接拼接 `url` + 文件名）

```yaml
# ncurses 从 GNU FTP 列表页抓取最新版本
artifact:
  source:
    type: filelist
    url: 'https://ftp.gnu.org/pub/gnu/ncurses/'
    regex: '/href="(?<file>ncurses-(?<version>[^"]+)\.tar\.gz)"/'

# openssl 镜像源同样使用 filelist
artifact:
  source-mirror:
    type: filelist
    url: 'https://www.openssl.org/source/'
    regex: '/href="(?<file>openssl-(?<version>[^"]+)\.tar\.gz)"/'
```

---

### pecl

从 [PECL](https://pecl.php.net)（PHP 扩展库）下载 PHP 扩展源码包。通过 PECL REST API 获取版本列表，自动选取最新稳定版。

- **实现类**：`StaticPHP\Artifact\Downloader\Type\PECL`
- **支持能力**：检查版本更新（`CheckUpdateInterface`）
- **必填**：`name` — PECL 包名（大小写不敏感，如 `APCu`）
- **选填**：
  - `prefer-stable` — 是否只下载稳定版（默认 `true`）
  - `extract` — 解压目标目录（默认解压到 `php-src/ext/{name}`）

```yaml
# APCu 从 PECL 下载
artifact:
  source:
    type: pecl
    name: APCu
```

---

### pie

从 [Packagist](https://repo.packagist.org) 下载符合 [PIE](https://github.com/php/pie) 规范的 PHP 扩展包。通过 Packagist 的 `p2/` API 获取包信息，并从 `dist` 字段下载源码。

- **实现类**：`StaticPHP\Artifact\Downloader\Type\PIE`
- **支持能力**：检查版本更新（`CheckUpdateInterface`）
- **必填**：`repo` — Packagist 包路径，格式 `vendor/package`
- **选填**：
  - `extract` — 解压目标目录

```yaml
# xdebug 从 Packagist 下载
artifact:
  source:
    type: pie
    repo: xdebug/xdebug

# php-spx 指定自定义解压目录
artifact:
  source:
    type: pie
    repo: noisebynorthwest/php-spx
    extract: php-src/ext/spx
```

---

### php-release

从 [php.net](https://www.php.net) 官方下载 PHP 源码。版本号由构建时传入的 `--with-php` 参数决定，并会自动校验 SHA256 完整性。支持传入 `git` 作为版本号以直接克隆 `php/php-src` 的 master 分支。

- **实现类**：`StaticPHP\Artifact\Downloader\Type\PhpRelease`
- **支持能力**：检查版本更新（`CheckUpdateInterface`）、下载完整性校验（`ValidatorInterface`，校验 SHA256）
- **必填**：`domain` — 下载镜像域名（如 `https://www.php.net` 或自定义镜像）
- **选填**：
  - `extract` — 解压目标目录

```yaml
# php-src 官方下载，同时配置镜像
artifact:
  source:
    type: php-release
    domain: 'https://www.php.net'
  source-mirror:
    type: php-release
    domain: 'https://phpmirror.static-php.dev'
```

---

### bitbuckettag

从 Bitbucket 仓库的最新 Tag 下载源码 tarball。通过 Bitbucket REST API 获取 Tag 列表，取第一条（即最新 Tag）进行下载。

- **实现类**：`StaticPHP\Artifact\Downloader\Type\BitBucketTag`
- **支持能力**：仅基础下载，不支持自动检查版本更新
- **必填**：`repo` — 仓库路径，格式 `workspace/repo`
- **选填**：
  - `extract` — 解压目标目录

```yaml
artifact:
  source:
    type: bitbuckettag
    repo: snappy-m-o/php-snappy
```

---

### local

直接使用本地已有目录作为源码，不执行任何下载操作。适用于源码已预先放置到本地的场景（如离线环境、本地开发调试）。

- **实现类**：`StaticPHP\Artifact\Downloader\Type\LocalDir`
- **支持能力**：仅基础下载，不支持自动检查版本更新
- **必填**：`dirname` — 本地目录绝对路径
- **选填**：
  - `extract` — 解压目标目录

```yaml
artifact:
  source:
    type: local
    dirname: /path/to/local/source
```

---

### custom

完全自定义的下载逻辑，由 `src/Package/Artifact/` 目录下对应的 PHP 类实现。如果不指定 `func`，则调用类的默认下载方法。

- **选填**：`func` — 调用实现类中的指定方法名

```yaml
artifact:
  source:
    type: custom
```

