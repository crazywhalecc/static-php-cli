# Contributing

StaticPHP welcomes fixes, package definitions, platform support, tests, and documentation. v3 is still defining parts of its extension API, so distinguish between a contribution to the built-in `core` Registry and an external Registry that needs a long-term compatibility contract.

## Development Setup

A source checkout requires PHP 8.4 or later and Composer. The CLI also needs the PHP extensions listed in the [source installation guide](/en/guide/installation). Install the development dependencies from the repository root:

```bash
composer install
bin/spc --version
```

Node.js and npm are only required when changing the VitePress documentation:

```bash
npm install
npm run docs:build
```

Run `bin/spc doctor` before attempting a real build. Full static builds are platform-sensitive and can be slow, so they are not a prerequisite for every documentation, test, or configuration-only change.

## Find the Correct Layer

Keep a change in the narrowest layer that can express it:

| Area | Purpose |
|---|---|
| `config/pkg/ext/` | PHP extension Package definitions |
| `config/pkg/lib/` | Link-time dependency library definitions |
| `config/pkg/target/` | Final and virtual build targets |
| `config/pkg/tool/` | Host-side build tool Packages |
| `config/artifact/` | Shared or complex source/binary Artifact definitions |
| `src/Package/` | Package-specific recipes, patches, hooks, and custom Artifact behavior |
| `src/StaticPHP/` | Framework, resolver, runtime, Doctor, Registry, and command implementation |
| `tests/` | PHPUnit tests and fixtures |
| `docs/en/`, `docs/zh/` | Canonical English documentation and its synchronized Chinese version |

Prefer declarative YAML fields over PHP conditions. Add a recipe class only when the package needs build commands, source rewrites, validation, custom configure arguments, or lifecycle hooks.

Do not fix a problem by editing generated `buildroot/`, `source/`, `downloads/`, or `pkgroot/` content. Changes there are discarded and do not describe how another user can reproduce the build.

## Adding or Updating a Package

Before writing a new definition, search for a package with the same build system and artifact type. Follow its structure, then verify these points:

1. Choose the correct type: `php-extension`, `library`, `target`, `virtual-target`, or `tool`.
2. Use the exact package name. PHP extension dependencies and configs use the `ext-` prefix.
3. Put hard dependencies in `depends`, optional relationships in `suggests`, and host build tools in `tools`.
4. Express platform differences with `@windows`, `@unix`, `@linux`, or `@macos` fields when the schema supports them.
5. Prefer an inline Artifact for a simple one-package source; use `config/artifact/` when multiple Packages share it or custom behavior makes a standalone definition clearer.
6. Record license identifiers and files in Artifact metadata. Add structured Package `license` entries only for material that must be copied after a source build.
7. Add or update the relevant PHP recipe only when configuration alone is insufficient.

See [Package Model](/en/develop/package-model) and [Artifact Model](/en/develop/artifact-model) for the current schemas.

Configuration linting is strict about unknown fields but cannot prove that a URL exists, a patch still applies, or a library links on every platform. When practical, test the oldest and newest affected PHP branches and the platforms touched by the change.

## PHP Code and Tests

StaticPHP follows the existing code style and favors existing package/build patterns over new abstractions. For framework code:

- Keep internal orchestration details internal; PHP `public` visibility alone does not make a symbol part of the supported extension API.
- Add a focused PHPUnit test for resolver, config, Registry, command, or utility behavior.
- Include the failing input or regression case in the test instead of relying only on a manual build.
- Avoid unrelated formatting or mechanical changes in the same pull request.

Tests live under `tests/StaticPHP/` and use the `Tests\StaticPHP\` namespace. Run the smallest relevant test during development, then the project checks before submitting.

## Doctor Changes

Built-in Doctor checks currently live under `src/StaticPHP/Doctor/Item/`. A check should observe the environment without changing it; installation, downloads, and file changes belong in a separate fix callback.

The current Attributes and callback signatures are internal and have known limitations, including raw fix parameters and no automatic re-check after a fix. Follow an existing core check when maintaining the built-in set, but do not present that pattern as a stable third-party API. Update [Doctor Module](/en/develop/doctor-module) and [Compilation Tools](/en/develop/system-build-tools) when user-visible checks, fixes, prerequisites, or lock behavior change.

## Documentation

English under `docs/en/` is canonical. Every English Markdown file must have a corresponding file under `docs/zh/`, and both versions must have the same headings, examples, tables, admonitions, and links to the appropriate language path.

When behavior changes, update all pages that describe it—not only the closest reference page. Common examples include CLI options, environment variables, Package/Artifact fields, Doctor checks, and migration guidance.

Validate matching file trees and build the site:

```bash
diff <(find docs/en -name '*.md' | sed 's|docs/en/||' | sort) \
     <(find docs/zh -name '*.md' | sed 's|docs/zh/||' | sort)
npm run docs:build
```

New pages must also be added to both VitePress sidebars.

## Validation Checklist

Use checks proportional to the change:

| Change | Required checks |
|---|---|
| Package, Artifact, target, extension, library, or tool config | `bin/spc dev:lint-config`, `composer cs-fix` |
| PHP framework or recipe code | `composer cs-fix`, `composer test`, `composer analyse` |
| Documentation | English/Chinese tree and structure checks, `npm run docs:build` |
| Platform build fix | The checks above plus the narrowest affected build or CI matrix when available |

Useful commands:

```bash
bin/spc dev:lint-config
composer cs-fix
composer test
composer analyse
```

If a full build cannot be run locally, state which platform/PHP combinations remain untested and include the relevant logs or CI result. Build and shell logs live under `log/` by default, but generated logs should not normally be committed.

## Pull Requests

Keep each pull request focused and explain:

- The problem and why the selected layer is appropriate.
- The affected Package/Artifact, target OS, architecture, toolchain, and PHP versions.
- User-visible behavior or compatibility impact.
- Tests and builds that were run, including anything not tested.
- Documentation updated for both languages.

Complete the repository pull-request checklist. Do not commit generated build directories, downloaded archives, caches, local environment files, or unrelated editor/OS metadata.

For a new dependency or bundled patch, include its upstream source, license, and the condition that requires it. Prefer an upstream fix and keep downstream patches scoped to the affected versions.

## Security Reports

Do not publish exploit details, credentials, signing material, or a reproducible vulnerability in a normal issue. Use GitHub's private vulnerability reporting for the repository when available, or contact the maintainers through an existing project channel to arrange a private disclosure.

The repository does not currently contain a dedicated `SECURITY.md`; adding a formal supported-version and disclosure policy remains a project maintenance task.
