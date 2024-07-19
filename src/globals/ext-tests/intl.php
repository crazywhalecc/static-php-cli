<?php

declare(strict_types=1);

assert(class_exists(NumberFormatter::class));
assert(function_exists('locale_get_default'));
$fmt = new NumberFormatter('de-DE', NumberFormatter::DECIMAL);
assert(strval($fmt->parse('1.100')) === '1100');
