---
outline: 'deep'
---

# Artifact Model

An **Artifact** is a core concept in the StaticPHP build system. It represents a source archive or pre-built binary required to build a package. Each artifact describes where to download the file, how to extract it, and the resulting path layout. Packages reference one or more artifacts via the `artifact` field to obtain the source code or binaries they need.

## Defining an Artifact

Here is a minimal artifact object that points to a source archive (curl's source code):

```yaml
&:
  source:
    type: ghrel
    repo: curl/curl
    match: curl.+\.tar\.xz
    prefer-stable: true
```

There are two ways to define an artifact and associate it with a package: **inline** (defined directly inside the package file) or **standalone** (defined in a separate file and referenced by name):

::: code-group
```yaml [Inline Artifact]
# This is a package declaration
curl:
  type: target
  artifact:
    source:
      type: ghrel
      repo: curl/curl
      match: curl.+\.tar\.xz
      prefer-stable: true
```
```yaml [Standalone Artifact]
# This is a standalone artifact declaration, typically placed under config/artifact/
curl-src:
  source:
    type: ghrel
    repo: curl/curl
    match: curl.+\.tar\.xz
    prefer-stable: true
```
```yaml [Package Referencing a Standalone Artifact]
# This is a package declaration
curl:
  type: target
  artifact: curl-src
```
:::

## Structure

An artifact has three top-level sections: `source`, `binary`, and `metadata`.

- `source` — the source code archive
- `binary` — pre-built binaries for specific platforms
- `metadata` — additional information such as license paths

Both `source` and `binary` accept either an inline source object or a reference to a standalone artifact by name (as shown above).

Full artifact object format:

```yaml
&:
  source: {source-object} # (optional)
  binary:
    windows-x86_64: {source-object} # (optional)
    linux-x86_64: {source-object} # (optional)
    linux-aarch64: {source-object} # (optional)
    macos-x86_64: {source-object} # (optional)
    macos-aarch64: {source-object} # (optional)
  metadata: # (optional)
    license: "" # (optional) SPDX identifier
    license-files: ["LICENSE"] # License files from the source directory
    source-root: "subdir" # (optional) Use if the actual source root is inside a subdirectory
```

The basic format of a `source-object`:

```yaml
&:
  type: "url" # Download type
  # ...: Additional keys depend on the type; see below
  extract: "path/to/dir" # (optional) Override extract path; default: SOURCE_PATH/{artifact-name}
```

## Metadata

The `metadata` field provides supplementary information about an artifact. It supports three subfields:

### license

- **Type**: `string` (optional)
- **Description**: The open-source license identifier for this package, following the [SPDX License Identifier](https://spdx.org/licenses/) format (e.g. `MIT`, `Apache-2.0`, `GPL-2.0-only`). This is used only for annotation in the license summary of the build output and has no effect on the build process itself.

```yaml
metadata:
  license: MIT
```

### license-files

- **Type**: `string[]` (optional)
- **Description**: A list of paths to license files. After a successful build, the framework collects these files and places them in the `license/` directory of the build output. Two path formats are supported:
  - **Relative paths** (e.g. `LICENSE`, `COPYING`, `gettext-runtime/intl/COPYING.LIB`): resolved relative to the artifact's source root directory.
  - **`@/` prefix** (e.g. `@/bzip2.txt`): references a license file bundled with the framework itself, resolved to `src/globals/licenses/`. This is useful when the upstream source package does not include a license file (or the license text is embedded in other documentation) — in such cases, the license text can be placed in the built-in directory and referenced with `@/`.

The following built-in license files are currently available: `bzip2.txt`, `gmp.txt`, `icu.txt`, `postgresql.txt`, `sqlite.txt`, `zlib.txt`.

```yaml
# Common case: read from the source directory
metadata:
  license-files: [LICENSE]

# Multiple license files
metadata:
  license-files: [LICENSE, COPYING.LESSER]

# License file inside a subdirectory
metadata:
  license-files: [gettext-runtime/intl/COPYING.LIB]

# Use a built-in license file when the source package does not include one
metadata:
  license-files: ['@/bzip2.txt']
```

### source-root

- **Type**: `string` (optional)
- **Description**: When the actual source root is located inside a subdirectory of the extracted archive, this field specifies that subdirectory name. The framework will use this path as the working directory during the build instead of the top-level extraction directory.

```yaml
# krb5's actual source root is in the src/ subdirectory after extraction
metadata:
  source-root: src
```

## Download Types

Artifacts support a variety of download types. Choose the one that best fits where the package is hosted.

| Type | Description |
|---|---|
| `url` | Download from a fixed URL. Supports `filename` (custom local filename) and `version` (manually set version). |
| `git` | Clone from a Git repository. Supports `rev` (branch/tag/commit), `submodules` (fetch submodules), and `extract`. |
| `ghrel` | Download from GitHub Release Assets by regex match. Requires `repo` (`owner/repo`) and `match` (filename regex). Supports `prefer-stable`. |
| `ghtar` | Download the source tarball from a GitHub Release (`/releases` API), matching by release name with `match`. Supports `prefer-stable`. |
| `ghtagtar` | Download the source tarball from a GitHub Tag (`/tags` API), matching by tag name with `match`. Supports `prefer-stable`. |
| `filelist` | Scrape an HTML page for a file listing, extract the filename and version via `regex`, then download the matched file. Suitable for official download index pages (e.g. ftp.gnu.org, openssl.org). |
| `pecl` | Download a PHP extension from [PECL](https://pecl.php.net) by `name`. Supports `prefer-stable`. |
| `pie` | Download a PHP extension from Packagist via the [PIE](https://github.com/php/pie) spec. Requires `repo` (`vendor/package`). |
| `php-release` | Download official PHP source from php.net. The version is controlled by the `--with-php` build argument. |
| `bitbuckettag` | Download source tarball from the latest Bitbucket tag. Requires `repo` (`workspace/repo`). |
| `local` | Use a pre-existing local directory as the source. Requires `dirname`. Useful for offline or development scenarios. |
| `custom` | Fully custom download logic implemented in a PHP class under `src/Package/Artifact/`. Optionally calls a specific method via `func`. |

## Type Reference

### url

Downloads a file from a fixed URL and extracts it automatically.

- **Class**: `StaticPHP\Artifact\Downloader\Type\Url`
- **Capabilities**: Basic download only; no automatic version update checking
- **Required**: `url` — the download address
- **Optional**:
  - `filename` — local filename to save as (defaults to the last path segment of the URL)
  - `version` — manually specify a version string (this type cannot auto-detect versions)
  - `extract` — override the extraction directory (default: `SOURCE_PATH/{artifact-name}`)

```yaml
# sqlite downloaded from a fixed URL
artifact:
  source:
    type: url
    url: 'https://www.sqlite.org/2024/sqlite-autoconf-3450200.tar.gz'
```

::: tip

Inside an artifact, a bare string starting with `http://` or `https://` is automatically expanded into a `type: url` object, so you can often just write the URL directly:

```yaml
artifact:
  source: 'https://www.sqlite.org/2024/sqlite-autoconf-3450200.tar.gz'
```
:::

---

### git

Clones a Git repository as the source. Supports two modes: clone a specific branch/tag/commit (`rev`), or use a regex to match the highest-versioned branch from all remote refs (`regex`).

- **Class**: `StaticPHP\Artifact\Downloader\Type\Git`
- **Capabilities**: Version update checking (`CheckUpdateInterface`)
- **Required**: `url` — repository URL
- **Optional** (at least one of `rev` or `regex` is required):
  - `rev` — clone a specific branch, tag, or commit hash
  - `regex` — match remote branch names with a PCRE regex; the highest matching version is selected (must include a named capture group `(?P<version>...)`)
  - `submodules` — whether to fetch git submodules (boolean)
  - `extract` — override the clone target directory

```yaml
# php-glfw cloned from the master branch
artifact:
  source:
    type: git
    url: 'https://github.com/mario-deluna/php-glfw'
    rev: master
```

---

### ghrel

Downloads a file from GitHub Release Assets using a regex to match the asset filename. Best suited for repositories that upload pre-compiled packages or source archives as release assets.

- **Class**: `StaticPHP\Artifact\Downloader\Type\GitHubRelease`
- **Capabilities**: Version update checking (`CheckUpdateInterface`), integrity verification (`ValidatorInterface`, SHA256)
- **Required**:
  - `repo` — repository path in `owner/repo` format
  - `match` — PCRE regex (without delimiters) to match the asset filename, e.g. `openssl.+\.tar\.gz`
- **Optional**:
  - `prefer-stable` — skip pre-release versions (default: `true`)
  - `query` — query string appended to the API URL (e.g. `?per_page=5`)
  - `extract` — override extraction directory

```yaml
# openssl downloaded from GitHub Release Assets
artifact:
  source:
    type: ghrel
    repo: openssl/openssl
    match: openssl.+\.tar\.gz
    prefer-stable: true
```

---

### ghtar

Downloads the source tarball automatically generated by a GitHub Release (the "Source code" archive on the Release page). Unlike `ghrel` which downloads uploaded assets, `ghtar` uses the auto-generated tarball from the `/releases` API.

- **Class**: `StaticPHP\Artifact\Downloader\Type\GitHubTarball`
- **Capabilities**: Version update checking (`CheckUpdateInterface`)
- **Required**: `repo` — repository path in `owner/repo` format
- **Optional**:
  - `prefer-stable` — skip pre-release versions (default: `true`)
  - `match` — regex filter applied to `tarball_url` (if omitted, the first result is used)
  - `query` — query string appended to the API URL
  - `extract` — override extraction directory

```yaml
# librdkafka downloaded via GitHub Release tarball
artifact:
  source:
    type: ghtar
    repo: confluentinc/librdkafka
```

---

### ghtagtar

Downloads a source tarball from a GitHub Tag via the `/tags` API. Functionally identical to `ghtar`, but targets the tags endpoint instead of releases — useful for repositories that tag releases without creating a formal GitHub Release.

- **Class**: `StaticPHP\Artifact\Downloader\Type\GitHubTarball` (shared with `ghtar`)
- **Capabilities**: Version update checking (`CheckUpdateInterface`)
- **Required**: `repo` — repository path in `owner/repo` format
- **Optional**:
  - `prefer-stable` — skip pre-release versions (default: `true`)
  - `match` — regex filter applied to tag names (if omitted, the latest tag is used)
  - `query` — query string appended to the API URL
  - `extract` — override extraction directory

```yaml
# brotli: only match v1.x tags
artifact:
  source:
    type: ghtagtar
    repo: google/brotli
    match: 'v1\.\d.*'

# libpng: match v1.6.x tags, with pagination
artifact:
  source:
    type: ghtagtar
    repo: pnggroup/libpng
    match: v1\.6\.\d+
    query: '?per_page=150'
```

---

### filelist

Fetches an HTML page (typically an official download index), extracts filenames and version numbers from the page content using a regex, then automatically selects and downloads the highest stable version. Pre-release versions (those containing keywords like alpha, beta, rc, dev, nightly, or snapshot) are automatically skipped.

**Best for**: projects without GitHub that publish versioned archives on their own FTP or web index, such as `https://ftp.gnu.org/pub/gnu/ncurses/`.

- **Class**: `StaticPHP\Artifact\Downloader\Type\FileList`
- **Capabilities**: Version update checking (`CheckUpdateInterface`)
- **Required**:
  - `url` — URL of the HTML page containing the file listing
  - `regex` — PCRE regex to extract filenames and versions from the page (must include named capture groups `(?<file>...)` and `(?<version>...)`)
- **Optional**:
  - `extract` — override extraction directory
  - `download-url` — custom download URL template supporting `{file}` and `{version}` placeholders (by default the filename is appended directly to `url`)

```yaml
# ncurses: scrape latest version from the GNU FTP index
artifact:
  source:
    type: filelist
    url: 'https://ftp.gnu.org/pub/gnu/ncurses/'
    regex: '/href="(?<file>ncurses-(?<version>[^"]+)\.tar\.gz)"/'

# openssl: mirror source using filelist
artifact:
  source-mirror:
    type: filelist
    url: 'https://www.openssl.org/source/'
    regex: '/href="(?<file>openssl-(?<version>[^"]+)\.tar\.gz)"/'
```

---

### pecl

Downloads a PHP extension source package from [PECL](https://pecl.php.net) using the PECL REST API. The latest stable version is selected automatically.

- **Class**: `StaticPHP\Artifact\Downloader\Type\PECL`
- **Capabilities**: Version update checking (`CheckUpdateInterface`)
- **Required**: `name` — PECL package name (case-insensitive, e.g. `APCu`)
- **Optional**:
  - `prefer-stable` — download stable releases only (default: `true`)
  - `extract` — override extraction directory (default: `php-src/ext/{name}`)

```yaml
# APCu downloaded from PECL
artifact:
  source:
    type: pecl
    name: APCu
```

---

### pie

Downloads a PHP extension from [Packagist](https://repo.packagist.org) following the [PIE](https://github.com/php/pie) specification. Package metadata is fetched via the Packagist `p2/` API, and the source archive is downloaded from the `dist` field.

- **Class**: `StaticPHP\Artifact\Downloader\Type\PIE`
- **Capabilities**: Version update checking (`CheckUpdateInterface`)
- **Required**: `repo` — Packagist package path in `vendor/package` format
- **Optional**:
  - `extract` — override extraction directory

```yaml
# xdebug downloaded from Packagist
artifact:
  source:
    type: pie
    repo: xdebug/xdebug

# php-spx with a custom extraction path
artifact:
  source:
    type: pie
    repo: noisebynorthwest/php-spx
    extract: php-src/ext/spx
```

---

### php-release

Downloads the official PHP source from [php.net](https://www.php.net). The version is determined at build time by the `--with-php` argument. SHA256 integrity is verified automatically. Passing `git` as the version will clone the `master` branch of `php/php-src` directly.

- **Class**: `StaticPHP\Artifact\Downloader\Type\PhpRelease`
- **Capabilities**: Version update checking (`CheckUpdateInterface`), integrity verification (`ValidatorInterface`, SHA256)
- **Required**: `domain` — download domain (e.g. `https://www.php.net` or a custom mirror)
- **Optional**:
  - `extract` — override extraction directory

```yaml
# php-src with primary and mirror sources
artifact:
  source:
    type: php-release
    domain: 'https://www.php.net'
  source-mirror:
    type: php-release
    domain: 'https://phpmirror.static-php.dev'
```

---

### bitbuckettag

Downloads a source tarball from the latest tag of a Bitbucket repository via the Bitbucket REST API.

- **Class**: `StaticPHP\Artifact\Downloader\Type\BitBucketTag`
- **Capabilities**: Basic download only; no automatic version update checking
- **Required**: `repo` — repository path in `workspace/repo` format
- **Optional**:
  - `extract` — override extraction directory

```yaml
artifact:
  source:
    type: bitbuckettag
    repo: snappy-m-o/php-snappy
```

---

### local

Uses a pre-existing local directory as the source without performing any download. Useful for offline environments or local development where the source has already been placed on disk.

- **Class**: `StaticPHP\Artifact\Downloader\Type\LocalDir`
- **Capabilities**: Basic download only; no automatic version update checking
- **Required**: `dirname` — absolute path to the local directory
- **Optional**:
  - `extract` — override extraction directory

```yaml
artifact:
  source:
    type: local
    dirname: /path/to/local/source
```

---

### custom

Delegates download logic entirely to a PHP class under `src/Package/Artifact/`. If `func` is not specified, the class's default download method is called.

- **Optional**: `func` — name of the specific method to invoke in the implementation class

```yaml
artifact:
  source:
    type: custom
```
