# StaticPHP v3 Copilot Instructions

> The canonical agent instructions live in [AGENTS.md](./AGENTS.md). This file provides the essential summary for GitHub Copilot; read `AGENTS.md` for the complete project map, working rules, and validation steps.

StaticPHP is a PHP CLI application for building static PHP binaries, PHP SAPIs, extensions, libraries, and helper tools. Prefer the v3 architecture under `src/StaticPHP`, `src/Package`, `config/pkg`, and `config/artifact`.

## Skills

Use the repository skills when the task matches them:

- `.github/skills/staticphp-package-maintenance`: add, modify, review, or validate package, artifact, extension, library, target, or tool definitions.
- `.github/skills/staticphp-build-troubleshooting`: diagnose build, download, doctor, shell, terminal, CI, smoke-test, or log failures.

## Quick Reference

- **Entrypoint**: `php bin/spc`
- **Config lint**: `php bin/spc dev:lint-config`
- **CS fix**: `composer cs-fix`
- **Tests**: `composer test`
- **Static analysis**: `composer analyse`
- **Extension packages**: `config/pkg/ext/ext-*.yml`, prefix with `ext-` in dependencies
- **Platform suffixes**: `@unix`, `@linux`, `@macos`, `@windows` — prefer these over runtime conditionals
- **Do NOT edit generated dirs**: `buildroot/`, `source/`, `downloads/`, `pkgroot/`
