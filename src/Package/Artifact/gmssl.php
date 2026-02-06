<?php

declare(strict_types=1);

namespace Package\Artifact;

use StaticPHP\Attribute\Artifact\AfterSourceExtract;
use StaticPHP\Attribute\PatchDescription;
use StaticPHP\Util\FileSystem;

class gmssl
{
    #[AfterSourceExtract('gmssl')]
    #[PatchDescription('Patch gmssl hex.c to rename OPENSSL functions to GMSSL')]
    public function patch(string $target_path): void
    {
        FileSystem::replaceFileStr($target_path . '/src/hex.c', 'unsigned char *OPENSSL_hexstr2buf(const char *str, size_t *len)', 'unsigned char *GMSSL_hexstr2buf(const char *str, size_t *len)');
        FileSystem::replaceFileStr($target_path . '/src/hex.c', 'OPENSSL_hexchar2int', 'GMSSL_hexchar2int');
    }
}
