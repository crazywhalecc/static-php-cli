# 资源模块

static-php-cli 的下载资源模块是一个主要的功能，它包含了所依赖的库、外部扩展、PHP 源码的下载方式和资源解压方式。
下载的配置文件主要涉及 `source.json` 和 `pkg.json` 文件，这个文件记录了所有可下载的资源的下载方式。

下载功能主要涉及的命令有 `bin/spc download` 和 `bin/spc extract`。其中 `download` 命令是一个下载器，它会根据配置文件下载资源；
`extract` 命令是一个解压器，它会根据配置文件解压资源。

一般来说，下载资源可能会比较慢，因为这些资源来源于各个官网、GitHub 等不同位置，同时它们也占用了较大空间，所以你可以在一次下载资源后，可重复使用。

下载器的配置文件是 `source.json`，它包含了所有资源的下载方式，你可以在其中添加你需要的资源下载方式，也可以修改已有的资源下载方式。

每个资源的下载配置结构如下，下面是 `libevent` 扩展对应的资源下载配置：

```json
{
  "libevent": {
    "type": "ghrel",
    "repo": "libevent/libevent",
    "match": "libevent.+\\.tar\\.gz",
    "provide-pre-built": true,  
    "license": {
      "type": "file",
      "path": "LICENSE"
    }
  }
}
```

这里最主要的字段是 `type`，目前它支持的类型有：

- `url`: 直接使用 URL 下载，例如：`https://download.libsodium.org/libsodium/releases/libsodium-1.0.18.tar.gz`。
- `pie`: 使用 PIE（PHP Installer for Extensions）标准从 Packagist 下载 PHP 扩展。
- `ghrel`: 使用 GitHub Release API 下载，即从 GitHub 项目发布的最新版本中上传的附件下载。
- `ghtar`: 使用 GitHub Release API 下载，与 `ghrel` 不同的是，`ghtar` 是从项目的最新 Release 中找 `source code (tar.gz)` 下载的。
- `ghtagtar`: 使用 GitHub Release API 下载，与 `ghtar` 相比，`ghtagtar` 可以从 `tags` 列表找最新的，并下载 `tar.gz` 格式的源码（因为有些项目只使用了 `tag` 发布版本）。
- `bitbuckettag`: 使用 BitBucket API 下载，基本和 `ghtagtar` 相同，只是这个适用于 BitBucket。
- `git`: 直接从一个 Git 地址克隆项目来下载资源，适用于任何公开 Git 仓库。
- `filelist`: 使用爬虫爬取提供文件索引的 Web 下载站点，并获取最新版本的文件名并下载。
- `custom`: 如果以上下载方式都不能满足，你可以编写 `custom` 后，在 `src/SPC/store/source/` 下新建一个类，并继承 `CustomSourceBase`，自己编写下载脚本。

## source.json 通用参数

source.json 中每个源文件拥有以下字段：

- `license`: 源代码的开源许可证，见下方 **开源许可证** 章节
- `type`: 必须为上面提到的类型之一
- `path`（可选）: 释放源码到指定目录而非 `source/{name}`
- `provide-pre-built`（可选）: 是否提供预编译的二进制文件，如果为 `true`，则会在 `bin/spc download` 时尝试自动下载预编译的二进制文件

::: tip
`source.json` 中的 `path` 参数可指定相对路径或绝对路径。当指定为相对路径时，路径基于 `source/`。
:::

## 下载类型 - url

url 类型的资源指的是从 URL 直接下载文件。

包含的参数有：

- `url`: 文件的下载地址，如 `https://example.com/file.tgz`
- `filename`（可选）: 保存到本地的文件名，如不指定，则使用 url 的文件名

例子（下载 imagick 扩展，并解压缩到 php 源码的扩展存放路径）：

```json
{
  "ext-imagick": {
    "type": "url",
    "url": "https://pecl.php.net/get/imagick",
    "path": "php-src/ext/imagick",
    "filename": "imagick.tgz",
    "license": {
      "type": "file",
      "path": "LICENSE"
    }
  }
}
```

