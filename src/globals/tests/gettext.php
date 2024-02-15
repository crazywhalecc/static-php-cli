<?php

declare(strict_types=1);

assert(function_exists('gettext'));
assert(function_exists('bindtextdomain'));
assert(function_exists('bind_textdomain_codeset'));

if (!is_dir('locale/en_US/LC_MESSAGES/')) {
    mkdir('locale/en_US/LC_MESSAGES/', 0755, true);
}
if (!file_exists('locale/en_US/LC_MESSAGES/test.mo')) {
    file_put_contents('locale/en_US/LC_MESSAGES/test.mo', file_get_contents(__DIR__ . '/../objs/test.mo'));
}
putenv('LANG=en_US');
setlocale(LC_ALL, 'en_US');

$domain = 'test';
bindtextdomain($domain, 'locale/');
bind_textdomain_codeset($domain, 'UTF-8');
textdomain($domain);

assert(gettext('示例') === 'Example');
