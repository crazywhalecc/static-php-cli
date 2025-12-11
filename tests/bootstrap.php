<?php

declare(strict_types=1);
use Psr\Log\LogLevel;

require_once __DIR__ . '/../src/bootstrap.php';
\StaticPHP\Registry\Registry::checkLoadedRegistries();

logger()->setLevel(LogLevel::ERROR);
