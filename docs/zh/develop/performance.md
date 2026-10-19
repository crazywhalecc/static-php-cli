# 性能

本页说明 StaticPHP 构建的 PHP 性能如何、构建过程已经为你做了哪些优化、你自己可以调整什么，以及项目性能研究的方向。本页同时面向两类读者：用 StaticPHP 构建 PHP、希望得到快速且合适的二进制的使用者，以及想理解或参与性能优化工作的贡献者。

先说明一点：不存在对所有负载都最快的构建配置。结果取决于 PHP 版本、CPU、工具链、扩展集合、SAPI、libc 和运行时配置。本页描述的是默认值和取舍，而不是万能配方。

## 默认构建已包含的优化

默认的 StaticPHP 构建就是优化过的构建。不传任何额外参数，你就能得到：

- **`-O3` 优化**：PHP 和所有依赖库都偏向运行速度而非编译时间和代码体积。
- **无用代码消除**：`-ffunction-sections -fdata-sections` 配合 `--gc-sections`（Linux）或 `-Wl,-dead_strip`（macOS），移除未使用的函数和数据，保持二进制精简。
- **更低成本的 ELF 符号处理**（Linux）：`-fno-semantic-interposition` 和 `-fno-plt` 避免只在可插入（interposable）共享库场景下才需要的间接调用。
- **链接加固**（Linux）：`relro`、`-z now`、`noexecstack`、`--as-needed`。
- **Windows 链接时代码生成**：StaticPHP 改写最终 CLI/CGI/micro/embed 的链接规则并加入 `/LTCG`。
- **精简的 PHP 配置**：只构建你请求的 SAPI 和扩展，默认启用 `--enable-re2c-cgoto`。
- **部署二进制默认 strip**，调试信息单独保存在 `buildroot/debug/`。`--no-strip` 保留符号但*不会*关闭优化——这是 [Issue #385](https://github.com/crazywhalecc/static-php-cli/issues/385) 的教训：v2 的 `--no-strip` 会静默选择 `-O0`，让一个 Laravel 测试慢了约 3 倍。

这些默认值以 `config/env.ini` 中的定义为准（`SPC_DEFAULT_CFLAGS`、`SPC_DEFAULT_LDFLAGS`、`SPC_CMD_VAR_PHP_MAKE_EXTRA_CFLAGS` 及各平台变体）。

编译阶段有意保留 frame pointer 和 `-g`，使性能分析工具（perf、samply、Instruments）能产生可用的调用栈；部署用的二进制最终会被 strip，因此运行时没有额外成本。

## Linux：选择链接方式和 libc

Linux 默认是 **musl 全静态二进制**（`SPC_TARGET=native-native-musl`，Zig 工具链）：单个文件即可在任何兼容内核上运行，不支持共享扩展和 FFI。这是分发的最佳选择，但它首先是*可移植性*选择，其次才是性能选择。

性能研究关注三个 target：

| Target | libc / 链接方式 | 共享扩展、FFI | 何时选择 |
|---|---|---|---|
| `native-native-musl` | musl，全静态 | 不支持 | 默认：可移植单文件分发 |
| `native-native-musl -dynamic` | musl，动态链接 | 支持 | 在 musl 下隔离链接方式影响的实验 |
| `native-native-gnu.2.17` | glibc，动态链接（2.17 基线） | 支持 | 需要 `.so` 扩展、FFI、NSS，或实测 glibc 更适合你的负载 |

这里的“动态”仅指 libc 和 loader 是动态的——StaticPHP 仍会把大多数库和扩展静态链接进可执行文件。只有 musl 全静态 target 无法在运行时加载扩展。

**链接方式真的影响速度吗？** 比大多数人想象的要小。[Issue #838](https://github.com/crazywhalecc/static-php-cli/issues/838) 的测量显示，PHP 8.4 下静态和动态构建的差异在约 1% 以内。静态链接可以省去极短 CLI 调用中的 loader 和重定位时间；长时间运行的 FPM 或 FrankenPHP worker 则完全将其摊销。同样，musl 与 glibc 的差异只在负载实际触及 libc 的地方出现——大量原生内存分配下的 allocator、ZTS 下的线程、DNS 解析、locale/iconv。纯 PHP 代码运行在 Zend VM 和 Zend Memory Manager 中，基本绕开系统 allocator。

实践建议：

- **默认用 musl 静态**。可移植、可预测，对典型 PHP 负载没有可测量的性能损失。
- **需要共享扩展或 FFI，或自己的基准测试显示 glibc 更优时，选 glibc 动态**（glibc 可调的 per-thread allocator cache 对分配密集的多线程负载可能有帮助）。
- **用你自己的真实应用做基准测试**。仅 resolver 行为（musl 并行查询 nameserver，传统 glibc 依次尝试）一项，就足以在 DNS 密集的应用中主导一场“libc 性能”对比。

如果你要发布或对比数据，请从二进制本身确认链接方式，而不是根据文件名推断：

```bash
file buildroot/bin/php
readelf -l buildroot/bin/php | grep 'Requesting program interpreter'
```

## 工具链和 PHP 版本

编译器选择的影响可能超过任何单个参数：

- **Linux 默认使用 Zig**（`zig cc`，基于 LLVM），因为它提供灵活的交叉编译 target 支持和可复现的 musl/glibc 选择——而不是因为它总能生成最快的二进制。native GCC 仍可通过维护者级 `SPC_TOOLCHAIN` 使用。
- **GCC 与 Clang**：[Issue #838](https://github.com/crazywhalecc/static-php-cli/issues/838) 在部分 PHP 8.4 x86-64 测试中发现较大差异，因为较旧的 Zend VM 能利用 GCC 的 global register variable。PHP 8.5 的新 VM 改变了这一局面——一个 PHP 版本上的结论不能自动迁移到下一个版本。
- **macOS** 默认使用系统 Clang；`SPC_USE_LLVM=brew` 或 `=port` 选择 Homebrew/MacPorts 的较新 upstream LLVM 完整工具链。这让构建不受 Apple 发布节奏限制，对部分 PHP 版本和负载有帮助，但并非必然带来提升。
- **Windows** 使用 MSVC，最终链接带 `/LTCG`。FrankenPHP 的 CGO 链接走 Clang/LLD，因此部分库会刻意禁用 `/GL`——链接命令行中出现 `/LTCG` 不代表每个目标文件（object）都参与了全程序优化（whole-program optimization）。

每个 PHP 版本的编译器格局是最容易被低估的性能因素之一：升级 PHP（或编译器）可能胜过数周的参数调优。

## 调整你自己的构建

所有默认值都可以通过 `config/env.custom.ini`（或进程环境变量）可复现地覆盖。配置值会完整替换默认字符串，因此你可以保持基线可见、一次只改变一个因素。

### CPU 指令集级别

Linux 上影响最大的用户级可调项是 ISA 基线。加入 `-march=x86-64-v3` 可让编译器使用 AVX2 等指令：

```ini
[linux]
SPC_DEFAULT_CFLAGS="-fPIC -O3 -pipe -fno-plt -fno-semantic-interposition -fstack-clash-protection -fno-omit-frame-pointer -mno-omit-leaf-frame-pointer -ffunction-sections -fdata-sections -march=x86-64-v3"
SPC_DEFAULT_CXXFLAGS="${SPC_DEFAULT_CFLAGS}"
```

代价是可移植性：产物无法在低于该级别的 CPU 上运行，`-march=native` 则把产物绑定到构建机。[Issue #1088](https://github.com/crazywhalecc/static-php-cli/issues/1088) 正在追踪一种更友好的方式，在依赖库和 PHP 之间统一声明 intrinsic 级别，而不是手工管理参数。

### 覆盖范围

- **全部**：`SPC_DEFAULT_CFLAGS` / `SPC_DEFAULT_CXXFLAGS` / `SPC_DEFAULT_LDFLAGS`
- **仅 PHP 和源码树内扩展**：`SPC_CMD_VAR_PHP_MAKE_EXTRA_CFLAGS`（及 CXX/LD 对应项）
- **单个库**：snake-case 变量，如 `libaom_CFLAGS`，由通用构建 executor 与默认参数合并

### 注意：很多“构建性能”问题其实是运行时问题

- **Opcache 是运行时特性**。构建 `ext-opcache` 只是让它可用；`opcache.enable_cli`、JIT 配置和真实应用行为才决定它的效果。`--disable-opcache-jit` 改变的是构建能力，不是实测速度。
- **ZTS 与 NTS**：FrankenPHP 要求 ZTS，其余 SAPI 默认 NTS。历史测试显示的差异较小，且局限于当时的负载。
- **FrankenPHP** 的性能主要由 worker 模式和数量、应用启动行为、Caddy 模块和 Go runtime 设置决定——没有编译参数能弥补不具代表性的服务器配置。
- **UPX**（`--with-upx-pack`）是体积优化，代价是启动时间、内存映射和安全工具行为。它不会让 PHP 执行得更快。

## 正在进行的性能研究

StaticPHP 的性能工作是活跃且渐进式的，主要方向：

**PGO（Profile-Guided Optimization）**——[PR #1138](https://github.com/crazywhalecc/static-php-cli/pull/1138) 正在带来 v3 原生的 PGO：插桩构建、用代表性负载训练、用收集到的 profile 重新构建。难点在编排而非参数：每个 SAPI 独立的 profile 目录、切换 profile 时的干净重建、保证 profile 真正 flush 的 shutdown 补丁（Go/CGO 进程会绕过 libc `atexit` 路径），以及源码变化后的失效处理。直接在全局参数中添加 `-fprofile-generate` 会连所有依赖库一起插桩，甚至可能无法产出可用数据——所以 PGO 会以结构化功能的形式到来，而不是一个环境变量。相关：[PR #1142](https://github.com/crazywhalecc/static-php-cli/pull/1142) 已经在构建中使用 FrankenPHP 自带的 Go profile（`default.pgo`）优化 Go/Caddy 部分——它只优化 Go 代码，不会针对你的应用训练 php-src。早期的一刀切提案（[Issue #862](https://github.com/crazywhalecc/static-php-cli/issues/862)）被关闭，因为有意义的训练必须感知 SAPI 且由用户负责。

**LTO（Link-Time Optimization）**——Unix 上默认不启用。在 [Issue #838](https://github.com/crazywhalecc/static-php-cli/issues/838) 的一个测试环境中，运行时收益约 2%，而 ThinLTO 构建时间翻倍、Full LTO 增至七倍。LTO 还会在数十个依赖库中暴露 archive 不兼容、Package bug 和 CGO 链接限制，因此当前方向是限定范围的 LTO（PHP 核心和源码树内扩展、单一工具链家族、一致的 `-flto=thin`），而不是全局开关。Windows 如上文所述已在最终链接使用 `/LTCG`。

**更好的参数传递**——参数沿 `env.ini` → 工具链 → Package executor → PHP/FrankenPHP target 传递，但使用手写编译命令的包仍可能忽略部分全局参数。让传递保持一致是持续进行的工作，它会让其他所有优化受益。

## 我们如何做基准测试

本项目的性能结论遵循可复现的方法论，并以 [Issue #838](https://github.com/crazywhalecc/static-php-cli/issues/838) 作为公开记录：

- 只比较**单一因素**不同的构建，保持 PHP 版本、commit、扩展、SAPI、ZTS/NTS、INI、libc 和编译器一致。
- 在**同一台机器**上交替运行基线和候选构建并重复多次；中位数和波动范围比一次最佳结果更有意义。
- 记录完整上下文：工具链版本、`SPC_TARGET`、CPU 型号和频率策略、扩展集合、环境变量覆盖、负载 revision、并发、预热，以及适用的吞吐量/尾延迟/RSS/二进制体积/构建时间。
- 验证实际构建出来的东西（用 `file`/`readelf` 确认链接方式，用构建日志和 `php -i` 确认参数），而不是轻信调用参数。

如果你要报告性能问题或退化，附上这些上下文会让报告更具可操作性。

## 历史记录与经验

| 记录 | 经验 |
|---|---|
| [Issue #385](https://github.com/crazywhalecc/static-php-cli/issues/385) | 调试符号绝不能静默关闭优化；促成了可定制 PHP 编译变量。 |
| [PR #806](https://github.com/crazywhalecc/static-php-cli/pull/806) | 为 target 灵活性加入 Zig 工具链；讨论中包含静态/动态约 1% 差异的观察。 |
| [Issue #838](https://github.com/crazywhalecc/static-php-cli/issues/838) | 主要的性能记录：`-O3` 默认值背后的编译器、ZTS/NTS、Opcache、LTO、VM 和架构对比。 |
| [Issue #985](https://github.com/crazywhalecc/static-php-cli/issues/985) | v3 工具链决策：优先可预测默认值，保留性能导向的替代方案。 |
| [Issue #862](https://github.com/crazywhalecc/static-php-cli/issues/862) | 通用 PGO 训练脚本不现实；训练必须感知 SAPI 且由用户负责。 |
| [PR #966](https://github.com/crazywhalecc/static-php-cli/pull/966) | 统一 PHP make 参数；将 strip 与优化解耦。 |
| [PR #1142](https://github.com/crazywhalecc/static-php-cli/pull/1142) | 构建中使用 FrankenPHP 自带 Go profile；不同于应用训练的 PGO。 |
| [Issue #1088](https://github.com/crazywhalecc/static-php-cli/issues/1088) | 开放：在依赖库和 PHP 之间统一声明 CPU intrinsic 级别。 |
| [PR #1138](https://github.com/crazywhalecc/static-php-cli/pull/1138) | 进行中的 v3 原生 PGO 实现。 |
| [PR #1150](https://github.com/crazywhalecc/static-php-cli/pull/1150) | 优化参数必须感知目标文件格式（一个 ELF 专用参数曾破坏 macOS configure 检查）。 |

## 参与贡献

大部分性能调查和实现由 [@henderkes](https://github.com/henderkes) 主导，其他贡献者参与 review、集成和测试——也欢迎更多帮助：

- **基准测试**：真实应用上的可复现测量（尤其是负载下的 FrankenPHP/FPM、分配密集的扩展、DNS 密集负载）永远有价值。按照上面的方法论，把结果分享到 [Issue #838](https://github.com/crazywhalecc/static-php-cli/issues/838) 或新开 Issue。
- **平台覆盖**：ARM 服务器、较旧的 glibc 基线和 Windows/MSVC 行为的探索程度不如 Linux x86_64。
- **PGO 和 LTO**：在你的负载上测试 [PR #1138](https://github.com/crazywhalecc/static-php-cli/pull/1138) 并报告构建或运行时问题，会直接塑造这个功能。

由于 StaticPHP 处在 PHP、依赖生态和多种工具链的交叉点，这里的发现经常会暴露上游 bug——在 php-src、依赖库、Zig 或 LLVM 中。回流到上游的报告和修复改善的是整个 PHP 社区，而不只是静态构建。
