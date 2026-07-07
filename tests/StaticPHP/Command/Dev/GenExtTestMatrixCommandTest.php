<?php

declare(strict_types=1);

namespace Tests\StaticPHP\Command\Dev;

use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use StaticPHP\Command\Dev\GenExtTestMatrixCommand;
use StaticPHP\Config\PackageConfig;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
class GenExtTestMatrixCommandTest extends TestCase
{
    private Application $app;

    protected function setUp(): void
    {
        parent::setUp();

        // Reset PackageConfig static state
        $ref = new \ReflectionClass(PackageConfig::class);
        $prop = $ref->getProperty('package_configs');
        $prop->setValue(null, []);

        // Register fixture packages
        PackageConfig::loadFromArray(self::buildFixture(), 'test');

        // Set up Symfony Application with the command under test
        $this->app = new Application();
        $this->app->add(new GenExtTestMatrixCommand());
        $this->app->setAutoExit(false);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Reset PackageConfig static state
        $ref = new \ReflectionClass(PackageConfig::class);
        $prop = $ref->getProperty('package_configs');
        $prop->setValue(null, []);

        // Restore logger level (BaseCommand::execute() may have changed it)
        logger()->setLevel(LogLevel::ERROR);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Tests
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * swoole entry must contain all swoole-hook-* virtuals and nothing else.
     */
    public function testSwooleBundlesHookVirtuals(): void
    {
        $matrix = $this->runMatrix(['--os' => 'Linux']);

        $swooleEntries = $this->findEntriesContaining($matrix, 'swoole');
        $this->assertCount(1, $swooleEntries, 'Expected exactly one entry containing swoole');

        $parts = explode(',', $swooleEntries[0]['extension']);
        sort($parts);

        $this->assertContains('swoole', $parts);
        $this->assertContains('swoole-hook-mysql', $parts);
        $this->assertContains('swoole-hook-pgsql', $parts);
    }

    /**
     * curl must NOT appear in the same entry as swoole, even though swoole depends on it.
     */
    public function testCurlIsNotPulledIntoSwooleEntry(): void
    {
        $matrix = $this->runMatrix(['--os' => 'Linux']);

        // The swoole entry must not contain 'curl'
        $swooleEntries = $this->findEntriesContaining($matrix, 'swoole');
        $this->assertCount(1, $swooleEntries);
        $parts = explode(',', $swooleEntries[0]['extension']);
        $this->assertNotContains('curl', $parts, 'curl must not appear inside the swoole matrix entry');

        // curl must appear in a separate entry
        $curlEntries = $this->findEntriesContaining($matrix, 'curl');
        $this->assertNotEmpty($curlEntries, 'curl must have its own matrix entry');
    }

    /**
     * swow must be fully isolated — its entry should only contain 'swow'.
     */
    public function testSwowIsIsolated(): void
    {
        $matrix = $this->runMatrix(['--os' => 'Linux']);

        $swowEntries = $this->findEntriesContaining($matrix, 'swow');
        $this->assertCount(1, $swowEntries, 'Expected exactly one entry containing swow');
        $this->assertSame('swow', $swowEntries[0]['extension'], 'swow entry must contain only swow');
    }

    /**
     * dom and xml must appear in the same matrix entry (DFS chain).
     */
    public function testDomXmlChain(): void
    {
        $matrix = $this->runMatrix(['--os' => 'Linux']);

        $chainEntries = $this->findEntriesContaining($matrix, 'dom', 'xml');
        $this->assertNotEmpty($chainEntries, 'dom and xml must appear in the same matrix entry');
    }

    /**
     * --os=Windows must exclude ext-linux-only.
     */
    public function testOsFilterExcludesLinuxOnlyFromWindows(): void
    {
        $matrix = $this->runMatrix(['--os' => 'Windows']);

        $linuxOnlyEntries = $this->findEntriesContaining($matrix, 'linux-only');
        $this->assertEmpty($linuxOnlyEntries, 'ext-linux-only must not appear in the Windows matrix');
    }

    /**
     * --os=Linux must include ext-linux-only.
     */
    public function testOsFilterIncludesLinuxOnly(): void
    {
        $matrix = $this->runMatrix(['--os' => 'Linux']);

        $linuxOnlyEntries = $this->findEntriesContaining($matrix, 'linux-only');
        $this->assertNotEmpty($linuxOnlyEntries, 'ext-linux-only must appear in the Linux matrix');
    }

    /**
     * All returned entries must reference the requested OS runner when --os is specified.
     */
    public function testOsFilterRestrictsRunners(): void
    {
        $matrix = $this->runMatrix(['--os' => 'Linux']);

        foreach ($matrix as $entry) {
            $this->assertSame('linux', $entry['os'], "Entry {$entry['extension']} must only target Linux");
        }
    }

    /**
     * --for-extensions=redis must return only entries that contain 'redis'.
     */
    public function testForExtensionsFilter(): void
    {
        $matrix = $this->runMatrix(['--os' => 'Linux', '--for-extensions' => 'redis']);

        $this->assertNotEmpty($matrix, '--for-extensions=redis must yield at least one entry');
        foreach ($matrix as $entry) {
            $parts = explode(',', $entry['extension']);
            $this->assertContains('redis', $parts, "Entry {$entry['extension']} does not contain redis");
        }
    }

    /**
     * --for-libs=libxml2 must return only entries whose extension(s) depend on libxml2.
     */
    public function testForLibsFilter(): void
    {
        $matrix = $this->runMatrix(['--os' => 'Linux', '--for-libs' => 'libxml2']);

        $this->assertNotEmpty($matrix, '--for-libs=libxml2 must yield at least one entry');
        foreach ($matrix as $entry) {
            $parts = explode(',', $entry['extension']);
            // xml depends on libxml2 directly; dom depends on xml (which depends on libxml2)
            $match = count(array_intersect($parts, ['xml', 'dom'])) > 0;
            $this->assertTrue($match, "Entry {$entry['extension']} should not appear in --for-libs=libxml2 results");
        }
    }

    /**
     * --for-libs must include extensions that depend on the library through other libraries.
     */
    public function testForLibsFilterIncludesTransitiveLibraryDeps(): void
    {
        $matrix = $this->runMatrix(['--os' => 'Linux', '--for-libs' => 'libde265']);

        $this->assertNotEmpty($matrix, '--for-libs=libde265 must yield at least one entry');
        foreach ($matrix as $entry) {
            $parts = explode(',', $entry['extension']);
            $this->assertContains('imagick', $parts, "Entry {$entry['extension']} should not appear in --for-libs=libde265 results");
        }
    }

    /**
     * --tier2 must produce only Tier2 runners and no Windows entries.
     */
    public function testTier2Flag(): void
    {
        $matrix = $this->runMatrix(['--tier2' => true]);

        $this->assertNotEmpty($matrix);
        foreach ($matrix as $entry) {
            $this->assertNotSame('windows', $entry['os'], '--tier2 must not include Windows entries');
            $this->assertContains(
                $entry['runner'],
                ['ubuntu-24.04-arm', 'macos-15-intel'],
                "Runner {$entry['runner']} is not a valid Tier2 runner"
            );
        }
    }

    /**
     * Each entry must have the mandatory keys and correct types.
     */
    public function testEntryShape(): void
    {
        $matrix = $this->runMatrix(['--os' => 'Linux']);

        $this->assertNotEmpty($matrix);
        foreach ($matrix as $entry) {
            $this->assertArrayHasKey('runner', $entry);
            $this->assertArrayHasKey('os', $entry);
            $this->assertArrayHasKey('arch', $entry);
            $this->assertArrayHasKey('extension', $entry);
            $this->assertArrayHasKey('build-args', $entry);
            $this->assertIsString($entry['extension']);
            $this->assertIsString($entry['build-args']);
            $this->assertStringContainsString($entry['extension'], $entry['build-args']);
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Run the command with the given options and return the parsed JSON matrix.
     */
    private function runMatrix(array $options = []): array
    {
        $tester = new CommandTester($this->app->find('dev:gen-ext-test-matrix'));
        $tester->execute($options, ['decorated' => false]);
        $output = $tester->getDisplay();
        $matrix = json_decode($output, true);
        $this->assertIsArray($matrix, "Command output is not valid JSON. Output:\n{$output}");
        return $matrix;
    }

    /**
     * Find matrix entries whose 'extension' field contains all of the given names.
     *
     * @return array[] matching entries
     */
    private function findEntriesContaining(array $matrix, string ...$names): array
    {
        return array_values(array_filter($matrix, static function (array $entry) use ($names): bool {
            $parts = explode(',', $entry['extension']);
            foreach ($names as $name) {
                if (!in_array($name, $parts, true)) {
                    return false;
                }
            }
            return true;
        }));
    }

    /**
     * Minimal valid php-extension fixture.
     *
     * Layout:
     *  - ext-swow            standalone isolated, no ext deps
     *  - ext-swoole          standalone isolated, depends on ext-curl
     *  - ext-swoole-hook-*   virtual (arg-type: none) — must be bundled with swoole
     *  - ext-curl            simple orphan, depended on by swoole but must NOT be pulled into swoole entry
     *  - ext-redis           simple orphan
     *  - ext-xml             depends on lib 'libxml2'
     *  - ext-dom             depends on ext-xml (DFS chain)
     *  - ext-imagick         depends on imagemagick -> libheif -> libde265
     *  - ext-linux-only      restricted to Linux via os: [Linux]
     */
    private static function buildFixture(): array
    {
        // php-extension must be a non-empty assoc array ([] fails is_assoc_array() check).
        $ext = static fn (array $phpExt = ['arg-type' => 'standard'], array $topLevel = []): array => array_merge(['type' => 'php-extension', 'php-extension' => $phpExt], $topLevel);
        $lib = static fn (array $topLevel = []): array => array_merge(['type' => 'library', 'artifact' => ['source' => 'custom']], $topLevel);

        return [
            // Isolated standalones
            'ext-swow' => $ext(),
            'ext-swoole' => $ext(['arg-type' => 'standard'], ['depends' => ['ext-curl']]),

            // Swoole hook virtuals (arg-type: none → virtual)
            'ext-swoole-hook-mysql' => $ext(['arg-type' => 'none']),
            'ext-swoole-hook-pgsql' => $ext(['arg-type' => 'none']),

            // Simple orphans
            'ext-curl' => $ext(),
            'ext-redis' => $ext(),

            // DFS chain: dom depends on xml; xml depends on lib 'libxml2'
            'ext-xml' => $ext(['arg-type' => 'standard'], ['depends' => ['libxml2']]),
            'ext-dom' => $ext(['arg-type' => 'standard'], ['depends' => ['ext-xml']]),

            // Transitive library chain: imagick -> imagemagick -> libheif -> libde265
            'ext-imagick' => $ext(['arg-type' => 'standard'], ['depends' => ['imagemagick']]),
            'imagemagick' => $lib(['depends' => ['libheif']]),
            'libheif' => $lib(['depends' => ['libde265']]),
            'libde265' => $lib(),

            // OS-restricted to Linux only
            'ext-linux-only' => $ext(['os' => ['Linux']]),
        ];
    }
}
