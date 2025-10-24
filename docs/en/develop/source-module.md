# Source module

The download source module of static-php-cli is a major module.
It includes dependent libraries, external extensions, PHP source code download methods and file decompression methods.
The download configuration file mainly involves the `source.json` and `pkg.json` file, which records the download method of all downloadable sources.

The main commands involved in the download function are `bin/spc download` and `bin/spc extract`. 
The `download` command is a downloader that downloads sources according to the configuration file, 
and the `extract` command is an extractor that extract sources from downloaded files.

Generally speaking, downloading sources may be slow because these sources come from various official websites, GitHub, 
and other different locations. 
At the same time, they also occupy a large space, so you can download the sources once and reuse them.

The configuration file of the downloader is `source.json`, which contains the download methods of all sources. 
You can add the source download methods you need, or modify the existing source download methods.

The download configuration structure of each source is as follows. 
The following is the source download configuration corresponding to the `libevent` extension:

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

The most important field here is `type`. Currently, the types it supports are:

- `url`: Directly use URL to download, for example: `https://download.libsodium.org/libsodium/releases/libsodium-1.0.18.tar.gz`.
- `pie`: Download PHP extensions from Packagist using the PIE (PHP Installer for Extensions) standard.
- `ghrel`: Use the GitHub Release API to download, download the artifacts uploaded from the latest version released by maintainers.
- `ghtar`: Use the GitHub Release API to download. 
    Different from `ghrel`, `ghtar` is downloaded from the `source code (tar.gz)` in the latest Release of the project.
- `ghtagtar`: Use GitHub Release API to download. 
    Compared with `ghtar`, `ghtagtar` can find the latest one from the `tags` list and download the source code in `tar.gz` format 
    (because some projects only use `tag` release version).
- `bitbuckettag`: Download using BitBucket API, basically the same as `ghtagtar`, except this one applies to BitBucket.
- `git`: Clone the project directly from a Git address to download sources, applicable to any public Git repository.
- `filelist`: Use a crawler to crawl the Web download site that provides file index,
    and get the latest version of the file name and download it.
- `custom`: If none of the above download methods are satisfactory, you can write `custom`, 
    create a new class under `src/SPC/store/source/`, extends `CustomSourceBase`, and write the download script yourself.

## source.json Common parameters

Each source file in source.json has the following params:

- `license`: the open source license of the source code, see **Open Source License** section below
- `type`: must be one of the types mentioned above
- `path` (optional): release the source code to the specified directory instead of `source/{name}`
- `provide-pre-built` (optional): whether to provide precompiled binary files. 
    If `true`, it will automatically try to download precompiled binary files when running `bin/spc download`

::: tip
The `path` parameter in `source.json` can specify a relative or absolute path. When specified as a relative path, the path is based on `source/`.
:::

## Download type - url

URL type sources refer to downloading files directly from the URL.

The parameters included are:

- `url`: The download address of the file, such as `https://example.com/file.tgz`
- `filename` (optional): The file name saved to the local area. If not specified, the file name of the url will be used.

Example (download the imagick extension and extract it to the extension storage path of the php source code):

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

## Download type - pie

PIE (PHP Installer for Extensions) type sources refer to downloading PHP extensions from Packagist that follow the PIE standard.
This method automatically fetches extension information from the Packagist repository and downloads the appropriate distribution file.

The parameters included are:

- `repo`: The Packagist vendor/package name, such as `vendor/package-name`

Example (download a PHP extension from Packagist using PIE):

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
The PIE download type will automatically detect the extension information from Packagist metadata, 
including the download URL, version, and distribution type. 
The extension must be marked as `type: php-ext` or contain `php-ext` metadata in its Packagist package definition.
:::

## Download type - ghrel

ghrel will download files from Assets uploaded in GitHub Release. 
First use the GitHub Release API to get the latest version, and then download the corresponding files according to the regular matching method.

The parameters included are:

- `repo`: GitHub repository name
- `match`: regular expression matching Assets files
- `prefer-stable`: Whether to download stable versions first (default is `false`)

Example (download the libsodium library, matching the libsodium-x.y.tar.gz file in Release):

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

## Download type - ghtar

ghtar will download the file from the GitHub Release Tag. 
Unlike `ghrel`, `ghtar` will download the `source code (tar.gz)` from the latest Release of the project.

The parameters included are:

- `repo`: GitHub repository name
- `prefer-stable`: Whether to download stable versions first (default is `false`)

Example (brotli library):

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

## Download type - ghtagtar

Use the GitHub Release API to download. 
Compared with `ghtar`, `ghtagtar` can find the latest one from the `tags` list and download the source code in `tar.gz` format 
(because some projects only use the `tag` version).

The parameters included are:

- `repo`: GitHub repository name
- `prefer-stable`: Whether to download stable versions first (default is `false`)

Example (gmp library):

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

## Download Type - bitbuckettag

Download using BitBucket API, basically the same as `ghtagtar`, except this one works with BitBucket.

The parameters included are:

- `repo`: BitBucket repository name

## Download type - git

Clone the project directly from a Git address to download sources, applicable to any public Git repository.

The parameters included are:

- `url`: Git link (HTTPS only)
- `rev`: branch name

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

## Download type - filelist

Use a crawler to crawl a web download site that provides a file index and get the latest version of the file name and download it.

Note that this method is only applicable to static sites with page index functions such as mirror sites and GNU official websites.

The parameters included are:

- `url`: The URL of the page to crawl the latest version of the file
- `regex`: regular expression matching file names and download links

Example (download the libiconv library from the GNU official website):

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

## Download type - custom

If the above downloading methods are not satisfactory, you can write `custom`, 
create a new class under `src/SPC/store/source/`, extends `CustomSourceBase`, and write the download script yourself.

I won’t go into details here, you can look at `src/SPC/store/source/PhpSource.php` or `src/SPC/store/source/PostgreSQLSource.php` as examples.

## pkg.json General parameters

pkg.json stores non-source-code files, such as precompiled tools musl-toolchain and UPX. It includes:

- `type`: The same type as `source.json` and different kinds of parameters.
- `extract` (optional): The path to decompress after downloading, the default is `pkgroot/{pkg_name}`.
- `extract-files` (optional): Extract only the specified files to the specified location after downloading.

It should be noted that `pkg.json` does not involve compilation, modification and distribution of source code, 
so there is no `license` open source license field. 
And you cannot use the `extract` and `extract-files` parameters at the same time.

Example (download nasm locally and extract only program files to PHP SDK):

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

The key name in `extract-files` is the file in the source folder, and the key value is the storage path. The storage path can use the following variables:

- `{php_sdk_path}`: (Windows only) PHP SDK path
- `{pkg_root_path}`: `pkgroot/`
- `{working_dir}`: current working directory
- `{download_path}`: download directory
- `{source_path}`: source code decompression directory

When `extract-files` does not use variables and is a relative path, the directory of the relative path is `{working_dir}`.

## Open source license

For `source.json`, each source file should contain an open source license. 
The `license` field stores the open source license information.

Each `license` contains the following parameters:

- `type`: `file` or `text`
- `path`: the license file in the source code directory (required when `type` is `file`)
- `text`: License text (required when `type` is `text`)

Example (yaml extension source code with LICENSE file):

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

When an open source project has multiple licenses, multiple files can be specified:

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

When the license of an open source project uses different files between versions, 
`path` can be used as an array to list the possible license files:

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
