<?php

declare(strict_types=1);

namespace SPC\command;

use SPC\util\SPCConfigUtil;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand('spc-config', 'Build dependencies')]
class SPCConfigCommand extends BaseCommand
{
    protected bool $no_motd = true;

    public function configure(): void
    {
        $this->addArgument('extensions', InputArgument::OPTIONAL, 'The extensions will be compiled, comma separated');
        $this->addOption('with-libs', null, InputOption::VALUE_REQUIRED, 'add additional libraries, comma separated', '');
        $this->addOption('with-suggested-libs', 'L', null, 'Build with suggested libs for selected exts and libs');
        $this->addOption('with-suggested-exts', 'E', null, 'Build with suggested extensions for selected exts');
        $this->addOption('includes', null, null, 'Add additional include path');
        $this->addOption('libs', null, null, 'Add additional libs path');
        $this->addOption('libs-only-deps', null, null, 'Output dependent libraries with -l prefix');
        $this->addOption('absolute-libs', null, null, 'Output absolute paths for libraries');
        $this->addOption('no-php', null, null, 'Do not link to PHP library');
    }

    public function handle(): int
    {
        // transform string to array
        $libraries = array_map('trim', array_filter(explode(',', $this->getOption('with-libs'))));
        // transform string to array
        $extensions = $this->getArgument('extensions') ? $this->parseExtensionList($this->getArgument('extensions')) : [];
        $include_suggest_ext = $this->getOption('with-suggested-exts');
        $include_suggest_lib = $this->getOption('with-suggested-libs');

        $util = new SPCConfigUtil(options: [
            'no_php' => $this->getOption('no-php'),
            'libs_only_deps' => $this->getOption('libs-only-deps'),
            'absolute_libs' => $this->getOption('absolute-libs'),
        ]);
        $config = $util->config($extensions, $libraries, $include_suggest_ext, $include_suggest_lib);

        $this->output->writeln(match (true) {
            $this->getOption('includes') => $config['cflags'],
            $this->getOption('libs-only-deps') => $config['libs'],
            $this->getOption('libs') => "{$config['ldflags']} {$config['libs']}",
            default => "{$config['cflags']} {$config['ldflags']} {$config['libs']}",
        });

        return 0;
    }
}
