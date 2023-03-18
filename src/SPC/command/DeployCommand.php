<?php

declare(strict_types=1);

namespace SPC\command;

use CliHelper\Tools\ArgFixer;
use CliHelper\Tools\DataProvider;
use CliHelper\Tools\SeekableArrayIterator;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/** @noinspection PhpUnused */
class DeployCommand extends BaseCommand
{
    protected static $defaultName = 'deploy-self';

    public function configure()
    {
        $this->setDescription('Deploy static-php-cli self to an .phar application');
        $this->addArgument('target', InputArgument::OPTIONAL, 'The file or directory to pack.');
        $this->addOption('auto-phar-fix', null, InputOption::VALUE_NONE, 'Automatically fix ini option.');
        $this->addOption('overwrite', 'W', InputOption::VALUE_NONE, 'Overwrite existing files.');
        $this->addOption('disable-gzip', 'z', InputOption::VALUE_NONE, 'disable gzip archive mode');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        // 第一阶段流程：如果没有写path，将会提示输入要打包的path
        $prompt = new ArgFixer($input, $output);
        // 首先得确认是不是关闭了readonly模式
        if (ini_get('phar.readonly') == 1) {
            if ($input->getOption('auto-phar-fix')) {
                $ask = true;
            } else {
                $ask = $prompt->requireBool('<comment>pack command needs "phar.readonly" = "Off" !</comment>' . PHP_EOL . 'If you want to automatically set it and continue, just Enter', true);
            }
            $output->writeln('<info>Now running command in child process.</info>');
            if ($ask) {
                global $argv;
                passthru(PHP_BINARY . ' -d phar.readonly=0 ' . implode(' ', $argv), $retcode);
                exit($retcode);
            }
        }
        // 获取路径
        $path = WORKING_DIR;
        // 如果是目录，则将目录下的所有文件打包
        $phar_path = $prompt->requireArgument('target', 'Please input the phar target filename', 'static-php-cli.phar');

        if (DataProvider::isRelativePath($phar_path)) {
            $phar_path = '/tmp/' . $phar_path;
        }
        if (file_exists($phar_path)) {
            $ask = $input->getOption('overwrite') ? true : $prompt->requireBool('<comment>The file "' . $phar_path . '" already exists, do you want to overwrite it?</comment>' . PHP_EOL . 'If you want to, just Enter');
            if (!$ask) {
                $output->writeln('<comment>User canceled.</comment>');
                return 1;
            }
            @unlink($phar_path);
        }
        $phar = new \Phar($phar_path);
        $phar->startBuffering();

        $all = DataProvider::scanDirFiles($path, true, true);

        $all = array_filter($all, function ($x) {
            $dirs = preg_match('/(^(bin|config|src|vendor)\\/|^(composer\\.json|README\\.md|source\\.json|LICENSE|README-en\\.md)$)/', $x);
            return !($dirs !== 1);
        });
        sort($all);
        $map = [];
        foreach ($all as $v) {
            $map[$v] = $path . '/' . $v;
        }

        $output->writeln('<info>Start packing files...</info>');
        try {
            $phar->buildFromIterator(new SeekableArrayIterator($map, new ProgressBar($output)));
            $phar->addFromString(
                '.phar-entry.php',
                str_replace(
                    '/../vendor/autoload.php',
                    '/vendor/autoload.php',
                    file_get_contents(ROOT_DIR . '/bin/spc')
                )
            );
            $stub = '.phar-entry.php';
            $phar->setStub($phar->createDefaultStub($stub));
        } catch (\Throwable $e) {
            $output->writeln($e);
            return 1;
        }
        $phar->addFromString('.prod', 'true');
        if (!$input->getOption('disable-gzip')) {
            $phar->compressFiles(\Phar::GZ);
        }
        $phar->stopBuffering();
        $output->writeln(PHP_EOL . 'Done! Phar file is generated at "' . $phar_path . '".');
        if (file_exists(SOURCE_PATH . '/php-src/sapi/micro/micro.sfx')) {
            $output->writeln('Detected you have already compiled micro binary, I will make executable now for you!');
            file_put_contents(
                $phar_path . '.exe',
                file_get_contents(SOURCE_PATH . '/php-src/sapi/micro/micro.sfx') .
                file_get_contents($phar_path)
            );
            chmod($phar_path . '.exe', 0755);
            $output->writeln('<info>Static: ' . $phar_path . '.exe</info>');
        }
        chmod($phar_path, 0755);
        $output->writeln('<info>Phar: ' . $phar_path . '</info>');
        return 0;
    }
}
