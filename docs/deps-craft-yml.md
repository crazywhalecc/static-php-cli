```yaml
# PHP version to build (default: 8.5)
php-version: 8.5
# [REQUIRED] Static PHP extensions to build (list or comma-separated are both accepted)
extensions: bcmath,fileinfo,phar,zlib,sodium,posix,pcntl
# Extra packages to build (list or comma-separated are both accepted)
packages: [ ]
# [REQUIRED] Build SAPIs (list or comma-separated are both accepted)
# Available: cli, micro, fpm, embed, frankenphp, cgi, all
sapi: cli,micro,fpm
# Show full console output (default: false)
debug: false
# Before build, remove all old build files and sources (default: false)
clean-build: false
# Build options (same as `build:php` command options, all options are optional)
build-options:
  # Build with all suggested packages (libraries and extensions) as well (default: false)
  with-suggests: false
  # Build extra shared extensions (comma-separated string)
  build-shared: ""
  # Build without stripping the binary (default: false)
  no-strip: false
  # Disable Opcache JIT (default: false)
  disable-opcache-jit: false
  # Enable thread-safe (ZTS) support (default: false)
  enable-zts: false
  # Disable smoke test, or for specific SAPIs comma-separated (default: false)
  no-smoke-test: false
  # PHP configuration options (same as --with-config-file-path)
  with-config-file-path: ""
  # PHP configuration options (same as --with-config-file-scan-dir)
  with-config-file-scan-dir: ""
  # Hardcoded INI options for cli and micro SAPI (e.g. "memory_limit=4G", list accepted)
  with-hardcoded-ini: [ ]
  # Pretend micro SAPI as cli SAPI to avoid some frameworks to limit the usage of micro SAPI
  with-micro-fake-cli: false
  # Additional patch point injection files (e.g. "path/to/patch.php", list accepted)
  with-added-patch: [ ]
  # Ignore micro extension tests (if you are using micro SAPI, default: false)
  without-micro-ext-test: false
  # UPX pack the binary (default: false)
  with-upx-pack: false
  # Set the micro.exe program icon (only for Windows, default: "")
  with-micro-logo: ""
  # Set micro SAPI as win32 mode, without this, micro SAPI will be compiled as a console application (only for Windows, default: false)
  enable-micro-win32: false
  # Path to a folder to be embedded in FrankenPHP (frankenphp SAPI only)
  with-frankenphp-app: ""

# Build options for shared extensions (list or comma-separated are both accepted)
shared-extensions: [ ]

# Download options
download-options:
  # Number of parallel downloads (default: 1)
  parallel: 1
  # Retries count for downloading sources (default: 0)
  retry: 0
  # Prefer source downloads when both source and binary are available (default: false)
  prefer-source: false
  # Prefer binary downloads when both source and binary are available (default: false)
  prefer-binary: false
  # Only download source artifacts, skip binary artifacts (default: false)
  source-only: false
  # Only download binary artifacts, skip source artifacts (default: false)
  binary-only: false
  # Ignore download cache for specified packages, comma separated (default: false)
  ignore-cache: false
  # Do not use alternative mirror download sources (default: false)
  no-alt: false
  # Do not clone shallowly repositories when downloading sources (default: false)
  no-shallow-clone: false
  # Use custom url for specified sources, format: "{source-name}:{url}" (e.g. "php-src:https://example.com/php-8.4.0.tar.gz")
  custom-url: [ ]
  # Use custom git repo for specified sources, format: "{source-name}:{branch}:{url}" (e.g. "php-src:master:https://github.com/php/php-src.git")
  custom-git: [ ]
  # Use custom local source path, format: "{source-name}:{path}" (e.g. "php-src:/path/to/php-src")
  custom-local: [ ]

craft-options:
  doctor: true
  download: true

# Extra environment variables
extra-env:
  # e.g. Use github token to avoid rate limit
  GITHUB_TOKEN: your-github-token
```
