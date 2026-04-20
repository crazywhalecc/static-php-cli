<?php

declare(strict_types=1);

namespace StaticPHP\Util;

use StaticPHP\Artifact\Artifact;
use StaticPHP\Package\TargetPackage;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * Compatibility layer for static-php-cli 2.x version.
 * Internal use only.
 */
class V2CompatLayer
{
    /**
     * Mapping legacy build options to new build options.
     *
     * - `with-suggested-libs` and `with-suggested-exts` are mapped to `with-suggests`.
     * - `with-libs` is mapped to `with-packages`.
     */
    public static function convertOptions(InputInterface $input): void
    {
        if ($input->getOption('with-suggested-libs')) {
            $input->setOption('with-suggests', true);
        }
        if ($input->getOption('with-suggested-exts')) {
            $input->setOption('with-suggests', true);
        }
        if ($input->getOption('with-libs')) {
            $existing = $input->getOption('with-packages');
            $additional = $input->getOption('with-libs');
            if (!empty($existing)) {
                $input->setOption('with-packages', $existing . ',' . $additional);
            } else {
                $input->setOption('with-packages', $additional);
            }
        }
    }

    public static function getLegacyBuildOptions(): array
    {
        return [
            new InputOption('with-suggested-libs', null, InputOption::VALUE_NONE, 'Resolve and install suggested libraries as well (legacy)'),
            new InputOption('with-suggested-exts', null, InputOption::VALUE_NONE, 'Resolve and install suggested extensions as well (legacy)'),
            new InputOption('with-libs', null, InputOption::VALUE_REQUIRED, 'add additional libraries to install/build, comma separated (legacy)', ''),
        ];
    }

    /**
     * Add legacy build options for the 'php' target package.
     */
    public static function addLegacyBuildOptionsForPhp(TargetPackage $package): void
    {
        if ($package->getName() === 'php') {
            $package->addBuildOption('build-micro', null, null, 'Build micro SAPI');
            $package->addBuildOption('build-cli', null, null, 'Build cli SAPI');
            $package->addBuildOption('build-fpm', null, null, 'Build fpm SAPI (not available on Windows)');
            $package->addBuildOption('build-embed', null, null, 'Build embed SAPI (not available on Windows)');
            $package->addBuildOption('build-frankenphp', null, null, 'Build FrankenPHP SAPI (not available on Windows)');
            $package->addBuildOption('build-cgi', null, null, 'Build cgi SAPI');
            $package->addBuildOption('build-all', null, null, 'Build all SAPI');
        }
    }

    public static function beforeExtractHook(Artifact $artifact): void {}

    public static function afterExtractHook(Artifact $artifact): void {}

    public static function beforeExtsExtractHook(): void {}

    public static function afterExtsExtractHook(): void {}

    public static function beforeLibExtractHook(string $pkg): void {}

    public static function afterLibExtractHook(string $pkg): void {}
}
