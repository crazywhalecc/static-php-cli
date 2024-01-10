<?php

declare(strict_types=1);

assert(function_exists('openssl_digest'));
assert(openssl_digest('123456', 'md5') === 'e10adc3949ba59abbe56e057f20f883e');
