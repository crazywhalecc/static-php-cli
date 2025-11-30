<?php

declare(strict_types=1);

namespace Package\Library;

use StaticPHP\Attribute\Package\BeforeStage;
use StaticPHP\Attribute\Package\Library;
use StaticPHP\Attribute\PatchDescription;
use StaticPHP\Package\TargetPackage;

#[Library('postgresql')]
class postgresql
{
    #[BeforeStage('php', 'unix-configure', 'postgresql')]
    #[PatchDescription('Patch to avoid explicit_bzero detection issues on some systems')]
    public function patchBeforePHPConfigure(TargetPackage $package): void
    {
        shell()->cd($package->getSourceDir())
            ->exec('sed -i.backup "s/ac_cv_func_explicit_bzero\" = xyes/ac_cv_func_explicit_bzero\" = x_fake_yes/" ./configure');
    }
}
