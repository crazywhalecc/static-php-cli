<?php

declare(strict_types=1);

namespace StaticPHP\Command;

use StaticPHP\Util\SPCConfigUtil;
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
        $this->addOption('with-packages', null, InputOption::VALUE_REQUIRED, 'add additional libraries, comma separated', '');
        $this->addOption('with-suggested-libs', 'L', null, 'Build with suggested libs for selected exts and libs');
        $this->addOption('with-suggests', null, null, 'Build with suggested packages for selected exts and libs');
        $this->addOption('with-suggested-exts', 'E', null, 'Build with suggested extensions for selected exts');
        $this->addOption('includes', null, null, 'Add additional include path');
        $this->addOption('libs', null, null, 'Add additional libs path');
        $this->addOption('libs-only-deps', null, null, 'Output dependent libraries with -l prefix');
        $this->addOption('absolute-libs', null, null, 'Output absolute paths for libraries');
        $this->addOption('no-php', null, null, 'Link to PHP library');
    }

    public function handle(): int
    {
        // transform string to array
        $libraries = parse_comma_list($this->getOption('with-libs'));
        $libraries = array_merge($libraries, $this->getOption('with-packages'));
        // transform string to array
        $extensions = $this->getArgument('extensions') ? parse_extension_list($this->getArgument('extensions')) : [];
        $include_suggests = $this->getOption('with-suggests') ?: $this->getOption('with-suggested-libs') || $this->getOption('with-suggested-exts');

        $util = new SPCConfigUtil(options: [
            'no_php' => (bool) $this->getOption('no-php'),
            'libs_only_deps' => (bool) $this->getOption('libs-only-deps'),
            'absolute_libs' => (bool) $this->getOption('absolute-libs'),
        ]);
        $packages = array_merge(array_map(fn ($x) => "ext-{$x}", $extensions), $libraries);
        $config = $util->config($packages, $include_suggests);

        $this->output->writeln(match (true) {
            $this->getOption('includes') => $config['cflags'],
            $this->getOption('libs-only-deps') => $config['libs'],
            $this->getOption('libs') => "{$config['ldflags']} {$config['libs']}",
            default => "{$config['cflags']} {$config['ldflags']} {$config['libs']}",
        });

        return 0;
    }
}
