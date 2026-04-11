<?php

declare(strict_types=1);

namespace Package\Extension;

use Package\Target\php;
use StaticPHP\Attribute\Package\BeforeStage;
use StaticPHP\Attribute\Package\Extension;
use StaticPHP\Attribute\PatchDescription;
use StaticPHP\Util\FileSystem;

#[Extension('yaml')]
class yaml
{
    #[BeforeStage('php', [php::class, 'buildconfForWindows'], 'ext-yaml')]
    #[PatchDescription('Fix yaml config.w32 to always link against static libyaml on Windows')]
    public function patchBeforeBuildconfForWindows(): void
    {
        // Force static libyaml linkage: config.w32 normally only picks libs ending in '_a.lib',
        // but libyaml may not follow that naming convention, so we add 'yes' == 'yes' to always match.
        FileSystem::replaceFileStr(
            SOURCE_PATH . '/php-src/ext/yaml/config.w32',
            "lib.substr(lib.length - 6, 6) == '_a.lib'",
            "lib.substr(lib.length - 6, 6) == '_a.lib' || 'yes' == 'yes'"
        );
    }
}
