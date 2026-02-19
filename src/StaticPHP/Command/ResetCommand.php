<?php

declare(strict_types=1);

namespace StaticPHP\Command;

use StaticPHP\Util\FileSystem;
use StaticPHP\Util\InteractiveTerm;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

use function Laravel\Prompts\confirm;

#[AsCommand('reset')]
class ResetCommand extends BaseCommand
{
    public function configure(): void
    {
        $this->setDescription('Reset and clean build directories')
            ->addOption('with-pkgroot', null, InputOption::VALUE_NONE, 'Also remove pkgroot directory')
            ->addOption('with-download', null, InputOption::VALUE_NONE, 'Also remove downloads directory')
            ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Skip confirmation prompt');
    }

    public function handle(): int
    {
        $dirs_to_remove = [
            'buildroot' => BUILD_ROOT_PATH,
            'source' => SOURCE_PATH,
        ];

        if ($this->input->getOption('with-pkgroot')) {
            $dirs_to_remove['pkgroot'] = PKG_ROOT_PATH;
        }

        if ($this->input->getOption('with-download')) {
            $dirs_to_remove['downloads'] = DOWNLOAD_PATH;
        }

        // Show warning
        InteractiveTerm::notice('You are doing some operations that are not recoverable:');
        foreach ($dirs_to_remove as $name => $path) {
            InteractiveTerm::notice("- Removing directory: {$path}");
        }

        // Confirm with user unless --yes is specified
        if (!$this->input->getOption('yes')) {
            if (!confirm('Are you sure you want to continue?', false)) {
                InteractiveTerm::error(message: 'Reset operation cancelled.');
                return static::SUCCESS;
            }
        }

        // Remove directories
        foreach ($dirs_to_remove as $name => $path) {
            if (!is_dir($path)) {
                InteractiveTerm::notice("Directory {$name} does not exist, skipping: {$path}");
                continue;
            }

            InteractiveTerm::indicateProgress("Removing: {$path}");

            if (PHP_OS_FAMILY === 'Windows') {
                // Force delete on Windows to handle git directories
                $this->removeDirectoryWindows($path);
            } else {
                // Use FileSystem::removeDir for Unix systems
                FileSystem::removeDir($path);
            }

            InteractiveTerm::finish("Removed: {$path}");
        }

        InteractiveTerm::notice('Reset completed.');
        return static::SUCCESS;
    }

    /**
     * Force remove directory on Windows
     * Uses PowerShell to handle git directories and other problematic files
     *
     * @param string $path Directory path to remove
     */
    private function removeDirectoryWindows(string $path): void
    {
        $path = FileSystem::convertPath($path);

        // Try using PowerShell for force deletion
        $escaped_path = escapeshellarg($path);

        // Use PowerShell Remove-Item with -Force and -Recurse
        $ps_cmd = "powershell -Command \"Remove-Item -Path {$escaped_path} -Recurse -Force -ErrorAction SilentlyContinue\"";
        f_exec($ps_cmd, $output, $ret_code);

        // If PowerShell fails or directory still exists, try cmd rmdir
        if ($ret_code !== 0 || is_dir($path)) {
            $cmd_command = "rmdir /s /q {$escaped_path}";
            f_exec($cmd_command, $output, $ret_code);
        }

        // Final fallback: use FileSystem::removeDir
        if (is_dir($path)) {
            FileSystem::removeDir($path);
        }
    }
}
