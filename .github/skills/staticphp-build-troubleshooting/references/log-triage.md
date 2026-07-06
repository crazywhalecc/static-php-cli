# StaticPHP Log Triage

## Contents

- Log files
- Terminal summary
- Search strategy
- Failure categories
- Rebuild hygiene
- Reporting checklist

## Log Files

Default log directory is `log/`, controlled by `SPC_LOGS_DIR`. The important files are:

- `spc.output.log`: StaticPHP console/logger output and exception summaries.
- `spc.shell.log`: command execution log with command, caller, inline env, working directory, stdout, stderr, and exit code.
- `php-src.config.log`: copied when PHP source configure fails.
- `lib.<pkg>.console.log`: copied Autoconf `config.log` for a library when available.
- `lib.<pkg>.cmake-configure.log`: copied CMake 3.26+ configure log.
- `lib.<pkg>.cmake-error.log`: copied CMake error log.
- `lib.<pkg>.cmake-output.log`: copied CMake output log.

Logs are created when `SPC_ENABLE_LOG_FILE` is true. Old `*.log` files are cleaned unless the preserve-log env is true. Note the current code checks `SPC_PRESERVE_LOG`, while `config/env.ini` and docs mention `SPC_PRESERVE_LOGS`; verify this mismatch when diagnosing log retention behavior.

## Terminal Summary

StaticPHP exceptions often print:

- Exception category: build, download, environment, execution, file system, patch, validation, wrong usage, registry, internal.
- Failed module: package name, type, and sometimes class/file/line.
- Failed stage: call chain such as `build` or `build -> configure`.
- Failed command: the command that returned non-zero.
- Command working directory and inline env.
- Paths to `spc.output.log`, `spc.shell.log`, and extra logs.

Use this summary to choose the first files to read. If a package and stage are present, start with that package's YAML and class after reading the relevant log tail.

## Search Strategy

For long logs, search from the end first.

Useful patterns:

```text
Failed module:
Failed stage:
Failed command:
Command exited with non-zero code
configure: error
CMake Error
undefined reference
cannot find -l
fatal error:
No package
Package .* not found
Could NOT find
is not a valid
permission denied
curl: (
HTTP/2 403
rate limit
```

In `spc.shell.log`, each command block begins with `>>>>>>>>>>>>>>>>>>>>>>>>>>`, then prints the command, caller, env, and working dir. The last failed block is usually the highest-value evidence.

## Failure Categories

### Download Failure

Signs:

- `Download failed`
- `curl: (56)`, `curl: (22)`, HTTP 403/404/429
- GitHub API calls in verbose logs
- Asset regex did not match a release asset

Likely checks:

- If GitHub rate limited, ask user to set `GITHUB_TOKEN`.
- Verify artifact source type and regex in `config/pkg/*` or `config/artifact/*`.
- Use `--dl-retry`, `--dl-parallel`, `--ignore-cache`, `--no-alt`, or custom source options only as repro aids.
- Prefer fixing stale URLs/regexes over adding workarounds.

### Doctor or Environment Failure

Signs:

- `Environment check failed`
- missing `make`, `cmake`, `autoconf`, `pkg-config`, compiler, Perl, Visual Studio, MSYS2, Zig, etc.
- `Some check items can not be fixed`

Likely checks:

- Run `php bin/spc doctor -vvv`.
- Inspect `src/StaticPHP/Doctor/Item/*` for the check and fix behavior.
- Inspect `config/pkg/tool/*` for installable helper tools.
- On Windows, some dependencies cannot be auto-installed.

### Configure Failure

Signs:

- `configure: error`
- Autoconf failure before compilation
- extra `config.log` listed

Likely checks:

- Read copied `php-src.config.log` or `lib.<pkg>.console.log`.
- Look for the first compiler/linker probe that failed.
- Check `headers`, `static-libs`, `pkg-configs`, dependency order, and `initializeEnv($pkg)` behavior.
- For extensions, check `php-extension.arg-type` and `#[CustomPhpConfigureArg]`.

### CMake Failure

Signs:

- `CMake Error`
- `Could NOT find`
- missing target, package config, or static library

Likely checks:

- Read `lib.<pkg>.cmake-error.log` and `lib.<pkg>.cmake-configure.log`.
- Inspect `UnixCMakeExecutor`/`WindowsCMakeExecutor` usage in the package class.
- Check CMake options, toolchain root path, `pkg-configs`, and transitive dependency libraries.

### Compile Failure

Signs:

- compiler `fatal error: header.h: No such file or directory`
- syntax/type errors from upstream source
- incompatible PHP API or platform macros

Likely checks:

- Identify the source file and include path from `spc.shell.log`.
- Verify dependency headers are installed in `buildroot/include`.
- Check PHP version compatibility and existing patches in `src/globals/patch`.
- Prefer targeted patches or compile flags in the package class over broad toolchain changes.

### Link Failure

Signs:

- `undefined reference`
- `cannot find -lfoo`
- duplicate symbols
- Windows unresolved external symbol or missing `.lib`

Likely checks:

- Verify `static-libs`, dependency order, and `getStaticLibFiles()` usage.
- Inspect `SPC_EXTRA_LIBS`, `Libs.private` in `.pc` files, and package-specific pkg-config patching.
- On Windows, confirm `.lib` names and required system libraries.
- For OpenSSL/curl-like packages, search existing linker flag hooks before adding a new one.

### Smoke Test or Validation Failure

Signs:

- `Validation failed`
- `php --ri` failure
- extension not loaded after build
- micro/embed runtime smoke test fails

Likely checks:

- Check extension `display-name`; empty string skips `php --ri`.
- Confirm static vs shared build settings.
- Confirm the extension is included in the final SAPI target and not only built as a dependency.
- Inspect `src/globals/ext-tests/*` and common tests for expected runtime behavior.

### Registry or Config Failure

Signs:

- `Registry error`
- unknown package, invalid field, invalid platform suffix, type mismatch
- package referenced by attribute but missing config

Likely checks:

- Run `php bin/spc dev:lint-config`.
- Inspect `ConfigValidator` tests for accepted fields and error expectations.
- Confirm attribute type matches YAML `type`.
- Confirm hooks reference existing package names and stages for the current platform.

## Rebuild Hygiene

Use the smallest cleanup that can invalidate the suspected stale state:

- Redownload one artifact: `--ignore-cache="artifact-name"`.
- Skip downloads to test local source/build logic: `--no-download`.
- Prefer source when debugging build logic: `--dl-prefer-source`.
- Clear build products only when necessary. `spc reset --with-download --yes` removes caches and should not be suggested casually.

## Reporting Checklist

When preparing an issue or PR explanation, include:

- Exact `php bin/spc ...` command.
- OS, architecture, PHP version option, and relevant env vars.
- Failed package and stage.
- Last failed command and exit code.
- Tail of `spc.output.log`, relevant command block from `spc.shell.log`, and any extra config/CMake log.
- Whether the failure reproduces after a focused clean or cache refresh.
