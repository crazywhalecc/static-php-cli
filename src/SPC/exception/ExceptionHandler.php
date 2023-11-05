<?php

declare(strict_types=1);

namespace SPC\exception;

class ExceptionHandler
{
    protected mixed $whoops = null;

    private static ?ExceptionHandler $obj = null;

    private function __construct()
    {
        $whoops_class = 'Whoops\Run';
        $collision_class = 'NunoMaduro\Collision\Handler';
        if (class_exists($collision_class) && class_exists($whoops_class)) {
            /* @phpstan-ignore-next-line */
            $this->whoops = new $whoops_class();
            $this->whoops->allowQuit(false);
            $this->whoops->writeToOutput(false);
            $this->whoops->pushHandler(new $collision_class());
            $this->whoops->register();
        }
    }

    public static function getInstance(): ExceptionHandler
    {
        if (self::$obj === null) {
            self::$obj = new self();
        }
        return self::$obj;
    }

    public function handle(\Throwable $e): void
    {
        if (is_null($this->whoops)) {
            logger()->error('Uncaught ' . get_class($e) . ': ' . $e->getMessage() . ' at ' . $e->getFile() . '(' . $e->getLine() . ')');
            logger()->error($e->getTraceAsString());
            return;
        }
        $this->whoops->handleException($e);

        logger()->critical('You can report this exception to static-php-cli GitHub repo.');
    }
}
