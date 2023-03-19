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

### Compilation

```bash
chmod +x spc
# fetch all libraries
./spc fetch --all
# with bcmath,openssl,tokenizer,sqlite3,pdo_sqlite,ftp,curl extension, build both CLI and phpmicro SAPI
./spc build bcmath,openssl,tokenizer,sqlite3,pdo_sqlite,ftp,curl --build-all
```

### php-cli Usage

When using the parameter `--build-all` or not adding the `--build-micro` parameter, 
the final compilation result will output a binary file named `./php`, 
which can be distributed and used directly. 
This file will be located in the directory `source/php-src/sapi/cli/`, simply copy it out for use.

```bash
./php -v
./php -m
./php your_code.php
```

### micro.sfx Usage

When using the parameter `--build-all` or `--build-micro`, 
the final compilation result will output a file named `./micro.sfx`, 
which needs to be used with your PHP source code like `code.php`. 
This file will be located in the directory `source/php-src/sapi/micro/`, simply copy it out for use.

Prepare your project source code, which can be a single PHP file or a Phar file, for use.

```bash
echo "<?php echo 'Hello world' . PHP_EOL;" > code.php
cat micro.sfx code.php > single-app && chmod +x single-app
./single-app

# If packing a PHAR file, simply replace code.php with the Phar file path.
```

> In some cases, PHAR files may not run in a micro environment.

## Current Status

- [X] Basic CLI framework (by symfony/console)
- [ ] Linux support
- [X] macOS support
- [X] Exception handler
- [ ] Windows support
- [X] PHP 7.4 support

## Supported Extensions (WIP)

[Support Extension List](/ext-support.md)

## Contribution

Currently, there are only a few supported extensions. 
If the extension you need is missing, you can create an issue. 
If you are familiar with this project, you are also welcome to initiate a pull request.

The basic principles for contributing are as follows:

- This project uses php-cs-fixer and phpstan as code formatting tools. Before contributing, please run `composer analyze` and `composer cs-fix` on the updated code.
- If other open source libraries are involved, the corresponding licenses should be provided. 
    Also, configuration files should be sorted using the command `sort-config` after modification.
    For more information about sorting commands, see the documentation.
- Naming conventions should be followed, such as using the extension name registered in PHP for the extension name itself, 
    and external library names should follow the project's own naming conventions. For internal logic functions, class names, variables, etc., 
    camelCase and underscore formats should be followed, and mixing within the same module is prohibited.
- When compiling external libraries and creating patches, compatibility with different operating systems should be considered.

## Open-Source License

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
