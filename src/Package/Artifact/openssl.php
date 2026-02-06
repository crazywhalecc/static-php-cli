<?php

declare(strict_types=1);

namespace Package\Artifact;

use StaticPHP\Attribute\Artifact\AfterSourceExtract;
use StaticPHP\Attribute\PatchDescription;
use StaticPHP\Util\FileSystem;

/**
 * openssl artifact patches.
 */
class openssl
{
    /**
     * Patch OpenSSL 1.1 for Darwin (missing string.h include).
     */
    #[AfterSourceExtract('openssl')]
    #[PatchDescription('Patch OpenSSL 1.1 for Darwin (missing string.h include)')]
    public function patchOpenssl11Darwin(string $target_path): void
    {
        spc_skip_if(PHP_OS_FAMILY !== 'Darwin', 'This patch is only for Darwin systems.');

        spc_skip_if(file_exists("{$target_path}/openssl/VERSION.dat"), 'This patch is only for OpenSSL 1.1.x versions.');

        spc_skip_if(!file_exists("{$target_path}/openssl/test/v3ext.c"), 'v3ext.c not found, skipping patch.');

        FileSystem::replaceFileStr(
            "{$target_path}/openssl/test/v3ext.c",
            '#include <stdio.h>',
            "#include <stdio.h>\n#include <string.h>"
        );
    }
}
