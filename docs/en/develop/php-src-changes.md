# Modifications to PHP source code

During the static compilation process, static-php-cli made some modifications to the PHP source code 
in order to achieve good compatibility, performance, and security. 
The following is a description of the current modifications to the PHP source code.

## Micro related patches

Based on the patches provided by the phpmicro project, 
static-php-cli has made some modifications to the PHP source code to meet the needs of static compilation. 
The patches currently used by static-php-cli during compilation in the [patch list](https://github.com/easysoft/phpmicro/tree/master/patches) are:

- static_opcache
- static_extensions_win32
- cli_checks
- disable_huge_page
- vcruntime140
- win32
- zend_stream
- cli_static
- macos_iconv
- phar

## PHP <= 8.1 libxml patch

Because PHP only provides security updates for 8.1 and stops updating older versions, 
static-php-cli applies the libxml compilation patch that has been applied in newer versions of PHP to PHP 8.1 and below.

## gd extension Windows patch

Compiling the gd extension under Windows requires major changes to the `config.w32` file. 
static-php-cli has made some changes to the gd extension to make it easier to compile under Windows.

## YAML extension Windows patch

YAML extension needs to modify the `config.w32` file to compile under Windows. 
static-php-cli has made some modifications to the YAML extension to make it easier to compile under Windows.

## static-php-cli version information insertion

When compiling, static-php-cli will insert the static-php-cli version information into the PHP version information for easy identification.

## Add option to hardcode INI

When using the `-I` parameter to hardcode INI into static PHP functionality, 
static-php-cli will modify the PHP source code to insert the hardcoded content.

## Linux system repair patch

Some compilation environments may lack some system header files or libraries. 
static-php-cli will automatically fix these problems during compilation, such as:

- HAVE_STRLCAT missing problem
- HAVE_STRLCPY missing problem

## Fiber issue fix patch for Windows

When compiling PHP on Windows, there will be some issues with the Fiber extension. 
static-php-cli will automatically fix these issues during compilation (modify `config.w32` in php-src).
