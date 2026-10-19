# Doctor Module

Doctor diagnoses whether the current host, selected target, and compiler toolchain can run StaticPHP's build pipeline. It checks common host commands, platform-specific SDKs, managed tool packages, and target-aware requirements such as Zig or musl support.

This page documents the current v3 behavior. The built-in check/fix loader is also described for maintainers, but its PHP Attributes, callback signatures, and loader classes are not yet a stable third-party extension API.

## Running Doctor

Run Doctor explicitly after installing StaticPHP, changing the target/toolchain, or updating system build tools:

```bash
# Pre-built binary
./spc doctor

# Source installation
bin/spc doctor
```

The fix policy is selected with `--auto-fix` (`-y`):

| Invocation | Current behavior |
|---|---|
| `spc doctor` | Ask before each available automatic fix |
| `spc doctor --auto-fix` | Apply available fixes without prompting |
| `spc doctor --auto-fix=never` | Report the first unresolved failure and do not attempt a fix |

Doctor exits successfully only when every applicable check either passes, is skipped, or reports a fix that the current implementation considers successful. It stops at the first unresolved failure.

The `craft` command also runs Doctor by default. It uses the automatic-fix policy without prompting; set `craft-options.doctor: false` in `craft.yml` to disable that step.

## Execution Model

The current execution sequence is:

1. `DoctorLoader` collects built-in and Registry-provided classes and scans their public methods for Doctor Attributes.
2. Checks are sorted by descending `level`.
3. `#[OptionalCheck]`, `limit_os`, and `SPC_SKIP_DOCTOR_CHECK_ITEMS` remove checks that do not apply.
4. The check callback is called directly.
5. `null` means skipped; `CheckResult::ok()` means passed; `CheckResult::fail()` describes a failure and may name a fix.
6. Depending on the selected fix policy, Doctor rejects, prompts for, or immediately invokes the named fix through `ApplicationContext`.

`checkAll()` is fail-fast: it does not collect all failures into a final report. A successful fixer is currently accepted without re-running its original check.

::: warning
An invalid check return value is currently printed as “Skipped due to invalid return value” and treated as successful. This is an implementation limitation, not behavior extension authors should rely on.
:::

## Built-in Check Groups

The exact list is target-aware. The current core Registry provides these groups:

| Scope | Checks |
|---|---|
| Common | At least one Registry is loaded; the host OS is Linux, macOS, or Windows |
| Linux | Distribution build-tool baseline, CMake 3.22+, `re2c` 1.0.3+, and Linux headers on musl distributions |
| macOS | Homebrew or MacPorts, required build commands, GNU Bison 3+, and the selected Homebrew/MacPorts LLVM variant |
| Windows target | `vswhere`, Visual Studio C++ tools, Git `patch.exe`, managed MSYS2, `7za.exe`, and the initialized MSVC command environment |
| Unix target | StaticPHP-managed and functional `pkg-config` |
| Zig toolchain | Installed StaticPHP `zig` tool package |
| musl target/toolchain | Required musl wrapper and, for the legacy `MuslToolchain`, the cross toolchain under `/usr/local/musl` |

Package-specific tools declared through a package's `tools` field are resolved by `PackageInstaller`; they are not all duplicated as baseline Doctor checks. See [Compilation Tools](./system-build-tools) for the platform package lists and toolchain details.

## Automatic Fixes

Depending on the failed item and platform, a fix may:

- Invoke `apt`, `apk`, `dnf`, `yum`, `pacman`, Homebrew, or MacPorts.
- Use `sudo` for system-level installation.
- Download and install a StaticPHP Tool or Artifact package.
- Build a replacement tool such as `re2c`.
- Install musl runtime or cross-toolchain files outside the workspace.

Automatic fixes can therefore use the network and change the host system. The default interactive policy asks first; CI commonly uses either `--auto-fix` in a disposable environment or `--auto-fix=never` in a pre-provisioned image.

Fixers do not share a transaction or rollback mechanism. A fixer returning `true` is currently considered sufficient, even if the environment still fails the original condition. Re-run `spc doctor --auto-fix=never` after a repair when verification matters.

## Doctor Lock

After all checks are considered successful, Doctor writes `.spc-doctor.lock` containing the current StaticPHP version. `craft` writes the same lock after its Doctor step succeeds.

The preferred locations are:

| Platform | Preferred path | Fallbacks |
|---|---|---|
| Unix | `$XDG_CACHE_HOME/.spc-doctor.lock`, or `$HOME/.cache/.spc-doctor.lock` | System temporary directory, then the working directory |
| Windows | `%LOCALAPPDATA%\.spc-doctor.lock` | System temporary directory, then the working directory |

Build/download commands that call `checkDoctorCache()` only use this lock to decide whether to show a warning. The lock does not skip an explicit `spc doctor` run and does not block a build.

The current fingerprint is only the StaticPHP version. A target, toolchain, Registry set, PATH, or operating-system change does not invalidate it automatically. Delete the lock or run Doctor explicitly after such a change.

## Skipping Checks and Warnings

Two environment variables have different purposes:

```ini
# Skip named Doctor items during an actual Doctor run (exact item names, comma-separated)
SPC_SKIP_DOCTOR_CHECK_ITEMS="if cmake version >= 3.22,if re2c version >= 1.0.3"

# Suppress the pre-build warning when the Doctor lock is missing or stale
SPC_SKIP_DOCTOR_CHECK=1
```

`SPC_SKIP_DOCTOR_CHECK` does not remove checks from an explicit `spc doctor` invocation. `SPC_SKIP_DOCTOR_CHECK_ITEMS` does, and a skipped required check can allow an unusable environment to appear healthy, so it should be limited to controlled CI or debugging cases.

## Current Internal Registration Model

A Registry can currently point `doctor.psr-4` or `doctor.classes` at PHP classes. `DoctorLoader` constructs each class without constructor arguments and recognizes:

| Attribute | Current role |
|---|---|
| `#[CheckItem]` | Registers a check name, optional OS limit, and numeric level; higher levels run first |
| `#[OptionalCheck]` | Supplies a class- or method-level callable that decides whether the check is present |
| `#[FixItem]` | Registers a string fix name used by a failed `CheckResult` |

Check callbacks currently receive no DI context and return `null` or `CheckResult`. Fix callbacks are invoked through `ApplicationContext`, with `CheckResult`'s raw `fix_params` array used as callback context. The `manual` property on `CheckItem` is not consumed by the current executor.
