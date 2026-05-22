<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\util\CustomExt;

#[CustomExt('clickhouse')]
class clickhouse extends Extension
{
    public function getUnixConfigureArg(bool $shared = false): string
    {
        $arg = '--enable-clickhouse' . ($shared ? '=shared' : '');
        if ($this->builder->getLib('openssl')) {
            $arg .= ' --enable-clickhouse-openssl';
        }
        return $arg;
    }
}
