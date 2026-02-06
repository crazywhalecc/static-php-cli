<?php

declare(strict_types=1);

namespace Package\Artifact;

use StaticPHP\Artifact\Artifact;
use StaticPHP\Attribute\Artifact\AfterSourceExtract;
use StaticPHP\Attribute\PatchDescription;
use StaticPHP\Util\FileSystem;

class bzip2
{
    #[AfterSourceExtract('bzip2')]
    #[PatchDescription('Patch bzip2 Makefile to add -fPIC flag for position-independent code')]
    public function patchBzip2Makefile(Artifact $artifact): void
    {
        FileSystem::replaceFileStr("{$artifact->getSourceDir()}/Makefile", 'CFLAGS=-Wall', 'CFLAGS=-fPIC -Wall');
    }
}
