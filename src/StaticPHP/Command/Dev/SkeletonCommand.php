<?php

declare(strict_types=1);

namespace StaticPHP\Command\Dev;

use StaticPHP\Command\BaseCommand;
use StaticPHP\Exception\WrongUsageException;
use StaticPHP\Registry\Registry;
use StaticPHP\Runtime\SystemTarget;
use StaticPHP\Skeleton\ArtifactGenerator;
use StaticPHP\Skeleton\PackageGenerator;
use Symfony\Component\Console\Attribute\AsCommand;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

#[AsCommand('dev:skel', 'Generate a skeleton for a StaticPHP package')]
class SkeletonCommand extends BaseCommand
{
    public function handle(): int
    {
        // Only available for non-Windows systems for now
        // Only available when spc loading mode is SPC_MODE_VENDOR, SPC_MODE_SOURCE
        if (spc_mode(SPC_MODE_PHAR)) {
            $this->output->writeln('<error>The dev:skel command is not available in phar mode.</error>');
            return 1;
        }
        if (SystemTarget::getTargetOS() === 'Windows') {
            $this->output->writeln('<error>The dev:skel command is not available on Windows systems.</error>');
            return 1;
        }

        $this->runMainMenu();

        return 0;
    }

    public function validatePackageName(string $name): ?string
    {
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $name)) {
            return 'Library name can only contain letters, numbers, underscores, and hyphens.';
        }
        // must start with a letter
        if (!preg_match('/^[a-zA-Z]/', $name)) {
            return 'Library name must start with a letter.';
        }
        return null;
    }

    private function runMainMenu(): void
    {
        $main = select('Please select the skeleton option', [
            'library' => 'Create a new library package',
            'target' => 'Create a new target package',
            'php-extension' => 'Create a new PHP extension',
            'q' => 'Exit',
        ]);
        $generator = match ($main) {
            'library' => $this->runCreateLib(),
            'target' => $this->runCreateTarget(),
            'php-extension' => $this->runCreateExt(),
            'q' => exit(0),
            default => null,
        };
        $write = $generator->writeAll();
        $this->output->writeln("<info>Package config in: {$write['package_config']}</info>");
        $this->output->writeln("<info>Artifact config in: {$write['artifact_config']}</info>");
        $this->output->writeln('<comment>Package class:</comment>');
        $this->output->writeln($write['package_class_content']);
    }

    private function runCreateLib(): PackageGenerator
    {
        // init empty
        $static_libs = '';
        $headers = '';
        $static_bins = '';
        $pkg_configs = '';

        // ask name
        $package_name = text('Please enter your library name', placeholder: 'e.g. pcre2', validate: [$this, 'validatePackageName']);

        // ask OS
        $os = select("[{$package_name}] On which OS family do you want to build this library?", [
            'unix' => 'Both Linux and Darwin (unix-like OS)',
            'linux' => 'Linux only',
            'macos' => 'Darwin(macOS) only',
            'windows' => 'Windows only',
            'all' => 'All supported OS (' . implode(', ', SUPPORTED_OS_FAMILY) . ')',
        ]);

        $produce = select("[{$package_name}] What does this library produce?", [
            'static_libs' => 'Static Libraries (.a/.lib)',
            'headers' => 'Header Files (.h)',
            'static_bins' => 'Static Binaries (executables)',
            'pkg_configs' => 'Pkg-Config files (.pc)',
            'all' => 'All of the above',
        ]);

        if ($produce === 'all' || $produce === 'static_libs') {
            $static_libs = text(
                'Please enter the names of the static libraries produced',
                placeholder: 'e.g. libpcre2.a, libbar.a',
                default: str_starts_with($package_name, 'lib') ? "{$package_name}.a" : "lib{$package_name}.a",
                validate: function ($value) {
                    $names = array_map('trim', explode(',', $value));
                    if (array_any($names, fn ($name) => !preg_match('/^[a-zA-Z0-9_.-]+$/', $name))) {
                        return 'Library names can only contain letters, numbers, underscores, hyphens, and dots.';
                    }
                    return null;
                },
                hint: 'Separate multiple names with commas'
            );
        }
        if ($produce === 'all' || $produce === 'headers') {
            $headers = text(
                'Please enter the names of the header files produced',
                placeholder: 'e.g. foo.h, bar.h',
                default: str_starts_with($package_name, 'lib') ? str_replace('lib', '', $package_name) . '.h' : $package_name . '.h',
                validate: function ($value) {
                    $names = array_map('trim', explode(',', $value));
                    if (array_any($names, fn ($name) => !preg_match('/^[a-zA-Z0-9_.-]+$/', $name))) {
                        return 'Header file names can only contain letters, numbers, underscores, hyphens, and dots.';
                    }
                    return null;
                },
                hint: 'Separate multiple names with commas, directories are allowed (e.g. openssl directory)'
            );
        }
        if ($produce === 'all' || $produce === 'static_bins') {
            $static_bins = text(
                'Please enter the names of the static binaries produced',
                placeholder: 'e.g. foo, bar',
                default: $package_name,
                validate: function ($value) {
                    $names = array_map('trim', explode(',', $value));
                    if (array_any($names, fn ($name) => !preg_match('/^[a-zA-Z0-9_.-]+$/', $name))) {
                        return 'Binary names can only contain letters, numbers, underscores, hyphens, and dots.';
                    }
                    return null;
                },
                hint: 'Separate multiple names with commas'
            );
        }
        if ($produce === 'all' || $produce === 'pkg_configs') {
            $pkg_configs = text(
                'Please enter the names of the pkg-config files produced',
                placeholder: 'e.g. foo.pc, bar.pc',
                default: str_starts_with($package_name, 'lib') ? str_replace('lib', '', $package_name) . '.pc' : $package_name . '.pc',
                validate: function ($value) {
                    if (!str_ends_with($value, '.pc')) {
                        return 'Pkg-config file names must end with .pc extension.';
                    }
                    return null;
                },
                hint: 'Separate multiple names with commas'
            );
        }

        if ($headers === '' && $static_bins === '' && $static_libs === '' && $pkg_configs === '') {
            $this->output->writeln('<error>You must specify at least one of static libraries, header files, or static binaries produced.</error>');
            exit(1);
        }

        // ask source
        $artifact_generator = $this->runCreateArtifact($package_name, true, false, null);
        $package_generator = new PackageGenerator($package_name, 'library');
        // set artifact
        $package_generator = $package_generator->addArtifact($artifact_generator);
        // set os
        $package_generator = match ($os) {
            'unix' => $package_generator->enableBuild(['Darwin', 'Linux'], 'build'),
            'linux' => $package_generator->enableBuild(['Linux'], 'build'),
            'macos' => $package_generator->enableBuild(['Darwin'], 'build'),
            'windows' => $package_generator->enableBuild(['Windows'], 'build'),
            'all' => $package_generator->enableBuild(SUPPORTED_OS_FAMILY, 'build'),
            default => $package_generator,
        };
        // set produce
        if ($static_libs !== '') {
            $lib_names = array_map('trim', explode(',', $static_libs));
            foreach ($lib_names as $lib_name) {
                $package_generator = $package_generator->addStaticLib($lib_name, $os === 'all' ? 'all' : ($os === 'unix' ? 'unix' : $os));
            }
        }
        if ($headers !== '') {
            $header_names = array_map('trim', explode(',', $headers));
            foreach ($header_names as $header_name) {
                $package_generator = $package_generator->addHeaderFile($header_name, $os === 'all' ? 'all' : ($os === 'unix' ? 'unix' : $os));
            }
        }
        if ($static_bins !== '') {
            $bin_names = array_map('trim', explode(',', $static_bins));
            foreach ($bin_names as $bin_name) {
                $package_generator = $package_generator->addStaticBin($bin_name, $os === 'all' ? 'all' : ($os === 'unix' ? 'unix' : $os));
            }
        }
        if ($pkg_configs !== '') {
            $pc_names = array_map('trim', explode(',', $pkg_configs));
            foreach ($pc_names as $pc_name) {
                $package_generator = $package_generator->addPkgConfigFile($pc_name, $os === 'all' ? 'all' : ($os === 'unix' ? 'unix' : $os));
            }
        }
        // ask for package config writing selection, same as artifact
        $package_configs = Registry::getLoadedPackageConfigs();
        $package_config_file = select("[{$package_name}] Please select the package config file to write the package config to", $package_configs);
        return $package_generator->setConfigFile($package_config_file);
    }

    private function runCreateArtifact(
        string $package_name,
        ?bool $create_source,
        ?bool $create_binary,
        string|true|null $default_extract_dir = true
    ): ArtifactGenerator {
        $artifact = new ArtifactGenerator($package_name);

        if ($create_source === null) {
            $create_source = confirm("[{$package_name}] Do you want to create a source artifact?");
        }

        if (!$create_source) {
            goto binary;
        }

        $source_type = select("[{$package_name}] Where is the source code located?", SPC_DOWNLOAD_TYPE_DISPLAY_NAME);

        $source_config = $this->askDownloadTypeConfig($package_name, $source_type, $default_extract_dir, 'source');
        $artifact = $artifact->setSource($source_config);

        binary:
        if ($create_binary === null) {
            $create_binary = confirm("[{$package_name}] Do you want to create a binary artifact?");
        }

        if (!$create_binary) {
            goto end;
        }

        $binary_fix = [
            'macos-x86_64' => null,
            'macos-aarch64' => null,
            'linux-x86_64' => null,
            'linux-aarch64' => null,
            'windows-x86_64' => null,
        ];
        while (($os = select("[{$package_name}] Please configure the binary downloading options for OS", [
            'macos-x86_64' => 'macos-x86_64' . ($binary_fix['macos-x86_64'] ? ' (done)' : ''),
            'macos-aarch64' => 'macos-aarch64' . ($binary_fix['macos-aarch64'] ? ' (done)' : ''),
            'linux-x86_64' => 'linux-x86_64' . ($binary_fix['linux-x86_64'] ? ' (done)' : ''),
            'linux-aarch64' => 'linux-aarch64' . ($binary_fix['linux-aarch64'] ? ' (done)' : ''),
            'windows-x86_64' => 'windows-x86_64' . ($binary_fix['windows-x86_64'] ? ' (done)' : ''),
            'copy' => 'Duplicate from another OS',
            'finish' => 'Submit',
        ])) !== 'finish') {
            $source_type = select("[{$package_name}] Where is the binary for {$os} located?", SPC_DOWNLOAD_TYPE_DISPLAY_NAME);
            $source_config = $this->askDownloadTypeConfig($package_name, $source_type, $default_extract_dir, 'binary');
            // set to artifact
            $artifact = $artifact->setBinary($os, $source_config);
            $binary_fix[$os] = true;
        }

        end:

        // generate config files, select existing package config file to write
        $artifact_configs = Registry::getLoadedArtifactConfigs();
        $artifact_config_file = select("[{$package_name}] Please select the artifact config file to write the artifact config to", $artifact_configs);
        return $artifact->setConfigFile($artifact_config_file);
    }

    private function runCreateTarget(): PackageGenerator
    {
        throw new WrongUsageException('Not implemented');
    }

    private function runCreateExt(): PackageGenerator
    {
        throw new WrongUsageException('Not implemented');
    }

    private function askDownloadTypeConfig(string $package_name, int|string $source_type, bool|string|null $default_extract_dir, string $config_type): array
    {
        $source_config = ['type' => $source_type];
        switch ($source_type) {
            case 'bitbuckettag':
                $source_config['repo'] = text("[{$package_name}] Please enter the BitBucket repository (e.g. user/repo)");
                break;
            case 'filelist':
                $source_config['url'] = text(
                    "[{$package_name}] Please enter the file index website URL",
                    placeholder: 'e.g. https://ftp.gnu.org/pub/gnu/gettext/',
                    hint: 'Make sure the target url is a directory listing page like ftp.gnu.org.'
                );
                $source_config['regex'] = text(
                    "[{$package_name}] Please enter the regex pattern to match the archive file",
                    placeholder: 'e.g. /gettext-(\d+\.\d+(\.\d+)?)\.tar\.gz/',
                    default: "/href=\"(?<file>{$package_name}-(?<version>[^\"]+)\\.tar\\.gz)\"/",
                    hint: 'Make sure the regex contains a capturing group for the version number.'
                );
                break;
            case 'git':
                $source_config['url'] = text(
                    "[{$package_name}] Please enter the Git repository URL",
                    validate: function ($value) {
                        if (!filter_var($value, FILTER_VALIDATE_URL) && !preg_match('/^(git|ssh|http|https|git@[-\w.]+):(\/\/)?(.*?)(\.git)(\/?|#[-\d\w._]+?)$/', $value)) {
                            return 'Please enter a valid Git repository URL.';
                        }
                        return null;
                    },
                    hint: 'e.g. https://github.com/user/repo.git'
                );
                $source_config['rev'] = text(
                    "[{$package_name}] Please enter the Git revision (branch, tag, or commit hash)",
                    default: 'main',
                    hint: 'e.g. main, master, v1.0.0, or a commit hash'
                );
                break;
            case 'ghrel':
                $source_config['repo'] = text("[{$package_name}] Please enter the GitHub repository (e.g. user/repo)");
                $source_config['match'] = text(
                    "[{$package_name}] Please enter the regex pattern to match the source archive file",
                    placeholder: 'e.g. /foo-(\d+\.\d+(\.\d+)?)\.tar\.gz/',
                    default: "{$package_name}-.+\\.tar\\.gz",
                );
                break;
            case 'ghtar':
            case 'ghtagtar':
                $source_config['repo'] = text("[{$package_name}] Please enter the GitHub repository (e.g. user/repo)");
                $source_config['prefer-stable'] = confirm("[{$package_name}] Do you want to prefer stable releases?");
                if ($source_type === 'ghtagtar' && confirm('Do you want to match tags with a specific pattern?', default: false)) {
                    $source_config['match'] = text(
                        "[{$package_name}] Please enter the regex pattern to match tags",
                        placeholder: 'e.g. v(\d+\.\d+(\.\d+)?)',
                    );
                }
                break;
            case 'local':
                $source_config['dirname'] = text(
                    "[{$package_name}] Please enter the local directory path",
                    validate: function ($value) {
                        if (trim($value) === '') {
                            return 'Local source directory cannot be empty.';
                        }
                        if (!is_dir($value)) {
                            return 'The specified local source directory does not exist.';
                        }
                        return null;
                    },
                );
                break;
            case 'pie':
                $source_config['repo'] = text(
                    "[{$package_name}] Please enter the PIE repository name",
                    placeholder: 'e.g. user/repo',
                );
                break;
            case 'url':
                $source_config['url'] = text(
                    "[{$package_name}] Please enter the file download URL",
                    validate: function ($value) {
                        if (!filter_var($value, FILTER_VALIDATE_URL)) {
                            return 'Please enter a valid URL.';
                        }
                        return null;
                    },
                );
                break;
            case 'custom':
                break;
        }
        // ask extract dir if is true
        if ($default_extract_dir === true) {
            if (confirm('Do you want to specify a custom extract directory?')) {
                $extract_hint = match ($config_type) {
                    'source' => 'the source will be from the `source/` dir by default',
                    'binary' => 'the binary will be from the `pkgroot/{arch}-{os}/` dir by default',
                    default => '',
                };
                $default_extract_dir = text(
                    "[{$package_name}] Please enter the source extract directory",
                    validate: function ($value) {
                        if (trim($value) === '') {
                            return 'Extract directory cannot be empty.';
                        }
                        return null;
                    },
                    hint: 'You can use relative path, ' . $extract_hint . '.'
                );
            } else {
                $default_extract_dir = null;
            }
        }
        if ($default_extract_dir !== null) {
            $source_config['extract'] = $default_extract_dir;
        }

        // return config
        return $source_config;
    }
}
