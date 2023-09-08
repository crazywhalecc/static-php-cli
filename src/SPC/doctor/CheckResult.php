<?php

declare(strict_types=1);

namespace SPC\doctor;

class CheckResult
{
    public function __construct(private readonly bool $ok, private readonly ?string $message = null, private string $fix_item = '', private array $fix_params = []) {}

    public static function fail(string $message, string $fix_item = '', array $fix_params = []): CheckResult
    {
        return new static(false, $message, $fix_item, $fix_params);
    }

    public static function ok(?string $message = null): CheckResult
    {
        return new static(true, $message);
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function getFixItem(): string
    {
        return $this->fix_item;
    }

    public function getFixParams(): array
    {
        return $this->fix_params;
    }

    public function isOK(): bool
    {
        return $this->ok;
    }

    public function setFixItem(string $fix_item = '', array $fix_params = []): void
    {
        $this->fix_item = $fix_item;
        $this->fix_params = $fix_params;
    }
}
