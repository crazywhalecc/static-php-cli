<?php

declare(strict_types=1);

namespace SPC\builder\extension;

use SPC\builder\Extension;
use SPC\util\CustomExt;

#[CustomExt('com_dotnet')]
class com_dotnet extends Extension
{
    public function getWindowsConfigureArg(bool $shared = false): string
    {
        return '--enable-com-dotnet=yes';
    }
}
