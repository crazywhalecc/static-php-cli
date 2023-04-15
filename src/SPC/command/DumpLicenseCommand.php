<?php

declare(strict_types=1);

namespace SPC\command;

use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\exception\WrongUsageException;
use SPC\util\DependencyUtil;
use SPC\util\LicenseDumper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * 修改 config 后对其 kv 进行排序的操作
 */
class DumpLicenseCommand extends BaseCommand
{
    protected static $defaultName = 'dump-license';

    public function configure()
    {
        $this->setDescription('Dump licenses for required libraries');
        $this->addOption('by-extensions', null, InputOption::VALUE_REQUIRED, 'Dump by extensions and related libraries', null);
        $this->addOption('without-php', null, InputOption::VALUE_NONE, 'Dump without php-src');
        $this->addOption('by-libs', null, InputOption::VALUE_REQUIRED, 'Dump by libraries', null);
        $this->addOption('by-sources', null, InputOption::VALUE_REQUIRED, 'Dump by original sources (source.json)', null);
        $this->addOption('dump-dir', null, InputOption::VALUE_REQUIRED, 'Change dump directory', BUILD_ROOT_PATH . '/license');
    }

    /**
     * @throws WrongUsageException
     * @throws FileSystemException
     * @throws RuntimeException
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $dumper = new LicenseDumper();
        if ($input->getOption('by-extensions') !== null) {
            // 从参数中获取要编译的 extensions，并转换为数组
            $extensions = array_map('trim', array_filter(explode(',', $input->getOption('by-extensions'))));
            // 根据提供的扩展列表获取依赖库列表并编译
            [$extensions, $libraries, $not_included] = DependencyUtil::getExtLibsByDeps($extensions);
            $dumper->addExts($extensions);
            $dumper->addLibs($libraries);
            if (!$input->getOption('without-php')) {
                $dumper->addSources(['php-src']);
            }
            $dumper->dump($input->getOption('dump-dir'));
            $output->writeln('Dump license with extensions: ' . implode(', ', $extensions));
            $output->writeln('Dump license with libraries: ' . implode(', ', $libraries));
            $output->writeln('Dump license with' . ($input->getOption('without-php') ? 'out' : '') . ' php-src');
            $output->writeln('Dump target dir: ' . $input->getOption('dump-dir'));
            return 0;
        }
        if ($input->getOption('by-libs') !== null) {
            $libraries = array_map('trim', array_filter(explode(',', $input->getOption('by-libs'))));
            $libraries = DependencyUtil::getLibsByDeps($libraries);
            $dumper->addLibs($libraries);
            $dumper->dump($input->getOption('dump-dir'));
            $output->writeln('Dump target dir: ' . $input->getOption('dump-dir'));
            return 0;
        }
        if ($input->getOption('by-sources') !== null) {
            $sources = array_map('trim', array_filter(explode(',', $input->getOption('by-sources'))));
            $dumper->addSources($sources);
            $dumper->dump($input->getOption('dump-dir'));
            $output->writeln('Dump target dir: ' . $input->getOption('dump-dir'));
            return 0;
        }
        $output->writeln('You must use one of "--by-extensions=", "--by-libs=", "--by-sources=" to dump');
        return 1;
    }
}
