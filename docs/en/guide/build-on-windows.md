# Build on Windows

Because the Windows system is an NT kernel, the compilation tools and operating system interfaces 
used by Unix-like operating systems are almost completely different, 
so the build process on Windows will be slightly different from that of Unix systems.

## GitHub Actions Build

Building the Windows version of static-php from Actions is now supported.
Like Linux and macOS, you need to Fork the static-php-cli repository to your GitHub account first, 
then you can enter [Extension List](./extensions) to select the extension to be compiled, 
and then go to your own `CI on Windows` select the PHP version, fill in the extension list (comma separated), and click Run.

If you're going to develop or build locally, please read on.

## Requirements

The tools required to build static PHP on Windows are the same as PHP's official Windows build tools. 
You can read [Official Documentation](https://wiki.php.net/internals/windows/stepbystepbuild_sdk_2).

To sum up, you need the following environment and tools:

- Windows 10/11 (requires build 17063 or later)
- Visual Studio 2019/2022 (recommended 2022)
- C++ desktop development for Visual Studio
- Git for Windows
- [php-sdk-binary-tools](https://github.com/php/php-sdk-binary-tools) (can be installed automatically using doctor)
- strawberry-perl (can be installed automatically using doctor)
- nasm (can be installed automatically using doctor)

::: tip
The construction of static-php-cli on Windows refers to using MSVC to build PHP and is not based on MinGW, Cygwin, WSL and other environments.

If you prefer to use WSL, please refer to the chapter on Building on Linux.
:::

After installing Visual Studio and selecting the C++ desktop development workload, 
you may download about 8GB of compilation tools, and the download speed depends on your network conditions.

### Install Git

Git for Windows can be downloaded and installed from [here](https://git-scm.com/download/win) `Standalone Installer 64-bit` version, 
installed in the default location (`C:\Program Files\Git\`).
If you don't want to download and install manually, 
you can also use Visual Studio Installer and check Git in the **Individual component** tab.

### Prepare static-php-cli

Downloading the static-php-cli project is very simple, just use git clone. 
It is recommended to place the project in `C:\spc-build\` or a similar directory. 
It is best **not to have spaces in the path**.

```shell
mkdir "C:\spc-build"
cd C:\spc-build
git clone https://github.com/crazywhalecc/static-php-cli.git
cd static-php-cli
```

It is a bit strange that static-php-cli itself requires a PHP environment, 
but now you can quickly install the PHP environment through a script.
Generally, your computer will not have the Windows version of PHP installed, 
so we recommend that you use `bin/setup-runtime` directly after downloading static-php-cli to install PHP and Composer in the current directory.

```shell
# Install PHP and Composer to the ./runtime/ directory
bin/setup-runtime

# After installation, if you need to use PHP and Composer in global commands, 
# use the following command to add the runtime/ directory to PATH
bin/setup-runtime -action add-path

# Delete the runtime/ directory in PATH
bin/setup-runtime -action remove-path
```

Finally, now that you have PHP and Composer installed, you need to install static-php-cli's Composer dependencies:

```shell
composer install
```

### Install other Tools (automatic)

For `php-sdk-binary-tools`, `strawberry-perl`, and `nasm`, 
we recommend that you directly use the command `bin/spc doctor` to check and install them.

If doctor successfully installs automatically, please **skip** the steps below to manually install the above tools.

But if the automatic installation fails, please refer to the manual installation method below.

### Install php-sdk-binary-tools (manual)

```shell
cd C:\spc-build\static-php-cli
git clone https://github.com/php/php-sdk-binary-tools.git
```

> You can also set the global variable `PHP_SDK_PATH` in Windows settings and 
> clone the project to the path corresponding to the variable. 
> Under normal circumstances, you don't need to change it.

### Install strawberry-perl (manual)

> If you don't need to compile the openssl extension, you don't need to install perl.

1. Download the latest version of strawberry-perl from [GitHub](https://github.com/StrawberryPerl/Perl-Dist-Strawberry/releases/).
2. Install to the `C:\spc-build\static-php-cli\pkgroot\perl\` directory.

> You can download the `-portable` version and extract it directly to the above directory.
> The last `perl.exe` should be located at `C:\spc-build\static-php-cli\pkgroot\perl\perl\bin\perl.exe`.

### Install nasm (manual)

> If you don't need to compile openssl extension, you don't need to install nasm.

1. Download the nasm tool (x64) from [official website](https://www.nasm.us/pub/nasm/releasebuilds/).
2. Place `nasm.exe` and `ndisasm.exe` in the `C:\spc-build\static-php-cli\php-sdk-binary-tools\bin\` directory.

## Download required sources

Same as [Manual build - Download](./manual-build.html#command-download)

## Build PHP

Use the build command to start building the static php binary.
Before executing the `bin/spc build` command, be sure to use the `download` command to download sources.
It is recommended to use `doctor` to check the environment.

### Build SAPI

You need to go to [Extension List](./extensions) or [Command Generator](./cli-generator) to select the extension you want to add,
and then use the command `bin/spc build` to compile.
You need to specify targets, choose from the following parameters (at least one):

- `--build-cli`: Build a cli sapi (command line interface, which can execute PHP code on the command line)
- `--build-micro`: Build a micro sapi (used to build a standalone executable binary containing PHP code)

```shell
# Compile PHP with bcmath,openssl,zlib extensions, the compilation target is cli
bin/spc build "bcmath,openssl,zlib" --build-cli

# Compile PHP with phar,curl,posix,pcntl,tokenizer extensions, compile target is micro and cli
bin/spc build "bcmath,openssl,zlib" --build-micro --build-cli
```

::: warning
In Windows, it is best to use double quotes to wrap parameters containing commas, such as `"bcmath,openssl,mbstring"`.
:::

### Debug

If you encounter problems during the compilation process, or want to view each executing shell command,
you can use `--debug` to enable debug mode and view all terminal logs:

```shell
bin/spc build "openssl" --build-cli --debug
```

### Build Options

During the compilation process, in some special cases,
the compiler and the content of the compilation directory need to be intervened.
You can try to use the following commands:

- `--with-clean`: clean up old make files before compiling PHP
- `--enable-zts`: Make compiled PHP thread-safe version (default is NTS version)
- `--with-libs=XXX,YYY`: Compile the specified dependent library before compiling PHP, and activate some extension optional functions 
- `--with-config-file-scan-dir=XXX`: Set the directory to scan for `.ini` files after reading `php.ini` (Check [here](../faq/index.html#what-is-the-path-of-php-ini) for default paths)
- `-I xxx=yyy`: Hard compile INI options into PHP before compiling (support multiple options, alias is `--with-hardcoded-ini`)
- `--with-micro-fake-cli`: When compiling micro, let micro's `PHP_SAPI` pretend to be `cli` (for compatibility with some programs that check `PHP_SAPI`)
- `--disable-opcache-jit`: Disable opcache jit (enabled by default)
- `--without-micro-ext-test`: After building micro.sfx, do not test the running results of different extensions in micro.sfx
- `--with-suggested-exts`: Add `ext-suggests` as dependencies when compiling
- `--with-suggested-libs`: Add `lib-suggests` as dependencies when compiling
- `--with-upx-pack`: Use UPX to reduce the size of the binary file after compilation (you need to use `bin/spc install-pkg upx` to install upx first)
- `--with-micro-logo=XXX.ico`: Customize the icon of the `exe` executable file after customizing the micro build (in the format of `.ico`)

Here is a simple example where we preset a larger `memory_limit` and disable the `system` function:

```shell
bin/spc build "bcmath,openssl" --build-cli -I "memory_limit=4G" -I "disable_functions=system"
```

Another example: Customize our hello-world.exe program logo:

```shell
bin/spc build "ffi,bcmath" --build-micro --with-micro-logo=mylogo.ico --debug
bin/spc micro:combine hello.php
# Then we got `my-app.exe` with custom logo!
my-app.exe
```

## Use php.exe

After php.exe is compiled, it is located in the `buildroot\bin\` directory. You can copy it to any location for use.

```shell
.\php -v
```

## Use micro.sfx

> phpmicro is a SelF-extracted eXecutable SAPI module,
> provided by [phpmicro](https://github.com/dixyes/phpmicro) project.
> But this project is using a [fork](https://github.com/static-php/phpmicro) of phpmicro, because we need to add some features to it.
> It can put php runtime and your source code together.

The final compilation result will output a file named `./micro.sfx`,
which needs to be used with your PHP source code like `code.php`.
This file will be located in the path `buildroot/bin/micro.sfx`.

Prepare your project source code, which can be a single PHP file or a Phar file, for use.

> If you want to combine phar files, you must add `phar` extension when compiling!

```shell
# code.php "<?php echo 'Hello world' . PHP_EOL;"
bin/spc micro:combine code.php -O my-app.exe
# Run it!!! Copy it to another computer!!!
./my-app.exe
```

If you package a PHAR file, just replace `code.php` with the phar file path.
You can use [box-project/box](https://github.com/box-project/box) to package your CLI project as Phar,
It is then combined with phpmicro to produce a standalone executable binary.

For more details on the `micro:combine` command, refer to [command](./manual-build) on Unix systems.
