<?php

declare(strict_types=1);

namespace SPC\command\dev;

use Symfony\Component\Console\Attribute\AsCommand;

use function Laravel\Prompts\select;
use function Laravel\Prompts\text;
use function Laravel\Prompts\textarea;

#[AsCommand('dev:source-skel', 'Generate source skeleton', ['source-skel'])]
class SourceSkeletonCommand extends SkeletonCommand
{
    public function handle(): int
    {
        // Get extension name
        $source_name = $this->input->getArgument('name');
        $result = [];

        // select a source type
        $source_type = select("Please select source [{$source_name}] download method", [
            'url' => 'Direct URL (e.g. http://a.com/file.zip)',
            'git' => 'Git (e.g. https://github.com/user/repo.git)',
            'filelist' => 'Crawl from web server index (filelist)',
            'ghtar' => 'GitHub Release Tarball',
            'ghtagtar' => 'GitHub Tag Tarball',
            'ghrel' => 'GitHub Release asset',
        ], default: 'external', scroll: 6, required: true);

        $result['type'] = $source_type;

        switch ($source_type) {
            case 'url':
                $result['url'] = text('Please enter source URL', required: true, validate: fn ($x) => filter_var($x, FILTER_VALIDATE_URL) ? null : 'Invalid URL');
                break;
            case 'git':
                $result['rev'] = text('Please enter git branch name', default: 'main', required: true);
                $result['url'] = text('Please enter git URL', required: true, validate: fn ($x) => filter_var($x, FILTER_VALIDATE_URL) ? null : 'Invalid URL');
                break;
            case 'filelist':
                $result['url'] = text('Please enter filelist fetch URL', required: true, validate: fn ($x) => filter_var($x, FILTER_VALIDATE_URL) ? null : 'Invalid URL');
                $result['regex'] = text('Please enter match file regex', default: '/href="(?<file>' . $source_name . '-(?<version>[^"]+)\.tar\.gz)"/', required: true, hint: 'Regex must contain named groups "file" and "version"');
                break;
            case 'ghtar':
            case 'ghtagtar':
                $result['repo'] = text('Please enter GitHub repo name (e.g. phpredis/phpredis)', required: true, validate: fn ($x) => preg_match('/^[a-zA-Z0-9_]+\/[a-zA-Z0-9_]+$/', $x) ? null : 'Invalid repo name');
                break;
            case 'ghrel':
                $result['repo'] = text('Please enter GitHub repo name (e.g. phpredis/phpredis)', required: true, validate: fn ($x) => preg_match('/^[a-zA-Z0-9_]+\/[a-zA-Z0-9_]+$/', $x) ? null : 'Invalid repo name');
                $result['match'] = text('Please enter regex to match asset name', default: $source_name . '.+\.tar\.gz', required: true);
                break;
        }

        // select license
        $license = select("Please select source [{$source_name}] license", [
            'none' => 'None',
            'file-license' => '"LICENSE" file in source root',
            'file-copying' => '"COPYING" file in source root',
            'custom-file' => 'Custom file in source root',
            'custom-text' => 'Custom text',
        ], default: 'none', required: true);
        switch ($license) {
            case 'file-license':
                $result['license']['type'] = 'file';
                $result['license']['path'] = 'LICENSE';
                break;
            case 'file-copying':
                $result['license']['type'] = 'file';
                $result['license']['path'] = 'copying';
                break;
            case 'custom-file':
                $result['license']['type'] = 'file';
                $result['license']['path'] = text('Please enter custom license file name', required: true, hint: 'File name must be relative to source root, e.g. LICENSE.txt');
                break;
            case 'custom-text':
                $result['license']['type'] = 'text';
                $result['license']['text'] = textarea('Please enter custom license text', required: true);
                break;
            case 'none':
                $result['license']['type'] = 'text';
                $result['license']['text'] = 'No license';
                break;
        }

        // Select extension support
        $this->output->writeln("<info>Source {$source_name} added!</info>");
        $this->output->writeln('<info>' . json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . '</info>');
        SkeletonCommand::$cache['source'][$source_name] = $result;
        if (!$this->getOption('is-middle-step')) {
            // write source to config
        }
        return static::SUCCESS;
    }
}
