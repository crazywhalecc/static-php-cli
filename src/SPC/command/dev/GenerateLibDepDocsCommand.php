<?php

declare(strict_types=1);

namespace SPC\command\dev;

use SPC\command\BaseCommand;
use SPC\store\Config;
use SPC\store\FileSystem;
use SPC\util\ConfigValidator;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand('dev:gen-lib-dep-docs', 'Generate lib dependency map markdown', [], true)]
class GenerateLibDepDocsCommand extends BaseCommand
{
    protected bool $no_motd = true;

    protected array $support_lib_list = [];

    public function handle(): int
    {
        foreach (['linux', 'macos', 'windows', 'freebsd'] as $os) {
            $this->support_lib_list[$os] = [];
            $classes = FileSystem::getClassesPsr4(
                FileSystem::convertPath(ROOT_DIR . '/src/SPC/builder/' . $os . '/library'),
                'SPC\builder\\' . $os . '\library'
            );
            foreach ($classes as $class) {
                if (defined($class . '::NAME') && $class::NAME !== 'unknown' && Config::getLib($class::NAME) !== null) {
                    $this->support_lib_list[$os][$class::NAME] = $class;
                }
            }
        }

        // Get lib.json
        $libs = json_decode(FileSystem::readFile(ROOT_DIR . '/config/lib.json'), true);
        ConfigValidator::validateLibs($libs);

        // Markdown table needs format, we need to calculate the max length of each column
        $content = '';

        // Calculate table column max length
        $max_linux = [0, 20, 19];
        $max_macos = [0, 20, 19];
        $max_windows = [0, 20, 19];
        $max_freebsd = [0, 20, 19];

        $md_lines_linux = [];
        $md_lines_macos = [];
        $md_lines_windows = [];
        $md_lines_freebsd = [];

        foreach ($libs as $lib_name => $lib) {
            $line_linux = [
                "<b>{$lib_name}</b>",
                implode('<br>', $lib['lib-depends-linux'] ?? $lib['lib-depends-unix'] ?? $lib['lib-depends'] ?? []),
                implode('<br>', $lib['lib-suggests-linux'] ?? $lib['lib-suggests-unix'] ?? $lib['lib-suggests'] ?? []),
            ];
            $this->applyMaxLen($max_linux, $line_linux);
            if ($this->isSupported($lib_name, 'linux') && !$this->isEmptyLine($line_linux)) {
                $md_lines_linux[] = $line_linux;
            }
            $line_macos = [
                "<b>{$lib_name}</b>",
                implode('<br>', $lib['lib-depends-macos'] ?? $lib['lib-depends-unix'] ?? $lib['lib-depends'] ?? []),
                implode('<br>', $lib['lib-suggests-macos'] ?? $lib['lib-suggests-unix'] ?? $lib['lib-suggests'] ?? []),
            ];
            $this->applyMaxLen($max_macos, $line_macos);
            if ($this->isSupported($lib_name, 'macos') && !$this->isEmptyLine($line_macos)) {
                $md_lines_macos[] = $line_macos;
            }
            $line_windows = [
                "<b>{$lib_name}</b>",
                implode('<br>', $lib['lib-depends-windows'] ?? $lib['lib-depends-win'] ?? $lib['lib-depends'] ?? []),
                implode('<br>', $lib['lib-suggests-windows'] ?? $lib['lib-suggests-win'] ?? $lib['lib-suggests'] ?? []),
            ];
            $this->applyMaxLen($max_windows, $line_windows);
            if ($this->isSupported($lib_name, 'windows') && !$this->isEmptyLine($line_windows)) {
                $md_lines_windows[] = $line_windows;
            }
            $line_freebsd = [
                "<b>{$lib_name}</b>",
                implode('<br>', $lib['lib-depends-freebsd'] ?? $lib['lib-depends-bsd'] ?? $lib['lib-depends-unix'] ?? $lib['lib-depends'] ?? []),
                implode('<br>', $lib['lib-suggests-freebsd'] ?? $lib['lib-suggests-bsd'] ?? $lib['lib-suggests-unix'] ?? $lib['lib-suggests'] ?? []),
            ];
            $this->applyMaxLen($max_freebsd, $line_freebsd);
            if ($this->isSupported($lib_name, 'freebsd') && !$this->isEmptyLine($line_freebsd)) {
                $md_lines_freebsd[] = $line_freebsd;
            }
        }

        // Generate markdown
        if (!empty($md_lines_linux)) {
            $content .= "### Linux\n\n";
            $content .= '| ';
            $pads = ['Library Name', 'Required Libraries', 'Suggested Libraries'];
            $content .= implode(' | ', array_map(fn ($i, $pad) => str_pad($pad, $max_linux[$i]), array_keys($pads), $pads));
            $content .= ' |' . PHP_EOL;
            $content .= '| ';
            $content .= implode(' | ', array_map(fn ($i, $pad) => str_pad('', $max_linux[$i], '-'), array_keys($pads), $pads));
            $content .= ' |' . PHP_EOL;
            foreach ($md_lines_linux as $line) {
                $content .= '| ' . implode(' | ', array_map(fn ($i, $pad) => str_pad($line[$i], $max_linux[$i]), array_keys($line), $line)) . ' |' . PHP_EOL;
            }
        }

        if (!empty($md_lines_macos)) {
            $content .= "### macOS\n\n";
            $content .= '| ';
            $pads = ['Library Name', 'Required Libraries', 'Suggested Libraries'];
            $content .= implode(' | ', array_map(fn ($i, $pad) => str_pad($pad, $max_macos[$i]), array_keys($pads), $pads));
            $content .= ' |' . PHP_EOL;
            $content .= '| ';
            $content .= implode(' | ', array_map(fn ($i, $pad) => str_pad('', $max_macos[$i], '-'), array_keys($pads), $pads));
            $content .= ' |' . PHP_EOL;
            foreach ($md_lines_macos as $line) {
                $content .= '| ' . implode(' | ', array_map(fn ($i, $pad) => str_pad($line[$i], $max_macos[$i]), array_keys($line), $line)) . ' |' . PHP_EOL;
            }
        }

        if (!empty($md_lines_windows)) {
            $content .= "### Windows\n\n";
            $content .= '| ';
            $pads = ['Library Name', 'Required Libraries', 'Suggested Libraries'];
            $content .= implode(' | ', array_map(fn ($i, $pad) => str_pad($pad, $max_windows[$i]), array_keys($pads), $pads));
            $content .= ' |' . PHP_EOL;
            $content .= '| ';
            $content .= implode(' | ', array_map(fn ($i, $pad) => str_pad('', $max_windows[$i], '-'), array_keys($pads), $pads));
            $content .= ' |' . PHP_EOL;
            foreach ($md_lines_windows as $line) {
                $content .= '| ' . implode(' | ', array_map(fn ($i, $pad) => str_pad($line[$i], $max_windows[$i]), array_keys($line), $line)) . ' |' . PHP_EOL;
            }
        }

        if (!empty($md_lines_freebsd)) {
            $content .= "### FreeBSD\n\n";
            $content .= '| ';
            $pads = ['Library Name', 'Required Libraries', 'Suggested Libraries'];
            $content .= implode(' | ', array_map(fn ($i, $pad) => str_pad($pad, $max_freebsd[$i]), array_keys($pads), $pads));
            $content .= ' |' . PHP_EOL;
            $content .= '| ';
            $content .= implode(' | ', array_map(fn ($i, $pad) => str_pad('', $max_freebsd[$i], '-'), array_keys($pads), $pads));
            $content .= ' |' . PHP_EOL;
            foreach ($md_lines_freebsd as $line) {
                $content .= '| ' . implode(' | ', array_map(fn ($i, $pad) => str_pad($line[$i], $max_freebsd[$i]), array_keys($line), $line)) . ' |' . PHP_EOL;
            }
        }

        $this->output->writeln($content);
        return static::SUCCESS;
    }

    private function applyMaxLen(array &$max, array $lines): void
    {
        foreach ($max as $k => $v) {
            $max[$k] = max($v, strlen($lines[$k]));
        }
    }

    private function isSupported(string $ext_name, string $os): bool
    {
        if (!in_array($os, ['linux', 'macos', 'freebsd', 'windows'])) {
            throw new \InvalidArgumentException('Invalid os: ' . $os);
        }
        return isset($this->support_lib_list[$os][$ext_name]);
    }

    private function isEmptyLine(array $line): bool
    {
        return $line[1] === '' && $line[2] === '';
    }
}
