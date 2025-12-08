<?php

declare(strict_types=1);

namespace Package\Library;

use StaticPHP\Attribute\Package\BeforeStage;
use StaticPHP\Attribute\Package\BuildFor;
use StaticPHP\Attribute\Package\Library;
use StaticPHP\Package\LibraryPackage;
use StaticPHP\Runtime\Executor\UnixAutoconfExecutor;
use StaticPHP\Util\FileSystem;

#[Library('libedit')]
class libedit extends LibraryPackage
{
    #[BeforeStage('libedit', 'build')]
    public function patchBeforeBuild(): void
    {
        FileSystem::replaceFileRegex(
            "{$this->getSourceDir()}/src/sys.h",
            '|//#define\s+strl|',
            '#define strl'
        );
    }

    #[BuildFor('Darwin')]
    #[BuildFor('Linux')]
    public function build(): void
    {
        UnixAutoconfExecutor::create($this)
            ->appendEnv(['CFLAGS' => '-D__STDC_ISO_10646__=201103L'])
            ->configure()
            ->make();
        $this->patchPkgconfPrefix(['libedit.pc']);
    }
}
