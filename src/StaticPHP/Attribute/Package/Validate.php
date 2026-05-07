<?php

declare(strict_types=1);

namespace StaticPHP\Attribute\Package;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class Validate {}
