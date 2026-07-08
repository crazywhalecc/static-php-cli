---
name: staticphp-build-troubleshooting
description: Diagnose StaticPHP v3 failures. Use when investigating build, compile, linker, download, doctor, environment, CI, smoke-test, terminal output, spc.output.log, spc.shell.log, config.log, CMake logs, or user-provided error snippets from StaticPHP commands.
---

# StaticPHP Build Troubleshooting

## Overview

Use this skill to triage StaticPHP failures from the outside in: command and environment first, SPC module/stage metadata next, shell/config logs last. The goal is to isolate the failing package, stage, command, and root cause before editing code.

## First Pass

1. Capture the exact command, OS, architecture, PHP version, extensions/libs/targets, and whether the run used `-v`, `-vv`, or `-vvv`.
2. Read the final terminal error first. StaticPHP prints module error info, failed package, failed stage, failed command, log paths, and extra log files when available.
3. If log files exist, inspect them in this order:
   - `log/spc.output.log`: user-facing SPC messages and exception summary.
   - `log/spc.shell.log`: executed commands, working directories, env, stdout/stderr.
   - Extra logs named in the exception output: `php-src.config.log`, `lib.<pkg>.console.log`, `lib.<pkg>.cmake-error.log`, `lib.<pkg>.cmake-configure.log`, `lib.<pkg>.cmake-output.log`.
4. Read `references/log-triage.md` for pattern matching, likely causes, and next checks.

## Diagnosis Rules

- Do not start by changing shared core code. Most failures are package metadata, environment, upstream source, dependency order, or platform flags.
- Prefer evidence from the last failing command over earlier warnings.
- When logs are long, search backward for `Command exited`, `error:`, `undefined reference`, `not found`, `No package`, `CMake Error`, `configure: error`, `fatal error`, `Failed module`, and `Failed stage`.
- Use `config.log` and CMake logs for configure detection failures; use `spc.shell.log` for the actual command and env.
- Be careful with rebuild suggestions. `spc reset --with-download --yes` is destructive to caches; ask before clearing caches unless the user explicitly asked.

## Repro Commands

Use focused commands when reproducing:

```bash
php bin/spc doctor -vvv
php bin/spc download --for-extensions="curl,openssl" --with-php=8.5 --parallel=4 --retry=3 -vvv
php bin/spc build:libs "openssl" -vvv
php bin/spc build:php "bcmath,openssl,curl" --build-cli -vvv
php bin/spc dev:lint-config
```

Choose the smallest command that still reaches the failing package.

## Fix Direction

After finding the failing package/stage:

- Package YAML issue: use `$staticphp-package-maintenance`, then edit `config/pkg/*` or `config/artifact/*`.
- Build command or patch issue: inspect the package class under `src/Package/*`.
- Environment issue: check `doctor`, toolchain classes, and `config/pkg/tool/*`.
- Upstream download issue: check artifact type, regex, GitHub rate limiting, mirror behavior, and `GITHUB_TOKEN`.
- Core exception/logging issue: inspect `src/StaticPHP/Exception`, `src/StaticPHP/Runtime/Shell`, or executor classes only after package-level causes are ruled out.

## Resources

- `references/log-triage.md`: log files, failure categories, search patterns, and likely fixes.
