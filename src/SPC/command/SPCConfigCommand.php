<?php

declare(strict_types=1);

namespace SPC\command;

use SPC\exception\RuntimeException;
use SPC\util\SPCConfigUtil;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand('spc-config', 'Build dependencies')]
class SPCConfigCommand extends BuildCommand
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
    }

    /**
     * @throws RuntimeException
     */
    public function handle(): int
    {
        // transform string to array
        $libraries = array_map('trim', array_filter(explode(',', $this->getOption('with-libs'))));
        // transform string to array
        $extensions = $this->getArgument('extensions') ? $this->parseExtensionList($this->getArgument('extensions')) : [];
        $include_suggest_ext = $this->getOption('with-suggested-exts');
        $include_suggest_lib = $this->getOption('with-suggested-libs');

        $util = new SPCConfigUtil(null, $this->input);
        $config = $util->config($extensions, $libraries, $include_suggest_ext, $include_suggest_lib);

        if ($this->getOption('includes')) {
            $this->output->writeln($config['cflags']);
        } elseif ($this->getOption('libs')) {
            $this->output->writeln("{$config['ldflags']} {$config['libs']}");
        } else {
            $this->output->writeln("{$config['cflags']} {$config['ldflags']} {$config['libs']}");
        }

        return 0;
    }
}
