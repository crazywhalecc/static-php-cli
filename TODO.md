# v3 TODO List

Tracking items identified during the v2 → v3 migration audit.

---

## Commands

- [ ] Implement `craft` command (drives full build from `craft.yml`; should be easier with v3 vendor/registry mode)
- [ ] Migrate `micro:combine` command (combine `micro.sfx` with PHP code + INI injection)
- [ ] Implement `dump-extensions` command (extract required extensions from `composer.json` / `composer.lock`)
- [ ] Design and implement v3 dev toolchain commands (WIP — needs design decision):
  - [ ] `dev:extensions` / equivalent listing command
  - [ ] `dev:php-version`, `dev:ext-version`, `dev:lib-version`
  - [ ] Doc generation commands (`dev:gen-ext-docs`, `dev:gen-ext-dep-docs`, `dev:gen-lib-dep-docs`) — pending v3 doc design

---

## Source Patches (SourcePatcher → Artifact migration)

The following v2 `SourcePatcher` hooks are not yet migrated to v3 `src/Package/Artifact/` classes:

- [ ] Migrate `patchSQLSRVWin32` — removes `/sdl` compile flag to prevent Zend build failure on Windows
- [ ] Migrate `patchSQLSRVPhp85` — fixes `pdo_sqlsrv` directory layout for PHP 8.5
- [ ] Migrate `patchYamlWin32` — patches `config.w32` `_a.lib` detection logic for the `yaml` extension
- [ ] Migrate `patchImagickWith84` — applies PHP 8.4 compatibility patch for `imagick` based on version detection

---

## Extension Package Classes (Unix)

Extensions that had non-trivial v2 build logic and are missing a v3 `src/Package/Extension/` class:

- [x] `gettext` — macOS: fix `config.m4` bracket syntax for cross-version compatibility + append frameworks to linker flags (critical for macOS linking; this is a Unix-side gap, not Windows-only)

---

## Windows Extensions (Early Stage)

Windows extension support is still in early stage. The following extensions had Windows-specific configure args or patches in v2 and are pending v3 Windows implementation:

- [ ] `amqp` — Windows configure args
- [ ] `com_dotnet` — Windows-only extension
- [ ] `dom` — remove `dllmain.c` from `config.w32`
- [ ] `ev` — fix `PHP_EV_SHARED` in `config.w32`
- [ ] `gmssl` — add `CHECK_LIB("gmssl.lib")` to `config.w32`
- [ ] `intl` — fix `PHP_INTL_SHARED` in `config.w32`
- [ ] `lz4` — Windows configure args
- [ ] `mbregex` — Windows configure args
- [ ] `sqlsrv` / `pdo_sqlsrv` — complex conditional build logic (independent `sqlsrv` without `pdo_sqlsrv`)
- [ ] `xml` — remove `dllmain.c` from `config.w32`; handles `soap`, `xmlreader`, `xmlwriter`, `simplexml`

---

## Documentation

- [ ] Write v3 user documentation (currently zero v3 docs)
