<?php

declare(strict_types=1);

namespace StaticPHP\Attribute\Doctor;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class CheckItem
{
    public mixed $callback = null;

    public function __construct(
        public string $item_name,
        public ?string $limit_os = null,
        public int $level = 100,
        public bool $manual = false,
    ) {}
}
