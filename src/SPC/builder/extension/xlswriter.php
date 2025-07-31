<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\store\SourcePatcher;
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

    public function getWindowsConfigureArg(bool $shared = false): string
    {
        return '--with-xlswriter';
    }

    public function patchBeforeMake(): bool
    {
        $patched = parent::patchBeforeMake();
        if (PHP_OS_FAMILY === 'Windows') {
            // fix windows build with openssl extension duplicate symbol bug
            SourcePatcher::patchFile('spc_fix_xlswriter_win32.patch', $this->source_dir);
            $content = file_get_contents($this->source_dir . '/library/libxlsxwriter/src/theme.c');
            $bom = pack('CCC', 0xEF, 0xBB, 0xBF);
            if (!str_starts_with($content, $bom)) {
                file_put_contents($this->source_dir . '/library/libxlsxwriter/src/theme.c', $bom . $content);
            }
            return true;
        }
        return $patched;
    }
}
