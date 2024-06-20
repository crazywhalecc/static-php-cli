<?php

declare(strict_types=1);

assert(class_exists('\DOMDocument'));
$doc = new DOMDocument();
$doc->loadHtml('<html><head><meta charset="UTF-8"><meta http-equiv="Content-Type" content="text/html; charset=UTF-8"></head><body id="app">Hello</body></html>');
assert($doc->getElementById('app')->nodeValue === 'Hello');
