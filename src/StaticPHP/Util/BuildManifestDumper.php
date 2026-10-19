<?php

declare(strict_types=1);

namespace StaticPHP\Util;

use StaticPHP\ConsoleApplication;
use StaticPHP\Exception\SPCInternalException;
use StaticPHP\Package\Package;
use StaticPHP\Package\PackageInstaller;
use StaticPHP\Package\TargetPackage;
use StaticPHP\Runtime\SystemTarget;

final class BuildManifestDumper
{
    public const int SCHEMA_VERSION = 1;

    /**
     * @param array<string, Package> $packages        Resolved packages in dependency order
     * @param array<string, Package> $buildPackages   Packages explicitly scheduled for building
     * @param array<string, Package> $installPackages Packages explicitly scheduled for installation
     */
    public function dump(
        array $packages,
        array $buildPackages,
        array $installPackages,
        PackageInstaller $installer,
        string $outputPath = BUILD_ROOT_PATH . '/build-manifest.json',
    ): void {
        $targets = [];
        foreach ($packages as $package) {
            if (!$package instanceof TargetPackage) {
                continue;
            }

            $data = $package->getBuildManifestData($installer);
            if ($data !== []) {
                $targets[$package->getName()] = $data;
            }
        }

        $manifest = [
            'schema-version' => self::SCHEMA_VERSION,
            'spc-version' => ConsoleApplication::VERSION,
            'target' => [
                'platform' => SystemTarget::getCurrentPlatformString(),
                'os' => SystemTarget::getTargetOS(),
                'architecture' => SystemTarget::getTargetArch(),
                'libc' => SystemTarget::getLibc(),
                'libc-version' => SystemTarget::getLibcVersion(),
                'spc-target' => getenv('SPC_TARGET') ?: null,
                'toolchain' => getenv('SPC_TOOLCHAIN') ?: null,
            ],
            'requested-packages' => [
                'build' => array_keys($buildPackages),
                'install' => array_keys($installPackages),
            ],
            'packages' => array_values(array_map(
                static function (Package $package): array {
                    $artifact = $package->getArtifact();
                    return [
                        'name' => $package->getName(),
                        'type' => $package->getType(),
                        'artifact' => $artifact?->getName(),
                    ];
                },
                $packages,
            )),
            'targets' => $targets,
        ];

        $json = json_encode(
            $manifest,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );
        if (FileSystem::writeFile($outputPath, $json . PHP_EOL) === false) {
            throw new SPCInternalException("Failed to write build manifest: {$outputPath}");
        }

        logger()->info('Generated build manifest with ' . count($packages) . " package(s): {$outputPath}");
    }
}
