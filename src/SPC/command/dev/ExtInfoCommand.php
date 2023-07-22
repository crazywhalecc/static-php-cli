<?php

declare(strict_types=1);

namespace SPC\command\dev;

use SPC\command\BaseCommand;
use SPC\store\Config;
use SPC\util\DependencyUtil;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;

#[AsCommand('dev:ext-info', 'Dev command')]
class ExtInfoCommand extends BaseCommand
{
    public function configure()
    {
        $this->addArgument('extensions', InputArgument::REQUIRED, 'The extension name you need to get info');
    }

    public function handle(): int
    {
        $extensions = array_map('trim', array_filter(explode(',', $this->getArgument('extensions'))));

        // 根据提供的扩展列表获取依赖库列表并编译
        foreach ($extensions as $extension) {
            $this->output->writeln('<comment>[ ' . $extension . ' ]</comment>');
            [, $libraries, $not_included] = DependencyUtil::getExtLibsByDeps([$extension]);
            $lib_suggests = Config::getExt($extension, 'lib-suggests', []);
            $ext_suggests = Config::getExt($extension, 'ext-suggests', []);
            $this->output->writeln("<info>lib-depends:\t" . implode(', ', $libraries) . '</info>');
            $this->output->writeln("<info>lib-suggests:\t" . implode(', ', $lib_suggests) . '</info>');
            $this->output->writeln("<info>ext-depends:\t" . implode(',', $not_included) . '</info>');
            $this->output->writeln("<info>ext-suggests:\t" . implode(', ', $ext_suggests) . '</info>');
            if (Config::getExt($extension, 'unix-only', false)) {
                $this->output->writeln("<info>Unix only:\ttrue</info>");
            }
            $this->output->writeln('');
        }

        return 0;
    }
}
