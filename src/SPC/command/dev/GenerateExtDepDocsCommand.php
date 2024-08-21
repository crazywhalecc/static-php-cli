<?php

declare(strict_types=1);

namespace SPC\command\dev;

use SPC\command\BaseCommand;
use SPC\store\FileSystem;
use SPC\util\ConfigValidator;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand('dev:gen-ext-dep-docs', 'Generate ext dependency map markdown', [], true)]
class GenerateExtDepDocsCommand extends BaseCommand
{
    protected bool $no_motd = true;

    public function handle(): int
    {
        // Get ext.json
        $exts = json_decode(FileSystem::readFile(ROOT_DIR . '/config/ext.json'), true);
        ConfigValidator::validateExts($exts);

        // Markdown table needs format, we need to calculate the max length of each column
        $content = '';

        // Calculate table column max length
        $max_linux = [0, 20, 19, 20, 19];
        $max_macos = [0, 20, 19, 20, 19];
        $max_windows = [0, 20, 19, 20, 19];
        $max_freebsd = [0, 20, 19, 20, 19];

        $md_lines_linux = [];
        $md_lines_macos = [];
        $md_lines_windows = [];
        $md_lines_freebsd = [];

        foreach ($exts as $ext_name => $ext) {
            $line_linux = [
                "<b>{$ext_name}</b>",
                implode('<br>', $ext['ext-depends-linux'] ?? $ext['ext-depends-unix'] ?? $ext['ext-depends'] ?? []),
                implode('<br>', $ext['ext-suggests-linux'] ?? $ext['ext-suggests-unix'] ?? $ext['ext-suggests'] ?? []),
                implode('<br>', $ext['lib-depends-linux'] ?? $ext['lib-depends-unix'] ?? $ext['lib-depends'] ?? []),
                implode('<br>', $ext['lib-suggests-linux'] ?? $ext['lib-suggests-unix'] ?? $ext['lib-suggests'] ?? []),
            ];
            $this->applyMaxLen($max_linux, $line_linux);
            if ($this->isSupported($ext, 'Linux') && !$this->isEmptyLine($line_linux)) {
                $md_lines_linux[] = $line_linux;
            }
            $line_macos = [
                "<b>{$ext_name}</b>",
                implode('<br>', $ext['ext-depends-macos'] ?? $ext['ext-depends-unix'] ?? $ext['ext-depends'] ?? []),
                implode('<br>', $ext['ext-suggests-macos'] ?? $ext['ext-suggests-unix'] ?? $ext['ext-suggests'] ?? []),
                implode('<br>', $ext['lib-depends-macos'] ?? $ext['lib-depends-unix'] ?? $ext['lib-depends'] ?? []),
                implode('<br>', $ext['lib-suggests-macos'] ?? $ext['lib-suggests-unix'] ?? $ext['lib-suggests'] ?? []),
            ];
            $this->applyMaxLen($max_macos, $line_macos);
            if ($this->isSupported($ext, 'macOS') && !$this->isEmptyLine($line_macos)) {
                $md_lines_macos[] = $line_macos;
            }
            $line_windows = [
                "<b>{$ext_name}</b>",
                implode('<br>', $ext['ext-depends-windows'] ?? $ext['ext-depends-win'] ?? $ext['ext-depends'] ?? []),
                implode('<br>', $ext['ext-suggests-windows'] ?? $ext['ext-suggests-win'] ?? $ext['ext-suggests'] ?? []),
                implode('<br>', $ext['lib-depends-windows'] ?? $ext['lib-depends-win'] ?? $ext['lib-depends'] ?? []),
                implode('<br>', $ext['lib-suggests-windows'] ?? $ext['lib-suggests-win'] ?? $ext['lib-suggests'] ?? []),
            ];
            $this->applyMaxLen($max_windows, $line_windows);
            if ($this->isSupported($ext, 'Windows') && !$this->isEmptyLine($line_windows)) {
                $md_lines_windows[] = $line_windows;
            }
            $line_freebsd = [
                "<b>{$ext_name}</b>",
                implode('<br>', $ext['ext-depends-freebsd'] ?? $ext['ext-depends-bsd'] ?? $ext['ext-depends-unix'] ?? $ext['ext-depends'] ?? []),
                implode('<br>', $ext['ext-suggests-freebsd'] ?? $ext['ext-suggests-bsd'] ?? $ext['ext-suggests-unix'] ?? $ext['ext-suggests'] ?? []),
                implode('<br>', $ext['lib-depends-freebsd'] ?? $ext['lib-depends-bsd'] ?? $ext['lib-depends-unix'] ?? $ext['lib-depends'] ?? []),
                implode('<br>', $ext['lib-suggests-freebsd'] ?? $ext['lib-suggests-bsd'] ?? $ext['lib-suggests-unix'] ?? $ext['lib-suggests'] ?? []),
            ];
            $this->applyMaxLen($max_freebsd, $line_freebsd);
            if ($this->isSupported($ext, 'BSD') && !$this->isEmptyLine($line_freebsd)) {
                $md_lines_freebsd[] = $line_freebsd;
            }
        }

        // Generate markdown
        if (!empty($md_lines_linux)) {
            $content .= "### Linux\n\n";
            $content .= '| ';
            $pads = ['Extension Name', 'Required Extensions', 'Suggested Extensions', 'Required Libraries', 'Suggested Libraries'];
            // 生成首行
            $content .= implode(' | ', array_map(fn ($i, $pad) => str_pad($pad, $max_linux[$i]), array_keys($pads), $pads));
            $content .= ' |' . PHP_EOL;
            // 生成第二行表格分割符 | --- | --- | --- | --- | --- |
            $content .= '| ';
            $content .= implode(' | ', array_map(fn ($i, $pad) => str_pad('', $max_linux[$i], '-'), array_keys($pads), $pads));
            $content .= ' |' . PHP_EOL;
            foreach ($md_lines_linux as $line) {
                $content .= '| ' . implode(' | ', array_map(fn ($i, $pad) => str_pad($line[$i], $max_linux[$i]), array_keys($line), $line)) . ' |' . PHP_EOL;
            }
        }
        if (!empty($md_lines_macos)) {
            $content .= "\n\n### macOS\n\n";
            $content .= '| ';
            $pads = ['Extension Name', 'Required Extensions', 'Suggested Extensions', 'Required Libraries', 'Suggested Libraries'];
            // 生成首行
            $content .= implode(' | ', array_map(fn ($i, $pad) => str_pad($pad, $max_macos[$i]), array_keys($pads), $pads));
            $content .= ' |' . PHP_EOL;
            // 生成第二行表格分割符 | --- | --- | --- | --- | --- |
            $content .= '| ';
            $content .= implode(' | ', array_map(fn ($i, $pad) => str_pad('', $max_macos[$i], '-'), array_keys($pads), $pads));
            $content .= ' |' . PHP_EOL;
            foreach ($md_lines_macos as $line) {
                $content .= '| ' . implode(' | ', array_map(fn ($i, $pad) => str_pad($line[$i], $max_macos[$i]), array_keys($line), $line)) . ' |' . PHP_EOL;
            }
        }
        if (!empty($md_lines_windows)) {
            $content .= "\n\n### Windows\n\n";
            $content .= '| ';
            $pads = ['Extension Name', 'Required Extensions', 'Suggested Extensions', 'Required Libraries', 'Suggested Libraries'];
            // 生成首行
            $content .= implode(' | ', array_map(fn ($i, $pad) => str_pad($pad, $max_windows[$i]), array_keys($pads), $pads));
            $content .= ' |' . PHP_EOL;
            // 生成第二行表格分割符 | --- | --- | --- | --- | --- |
            $content .= '| ';
            $content .= implode(' | ', array_map(fn ($i, $pad) => str_pad('', $max_windows[$i], '-'), array_keys($pads), $pads));
            $content .= ' |' . PHP_EOL;
            foreach ($md_lines_windows as $line) {
                $content .= '| ' . implode(' | ', array_map(fn ($i, $pad) => str_pad($line[$i], $max_windows[$i]), array_keys($line), $line)) . ' |' . PHP_EOL;
            }
        }
        if (!empty($md_lines_freebsd)) {
            $content .= "\n\n### FreeBSD\n\n";
            $content .= '| ';
            $pads = ['Extension Name', 'Required Extensions', 'Suggested Extensions', 'Required Libraries', 'Suggested Libraries'];
            // 生成首行
            $content .= implode(' | ', array_map(fn ($i, $pad) => str_pad($pad, $max_freebsd[$i]), array_keys($pads), $pads));
            $content .= ' |' . PHP_EOL;
            // 生成第二行表格分割符 | --- | --- | --- | --- | --- |
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

    private function isSupported(array $ext, string $os): bool
    {
        return !in_array($ext['support'][$os] ?? 'yes', ['no', 'wip']);
    }

    private function isEmptyLine(array $line): bool
    {
        return $line[1] === '' && $line[2] === '' && $line[3] === '' && $line[4] === '';
    }
}
