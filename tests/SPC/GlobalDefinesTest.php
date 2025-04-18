<?php

declare(strict_types=1);

namespace SPC\Tests;

use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class GlobalDefinesTest extends TestCase
{
    public function testGlobalDefines(): void
    {
        require __DIR__ . '/../../src/globals/defines.php';
        $this->assertTrue(defined('WORKING_DIR'));
    }

    public function testInternalEnv(): void
    {
        require __DIR__ . '/../../src/globals/internal-env.php';
        $this->assertTrue(defined('GNU_ARCH'));
    }
}
