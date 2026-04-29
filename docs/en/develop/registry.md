# Registry & Plugin System

## Overview

The **Registry** is StaticPHP's core extension mechanism. Think of it as a "plugin package": a Registry consists of a declaration file (`spc.registry.yml`) and the configuration files and PHP classes it points to, describing a set of package definitions (YAML configuration) and their corresponding build logic (PHP classes). The build system loads all registered Registries at startup and merges their package definitions for use throughout the entire build process.

StaticPHP ships with a built-in core registry (`core`) that contains all definitions for PHP and related extensions, libraries, build tools, and more. The declaration file for the `core` registry is `spc.registry.yml` in the project root, which describes the mapping between the configuration file directory (`config/pkg/`, `config/artifact/`) and the build class PSR-4 namespace (`src/Package/`).

External Registries can only define new packages that don't already exist in `core`; they cannot override or modify existing definitions in the core registry. Depending on your needs, there are three ways to extend or modify StaticPHP's build capabilities:

- **Modify the `core` registry**: Directly edit files under `src/Package` and `config/pkg/`, suitable when you want to contribute changes back to the StaticPHP mainline. Please read the [Contributing Guide](../contributing/) section on contributing new packages before submitting a PR.
- **Vendor Mode**: Package your custom packages as a standalone sub-registry distributed as a Composer package, suitable for private packages or scenarios where you want to reuse build logic as a library. See [Extending StaticPHP](./extending/) for details.
- **External Registry (`SPC_REGISTRIES`)**: Specify one or more external registry file paths via the `SPC_REGISTRIES` environment variable, which StaticPHP loads at startup. Suitable for temporary extensions or scenarios where packaging as a Composer package isn't practical, similar to external source mechanisms in other package managers.

## Registry Declaration File

Each Registry has a declaration file, typically named `spc.registry.yml`, located in the project root or the root of a Composer package. The file format supports YAML (`.yml` / `.yaml`) and JSON (`.json`). All paths within the file are resolved relative to the directory containing the declaration file itself.

In source mode (direct git clone), StaticPHP loads `spc.registry.yml` in the project root as the core registry (`core`) by default. In Vendor mode, it automatically detects whether `spc.registry.yml` exists in the current Composer package root and loads it as a standalone registry. External registries specified via the `SPC_REGISTRIES` environment variable must also contain a valid declaration file.

Below is a complete example with all available fields (based on the `core` registry):

```yaml
# [Required] Unique registry name; loading a registry with a duplicate name is automatically skipped
name: my-registry

# [Optional] Composer autoload file path, used when an external registry has its own dependencies
autoload: vendor/autoload.php

# Package (library / php-extension / target) related configuration
package:
  # YAML configuration file directory or specific file paths for packages, can be an array
  config:
    - config/pkg/lib/
    - config/pkg/target/
    - config/pkg/ext/
  # PSR-4 namespace → directory path mapping for package build classes; the loader scans all PHP classes in the directory
  psr-4:
    Package: src/Package
  # You can also load specific classes as needed, supporting array format or {"ClassName": "file path"} mapping
  # classes:
  #   - Package\Library\MyLib
  #   MyLib: src/Package/Library/MyLib.php

# Artifact (build artifact) related configuration
artifact:
  # YAML configuration file directory or specific file paths for artifacts
  config:
    - config/artifact/
  # PSR-4 namespace → directory path mapping for custom artifact download/extract classes
  psr-4:
    Package\Artifact: src/Package/Artifact
  # classes: ... (same format as package.classes)

# Doctor environment check configuration
doctor:
  # PSR-4 namespace → directory path mapping for Doctor check item classes
  psr-4:
    StaticPHP\Doctor\Item: src/StaticPHP/Doctor/Item
  # classes: ... (same format as package.classes)

# Additional CLI command configuration
command:
  # PSR-4 namespace → directory path mapping for custom command classes
  psr-4:
    Package\Command: src/Package\Command
  # classes: ... (same format as package.classes)
```

Top-level field descriptions:

| Field | Required | Description |
|---|---|---|
| `name` | ✅ | Unique registry name; loading a registry with a duplicate name is automatically skipped |
| `autoload` | | Composer autoload file path, for external registries that carry their own dependencies |
| `package` | | Package definition, including YAML config (`config`) and build classes (`psr-4` / `classes`) |
| `artifact` | | Artifact definition, including YAML config (`config`) and custom classes (`psr-4` / `classes`) |
| `doctor` | | Doctor check item definition, class loading only (`psr-4` / `classes`) |
| `command` | | Additional CLI command definition, class loading only (`psr-4` / `classes`) |

The difference between `psr-4` and `classes`: `psr-4` scans all PHP classes in the entire directory that match the namespace rules and registers them in bulk; `classes` is used to precisely specify individual classes, supporting plain array format (`["ClassName"]`, must already be available in autoload) or key-value mapping format (`{"ClassName": "path/to/file.php"}`, the loader will automatically `require` the corresponding file).
