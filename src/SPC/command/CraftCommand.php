<?php

declare(strict_types=1);

namespace SPC\command;

use SPC\exception\ValidationException;
use SPC\store\pkg\GoXcaddy;
use SPC\store\pkg\Zig;
use SPC\toolchain\ToolchainManager;
use SPC\toolchain\ZigToolchain;
use SPC\util\ConfigValidator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Process\Process;

#[AsCommand('craft', 'Build static-php from craft.yml')]
class CraftCommand extends BuildCommand
{
    public function configure(): void
    {
        $this->addArgument('craft', null, 'Path to craft.yml file', WORKING_DIR . '/craft.yml');
    }

    public function handle(): int
    {
        $craft_file = $this->getArgument('craft');
        // Check if the craft.yml file exists
        if (!file_exists($craft_file)) {
            $this->output->writeln('<error>craft.yml not found, please create one!</error>');
            return static::FAILURE;
        }

        // Check if the craft.yml file is valid
        try {
            $craft = ConfigValidator::validateAndParseCraftFile($craft_file, $this);
            if ($craft['debug']) {
                $this->input->setOption('debug', true);
            }
        } catch (ValidationException $e) {
            $this->output->writeln('<error>craft.yml parse error: ' . $e->getMessage() . '</error>');
            return static::FAILURE;
        }

        // Craft!!!
        $this->output->writeln('<info>Crafting...</info>');

        // apply env
        if (isset($craft['extra-env'])) {
            $env = $craft['extra-env'];
            foreach ($env as $key => $val) {
                f_putenv("{$key}={$val}");
            }
        }

        $static_extensions = implode(',', $craft['extensions']);
        $shared_extensions = implode(',', $craft['shared-extensions'] ?? []);
        $libs = implode(',', $craft['libs']);

        // craft doctor
        if ($craft['craft-options']['doctor']) {
            $retcode = $this->runCommand('doctor', '--auto-fix');
            if ($retcode !== 0) {
                $this->output->writeln('<error>craft doctor failed</error>');
                return static::FAILURE;
            }
        }
        // install go and xcaddy for frankenphp
        if (in_array('frankenphp', $craft['sapi']) && !GoXcaddy::isInstalled()) {
            $retcode = $this->runCommand('install-pkg', 'go-xcaddy');
            if ($retcode !== 0) {
                $this->output->writeln('<error>craft go-xcaddy failed</error>');
                return static::FAILURE;
            }
        }
        // install zig if requested
        if (ToolchainManager::getToolchainClass() === ZigToolchain::class && !Zig::isInstalled()) {
            $retcode = $this->runCommand('install-pkg', 'zig');
            if ($retcode !== 0) {
                $this->output->writeln('<error>craft zig failed</error>');
                return static::FAILURE;
            }
        }
        // craft download
        if ($craft['craft-options']['download']) {
            $sharedAppend = $shared_extensions ? ',' . $shared_extensions : '';
            $args = ["--for-extensions={$static_extensions}{$sharedAppend}"];
            if ($craft['libs'] !== []) {
                $args[] = "--for-libs={$libs}";
            }
            if (isset($craft['php-version'])) {
                $args[] = '--with-php=' . $craft['php-version'];
                if (!array_key_exists('ignore-cache-sources', $craft['download-options'])) {
                    $craft['download-options']['ignore-cache-sources'] = 'php-src';
                }
            }
            $this->optionsToArguments($craft['download-options'], $args);
            $retcode = $this->runCommand('download', ...$args);
            if ($retcode !== 0) {
                $this->output->writeln('<error>craft download failed</error>');
                return static::FAILURE;
            }
        }

        // craft build
        if ($craft['craft-options']['build']) {
            $args = [$static_extensions, "--with-libs={$libs}", "--build-shared={$shared_extensions}", ...array_map(fn ($x) => "--build-{$x}", $craft['sapi'])];
            $this->optionsToArguments($craft['build-options'], $args);
            $retcode = $this->runCommand('build', ...$args);
            if ($retcode !== 0) {
                $this->output->writeln('<error>craft build failed</error>');
                return static::FAILURE;
            }
        }

        return 0;
    }

    public function processLogCallback($type, $buffer): void
    {
        if ($type === Process::ERR) {
            fwrite(STDERR, $buffer);
        } else {
            fwrite(STDOUT, $buffer);
        }
    }

    private function runCommand(string $cmd, ...$args): int
    {
        global $argv;
        if ($this->getOption('debug')) {
            array_unshift($args, '--debug');
        }
        array_unshift($args, '--preserve-log');
        $prefix = PHP_SAPI === 'cli' ? [PHP_BINARY, $argv[0]] : [$argv[0]];

        $env = getenv();
        $process = new Process([...$prefix, $cmd, '--no-motd', ...$args], env: $env, timeout: null);

        if (PHP_OS_FAMILY === 'Windows') {
            sapi_windows_set_ctrl_handler(function () use ($process) {
                if ($process->isRunning()) {
                    $process->signal(-1073741510);
                }
            });
        } elseif (extension_loaded('pcntl')) {
            pcntl_signal(SIGINT, function () use ($process) {
                /* @noinspection PhpComposerExtensionStubsInspection */
                $process->signal(SIGINT);
            });
        } else {
            logger()->debug('You have not enabled `pcntl` extension, cannot prevent download file corruption when Ctrl+C');
        }
        // $process->setTty(true);
        $process->run([$this, 'processLogCallback']);
        return $process->getExitCode();
    }

    private function optionsToArguments(array $options, array &$args): void
    {
        foreach ($options as $option => $val) {
            if ((is_bool($val) && $val) || $val === null) {
                $args[] = "--{$option}";

                continue;
            }
            if (is_string($val)) {
                $args[] = "--{$option}={$val}";

                continue;
            }
            if (is_array($val)) {
                foreach ($val as $v) {
                    if (is_string($v)) {
                        $args[] = "--{$option}={$v}";
                    }
                }
            }
        }
    }
}
