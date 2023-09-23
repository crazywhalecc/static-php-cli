<?php

declare(strict_types=1);

namespace SPC;

use SPC\command\BuildCliCommand;
use SPC\command\BuildLibsCommand;
use SPC\command\dev\AllExtCommand;
use SPC\command\dev\PhpVerCommand;
use SPC\command\dev\SortConfigCommand;
use SPC\command\DoctorCommand;
use SPC\command\DownloadCommand;
use SPC\command\DumpLicenseCommand;
use SPC\command\ExportDockerShCommand;
use SPC\command\ExtractCommand;
use SPC\command\MicroCombineCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\HelpCommand;
use Symfony\Component\Console\Command\ListCommand;

/**
 * static-php-cli console app entry
 */
final class ConsoleApplication extends Application
{
    public const VERSION = '2.0.0';

    public function __construct()
    {
        $name = !empty(getenv('SPC_FIX_DEPLOY_ROOT')) ? 'static-php-cli (Docker)' : 'static-php-cli';
        parent::__construct($name, self::VERSION);

        global $argv;

        // Detailed debugging errors are not displayed in the production environment. Only the error display provided by Symfony console is used.
        $this->setCatchExceptions(file_exists(ROOT_DIR . '/.prod') || !in_array('--debug', $argv));

        $this->addCommands(
            [
                new BuildCliCommand(),
                new BuildLibsCommand(),
                new DoctorCommand(),
                new DownloadCommand(),
                new DumpLicenseCommand(),
                new ExtractCommand(),
                new MicroCombineCommand(),
                new ExportDockerShCommand(),

                // Dev commands
                new AllExtCommand(),
                new PhpVerCommand(),
                new SortConfigCommand(),
            ]
        );
    }

    protected function getDefaultCommands(): array
    {
        return [new HelpCommand(), new ListCommand()];
    }
}
