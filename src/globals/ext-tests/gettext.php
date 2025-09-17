<?php

declare(strict_types=1);

assert(function_exists('gettext'));
assert(function_exists('bindtextdomain'));
assert(function_exists('bind_textdomain_codeset'));
assert(function_exists('textdomain'));

foreach (['en_US', 'en_GB'] as $lc) {
    $dir = "locale/{$lc}/LC_MESSAGES";
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $mo = '3hIElQAAAAACAAAAHAAAACwAAAAFAAAAPAAAAAAAAABQAAAABgAAAFEAAAAXAQAAWAAAAAcAAABwAQAAAQAAAAAAAAAAAAAAAgAAAAAAAAAA56S65L6LAFByb2plY3QtSWQtVmVyc2lvbjogUEFDS0FHRSBWRVJTSU9OClJlcG9ydC1Nc2dpZC1CdWdzLVRvOiAKUE8tUmV2aXNpb24tRGF0ZTogWUVBUi1NTy1EQSBITzpNSytaT05FCkxhc3QtVHJhbnNsYXRvcjogRlVMTCBOQU1FIDxFTUFJTEBBRERSRVNTPgpMYW5ndWFnZS1UZWFtOiBMQU5HVUFHRSA8TExAbGkub3JnPgpMYW5ndWFnZTogCk1JTUUtVmVyc2lvbjogMS4wCkNvbnRlbnQtVHlwZTogdGV4dC9wbGFpbjsgY2hhcnNldD1VVEYtOApDb250ZW50LVRyYW5zZmVyLUVuY29kaW5nOiA4Yml0CgBFeGFtcGxlAA==';
    $path = "{$dir}/test.mo";
    if (!file_exists($path)) {
        file_put_contents($path, base64_decode($mo));
    }
}

// Probe for an available English locale
$candidates = [
    'en_US.UTF-8', 'en_US.utf8', 'en_US.utf-8', 'en_US',
    'en_GB.UTF-8', 'en_GB.utf8', 'en_GB.utf-8', 'en_GB',
    'English_United States.65001', 'English_United States.1252',
    'English_United Kingdom.65001', 'English_United Kingdom.1252',
];

$locale = setlocale(LC_ALL, $candidates);
assert($locale !== false);

putenv('LC_ALL=' . $locale);
putenv('LANG=' . $locale);
putenv('LANGUAGE=' . (stripos($locale, 'US') !== false ? 'en_US:en_GB' : 'en_GB:en_US'));

$domain = 'test';
bindtextdomain($domain, 'locale/');
bind_textdomain_codeset($domain, 'UTF-8');
textdomain($domain);

$src = json_decode('"\u793a\u4f8b"', true);
assert(gettext($src) === 'Example');
