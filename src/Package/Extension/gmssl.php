<?php

declare(strict_types=1);

namespace Package\Extension;

use Package\Target\php;
use StaticPHP\Attribute\Package\BeforeStage;
use StaticPHP\Attribute\Package\Extension;
use StaticPHP\Attribute\PatchDescription;
use StaticPHP\Package\PhpExtensionPackage;
use StaticPHP\Util\FileSystem;

#[Extension('gmssl')]
class gmssl extends PhpExtensionPackage
{
    #[BeforeStage('php', [php::class, 'buildconfForUnix'], 'ext-gmssl')]
    #[BeforeStage('php', [php::class, 'buildconfForWindows'], 'ext-gmssl')]
    #[PatchDescription('Fix ext-gmssl v1.1.1 compatibility with GmSSL >= 3.1.0 where SM2_VERIFY_CTX was removed (unified into SM2_SIGN_CTX)')]
    public function patchSm2VerifyCtx(): void
    {
        // See: https://github.com/crazywhalecc/static-php-cli/issues/1182
        FileSystem::replaceFileStr(
            "{$this->getSourceDir()}/gmssl.c",
            'SM2_VERIFY_CTX',
            'SM2_SIGN_CTX'
        );
    }

    #[BeforeStage('php', [php::class, 'buildconfForWindows'], 'ext-gmssl')]
    #[PatchDescription('Add CHECK_LIB to config.w32 for static Windows builds')]
    public function patchBeforeBuildconfWin(): bool
    {
        $configW32 = "{$this->getSourceDir()}/config.w32";
        if (str_contains(FileSystem::readFile($configW32), 'CHECK_LIB(')) {
            return false;
        }
        FileSystem::replaceFileStr(
            $configW32,
            'AC_DEFINE(',
            'CHECK_LIB("gmssl.lib", "gmssl", PHP_GMSSL);' . PHP_EOL . 'AC_DEFINE('
        );
        return true;
    }
}
