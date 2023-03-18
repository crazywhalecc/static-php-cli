<?php

declare(strict_types=1);

namespace SPC\command;

use Symfony\Component\Console\Input\InputOption;

abstract class BuildCommand extends BaseCommand
{
    public function __construct(string $name = null)
    {
        parent::__construct($name);

        // 根据运行的操作系统分配允许不同的命令行参数，Windows 需要额外的 VS 和 SDK等，*nix 需要提供架构
        switch (PHP_OS_FAMILY) {
            case 'Windows':
                $this->addOption('with-sdk-binary-dir', null, InputOption::VALUE_REQUIRED, 'path to binary sdk');
                $this->addOption('vs-ver', null, InputOption::VALUE_REQUIRED, 'vs version, e.g. "17" for Visual Studio 2022');
                $this->addOption('arch', null, InputOption::VALUE_REQUIRED, 'architecture, "x64" or "arm64"', 'x64');
                break;
            case 'Linux':
                $this->addOption('no-system-static', null, null, 'do not use system static libraries');
                // no break
            case 'Darwin':
                $this->addOption('cc', null, InputOption::VALUE_REQUIRED, 'C compiler');
                $this->addOption('cxx', null, InputOption::VALUE_REQUIRED, 'C++ compiler');
                $this->addOption('arch', null, InputOption::VALUE_REQUIRED, 'architecture', php_uname('m'));
                break;
        }

        // 是否在编译 make 前清除旧的文件
        $this->addOption('with-clean', null, null, 'fresh build, `make clean` before `make`');
        // 是否采用强制链接，让链接器强制加载静态库文件
        $this->addOption('bloat', null, null, 'add all libraries into binary');
    }
}
