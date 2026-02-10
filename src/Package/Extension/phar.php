<?php

declare(strict_types=1);

namespace Package\Extension;

use Package\Target\php;
use StaticPHP\Attribute\Package\AfterStage;
use StaticPHP\Attribute\Package\BeforeStage;
use StaticPHP\Attribute\Package\Extension;
use StaticPHP\Attribute\PatchDescription;
use StaticPHP\Util\SourcePatcher;

#[Extension('phar')]
class phar
{
    #[BeforeStage('php', [php::class, 'makeMicroForUnix'], 'ext-phar')]
    #[PatchDescription('Patch phar extension for micro SAPI to support compressed phar')]
    public function beforeMicroUnixBuild(): void
    {
        SourcePatcher::patchMicroPhar(php::getPHPVersionID());
    }

    #[AfterStage('php', [php::class, 'makeMicroForUnix'], 'ext-phar')]
    public function afterMicroUnixBuild(): void
    {
        SourcePatcher::unpatchMicroPhar();
    }
}
