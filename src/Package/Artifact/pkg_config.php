<?php

declare(strict_types=1);

namespace Package\Artifact;

use StaticPHP\Attribute\Artifact\AfterSourceExtract;
use StaticPHP\Attribute\PatchDescription;
use StaticPHP\Util\SourcePatcher;

class pkg_config
{
    #[AfterSourceExtract('pkg-config')]
    #[PatchDescription('Patch pkg-config for GCC 15 compatibility - __builtin_available issue')]
    public function patch(string $target_path): void
    {
        SourcePatcher::patchFile('pkg-config_gcc15.patch', $target_path);
    }
}
