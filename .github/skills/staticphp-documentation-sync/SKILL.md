---
name: staticphp-documentation-sync
description: Synchronize bilingual documentation when StaticPHP v3 user-facing or developer-facing documentation must change. Use when adding, modifying, deprecating, or removing documented features, CLI commands/options, environment variables, configuration formats, public APIs/extension points, workflows, installation requirements, migration guidance, or user-visible behavior. Do not use solely for internal implementation changes, package/library additions, or build bug fixes unless they change documented behavior or require updating existing docs.
---

# StaticPHP Documentation Sync

## Overview

StaticPHP maintains bilingual documentation under `docs/en/` (English) and `docs/zh/` (Chinese). User-facing or developer-facing documentation changes must be reflected in both languages. Use this skill to identify affected docs, update them in sync, and validate sidebar/config consistency.

## When to Use This Skill

Invoke this skill when the change involves:

- New or modified CLI commands, options, or arguments
- New or changed environment variables
- Package (extension/library/target/tool) additions, removals, or behavioral changes that affect documented support, dependencies, commands, or workflows
- Configuration format changes (YAML fields, artifact definitions, registry)
- Build lifecycle, doctor, or toolchain changes
- API or extension point changes (attributes, hooks, DI, custom artifacts)
- Workflow changes (installation, first build, migration)
- Deprecation or removal of any documented feature

Do not update docs just because code changed. Internal refactors, new libraries used only as dependencies, package build fixes, CI-only changes, and bug fixes that preserve documented behavior usually do not need documentation changes. If existing docs mention the changed behavior, command, package support, dependency, caveat, or workaround, update them.

## Documentation Map

```
docs/
├── en/                          # English docs (canonical source)
│   ├── index.md                 # Home page hero/features
│   ├── guide/                   # User-facing guides
│   │   ├── index.md             # Overview
│   │   ├── installation.md      # Installation instructions
│   │   ├── first-build.md       # First build walkthrough
│   │   ├── sapi-reference.md    # PHP SAPI options
│   │   ├── cli-reference.md     # CLI commands and options
│   │   ├── cli-generator.md     # Build command generator
│   │   ├── migrate-from-v2.md   # v2 → v3 migration
│   │   ├── extensions.md        # Supported extensions list
│   │   ├── extension-notes.md   # Per-extension notes
│   │   ├── env-vars.md          # Environment variables
│   │   ├── deps-map.md          # Dependency map
│   │   └── troubleshooting.md   # Troubleshooting guide
│   ├── develop/                 # Developer docs
│   │   ├── index.md             # Dev overview
│   │   ├── structure.md         # Project structure
│   │   ├── registry.md          # Registry model
│   │   ├── package-model.md     # Package YAML model
│   │   ├── artifact-model.md    # Artifact YAML model
│   │   ├── craft-yml.md         # craft.yml reference
│   │   ├── build-lifecycle.md   # Build stages/lifecycle
│   │   ├── system-build-tools.md # System tool requirements
│   │   ├── doctor-module.md     # Doctor module
│   │   ├── php-src-changes.md   # PHP source patches
│   │   └── extending/           # Extension authoring
│   │       ├── index.md
│   │       ├── package-classes.md
│   │       ├── annotations.md
│   │       ├── lifecycle-hooks.md
│   │       ├── dependency-injection.md
│   │       └── custom-artifact.md
│   ├── contributing/
│   │   └── index.md
│   └── faq/
│       └── index.md
├── zh/                          # Chinese docs (mirrors en/ structure)
│   └── (same structure as en/)
├── .vitepress/
│   ├── sidebar.en.ts            # English sidebar config
│   ├── sidebar.zh.ts            # Chinese sidebar config
│   └── config.ts                # VitePress site config
└── deps-craft-yml.md            # Shared craft.yml include used by both languages
```

Other documentation files outside this tree may also matter, especially root-level `ext-support.md`.

**Key rule**: `docs/en/` and `docs/zh/` have identical file trees. Every `.md` file under `en/` must have a corresponding file under `zh/`.

## Workflow

### Step 1: Identify Impact Scope

Map the change to affected documentation:

