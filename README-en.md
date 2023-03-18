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
chmod +x spc
# fetch all libraries
./spc fetch --all
# with bcmath,openssl,swoole extension, build both CLI and phpmicro SAPI
./spc build "bcmath,openssl,swoole" --build-all
```

## Current Status

- [X] Basic CLI framework (by symfony/console)
- [ ] Linux support
- [X] macOS support
- [X] Exception handler
- [ ] Windows support
- [ ] PHP 7.4 support

## Supported Extensions (WIP)

[Support Extension List](/ext-support.md)

## Open-Source LICENSE

This project is based on the tradition of using the MIT License for old versions, 
while the new version references source code from some other projects. 
Special thanks to:

- [dixyes/lwmbs](https://github.com/dixyes/lwmbs) (Mulun Permissive License)
- [swoole/swoole-cli](https://github.com/swoole/swoole-cli) (Apache 2.0 LICENSE+SWOOLE-CLI LICENSE)

Due to the special nature of this project, 
many other open source projects such as curl and protobuf will be used during the project compilation process, 
and they all have their own open source licenses.

Please use the `dump-license`(TODO) command to export the open source licenses used in the project after compilation, 
and comply with the corresponding project's LICENSE.
