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
    public function patchImagickWith84(): void
    {
        // match imagick version id
        $file = SOURCE_PATH . '/php-src/ext/imagick/php_imagick.h';
        if (!file_exists($file)) {
            return;
        }
        $content = file_get_contents($file);
        if (preg_match('/#define PHP_IMAGICK_EXTNUM\s+(\d+)/', $content, $match) === 0) {
            return;
        }
        $extnum = intval($match[1]);
        if ($extnum < 30800) {
            SourcePatcher::patchFile('imagick_php84_before_30800.patch', SOURCE_PATH . '/php-src/ext/imagick');
        }
    }
}
