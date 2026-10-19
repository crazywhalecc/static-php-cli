# 对 PHP 源码的修改

StaticPHP 会修改解压后的 PHP 源码树，以支持静态链接、额外的 SAPI、较旧的 PHP 分支以及特定平台工具链。这些改动应用于 `source/php-src` 下生成的工作区；`downloads/` 中下载的归档文件不会被修改。

本页描述 `core` registry 自带的补丁。它是供维护者使用的实现清单，并不承诺 `SourcePatcher`、生命周期回调签名或单个补丁名称属于稳定的公开 API。

## 修改发生的时机

修改会在以下几个时机进入源码树：

1. **解压 `php-src` 后**：Artifact hook 会在构建任何 Package 前应用兼容性修改。
2. **`buildconf` / configure 前**：PHP target 会应用版本化补丁集并改写构建系统输入。
3. **构建某个 SAPI 或扩展前**：条件 hook 会修改生成的 Makefile 或扩展源码。
4. **围绕某个构建阶段临时应用**：少量改动会先备份，并在该 SAPI 构建结束后恢复。

带有 `#[PatchDescription]` 的方法由 StaticPHP 通过 lifecycle/DI dispatcher 调用时，会输出 `[PATCH]` 消息，便于追踪构建过程。但它不是完整的机器可读清单：直接方法调用不会经过该日志逻辑，仍有少量源码改写和生成文件修改也没有该标注。

## 解压后应用的修改

每次解压全新的 `php-src` Artifact 时，都会运行 `src/Package/Artifact/php_src.php`。

| 源码版本或条件 | 修改 |
|---|---|
| PHP 低于 8.0 | 应用旧版 Alpine/musl 构建使用的 stream cast 兼容补丁 |
| PHP 8.0.x | 对 DOM、libxml 和 SOAP 应用 libxml2 2.12 兼容补丁，并应用旧版 stream cast 补丁 |
| PHP 8.1.x | 应用旧版 stream cast 补丁 |
| 所有受支持 PHP 分支中的 GD | 使用 StaticPHP 针对不同版本的静态构建配置替换 `ext/gd/config.w32`；低于 PHP 8.2 时还会对 `gdft.c` 应用 `_WIN32` 兼容修复 |
| 内置 IMAP 源码缺少许可证文件 | 添加内置 Apache 许可证文本作为 `ext/imap/LICENSE`，供许可证收集使用 |

GD 和 IMAP 操作修改的是只有在相应扩展存在或参与构建时才有作用的文件。解压 hook 本身会在解压 PHP 源码时运行，此时扩展解析尚未进入构建 stage。

## 版本化 PHP 补丁集

在 `buildconf` 前，Unix 和 Windows 构建都会调用 `SourcePatcher::patchPhpSrc()`。尽管它沿用了“micro patches”这一历史名称，但实际会用于整个 PHP 构建，并不要求选择 `php-micro`。

`config/env.ini` 中默认的 `SPC_MICRO_PATCHES` 值如下：

| 宿主平台 | 默认补丁名称 |
|---|---|
| Linux | `cli_checks`、`disable_huge_page` |
| macOS | `cli_checks`、`macos_iconv` |
| Windows | `static_extensions_win32`、`cli_checks`、`disable_huge_page`、`vcruntime140`、`win32`、`zend_stream`、`cli_static`、`win32_api` |

这些补丁当前的用途如下：

| 补丁 | 用途 |
|---|---|
| `cli_checks` | 在原本直接将 SAPI 名称与 `cli` 比较的 PHP 代码路径中，把 micro SAPI 视作 CLI |
| `disable_huge_page` | 禁用 Linux 的 2 MiB segment alignment 探测，避免 SFX 二进制无谓增大 |
| `macos_iconv` | 让 PHP 的 iconv 检查正确链接 macOS iconv 库 |
| `static_extensions_win32` | 调整包括 Fileinfo 和 OpenSSL 在内的 Windows 扩展配置，以支持静态构建 |
| `cli_static` | 调整 Windows CLI 源码，以生成完全静态的可执行文件 |
| `vcruntime140` | 绕过启动时对动态加载 `vcruntime140` 模块的检查 |
| `win32` | 把 PHP Windows 构建配置从动态 MSVC runtime（`/MD`）切换到静态 runtime（`/MT`） |
| `zend_stream` | 避免 `isatty` 声明与 Windows 静态 CRT 冲突 |
| `win32_api` | 修正受支持 PHP 分支所需的 Windows API 声明 |

