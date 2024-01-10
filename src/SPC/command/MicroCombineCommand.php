<?php

declare(strict_types=1);

namespace SPC\command;

use SPC\store\FileSystem;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand('micro:combine', 'Combine micro.sfx and php code together')]
class MicroCombineCommand extends BaseCommand
{
    public function configure(): void
    {
        $this->addArgument('file', InputArgument::REQUIRED, 'The php or phar file to be combined');
        $this->addOption('with-micro', 'M', InputOption::VALUE_REQUIRED, 'Customize your micro.sfx file');
        $this->addOption('with-ini-set', 'I', InputOption::VALUE_IS_ARRAY | InputOption::VALUE_REQUIRED, 'ini to inject into micro.sfx when combining');
        $this->addOption('with-ini-file', 'N', InputOption::VALUE_REQUIRED, 'ini file to inject into micro.sfx when combining');
        $this->addOption('output', 'O', InputOption::VALUE_REQUIRED, 'Customize your output binary file name');
    }

    public function handle(): int
    {
        // 0. Initialize path variables
        $internal = FileSystem::convertPath(BUILD_ROOT_PATH . '/bin/micro.sfx');
        $micro_file = $this->input->getOption('with-micro');
        $file = $this->getArgument('file');
        $ini_set = $this->input->getOption('with-ini-set');
        $ini_file = $this->input->getOption('with-ini-file');
        $target_ini = [];
        $output = $this->input->getOption('output') ?? 'my-app';
        $ini_part = '';
        // 1. Make sure specified micro.sfx file exists
        if ($micro_file !== null && !file_exists($micro_file)) {
            $this->output->writeln('<error>The micro.sfx file you specified is incorrect or does not exist!</error>');
            return static::FAILURE;
        }
        // 2. Make sure buildroot/bin/micro.sfx exists
        if ($micro_file === null && !file_exists($internal)) {
            $this->output->writeln('<error>You haven\'t compiled micro.sfx yet, please use "build" command and "--build-micro" to compile phpmicro first!</error>');
            return static::FAILURE;
        }
        // 3. Use buildroot/bin/micro.sfx
        if ($micro_file === null) {
            $micro_file = $internal;
        }
        // 4. Make sure php or phar file exists
        if (!is_file(FileSystem::convertPath($file))) {
            $this->output->writeln('<error>The file to combine does not exist!</error>');
            return static::FAILURE;
        }
        // 5. Confirm ini files (ini-set has higher priority)
        if ($ini_file !== null) {
            // Check file exist first
            if (!file_exists($ini_file)) {
                $this->output->writeln('<error>The ini file to combine does not exist! (' . $ini_file . ')</error>');
                return static::FAILURE;
            }
            $arr = parse_ini_file($ini_file);
            if ($arr === false) {
                $this->output->writeln('<error>Cannot parse ini file</error>');
                return static::FAILURE;
            }
            $target_ini = array_merge($target_ini, $arr);
        }
        // 6. Confirm ini sets
        if ($ini_set !== []) {
            foreach ($ini_set as $item) {
                $arr = parse_ini_string($item);
                if ($arr === false) {
                    $this->output->writeln('<error>--with-ini-set parse failed</error>');
                    return static::FAILURE;
                }
                $target_ini = array_merge($target_ini, $arr);
            }
        }
        // 7. Generate ini injection parts
        if (!empty($target_ini)) {
            $ini_str = $this->encodeINI($target_ini);
            logger()->debug('Injecting ini parts: ' . PHP_EOL . $ini_str);
            $ini_part = "\xfd\xf6\x69\xe6";
            $ini_part .= pack('N', strlen($ini_str));
            $ini_part .= $ini_str;
        }
        // 8. Combine !
        $output = FileSystem::isRelativePath($output) ? (WORKING_DIR . '/' . $output) : $output;
        $file_target = file_get_contents($micro_file) . $ini_part . file_get_contents($file);
        if (PHP_OS_FAMILY === 'Windows' && !str_ends_with(strtolower($output), '.exe')) {
            $output .= '.exe';
        }
        $output = FileSystem::convertPath($output);
        $result = file_put_contents($output, $file_target);
        if ($result === false) {
            $this->output->writeln('<error>Combine failed.</error>');
            return static::FAILURE;
        }
        // 9. chmod +x
        chmod($output, 0755);
        $this->output->writeln('<info>Combine success! Binary file: ' . $output . '</info>');
        return static::SUCCESS;
    }

    private function encodeINI(array $array): string
    {
        $res = [];
        foreach ($array as $key => $val) {
            if (is_array($val)) {
                $res[] = "[{$key}]";
                foreach ($val as $skey => $sval) {
                    $res[] = "{$skey}=" . (is_numeric($sval) ? $sval : '"' . $sval . '"');
                }
            } else {
                $res[] = "{$key}=" . (is_numeric($val) ? $val : '"' . $val . '"');
            }
        }
        return implode("\n", $res);
    }
}
