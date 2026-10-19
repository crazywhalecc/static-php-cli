# Performance

This page explains how StaticPHP-built PHP performs, what the build already optimizes for you, what you can tune yourself, and where the project's performance research is heading. It is written for two audiences at once: users who build PHP with StaticPHP and want a fast, fit-for-purpose binary, and contributors who want to understand or extend the optimization work.

One caveat up front: no build configuration is fastest for every workload. Results depend on PHP version, CPU, toolchain, extensions, SAPI, libc, and runtime configuration. This page describes defaults and tradeoffs, not a universal recipe.

## What You Get by Default

A default StaticPHP build is already an optimized build. Without passing any extra flags you get:

- **`-O3` optimization** for PHP and all dependency libraries, favoring runtime speed over compile time and code size.
- **Dead code elimination**: `-ffunction-sections -fdata-sections` combined with `--gc-sections` (Linux) or `-Wl,-dead_strip` (macOS) removes unused functions and data, keeping binaries smaller.
- **Cheaper ELF symbol handling** on Linux: `-fno-semantic-interposition` and `-fno-plt` let the compiler avoid indirection that only matters for interposable shared libraries.
- **Link hardening** on Linux: `relro`, `-z now`, `noexecstack`, `--as-needed`.
- **Link-time code generation on Windows**: StaticPHP rewrites the final CLI/CGI/micro/embed link rules to include `/LTCG`.
- **A lean PHP configuration**: only the SAPIs and extensions you request are built, and `--enable-re2c-cgoto` is enabled by default.
- **Stripped deployed binaries** with debug info preserved separately under `buildroot/debug/`. `--no-strip` keeps symbols *without* disabling optimization — a lesson learned from [issue #385](https://github.com/crazywhalecc/static-php-cli/issues/385), where v2's `--no-strip` silently selected `-O0` and made one Laravel test about 3× slower.

The authoritative values live in `config/env.ini` (`SPC_DEFAULT_CFLAGS`, `SPC_DEFAULT_LDFLAGS`, `SPC_CMD_VAR_PHP_MAKE_EXTRA_CFLAGS`, and their per-platform variants).

Frame pointers and `-g` are deliberately kept during compilation so profiling tools (perf, samply, Instruments) produce usable stacks; the deployed binary is stripped anyway, so this costs nothing at runtime.

## Linux: Choosing Linkage and libc

The Linux default is a **fully static musl binary** (`SPC_TARGET=native-native-musl` with the Zig toolchain): a single file that runs on any compatible kernel, with no shared extensions and no FFI. That is the right choice for distribution, but it is a *portability* choice first and a performance choice second.

Three targets matter for performance work:

| Target | libc / linkage | Shared extensions, FFI | Choose when |
|---|---|---|---|
| `native-native-musl` | musl, fully static | No | Default: portable single-file distribution |
| `native-native-musl -dynamic` | musl, dynamic | Yes | Experiments isolating linkage effects under musl |
| `native-native-gnu.2.17` | glibc, dynamic (2.17 baseline) | Yes | You need `.so` extensions, FFI, NSS, or your own benchmarks favor glibc |

"Dynamic" here only means libc and the loader are dynamic — StaticPHP still links most libraries and extensions statically into the executable. Only the fully static musl target cannot load extensions at runtime.

**Does linkage actually affect speed?** Less than most people assume. Measurements in [issue #838](https://github.com/crazywhalecc/static-php-cli/issues/838) showed static and dynamic builds within about 1% of each other on PHP 8.4. Static linking can shave loader and relocation time off very short CLI invocations; long-running FPM or FrankenPHP workers amortize that completely. Likewise, musl-versus-glibc differences only appear where a workload actually reaches libc — the allocator under heavy native allocation, threading under ZTS, DNS resolution, locale/iconv. Pure PHP code runs in the Zend VM and Zend Memory Manager, which mostly bypass the system allocator.

Practical guidance:

- **Default to musl static.** It is portable, predictable, and not measurably slower for typical PHP workloads.
- **Choose glibc dynamic** when you need shared extensions or FFI, or when your own benchmarks show a glibc advantage (glibc's tunable per-thread allocator caches can help allocation-heavy threaded workloads).
- **Benchmark with your real application.** Resolver behavior alone (musl queries nameservers in parallel; glibc traditionally sequentially) can dominate a "libc performance" comparison if your app does many DNS lookups.

If you publish or compare numbers, verify linkage from the binary itself rather than filenames:

```bash
file buildroot/bin/php
readelf -l buildroot/bin/php | grep 'Requesting program interpreter'
```

## Toolchains and PHP Version

Compiler choice can matter more than any individual flag:

- **Linux defaults to Zig** (`zig cc`, LLVM-based) because it provides flexible cross-targeting and reproducible musl/glibc selection — not because it always produces the fastest binary. Native GCC remains available as a maintainer-level option via `SPC_TOOLCHAIN`.
- **GCC vs Clang**: [issue #838](https://github.com/crazywhalecc/static-php-cli/issues/838) found large differences for some PHP 8.4 x86-64 tests because the older Zend VM can exploit GCC's global register variables. PHP 8.5's newer VM changes this picture — conclusions from one PHP version do not transfer automatically to the next.
- **macOS** uses system Clang by default; `SPC_USE_LLVM=brew` or `=port` selects a newer upstream LLVM from Homebrew or MacPorts as a coherent toolchain. This frees builds from Apple's release cycle and helps some PHP versions and workloads, but it is not a guaranteed uplift.
- **Windows** uses MSVC with `/LTCG` on the final link. FrankenPHP's CGO link goes through Clang/LLD, so some libraries deliberately avoid `/GL` — `/LTCG` on the link line does not mean every object participates in whole-program optimization.

The compiler landscape per PHP version is one of the most underappreciated performance factors: upgrading PHP (or the compiler) can outperform weeks of flag tuning.

## Tuning Your Own Build

All defaults can be overridden reproducibly through `config/env.custom.ini` (or process environment variables). Values replace the complete default string, so you keep the baseline visible and change one factor at a time.

### CPU instruction set level

The most impactful user-level knob on Linux is the ISA baseline. Adding `-march=x86-64-v3` lets the compiler use AVX2 and friends:

```ini
[linux]
SPC_DEFAULT_CFLAGS="-fPIC -O3 -pipe -fno-plt -fno-semantic-interposition -fstack-clash-protection -fno-omit-frame-pointer -mno-omit-leaf-frame-pointer -ffunction-sections -fdata-sections -march=x86-64-v3"
SPC_DEFAULT_CXXFLAGS="${SPC_DEFAULT_CFLAGS}"
```

The tradeoff is portability: the binary will not run on CPUs below that level, and `-march=native` ties it to the build machine. [Issue #1088](https://github.com/crazywhalecc/static-php-cli/issues/1088) tracks a friendlier way to declare intrinsic levels across libraries and PHP instead of hand-managing flags.

### Scope of overrides

- **Everything**: `SPC_DEFAULT_CFLAGS` / `SPC_DEFAULT_CXXFLAGS` / `SPC_DEFAULT_LDFLAGS`
- **PHP and in-tree extensions only**: `SPC_CMD_VAR_PHP_MAKE_EXTRA_CFLAGS` (and CXX/LD variants)
- **One library**: snake-case variables such as `libaom_CFLAGS`, merged with the defaults by the common build executors

### Remember: many "build performance" questions are runtime questions

- **Opcache is a runtime feature.** Building `ext-opcache` only makes it available; `opcache.enable_cli`, JIT settings, and real application behavior determine what it does for you. `--disable-opcache-jit` changes build capability, not measured speed.
- **ZTS vs NTS**: FrankenPHP requires ZTS; everything else defaults to NTS. Historical tests showed small differences, specific to those workloads.
- **FrankenPHP** performance is dominated by worker mode and count, application boot behavior, Caddy modules, and Go runtime settings — no compiler flag fixes an unrepresentative server configuration.
- **UPX** (`--with-upx-pack`) is a size optimization that trades startup time, memory mapping, and security-tool behavior. It does not make PHP execute faster.

## Ongoing Research

Performance work in StaticPHP is active and incremental. The main directions:

**PGO (Profile-Guided Optimization)** — [PR #1138](https://github.com/crazywhalecc/static-php-cli/pull/1138) is bringing v3-native PGO: instrument the build, train it on representative workloads, rebuild with the collected profiles. The hard parts are orchestration, not flags: per-SAPI profile directories, clean rebuilds when switching profiles, shutdown patches so profiles actually flush (Go/CGO processes bypass the libc `atexit` path), and invalidation when sources change. A naive `-fprofile-generate` in the global flags instruments every dependency library and may not even produce usable data, which is why PGO will arrive as a structured feature rather than an environment variable. Related: [PR #1142](https://github.com/crazywhalecc/static-php-cli/pull/1142) already uses FrankenPHP's bundled Go profile (`default.pgo`) for the Go/Caddy portion — that optimizes Go code only, not php-src against your application. The earlier one-size-fits-all proposal ([issue #862](https://github.com/crazywhalecc/static-php-cli/issues/862)) was closed because meaningful training has to be SAPI-aware and user-owned.

**LTO (Link-Time Optimization)** — not enabled by default on Unix. In one environment measured in [issue #838](https://github.com/crazywhalecc/static-php-cli/issues/838), the runtime gain was about 2% while ThinLTO doubled build time and full LTO multiplied it by seven. LTO also exposes archive incompatibilities, package bugs, and CGO linker limitations across dozens of dependency libraries, so the current direction is limited-scope LTO (PHP core and in-tree extensions, one toolchain family, consistent `-flto=thin`) rather than a global switch. Windows already uses `/LTCG` on final links as noted above.

**Better flag plumbing** — flags propagate through `env.ini` → toolchain → package executors → PHP/FrankenPHP targets, but packages with hand-written compiler commands can still ignore parts of the global set. Making propagation uniform is ongoing work that benefits every other optimization.

## How We Benchmark

Performance claims in this project follow a reproducible methodology, centered on [issue #838](https://github.com/crazywhalecc/static-php-cli/issues/838) as the public notebook:

- Compare builds that differ in **one factor**, holding PHP version, commit, extensions, SAPI, ZTS/NTS, INI, libc, and compiler constant.
- Alternate baseline and candidate on the **same machine**, with repetitions; medians and variation matter more than a single best run.
- Record full context: toolchain versions, `SPC_TARGET`, CPU model and frequency policy, extension set, env overrides, workload revision, concurrency, warmup, plus throughput/tail-latency/RSS/binary-size/build-time as applicable.
- Verify what was actually built (linkage via `file`/`readelf`, flags via build logs and `php -i`) rather than trusting invocation parameters.

If you report a performance issue or regression, including this context makes it actionable.

## History and Lessons

| Record | Lesson |
|---|---|
| [Issue #385](https://github.com/crazywhalecc/static-php-cli/issues/385) | Debug symbols must never silently disable optimization; led to customizable PHP compiler variables. |
| [PR #806](https://github.com/crazywhalecc/static-php-cli/pull/806) | Zig toolchain added for target flexibility; discussion contains the static/dynamic ~1% observation. |
| [Issue #838](https://github.com/crazywhalecc/static-php-cli/issues/838) | The main performance notebook: compiler, ZTS/NTS, Opcache, LTO, VM, and architecture comparisons behind the `-O3` defaults. |
| [Issue #985](https://github.com/crazywhalecc/static-php-cli/issues/985) | v3 toolchain decision: predictable defaults first, performance-oriented alternatives available. |
| [Issue #862](https://github.com/crazywhalecc/static-php-cli/issues/862) | A universal PGO training script is unrealistic; training must be SAPI-aware and user-owned. |
| [PR #966](https://github.com/crazywhalecc/static-php-cli/pull/966) | Unified PHP make flags; decoupled stripping from optimization. |
| [PR #1142](https://github.com/crazywhalecc/static-php-cli/pull/1142) | FrankenPHP's bundled Go profile used in the build; distinct from application-trained PGO. |
| [Issue #1088](https://github.com/crazywhalecc/static-php-cli/issues/1088) | Open: declaring CPU intrinsic levels uniformly across libraries and PHP. |
| [PR #1138](https://github.com/crazywhalecc/static-php-cli/pull/1138) | v3-native PGO implementation in progress. |
| [PR #1150](https://github.com/crazywhalecc/static-php-cli/pull/1150) | Optimization flags must be target-format aware (an ELF-only flag broke macOS configure checks). |

## Contributing

Most of the performance investigation and implementation has been led by [@henderkes](https://github.com/henderkes), with review, integration, and testing from other contributors — and more help is welcome:

- **Benchmarks**: reproducible measurements on real applications (especially FrankenPHP/FPM under load, allocation-heavy extensions, DNS-heavy workloads) are always valuable. Follow the methodology above and share results in [issue #838](https://github.com/crazywhalecc/static-php-cli/issues/838) or a new issue.
- **Platform coverage**: ARM servers, older glibc baselines, and Windows/MSVC behavior are less explored than Linux x86_64.
- **PGO and LTO**: testing [PR #1138](https://github.com/crazywhalecc/static-php-cli/pull/1138) on your workloads and reporting build or runtime issues directly shapes the feature.

Because StaticPHP sits at the intersection of PHP, its dependency ecosystem, and multiple toolchains, findings here often surface upstream bugs — in php-src, in libraries, in Zig or LLVM. Reports and fixes that flow back upstream improve things for the wider PHP community, not just for static builds.
