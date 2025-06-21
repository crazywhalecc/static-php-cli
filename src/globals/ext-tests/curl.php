<?php

declare(strict_types=1);

assert(function_exists('curl_init'));
assert(function_exists('curl_setopt'));
assert(function_exists('curl_exec'));
assert(function_exists('curl_close'));
$curl_version = curl_version();
if (stripos($curl_version['ssl_version'], 'schannel') !== false) {
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, 'https://example.com/');
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_HEADER, 0);
    $data = curl_exec($curl);
    curl_close($curl);
    assert($data !== false);
}
