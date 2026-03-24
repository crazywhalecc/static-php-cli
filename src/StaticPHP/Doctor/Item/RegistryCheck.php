<?php

declare(strict_types=1);

namespace StaticPHP\Doctor\Item;

use StaticPHP\Attribute\Doctor\CheckItem;
use StaticPHP\Doctor\CheckResult;
use StaticPHP\Registry\Registry;
use ZM\Logger\ConsoleColor;

class RegistryCheck
{
    #[CheckItem('if at least one registry is configured', level: 99999)]
    public function checkRegistry(): ?CheckResult
    {
        $regs = Registry::getLoadedRegistries();
        if (count($regs) > 0) {
            return CheckResult::ok(implode(',', array_map(fn ($x) => ConsoleColor::green($x), $regs)));
        }
        return CheckResult::fail('No registry configured');
    }
}
