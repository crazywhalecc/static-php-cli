<?php

declare(strict_types=1);

namespace SPC\exception;

class ExceptionHandler
{
    protected mixed $whoops = null;

    private static ?ExceptionHandler $obj = null;

    public static function getInstance(): ExceptionHandler
    {
        if (self::$obj === null) {
            self::$obj = new self();
        }
        return self::$obj;
    }

    public function handle(\Throwable $e): void
    {
        logger()->error('Uncaught ' . get_class($e) . ': ' . $e->getMessage() . ' at ' . $e->getFile() . '(' . $e->getLine() . ')');
        logger()->error($e->getTraceAsString());
        logger()->critical('You can report this exception to static-php-cli GitHub repo.');
    }
}
