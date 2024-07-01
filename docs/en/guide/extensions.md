# Extensions

> - `yes`: supported
> - _blank_: not supported yet, or WIP
> - `no` with issue link: confirmed to be unavailable due to issue
> - `partial` with issue link: supported but not perfect due to issue

<!--@include: ../../extensions.md-->

::: tip
If an extension you need is missing, you can create a [Feature Request](https://github.com/crazywhalecc/static-php-cli/issues).

Some extensions or libraries that the extension depends on will have some optional features. 
For example, the gd library optionally supports libwebp, freetype, etc. 
If you only use `bin/spc build gd --build-cli` they will not be included (static-php-cli defaults to the minimum dependency principle).

You can use `--with-libs=` to add these libraries when compiling. 
When the dependent libraries of this compilation include them, gd will automatically use them to enable these features.
(For example: `bin/spc build gd --with-libs=libwebp,freetype --build-cli`)

Alternatively you can use `--with-suggested-exts` and `--with-suggested-libs` to enable all optional dependencies of these extensions and libraries.
(For example: `bin/spc build gd --with-suggested-libs --build-cli`)

If you don't know whether an extension has optional features, 
you can check the [spc configuration file](https://github.com/crazywhalecc/static-php-cli/tree/main/config) 
or use the command `bin/spc dev:extensions` (library dependency is `lib-suggests`, extension dependency is `ext-suggests`).
:::
