<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\util\CustomExt;

#[CustomExt('imagick')]
class imagick extends Extension
{
    public function getUnixConfigureArg(): string
    {
        return '--with-imagick=' . BUILD_ROOT_PATH;
    }
}
