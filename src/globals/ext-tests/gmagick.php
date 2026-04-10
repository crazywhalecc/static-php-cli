<?php

declare(strict_types=1);

assert(class_exists('Gmagick'));
assert(in_array('JPEG', (new Gmagick())->queryformats()));
assert(in_array('PNG', (new Gmagick())->queryformats()));
