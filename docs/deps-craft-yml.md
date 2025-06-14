```yaml
# PHP version to build (default: 8.4)
php-version: 8.4
# [REQUIRED] Static PHP extensions to build (list or comma-separated are both accepted)
extensions: bcmath,fileinfo,phar,zlib,sodium,posix,pcntl
# Extra libraries to build (list or comma-separated are both accepted)
libs: [ ]
# [REQUIRED] Build SAPIs (list or comma-separated are both accepted)
sapi: cli,micro,fpm
# Show full console output (default: false)
debug: false
# Build options (same as `build` command options, all options are optional)
build-options:
  # Before build, remove all old build files and sources (default: false)
  with-clean: false
  # Build with all suggested libraries (default: false)
  with-suggested-libs: false
  # Build with all suggested extensions (default: false)
  with-suggested-exts: false
  # Build extra shared extensions (list or comma-separated are both accepted)
  build-shared: [ ]
  # Build without stripping the binary (default: false)
  no-strip: false
  # Disable Opcache JIT (default: false)
  disable-opcache-jit: false
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

# Build options for shared extensions (list or comma-separated are both accepted)
shared-extensions: [ ]

# Download options
download-options:
  # Use custom url for specified sources, format: "{source-name}:{url}" (e.g. "php-src:https://example.com/php-8.4.0.tar.gz")
  custom-url: [ ]
  # Use custom git repo for specified sources, format: "{source-name}:{branch}:{url}" (e.g. "php-src:master:https://github.com/php/php-src.git")
  custom-git: [ ]
  # Retries count for downloading sources (default: 5)
  retry: 5
  # Use pre-built libraries if available (default: false)
  prefer-pre-built: true
  # Do not download from alternative sources (default: false)
  no-alt: false

craft-options:
  doctor: true
  download: true
  build: true

# Extra environment variables
extra-env:
  # e.g. Use github token to avoid rate limit
  GITHUB_TOKEN: your-github-token
```
