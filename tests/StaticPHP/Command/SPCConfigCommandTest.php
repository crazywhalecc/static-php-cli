<?php

declare(strict_types=1);

namespace Tests\StaticPHP\Command;

use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use StaticPHP\Command\SPCConfigCommand;
use StaticPHP\Config\PackageConfig;
use StaticPHP\DI\ApplicationContext;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
class SPCConfigCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        ApplicationContext::reset();
        $this->setPackageConfig([
            'root' => [
                'type' => 'library',
                'suggests' => ['optional'],
                'static-libs' => ['/fixtures/libroot.a'],
            ],
            'optional' => [
                'type' => 'library',
                'static-libs' => ['/fixtures/liboptional.a'],
            ],
        ]);
    }

    protected function tearDown(): void
    {
        $this->setPackageConfig([]);
        ApplicationContext::reset();
        logger()->setLevel(LogLevel::ERROR);
        parent::tearDown();
    }

    public function testWithSuggestsOptionStillAffectsStandaloneCommand(): void
    {
        $withoutSuggests = $this->runCommand(false);
        $withSuggests = $this->runCommand(true);

        $this->assertStringNotContainsString('/fixtures/liboptional.a', $withoutSuggests);
        $this->assertStringContainsString('/fixtures/liboptional.a', $withSuggests);
    }

    private function runCommand(bool $withSuggests): string
    {
        $application = new Application();
        $application->setAutoExit(false);
        $application->add(new SPCConfigCommand());
        $tester = new CommandTester($application->find('spc-config'));
        $input = [
            '--with-libs' => 'root',
            '--libs-only-deps' => true,
            '--no-php' => true,
            '--no-ansi' => true,
        ];
        if ($withSuggests) {
            $input['--with-suggests'] = true;
        }
        $tester->execute($input);

        return $tester->getDisplay();
    }

    private function setPackageConfig(array $config): void
    {
        $reflection = new \ReflectionClass(PackageConfig::class);
        $reflection->getProperty('package_configs')->setValue(null, $config);
    }
}
