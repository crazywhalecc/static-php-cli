# Shared dependency libraries

SPC builds static dependency libraries by default. Linux builds can opt into
shared dependencies without changing existing static build configurations:

```yaml
extensions: zlib
sapi: cli
build-options:
  with-shared-libs: true
  with-shared-libdir: /usr/lib/php-zts/lib
```

`with-shared-libs` defaults to false. `with-shared-libdir` controls the installed
ELF RUNPATH; it is independent of the buildroot. The runtime libraries must be
installed there before running the resulting PHP without `LD_LIBRARY_PATH`.
Native glibc and musl GCC builds are supported. Static prebuilt archives are not
used as substitutes for shared source builds.

Run `bin/spc craft --libs-only` to produce the library SDK first. Then run
`bin/spc craft --use-existing-libraries` to build PHP against that SDK. Reuse
checks source hashes, installed outputs, ELF metadata and pending dependent
rebuilds. An invalid SDK fails instead of silently recompiling dependencies.

The SDK records runtime files, licenses, source versions, SONAMEs, NEEDED entries
and RUNPATHs in `buildroot/shared-libraries.json`. Development files and their
checksums are recorded in `buildroot/.shared-library-ownership.json`. These files
must travel together with the SDK. Compiler runtime libraries are exported as a
separate `gcc-runtime` entry when needed.

For a selective library update, use a library-only craft configuration with
`extensions: []`, `shared-extensions: []`, `sapi: []` and the selected `libs`.
Existing verified dependencies are reused. Rebuilding a provider retires its
previous owned files. A removed SONAME leaves its consumers in `pending_rebuilds`
until those consumers are rebuilt. Interrupted or failed builds invalidate their
entry before another process can reuse it.

Library recipes may declare `shared-libs` when their DSO names differ from their
archive names. A `static-only: true` library retains explicit archive linkage in
an otherwise shared build. Static builds continue using `static-libs` regardless
of whether shared objects are also present.

Regression checks:

```sh
vendor/bin/phpunit tests/StaticPHP/Util/SharedLibraryUtilTest.php
python3 tests/native-shared-library-transition.py
python3 tests/native-zlib-linkage.py --archive /path/to/zlib-1.3.1.tar.gz \
    --baseline-root /path/to/original-static-php-cli
```

The native tests require a C/C++ compiler, `readelf`, `patchelf`, and installed
Composer dependencies. The zlib test builds both modes and runs consumers with
`LD_LIBRARY_PATH` unset; supplying a baseline also checks byte-identical static
archives. These tests do not replace full producer and package validation on EL8
and Alpine.
