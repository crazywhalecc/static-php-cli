<?php

declare(strict_types=1);

assert(function_exists('openssl_digest'));
assert(openssl_digest('123456', 'md5') === 'e10adc3949ba59abbe56e057f20f883e');
if (file_exists('/etc/ssl/openssl.cnf')) {
    $domain_list = [
        'https://captive.apple.com/',
        'https://detectportal.firefox.com/',
        'https://static-php.dev/',
        'https://www.example.com/',
    ];
    $valid = false;
    foreach ($domain_list as $domain) {
        if (file_get_contents($domain) !== false) {
            $valid = true;
            break;
        }
    }
    assert($valid);
}
if (PHP_VERSION_ID >= 80500 && defined('OPENSSL_VERSION_NUMBER') && OPENSSL_VERSION_NUMBER >= 0x30200000) {
    assert(function_exists('openssl_password_hash'));
}
