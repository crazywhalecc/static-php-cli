<?php

declare(strict_types=1);
use Psr\Log\LogLevel;
use StaticPHP\Registry\Registry;

require_once __DIR__ . '/../src/bootstrap.php';

logger()->setLevel(LogLevel::ERROR);

Registry::resolve();
