<?php

declare(strict_types=1);

namespace Package\Extension;

use StaticPHP\Artifact\Artifact;
use StaticPHP\Attribute\Package\CustomPhpConfigureArg;
use StaticPHP\Attribute\Package\Extension;
use StaticPHP\Attribute\Package\Validate;
use StaticPHP\Exception\WrongUsageException;
use StaticPHP\Package\PhpExtensionPackage;

#[Extension('zip')]
class zip extends PhpExtensionPackage
{
    /**
     * pecl/zip is archived: the extension is maintained in php-src since 8.5.
     * Use the bundled php-src source when available
     */
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
        if ($this->isBundledWithPhpSrc()) {
            return !$shared ? '--with-zip' : '--with-zip=shared';
        }
        return !$shared ? '--enable-zip' : '--enable-zip=shared';
    }

    protected function isBundledWithPhpSrc(): bool
    {
        return is_dir(SOURCE_PATH . '/php-src/ext/zip');
    }
}
