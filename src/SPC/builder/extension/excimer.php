<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\util\CustomExt;

#[CustomExt('excimer')]
class excimer extends Extension
{
    public function getSharedExtensionEnv(): array
    {
        $env = parent::getSharedExtensionEnv();
        $env['LIBS'] = clean_spaces(str_replace('-lphp', '', $env['LIBS']));
        return $env;
    }
}
