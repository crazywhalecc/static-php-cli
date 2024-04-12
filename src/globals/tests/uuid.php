<?php

declare(strict_types=1);

assert(function_exists('uuid_create'));
assert(strlen(uuid_create(0)) === 36);
