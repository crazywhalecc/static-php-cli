<?php

declare(strict_types=1);

namespace StaticPHP\Artifact\Downloader\Type;

readonly class CheckUpdateResult
{
    public function __construct(
        public ?string $old,
        public string $new,
        public bool $needUpdate,
    ) {}
}