补丁文件位于 `src/globals/patch/php-src-patches/`。同一名称可以只有不带版本的文件，如 `zend_stream.patch`；也可以包含 `cli_checks_84.patch` 这样的版本文件。对于带版本的名称，StaticPHP 会选择不高于目标源码 minor 版本的最新补丁。例如，可用 `_81` 和 `_84` 但没有 `_82` 时，PHP 8.2 源码会使用 `_81`。PHP 7.4 会跳过该补丁集加载器。

该目录还包含 `comctl32`、`phar` 和 `static_opcache` 等非默认或按条件选择的补丁。找不到兼容补丁或执行 `patch -p1` 失败时，构建会以 `PatchException` 终止。

## 通用构建期修改

除下方各平台表格中的改动外，PHP target 还会应用两个通用修改。

### StaticPHP 版本元数据

在 PHP `build` stage 前，如果源码尚未包含相关内容，StaticPHP 会在 `main/main.c` 中添加名为 `StaticPHP.version` 的 INI 条目。这样可以在运行时获取生成该二进制的 StaticPHP 版本。

### 硬编码 INI 值

每个 `--with-hardcoded-ini=key=value` 选项都会向所有实际存在且受支持的 SAPI 源码中的 `HARDCODED_INI` 添加一行字面值：

- `sapi/cli/php_cli.c`
- `sapi/micro/php_micro.c`
- `sapi/embed/php_embed.c`

原文件会保存为带 `.bak` 后缀的备份。后续调用再次提供非空值集合时，StaticPHP 会先恢复备份，再应用新的集合，因此从一组配置值改成另一组时不会累积旧值。当前完全不再传入任何 `--with-hardcoded-ini` 选项时不会触发恢复逻辑；要移除先前注入的值，需要重新解压 `php-src`。

::: warning
硬编码值会成为源码和最终二进制的一部分。不要使用该选项保存密码、token 或其他秘密信息。
:::

## Unix 构建期修改

Linux 和 macOS 构建会应用以下与目标相关的修改：

| 条件 | 文件或生成的输入 | 修改 |
|---|---|---|
| 每次 Unix PHP 构建 | `configure.ac` | 使用 `SPC_TARGET` 选择的 libc 取代基于宿主机 `ldd` 的 musl 检测；原文件会被备份 |
| 每次 Unix PHP 构建 | `build/php.m4` 和扩展的 `config.m4` 文件 | 把 `PKG_CHECK_MODULES` 替换为 `PKG_CHECK_MODULES_STATIC`，使依赖检查包含静态链接所需的 private 依赖 |
| PHP 8.3.x | `build/php.m4` | 应用 PHP 8.4 以前使用的 AVX-512 configure cache 兼容补丁 |
| PHP 低于 8.3 | 生成的 `configure` | 使用受支持的 `-std=gnu17` 探测，而不是较新的 `-std=gnu23` 探测 |
| 动态链接的 musl 目标 | `TSRM/TSRM.h` | 为主 TSRM TLS cache 符号添加 default visibility，使采用 `initial-exec` TLS model 的共享扩展可以加载 |
| Linux | 生成的 `Makefile` | 把意外出现的 `//lib` 路径规范化为 `/lib` |
| 使用 Zig 的 Linux | 生成的 `Makefile` | 让 `BUILD_CC` 使用宿主机 `cc` 而不是 `zig-cc`，避免 minilua 等宿主工具被构建为目标环境程序 |
| 存在 release 链接参数 | `ext/standard/info.c` | 从 PHP 信息输出中隐藏 configure 命令 |

解析到 `php-micro` 时，其 Package hook 还会调整生成的 Makefile：embed 前置构建只生成 `libphp.la`，并防止 PHP 主安装步骤过早安装 micro 二进制。

如果 Unix micro 构建选择了 `ext-phar`，`ext/phar/phar.c` 会被临时修改，使追加到当前可执行文件的压缩 PHAR 即使文件名不含 `.phar` 也能被识别。micro stage 结束后会立即恢复原文件。

## Windows 构建期修改

Windows 需要更大范围的构建系统修改，因为 PHP 上游的 Windows 流程通常假定使用动态 CRT、PHP SDK 工具以及基于 import library 的 SAPI。

