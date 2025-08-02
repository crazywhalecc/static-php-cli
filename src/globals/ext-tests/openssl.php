<?php

declare(strict_types=1);

assert(function_exists('openssl_digest'));
assert(openssl_digest('123456', 'md5') === 'e10adc3949ba59abbe56e057f20f883e');
if (file_exists('/etc/ssl/openssl.cnf')) {
    $domain_list = [
        'captive.apple.com',
        'detectportal.firefox.com',
        'static-php.dev',
        'www.example.com',
    ];
    $valid = false;
    foreach ($domain_list as $domain) {
        $ssloptions = [
            'capture_peer_cert' => true,
            'capture_peer_cert_chain' => true,
            'allow_self_signed' => false,
            'CN_match' => $domain,
            'verify_peer' => true,
            'SNI_enabled' => true,
            'SNI_server_name' => $domain,
        ];
        $context = stream_context_create(['ssl' => $ssloptions]);
        $result = stream_socket_client("ssl://{$domain}:443", $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $context);
        if ($result !== false) {
            $valid = true;
            break;
        }
    }
    assert($valid);
}
if (PHP_VERSION_ID >= 80500 && defined('OPENSSL_VERSION_NUMBER') && OPENSSL_VERSION_NUMBER >= 0x30200000) {
    assert(function_exists('openssl_password_hash'));
}
