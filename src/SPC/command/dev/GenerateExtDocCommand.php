<?php

declare(strict_types=1);

namespace SPC\command\dev;

use SPC\command\BaseCommand;
use SPC\store\FileSystem;
use SPC\util\ConfigValidator;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand('dev:gen-ext-docs', 'Generate extension list markdown', [], true)]
class GenerateExtDocCommand extends BaseCommand
{
    protected bool $no_motd = true;

    public function handle(): int
    {
        // Get ext.json
        $exts = json_decode(FileSystem::readFile(ROOT_DIR . '/config/ext.json'), true);
        ConfigValidator::validateExts($exts);
        // Markdown table needs format, we need to calculate the max length of each column
        $max_name = 0;
        $max_linux = 5;
        $max_macos = 5;
        $max_freebsd = 7;
        $max_windows = 7;
        $md_lines = [];
        foreach ($exts as $ext_name => $ext) {
            // notes is optional
            $name = ($ext['notes'] ?? false) === true ? "[{$ext_name}](./extension-notes#{$ext_name})" : $ext_name;
            // calculate max length
            $max_name = max($max_name, strlen($name));

            // linux
            $linux = match ($ext['support']['Linux'] ?? 'yes') {
                'wip' => '',
                default => $ext['support']['Linux'] ?? 'yes',
            };
            $max_linux = max($max_linux, strlen($linux));

            // macos
            $macos = match ($ext['support']['Darwin'] ?? 'yes') {
                'wip' => '',
                default => $ext['support']['Darwin'] ?? 'yes',
            };
            $max_macos = max($max_macos, strlen($macos));

            // freebsd
            $freebsd = match ($ext['support']['BSD'] ?? 'yes') {
                'wip' => '',
                default => $ext['support']['BSD'] ?? 'yes',
            };
            $max_freebsd = max($max_freebsd, strlen($freebsd));

            // windows
            $windows = match ($ext['support']['Windows'] ?? 'yes') {
                'wip' => '',
                default => $ext['support']['Windows'] ?? 'yes',
            };
            $max_windows = max($max_windows, strlen($windows));
            $md_lines[] = [
                $name,
                $linux,
                $macos,
                $freebsd,
                $windows,
            ];
        }

        // generate markdown
        $md = '| ' . str_pad('Extension Name', $max_name) . ' | ' . str_pad('Linux', $max_linux) . ' | ' . str_pad('macOS', $max_macos) . ' | ' . str_pad('FreeBSD', $max_freebsd) . ' | ' . str_pad('Windows', $max_windows) . ' |' . PHP_EOL;
        $md .= '| ' . str_repeat('-', $max_name) . ' | ' . str_repeat('-', $max_linux) . ' | ' . str_repeat('-', $max_macos) . ' | ' . str_repeat('-', $max_freebsd) . ' | ' . str_repeat('-', $max_windows) . ' |' . PHP_EOL;
        foreach ($md_lines as $line) {
            $md .= '| ' . str_pad($line[0], $max_name) . ' | ' . str_pad($line[1], $max_linux) . ' | ' . str_pad($line[2], $max_macos) . ' | ' . str_pad($line[3], $max_freebsd) . ' | ' . str_pad($line[4], $max_windows) . ' |' . PHP_EOL;
        }
        $this->output->writeln($md);
        return static::SUCCESS;
    }
}
