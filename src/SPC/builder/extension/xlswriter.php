<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\util\CustomExt;

#[CustomExt('xlswriter')]
class xlswriter extends Extension
{
    public function getUnixConfigureArg(bool $shared = false): string
    {
        $arg = '--with-xlswriter --enable-reader';
        if ($this->builder->getLib('openssl')) {
            $arg .= ' --with-openssl=' . BUILD_ROOT_PATH;
        }
        return $arg;
    }

    public function getWindowsConfigureArg(): string
    {
        return '--with-xlswriter';
    }

    public function patchBeforeMake(): bool
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $content = file_get_contents($this->source_dir . '/library/libxlsxwriter/src/theme.c');
            $bom = pack('CCC', 0xef, 0xbb, 0xbf);
            if (substr($content, 0, 3) !== $bom) {
                file_put_contents($this->source_dir . '/library/libxlsxwriter/src/theme.c', $content);
                return true;
            }
            return false;
        }
        return false;
    }
}
