<?php

declare(strict_types=1);

assert(function_exists('openssl_digest'));
assert(openssl_digest('123456', 'md5') === 'e10adc3949ba59abbe56e057f20f883e');
if (file_exists('/etc/ssl/openssl.cnf')) {
    assert(file_get_contents('https://example.com/') !== false);
}
