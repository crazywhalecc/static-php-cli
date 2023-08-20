<?php

declare(strict_types=1);

namespace SPC\command;

use SPC\store\FileSystem;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\text;

#[AsCommand('deploy', 'Deploy static-php-cli self to an .phar application')]
class DeployCommand extends BaseCommand
{
    public function configure(): void
    {
        $this->addArgument('target', InputArgument::OPTIONAL, 'The file or directory to pack.');
        $this->addOption('auto-phar-fix', null, InputOption::VALUE_NONE, 'Automatically fix ini option.');
        $this->addOption('overwrite', 'W', InputOption::VALUE_NONE, 'Overwrite existing files.');
        $this->addOption('with-no-dev', 'D', InputOption::VALUE_NONE, 'Automatically use non-dev composer dependencies to reduce size');
        $this->addOption('with-dev', 'd', InputOption::VALUE_NONE, 'Automatically use dev composer dependencies');
    }

    /**
     * @throws \PharException
     */
    public function handle(): int
    {
        $composer = require ROOT_DIR . '/vendor/composer/installed.php';
        if (($composer['root']['dev'] ?? false) === true) {
            if (!$this->getOption('with-no-dev')) {
                $this->output->writeln('<comment>Current static-php-cli dependencies have installed dev-dependencies</comment>');
                $this->output->writeln('<comment>If you want to remove, you can choose "Yes" to run command "composer update --no-dev" to remove.</comment>');
                $this->output->writeln('<comment>Or choose "No", just pack, deploy.</comment>');
                $ask = confirm('Do you want to remove dev-dependencies to reduce size of phar file?');
            } elseif (!$this->getOption('with-dev')) {
                $ask = true;
            } else {
                $ask = false;
            }
            if ($ask) {
                [$code] = shell()->execWithResult('composer update --no-dev');
                if ($code !== 0) {
                    $this->output->writeln('<error>"composer update --no-dev" failed with exit code [' . $code . ']</error>');
                    $this->output->writeln('<error>You may need to run this command by your own.</error>');
                    return static::FAILURE;
                }
                $this->output->writeln('<info>Update successfully, you need to re-run deploy command to pack.</info>');
                return static::SUCCESS;
            }
        }
        // 首先得确认是不是关闭了readonly模式
        if (ini_get('phar.readonly') == 1) {
            if ($this->getOption('auto-phar-fix')) {
                $ask = true;
            } else {
                $this->output->writeln('<comment>pack command needs "phar.readonly" = "Off" !</comment>');
                $ask = confirm('Do you want to automatically set it and continue ?');
                // $ask = $prompt->requireBool('<comment>pack command needs "phar.readonly" = "Off" !</comment>' . PHP_EOL . 'If you want to automatically set it and continue, just Enter', true);
            }
            if ($ask) {
                global $argv;
                $args = array_merge(['-d', 'phar.readonly=0'], $_SERVER['argv'], ['--no-motd']);
                if (function_exists('pcntl_exec')) {
                    $this->output->writeln('<info>Changing to phar.readonly=0 mode ...</info>');
                    if (pcntl_exec(PHP_BINARY, $args) === false) {
                        throw new \PharException('Switching to read write mode failed, please check the environment.');
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
        $phar_path = text('Please input the phar target filename', default: '/tmp/static-php-cli.phar');
        // $phar_path = $prompt->requireArgument('target', 'Please input the phar target filename', 'static-php-cli.phar');

        if (FileSystem::isRelativePath($phar_path)) {
            $phar_path = WORKING_DIR . '/' . $phar_path;
        }
        if (file_exists($phar_path)) {
            if (!$this->getOption('overwrite')) {
                $this->output->writeln('<comment>The file "' . $phar_path . '" already exists.</comment>');
                $ask = confirm('Do you want to overwrite it?');
            } else {
                $ask = true;
            }
            if (!$ask) {
                $this->output->writeln('<comment>User canceled.</comment>');
                return static::FAILURE;
            }
            @unlink($phar_path);
        }
        $phar = new \Phar($phar_path);
        $phar->startBuffering();

        $all = FileSystem::scanDirFiles($path, true, true);

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

    private function progress(): ProgressBar
    {
        $progress = new ProgressBar($this->output, 0);
        $progress->setBarCharacter('<fg=green>⚬</>');
        $progress->setEmptyBarCharacter('<fg=red>⚬</>');
        $progress->setProgressCharacter('<fg=green>➤</>');
        $progress->setFormat(
            "%current%/%max% [%bar%] %percent:3s%%\n🪅 %estimated:-20s%  %memory:20s%" . PHP_EOL
        );
        return $progress;
    }
}
