<?php

declare(strict_types=1);

namespace StaticPHP;

use StaticPHP\Command\BuildLibsCommand;
use StaticPHP\Command\BuildTargetCommand;
use StaticPHP\Command\Dev\EnvCommand;
use StaticPHP\Command\Dev\IsInstalledCommand;
use StaticPHP\Command\Dev\ShellCommand;
use StaticPHP\Command\Dev\SortConfigCommand;
use StaticPHP\Command\DoctorCommand;
use StaticPHP\Command\DownloadCommand;
use StaticPHP\Command\ExtractCommand;
use StaticPHP\Command\InstallPackageCommand;
use StaticPHP\Command\SPCConfigCommand;
use StaticPHP\Package\TargetPackage;
use StaticPHP\Registry\PackageLoader;
use StaticPHP\Registry\Registry;
use Symfony\Component\Console\Application;

class ConsoleApplication extends Application
{
    public const string VERSION = '3.0.0-dev';

    private static array $additional_commands = [];

    public function __construct()
    {
        parent::__construct('StaticPHP', self::VERSION);

        require_once ROOT_DIR . '/src/bootstrap.php';

        // resolve registry
        Registry::resolve();

        /**
         * @var string        $name
         * @var TargetPackage $package
         */
        foreach (PackageLoader::getPackages(['target', 'virtual-target']) as $name => $package) {
            // only add target that contains artifact.source
            if ($package->hasStage('build')) {
                logger()->debug("Registering build target command for package: {$name}");
                $this->addCommand(new BuildTargetCommand($name));
            }
        }

        // add core commands
        $this->addCommands([
            new DownloadCommand(),
            new DoctorCommand(),
            new InstallPackageCommand(),
            new BuildLibsCommand(),
            new ExtractCommand(),
            new SPCConfigCommand(),

            // dev commands
            new ShellCommand(),
            new IsInstalledCommand(),
            new EnvCommand(),
            new SortConfigCommand(),
        ]);

        // add additional commands from registries
        if (!empty(self::$additional_commands)) {
            $this->addCommands(self::$additional_commands);
        }
    }

    /**
     * @internal
     */
    public static function _addAdditionalCommands(array $additional_commands): void
    {
        self::$additional_commands = array_merge(self::$additional_commands, $additional_commands);
    }
}