| Change Type | Likely Affected Docs |
|---|---|
| New/removed CLI command | `guide/cli-reference.md`, `guide/cli-generator.md` |
| New/removed CLI option | `guide/cli-reference.md`, `guide/first-build.md` |
| New env var | `guide/env-vars.md` |
| New/removed documented extension package | `guide/extensions.md`, `guide/extension-notes.md`, `guide/deps-map.md`, root `ext-support.md` |
| Package config field change | `develop/package-model.md`, `develop/artifact-model.md` |
| Build lifecycle change | `develop/build-lifecycle.md` |
| Doctor change | `develop/doctor-module.md`, `develop/system-build-tools.md` |
| New attribute/hook/DI | `develop/extending/annotations.md`, `develop/extending/lifecycle-hooks.md`, `develop/extending/dependency-injection.md` |
| PHP source patch | `develop/php-src-changes.md` |
| Registry change | `develop/registry.md` |
| Install/setup change | `guide/installation.md`, `guide/first-build.md` |
| Deprecation/removal | All docs referencing the feature |
| New doc page | `sidebar.en.ts`, `sidebar.zh.ts` |

### Step 2: Read Current Docs

Before editing, read BOTH the English and Chinese versions of each affected file to understand the current content and existing translation patterns.

```bash
# Example: check both language versions of the CLI reference
cat docs/en/guide/cli-reference.md
cat docs/zh/guide/cli-reference.md
```

### Step 3: Update English Docs First

1. Update `docs/en/` files with accurate, complete English content.
2. Follow existing formatting conventions (headings, code blocks, tables, admonitions).
3. If adding a new page:
   - Create the file under `docs/en/<category>/<name>.md`
   - Add the corresponding entry in `docs/.vitepress/sidebar.en.ts`
   - Ensure the file has proper VitePress frontmatter if needed

### Step 4: Sync Chinese Docs

1. Update `docs/zh/` files to match the English changes.
2. Translate all changed or added prose. Do not leave placeholder text or untranslated English in Chinese docs.
3. Match the structure and formatting of the English version exactly.
4. If a new page was added in English, create the corresponding `docs/zh/` file and update `docs/.vitepress/sidebar.zh.ts`.
5. Treat `docs/deps-craft-yml.md` as a special shared include for both languages. If it needs prose changes, either keep the shared content acceptable for both languages or split the included content before adding language-specific prose.

### Step 5: Validate

Run these checks before considering the task complete:

```bash
# Check that en/ and zh/ have matching file trees
diff <(find docs/en -name '*.md' | sed 's|docs/en/||' | sort) \
     <(find docs/zh -name '*.md' | sed 's|docs/zh/||' | sort)

# Check that all sidebar links point to existing files
# (manual check: every 'link' in sidebar.*.ts must resolve to an actual .md file)

# Build docs when Node dependencies are available; this catches many broken links/config issues
npm run docs:build
```

## Translation Guidelines

- **Chinese docs must be complete**: Every section, paragraph, and table in the English docs must have a Chinese equivalent. Never skip sections.
- **Code blocks and command examples**: Keep executable code and commands identical in both languages. Translate surrounding prose; translate comments inside examples only when they are explanatory docs rather than copy-pasteable source/config.
- **Technical terms**: Use consistent translations. Refer to existing Chinese docs for established term translations. When in doubt, prefer the term used elsewhere in `docs/zh/`.
- **Links**: Internal links in Chinese docs should point to `/zh/...` paths (not `/en/...`).
- **YAML frontmatter**: Keep frontmatter identical between languages (layout, etc.) unless the title/tagline field is translated.

## Common Pitfalls

- **Don't update only one language**: This is the most common mistake. Always update both `en/` and `zh/` in the same change.
- **Don't forget sidebar configs**: When adding, renaming, or removing doc pages, update both `sidebar.en.ts` and `sidebar.zh.ts`.
- **Don't forget shared/root docs**: root `ext-support.md` and `docs/deps-craft-yml.md` are also documentation. Update them if relevant.
- **Don't leave TODO markers**: Do not commit TODO translation placeholders unless the user explicitly allows it or the task is blocked; if that happens, call it out in the final response.
- **Don't translate file paths or URLs**: Only translate human-readable content, not paths, URLs, command names, or code identifiers.
