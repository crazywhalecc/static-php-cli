# Build (Linux, macOS, FreeBSD)

This section covers the build process for Linux, macOS, and FreeBSD. If you want to build on Windows, 
also need to read [Build on Windows](./build-on-windows).

### Build locally (using SPC binary) (recommended)

This project provides a binary file of static-php-cli.
You can directly download the binary file of the corresponding platform and then use it to build static PHP.
Currently, the platforms supported by `spc` binary are Linux and macOS.

Here's how to download from self-hosted server:

```bash
# Download from self-hosted nightly builds (sync with main branch)
# For Linux x86_64
curl -fsSL -o spc https://dl.static-php.dev/static-php-cli/spc-bin/nightly/spc-linux-x86_64
# For Linux aarch64
curl -fsSL -o spc https://dl.static-php.dev/static-php-cli/spc-bin/nightly/spc-linux-aarch64
# macOS x86_64 (Intel)
curl -fsSL -o spc https://dl.static-php.dev/static-php-cli/spc-bin/nightly/spc-macos-x86_64
# macOS aarch64 (Apple)
curl -fsSL -o spc https://dl.static-php.dev/static-php-cli/spc-bin/nightly/spc-macos-aarch64
# Windows (x86_64, win10 build 17063 or later)
curl.exe -fsSL -o spc.exe https://dl.static-php.dev/static-php-cli/spc-bin/nightly/spc-windows-x64.exe

# Add execute perm (Linux and macOS only)
chmod +x ./spc

# Run (Linux and macOS)
./spc --version
# Run (Windows powershell)
.\spc.exe --version
```

> If you are using the packaged `spc` binary, you will need to replace the leading `bin/spc` with `./spc` in all the commands below.

### Build locally (using source code)

If you have problems using the spc binary, or if you need to modify the static-php-cli source code, download static-php-cli from the source code.

Currently, it supports building on macOS and Linux. 
macOS supports the latest version of the operating system and two architectures, 
while Linux supports Debian and derivative distributions, as well as Alpine Linux.

Because this project itself is developed using PHP, 
it is also necessary to install PHP on the system during compilation. 
This project also provides static binary PHP suitable for this project, 
which can be selected and used according to actual situations.

```bash
# clone repo
git clone https://github.com/crazywhalecc/static-php-cli.git --depth=1
cd static-php-cli

# You need to install the PHP environment first before running Composer and this project. The installation method can be referred to below.
composer update
```

### Use System PHP

Below are some example commands for installing PHP and Composer in the system. 
It is recommended to search for the specific installation method yourself or ask the AI search engine to obtain the answer, 
which will not be elaborated here.

```bash
# [macOS], need install Homebrew first. See https://brew.sh/
# Remember change your composer executable path. For M1/M2 Chip mac, "/opt/homebrew/bin/", for Intel mac, "/usr/local/bin/". Or add it to your own path.
brew install php wget
wget https://getcomposer.org/download/latest-stable/composer.phar -O /path/to/your/bin/composer && chmod +x /path/to/your/bin/composer

# [Debian], you need to make sure your php version >= 8.1 and composer >= 2.0
sudo apt install php-cli composer php-tokenizer

# [Alpine]
apk add bash file wget xz php81 php81-common php81-pcntl php81-tokenizer php81-phar php81-posix php81-xml composer
```

::: tip
Currently, some versions of Ubuntu install older PHP versions, 
so no installation commands are provided. If necessary, it is recommended to add software sources such as ppa first, 
and then install the latest version of PHP and tokenizer, XML, and phar extensions.

Older versions of Debian may have an older (<= 7.4) version of PHP installed by default, it is recommended to upgrade Debian first.
:::

### Use Docker

If you don't want to install PHP and Composer runtime environment on your system, you can use the built-in Docker environment build script.

```bash
# To use directly, replace `bin/spc` with `bin/spc-alpine-docker` in all used commands
bin/spc-alpine-docker
```

