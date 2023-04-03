<?php

declare(strict_types=1);

if (!class_exists('\\DOMDocument')) {
    exit(1);
}
$doc = new DOMDocument();
$doc->loadHtml("<html><head><meta charset=\"UTF-8\"><meta http-equiv=\"Content-Type\" content=\"text/html; charset=UTF-8\"></head><body id='app'>Hello</body></html>");
exit($doc->getElementById('app')->nodeValue === 'Hello' ? 0 : 1);
