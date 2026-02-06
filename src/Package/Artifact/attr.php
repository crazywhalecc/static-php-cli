<?php

declare(strict_types=1);

namespace Package\Artifact;

use StaticPHP\Artifact\Artifact;
use StaticPHP\Attribute\Artifact\AfterSourceExtract;
use StaticPHP\Attribute\PatchDescription;
use StaticPHP\Runtime\SystemTarget;
use StaticPHP\Util\SourcePatcher;
use StaticPHP\Util\System\LinuxUtil;

class attr
{
    #[AfterSourceExtract('attr')]
    #[PatchDescription('Patch attr for Alpine Linux (musl) and macOS - gethostname declaration')]
    public function patchAttrForAlpine(Artifact $artifact): void
    {
        spc_skip_unless(SystemTarget::getTargetOS() === 'Darwin' || SystemTarget::getTargetOS() === 'Linux' && !LinuxUtil::isMuslDist(), 'Only for Alpine Linux (musl) and macOS');
        SourcePatcher::patchFile('attr_alpine_gethostname.patch', $artifact->getSourceDir());
    }
}
