<?php

declare(strict_types=1);

namespace SPC;

use SPC\command\BuildCliCommand;
use SPC\command\BuildLibsCommand;
use SPC\command\DeleteDownloadCommand;
use SPC\command\dev\AllExtCommand;
use SPC\command\dev\PhpVerCommand;
use SPC\command\dev\SortConfigCommand;
use SPC\command\DoctorCommand;
use SPC\command\DownloadCommand;
use SPC\command\DumpLicenseCommand;
use SPC\command\ExtractCommand;
use SPC\command\InstallPkgCommand;
use SPC\command\MicroCombineCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\HelpCommand;
use Symfony\Component\Console\Command\ListCommand;

/**
 * static-php-cli console app entry
 */
final class ConsoleApplication extends Application
{
    public const VERSION = '2.1.3';

    public function __construct()
    {
        parent::__construct('static-php-cli', self::VERSION);

        $this->addCommands(
            [
                new BuildCliCommand(),
                new BuildLibsCommand(),
                new DoctorCommand(),
                new DownloadCommand(),
                new InstallPkgCommand(),
                new DeleteDownloadCommand(),
                new DumpLicenseCommand(),
                new ExtractCommand(),
                new MicroCombineCommand(),

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
