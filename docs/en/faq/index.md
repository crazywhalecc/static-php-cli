# FAQ

Here will be some questions that you may encounter easily. There are currently many, but I need to take time to organize them.

## What is the path of php.ini ?

On Linux, macOS and FreeBSD, the path of `php.ini` is `/usr/local/etc/php/php.ini`.
On Windows, the path is `C:\windows\php.ini` or the current directory of `php.exe`.
The directory where to look for `php.ini` can be changed on *nix using the manual build option `--with-config-file-path`.

In addition, on Linux, macOS and FreeBSD, `.ini` files present in the `/usr/local/etc/php/conf.d` directory will also be loaded.
On Windows, this path is empty by default.
The directory can be changed using the manual build option `--with-config-file-scan-dir`.

`php.ini` will also be searched for in [the other standard locations](https://www.php.net/manual/configuration.file.php). 

## Can statically-compiled PHP install extensions?

Because the principle of installing PHP extensions under the normal mode is to use `.so` type dynamic link library to install new extensions, 
and we use the static link PHP compiled by this project. However, static linking has different definitions in different operating systems.

First of all, for Linux systems, statically linked binaries will not link the system's dynamic link library. 
Purely statically linked binaries (`build with -all-static`) cannot load dynamic libraries, so new extensions cannot be added.
At the same time, in pure static mode, you cannot use extensions such as `ffi` to load external `.so` modules.

You can use the command `ldd buildroot/bin/php` to check whether the binary you built under Linux is purely statically linked.

If you [build GNU libc based PHP](../guide/build-with-glibc), you can use the `ffi` extension to load external `.so` modules and load `.so` extensions with the same ABI.

For example, you can use the following command to build a static PHP binary dynamically linked with glibc, 
supporting FFI extensions and loading the `xdebug.so` extension of the same PHP version and the same TS type:

```bash
bin/spc-gnu-docker download --for-extensions=ffi,xml --with-php=8.4
bin/spc-gnu-docker build ffi,xml --build-cli --debug

buildroot/bin/php -d "zend_extension=/path/to/php{PHP_VER}-{ts/nts}/xdebug.so" --ri xdebug
```

For macOS platform, almost all binaries under macOS cannot be truly purely statically linked, and almost all binaries will link macOS system libraries: `/usr/lib/libresolv.9.dylib` and `/usr/lib/libSystem.B.dylib`.
So on macOS, you can **directly** use SPC to build statically compiled PHP binaries with dynamically linked extensions:

1. Build shared extension `xxx.so` using: `--build-shared=XXX` option. e.g. `bin/spc build bcmath,zlib --build-shared=xdebug --build-cli`
2. You will get `buildroot/modules/xdebug.so` and `buildroot/bin/php`.
3. The `xdebug.so` file could be used for php that version and thread-safe are the same.

For the Windows platform, since officially built extensions (such as `php_yaml.dll`) force the use of the `php8.dll` dynamic library as a link, and statically built PHP does not include any dynamic libraries other than system libraries,
php.exe built by static-php cannot load officially built dynamic extensions. Since static-php-cli does not yet support building dynamic extensions, there is currently no way to load dynamic extensions with static-php.

However, Windows can normally use the `FFI` extension to load other dll files and call them.

## Can it support Oracle database extension?

Some extensions that rely on closed source libraries, such as `oci8`, `sourceguardian`, etc., 
they do not provide purely statically compiled dependent library files (`.a`), only dynamic dependent library files (`.so`).
These extensions cannot be compiled into static-php-cli using source code, so this project may never support these extensions.
However, in theory you can access and use such extensions under macOS and Linux according to the above questions.

If you have a need for such extensions, or most people have needs for these closed-source extensions,
see the discussion on [standalone-php-cli](https://github.com/crazywhalecc/static-php-cli/discussions/58). Welcome to leave a message.

## Does it support Windows?

The project currently supports Windows, but the number of supported extensions is small. Windows support is not perfect. There are mainly the following problems:

1. The compilation process of Windows is different from that of *nix, and the toolchain used is also different. The compilation tools used to compile the dependent libraries of each extension are almost completely different.
2. The demand for the Windows version will also be advanced based on the needs of all people who use this project. If many people need it, I will support related extensions as soon as possible.

## Can I protect my source code with micro?

You can't. micro.sfx is essentially combining php and php code into one file, 
there is no process of compiling or encrypting the PHP code.

First of all, php-src is the official interpreter of PHP code, and there is no PHP compiler compatible with mainstream branches on the market.
I saw on the Internet that there is a project called BPC (Binary PHP Compiler?) that can compile PHP into binary, 
but there are many restrictions.

The direction of encrypting and protecting the code is not the same as compiling. 
After compiling, the code can also be obtained through reverse engineering and other methods. 
The real protection is still carried out by means of packing and encrypting the code.

Therefore, this project (static-php-cli) and related projects (lwmbs, swoole-cli) all provide a convenient compilation tool for php-src source code.
The phpmicro referenced by this project and related projects is only a package of PHP's sapi interface, not a compilation tool for PHP code.
The compiler for PHP code is a completely different project, so the extra cases are not taken into account. 
If you are interested in encryption, you can consider using existing encryption technologies, 
such as Swoole Compiler, Source Guardian, etc.

## Unable to use ssl

**Update: This issue has been fixed in the latest version of static-php-cli, which now reads the system's certificate file by default. If you still have problems, try the solution below.**

When using curl, pgsql, etc. to request an HTTPS website or establish an SSL connection, there may be an `error:80000002:system library::No such file or directory` error.
This error is caused by statically compiled PHP without specifying `openssl.cafile` via `php.ini`.

You can solve this problem by specifying `php.ini` before using PHP and adding `openssl.cafile=/path/to/your-cert.pem` in the INI.

For Linux systems, you can download the [cacert.pem](https://curl.se/docs/caextract.html) file from the curl official website, or you can use the certificate file that comes with the system.
For the certificate locations of different distros, please refer to [Golang docs](https://go.dev/src/crypto/x509/root_linux.go).

> INI configuration `openssl.cafile` cannot be set dynamically using the `ini_set()` function, because `openssl.cafile` is a `PHP_INI_SYSTEM` type configuration and can only be set in the `php.ini` file.

## Why don't we support older versions of PHP?

Because older versions of PHP have many problems, such as security issues, performance issues, and functional issues. 
In addition, many older versions of PHP are not compatible with the latest dependency libraries, 
which is one of the reasons why older versions of PHP are not supported.

You can use older versions compiled earlier by static-php-cli, such as PHP 8.0, but earlier versions will not be explicitly supported.
