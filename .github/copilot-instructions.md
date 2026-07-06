# StaticPHP v3 AI Instructions

StaticPHP is a PHP CLI application for building static PHP binaries, PHP SAPIs, extensions, libraries, and helper tools. Prefer the v3 architecture under `src/StaticPHP`, `src/Package`, `config/pkg`, and `config/artifact`.

Use the repository skills when the task matches them:

- `.github/skills/staticphp-package-maintenance`: adding, changing, reviewing, or validating package, artifact, extension, library, target, or tool definitions.
- `.github/skills/staticphp-build-troubleshooting`: diagnosing build, download, doctor, shell, terminal, CI, or log failures.

## Project Map

- `bin/spc`: primary CLI entrypoint.
- `composer.json`: PHP 8.4+, Symfony Console, DI, YAML, logger; useful scripts are `composer test`, `composer analyse`, `composer lint-config`, and `composer cs-fix`.
- `spc.registry.yml`: built-in `core` registry.
- `config/pkg/ext`: PHP extension package YAML, usually named `ext-*.yml`.
- `config/pkg/lib`: library package YAML.
- `config/pkg/target`: final build target and virtual target YAML.
- `config/pkg/tool`: helper tool package YAML.
- `config/artifact`: standalone artifact YAML for shared or complex sources/binaries.
- `src/StaticPHP`: framework core: registry loading, config validation, dependency resolution, package install/build pipeline, doctor, toolchains, runtime shell/executors, exceptions.
- `src/Package`: package-specific build logic registered by PHP attributes.
- `src/globals`: constants, helper functions, patches, smoke tests, bundled license text.
- `tests`: PHPUnit tests for config, registry, artifacts, dependency resolver, DI, commands, and utilities.
- `docs/en/develop` and `docs/zh/develop`: developer documentation. Some pages may be TODO; verify against source when behavior matters.

## Working Rules

- Keep changes scoped to the requested package, artifact, command, docs, or tests.
- Prefer existing package patterns over new abstractions. Search for a similar package first.
- Use structured YAML config; do not encode dependency logic in PHP when config fields are enough.
- Match package names exactly: extension packages use `ext-` in config and dependencies; `#[Extension('curl')]` expands to `ext-curl`.
- Use platform suffixes such as `@unix`, `@linux`, `@macos`, and `@windows` instead of runtime conditionals when the difference is declarative.
- Add or update PHP package classes only when build commands, validation, custom configure args, hooks, or source patching are required.
- Do not edit generated build directories (`buildroot/`, `source/`, `downloads/`, `pkgroot/`) as a fix.

## Validation

For config or package changes, run CS checks first:

```bash
php bin/spc dev:lint-config
composer cs-fix
```

For broader PHP code changes, also run:

```bash
composer test
composer analyse
```

Use build commands only when needed for the task or when the user provided a repro, because full static builds can be slow and platform-sensitive.
