<?php

declare(strict_types=1);

namespace Package\Extension;

use Package\Target\php;
use StaticPHP\Attribute\Package\BeforeStage;
use StaticPHP\Attribute\Package\CustomPhpConfigureArg;
use StaticPHP\Attribute\Package\Extension;
use StaticPHP\Attribute\Package\Validate;
use StaticPHP\Attribute\PatchDescription;
use StaticPHP\Exception\WrongUsageException;
use StaticPHP\Package\PackageBuilder;
use StaticPHP\Package\PackageInstaller;
use StaticPHP\Package\PhpExtensionPackage;
use StaticPHP\Runtime\SystemTarget;
use StaticPHP\Util\SourcePatcher;

#[Extension('opcache')]
class opcache extends PhpExtensionPackage
{
    #[Validate]
    public function validate(): void
    {
        if (php::getPHPVersionID() < 80000 && getenv('SPC_SKIP_PHP_VERSION_CHECK') !== 'yes') {
            throw new WrongUsageException('Statically compiled PHP with Zend Opcache only available for PHP >= 8.0 !');
        }
    }

    #[BeforeStage('php', [php::class, 'buildconfForUnix'], 'ext-opcache')]
    #[PatchDescription('Fix static opcache build for PHP 8.2.0 to 8.4.x')]
    public function patchBeforeBuildconf(PackageInstaller $installer): bool
    {
        $version = php::getPHPVersion();
        $php_src = $installer->getTargetPackage('php')->getSourceDir();
        if (file_exists("{$php_src}/.opcache_patched")) {
            return false;
        }
        // if 8.2.0 <= PHP_VERSION < 8.2.23, we need to patch from legacy patch file
        if (version_compare($version, '8.2.0', '>=') && version_compare($version, '8.2.23', '<')) {
            SourcePatcher::patchFile('spc_fix_static_opcache_before_80222.patch', $php_src);
        }
        // if 8.3.0 <= PHP_VERSION < 8.3.11, we need to patch from legacy patch file
        elseif (version_compare($version, '8.3.0', '>=') && version_compare($version, '8.3.11', '<')) {
            SourcePatcher::patchFile('spc_fix_static_opcache_before_80310.patch', $php_src);
        }
        // if 8.3.12 <= PHP_VERSION < 8.5.0-dev, we need to patch from legacy patch file
        elseif (version_compare($version, '8.5.0-dev', '<')) {
            SourcePatcher::patchPhpSrc(items: ['static_opcache']);
        }
        // PHP 8.5.0-dev and later supports static opcache without patching
        else {
            return false;
        }
        return file_put_contents($php_src . '/.opcache_patched', '1') !== false;
    }

    #[CustomPhpConfigureArg('Darwin')]
    #[CustomPhpConfigureArg('Linux')]
    public function getUnixConfigureArg(bool $shared, PackageBuilder $builder): string
    {
        $phpVersionID = php::getPHPVersionID();
        $opcache_jit = ' --enable-opcache-jit';
        if ((SystemTarget::getTargetOS() === 'Linux' &&
                SystemTarget::getLibc() === 'musl' &&
                $builder->getOption('enable-zts') &&
                SystemTarget::getTargetArch() === 'x86_64' &&
                $phpVersionID < 80500) ||
            $builder->getOption('disable-opcache-jit')
        ) {
            $opcache_jit = ' --disable-opcache-jit';
        }
        return '--enable-opcache' . ($shared ? '=shared' : '') . $opcache_jit;
    }
}
