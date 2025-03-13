<?php

declare(strict_types=1);

assert(function_exists('gettext'));
assert(function_exists('bindtextdomain'));
assert(function_exists('textdomain'));

if (!is_dir('locale/en_US/LC_MESSAGES/')) {
    mkdir('locale/en_US/LC_MESSAGES/', 0755, true);
}
if (!file_exists('locale/en_US/LC_MESSAGES/test.mo')) {
    $mo = '3hIElQAAAAACAAAAHAAAACwAAAAFAAAAPAAAAAAAAABQAAAABgAAAFEAAAAXAQAAWAAAAAcAAABwAQAAAQAAAAAAAAAAAAAAAgAAAAAAAAAA56S65L6LAFByb2plY3QtSWQtVmVyc2lvbjogUEFDS0FHRSBWRVJTSU9OClJlcG9ydC1Nc2dpZC1CdWdzLVRvOiAKUE8tUmV2aXNpb24tRGF0ZTogWUVBUi1NTy1EQSBITzpNSStaT05FCkxhc3QtVHJhbnNsYXRvcjogRlVMTCBOQU1FIDxFTUFJTEBBRERSRVNTPgpMYW5ndWFnZS1UZWFtOiBMQU5HVUFHRSA8TExAbGkub3JnPgpMYW5ndWFnZTogCk1JTUUtVmVyc2lvbjogMS4wCkNvbnRlbnQtVHlwZTogdGV4dC9wbGFpbjsgY2hhcnNldD1VVEYtOApDb250ZW50LVRyYW5zZmVyLUVuY29kaW5nOiA4Yml0CgBFeGFtcGxlAA==';
    file_put_contents('locale/en_US/LC_MESSAGES/test.mo', base64_decode($mo));
}
putenv('LANG=en_US');
assert(setlocale(LC_ALL, 'en_US.utf-8') === 'en_US.utf-8');

$domain = 'test';
bindtextdomain($domain, 'locale/');
textdomain($domain);

assert(gettext(json_decode('"\u793a\u4f8b"', true)) === 'Example');
