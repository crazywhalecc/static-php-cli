<?php

declare(strict_types=1);

namespace Package\Artifact;

use StaticPHP\Artifact\Artifact;
use StaticPHP\Attribute\Artifact\AfterSourceExtract;
use StaticPHP\Attribute\PatchDescription;
use StaticPHP\Util\SourcePatcher;
use StaticPHP\Util\System\LinuxUtil;

class attr
{
    #[AfterSourceExtract('attr')]
    #[PatchDescription('Patch attr for Alpine Linux (musl) and macOS - gethostname declaration')]
    public function patchAttrForAlpine(Artifact $artifact): void
    {
        if (PHP_OS_FAMILY === 'Darwin' || PHP_OS_FAMILY === 'Linux' && LinuxUtil::isMuslDist()) {
            SourcePatcher::patchFile('attr_alpine_gethostname.patch', $artifact->getSourceDir());
        }
    }
}
