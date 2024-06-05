<?php

declare(strict_types=1);

namespace SPC\command;

use SPC\exception\FileSystemException;
use SPC\exception\RuntimeException;
use SPC\exception\WrongUsageException;
use SPC\util\DependencyUtil;
use SPC\util\LicenseDumper;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

/**
 * 修改 config 后对其 kv 进行排序的操作
 */
#[AsCommand('dump-license', 'Dump licenses for required libraries')]
class DumpLicenseCommand extends BaseCommand
{
    public function configure(): void
    {
        $this->addOption('for-extensions', null, InputOption::VALUE_REQUIRED, 'Dump by extensions and related libraries', null);
        $this->addOption('without-php', null, InputOption::VALUE_NONE, 'Dump without php-src');
        $this->addOption('for-libs', null, InputOption::VALUE_REQUIRED, 'Dump by libraries', null);
        $this->addOption('for-sources', null, InputOption::VALUE_REQUIRED, 'Dump by original sources (source.json)', null);
        $this->addOption('dump-dir', null, InputOption::VALUE_REQUIRED, 'Change dump directory', BUILD_ROOT_PATH . '/license');
    }

    /**
     * @throws WrongUsageException
     * @throws FileSystemException
     * @throws RuntimeException
     */
    public function handle(): int
    {
        $dumper = new LicenseDumper();
        if ($this->getOption('for-extensions') !== null) {
            // 从参数中获取要编译的 extensions，并转换为数组
            $extensions = $this->parseExtensionList($this->getOption('for-extensions'));
            // 根据提供的扩展列表获取依赖库列表并编译
            [$extensions, $libraries] = DependencyUtil::getExtsAndLibs($extensions);
            $dumper->addExts($extensions);
            $dumper->addLibs($libraries);
            if (!$this->getOption('without-php')) {
                $dumper->addSources(['php-src']);
            }
            $dumper->dump($this->getOption('dump-dir'));
            $this->output->writeln('Dump license with extensions: ' . implode(', ', $extensions));
            $this->output->writeln('Dump license with libraries: ' . implode(', ', $libraries));
            $this->output->writeln('Dump license with' . ($this->getOption('without-php') ? 'out' : '') . ' php-src');
            $this->output->writeln('Dump target dir: ' . $this->getOption('dump-dir'));
            return static::SUCCESS;
        }
        if ($this->getOption('for-libs') !== null) {
            $libraries = array_map('trim', array_filter(explode(',', $this->getOption('for-libs'))));
            $libraries = DependencyUtil::getLibs($libraries);
            $dumper->addLibs($libraries);
            $dumper->dump($this->getOption('dump-dir'));
            return $this->logWithResult(
                $dumper->dump($this->getOption('dump-dir')),
                'Dump target dir: ' . $this->getOption('dump-dir'),
                'Dump failed!'
            );
        }
        if ($this->getOption('for-sources') !== null) {
            $sources = array_map('trim', array_filter(explode(',', $this->getOption('for-sources'))));
            $dumper->addSources($sources);
            $dumper->dump($this->getOption('dump-dir'));
            $this->output->writeln('Dump target dir: ' . $this->getOption('dump-dir'));
            return static::SUCCESS;
        }
        $this->output->writeln('You must use one of "--for-extensions=", "--for-libs=", "--for-sources=" to dump');
        return static::FAILURE;
    }
}
