<?php

declare(strict_types=1);

assert(class_exists('Imagick'));
assert(Imagick::queryFormats('AVIF') !== []);
assert(Imagick::queryFormats('HEIF') !== []);
assert(Imagick::queryFormats('HEIC') !== []);
assert(Imagick::queryFormats('WEBP') !== []);
assert(Imagick::queryFormats('JPEG') !== []);
assert(Imagick::queryFormats('PNG') !== []);
assert(Imagick::queryFormats('TIFF') !== []);
