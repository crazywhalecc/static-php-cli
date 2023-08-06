<?php

declare(strict_types=1);

namespace SPC\command;

use CliHelper\Tools\ArgFixer;
use CliHelper\Tools\DataProvider;
use CliHelper\Tools\SeekableArrayIterator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand('deploy', 'Deploy static-php-cli self to an .phar application')]
class DeployCommand extends BaseCommand
{
    public function configure()
    {
        $this->addArgument('target', InputArgument::OPTIONAL, 'The file or directory to pack.');
        $this->addOption('auto-phar-fix', null, InputOption::VALUE_NONE, 'Automatically fix ini option.');
        $this->addOption('overwrite', 'W', InputOption::VALUE_NONE, 'Overwrite existing files.');
    }

    public function handle(): int
    {
        // 第一阶段流程：如果没有写path，将会提示输入要打包的path
        $prompt = new ArgFixer($this->input, $this->output);
        // 首先得确认是不是关闭了readonly模式
        if (ini_get('phar.readonly') == 1) {
            if ($this->getOption('auto-phar-fix')) {
                $ask = true;
            } else {
                $ask = $prompt->requireBool('<comment>pack command needs "phar.readonly" = "Off" !</comment>' . PHP_EOL . 'If you want to automatically set it and continue, just Enter', true);
            }
            if ($ask) {
                global $argv;
                $args = array_merge(['-d', 'phar.readonly=0'], $_SERVER['argv']);
                if (function_exists('pcntl_exec')) {
                    $this->output->writeln('<info>Changing to phar.readonly=0 mode ...</info>');
                    if (pcntl_exec(PHP_BINARY, $args) === false) {
                        throw new \PharException('切换到读写模式失败，请检查环境。');
                    }
                } else {
                    $this->output->writeln('<info>Now running command in child process.</info>');
                    passthru(PHP_BINARY . ' -d phar.readonly=0 ' . implode(' ', $argv), $retcode);
                    exit($retcode);
                }
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
            $ask = $this->getOption('overwrite') ? true : $prompt->requireBool('<comment>The file "' . $phar_path . '" already exists, do you want to overwrite it?</comment>' . PHP_EOL . 'If you want to, just Enter');
            if (!$ask) {
                $this->output->writeln('<comment>User canceled.</comment>');
                return static::FAILURE;
            }
            @unlink($phar_path);
        }
        $phar = new \Phar($phar_path);
        $phar->startBuffering();

        $all = DataProvider::scanDirFiles($path, true, true);

        $all = array_filter($all, function ($x) {
            $dirs = preg_match('/(^(config|src|vendor)\\/|^(composer\\.json|README\\.md|source\\.json|LICENSE|README-en\\.md)$)/', $x);
            return !($dirs !== 1);
        });
        sort($all);
        $map = [];
        foreach ($all as $v) {
            $map[$v] = $path . '/' . $v;
        }

        $this->output->writeln('<info>Start packing files...</info>');
        try {
            foreach ($this->progress()->iterate($map) as $file => $origin_file) {
                $phar->addFromString($file, php_strip_whitespace($origin_file));
            }
            // $phar->buildFromIterator(new SeekableArrayIterator($map, new ProgressBar($output)));
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
            $this->output->writeln($e);
            return static::FAILURE;
        }
        $phar->addFromString('.prod', 'true');
        $phar->stopBuffering();
        $this->output->writeln(PHP_EOL . 'Done! Phar file is generated at "' . $phar_path . '".');
        if (file_exists(SOURCE_PATH . '/php-src/sapi/micro/micro.sfx')) {
            $this->output->writeln('Detected you have already compiled micro binary, I will make executable now for you!');
            file_put_contents(
                pathinfo($phar_path, PATHINFO_DIRNAME) . '/spc',
                file_get_contents(SOURCE_PATH . '/php-src/sapi/micro/micro.sfx') .
                file_get_contents($phar_path)
            );
            chmod(pathinfo($phar_path, PATHINFO_DIRNAME) . '/spc', 0755);
            $this->output->writeln('<info>Binary Executable: ' . pathinfo($phar_path, PATHINFO_DIRNAME) . '/spc</info>');
        }
        chmod($phar_path, 0755);
        $this->output->writeln('<info>Phar Executable: ' . $phar_path . '</info>');
        return static::SUCCESS;
    }

    private function progress(int $max = 0): ProgressBar
    {
        $progress = new ProgressBar($this->output, $max);
        $progress->setBarCharacter('<fg=green>⚬</>');
        $progress->setEmptyBarCharacter('<fg=red>⚬</>');
        $progress->setProgressCharacter('<fg=green>➤</>');
        $progress->setFormat(
            "%current%/%max% [%bar%] %percent:3s%%\n🪅 %estimated:-20s%  %memory:20s%" . PHP_EOL
        );
        return $progress;
    }
}
