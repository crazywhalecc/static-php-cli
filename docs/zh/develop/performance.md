# 性能工程

StaticPHP 控制 PHP、扩展、依赖库以及各个 SAPI 的编译方式。这些选择会影响运行时吞吐量与延迟、启动时间、内存占用、二进制体积、可移植性和构建时间。不过它们并不是同一个指标：更小的二进制不一定更快，更慢的构建过程也可能生成更快的程序。

本页记录当前 v3 的默认值、已经进入构建流程的优化，以及仍在探索的方向。在 Linux 上，libc 和链接模型构成第一层：默认的 musl 全静态构建与 glibc 动态构建在可移植性、扩展加载和性能方面具有不同特征；编译参数、PGO 和 LTO 则是这项持续优化工作的后续层次。

本页并不宣称 StaticPHP 二进制在所有情况下都比其他 PHP 发行方式更快。结果取决于 PHP 版本、CPU、工具链、扩展集合、SAPI、libc、链接方式、运行时配置和实际负载。

::: warning 当前 v3 状态
v3 分支目前没有提供稳定的 `--pgi`、`--pgo` 或 `--lto` CLI 选项。PGO 编排和更广泛的 LTO 兼容性仍处于持续探索阶段。下文描述的是设计、当前约束和优化方向，而不是受支持的正式版本构建方法。
:::

## Linux 链接方式与 libc

StaticPHP 的 Linux 默认配置优先保证产物自包含，并不追求在所有服务器负载中获得最高分。`config/env.ini` 默认选择 `${GNU_ARCH}-linux-musl`；配合默认 Zig 工具链，会生成完全静态链接 musl 的二进制。

当前性能研究会区分以下三个 Linux target：

| Target | libc 与最终链接方式 | 运行时要求 | 共享扩展 / FFI | 当前定位 |
|---|---|---|---|---|
| `native-native-musl` | musl，全静态 | 兼容 CPU 上的 Linux 内核 | 不支持 | 默认的可移植单文件发布 |
| `native-native-musl -dynamic` | musl，动态链接 | 兼容的 musl loader 和库 | 支持 | 保持 musl 不变，隔离静态/动态链接影响 |
| `native-native-gnu.2.17` | glibc，动态链接，以 2.17 为基线 | glibc 2.17 或更高版本 | 支持 | 广泛兼容 GNU/Linux，并作为 glibc 性能基线 |

可以用具体架构替换任一 `native`，例如 `x86_64-linux-musl`。不指定版本的 `-gnu` target 会面向所选/当前 glibc ABI，而不是承诺兼容 glibc 2.17。

这里的“动态链接 PHP”并不意味着每个依赖都是系统 `.so`。StaticPHP 仍会把大多数选中的第三方库和 PHP 扩展构建为静态 archive，并链接进 PHP 可执行文件。动态部分主要是 libc/runtime loader，以及通过 `--build-shared` 明确选择的扩展。这与 PHP 和许多依赖都作为独立共享对象提供的传统发行版 PHP 不同。

同样，“静态扩展”和“静态 libc”是两个独立选择。glibc 动态 PHP 可以把所有 PHP 扩展静态编入，musl 动态 PHP 也可以加载指定的 `.so` 扩展。只有 musl 全静态 target 因进程中不存在动态 loader 而无法在运行时加载扩展。

### 静态与动态链接改变了什么

静态和动态链接主要改变启动工作、重定位、符号解析、部署方式和库共享，并不会自动改变 PHP VM 的代码生成方式。

- 全静态进程不会通过 runtime dynamic loader 启动，也不需要重定位一组 DSO。这可能有利于极短 CLI 的启动，但 PHP 初始化、扩展启动、脚本解析和 Opcache 往往占据更多时间。
- 动态进程需要在启动时支付 loader 和 relocation 成本。长时间运行的 FPM 或 FrankenPHP worker 会把这部分成本分摊到许多请求上。
- 静态链接为 section garbage collection 以及显式启用的 LTO 提供了更完整的 closed world；但静态链接本身不会进行跨 object 优化。
- glibc 动态构建支持共享扩展、FFI、NSS 集成，并允许在不重建 PHP 的情况下替换或安全更新运行时库。全静态产物则必须重建才能获得 libc 或内嵌依赖修复。
- 不同程序可以复用共享库的代码页；相同的静态 PHP worker 进程之间也会共享文件映射的可执行代码页，因此“静态链接会让每个 worker 在内存里复制所有代码”并不准确。

