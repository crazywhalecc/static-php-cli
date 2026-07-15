# Performance Engineering

StaticPHP controls how PHP, its extensions, dependency libraries, and SAPIs are compiled. These choices can affect runtime throughput and latency, startup time, memory use, binary size, portability, and build time. They are related, but they are not the same metric: a smaller binary is not necessarily faster, and a slower build can still produce a faster program.

This page records the current v3 defaults, the optimization work already incorporated into the build, and the areas still being investigated. On Linux, libc and linkage form the first layer: the default fully static musl build and a dynamically linked glibc build have different portability, extension-loading, and performance characteristics. Compiler flags, PGO, and LTO form later layers of the same ongoing work.

This page does not claim that a StaticPHP binary is always faster than another PHP distribution. Results depend on the PHP version, CPU, toolchain, extension set, SAPI, libc, linkage, runtime configuration, and workload.

::: warning Current v3 status
The v3 branch does not currently expose stable `--pgi`, `--pgo`, or `--lto` CLI options. PGO orchestration and broader LTO compatibility remain active areas of investigation. The sections below describe their design, current constraints, and the direction of that work rather than a supported release recipe.
:::

## Linux Linkage and libc

StaticPHP's Linux default optimizes for a self-contained artifact, not for the highest possible score on every server workload. `config/env.ini` selects `${GNU_ARCH}-linux-musl`; with the default Zig toolchain this produces a fully static musl binary.

Current performance investigations distinguish three Linux targets:

| Target | libc and final linkage | Runtime requirements | Shared extensions / FFI | Current role |
|---|---|---|---|---|
| `native-native-musl` | musl, fully static | Linux kernel on a compatible CPU | No | Default portable single-file release |
| `native-native-musl -dynamic` | musl, dynamically linked | Compatible musl loader and libraries | Yes | Isolate static-versus-dynamic effects while keeping musl |
| `native-native-gnu.2.17` | glibc, dynamically linked with a 2.17 baseline | glibc 2.17 or newer | Yes | Broad GNU/Linux compatibility and a glibc performance baseline |

The architecture may replace either `native` component, for example `x86_64-linux-musl`. A `-gnu` target without an explicit version targets the selected/current glibc ABI instead of promising the 2.17 floor.

In this context, “dynamically linked PHP” does not mean every dependency is a system `.so`. StaticPHP still builds most selected third-party libraries and PHP extensions as static archives and links them into the PHP executable. The dynamic part is primarily the libc/runtime loader and any extensions explicitly requested through `--build-shared`. This differs from a conventional distribution PHP in which PHP and many dependencies are separate shared objects.

Likewise, a “static extension” and a “static libc” are independent choices. A glibc-dynamic PHP can contain all PHP extensions statically, while a musl-dynamic PHP can load selected `.so` extensions. Only a fully static musl target prevents runtime loading because no dynamic loader participates in the process.

### What Static versus Dynamic Linking Changes

Static and dynamic linkage mainly change startup work, relocation, symbol resolution, deployment, and library sharing. They do not automatically change PHP VM code generation.

- A fully static process does not start through a runtime dynamic loader or relocate a set of DSOs. This can help very short-lived CLI startup, but PHP initialization, extension startup, script parsing, and Opcache often cost more.
- A dynamic process pays loader and relocation costs at startup. Long-running FPM or FrankenPHP workers amortize this cost over many requests.
- Static linking gives the linker a larger closed world for section garbage collection and, when explicitly enabled, LTO. Static linking alone does not perform cross-object optimization.
- Dynamic glibc allows shared extensions, FFI, NSS integration, and replacement or security updates of runtime libraries without rebuilding PHP. A fully static artifact must be rebuilt to receive libc or embedded-library fixes.
- Shared-library code pages can be reused across different programs. Identical static PHP worker processes still share file-backed executable pages with one another, so “static means every worker duplicates all code in RAM” is not an accurate model.

StaticPHP's [performance testing issue #838](https://github.com/crazywhalecc/static-php-cli/issues/838) recorded one PHP 8.4 test in which static and dynamic builds stayed within about 1% in either direction across repeated runs. This is useful evidence that linkage alone may not dominate steady-state PHP execution, but it is not a universal result. Short CLI programs, many DSOs, different loaders, and I/O-heavy extensions can produce a different outcome.

