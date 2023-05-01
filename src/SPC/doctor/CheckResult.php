<?php

declare(strict_types=1);

namespace SPC\doctor;

class CheckResult
{
    public function __construct(private string $message = '', private string $fix_item = '', private array $fix_params = [])
    {
    }

    public static function fail(string $message, string $fix_item = '', array $fix_params = []): CheckResult
    {
        return new static($message, $fix_item, $fix_params);
    }

    public static function ok(): CheckResult
    {
        return new static();
    }

    public function getMessage(): string
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
        return empty($this->message);
    }

    public function setFixItem(string $fix_item = '', array $fix_params = [])
    {
        $this->fix_item = $fix_item;
        $this->fix_params = $fix_params;
    }
}
