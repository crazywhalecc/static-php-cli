<?php

declare(strict_types=1);

assert(function_exists('curl_init'));
assert(function_exists('curl_setopt'));
assert(function_exists('curl_exec'));
assert(function_exists('curl_close'));
assert(function_exists('curl_version'));
$curl_version = curl_version();
if (stripos($curl_version['ssl_version'], 'schannel') !== false) {
    $domain_list = [
        'https://captive.apple.com/',
        'https://detectportal.firefox.com/',
        'https://static-php.dev/',
        'https://www.example.com/',
    ];
    $valid = false;
    foreach ($domain_list as $domain) {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $domain);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        $data = curl_exec($curl);
        curl_close($curl);
        if ($data !== false) {
            $valid = true;
            break;
        }
    }
    assert($valid);
}
