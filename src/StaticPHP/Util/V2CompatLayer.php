<?php

declare(strict_types=1);

namespace StaticPHP\Util;

use StaticPHP\Artifact\Artifact;
use StaticPHP\DI\ApplicationContext;
use StaticPHP\Exception\InterruptException;
use StaticPHP\Exception\WrongUsageException;
use StaticPHP\Package\PackageBuilder;
use StaticPHP\Package\PackageInstaller;
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

    public static function beforeExtractHook(Artifact $artifact): void
    {
        self::emitPatchPoint(match ($artifact->getName()) {
            'php-src' => 'before-php-extract',
            'micro' => 'before-micro-extract',
            default => '',
        });
    }

    public static function afterExtractHook(Artifact $artifact): void
    {
        self::emitPatchPoint(match ($artifact->getName()) {
            'php-src' => 'after-php-extract',
            'micro' => 'after-micro-extract',
            default => '',
        });
    }

    public static function beforeExtsExtractHook(): void
    {
        self::emitPatchPoint('before-exts-extract');
    }

    public static function afterExtsExtractHook(): void
    {
        self::emitPatchPoint('after-exts-extract');
    }

    public static function beforeLibExtractHook(string $pkg): void
    {
        self::emitPatchPoint("before-library[{$pkg}]-extract");
    }

    public static function afterLibExtractHook(string $pkg): void
    {
        self::emitPatchPoint("after-library[{$pkg}]-extract");
    }

    public static function emitPatchPoint(string $point_name): void
    {
        if ($point_name === '') {
            return;
        }
        if (!ApplicationContext::has(PackageInstaller::class)) {
            return;
        }
        $builder = ApplicationContext::get(PackageBuilder::class);
        $patch_points = $builder->getOption('with-added-patch', []);
        ApplicationContext::set('patch_point', $point_name);
        foreach ($patch_points as $patch_point) {
            if (!file_exists($patch_point)) {
                throw new WrongUsageException("Additional patch script {$patch_point} does not exist!");
            }
            logger()->debug("Applying additional patch script {$patch_point}");

            try {
                require $patch_point;
            } catch (InterruptException $e) {
                if ($e->getCode() === 0) {
                    logger()->notice('Patch script ' . $patch_point . ' interrupted' . ($e->getMessage() ? (': ' . $e->getMessage()) : '.'));
                } else {
                    logger()->error('Patch script ' . $patch_point . ' interrupted with error code [' . $e->getCode() . ']' . ($e->getMessage() ? (': ' . $e->getMessage()) : '.'));
                }
            }
        }
        ApplicationContext::set('patch_point', '');
    }
}
