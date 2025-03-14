<?php

declare(strict_types=1);

namespace SPC\command;

use Symfony\Component\Console\Input\InputOption;

abstract class BuildCommand extends BaseCommand
{
    public function __construct(?string $name = null)
    {
        parent::__construct($name);

        if (PHP_OS_FAMILY === 'Windows') {
            $this->addOption('with-sdk-binary-dir', null, InputOption::VALUE_REQUIRED, 'path to binary sdk');
            $this->addOption('vs-ver', null, InputOption::VALUE_REQUIRED, 'vs version, e.g. "17" for Visual Studio 2022');
        }

        $this->addOption('with-clean', null, null, 'fresh build, remove `source` and `buildroot` dir before build');
        $this->addOption('bloat', null, null, 'add all libraries into binary');
        $this->addOption('rebuild', 'r', null, 'Delete old build and rebuild');
        $this->addOption('enable-zts', null, null, 'enable ZTS support');
    }
}