### musl versus glibc Performance Surfaces

libc differences appear only when a workload reaches libc or behavior implemented around it. Pure PHP code spends much of its time in the Zend VM, Opcache, and Zend Memory Manager. PHP's own [Zend Memory Manager](https://wiki.php.net/internals/zend_mm) handles most request-bound allocations, so a microbenchmark dominated by PHP arrays and opcodes may expose less of the system allocator than a C extension or server runtime does.

| Workload area | Why the libc can matter | Main observed signals |
|---|---|---|
| CPU-bound PHP opcodes | Usually dominated by PHP VM, compiler, CPU target, and Opcache rather than libc | Same PHP/VM/compiler first; then change only libc/linkage |
| Persistent and native allocations | Persistent PHP allocations, C extensions, libraries, and server code can call libc allocation directly | throughput, allocation contention, RSS, fragmentation after a long soak |
| ZTS and FrankenPHP | Thread creation, TLS, mutexes, allocator contention, and per-thread caches differ | scaling by worker/thread count, p95/p99, RSS per thread |
| DNS and name services | Resolver strategy, NSS, `/etc/resolv.conf`, and caching differ | cached and uncached lookup latency, failure/failover latency, query volume |
| Locale, iconv, regex, and stdio | Implementations and supported behavior differ, not merely speed | correctness first, then throughput with production locale/data |
| Very short CLI commands | Loader/relocation and initialization are a larger fraction of total time | cold and warm wall time over many process launches |
| Long-running workers | Startup is amortized; allocator, threading, syscalls, and application behavior become more important | sustained throughput, tail latency, peak/steady RSS |

glibc's allocator provides per-thread caches and multiple arenas whose limits can be tuned; these can improve concurrent allocation throughput at the cost of retained memory. The [glibc allocation tunables](https://sourceware.org/glibc/manual/latest/html_node/Memory-Allocation-Tunables.html) therefore belong in a benchmark record. musl uses a different allocator and prioritizes low baseline overhead, bounded fragmentation, and static-linking suitability; neither design guarantees lower latency or RSS for every PHP application.

