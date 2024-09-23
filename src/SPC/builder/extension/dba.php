<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\util\CustomExt;

#[CustomExt('dba')]
class dba extends Extension
{
    public function getUnixConfigureArg(): string
    {
        $qdbm = $this->builder->getLib('qdbm') ? (' --with-qdbm=' . BUILD_ROOT_PATH) : '';
        return '--enable-dba' . $qdbm;
    }

    public function getWindowsConfigureArg(): string
    {
        $qdbm = $this->builder->getLib('qdbm') ? ' --with-qdbm' : '';
        return '--with-dba' . $qdbm;
    }
}