## 下载类型 - pie

PIE（PHP Installer for Extensions）类型的资源是从 Packagist 下载遵循 PIE 标准的 PHP 扩展。
该方法会自动从 Packagist 仓库获取扩展信息，并下载相应的分发文件。

包含的参数有：

- `repo`: Packagist 的 vendor/package 名称，如 `vendor/package-name`

例子（使用 PIE 从 Packagist 下载 PHP 扩展）：

```json
{
  "ext-example": {
    "type": "pie",
    "repo": "vendor/example-extension",
    "path": "php-src/ext/example",
    "license": {
      "type": "file",
      "path": "LICENSE"
    }
  }
}
```

::: tip
PIE 下载类型会自动从 Packagist 元数据中检测扩展信息，包括下载 URL、版本和分发类型。
扩展必须在其 Packagist 包定义中标记为 `type: php-ext` 或包含 `php-ext` 元数据。
:::

## 下载类型 - ghrel

ghrel 会从 GitHub Release 中上传的 Assets 下载文件。首先使用 GitHub Release API 获取最新版本，然后根据正则匹配方式下载相应的文件。

包含的参数有：

- `repo`: GitHub 仓库名称
- `match`: 匹配 Assets 文件的正则表达式
- `prefer-stable`: 是否优先下载稳定版本（默认为 `false`）

例子（下载 libsodium 库，匹配 Release 中的 libsodium-x.y.tar.gz 文件）：

```json
{
  "libsodium": {
    "type": "ghrel",
    "repo": "jedisct1/libsodium",
    "match": "libsodium-\\d+(\\.\\d+)*\\.tar\\.gz",
    "license": {
      "type": "file",
      "path": "LICENSE"
    }
  }
}
```

## 下载类型 - ghtar

ghtar 会从 GitHub Release Tag 下载文件，与 `ghrel` 不同的是，`ghtar` 是从项目的最新 Release 中找 `source code (tar.gz)` 下载的。

包含的参数有：

- `repo`: GitHub 仓库名称
- `prefer-stable`: 是否优先下载稳定版本（默认为 `false`）

例子（brotli 库）：

```json
{
  "brotli": {
    "type": "ghtar",
    "repo": "google/brotli",
    "license": {
      "type": "file",
      "path": "LICENSE"
    }
  }
}
```

## 下载类型 - ghtagtar

使用 GitHub Release API 下载，与 `ghtar` 相比，`ghtagtar` 可以从 `tags` 列表找最新的，并下载 `tar.gz` 格式的源码（因为有些项目只使用了 `tag` 发布版本）。

包含的参数有：

- `repo`: GitHub 仓库名称
- `prefer-stable`: 是否优先下载稳定版本（默认为 `false`）

例子（gmp 库）：

```json
{
  "gmp": {
    "type": "ghtagtar",
    "repo": "alisw/GMP",
    "license": {
      "type": "text",
      "text": "EXAMPLE LICENSE"
    }
  }
}
```

## 下载类型 - bitbuckettag

使用 BitBucket API 下载，基本和 `ghtagtar` 相同，只是这个适用于 BitBucket。

包含的参数有：

- `repo`: BitBucket 仓库名称

## 下载类型 - git

直接从一个 Git 地址克隆项目来下载资源，适用于任何公开 Git 仓库。

包含的参数有：

- `url`: Git 链接（仅限 HTTPS）
- `rev`: 分支名称

```json
{
  "imap": {
    "type": "git",
    "url": "https://github.com/static-php/imap.git",
    "rev": "master",
    "license": {
      "type": "file",
      "path": "LICENSE"
    }
  }
}
```

## 下载类型 - filelist

使用爬虫爬取提供文件索引的 Web 下载站点，并获取最新版本的文件名并下载。

注意，该方法仅限于镜像站、GNU 官网等具有页面 index 功能的静态站点使用。

包含的参数有：

- `url`: 要爬取文件最新版本的页面 URL
- `regex`: 匹配文件名及下载链接的正则表达式

