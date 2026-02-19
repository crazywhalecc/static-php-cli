
# 补丁 / Patches

名称 Name | 平台 Platform | 可选? Optional? | 用途 Usage
--- | --- | --- | ---
phar | * | 可选 Optional | 允许micro使用压缩phar Allow micro use compressed phar
static_opcache | * | 可选 Optional | 支持静态构建opcache Support build opcache statically
macos_iconv | macOS | 可选 Optional | 支持链接到系统的iconv Support link against system iconv
static_extensions_win32 | Windows | 可选 Optional | 支持静态构建Windows其他扩展 Support build other extensions for windows
cli_checks | * | 可选 Optional | 修改PHP内核中硬编码的SAPI检查 Modify hardcoden SAPI name checks in PHP core
disable_huge_page | Linux | 可选 Optional | 禁用linux构建的max-page-size选项，缩减sfx体积（典型的， 10M+ -> 5M） Disable max-page-size for linux build，shrink sfx size (10M+ -> 5M typ.)
vcruntime140 | Windows | 必须 Nessesary | 禁用sfx启动时GetModuleHandle(vcruntime140(d).dll) Disable GetModuleHandle(vcruntime140(d).dll) at sfx start
win32 | Windows | 必须 Nessesary | 修改构建系统以静态构建 Modify build system for build sfx file
zend_stream | Windows | 必须 Nessesary | 修改构建系统以静态构建 Modify build system for build sfx file
comctl32 | Windows | 可选 Optional | 添加comctl32.dll manifest以启用[visual style](https://learn.microsoft.com/en-us/windows/win32/controls/visual-styles-overview) (会让窗口控件好看一些) Add manifest dependency for comctl32 to enable [visual style](https://learn.microsoft.com/en-us/windows/win32/controls/visual-styles-overview) (makes window control looks modern)
win32_api | Windows | 必须 Necessary | 修复一些win32 api的声明 Fix declarations of some win32 apis

## Usage

目前补丁不需要特定顺序，使用

```bash
# 在PHP源码目录
patch -p1 < sapi/micro/patches/some_patch.patch
```

来打patch

Currently, patches do not require a specific order. Use

```bash
# at PHP source root
patch -p1 < sapi/micro/patches/some_patch.patch
```

to apply the patch.

### version choose

patch文件名为\<名称\>.patch或者\<名称\>_\<版本\>.patch，如果没有版本号，说明这个补丁支持所有目前micro支持的PHP版本

Patch file name is \<name\>.patch or \<name\>_\<version\>.patch. If there is no version number, it means that the patch supports all PHP versions that micro supports.

选择等于或者低于要打补丁的PHP版本的最新版本的patch，例如要给php 8.2打patch，有 80 81 84 三个patch， 则选择81

Choose the latest patch that is equal to or lower than the PHP version you want to patch. For example, if you want to patch PHP 8.2, and there are patches 80 81 84, choose 81.

所有的补丁都是给最新的修正版本使用的

All patches are applied to the latest patch version of its minor version.

## Something special

### phar.patch

这个patch绕过PHAR对micro的文件名中包含".phar"的限制（并不会允许micro本身以外的其他文件），这使得micro文件名中不含".phar"时依然可以使用压缩过的phar

This patch bypasses the restriction that a PHAR file must contain '.phar' in its filename when invoked with micro (it will not allow files other than the sfx to be regarded as phar). This allows micro to handle compressed phar files without a custom stub.

有特别的stub的PHAR不需要这个补丁也可以使用

phar with a stub (may be a special one) do not need this patch.

这个补丁只能在micro中使用，会导致其他SAPI编译不过

This patch can only be used with micro, as it causes other SAPIs to fail to build.

### static_opcache

静态链接opcache到PHP里，可以在其他的SAPI上用

This makes opcache statically linked into PHP, and it can be used for other SAPIs.

PHP 8.3.11， 8.2.23中，opcache的config.m4发生了[变动](https://github.com/php/php-src/commit/d20d11375fa602236e1fb828f6a2236b19b43cdc)，这个patch对应变动后的版本

The opcache's config.m4 has [changed](https://github.com/php/php-src/commit/d20d11375fa602236e1fb828f6a2236b19b43cdc) in PHP 8.3.11 and 8.2.23, and this patch corresponds to the updated version.

### cli_checks

绕过许多硬编码的“是不是cli”的检查

This bypasses many hard-coded cli SAPI name checks.

### cli_static

允许Windows的cli静态构建，不是给micro用的

This allows the Windows cli SAPI to be built fully statically. It is not a patch for micro.

### win32_api

修复一些win32 api的声明，避免编译警告。这些修改已经在新版本 PHP （>=8.4）中合并，但保证旧版本也能用，这些补丁仍然需要

This fixes declarations of some win32 apis to avoid compilation warnings. These changes have been merged into newer versions of PHP (>=8.4), but to ensure that older versions can still be used, these patches are still needed.

