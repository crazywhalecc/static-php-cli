<?php

declare(strict_types=1);

namespace Package\Artifact;

use StaticPHP\Attribute\Artifact\AfterSourceExtract;
use StaticPHP\Attribute\PatchDescription;
use StaticPHP\Util\SourcePatcher;

class imagick
{
    #[AfterSourceExtract('ext-imagick')]
    #[PatchDescription('Patch imagick for PHP 8.4 compatibility (versions < 3.8.0)')]
    public function patchImagickWith84(string $target_path): void
    {
        // match imagick version id
        $file = $target_path . '/php_imagick.h';
        if (!file_exists($file)) {
            return;
        }
        $content = file_get_contents($file);
        if (preg_match('/#define PHP_IMAGICK_EXTNUM\s+(\d+)/', $content, $match) === 0) {
            return;
        }
        $extnum = intval($match[1]);
        if ($extnum < 30800) {
            SourcePatcher::patchFile('imagick_php84_before_30800.patch', $target_path);
        }
    }
}
