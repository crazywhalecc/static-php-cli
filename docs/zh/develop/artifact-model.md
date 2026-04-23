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