StaticPHP 的[性能测试 Issue #838](https://github.com/crazywhalecc/static-php-cli/issues/838) 记录过一组 PHP 8.4 测试：多次运行时，静态和动态构建在两个方向上的差异都不超过约 1%。这说明仅链接方式未必会主导 PHP 稳态执行性能，但不能把它当作普遍结论。短 CLI、大量 DSO、不同 loader 以及 I/O 密集扩展都可能得到不同结果。

### musl 与 glibc 的性能影响面

只有负载实际进入 libc 或围绕 libc 实现的功能时，两者差异才会出现。纯 PHP 代码的大量时间会花在 Zend VM、Opcache 和 Zend Memory Manager 中。PHP 自己的 [Zend Memory Manager](https://wiki.php.net/internals/zend_mm) 处理大多数 request-bound allocation，因此以 PHP array 和 opcode 为主的 microbenchmark，对系统 allocator 的敏感度可能低于 C 扩展或服务器 runtime。

| 负载领域 | libc 可能产生影响的原因 | 主要观测项 |
|---|---|---|
| CPU 密集 PHP opcode | 通常由 PHP VM、编译器、CPU target 和 Opcache 主导，而不是 libc | 先保持 PHP/VM/编译器一致，再只改变 libc/链接方式 |
| 持久及原生内存分配 | PHP persistent allocation、C 扩展、依赖库和服务器代码可能直接调用 libc allocator | 吞吐量、allocation contention、RSS、长时间运行后的 fragmentation |
| ZTS 与 FrankenPHP | 线程创建、TLS、mutex、allocator contention 和 per-thread cache 存在差异 | 随 worker/thread 数量的扩展性、p95/p99、每线程 RSS |
| DNS 与名称服务 | resolver 策略、NSS、`/etc/resolv.conf` 和缓存不同 | 有/无缓存的查询延迟、失败/切换延迟、查询量 |
| Locale、iconv、regex、stdio | 实现和支持行为不同，不只是速度不同 | 先验证正确性，再用生产 locale/数据测试吞吐量 |
| 极短 CLI 命令 | loader/relocation 和初始化占总时间的比例更大 | 大量进程启动的 cold/warm wall time |
| 长时间运行的 worker | 启动成本被分摊，allocator、线程、系统调用和应用行为更加重要 | 持续吞吐量、尾延迟、峰值/稳定 RSS |

glibc allocator 提供 per-thread cache 和多个 arena，并允许调整其上限；这可能提高并发分配吞吐量，但代价是保留更多内存。因此，StaticPHP 的基准记录会包含 [glibc allocation tunable](https://sourceware.org/glibc/manual/latest/html_node/Memory-Allocation-Tunables.html)。musl 使用不同的 allocator，更关注较低基线开销、受控 fragmentation 及静态链接适用性；两种设计都不能保证在所有 PHP 应用中具有更低延迟或 RSS。

Resolver 行为甚至可能反转一个表面上的“libc 性能”结果。[musl 功能差异文档](https://wiki.musl-libc.org/functional-differences-from-glibc.html)指出，musl 会并行查询配置的 nameserver，而传统 glibc 行为会依次尝试。这可能改善故障切换延迟，也可能增加 DNS 流量；glibc 还会参与可配置的 NSS module。因此，StaticPHP 的 DNS 对比会保持 resolver、缓存状态、search domain 和失败条件一致，而不会把 resolver 结果当作单纯 CPU benchmark。

musl 的默认线程栈明显小于典型 glibc 配置。这可以减少线程密集程序保留的地址空间，也可能暴露扩展对栈大小的假设。它属于部署和内存特征，不能证明某个 libc 执行 PHP opcode 更快。

### 当前对比模型

默认 musl 静态二进制与发行版 glibc PHP 的直接对比会同时改变 libc、linker、编译器、参数、PHP 配置、扩展、依赖版本和 INI，因此无法把全部差异归因于 libc。

项目采用相同 StaticPHP commit、PHP 源码、Zig 版本、参数、扩展和 SAPI 形成受控矩阵。三个构建对应以下命令：

```bash
EXTENSIONS="bcmath,curl,openssl,opcache"

# A：默认形式的 musl 全静态构建
SPC_TARGET=native-native-musl spc build:php "$EXTENSIONS" --build-cli

# B：musl 动态构建，需要 musl runtime
SPC_TARGET="native-native-musl -dynamic" spc build:php "$EXTENSIONS" --build-cli

# C：glibc 动态构建，并指定兼容性下限
SPC_TARGET=native-native-gnu.2.17 spc build:php "$EXTENSIONS" --build-cli
```

每项结果都与其产物、build manifest、`php -i` 和日志一起保存。实际链接方式由二进制本身确认，而不是根据文件名推断：

```bash
file buildroot/bin/php
readelf -l buildroot/bin/php | grep 'Requesting program interpreter'
```

A 对比 B 用于在保持 musl 不变时估计链接方式的影响；B 对比 C 用于在两者均动态链接时估计 libc/runtime 的影响。发行版 PHP 仍可作为外部参考，但由于编译器和打包配置不同，会单独记录。

当前对比集合同时覆盖 no-op 启动和实际 CLI 脚本。FPM 与 FrankenPHP 会把 worker 预热和持续吞吐量、尾延迟分开记录。libc 敏感场景则包括使用扩展的大量内存分配、并发请求、DNS cache hit/miss/failure、文件 I/O，以及长时间运行后的 RSS/fragmentation。

### 部署特征

- **musl 静态**是 StaticPHP 默认值，代表不依赖共享扩展或 FFI 的可移植、自包含部署形态。
- **glibc 动态**代表与 GNU/Linux runtime 集成的部署形态，包括 `.so` 扩展、FFI、宿主名称服务栈，以及实测更适合 glibc 的负载。
- **musl 动态**代表仍需要共享扩展的 musl runtime，同时也是上述受控对比的中间项；它会放弃默认构建的大部分单文件可移植性。

默认值以可预测和广泛部署为目标。最快 target 与具体负载相关，项目仍在结合实际 CPU、PHP 版本、SAPI 和应用行为持续评估。

## 优化配置如何进入 v3 构建

v3 会通过多个层次传递优化配置：

1. `config/env.ini` 提供各平台默认值；`config/env.custom.ini` 或进程环境变量可以覆盖它们。
2. `ToolchainManager` 默认在 Linux 选择 Zig、Windows 选择 MSVC、macOS 选择系统/Homebrew/MacPorts Clang，并初始化 `CC`、`CXX`、`AR`、`RANLIB` 和 `LD`。
3. Package executor 和 `Package::getLibExtra*Flags()` 把默认参数传入 Autoconf、CMake 及包专用构建过程。
4. Unix 上的 PHP target 会把 PHP 专用参数同时传给 `./configure` 和 `make`。作为 php-src 一部分编译的静态扩展会看到相同的编译环境。
5. FrankenPHP target 通过 CGO 链接 PHP embed 库。它的 `CGO_CFLAGS` 和 `CGO_LDFLAGS` 会包含 PHP 与依赖库参数，再加入 Go 和外部链接器配置。
6. 部署阶段默认提取单独的调试信息并精简 Unix 二进制。UPX 是可选项，它影响体积和启动行为，而不会优化生成的 PHP 机器码。

这种传递有意覆盖较大范围，但目前还不是完全一致。使用手写编译命令或特殊上游构建系统的包可能忽略部分全局参数。让这些 adapter 一致传递 C、C++ 和 linker 参数，仍是当前优化工作的组成部分。

## 当前默认配置

权威值位于 `config/env.ini`。下面的表格用于解释当前 v3 默认值，不能替代该文件。

### Linux 编译器默认值

这些编译器默认值适用于上文各 Linux target。除非使用维护者级别的 `SPC_TOOLCHAIN` 覆盖项，否则 v3 toolchain manager 会选择 Zig。

| 变量 | 当前默认值 | 用途 |
|---|---|---|
| `SPC_DEFAULT_CFLAGS` | `-fPIC -O3 -pipe -fno-plt -fno-semantic-interposition -fstack-clash-protection -fno-omit-frame-pointer -mno-omit-leaf-frame-pointer -ffunction-sections -fdata-sections` | 依赖库和 PHP 共用的 C 参数 |
| `SPC_DEFAULT_CXXFLAGS` | `${SPC_DEFAULT_CFLAGS}` | 共用 C++ 参数 |
| `SPC_DEFAULT_LDFLAGS` | `-Wl,-z,relro -Wl,--as-needed -Wl,-z,now -Wl,-z,noexecstack -Wl,--gc-sections` | 链接加固、依赖裁剪及未使用 section 移除 |
| `SPC_CMD_VAR_PHP_MAKE_EXTRA_CFLAGS` | `-g -fstack-protector-strong -fno-ident -fPIE -fvisibility=hidden -fvisibility-inlines-hidden ${SPC_DEFAULT_CFLAGS}` | PHP 和源码树内扩展的 C 参数 |
| `SPC_CMD_VAR_PHP_MAKE_EXTRA_CXXFLAGS` | 对应的 C++ 形式 | PHP 和源码树内扩展的 C++ 参数 |
| `SPC_CMD_VAR_PHP_MAKE_EXTRA_LDFLAGS` | 空 | PHP 程序的附加链接参数 |

其中与性能最相关的默认项是：

- `-O3` 偏向运行速度，而不是编译时间和代码体积。
- `-fno-semantic-interposition` 让优化器能够更自由地处理最终程序中不会被 interpose 的 ELF 符号。
- `-fno-plt` 在工具链允许时避免传统的 ELF procedure linkage table 调用序列。
- `-ffunction-sections -fdata-sections` 配合 `--gc-sections`，使链接器可以移除未使用的函数和数据。
- 编译阶段有意保留 frame pointer 和 `-g`，方便性能分析和调试。部署 Unix 二进制时默认会执行 strip，同时把独立调试信息写入 `buildroot/debug/`。

默认 PHP configure 命令还会启用 `--enable-re2c-cgoto`、禁用未选择的 SAPI 和共享 PHP 库，并只构建请求的扩展。这些选择能减少无关代码，实际运行效果仍取决于具体负载。

### macOS

macOS 默认使用 native target 和系统 Clang。v3 也通过 `SPC_USE_LLVM=brew` 和 `SPC_USE_LLVM=port` 支持 Homebrew、MacPorts 提供的较新 upstream LLVM。Homebrew 变体会把其中的 `clang`、`clang++`、`llvm-ar` 和 `llvm-ranlib` 作为一套完整工具链使用，避免与 Apple 自带工具混用。

Homebrew LLVM 是 StaticPHP 当前在 macOS 上采用的性能优化路径之一。较新的 optimizer 和 code-generation backend 在部分 PHP 版本及负载中可以生成运行性能更好的 PHP 二进制，也让项目不必等待 Apple 系统 Clang 的发布节奏即可评估新的编译器优化。这并不是固定的性能保证：PHP VM 变化、CPU 架构、扩展、Opcache 和编译参数都会改变结果。项目的[性能测试 Issue #838](https://github.com/crazywhalecc/static-php-cli/issues/838)表明编译器及优化等级的影响与版本和负载相关，后续的 [v3 工具链讨论](https://github.com/crazywhalecc/static-php-cli/issues/985#issuecomment-3860775962)则记录了增加 macOS LLVM 路径的动机，即获得不受 Apple 编译器版本限制的较新工具链。

| 变量 | 当前默认值 | 用途 |
|---|---|---|
| `SPC_DEFAULT_CFLAGS` | `--target=${MAC_ARCH}-apple-darwin -O3 -fno-omit-frame-pointer -mno-omit-leaf-frame-pointer -ffunction-sections -fdata-sections` | 目标选择、优化、可观测性及 section 拆分 |
| `SPC_DEFAULT_CXXFLAGS` | `${SPC_DEFAULT_CFLAGS}` | 共用 C++ 参数 |
| `SPC_DEFAULT_LDFLAGS` | `-Wl,-dead_strip` | 移除不可达的 Mach-O 代码和数据 |
| PHP 附加参数 | `-g`、stack protection、PIC/PIE、hidden visibility 以及上述默认项 | PHP 和源码树内扩展编译 |

### Windows

Windows 使用 MSVC，以及[对 PHP 源码的修改](./php-src-changes)中介绍的静态 CRT 补丁。PHP release 配置提供上游优化参数，StaticPHP 会改写最终 CLI、CGI、micro 和 embed 的链接规则并加入 `/LTCG`。依赖的 CMake 构建通常使用 `/MT /Os /Ob1 /DNDEBUG`，个别 Package 可能选择其他 release 参数。

使用 `--no-strip` 时，StaticPHP 会保留 PDB/调试信息，同时在改写的构建命令中继续使用 `/O2`。这是有意设计：调试符号与关闭优化是两件不同的事。

Windows FrankenPHP 通过 Clang/LLD 完成 CGO 链接。部分 Windows 库会明确禁用 `/GL`，因为该链接路径无法接受 MSVC LTCG object。最终链接行出现 `/LTCG`，并不意味着每个输入都参与了 whole-program optimization。

## 优化维度

StaticPHP 将性能视为多个彼此相关的指标，而不是单一分数。每个维度受到不同构建环节影响：

| 目标 | 通常记录为 | 主要影响项 |
|---|---|---|
| 请求吞吐量 | 固定并发下的 requests/s | SAPI 模式、Opcache、worker 模型、PHP 版本、编译器和 CPU target |
| 尾延迟 | p50/p95/p99 和错误率 | 负载、预热、竞争、worker 数量、JIT/Opcache、内存压力 |
| CLI 执行 | wall time、CPU time、peak RSS | 启动成本、Opcache CLI 设置、扩展集合、PHP/编译器版本 |
| 二进制体积 | 部署文件和调试文件字节数 | 扩展集合、section GC/dead stripping、符号精简、UPX |
| 可移植性 | 能运行该二进制的最旧 CPU/OS/libc | `SPC_TARGET`、CPU ISA 参数、静态或动态 libc |
| 构建速度 | 干净构建 wall time、峰值磁盘和内存 | `SPC_CONCURRENCY`、工具链、LTO/PGO、Package 数量 |

可比较的结果会保持 PHP 版本、commit、扩展、依赖版本、SAPI、ZTS/NTS、INI、目标 libc、编译器、CPU 频率策略及负载一致。

## 运行时优化层面

### 1. 可复现的配置覆盖

`config/env.custom.ini` 提供可复现的覆盖层，同时让 `config/env.ini` 保持为项目基线。配置值会替换完整默认字符串而不会自动追加，因此性能研究会保留基线，并一次隔离一个变化因素。

例如，面向 x86-64-v3 的研究会保留 Linux 默认值并加入对应 ISA level：

```ini
[linux]
SPC_DEFAULT_CFLAGS="-fPIC -O3 -pipe -fno-plt -fno-semantic-interposition -fstack-clash-protection -fno-omit-frame-pointer -mno-omit-leaf-frame-pointer -ffunction-sections -fdata-sections -march=x86-64-v3"
SPC_DEFAULT_CXXFLAGS="${SPC_DEFAULT_CFLAGS}"
```

这可能让编译器使用更多向量和指令选择，但产物无法在低于该 ISA level 的 CPU 上运行。`-march=native` 会进一步收窄可移植性，使产物与构建机的 CPU 类型绑定。

PHP 范围的研究由 `SPC_CMD_VAR_PHP_MAKE_EXTRA_CFLAGS` 及其 C++ 对应项表示，而不是改变全局默认值。单个 Package 的研究使用 snake-case 变量，例如 `libaom_CFLAGS`、`libaom_CXXFLAGS` 和 `libaom_LDFLAGS`。当 Package 使用 v3 通用 executor 时，Package 专用参数会与默认参数合并。

### 2. 工具链和 PHP 版本的影响

编译器造成的差异可能大于单个优化参数。[Issue #838](https://github.com/crazywhalecc/static-php-cli/issues/838) 在部分 PHP 8.4 x86-64 测试中发现 GCC 与 Clang 之间有较大差异，因为较旧的 PHP VM 可以使用 GCC global register variable。该结果并不普遍适用：架构、PHP 版本、VM 实现和负载都会影响结论，PHP 8.5 的新 VM 也改变了这项比较。

Linux v3 默认使用 Zig，是为了 target 灵活性和可复现的 libc 选择，并不代表它在所有负载下都一定生成最快的二进制。项目的性能工作会在实际部署架构上对比这个可预测基线与 native toolchain。在稳定的用户级选择器得到正式文档前，`SPC_TOOLCHAIN` 仍属于内部/维护者控制项。

### 3. SAPI、线程安全及运行时配置

- FrankenPHP 要求 ZTS。CLI、FPM、CGI、micro 和 embed 默认保持 NTS，只有所选 SAPI 或应用确实需要线程安全时才涉及 ZTS。
- Issue #838 的历史测试在特定测试集中观察到的 ZTS/NTS 差异较小；该结论仍局限于当时的负载。
- 加入 `ext-opcache` 只代表构建该扩展；它的性能特征还取决于运行时 INI，包括重复 CLI 执行中的 `opcache.enable_cli` 和应用特定的 JIT 配置。
- `--disable-opcache-jit` 改变的是构建能力，不能代替运行时测试。在 Linux 构建 Opcache JIT 时，StaticPHP 会在必要位置禁用 undefined-behavior sanitizer。
- FrankenPHP 性能还取决于 worker/classic 模式、worker 数量、应用启动行为、Caddy 模块及 Go runtime 设置。编译参数无法弥补不具代表性的服务器配置。

### 4. 速度、符号和压缩

Unix 默认部署流程会提取调试信息、精简运行时二进制，并单独保留调试文件。`--no-strip` 代表未精简形态，当前 v3 在这种形态下仍会保留优化参数。

`--with-upx-pack` 是 Linux 和 Windows 的体积优化。它可能改变启动时间、内存映射、安全工具行为和可调试性，因此项目将它视为打包取舍，而不是 PHP 执行性能优化。

## Profile-Guided Optimization（PGO）

PGO 会先生成插桩程序，运行具有代表性的负载收集 profile，然后根据观察到的分支、调用和值频率重新编译。这样，编译器能够在代码布局、inline 和 hot/cold 决策方面超越静态启发式规则。

完整的 PGO 设计包含三个独立阶段：

1. **插桩阶段**使用相同的 PHP 版本、SAPI、扩展、依赖和 target，并加入 profile-generation 参数完成构建。
2. **训练阶段**以具有代表性的流量比例和并发覆盖启动及重要生产路径，也包含正常关闭进程以 flush raw profile。
3. **Profile-use 阶段**通过匹配工具链的 profile 工具合并 raw profile，并用 profile-use 参数重新构建完全一致的源码和配置。

Profile 是构建输入，不是可移植的基准产物。PHP、扩展、依赖、编译器版本、参数、SAPI 链接方式或负载发生实质变化后，原有 profile 即会失效。范围过窄或已经过期的 profile 可能加速训练过的 route，却让未训练路径变慢。

### StaticPHP PGO 状态

v3 的实验性设计正在探索面向 CLI、CGI、FPM、micro、embed 和 FrankenPHP 的独立 PGO context 与 lifecycle hook。目前评估的概念包括：

- 插桩和 profile-use 阶段，以及可选的第二轮 context-sensitive 插桩；
- 每个 SAPI 独立的 raw-profile 目录和合并后的 `.profdata` 文件；
- 切换活动 SAPI profile 时执行干净重建；
- php-src 和 FrankenPHP 的 shutdown 补丁，因为 Go/CGO 进程不会可靠执行通常负责 flush profile 的 libc `atexit` 路径；
- LLVM profile 工具，以及 Zig、Clang、GCC 和 FrankenPHP 所需的特殊链接/runtime 处理。

这项工作尚未进入稳定的 v3 功能集。当前 v3 源码已经包含部分前置兼容处理，例如 FrankenPHP 在 native GCC 下发现手动 `-fprofile*` C 参数时，会忽略 missing-profile error 并链接 `libgcov`；完整的训练、合并、失效和重建编排仍在探索中。

仅把 `-fprofile-generate` 加入全局默认值并不等同于完整编排：它还会同时插桩依赖库、改变链接要求、增加构建和运行成本，并且可能无法 flush 出可用数据。因此，当前内部变量尚不构成稳定的发布流程。

### FrankenPHP 的 Go PGO

[PR #1142](https://github.com/crazywhalecc/static-php-cli/pull/1142) 在 v2 xcaddy 构建中加入了对 FrankenPHP 上游 `default.pgo` 的使用。这个 profile 使用随 FrankenPHP 分发的数据优化 Go 部分；它不会针对你的应用训练 php-src、PHP embed 库、扩展或 CGO glue。

未来的端到端 FrankenPHP PGO 设计可能同时包含两类 profile：

- FrankenPHP 上游为 Go/Caddy 代码提供的 Go profile；
- 针对 PHP、静态扩展和 CGO 链接 C 代码的应用专用编译器 profile。

两者的归属及失效规则，是当前设计探索中彼此独立的问题。

## Link-Time Optimization（LTO）

LTO 会把编译器中间表示保留到链接阶段，让优化器跨 translation unit 工作。Full LTO 会更激进地处理合并后的程序；ThinLTO 会分散更多工作，通常能降低内存及构建时间成本。

静态链接不会自动启用 LTO。反过来，只给最终链接加入 `-flto` 也不够：相关 object 和 archive 必须由兼容的编译器、LTO mode、archiver、ranlib 及 linker plugin 生成。

Unix v3 默认不启用 LTO。历史工作让更多 archive 命令使用所选 `AR`，但项目后来移除了默认 LTO 尝试，因为跨依赖库维护兼容修复的成本过高。在 [Issue #838](https://github.com/crazywhalecc/static-php-cli/issues/838) 的一个测试环境中，运行时提升只有约 2%，ThinLTO 构建时间约变成两倍，Full LTO 约变成七倍。这只是特定环境的历史测量结果，不是承诺的固定比例。

当前 Unix LTO 研究会考虑以下约束：

1. object、archive 和最终链接需要使用同一个兼容的 LLVM/Clang 或 GCC 工具链家族。
2. C、C++、PHP 和 linker 参数需要采用相同模式，例如 `-flto=thin`。
3. 初期只覆盖 PHP 和源码树内扩展，有助于把核心结果与依赖库兼容性分开。
4. archive 内容和最终链接日志可以确认 LTO 是否进入预期 object；仅构建成功并不能说明这一点。
5. SAPI 与扩展 smoke test、干净构建时间、二进制体积、吞吐量和尾延迟共同构成结果。

LTO 可能暴露 Package bug、不支持的汇编、archive 不兼容、符号导出问题以及 Go/CGO 链接限制，也可能增加代码体积或造成 instruction cache 退化。这些兼容性与维护成本，是 Unix LTO 在持续评估收益期间仍保持 opt-in 的原因。

## 性能证据与可复现性

StaticPHP 的性能研究会记录足够的上下文，使其他维护者可以复现结果：

- StaticPHP commit 以及 registry/Package revision；
- PHP、FrankenPHP、Go、编译器、链接器和 profile 工具版本；
- 宿主与目标 OS、libc、架构，以及完整的 `SPC_TARGET`；
- CPU 型号、可用指令集、电源/频率策略、内存和虚拟化环境；
- 完整扩展与依赖集合、ZTS/NTS、SAPI、静态/动态链接方式和 INI；
- 所有覆盖的环境变量，以及二进制是否 strip 或使用 UPX；
- 负载源码 revision、数据集、route 比例、并发、持续时间、预热和重复次数；
- 中位数、波动范围、适用时的 p95/p99、peak RSS、二进制体积和干净构建时间。

项目会在同一台机器上交替运行基线和单项改动构建。多次重复和置信区间比一次最佳结果更有意义，基准脚本也会和原始测量结果一起保留。

## 历史记录与设计经验

以下已合并修改和 Issue 讨论解释了当前默认值和注意事项的来源。未合并的实现工作仅以技术概念的形式出现在本文其他位置。

| 记录 | 结果与经验 |
|---|---|
| [Issue #385](https://github.com/crazywhalecc/static-php-cli/issues/385)，性能退化 | v2 的一条 `--no-strip` 路径同时选择了 `-O0`，使报告中的一个 Laravel 测试约慢了三倍。这促成了可定制 PHP 编译变量，并再次说明调试符号不能静默关闭优化。当前 v3 的 `--no-strip` 会保留优化参数。 |
| [PR #806](https://github.com/crazywhalecc/static-php-cli/pull/806)，Zig 工具链 | 加入 target 灵活的 Zig 支持并记录构建/兼容性取舍；讨论中还包含静态/动态差异在 1% 内的观察，以及早期 GCC/Clang/Zig 对比。工具链选择部分是为了可移植性与 libc target，而不只是基准速度。 |
| [Issue #838](https://github.com/crazywhalecc/static-php-cli/issues/838)，性能测试 | 建立了可复现的编译器、ZTS/NTS、Opcache、LTO、架构及 PHP VM 对比。其结果为 `-O3` 默认值提供了背景，也展示了为什么结论必须绑定版本和负载。该 Issue 仍是主要的历史性能笔记。 |
| [Issue #985](https://github.com/crazywhalecc/static-php-cli/issues/985)，v3 工具链 | 记录了优先采用可预测默认值，同时保留性能导向替代方案的决策。Linux 对应 Zig 与 native GCC 路径；macOS 的讨论也促成了较新 upstream LLVM 的接入，使构建不受 Apple 编译器发布节奏限制。 |
| [Issue #862](https://github.com/crazywhalecc/static-php-cli/issues/862)，`--pgo=script.php` 提案 | 因一个通用训练脚本接口并不现实而关闭。PGO 需要感知 SAPI 的 lifecycle 编排，以及由用户负责的代表性训练。 |
| [PR #966](https://github.com/crazywhalecc/static-php-cli/pull/966)，参数和 strip | 统一 PHP make 参数并改进独立调试信息精简流程，帮助解耦优化与符号处理。 |
| [PR #1142](https://github.com/crazywhalecc/static-php-cli/pull/1142)，FrankenPHP Go PGO | 在 v2 构建中加入 FrankenPHP 自带 Go `default.pgo` 的使用；它不同于应用训练的 PHP/CGO PGO。 |
| [Issue #1088](https://github.com/crazywhalecc/static-php-cli/issues/1088)，native intrinsic | 尚未关闭的设计需求，希望在依赖库与 PHP/扩展之间统一声明 CPU intrinsic level，而不是手工管理每一项 ISA 参数。 |
| [PR #1150](https://github.com/crazywhalecc/static-php-cli/pull/1150)，macOS `-fno-plt` | 因一个 ELF 专用参数破坏依赖 configure 检查，将其从 macOS 默认值移除。优化参数必须感知目标文件格式。 |

其中大部分调查和性能优化由 [@henderkes](https://github.com/henderkes) 主导，其他 StaticPHP 贡献者参与了 review、集成、平台修复和测试。随着这项工作继续推进，项目会保留负载、原始结果和决策理由；单个编译参数只是更广泛性能决策的最后一行。
