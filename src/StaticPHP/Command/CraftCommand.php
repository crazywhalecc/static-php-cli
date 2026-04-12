<?php

declare(strict_types=1);

namespace StaticPHP\Command;

use StaticPHP\Doctor\Doctor;
use StaticPHP\Exception\ValidationException;
use StaticPHP\Package\PackageInstaller;
use StaticPHP\Util\FileSystem;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

#[AsCommand('craft', 'Build static-php from craft.yml')]
class CraftCommand extends BaseCommand
{
    public function configure(): void
    {
        $this->addArgument('craft', null, 'Path to craft.yml file', WORKING_DIR . '/craft.yml');
    }

    public function handle(): int
    {
        $craft_file = $this->getArgument('craft');
        if (!file_exists($craft_file)) {
            $this->output->writeln('<error>craft.yml not found, please create one!</error>');
            return static::USER_ERROR;
        }

        $craft = $this->validateAndParseCraftFile($craft_file);

        // set verbosity
        $this->output->setVerbosity($craft['verbosity']);

        // apply env
        array_walk($craft['extra-env'], fn ($v, $k) => f_putenv("{$k}={$v}"));

        // run doctor
        if ($craft['craft-options']['doctor']) {
            $doctor = new Doctor($this->output, FIX_POLICY_AUTOFIX);
            if ($doctor->checkAll()) {
                Doctor::markPassed();
                $this->output->writeln('');
            } else {
                $this->output->writeln('<error>Doctor check failed, please fix the issues and try again.</error>');
                return static::ENVIRONMENT_ERROR;
            }
        }

        // parse download-options to installer's dl options
        $build_options = $craft['build-options'];
        if (!$craft['craft-options']['download']) {
            $build_options['no-download'] = true;
        }
        foreach ($craft['download-options'] as $k => $v) {
            $build_options["dl-{$k}"] = $v;
        }

        // parse SAPI
        foreach ($craft['sapi'] as $name) {
            $build_options["build-{$name}"] = true;
        }

        // clean build
        if ($craft['clean-build']) {
            FileSystem::resetDir(BUILD_ROOT_PATH);
            FileSystem::resetDir(SOURCE_PATH);
        }

        $starttime = microtime(true);
        // run installer
        $installer = new PackageInstaller($build_options);
        $installer->addBuildPackage('php');
        $installer->run(true);

        $usedtime = round(microtime(true) - $starttime, 1);
        $this->output->writeln("\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        $this->output->writeln("<info>✔ BUILD SUCCESSFUL ({$usedtime} s)</info>");
        $this->output->writeln("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n");

        $installer->printBuildPackageOutputs();

        return static::SUCCESS;
    }

    /**
     * Validate and parse craft.yml file to array.
     *
     * @param string $craft_file craft.yml path
     * @return array{
     *     php-version: string,
     *     extensions: array<string>,
     *     shared-extensions: array<string>,
     *     packages: array<string>,
     *     sapi: array<string>,
     *     verbosity: int,
     *     debug: bool,
     *     clean-build: bool,
     *     build-options: array<string, mixed>,
     *     download-options: array<string, mixed>,
     *     extra-env: array<string, string>,
     *     craft-options: array{
     *         doctor: bool,
     *         download: bool,
     *         build: bool
     *     }
     * }  Parsed craft content
     */
    private function validateAndParseCraftFile(string $craft_file): array
    {
        $build_options = $this->getApplication()->find('build:php')->getDefinition()->getOptions();
        $download_options = $this->getApplication()->find('download')->getDefinition()->getOptions();
        try {
            $craft = Yaml::parseFile($craft_file);
        } catch (ParseException $e) {
            throw new ValidationException("Craft file '{$craft_file}' is broken: {$e->getMessage()}");
        }
        if (!is_assoc_array($craft)) {
            throw new ValidationException("Craft file '{$craft_file}' must be an associative array.");
        }

        // check php-version
        if (isset($craft['php-version']) && !preg_match('/^(\d+)(\.\d+)?(\.\d+)?$/', strval($craft['php-version']))) {
            throw new ValidationException("Craft file '{$craft_file}' has invalid 'php-version' field, it should be in format of '8.0.0'.");
        }

        // check php extensions field
        if (!isset($craft['extensions'])) {
            throw new ValidationException("Craft file '{$craft_file}' must have 'extensions' field.");
        }
        // parse extension if not list
        if (is_string($craft['extensions'])) {
            $craft['extensions'] = parse_extension_list($craft['extensions']);
        }

        // check shared-extensions field
        if (!isset($craft['shared-extensions'])) {
            $craft['shared-extensions'] = [];
        } elseif (is_string($craft['shared-extensions'])) {
            $craft['shared-extensions'] = parse_extension_list($craft['shared-extensions']);
        }

        // check libs and additional packages
        $v2_libs = parse_comma_list($craft['libs'] ?? []);
        $v3_packages = parse_comma_list($craft['packages'] ?? []);
        $craft['packages'] = array_merge($v2_libs, $v3_packages);

        // check PHP SAPI
        if (!isset($craft['sapi'])) {
            throw new ValidationException('Craft file "sapi" is required.');
        }
        if (is_string($craft['sapi'])) {
            $craft['sapi'] = parse_comma_list($craft['sapi']);
        }

        // verbosity
        $verbosity_level = $craft['verbosity'] ?? OutputInterface::VERBOSITY_NORMAL;
        $debug = $craft['debug'] ?? false;
        if ($debug) {
            $verbosity_level = OutputInterface::VERBOSITY_DEBUG;
        }
        $craft['verbosity'] = $verbosity_level;

        // clean-build (if true, reset before all builds)
        $craft['clean-build'] ??= false;

        // build-options
        if (isset($craft['build-options'])) {
            if (!is_assoc_array($craft['build-options'])) {
                throw new ValidationException('Craft file "build" options must be an associative array.');
            }
            foreach ($craft['build-options'] as $key => $value) {
                if (!isset($build_options[$key])) {
                    throw new ValidationException('Craft file "build" option "' . $key . '" is invalid.');
                }
                if ($build_options[$key]->isArray() && !is_array($value)) {
                    throw new ValidationException('Craft file "build" option "' . $key . '" must be an array.');
                }
            }
        } else {
            $craft['build-options'] = [];
        }

        // download-options
        if (isset($craft['download-options'])) {
            if (!is_assoc_array($craft['download-options'])) {
                throw new ValidationException('Craft file "download" options must be an associative array.');
            }
            foreach ($craft['download-options'] as $key => $value) {
                if (!isset($download_options[$key])) {
                    throw new ValidationException('Craft file "download" option "' . $key . '" is invalid.');
                }
                if ($download_options[$key]->isArray() && !is_array($value)) {
                    throw new ValidationException('Craft file "download" option "' . $key . '" must be an array.');
                }
            }
        } else {
            $craft['download-options'] = [];
        }

        // post-parse: parse php-version field to download options
        if (isset($craft['php-version'])) {
            $craft['download-options']['with-php'] = strval($craft['php-version']);
            $craft['download-options']['ignore-cache'] = (($craft['download-options']['ignore-cache'] ?? false) === true ? true : 'php-src');
        }

        // extra-env
        if (isset($craft['extra-env'])) {
            if (!is_assoc_array($craft['extra-env'])) {
                throw new ValidationException('Craft file "extra-env" must be an associative array.');
            }
        } else {
            $craft['extra-env'] = [];
        }

        // craft-options
        $craft['craft-options']['doctor'] ??= true;
        $craft['craft-options']['download'] ??= true;

        return $craft;
    }
}
