<?php

declare(strict_types=1);

namespace SPC\Tests\globals;

use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use SPC\exception\RuntimeException;
use SPC\exception\WrongUsageException;
use ZM\Logger\ConsoleLogger;

/**
 * @internal
 */
class GlobalFunctionsTest extends TestCase
{
    private static $logger_cache;

    public static function setUpBeforeClass(): void
    {
        global $ob_logger;
        self::$logger_cache = $ob_logger;
        $ob_logger = new ConsoleLogger(LogLevel::ALERT);
    }

    public static function tearDownAfterClass(): void
    {
        global $ob_logger;
        $ob_logger = self::$logger_cache;
    }

    public function testIsAssocArray(): void
    {
        $this->assertTrue(is_assoc_array(['a' => 1, 'b' => 2]));
        $this->assertFalse(is_assoc_array([1, 2, 3]));
    }

    public function testLogger(): void
    {
        $this->assertInstanceOf('Psr\Log\LoggerInterface', logger());
    }

    /**
     * @throws WrongUsageException
     */
    public function testArch2Gnu(): void
    {
        $this->assertEquals('x86_64', arch2gnu('x86_64'));
        $this->assertEquals('x86_64', arch2gnu('x64'));
        $this->assertEquals('x86_64', arch2gnu('amd64'));
        $this->assertEquals('aarch64', arch2gnu('arm64'));
        $this->assertEquals('aarch64', arch2gnu('aarch64'));
        $this->expectException('SPC\exception\WrongUsageException');
        arch2gnu('armv7');
    }

    public function testQuote(): void
    {
        $this->assertEquals('"hello"', quote('hello'));
        $this->assertEquals("'hello'", quote('hello', "'"));
    }

    /**
     * @throws RuntimeException
     */
    public function testFPassthru(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('Windows not support f_passthru');
        }
        $this->assertEquals(null, f_passthru('echo ""'));
        $this->expectException('SPC\exception\RuntimeException');
        f_passthru('false');
    }

    public function testFPutenv(): void
    {
        $this->assertTrue(f_putenv('SPC_TEST_ENV=1'));
        $this->assertEquals('1', getenv('SPC_TEST_ENV'));
    }

    public function testShell(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $this->markTestSkipped('Windows not support shell');
        }
        $shell = shell();
        $this->assertInstanceOf('SPC\util\UnixShell', $shell);
        $this->assertInstanceOf('SPC\util\UnixShell', $shell->cd('/'));
        $this->assertInstanceOf('SPC\util\UnixShell', $shell->exec('echo ""'));
        $this->assertInstanceOf('SPC\util\UnixShell', $shell->setEnv(['SPC_TEST_ENV' => '1']));

        [$code, $out] = $shell->execWithResult('echo "_"');
        $this->assertEquals(0, $code);
        $this->assertEquals('_', implode('', $out));

        $this->expectException('SPC\exception\RuntimeException');
        $shell->exec('false');
    }
}
