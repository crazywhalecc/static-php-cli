<?php

declare(strict_types=1);

namespace SPC\command\dev;

use SPC\exception\FileSystemException;
use SPC\store\Config;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Exception\ExceptionInterface;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

#[AsCommand('dev:ext-skel', 'Generate extension skeleton', ['ext-skel'])]
class ExtSkeletonCommand extends SkeletonCommand
{
    /**
     * @throws FileSystemException
     */
    public function initialize(InputInterface $input, OutputInterface $output): void
    {
        $name = $input->getArgument('name');
        if ($name !== null && is_string($r = $this->validateExtName($name))) {
            throw new InvalidArgumentException($r);
        }
    }

    /**
     * @throws ExceptionInterface|FileSystemException
     */
    public function handle(): int
    {
        $result = ['type' => 'external'];
        // Get extension name
        $ext_name = $this->input->getArgument('name');

        // apply source name
        $result['source'] = $ext_name;

        // Select extension support
        $ext_support = multiselect('Please select extension support for [' . $ext_name . '].', [
            'Linux' => 'Linux',
            'Darwin' => 'MacOS',
            'Windows' => 'Windows',
        ], default: ['Linux', 'Darwin'], required: true, hint: 'Use the space bar to select options, press enter to the next step');

        // $input->setArgument('name', $ext_name);
        $a = new ArrayInput(['name' => $ext_name, '--is-middle-step' => true]);
        $this->getApplication()->find('dev:source-skel')->run($a, $this->output);

        // check if extension depends on other extensions
        $ext_depends = confirm('Does this extension depend on other extensions?', default: false) ? multiselect('Please select extension dependencies', array_keys(Config::getExts()), hint: 'Use the space bar to select options, press enter to the next step') : [];
        if ($ext_depends) {
            $result['ext-depends'] = $ext_depends;
        }

        // check if extension suggests other extensions
        $ext_suggests = confirm('Does this extension suggest other extensions?', default: false) ? multiselect('Please select extension suggestions', array_keys(Config::getExts()), hint: 'Use the space bar to select options, press enter to the next step') : [];
        if ($ext_suggests) {
            $result['ext-suggests'] = $ext_suggests;
        }

        // select extension build arg type (--enable-xxx, --with-xxx, with-xxx=PATH, custom)
        if (in_array('Linux', $ext_support) || in_array('Darwin', $ext_support)) {
            $ext_build_args_unix = select('Please select *nix (Linux, macOS) extension build arg type', [
                'enable' => '--enable-' . strtolower($ext_name),
                'with' => '--with-' . strtolower($ext_name),
                'with-xxx=' => '--with-' . strtolower($ext_name) . '={buildroot}',
                'custom' => 'custom',
            ], default: 'enable');
        }
        if (in_array('Windows', $ext_support)) {
            $ext_build_args_windows = select('Please select Windows extension build arg type', [
                'enable' => '--enable-' . strtolower($ext_name),
                'with' => '--with-' . strtolower($ext_name),
                'with-xxx=' => '--with-' . strtolower($ext_name) . '={buildroot}',
                'custom' => 'custom',
            ], default: 'enable');
        }
        $ext_build_args_unix ??= null;
        $ext_build_args_windows ??= $ext_build_args_unix;
        if ($ext_build_args_windows === $ext_build_args_unix) {
            $result['arg-type'] = $ext_build_args_windows;
        } else {
            $result['arg-type-unix'] = $ext_build_args_unix;
            $result['arg-type-windows'] = $ext_build_args_windows;
        }

        // check if extension depends on other libraries
        if (confirm('Does this extension depend on other libraries?', default: false)) {
            // Select library dependencies, or create a new library skeleton
            if (select('You can select existing libraries or create a new library skeleton', ['Create a new library skeleton', 'Select existing libraries'], default: 'Select existing libraries') === 'Create a new library skeleton') {
                $lib_name = text('Please input new library name', required: true, validate: [$this, 'validateLibName']);
                $lib_name = strtolower($lib_name);
                $input = new ArrayInput(['name' => $lib_name, '--is-middle-step' => true]);
                $this->getApplication()->find('dev:lib-skel')->run($input, $this->output);
            } else {
                // Select existing libraries
                $ext_libs = multiselect('Please select library dependencies', array_keys(Config::getLibs()), hint: 'Use the space bar to select options, press enter to the next step');
            }
        } else {
            $ext_libs = [];
        }
        if (!empty($ext_libs)) {
            $result['lib-depends'] = $ext_libs;
        }
        $this->output->writeln('<info>Extension config generated!</info>');
        $this->output->writeln(sprintf('<info>%s</info>', json_encode($result, JSON_PRETTY_PRINT)));
        SkeletonCommand::$cache['ext'][$ext_name] = $result;
        if (!$this->getOption('is-middle-step')) {
            $this->generateAll();
        }
        return static::SUCCESS;
    }
}
