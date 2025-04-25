<?php

declare(strict_types=1);

namespace SPC\command\dev;

use SPC\exception\FileSystemException;
use SPC\store\Config;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Exception\ExceptionInterface;
use Symfony\Component\Console\Input\ArrayInput;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;
use function Laravel\Prompts\textarea;

#[AsCommand('dev:lib-skel', 'Generate extension skeleton', ['lib-skel'])]
class LibSkeletonCommand extends SkeletonCommand
{
    /**
     * @throws ExceptionInterface
     * @throws FileSystemException
     */
    public function handle(): int
    {
        $lib_name = $this->input->getArgument('name');
        $result = [];

        // Select extension support
        $lib_support = multiselect('Please select lib support OS for ' . $lib_name, [
            'Linux' => 'Linux',
            'Darwin' => 'MacOS',
            'Windows' => 'Windows',
        ], default: ['Linux', 'Darwin'], hint: 'Use the space bar to select options, press enter to the next step');
        $result['lib-support'] = $lib_support;
        if (in_array('Linux', $lib_support) || in_array('Darwin', $lib_support)) {
            // ask static-libs-unix
            if (select('Please select static lib type for *nix', [
                'current' => 'Use ' . (str_starts_with($lib_name, 'lib') ? "{$lib_name}.a" : "lib{$lib_name}.a") . ' as default',
                'custom' => 'Specify custom static lib files',
            ]) === 'custom') {
                $result['static-libs-unix'] = explode("\n", textarea(
                    "Please input [{$lib_name}] static lib files for *nix (Linux and macOS)",
                    default: str_starts_with($lib_name, 'lib') ? "{$lib_name}.a" : '',
                    validate: [$this, 'validateStaticLibs'],
                    hint: 'Each line is a static lib name, e.g. libfoo.a',
                    transform: fn ($x) => implode("\n", array_filter(explode("\n", trim($x)), fn ($v) => trim($v) !== ''))
                ));
            } else {
                $result['static-libs-unix'] = [str_starts_with($lib_name, 'lib') ? "{$lib_name}.a" : "lib{$lib_name}.a"];
            }
        }
        if (in_array('Windows', $lib_support)) {
            // ask static-libs-win
            $result['static-libs-windows'] = textarea("Please input [{$lib_name}] static lib files for Windows", default: str_starts_with($lib_name, 'lib') ? "{$lib_name}.lib" : '', hint: 'Each line is a static lib name, e.g. foo.lib');
        }
        // ask for a lib source
        $a = new ArrayInput(['name' => $lib_name, '--is-middle-step' => true]);
        $this->getApplication()->find('dev:source-skel')->run($a, $this->output);
        $result['source'] = $lib_name;

        // ask for lib depends
        if (confirm('Does this library depend on other libraries?', default: false)) {
            // Select library dependencies, or create a new library skeleton
            if (select('You can select existing libraries or create a new library skeleton', ['Create a new library skeleton', 'Select existing libraries'], default: 'Select existing libraries') === 'Create a new library skeleton') {
                $lib_name = text('Please input new library name', required: true, validate: [$this, 'validateLibName']);
                $lib_name = strtolower($lib_name);
                $input = new ArrayInput(['name' => $lib_name]);
                $this->run($input, $this->output);
            } else {
                // Select existing libraries
                $lib_depends = multiselect('Please select library dependencies', array_keys(Config::getLibs()), hint: 'Use the space bar to select options, press enter to the next step');
            }
        } else {
            $lib_depends = [];
        }
        if (!empty($lib_depends)) {
            $result['lib-depends'] = $lib_depends;
        }

        // ask for using autoconf, cmake or other
        if (in_array('Darwin', $lib_support) || in_array('Linux', $lib_support)) {
            $build_tool = select("Please select [{$lib_name}] *nix build tool", [
                'cmake' => 'CMake (CMakeLists.txt)',
                'autoconf' => 'Autoconf (./configure)',
                'other' => 'Other',
            ], default: 'cmake');
            $result['build-tool-unix'] = $build_tool;
        }
        if (in_array('Windows', $lib_support)) {
            $build_tool = select("Please select [{$lib_name}] Windows build tool", [
                'cmake' => 'CMake (CMakeLists.txt)',
                'sln' => 'Visual Studio Solution (XXX.sln)',
                'other' => 'Other',
            ], default: 'cmake');
            $result['build-tool-windows'] = $build_tool;
        }

        $this->output->writeln("<info>Generated library config for {$lib_name}!</info>");
        SkeletonCommand::$cache['lib'][$lib_name] = $result;
        if (!$this->getOption('is-middle-step')) {
            $this->generateAll();
        }
        return static::SUCCESS;
    }
}