例子（从 GNU 官网下载 libiconv 库）：

```json
{
  "libiconv": {
    "type": "filelist",
    "url": "https://ftp.gnu.org/gnu/libiconv/",
    "regex": "/href=\"(?<file>libiconv-(?<version>[^\"]+)\\.tar\\.gz)\"/",
    "license": {
      "type": "file",
      "path": "COPYING"
    }
  }
}
```

## 下载类型 - custom

如果以上下载方式都不能满足，你可以编写 `custom` 后，在 `src/SPC/store/source/` 下新建一个类，并继承 `CustomSourceBase`，自己编写下载脚本。

这里不再赘述，你可以查看 `src/SPC/store/source/PhpSource.php` 或 `src/SPC/store/source/PostgreSQLSource.php` 作为例子。

## pkg.json 通用参数

pkg.json 存放的是非源码类型的文件资源，例如 musl-toolchain、UPX 等预编译的工具。它的使用包含：

- `type`: 与 `source.json` 相同的类型及不同种类的参数。
- `extract`（可选）: 下载后解压缩的路径，默认为 `pkgroot/{pkg_name}`。
- `extract-files`（可选）: 下载后仅解压指定的文件到指定位置。

需要注意的是，`pkg.json` 不涉及源代码的编译和修改分发，所以没有 `license` 开源许可证字段。并且你不能同时使用 `extract` 和 `extract-files` 参数。

例子（下载 nasm 到本地，并只提取程序文件到 PHP SDK）：

```json
{
  "nasm-x86_64-win": {
    "type": "url",
    "url": "https://www.nasm.us/pub/nasm/releasebuilds/2.16.01/win64/nasm-2.16.01-win64.zip",
    "extract-files": {
      "nasm-2.16.01/nasm.exe": "{php_sdk_path}/bin/nasm.exe",
      "nasm-2.16.01/ndisasm.exe": "{php_sdk_path}/bin/ndisasm.exe"
    }
  }
}
```

`extract-files` 中的键名为源文件夹下的文件，键值为存放的路径。存放的路径可以使用以下变量：

- `{php_sdk_path}`: （仅限 Windows）PHP SDK 路径
- `{pkg_root_path}`: `pkgroot/`
- `{working_dir}`: 当前工作目录
- `{download_path}`: 下载目录
- `{source_path}`: 源码解压缩目录

当 `extract-files` 不使用变量且为相对路径时，相对路径的目录为 `{working_dir}`。

## 开源许可证

对于 `source.json` 而言，每个源文件都应包含开源许可证。`license` 字段存放了开源许可证的信息。

每个 `license` 包含的参数有：

- `type`: `file` 或 `text`
- `path`: 源代码目录中的许可证文件（当 `type` 为 `file` 时，此项必填）
- `text`: 许可证文本（当 `type` 为 `text` 时，此项必填）

例子（yaml 扩展的源代码中带有 LICENSE 文件）：

```json
{
  "yaml": {
    "type": "git",
    "path": "php-src/ext/yaml",
    "rev": "php7",
    "url": "https://github.com/php/pecl-file_formats-yaml",
    "license": {
      "type": "file",
      "path": "LICENSE"
    }
  }
}
```

当开源项目拥有多个许可证时，可指定多个文件：

```json
{
  "libuv": {
    "type": "ghtar",
    "repo": "libuv/libuv",
    "license": [
      {
        "type": "file",
        "path": "LICENSE"
      },
      {
        "type": "file",
        "path": "LICENSE-extra"
      }
    ]
  }
}
```

当一个开源项目的许可证在不同版本间使用不同的文件，`path` 参数可以使用数组将可能的许可证文件列出：

```json
{
  "redis": {
    "type": "git",
    "path": "php-src/ext/redis",
    "rev": "release/6.0.2",
    "url": "https://github.com/phpredis/phpredis",
    "license": {
      "type": "file",
      "path": [
        "LICENSE",
        "COPYING"
      ]
    }
  }
}
```
