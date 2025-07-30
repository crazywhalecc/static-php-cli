<?php

declare(strict_types=1);

assert(function_exists('openssl_digest'));
assert(openssl_digest('123456', 'md5') === 'e10adc3949ba59abbe56e057f20f883e');
if (file_exists('/etc/ssl/openssl.cnf')) {
    assert(file_get_contents('https://captive.apple.com/') !== false);
}
if (PHP_VERSION_ID >= 80500 && defined('OPENSSL_VERSION_NUMBER') && OPENSSL_VERSION_NUMBER >= 0x30200000) {
    assert(function_exists('openssl_password_hash'));
}
