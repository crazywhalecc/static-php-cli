<?php

declare(strict_types=1);

namespace Tests\StaticPHP\Util;

use PHPUnit\Framework\TestCase;
use StaticPHP\Util\System\UnixUtil;

/**
 * @internal
 */
class UnixUtilTest extends TestCase
{
    public function testDarwinDynsymOnlyContainsMachOSymbols(): void
    {
        // Sample lines from nm -g --defined-only -P output on macOS with BSD ar extended headers
        $nmOutput = [
            'libphp.a[php_basic_early_rinit.o]:',
            '_php_basic_early_rinit T 1000 0',
            'y_rinit', // Truncated name / header fragment
            '10',      // Length fragment from BSD ar #1/10 header
            '_zend_execute T 2000 0',
            'st_sort', // Member fragment from zend_llist_sort.o
            '_php_embed_init T 3000 0',
        ];

        $symbols = UnixUtil::parseDynamicSymbols($nmOutput, 'Darwin');

        $this->assertSame([
            '_php_basic_early_rinit',
            '_php_embed_init',
            '_zend_execute',
        ], $symbols);
    }
}
