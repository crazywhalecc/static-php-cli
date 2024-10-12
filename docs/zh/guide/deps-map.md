---
outline: 'deep'
---

# 依赖关系图表

在编译 PHP 时，每个扩展、库都有依赖关系，这些依赖关系可能是必需的，也可能是可选的。在编译 PHP 时，可以选择是否包含这些可选的依赖关系。

例如，在 Linux 下编译 `gd` 扩展时，会强制编译 `zlib,libpng` 库和 `zlib` 扩展，而 `libavif,libwebp,libjpeg,freetype` 库都是可选的库，默认不会编译，除非通过 `--with-libs=avif,webp,jpeg,freetype` 选项指定。

- 对于可选扩展（扩展的可选特性），需手动在编译时指定，例如启用 Redis 的 igbinary 支持：`bin/spc build redis,igbinary`。
- 对于可选库，需通过 `--with-libs=XXX` 选项编译指定。
- 如果想启用所有的可选扩展，可以使用 `bin/spc build redis --with-suggested-exts` 参数。
- 如果想启用所有的可选库，可以使用 `--with-suggested-libs` 参数。

## 扩展的依赖图

<!--@include: ../../deps-map-ext.md-->

## 库的依赖表

<!--@include: ../../deps-map-lib.md-->