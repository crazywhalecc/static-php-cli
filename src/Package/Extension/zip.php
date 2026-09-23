<?php

declare(strict_types=1);

namespace Package\Extension;

use Package\Target\php;
use StaticPHP\Artifact\Artifact;
use StaticPHP\Artifact\ArtifactCache;
use StaticPHP\Attribute\Package\CustomPhpConfigureArg;
use StaticPHP\Attribute\Package\Extension;
use StaticPHP\Attribute\Package\Validate;
use StaticPHP\DI\ApplicationContext;
use StaticPHP\Exception\WrongUsageException;
use StaticPHP\Package\PackageBuilder;
use StaticPHP\Package\PhpExtensionPackage;

#[Extension('zip')]
class zip extends PhpExtensionPackage
{
    /** pecl/zip is archived; PHP 8.5+ maintains the extension in php-src. */
    public function getArtifact(): ?Artifact
    {
        if ($this->isBundledWithPhpSrc()) {
            return null;
        }
        return parent::getArtifact();
    }

    #[Validate]
    public function validate(): void
    {
        if ($this->isBuildStatic() && !$this->isBundledWithPhpSrc()) {
            throw new WrongUsageException('ext-zip can only be built statically with PHP >= 8.5 (zip is bundled in php-src since 8.5, and the archived pecl/zip source cannot be built in-tree for older versions). For PHP < 8.5, build it shared instead: --build-shared zip');
        }
    }

    #[CustomPhpConfigureArg('Darwin')]
    #[CustomPhpConfigureArg('Linux')]
    public function getUnixConfigureArg(bool $shared): string
    {
        $config = file_get_contents(SOURCE_PATH . '/php-src/ext/zip/config.m4');
        $arg = str_contains($config, 'PHP_ARG_ENABLE([zip]') ? '--enable-zip' : '--with-zip';
        return $arg . ($shared ? '=shared' : '');
    }

    protected function isBundledWithPhpSrc(): bool
    {
        $requested = ApplicationContext::get(PackageBuilder::class)->getOption('dl-with-php');
        if (is_string($requested) && preg_match('/^\d+(?:\.\d+)?/', $requested)) {
            return version_compare($requested, '8.5', '>=');
        }

        $version = php::getPHPVersion(return_null_if_failed: true)
            ?? ApplicationContext::get(ArtifactCache::class)->getSourceInfo('php-src')['version']
            ?? null;

        return $version !== null
            ? version_compare($version, '8.5', '>=')
            : is_dir(SOURCE_PATH . '/php-src/ext/zip');
    }
}
