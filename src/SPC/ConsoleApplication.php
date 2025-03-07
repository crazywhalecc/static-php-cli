<?php

declare(strict_types=1);

namespace SPC;

use SPC\command\BuildCliCommand;
use SPC\command\BuildLibsCommand;
use SPC\command\DeleteDownloadCommand;
use SPC\command\dev\AllExtCommand;
use SPC\command\dev\ExtVerCommand;
use SPC\command\dev\GenerateExtDepDocsCommand;
use SPC\command\dev\GenerateExtDocCommand;
use SPC\command\dev\GenerateLibDepDocsCommand;
use SPC\command\dev\LibVerCommand;
use SPC\command\dev\PackLibCommand;
use SPC\command\dev\PhpVerCommand;
use SPC\command\dev\SortConfigCommand;
use SPC\command\DoctorCommand;
use SPC\command\DownloadCommand;
use SPC\command\DumpExtensionsCommand;
use SPC\command\DumpLicenseCommand;
use SPC\command\ExtractCommand;
use SPC\command\InstallPkgCommand;
use SPC\command\MicroCombineCommand;
use SPC\command\SPCConfigCommand;
use SPC\command\SwitchPhpVersionCommand;
use Symfony\Component\Console\Application;

/**
 * static-php-cli console app entry
 */
final class ConsoleApplication extends Application
{
    public const VERSION = '2.4.5';

    public function __construct()
    {
        parent::__construct('static-php-cli', self::VERSION);

        // Define internal env vars and constants
        require_once ROOT_DIR . '/src/globals/internal-env.php';

        $this->addCommands(
            [
                // Common commands
                new BuildCliCommand(),
                new BuildLibsCommand(),
                new DoctorCommand(),
                new DownloadCommand(),
                new InstallPkgCommand(),
                new DeleteDownloadCommand(),
                new DumpLicenseCommand(),
                new ExtractCommand(),
                new MicroCombineCommand(),
                new SwitchPhpVersionCommand(),
                new SPCConfigCommand(),
                new DumpExtensionsCommand(),

                // Dev commands
                new AllExtCommand(),
                new PhpVerCommand(),
                new LibVerCommand(),
                new ExtVerCommand(),
                new SortConfigCommand(),
                new GenerateExtDocCommand(),
                new GenerateExtDepDocsCommand(),
                new GenerateLibDepDocsCommand(),
                new PackLibCommand(),
            ]
        );
    }
}
