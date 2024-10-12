---
outline: 'deep'
---

# Dependency Table

When compiling PHP, each extension and library has dependencies, which may be required or optional.
You can choose whether to include these optional dependencies.

For example, when compiling the `gd` extension under Linux, 
the `zlib,libpng` libraries and the `zlib` extension are forced to be compiled, 
while the `libavif,libwebp,libjpeg,freetype` libraries are optional libraries and will not be compiled by default
unless specified by the `--with-libs=avif,webp,jpeg,freetype` option.

- For optional extensions (optional features of extensions), you need to specify them manually at compile time, for example, to enable igbinary support for Redis: `bin/spc build redis,igbinary`.
- For optional libraries, you need to compile and specify them through the `--with-libs=XXX` option.
- If you want to enable all optional extensions, you can use `bin/spc build redis --with-suggested-exts`.
- If you want to enable all optional libraries, you can use `--with-suggested-libs`.

## Extension Dependency Table

<!--@include: ../../deps-map-ext.md-->

## Library Dependency Table

<!--@include: ../../deps-map-lib.md-->