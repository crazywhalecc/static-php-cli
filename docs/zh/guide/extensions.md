# 扩展列表

> - `yes`: 已支持
> - 空白: 目前还不支持，或正在支持中
> - `no` with issue link: 确定不支持或无法支持
> - `partial` with issue link: 已支持，但是无法完美工作

<!--@include: ../../extensions.md-->

::: tip
如果缺少您需要的扩展，您可以创建 [功能请求](https://github.com/crazywhalecc/static-php-cli/issues)。

有些扩展或扩展依赖的库会有一些可选的特性，例如 gd 库可选支持 libwebp、freetype 等。
如果你只使用 `bin/spc build gd --build-cli` 是不会包含它们（static-php-cli 默认为最小依赖原则）。

你可以在编译时使用 `--with-libs=` 加入这些库，当本次编译的依赖库中包含它们，gd 会自动依赖它们启用这些特性。
（如：`bin/spc build gd --with-libs=libwebp,freetype --build-cli`）

或者你也可以使用 `--with-suggested-exts` 和 `--with-suggested-libs` 启用这些扩展和库所有可选的依赖。
（如：`bin/spc build gd --with-suggested-libs --build-cli`）

如果你不知道某个扩展是否有可选特性，可以通过查看 [spc 配置文件](https://github.com/crazywhalecc/static-php-cli/tree/main/config) 
或使用命令 `bin/spc dev:extensions` 查看（库依赖为 `lib-suggests`，扩展依赖为 `ext-suggests`）。
:::
