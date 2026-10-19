---
name: staticphp-package-maintenance
description: Maintain StaticPHP v3 packages and artifacts. Use when adding, modifying, reviewing, or validating config under config/pkg or config/artifact; package classes under src/Package; extension/library/target/tool dependencies; PHP configure args; build hooks; package metadata; or related tests and docs.
---

# StaticPHP Package Maintenance

## Overview

Use this skill to make focused package changes without re-reading the entire repository. StaticPHP v3 separates declarative package/artifact YAML from package-specific PHP build logic; prefer config-only edits unless current patterns require a class.

## Quick Workflow

1. Identify the package kind and exact name.
   - Extension: `config/pkg/ext/ext-name.yml`, class `src/Package/Extension/name.php`.
   - Library: `config/pkg/lib/name.yml`, class `src/Package/Library/name.php`.
   - Target or virtual target: `config/pkg/target/name.yml`, class `src/Package/Target/name.php`.
   - Tool: `config/pkg/tool/name.yml`, class `src/Package/Tool/name.php`.
   - Shared/custom artifact: `config/artifact/name.yml`, class `src/Package/Artifact/name.php`.

2. Search for the closest existing package before designing anything new.
   - Similar build system: `rg "#\\[BuildFor|UnixCMakeExecutor|UnixAutoconfExecutor|WindowsCMakeExecutor" src/Package`.
   - Similar config fields: `rg "static-libs|pkg-configs|arg-type|depends@" config/pkg`.
   - Similar download type: `rg "type: ghrel|type: pecl|type: pie|type: git|binary: hosted" config`.

3. Read `references/package-reference.md` when changing YAML fields, artifact definitions, package naming, dependencies, platform suffixes, or validation expectations.

4. Read `references/build-class-patterns.md` when PHP build logic, attributes, lifecycle hooks, custom configure args, source patching, or executor usage is needed.

5. Validate narrowly, then broadly if risk warrants it.
   - Config lint: `php bin/spc dev:lint-config`
   - Focused tests: `vendor/bin/phpunit tests/StaticPHP/Config tests/StaticPHP/Registry tests/StaticPHP/Util/DependencyResolverTest.php --no-coverage`
   - Full project checks: `composer test`, `composer analyse`

## Editing Rules

- Treat `config/pkg/*` and `config/artifact/*` as the source of package truth; package classes augment behavior.
- Use platform suffix fields for declarative OS differences: `@unix`, `@linux`, `@macos`, `@windows`.
- Add a PHP class only for custom build stages, validation, hook behavior, patches, or custom configure arguments.
- Keep extension dependencies prefixed with `ext-`; libraries, tools, and targets use their package names.
- Prefer existing helpers such as `UnixAutoconfExecutor`, `UnixCMakeExecutor`, `WindowsCMakeExecutor`, `shell()`, `cmd()`, and `FileSystem`.
- Do not modify build outputs (`buildroot/`, `source/`, `downloads/`, `pkgroot/`) to fix package definitions.
- If upstream metadata changes, update license metadata and smoke-test/display names when relevant.

## Common Task Paths

- Add a new PECL extension: define `config/pkg/ext/ext-name.yml` with `type: php-extension`, `artifact.source.type: pecl`, dependencies, and `php-extension.arg-type`; add a class only if configure args, patches, or hooks are non-standard.
- Add a library: define `config/pkg/lib/name.yml` with artifact, dependencies, and install verification fields (`headers`, `static-libs`, `pkg-configs`, `static-bins`); add build class methods by OS.
- Fix an existing package build: start from the failing package config/class, then inspect dependencies and hooks targeting that package before changing shared core code.
- Update a version source: prefer artifact fields that support update checking (`ghrel`, `ghtar`, `ghtagtar`, `git` with regex, `filelist`, `pecl`, `pie`) over hard-coded URLs when upstream supports it.

## Resources

- `references/package-reference.md`: YAML package/artifact model, naming, fields, validation, and commands.
- `references/build-class-patterns.md`: PHP package attributes, stages, hooks, executors, and class patterns.
