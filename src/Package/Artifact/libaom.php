<?php

declare(strict_types=1);

namespace Package\Artifact;

use StaticPHP\Attribute\Artifact\AfterSourceExtract;
use StaticPHP\Attribute\PatchDescription;
use StaticPHP\Runtime\SystemTarget;
use StaticPHP\Util\SourcePatcher;
use StaticPHP\Util\System\LinuxUtil;

class libaom
{
    #[AfterSourceExtract('libaom')]
    #[PatchDescription('Patch libaom for Linux Musl distributions - posix implicit declaration')]
    public function patch(string $target_path): void
    {
        spc_skip_if(SystemTarget::getTargetOS() !== 'Linux' || !LinuxUtil::isMuslDist(), 'Only for Linux Musl distros');
        SourcePatcher::patchFile('libaom_posix_implict.patch', $target_path);
    }
}
