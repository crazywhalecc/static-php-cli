<?php

declare(strict_types=1);

namespace Package\Library;

use StaticPHP\Attribute\Package\AfterStage;
use StaticPHP\Attribute\Package\Library;
use StaticPHP\Attribute\PatchDescription;
use StaticPHP\Runtime\SystemTarget;
use StaticPHP\Util\FileSystem;

#[Library('imap')]
class imap
{
    #[AfterStage('php', 'patch-embed-scripts', 'imap')]
    #[PatchDescription('Fix missing -lcrypt in php-config libs on glibc systems')]
    public function afterPatchScripts(): void
    {
        if (SystemTarget::getLibc() === 'glibc') {
            FileSystem::replaceFileRegex(BUILD_BIN_PATH . '/php-config', '/^libs="(.*)"$/m', 'libs="$1 -lcrypt"');
        }
    }
}