The first time the command is executed, `docker build` will be used to build a Docker image. 
The default built Docker image is the `x86_64` architecture, and the image name is `cwcc-spc-x86_64`.

If you want to build `aarch64` static-php-cli in `x86_64` environment, 
you can use qemu to emulate the arm image to run Docker, but the speed will be very slow.
Use command: `SPC_USE_ARCH=aarch64 bin/spc-alpine-docker`.

If it prompts that sudo is required to run after running, 
execute the following command once to grant static-php-cli permission to execute sudo:

```bash
export SPC_USE_SUDO=yes
```

### Use Precompiled Static PHP Binaries

If you don't want to use Docker and install PHP in the system, 
you can directly download the php binary cli program compiled by this project itself. The usage process is as follows:

Deploy the environment using the command, the command will download a static php-cli binary from [self-hosted server](https://dl.static-php.dev/static-php-cli/).
Next, it will automatically download Composer from [getcomposer](https://getcomposer.org/download/latest-stable/composer.phar) or [Aliyun mirror](https://mirrors.aliyun.com/composer/composer.phar).

::: tip
Using precompiled static PHP binaries is currently only supported on Linux and macOS.
The FreeBSD environment is currently not supported due to the lack of an automated build environment.
:::

```bash
bin/setup-runtime

# For users with special network environments such as mainland China, you can use mirror sites (aliyun) to speed up the download speed
bin/setup-runtime --mirror china
```

This script will download two files in total: `bin/php` and `bin/composer`. After the download is complete, there are two ways to use it:

1. Add the `bin/` directory to the PATH: `export PATH="/path/to/your/static-php-cli/bin:$PATH"`, after adding the path, 
it is equivalent to installing PHP in the system, you can directly Use commands such as `composer`, `php -v`, or directly use `bin/spc`.
2. Direct call, such as executing static-php-cli command: `bin/php bin/spc --help`, executing Composer: `bin/php bin/composer update`.

## Command - download

Use the command `bin/spc download` to download the source code required for compilation, 
including php-src and the source code of various dependent libraries.

```bash
# Download all dependencies
bin/spc download --all

# Download all dependent packages, and specify the main version of PHP to download, optional: 8.1, 8.2, 8.3, 8.4
# Also supports specific version of php release: 8.3.10, 8.2.22, etc.
bin/spc download --all --with-php=8.3

# Show download progress bar while downloading (curl)
bin/spc download --all --debug

# Delete old download data
bin/spc download --clean

# Download specified dependencies
bin/spc download php-src,micro,zstd,ext-zstd

# Download only extensions and libraries to be compiled (use extensions, including suggested libraries)
bin/spc download --for-extensions=openssl,swoole,zip,pcntl,zstd

# Download resources, prefer to download dependencies with pre-built packages (reduce the time to compile dependencies)
bin/spc download --for-extensions="curl,pcntl,xml,mbstring" --prefer-pre-built

# Download only the extensions and dependent libraries to be compiled (use extensions, excluding suggested libraries)
bin/spc download --for-extensions=openssl,swoole,zip,pcntl --without-suggestions

# Download only libraries to be compiled (use libraries, including suggested libraries and required libraries, can use --for-extensions together)
bin/spc download  --for-libs=liblz4,libevent --for-extensions=pcntl,rar,xml

# Download only libraries to be compiled (use libraries, excluding suggested libraries)
bin/spc download --for-libs=liblz4,libevent --without-suggestions

# When downloading sources, ignore some source caches (always force download, e.g. switching PHP version)
bin/spc download --for-extensions=curl,pcntl,xml --ignore-cache-sources=php-src --with-php=8.3.10

# Set retry times (default is 0)
bin/spc download --all --retry=2
```

If the network in your area is not good, or the speed of downloading the dependency package is too slow, 
you can download `download.zip` which is packaged regularly every week from GitHub Action, 
and use the command to directly use the zip archive as a dependency.

Dependent packages can be downloaded locally from [Action](https://github.com/static-php/static-php-cli-hosted/actions/workflows/download-cache.yml).
Enter Action and select the latest Workflow that has been successfully run, and download `download-files-x.y`.

```bash
bin/spc download --from-zip=/path/to/your/download.zip
```

If a source cannot be downloaded all the time, or you need to download some specific version of the package, 
such as downloading the beta version of PHP, the old version of the library, etc., 
you can use the parameter `-U` or `--custom-url` to rewrite the download link,
Make the downloader force the link you specify to download packages from this source. 
The method of use is `{source-name}:{url}`, which can rewrite the download URLs of multiple libraries at the same time.
Also, it is available when downloading with the `--for-extensions` option.


```bash
# Specifying to download a beta version of PHP8.3
bin/spc download --all -U "php-src:https://downloads.php.net/~eric/php-8.3.0beta1.tar.gz"

# Specifying to download an older version of the curl library
bin/spc download --all -U "curl:https://curl.se/download/curl-7.88.1.tar.gz"
```

If the source you download is not a link, but a git repository, you can use `-G` or `--custom-git` to rewrite the download link,
so that the downloader can force the use of the specified git repository to download packages from this source.
The usage method is `{source-name}:{branch}:{url}`, which can rewrite the download link of multiple libraries at the same time. 
It is also available when downloading with the `--for-extensions` option.

```bash
# Specifying to download the source code of the PHP extension from the specified branch of the git repository
bin/spc download --for-extensions=redis -G "php-src:master:https://github.com/php/php-src.git"

# Download the latest code from the master branch of the swoole-src repository instead of PECL release version
bin/spc download --for-extensions=swoole -G "swoole:master:https://github.com/swoole/swoole-src.git"
```

## Command - doctor

If you can run `bin/spc` normally but cannot compile static PHP or dependent libraries normally, 
you can run `bin/spc doctor` first to check whether the system itself lacks dependencies.

```bash
# Quick check
bin/spc doctor

# Quickly check and fix when it can be automatically repaired (use package management to install dependent packages, only support the above-mentioned operating systems and distributions)
bin/spc doctor --auto-fix
```

## Command - build

Use the build command to start building the static php binary. 
Before executing the `bin/spc build` command, be sure to use the `download` command to download sources. 
It is recommended to use `doctor` to check the environment.

### Basic build

You need to go to [Extension List](./extensions) or [Command Generator](./cli-generator) to select the extension you want to add, 
and then use the command `bin/spc build` to compile. 
You need to specify a compilation target, choose from the following parameters:

- `--build-cli`: Build a cli sapi (command line interface, which can execute PHP code on the command line)
- `--build-fpm`: Build a fpm sapi (php-fpm, used in conjunction with other traditional fpm architecture software such as nginx)
- `--build-micro`: Build a micro sapi (used to build a standalone executable binary containing PHP code)
- `--build-embed`: Build an embed sapi (used to embed into other C language programs)
- `--build-all`: build all above sapi

```bash
# Compile PHP with bcmath,curl,openssl,ftp,posix,pcntl extensions, the compilation target is cli
bin/spc build bcmath,curl,openssl,ftp,posix,pcntl --build-cli

# Compile PHP with phar,curl,posix,pcntl,tokenizer extensions, compile target is micro
bin/spc build phar,curl,posix,pcntl,tokenizer --build-micro
```

::: tip
If you need to repeatedly build and debug, you can delete the `buildroot/` and `source/` directories so that you can re-extract and build all you need from the downloaded source code package:

```shell
# remove
rm -rf buildroot source
# build again
bin/spc build bcmath,curl,openssl,ftp,posix,pcntl --build-cli
```
:::

::: tip
If you want to build multiple versions of PHP and don't want to build other dependent libraries repeatedly each time, 
you can use `switch-php-version` to quickly switch to another version and compile after compiling one version:

```shell
# switch to 8.4
bin/spc switch-php-version 8.4
# build
bin/spc build bcmath,curl,openssl,ftp,posix,pcntl --build-cli
# switch to 8.1
bin/spc switch-php-version 8.1
# build
bin/spc build bcmath,curl,openssl,ftp,posix,pcntl --build-cli
```
:::

### Debug

If you encounter problems during the compilation process, or want to view each executing shell command, 
you can use `--debug` to enable debug mode and view all terminal logs:

```bash
bin/spc build mysqlnd,pdo_mysql --build-all --debug
```

### Build Options

During the compilation process, in some special cases, 
the compiler and the content of the compilation directory need to be intervened. 
You can try to use the following commands:

- `--cc=XXX`: Specifies the execution command of the C language compiler (Linux default `musl-gcc` or `gcc`, macOS default `clang`)
- `--cxx=XXX`: Specifies the execution command of the C++ language compiler (Linux defaults to `g++`, macOS defaults to `clang++`)
- `--with-clean`: clean up old make files before compiling PHP
- `--enable-zts`: Make compiled PHP thread-safe version (default is NTS version)
- `--no-strip`: Do not run `strip` after compiling the PHP library to trim the binary file to reduce its size (the macOS binary file without trim can use dynamically linked third-party extensions)
- `--with-libs=XXX,YYY`: Compile the specified dependent library before compiling PHP, and activate some extended optional functions (such as libavif of the gd library, etc.)
- `--with-config-file-path=XXX`: Set the path in which to look for `php.ini` (Check [here](../faq/index.html#what-is-the-path-of-php-ini) for default paths)
- `--with-config-file-scan-dir=XXX`: Set the directory to scan for `.ini` files after reading `php.ini` (Check [here](../faq/index.html#what-is-the-path-of-php-ini) for default paths)
- `-I xxx=yyy`: Hard compile INI options into PHP before compiling (support multiple options, alias is `--with-hardcoded-ini`)
- `--with-micro-fake-cli`: When compiling micro, let micro's `PHP_SAPI` pretend to be `cli` (for compatibility with some programs that check `PHP_SAPI`)
- `--disable-opcache-jit`: Disable opcache jit (enabled by default)
- `-P xxx.php`: Inject external scripts during static-php-cli compilation (see **Inject external scripts** below for details)
- `--without-micro-ext-test`: After building micro.sfx, do not test the running results of different extensions in micro.sfx
- `--with-suggested-exts`: Add `ext-suggests` as dependencies when compiling
- `--with-suggested-libs`: Add `lib-suggests` as dependencies when compiling
- `--with-upx-pack`: Use UPX to reduce the size of the binary file after compilation (you need to use `bin/spc install-pkg upx` to install upx first)

For hardcoding INI options, it works for cli, micro, embed sapi. Here is a simple example where we preset a larger `memory_limit` and disable the `system` function:

```bash
bin/spc build bcmath,pcntl,posix --build-all -I "memory_limit=4G" -I "disable_functions=system"
```

## Command - micro:combine

Use the `micro:combine` command to build the compiled `micro.sfx` and your code (`.php` or `.phar` file) into an executable binary.
You can also use this command to directly build a micro binary injected with ini configuration.

::: tip
Injecting ini configuration refers to adding a special structure after micro.sfx to save ini configuration items before combining micro.sfx with PHP source code.

micro.sfx can identify the INI file header through a special byte, and the micro can be started with INI through the INI file header.

The original wiki of this feature is in [phpmicro - Wiki](https://github.com/easysoft/phpmicro/wiki/INI-settings), and this feature may change in the future.
:::

The following is the general usage, directly packaging the php source code into a file:

```bash
# Before doing the packaging process, you should use `build --build-micro` to compile micro.sfx
echo "<?php echo 'hello';" > a.php
bin/spc micro:combine a.php

# Just use it
./my-app
```

You can use the following options to specify the file name to be output, and you can also specify micro.sfx in other paths for packaging.

```bash
# specify the output filename
bin/spc micro:combine a.php --output=custom-bin
# Use absolute path
bin/spc micro:combine a.php -O /tmp/my-custom-app

# Specify micro.sfx in other locations for packaging
bin/spc micro:combine a.app --with-micro=/path/to/your/micro.sfx
```

If you want to inject ini configuration items, you can use the following parameters to add ini to the executable file from a file or command line option.

```bash
# Specified using command-line options (-I is shorthand for --with-ini-set)
bin/spc micro:combine a.php -I "a=b" -I "foo=bar"

# Use ini file specification (-N is shorthand for --with-ini-file)
bin/spc micro:combine a.php -N /path/to/your/custom.ini
```

::: warning
Note, please do not directly use the PHP source code or the `php.ini` file in the system-installed PHP, 
it is best to manually write an ini configuration file that you need, for example:

```ini
; custom.ini
curl.cainfo=/path/to/your/cafile.pem
memory_limit=1G
```

The ini injection of this command is achieved by appending a special structure after micro.sfx, 
which is different from the function of inserting hard-coded INI during compilation.
:::

If you want to package phar, just replace `a.php` with the packaged phar file. 
But please note that micro.sfx under phar needs extra attention to the path problem, see [Developing - Phar directory issue](../develop/structure#phar-application-directory-issue).

## Command - extract

Use the command `bin/spc extract` to unpack and copy the source code required for compilation, 
including php-src and the source code of various dependent libraries (you need to specify the name of the library to be unpacked).

For example, after we have downloaded sources, we want to distribute and execute the build process, 
manually unpack and copy the package to a specified location, and we can use commands.

```bash
# Unzip the downloaded compressed package of php-src and libxml2, and store the decompressed source code in the source directory
bin/spc extract php-src,libxml2
```

## Dev Command - dev

Debug commands refer to a collection of commands that can assist in outputting some information 
when you use static-php-cli to build PHP or modify and enhance the static-php-cli project itself.

- `dev:extensions`: output all currently supported extension names, or output the specified extension information
- `dev:php-version`: output the currently compiled PHP version (by reading `php_version.h`)
- `dev:sort-config`: Sort the list of configuration files in the `config/` directory in alphabetical order
- `dev:lib-ver <lib-name>`: Read the version from the source code of the dependency library (only available for specific dependency libraries)
- `dev:ext-ver <ext-name>`: Read the corresponding version from the source code of the extension (only available for specific extensions)
- `dev:pack-lib <lib-name>`: Package the specified library into a tar.gz file (maintainer only)
- `dev:gen-ext-docs`: Generate extension documentation (maintainer only)

```bash
# output all extensions information
bin/spc dev:extensions

# Output the meta information of the specified extension
bin/spc dev:extensions mongodb,curl,openssl

# Output the specified columns
# Available column name: lib-depends, lib-suggests, ext-depends, ext-suggests, unix-only, type
bin/spc dev:extensions --columns=lib-depends,type,ext-depends

# Output the currently compiled PHP version
# You need to decompress the downloaded PHP source code to the source directory first
# You can use `bin/spc extract php-src` to decompress the source code separately
bin/spc dev:php-version

# Sort the configuration files in the config/ directory in alphabetical order (e.g. ext.json)
bin/spc dev:sort-config ext
```

## Command - install-pkg

Use the command `bin/spc install-pkg` to download some precompiled or closed source tools and install them into the `pkgroot` directory.

When `bin/spc doctor` automatically repairs the Windows environment, tools such as nasm and perl will be downloaded, and the installation process of `install-pkg` will also be used.

Here is an example of installing the tool:

- Download and install UPX (Linux and Windows only): `bin/spc install-pkg upx`

## Command - del-download

In some cases, you need to delete single or multiple specified download source files and re-download them, such as switching PHP versions. 
The `bin/spc del-download` command is provided after the `2.1.0-beta.4` version. Specified source files can be deleted.

Deletes downloaded source files containing precompiled packages and source code named as keys in `source.json` or `pkg.json`. Here are some examples:

- Delete the old PHP source code and switch to download the 8.3 version: `bin/spc del-download php-src && bin/spc download php-src --with-php=8.3`
- Delete the download file of redis extension: `bin/spc del-download redis`
- Delete the downloaded musl-toolchain x86_64: `bin/spc del-download musl-toolchain-x86_64-linux`

## Inject External Script

Injecting external scripts refers to inserting one or more scripts during the static-php-cli compilation process
to more flexibly support parameter modifications and source code patches in different environments.

Under normal circumstances, this function mainly solves the problem that the patch cannot be modified
by modifying the static-php-cli code when compiling with `spc` binary.

There is another situation: your project directly depends on the `crazywhalecc/static-php-cli` repository and is synchronized with main branch, 
but some proprietary modifications are required, and these feature are not suitable for merging into the main branch.

In view of the above situation, in the official version 2.0.0, static-php-cli has added multiple event trigger points. 
You can write an external `xx.php` script and pass it in through the command line parameter `-P` and execute.

When writing to inject external scripts, the methods you will use are `builder()` and `patch_point()`. 
Among them, `patch_point()` obtains the name of the current event, and `builder()` obtains the BuilderBase object.

Because the incoming patch point does not distinguish between events, 
you must write the code you want to execute in `if(patch_point() === 'your_event_name')`, 
otherwise it will be executed repeatedly in other events.

The following are the supported `patch_point` event names and corresponding locations:

| Event name                   | Event description                                                                                  |
|------------------------------|----------------------------------------------------------------------------------------------------|
| before-libs-extract          | Triggered before the dependent libraries extracted                                                 |
| after-libs-extract           | Triggered after the compiled dependent libraries extracted                                         |
| before-php-extract           | Triggered before PHP source code extracted                                                         |
| after-php-extract            | Triggered after PHP source code extracted                                                          |
| before-micro-extract         | Triggered before phpmicro extract                                                                  |
| after-micro-extract          | Triggered after phpmicro extracted                                                                 |
| before-exts-extract          | Triggered before the extension (to be compiled) extracted to the PHP source directory              |
| after-exts-extract           | Triggered after the extension extracted to the PHP source directory                                |
| before-library[*name*]-build | Triggered before the library named `name` is compiled (such as `before-library[postgresql]-build`) |
| after-library[*name*]-build  | Triggered after the library named `name` is compiled                                               |
| before-php-buildconf         | Triggered before compiling PHP command `./buildconf`                                               |
| before-php-configure         | Triggered before compiling PHP command `./configure`                                               |
| before-php-make              | Triggered before compiling PHP command `make`                                                      |
| before-sanity-check          | Triggered after compiling PHP but before running extended checks                                   |

The following is a simple example of temporarily modifying the PHP source code. 
Enable the CLI function to search for the `php.ini` configuration in the current working directory:

```php
// a.php
<?php
// patch it before `./buildconf` executed
if (patch_point() === 'before-php-buildconf') {
    \SPC\store\FileSystem::replaceFileStr(
        SOURCE_PATH . '/php-src/sapi/cli/php_cli.c',
        'sapi_module->php_ini_ignore_cwd = 1;',
        'sapi_module->php_ini_ignore_cwd = 0;'
    );
}
```

```bash
bin/spc build mbstring --build-cli -P a.php
# Write in ./
echo 'memory_limit=8G' > ./php.ini
```

```
$ buildroot/bin/php -i | grep Loaded
Loaded Configuration File => /Users/jerry/project/git-project/static-php-cli/php.ini

$ buildroot/bin/php -i | grep memory
memory_limit => 8G => 8G
```

For the objects, methods and interfaces supported by static-php-cli, you can read the source code. Most methods and objects have corresponding comments.

Commonly used objects and functions using the `-P` function are:

- `SPC\store\FileSystem`: file management class
    - `::replaceFileStr(string $filename, string $search, $replace)`: Replace file string content
    - `::replaceFileStr(string $filename, string $pattern, $replace)`: Regularly replace file content
    - `::replaceFileUser(string $filename, $callback)`: User-defined function replaces file content
    - `::copyDir(string $from, string $to)`: Recursively copy a directory to another location
    - `::convertPath(string $path)`: Convert the path delimiter to the current system delimiter
    - `::scanDirFiles(string $dir, bool $recursive = true, bool|string $relative = false, bool $include_dir = false)`: Traverse directory files
- `SPC\builder\BuilderBase`: Build object
    - `->getPatchPoint()`: Get the current injection point name
    - `->getOption(string $key, $default = null)`: Get command line and compile-time options
    - `->getPHPVersionID()`: Get the currently compiled PHP version ID
    - `->getPHPVersion()`: Get the currently compiled PHP version number
    - `->setOption(string $key, $value)`: Set options
    - `->setOptionIfNotExists(string $key, $value)`: Set option if option does not exist

::: tip
static-php-cli has many open methods, which cannot be listed in the docs, 
but as long as it is a `public function` and is not marked as `@internal`, it theoretically can be called.
:::

## Multiple builds

If you need to build multiple times locally, the following method can save you time downloading resources and compiling.

- If you only switch the PHP version without changing the dependent libraries, you can use `bin/spc switch-php-version` to quickly switch the PHP version, and then re-run the same `build` command.
- If you want to rebuild once, but do not re-download the source code, you can first `rm -rf buildroot source` to delete the compilation directory and source code directory, and then rebuild.
- If you want to update a version of a dependency, you can use `bin/spc del-download <source-name>` to delete the specified source code, and then use `download <source-name>` to download it again.
- If you want to update all dependent versions, you can use `bin/spc download --clean` to delete all downloaded sources, and then download them again.

## embed usage

If you want to embed static-php into other C language programs, you can use `--build-embed` to build an embed version of PHP.

```bash
bin/spc build {your extensions} --build-embed --debug
```

Under normal circumstances, PHP embed will generate `php-config` after compilation. 
For static-php, we provide `spc-config` to obtain the parameters during compilation.
In addition, when using embed SAPI (libphp.a), you need to use the same compiler as libphp, otherwise there will be a link error.

Here is the basic usage of spc-config:

```bash
# output all flags and options
bin/spc spc-config curl,zlib,phar,openssl

# output libs
bin/spc spc-config curl,zlib,phar,openssl --libs

# output includes
bin/spc spc-config curl,zlib,phar,openssl --includes
```

By default, static-php uses the following compilers on different systems:

- macOS: `clang`
- Linux (Alpine Linux): `gcc`
- Linux (glibc based distros, x86_64): `/usr/local/musl/bin/x86_64-linux-musl-gcc`
- Linux (glibc based distros, aarch64): `/usr/local/musl/bin/aarch64-linux-musl-gcc`
- FreeBSD: `clang`

Here is an example of using embed SAPI:

```c
// embed.c
#include <sapi/embed/php_embed.h>

int main(int argc,char **argv){

    PHP_EMBED_START_BLOCK(argc,argv)

    zend_file_handle file_handle;

    zend_stream_init_filename(&file_handle,"embed.php");

    if(php_execute_script(&file_handle) == FAILURE){
        php_printf("Failed to execute PHP script.\n");
    }

    PHP_EMBED_END_BLOCK()
    return 0;
}
```


```php
<?php 
// embed.php
echo "Hello world!\n";
```

```bash
# compile in debian/ubuntu x86_64
/usr/local/musl/bin/x86_64-linux-musl-gcc embed.c $(bin/spc spc-config bcmath,zlib) -static -o embed
# compile in macOS/FreeBSD
clang embed.c $(bin/spc spc-config bcmath,zlib) -o embed

./embed
# out: Hello world!
```
