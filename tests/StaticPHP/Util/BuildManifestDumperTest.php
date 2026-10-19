<?php

declare(strict_types=1);

namespace Tests\StaticPHP\Util;

use Package\Target\php as PhpTargetPackage;
use PHPUnit\Framework\TestCase;
use StaticPHP\Artifact\Artifact;
use StaticPHP\ConsoleApplication;
use StaticPHP\Package\Package;
use StaticPHP\Package\PackageInstaller;
use StaticPHP\Package\PhpExtensionPackage;
use StaticPHP\Package\TargetPackage;
use StaticPHP\Runtime\SystemTarget;
use StaticPHP\Util\BuildManifestDumper;

/**
 * @internal
 */
class BuildManifestDumperTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/build_manifest_dumper_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        if (is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }
    }

    public function testDumpWritesResolvedPackagesAndBuildContext(): void
    {
        $phpArtifact = new Artifact('php-src', ['source' => ['type' => 'url', 'url' => 'https://example.com/php.tar.gz']]);
        $phpManifestData = [
            'thread-safety' => 'nts',
            'extensions' => ['all' => ['curl'], 'static' => ['curl'], 'shared' => []],
        ];
        $php = $this->createTargetPackage('php', $phpArtifact, $phpManifestData);
        $openssl = $this->createPackage('openssl', 'library');
        $curl = $this->createPackage('ext-curl', 'php-extension');
        $cli = $this->createPackage('php-cli', 'target');
        $installer = $this->createMock(PackageInstaller::class);

        $outputPath = $this->tempDir . '/nested/build-manifest.json';
        (new BuildManifestDumper())->dump(
            [
                'openssl' => $openssl,
                'ext-curl' => $curl,
                'php' => $php,
                'php-cli' => $cli,
            ],
            ['php' => $php],
            ['php-cli' => $cli],
            $installer,
            $outputPath,
        );

        $this->assertFileExists($outputPath);
        $manifest = json_decode((string) file_get_contents($outputPath), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(BuildManifestDumper::SCHEMA_VERSION, $manifest['schema-version']);
        $this->assertSame(ConsoleApplication::VERSION, $manifest['spc-version']);
        $this->assertSame(SystemTarget::getCurrentPlatformString(), $manifest['target']['platform']);
        $this->assertSame(SystemTarget::getTargetOS(), $manifest['target']['os']);
        $this->assertSame(SystemTarget::getTargetArch(), $manifest['target']['architecture']);
        $this->assertSame(['php'], $manifest['requested-packages']['build']);
        $this->assertSame(['php-cli'], $manifest['requested-packages']['install']);
        $this->assertSame([
            ['name' => 'openssl', 'type' => 'library', 'artifact' => null],
            ['name' => 'ext-curl', 'type' => 'php-extension', 'artifact' => null],
            ['name' => 'php', 'type' => 'target', 'artifact' => 'php-src'],
            ['name' => 'php-cli', 'type' => 'target', 'artifact' => null],
        ], $manifest['packages']);
        $this->assertSame(['php' => $phpManifestData], $manifest['targets']);
    }

    public function testPhpTargetExportsPhpSpecificBuildFacts(): void
    {
        $curl = new PhpExtensionPackage('curl', extension_config: ['arg-type' => 'with']);
        $curl->setBuildStatic();
        $pdo = new PhpExtensionPackage('pdo', extension_config: ['arg-type' => 'enable']);
        $pdo->setBuildStatic();
        $pdo->setBuildShared();
        $xdebug = new PhpExtensionPackage('xdebug', extension_config: ['arg-type' => 'enable']);
        $xdebug->setBuildShared();
        $unused = new PhpExtensionPackage('unused', extension_config: ['arg-type' => 'enable']);

        $installer = $this->createMock(PackageInstaller::class);
        $installer->method('getResolvedPackages')->willReturn([$curl, $pdo, $xdebug, $unused]);
        $installer->method('isPackageResolved')->willReturnCallback(
            static fn (string $name): bool => in_array($name, ['php-cli', 'php-micro', 'php-embed'], true),
        );

        $php = new class('php', 'target') extends PhpTargetPackage {
            public static function getPHPVersion(?string $from_custom_source = null, bool $return_null_if_failed = false): ?string
            {
                return '8.4.10';
            }

            public function getBuildOption(string $key, mixed $default = null): mixed
            {
                return $key === 'enable-zts' ? true : $default;
            }
        };

        $data = $php->getBuildManifestData($installer);

        $this->assertSame('8.4.10', $data['version']);
        $this->assertSame('zts', $data['thread-safety']);
        $this->assertSame(['cli', 'micro', 'embed'], $data['sapis']);
        $this->assertSame([
            'all' => ['curl', 'pdo', 'xdebug'],
            'static' => ['curl', 'pdo'],
            'shared' => ['pdo', 'xdebug'],
        ], $data['extensions']);
    }

    private function createPackage(string $name, string $type, ?Artifact $artifact = null): Package
    {
        return new class($name, $type, $artifact) extends Package {
            public function __construct(string $name, string $type, private readonly ?Artifact $artifact)
            {
                parent::__construct($name, $type);
            }

            public function getArtifact(): ?Artifact
            {
                return $this->artifact;
            }
        };
    }

    /** @param array<string, mixed> $manifestData */
    private function createTargetPackage(string $name, ?Artifact $artifact, array $manifestData): TargetPackage
    {
        return new class($name, 'target', $artifact, $manifestData) extends TargetPackage {
            /** @param array<string, mixed> $manifestData */
            public function __construct(
                string $name,
                string $type,
                private readonly ?Artifact $artifact,
                private readonly array $manifestData,
            ) {
                parent::__construct($name, $type);
            }

            public function getArtifact(): ?Artifact
            {
                return $this->artifact;
            }

            public function getBuildManifestData(PackageInstaller $installer): array
            {
                return $this->manifestData;
            }
        };
    }

    private function removeDirectory(string $directory): void
    {
        $items = scandir($directory);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $directory . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($directory);
    }
}
