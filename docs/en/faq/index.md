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

Because the principle of installing extensions in PHP under the traditional architecture is to install new extensions using `.so` type dynamic link libraries, 
and statically linked PHP compiled using this project cannot **directly** install new extensions using dynamic link libraries.

For the macOS platform, almost all binary files under macOS cannot be linked purely statically, 
and almost all binary files will link macOS system libraries: `/usr/lib/libresolv.9.dylib` and `/usr/lib/libSystem.B.dylib`.
So under macOS system, statically compiled php binary files can be used under certain compilation conditions, 
and dynamic link extensions can be used at the same time:

1. Using the `--no-strip` parameter will not strip information such as debugging symbols from the binary file for use with external Zend extensions such as `Xdebug`.
2. If you want to compile some Zend extensions, use Homebrew, MacPorts, source code compilation, and install a normal version of PHP on your operating system.
3. Use the `phpize && ./configure && make` command to compile the extensions you want to use.
4. Copy the extension file `xxxx.so` to the outside, use the statically compiled PHP binary, for example to use the Xdebug extension: `cd buildroot/bin/ && ./php -d "zend_extension=/path/to/xdebug.so"`.

```bash
# build statically linked php-cli but not stripped
bin/spc build ffi --build-cli --no-strip
```

For the Linux platform, the current compilation result is a purely statically linked binary file, 
and new extensions cannot be installed using a dynamic link library.

## Can it support Oracle database extension?

Some extensions that rely on closed source libraries, such as `oci8`, `sourceguardian`, etc., 
they do not provide purely statically compiled dependent library files (`.a`), only dynamic dependent library files (`.so`).
These extensions cannot be compiled into static-php-cli from source, so this project may never support them. 
However, in theory, you can access and use such extensions under macOS according to the above questions.

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
