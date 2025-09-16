<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\util\CustomExt;

#[CustomExt('dba')]
class dba extends Extension
{
    public function getUnixConfigureArg(bool $shared = false): string
    {
        $qdbm = $this->builder->getLib('qdbm') ? (' --with-qdbm=' . BUILD_ROOT_PATH) : '';
        return '--enable-dba' . ($shared ? '=shared' : '') . $qdbm;
    }

    public function getWindowsConfigureArg(bool $shared = false): string
    {
        $qdbm = $this->builder->getLib('qdbm') ? ' --with-qdbm' : '';
        return '--with-dba' . $qdbm;
    }
}
