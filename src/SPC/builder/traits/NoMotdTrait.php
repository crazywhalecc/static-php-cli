<?php

declare(strict_types=1);

namespace SPC\builder\traits;

/**
 * 仅供 Command 使用，如果使用了该 Trait，则在执行对应命令时不打印 motd
 */
trait NoMotdTrait
{
    protected bool $no_motd = true;
}