| Stage 或条件 | 修改 |
|---|---|
| `buildconf.bat` 前 | 从相应的 Windows core 构建规则中移除共享 `dllmain.c` 对象，然后应用版本化补丁集 |
| 缺少 `win32/wsyslog.h` | 使用 `win32/build/wsyslog.mc` 定义的 event ID 生成兼容头文件 |
| PHP 8.1.x | 把 Fiber 汇编对象从链接器参数移动到汇编对象列表，使其参与静态链接 |
| 检测到 Visual Studio | 改写 `win32/build/confutils.js`，使受支持的 Visual Studio 安装报告正确的 PHP toolset 名称 |
| `buildconf.bat` 后 | 禁用生成的 `configure.js` 中的 PHP SDK 版本检查，因为 v3 使用 MSVC 加自管 MSYS2 环境，而不是 PHP SDK binary tools |
| `--enable-micro-win32` | 在 `sapi/micro/php_micro.c` 中定义 `PHP_MICRO_WIN32_NO_CONSOLE`；选项不再启用时会恢复备份 |
| CLI 构建 | 改写生成的 `php.exe` 规则，直接链接 PHP core、CLI SAPI、静态扩展、汇编对象和静态库；同时把 `buildroot/include` 参数放到扩展参数之前 |
| CGI 构建 | 改写生成的 `php-cgi.exe` 规则以支持静态链接，并从 CGI 源码中移除重复的 `ZEND_TSRMLS_CACHE_DEFINE()` |
| Micro 构建 | 添加生成的 micro target 所需的 Fiber 汇编前置依赖，并提供静态库集合 |
| Embed 构建 | 改写生成的 embed target，使 `phpNembed.lib`（例如 `php8embed.lib`）包含 embed SAPI、PHP core、静态扩展和汇编对象，而不再只是 import library |

最后一项 embed 修改也是 Windows FrankenPHP 得以构建的基础：FrankenPHP 通过 CGO 使用 Clang/LLD 链接这个自包含的 embed 库。Unix FrankenPHP 不会应用单独的 php-src 补丁；它直接使用常规 PHP target 生成的 Unix `php-embed` 库。

## 条件式扩展补丁

扩展被解析到构建集合后，其 Package 类可以修改 `php-src/ext/<name>` 下的源码。两个跨版本的典型例子是：

- **Opcache**：`ext-opcache` 会为 PHP 8.0–8.4.x 应用相应版本的静态 Opcache 补丁。PHP 8.2.23 以前和 PHP 8.3.11 以前使用专用 legacy 补丁，其余版本使用版本化的 `static_opcache` 补丁。PHP 8.5 及以后无需该补丁即可使用所需的静态路径。
- **Micro 的 PHAR**：只有 `ext-phar` 参与构建时才会应用前文所述的 Unix micro hook，并在生成 micro 二进制后恢复原文件。

其他扩展类还包含针对特定 PHP、编译器、依赖库或操作系统版本的更小范围兼容修改。这些修改会刻意保留在 `src/Package/Extension/` 中相应扩展的构建逻辑旁，而不加入全局 PHP 补丁集。可以搜索 `#[PatchDescription]` 查看当前带标注的清单。

## 补丁应用与恢复

`SourcePatcher::patchFile()` 会把相对补丁名称解析到 `src/globals/patch/`，通过 reverse dry run 检查补丁是否已经应用，然后调用 `patch --binary -p1`。因此，在同一解压源码树中重复应用基于文件的补丁是安全的。直接字符串替换也按可重复调用的方式编写，再次执行时通常已经找不到需要替换的原始文本。

构建后不存在恢复整个 PHP 源码树的事务。只有 PHAR 和硬编码 INI 备份等明确的临时改动带有专用恢复逻辑。要回到干净的上游源码，请删除并重新解压 `source/php-src`；切换或刷新 PHP 版本时，也可以使用不带 `--keep-source` 的 `switch-php-version`。

v2 的任意 patch point 注入机制不是受支持的 v3 接口。在公开补丁扩展契约确定前，新增 core 补丁应实现为由维护者维护的 Artifact 或 Package hook。

## 添加或更新 Core 补丁

维护内置补丁时：

1. 优先推动上游修复；只有仍受支持的 PHP 分支确实需要时才保留下游补丁。
2. 可复用补丁文件放在 `src/globals/patch/`；需要按版本选择的 micro/static-PHP 补丁集放在 `src/globals/patch/php-src-patches/`。
3. 使用精确的 PHP/平台/依赖条件，避免无关构建无谓修改源码。
4. 确保操作可重复执行，并在预期的上游上下文已经变化时明确失败。
5. 对通过 lifecycle/DI dispatcher 调用的方法添加 `#[PatchDescription]`，使该修改在构建日志中可见；直接调用的方法应显式记录日志。
6. 测试该补丁影响的最旧和最新 PHP 分支；行为变化时同步更新本页的两个语言版本。
