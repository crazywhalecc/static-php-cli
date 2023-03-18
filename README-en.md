# static-php-cli
Compile A Statically Linked PHP With Swoole and other Extensions.

Compile a purely static PHP binary file with various extensions to make PHP-cli applications more portable! 

You can also use the micro binary file to package PHP source code and binary files into one for distribution!

Note: only support cli SAPI, not support fpm, cgi.

## Compilation Requirements

Yes, this project is written in PHP, pretty funny.
But php-static-cli only requires an environment above PHP 8.0.

- Linux
  - Supported arch: aarch64, amd64
  - Supported distributions: alpine, ubuntu, centos
  - Requirements: (TODO)
- macOS
  - Supported arch: arm64, x86_64
  - Requirements: make, bison, flex, pkg-config, git, autoconf, automake, tar, unzip, xz, gzip, bzip2, cmake
- Windows
  - Supported arch: x86_64
  - Requirements: (TODO)
- PHP
  - Supported version: 8.0, 8.1, 8.2

## Usage (WIP)

After stable release for this project, a single phar and single binary for this tool will be published.

And currently you may need to clone this branch and edit GitHub Action to build.

```bash
chmod +x static-php-cli
# 拉取所有依赖库
./static-php-cli fetch --all
# 构建包含 bcmath 扩展的 php-cli 和 micro.sfx
./static-php-cli build "bcmath" "" --build-all
```

## Current Status

- [X] Basic CLI framework (by symfony/console)
- [ ] Linux support
- [ ] macOS support
- [X] Exception handler
- [ ] Windows support
- [ ] PHP 7.4 support

## Supported Extensions

> - yes: supported and tested
> - untested: supported but not tested
> - empty: not supported yet

|            | Linux | macOS    | Windows |
|------------|-------|----------|---------|
| bcmath     |       | yes      |         |
| calendar   |       |          |         |`
| ctype      |       |          |         |
| curl       |       |          |         |
| date       |       | yes      |         | 
| dom        |       |          |         |
| event      |       |          |         |
| exif       |       |          |         |
| filter     |       |          |         |
| fileinfo   |       |          |         |
| ftp        |       |          |         |
| gd         |       | untested |         |
| hash       |       | yes      |         |
| iconv      |       |          |         |
| inotify    |       |          |         |
| json       |       | yes      |         |
| libxml     |       |          |         |
| mbstring   |       |          |         |
| mongodb    |       |          |         |
| mysqli     |       |          |         |
| mysqlnd    |       |          |         |
| openssl    |       | yes      |         |
| pcntl      |       | untested |         |
| pcre       |       | yes      |         |
| pdo        |       | yes      |         |
| pdo_mysql  |       |          |         |
| pdo_sqlite |       | yes      |         |
| pdo_pgsql  |       |          |         |
| phar       |       |          |         |
| posix      |       |          |         |
| protobuf   |       |          |         |
| readline   |       |          |         |
| redis      |       |          |         |
| Reflection |       | yes      |         |
| shmop      |       |          |         |
| simplexml  |       |          |         |
| soap       |       |          |         |
| sockets    |       |          |         |
| sqlite3    |       | untested |         |
| swow       |       |          |         |
| swoole     |       | yes      |         |
| tokenizer  |       |          |         |
| xml        |       |          |         |
| xmlreader  |       |          |         |
| xmlwriter  |       |          |         |
| zip        |       |          |         |
| zlib       |       |          |         |

## Open-Source LICENSE

This project is based on the tradition of using the MIT License for old versions, 
while the new version references source code from some other projects. 
Special thanks to:

- dixyes/lwmbs (Mulun Permissive License)
- swoole/swoole-cli (Apache 2.0 LICENSE+SWOOLE-CLI LICENSE)
- 
Due to the special nature of this project, 
many other open source projects such as curl and protobuf will be used during the project compilation process, 
and they all have their own open source licenses.

Please use the `dump-license` command to export the open source licenses used in the project after compilation, 
and comply with the corresponding project's LICENSE.