Resolver behavior can reverse an apparent “libc performance” result. The [musl functional-differences documentation](https://wiki.musl-libc.org/functional-differences-from-glibc.html) notes that musl queries configured nameservers in parallel, while traditional glibc behavior tries them sequentially. This can improve failover latency but increase DNS traffic. glibc also participates in configurable NSS modules. StaticPHP's DNS comparisons therefore keep resolver, cache state, search domains, and failure conditions consistent instead of treating resolver results as a CPU-only benchmark.

musl uses a much smaller default thread stack than typical glibc configurations. That can reduce reserved address space for thread-heavy programs but can also expose stack assumptions in extensions. It is a deployment and memory characteristic, not proof that one libc executes PHP opcodes faster.

### Current Comparison Model

A direct comparison between the default musl-static binary and a distribution glibc PHP changes libc, linker, compiler, flags, PHP configuration, extensions, dependency versions, and INI at once. It therefore cannot attribute the full difference to libc.

The project uses a controlled matrix with the same StaticPHP commit, PHP source, Zig version, flags, extensions, and SAPI. The three builds are represented by the following commands:

```bash
EXTENSIONS="bcmath,curl,openssl,opcache"

# A: default-style, fully static musl
SPC_TARGET=native-native-musl spc build:php "$EXTENSIONS" --build-cli

# B: dynamically linked musl; requires a musl runtime
SPC_TARGET="native-native-musl -dynamic" spc build:php "$EXTENSIONS" --build-cli

# C: dynamically linked glibc with a defined compatibility floor
SPC_TARGET=native-native-gnu.2.17 spc build:php "$EXTENSIONS" --build-cli
```

Each result is associated with its output, build manifest, `php -i`, and logs. The actual linkage is identified from the binary rather than inferred from its filename:

```bash
file buildroot/bin/php
readelf -l buildroot/bin/php | grep 'Requesting program interpreter'
```

A versus B estimates the linkage effect while holding musl constant. B versus C estimates libc/runtime effects with dynamic linkage on both sides. A distribution PHP remains useful as an external reference, but it is reported separately because its compiler and packaging configuration are different.

The current comparison set covers both no-op startup and real CLI scripts. FPM and FrankenPHP measurements separate worker warmup from sustained throughput and tail latency. libc-sensitive coverage includes allocation-heavy extension work, concurrent requests, DNS cache hit/miss/failure cases, file I/O, and long-running RSS/fragmentation behavior.

### Deployment Characteristics

- **musl static** is the StaticPHP default and represents the portable, self-contained deployment profile without shared extensions or FFI.
- **glibc dynamic** represents integration with a GNU/Linux runtime, including `.so` extensions, FFI, the host name-service stack, and workloads for which glibc produces better measured behavior.
- **musl dynamic** represents a musl-based runtime that still requires shared extensions. It also provides the middle point in the controlled comparison above, while giving up much of the default's single-file portability.

The default is designed to remain predictable and broadly deployable. The fastest target is workload-specific and continues to be evaluated against the actual CPU, PHP version, SAPI, and application behavior.

## Where Optimization Enters the v3 Build

The v3 build passes optimization settings through several layers:

1. `config/env.ini` supplies platform defaults. `config/env.custom.ini` or the process environment can override them.
2. `ToolchainManager` selects Zig on Linux, MSVC on Windows, and system, Homebrew, or MacPorts Clang on macOS by default. It initializes `CC`, `CXX`, `AR`, `RANLIB`, and `LD`.
3. Package executors and `Package::getLibExtra*Flags()` pass the default flags into Autoconf, CMake, and package-specific builds.
4. The PHP target passes its PHP-specific flags to both `./configure` and `make` on Unix. Static extensions compiled as part of php-src see the same compiler environment.
5. The FrankenPHP target links the PHP embed library through CGO. Its `CGO_CFLAGS` and `CGO_LDFLAGS` include the PHP and dependency-library flags, followed by Go and external-linker settings.
6. Deployment extracts separate debug information and strips Unix binaries by default. UPX is optional and affects size and startup behavior, not generated PHP code.

This propagation is intentionally broad, but it is not yet perfectly uniform. Packages with hand-written compiler commands or unusual upstream build systems may ignore part of the global flag set. Extending consistent C, C++, and linker-flag propagation across those adapters remains part of the optimization work.

## Current Default Configuration

The authoritative values are in `config/env.ini`. The following tables explain the current v3 defaults rather than replacing that file.

### Linux Compiler Defaults

These compiler defaults apply to the Linux targets above. The v3 toolchain manager selects Zig unless a maintainer-level `SPC_TOOLCHAIN` override is used.

| Variable | Current default | Purpose |
|---|---|---|
| `SPC_DEFAULT_CFLAGS` | `-fPIC -O3 -pipe -fno-plt -fno-semantic-interposition -fstack-clash-protection -fno-omit-frame-pointer -mno-omit-leaf-frame-pointer -ffunction-sections -fdata-sections` | Common C flags for libraries and PHP |
| `SPC_DEFAULT_CXXFLAGS` | `${SPC_DEFAULT_CFLAGS}` | Common C++ flags |
| `SPC_DEFAULT_LDFLAGS` | `-Wl,-z,relro -Wl,--as-needed -Wl,-z,now -Wl,-z,noexecstack -Wl,--gc-sections` | Link hardening, dependency pruning, and unused-section removal |
| `SPC_CMD_VAR_PHP_MAKE_EXTRA_CFLAGS` | `-g -fstack-protector-strong -fno-ident -fPIE -fvisibility=hidden -fvisibility-inlines-hidden ${SPC_DEFAULT_CFLAGS}` | PHP and in-tree extension C flags |
| `SPC_CMD_VAR_PHP_MAKE_EXTRA_CXXFLAGS` | The corresponding C++ form | PHP and in-tree extension C++ flags |
| `SPC_CMD_VAR_PHP_MAKE_EXTRA_LDFLAGS` | Empty | Additional PHP program linker flags |

The important performance-related defaults are:

- `-O3` favors runtime speed over compilation time and code size.
- `-fno-semantic-interposition` gives the optimizer more freedom for ELF symbols that cannot be interposed in the final program.
- `-fno-plt` avoids the traditional ELF procedure linkage table sequence where the toolchain can do so.
- `-ffunction-sections -fdata-sections` combined with `--gc-sections` lets the linker remove unused functions and data.
- Frame pointers and `-g` are deliberately retained during compilation for profiling and debugging. The deployed Unix binary is stripped by default, while separate debug information is written under `buildroot/debug/`.

The default PHP configure command also enables `--enable-re2c-cgoto`, disables unused SAPIs and shared PHP libraries, and builds only the requested extensions. These choices reduce unnecessary code; their runtime effect remains workload-dependent.

### macOS

macOS uses the native target and system Clang by default. v3 also supports the newer upstream LLVM distributions from Homebrew and MacPorts through `SPC_USE_LLVM=brew` and `SPC_USE_LLVM=port`. The Homebrew variant selects its `clang`, `clang++`, `llvm-ar`, and `llvm-ranlib` as one coherent toolchain rather than mixing them with the Apple-provided tools.

Homebrew LLVM is one of StaticPHP's current macOS performance paths. Its newer optimizer and code-generation backend can produce faster PHP binaries for some PHP versions and workloads, while also allowing the project to evaluate compiler improvements without waiting for Apple's system Clang release cycle. This is not a guaranteed uplift: PHP VM changes, CPU architecture, extensions, Opcache, and compiler flags can change the result. The project's [performance testing issue #838](https://github.com/crazywhalecc/static-php-cli/issues/838) shows that compiler and optimization-level effects are version- and workload-specific, while the later [v3 toolchain discussion](https://github.com/crazywhalecc/static-php-cli/issues/985#issuecomment-3860775962) records access to an up-to-date compiler as the motivation for the additional macOS LLVM path.

| Variable | Current default | Purpose |
|---|---|---|
| `SPC_DEFAULT_CFLAGS` | `--target=${MAC_ARCH}-apple-darwin -O3 -fno-omit-frame-pointer -mno-omit-leaf-frame-pointer -ffunction-sections -fdata-sections` | Target selection, optimization, observability, and section splitting |
| `SPC_DEFAULT_CXXFLAGS` | `${SPC_DEFAULT_CFLAGS}` | Common C++ flags |
| `SPC_DEFAULT_LDFLAGS` | `-Wl,-dead_strip` | Removes unreachable Mach-O code and data |
| PHP extra flags | `-g`, stack protection, PIC/PIE, hidden visibility, and the defaults above | PHP and in-tree extension compilation |

### Windows

Windows uses MSVC and the static CRT patches described in [PHP Source Modifications](./php-src-changes). PHP's release configuration supplies its upstream optimization flags, while StaticPHP rewrites the final CLI, CGI, micro, and embed link rules to include `/LTCG`. Dependency CMake builds commonly use `/MT /Os /Ob1 /DNDEBUG`; individual packages may choose other release flags.

`--no-strip` preserves PDB/debug information while keeping `/O2` optimization in StaticPHP's rewritten build command. This is intentional: debug symbols and disabled optimization are separate concerns.

Windows FrankenPHP uses Clang/LLD for the CGO link. Some Windows libraries explicitly avoid `/GL` because MSVC LTCG objects are not accepted by this link path. `/LTCG` on the final link line therefore does not mean every input participates in whole-program optimization.

## Optimization Dimensions

StaticPHP treats performance as several related metrics rather than one score. Each dimension is affected by a different part of the build:

| Goal | Usually measured as | Main influencing controls |
|---|---|---|
| Request throughput | requests/s at fixed concurrency | SAPI mode, Opcache, worker model, PHP version, compiler and CPU target |
| Tail latency | p50/p95/p99 and error rate | workload, warmup, contention, worker count, JIT/Opcache, memory pressure |
| CLI execution | wall time, CPU time, peak RSS | startup cost, Opcache CLI setting, extension set, PHP/compiler version |
| Binary size | deployed bytes and debug-file bytes | extension set, section GC/dead stripping, symbol stripping, UPX |
| Portability | oldest CPU/OS/libc that runs the binary | `SPC_TARGET`, CPU ISA flags, static versus dynamic libc |
| Build speed | clean build wall time and peak disk/RAM | `SPC_CONCURRENCY`, toolchain, LTO/PGO, number of packages |

Comparable results keep the PHP version, commit, extensions, dependency versions, SAPI, ZTS/NTS mode, INI, target libc, compiler, CPU frequency policy, and workload constant.

## Runtime Optimization Surfaces

### 1. Reproducible Configuration Overrides

`config/env.custom.ini` provides the reproducible override layer while leaving `config/env.ini` as the project baseline. Values replace the complete default string rather than being appended automatically, so performance investigations preserve the baseline and isolate one changed factor.

For example, an x86-64-v3-specific investigation retains the Linux defaults and adds the ISA level:

```ini
[linux]
SPC_DEFAULT_CFLAGS="-fPIC -O3 -pipe -fno-plt -fno-semantic-interposition -fstack-clash-protection -fno-omit-frame-pointer -mno-omit-leaf-frame-pointer -ffunction-sections -fdata-sections -march=x86-64-v3"
SPC_DEFAULT_CXXFLAGS="${SPC_DEFAULT_CFLAGS}"
```

This may allow more vector and instruction selection, but the output no longer runs on CPUs below that ISA level. `-march=native` narrows portability further by coupling the result to the build host's CPU class.

PHP-only investigations are represented by `SPC_CMD_VAR_PHP_MAKE_EXTRA_CFLAGS` and its C++ counterpart rather than the global defaults. Individual-package investigations use snake-case variables such as `libaom_CFLAGS`, `libaom_CXXFLAGS`, and `libaom_LDFLAGS`. Package-specific flags are combined with the defaults when that package uses the common v3 executors.

### 2. Toolchain and PHP-Version Effects

The compiler can matter more than an individual optimization flag. [Issue #838](https://github.com/crazywhalecc/static-php-cli/issues/838) found large GCC-versus-Clang differences for some PHP 8.4 x86-64 tests because PHP's older VM can use GCC global register variables. That result is not universal: architecture, PHP version, VM implementation, and workload all matter, and PHP 8.5 changes the comparison with its newer VM.

Linux v3 defaults to Zig for target flexibility and reproducible libc selection, not because it is guaranteed to produce the fastest binary for every workload. The project's performance work compares this predictable baseline with native toolchains on deployment architectures. `SPC_TOOLCHAIN` remains an internal/maintainer control until a stable user-facing selector is documented.

### 3. SAPI, Thread Safety, and Runtime Settings

- FrankenPHP requires ZTS. CLI, FPM, CGI, micro, and embed remain NTS by default unless the selected SAPI or application requires thread safety.
- Historical tests in issue #838 observed small ZTS/NTS differences in that particular suite; the result remains specific to that workload.
- Including `ext-opcache` only builds the extension. Its performance characteristics also depend on runtime INI, including `opcache.enable_cli` for repeated CLI execution and application-specific JIT settings.
- `--disable-opcache-jit` changes build capability; it is not a substitute for runtime benchmarking. StaticPHP disables the undefined-behavior sanitizer where necessary when Opcache JIT is built on Linux.
- FrankenPHP performance also depends on worker versus classic mode, worker count, application boot behavior, Caddy modules, and Go runtime settings. Compiler flags cannot compensate for an unrepresentative server configuration.

### 4. Speed, Symbols, and Compression

The default Unix deployment extracts debug information, strips the runtime binary, and keeps the debug file separately. `--no-strip` represents the unstripped variant while current v3 keeps optimization enabled.

`--with-upx-pack` is a size optimization for Linux and Windows. It can change startup time, memory mapping, security-tool behavior, and debuggability, so the project treats it as a packaging tradeoff rather than a PHP execution optimization.

## Profile-Guided Optimization (PGO)

PGO builds an instrumented program, runs a representative workload to collect profiles, and recompiles using the observed branch, call, and value frequencies. The compiler can then improve code layout, inlining, and hot/cold decisions beyond static heuristics.

A complete PGO design contains three distinct phases:

1. **Instrumentation** builds the same PHP version, SAPI, extensions, dependencies, and target with profile-generation flags.
2. **Training** covers startup and important production paths at representative traffic mixes and concurrency, including a clean shutdown so raw profiles are flushed.
3. **Profile use** merges the raw profiles with the matching toolchain utility and rebuilds the identical source/configuration with profile-use flags.

Profiles are inputs to the build, not portable benchmark artifacts. Meaningful changes to PHP, extensions, dependencies, compiler version, flags, SAPI linkage, or workload invalidate them. A narrow or stale profile can improve the trained route while making untrained behavior worse.

### StaticPHP PGO Status

The experimental v3 design explores a dedicated PGO context and lifecycle hooks for CLI, CGI, FPM, micro, embed, and FrankenPHP. The concepts under evaluation include:

- instrument and profile-use phases, plus an optional context-sensitive second instrumentation phase;
- per-SAPI raw-profile directories and merged `.profdata` files;
- clean rebuilds when switching the active SAPI profile;
- shutdown patches for php-src and FrankenPHP, because the Go/CGO process does not reliably run the libc `atexit` path that normally flushes profiles;
- LLVM profile tooling and special linker/runtime handling for Zig, Clang, GCC, and FrankenPHP.

This work is not part of the stable v3 feature set. The current v3 tree contains some preparatory compatibility handling—for example, FrankenPHP detects manual `-fprofile*` C flags when using native GCC, suppresses missing-profile errors, and links `libgcov`—but complete training, merging, invalidation, and rebuilding orchestration remains under investigation.

A raw `-fprofile-generate` addition to the global defaults is not equivalent to this orchestration: it also instruments dependency libraries, changes link requirements, increases build and runtime cost, and may fail to flush usable data. For that reason, the current internal variables do not yet constitute a stable release workflow.

### FrankenPHP's Go PGO

[PR #1142](https://github.com/crazywhalecc/static-php-cli/pull/1142) added FrankenPHP's upstream `default.pgo` to the v2 xcaddy build when present. That profile optimizes the Go portion using a profile distributed with FrankenPHP. It does not train php-src, the PHP embed library, extensions, or CGO glue against your application.

A future end-to-end FrankenPHP PGO design may therefore contain two profile sources:

- FrankenPHP's upstream Go profile for the Go/Caddy code;
- application-specific compiler profiles for PHP, static extensions, and CGO-linked C code.

Their ownership and invalidation rules are separate concerns in the current design exploration.

## Link-Time Optimization (LTO)

LTO retains compiler intermediate representation until link time so the optimizer can work across translation units. Full LTO processes the combined program more aggressively; ThinLTO distributes more work and usually reduces memory and build-time cost.

Static linking does not automatically enable LTO. Conversely, adding `-flto` to only the final link is insufficient: relevant objects and archives must be produced with a compatible compiler, LTO mode, archiver, ranlib, and linker plugin.

Unix v3 does not enable LTO by default. Historical work made more archive commands respect the selected `AR`, but the project later removed default LTO work because compatibility fixes across dependency libraries were costly. In [issue #838](https://github.com/crazywhalecc/static-php-cli/issues/838), one test environment observed only about 2% runtime improvement while ThinLTO roughly doubled build time and full LTO increased it by about seven times. These are historical measurements on one setup, not a promised ratio.

Current Unix LTO investigations account for the following constraints:

1. Objects, archives, and the final link need one compatible LLVM/Clang or GCC toolchain family.
2. C, C++, PHP, and linker flags need the same mode, such as `-flto=thin`.
3. Limiting the initial scope to PHP and its in-tree extensions separates core results from dependency-library compatibility.
4. Archive contents and the final link log reveal whether LTO reached the intended objects; build completion alone does not.
5. SAPI and extension smoke tests, clean build time, binary size, throughput, and tail latency together describe the result.

LTO can expose package bugs, unsupported assembly, incompatible archives, symbol-export problems, and Go/CGO linker limitations. It can also increase code size or regress instruction-cache behavior. These compatibility and maintenance costs are why Unix LTO remains opt-in while its benefits continue to be evaluated.

## Performance Evidence and Reproducibility

StaticPHP performance investigations record enough context for another maintainer to reproduce the result:

- StaticPHP commit and registry/package revisions;
- PHP, FrankenPHP, Go, compiler, linker, and profile-tool versions;
- host and target OS, libc, architecture, and exact `SPC_TARGET`;
- CPU model, available instruction set, power/frequency policy, memory, and virtualization;
- complete extension and dependency set, ZTS/NTS, SAPI, static/dynamic linkage, and INI;
- all overridden environment variables and whether the binary was stripped or UPX-packed;
- workload source revision, dataset, route mix, concurrency, duration, warmup, and repetitions;
- median result, variation, p95/p99 where relevant, peak RSS, binary size, and clean build time.

The project alternates a baseline and one changed build on the same machine. Multiple repetitions and confidence intervals carry more weight than a single best run, and benchmark scripts remain part of the result alongside the raw measurements.

## History and Design Lessons

The following merged changes and issue discussions explain why the current defaults and cautions exist. Unmerged implementation work is represented only by its technical concepts elsewhere on this page.

| Record | Outcome and lesson |
|---|---|
| [Issue #385](https://github.com/crazywhalecc/static-php-cli/issues/385), performance degradation | A v2 `--no-strip` path also selected `-O0`, making one reported Laravel test about three times slower. This led to customizable PHP compiler variables and reinforced that debug symbols must not silently disable optimization. Current v3 keeps optimized flags with `--no-strip`. |
| [PR #806](https://github.com/crazywhalecc/static-php-cli/pull/806), Zig toolchain | Added target-flexible Zig support and documented its build/compatibility tradeoffs. Its discussion also contains the static/dynamic within-1% observation and early GCC/Clang/Zig comparisons. Toolchain choice is partly about portability and libc targeting, not only benchmark speed. |
| [Issue #838](https://github.com/crazywhalecc/static-php-cli/issues/838), performance testing | Established reproducible compiler, ZTS/NTS, Opcache, LTO, architecture, and PHP VM comparisons. Its results provide background for the `-O3` defaults but also show why conclusions must remain version- and workload-specific. The issue remains the main historical performance notebook. |
| [Issue #985](https://github.com/crazywhalecc/static-php-cli/issues/985), v3 toolchains | Recorded the decision to prefer predictable defaults while retaining performance-oriented alternatives. On Linux this means Zig alongside native GCC paths; on macOS the discussion also motivated access to a current upstream LLVM instead of being limited to Apple's compiler release cycle. |
| [Issue #862](https://github.com/crazywhalecc/static-php-cli/issues/862), `--pgo=script.php` proposal | Closed after concluding that one universal training script interface was unrealistic. PGO needs SAPI-aware lifecycle orchestration and user-owned representative training. |
| [PR #966](https://github.com/crazywhalecc/static-php-cli/pull/966), flags and stripping | Unified PHP make flags and improved separate debug stripping, helping decouple optimization from symbol handling. |
| [PR #1142](https://github.com/crazywhalecc/static-php-cli/pull/1142), FrankenPHP Go PGO | Added use of FrankenPHP's bundled Go `default.pgo` in the v2 build. This is distinct from application-trained PHP/CGO PGO. |
| [Issue #1088](https://github.com/crazywhalecc/static-php-cli/issues/1088), native intrinsics | Open design request for declaring CPU intrinsic levels across both libraries and PHP/extensions instead of managing every ISA flag manually. |
| [PR #1150](https://github.com/crazywhalecc/static-php-cli/pull/1150), macOS `-fno-plt` | Removed an ELF-only flag from the macOS defaults after it broke dependency configure checks. Optimization flags must be target-format aware. |

Most of this investigation and implementation has been led by [@henderkes](https://github.com/henderkes), with reviews, integration, platform fixes, and testing from the other StaticPHP contributors. The project continues to preserve workloads, raw results, and rationale as this work evolves; an individual compiler flag is only the last line of a much broader performance decision.
