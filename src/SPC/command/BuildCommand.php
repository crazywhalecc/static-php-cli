<?php

declare(strict_types=1);

namespace SPC\command;

use Symfony\Component\Console\Input\InputOption;

abstract class BuildCommand extends BaseCommand
{
    public function __construct(?string $name = null)
    {
        parent::__construct($name);

        switch (PHP_OS_FAMILY) {
            case 'Windows':
                $this->addOption('with-sdk-binary-dir', null, InputOption::VALUE_REQUIRED, 'path to binary sdk');
                $this->addOption('vs-ver', null, InputOption::VALUE_REQUIRED, 'vs version, e.g. "17" for Visual Studio 2022');
                $this->addOption('arch', null, InputOption::VALUE_REQUIRED, 'architecture, "x64" or "arm64"', 'x64');
                break;
            case 'Linux':
                $this->addOption('libc', null, InputOption::VALUE_REQUIRED, 'glibc, musl or musl-wrapper', 'musl-wrapper');
                // no break
            case 'Darwin':
                $this->addOption('cc', null, InputOption::VALUE_REQUIRED, 'C compiler');
                $this->addOption('cxx', null, InputOption::VALUE_REQUIRED, 'C++ compiler');
                $this->addOption('ar', null, InputOption::VALUE_REQUIRED, 'ar');
                $this->addOption('ld', null, InputOption::VALUE_REQUIRED, 'ld');
                $this->addOption('arch', null, InputOption::VALUE_REQUIRED, 'architecture', php_uname('m'));
                break;
        }

        $this->addOption('with-clean', null, null, 'fresh build, remove `source` dir before `make`');
        $this->addOption('bloat', null, null, 'add all libraries into binary');
        $this->addOption('rebuild', 'r', null, 'Delete old build and rebuild');
        $this->addOption('enable-zts', null, null, 'enable ZTS support');
    }
}
