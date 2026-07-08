<?php

declare(strict_types=1);

namespace Tests\StaticPHP\Util;

use PHPUnit\Framework\TestCase;
use StaticPHP\Config\PackageConfig;
use StaticPHP\Exception\WrongUsageException;
use StaticPHP\Util\DependencyResolver;

/**
 * Tests for the DependencyResolver — the topological sort engine that
 * determines the order in which packages (libraries, extensions, targets)
 * must be built.
 *
 * @internal
 */
class DependencyResolverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->resetPackageConfig();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->resetPackageConfig();
    }

    // ──────────────────────────────────────────────
    //  Basic resolution
    // ──────────────────────────────────────────────

    public function testResolveSinglePackageNoDependencies(): void
    {
        $this->loadConfig([
            'zlib' => ['type' => 'library'],
        ]);

        $result = DependencyResolver::resolve(['zlib']);

        $this->assertSame(['zlib'], $result);
    }

    public function testResolveLinearChain(): void
    {
        // a -> b -> c   (a depends on b, b depends on c)
        $this->loadConfig([
            'a' => ['type' => 'library', 'depends' => ['b']],
            'b' => ['type' => 'library', 'depends' => ['c']],
            'c' => ['type' => 'library'],
        ]);

        $result = DependencyResolver::resolve(['a']);

        // c must be first, then b, then a
        $this->assertSame(['c', 'b', 'a'], $result);
    }

    public function testResolveMultipleIndependentChains(): void
    {
        // a -> b,   x -> y   (two independent dependency chains)
        $this->loadConfig([
            'a' => ['type' => 'library', 'depends' => ['b']],
            'b' => ['type' => 'library'],
            'x' => ['type' => 'library', 'depends' => ['y']],
            'y' => ['type' => 'library'],
        ]);

        $result = DependencyResolver::resolve(['a', 'x']);

        // Dependencies must come before their dependants
        $posB = array_search('b', $result, true);
        $posA = array_search('a', $result, true);
        $posY = array_search('y', $result, true);
        $posX = array_search('x', $result, true);

        $this->assertIsInt($posB);
        $this->assertIsInt($posA);
        $this->assertIsInt($posY);
        $this->assertIsInt($posX);
        $this->assertLessThan($posA, $posB, 'b should come before a');
        $this->assertLessThan($posX, $posY, 'y should come before x');
    }

    public function testResolveSharedDependency(): void
    {
        // a -> c,   b -> c   (c is shared)
        $this->loadConfig([
            'a' => ['type' => 'library', 'depends' => ['c']],
            'b' => ['type' => 'library', 'depends' => ['c']],
            'c' => ['type' => 'library'],
        ]);

        $result = DependencyResolver::resolve(['a', 'b']);

        // c must appear exactly once and before both a and b
        $cCount = count(array_keys($result, 'c', true));
        $this->assertSame(1, $cCount, 'Shared dependency c should appear exactly once');

        $posC = array_search('c', $result, true);
        $posA = array_search('a', $result, true);
        $posB = array_search('b', $result, true);

        $this->assertLessThan($posA, $posC, 'c should come before a');
        $this->assertLessThan($posB, $posC, 'c should come before b');
    }

    public function testResolveDiamondDependency(): void
    {
        //   a
        //  / \
        // b   c
        //  \ /
        //   d
        $this->loadConfig([
            'a' => ['type' => 'target', 'depends' => ['b', 'c']],
            'b' => ['type' => 'library', 'depends' => ['d']],
            'c' => ['type' => 'library', 'depends' => ['d']],
            'd' => ['type' => 'library'],
        ]);

        $result = DependencyResolver::resolve(['a']);

        // d must appear exactly once and before everything
        $dCount = count(array_keys($result, 'd', true));
        $this->assertSame(1, $dCount);

        $posD = array_search('d', $result, true);
        $posB = array_search('b', $result, true);
        $posC = array_search('c', $result, true);
        $posA = array_search('a', $result, true);

        $this->assertLessThan($posB, $posD, 'd should come before b');
        $this->assertLessThan($posC, $posD, 'd should come before c');
        $this->assertLessThan($posA, $posB, 'b should come before a');
        $this->assertLessThan($posA, $posC, 'c should come before a');
    }

    // ──────────────────────────────────────────────
    //  Suggests (optional dependencies)
    // ──────────────────────────────────────────────

    public function testResolveSuggestsAreExcludedByDefault(): void
    {
        // a depends on b, suggests c
        $this->loadConfig([
            'a' => ['type' => 'library', 'depends' => ['b'], 'suggests' => ['c']],
            'b' => ['type' => 'library'],
            'c' => ['type' => 'library'],
        ]);

        $result = DependencyResolver::resolve(['a']);

        // c should NOT be in the resolved list (it's only suggested, not depended)
        $this->assertNotContains('c', $result);
        $this->assertSame(['b', 'a'], $result);
    }

    public function testResolveSuggestsIncludedWhenFlagSet(): void
    {
        $this->loadConfig([
            'a' => ['type' => 'library', 'depends' => ['b'], 'suggests' => ['c']],
            'b' => ['type' => 'library'],
            'c' => ['type' => 'library', 'depends' => ['b']],
        ]);

        $result = DependencyResolver::resolve(['a'], include_suggests: true);

        // c IS a suggest of a and should be included when flag is set
        $this->assertContains('c', $result);
        $posB = array_search('b', $result, true);
        $posC = array_search('c', $result, true);
        $posA = array_search('a', $result, true);
        $this->assertLessThan($posA, $posB, 'b should come before a');
        $this->assertLessThan($posA, $posC, 'c should come before a');
    }

    // ──────────────────────────────────────────────
    //  Virtual-target promotion
    // ──────────────────────────────────────────────

    public function testResolveVirtualTargetPromotesDepsToParent(): void
    {
        // php-cli (virtual-target) depends on [php, ext-ctype]
        // When php-cli is in the input, ext-ctype should be promoted to php's deps
        $this->loadConfig([
            'php-cli' => ['type' => 'virtual-target', 'depends' => ['php', 'ext-ctype']],
            'php' => ['type' => 'target', 'depends' => ['libxml2']],
            'ext-ctype' => ['type' => 'php-extension', 'depends' => []],
            'libxml2' => ['type' => 'library'],
        ]);

        $result = DependencyResolver::resolve(['php-cli']);

        $posPhp = array_search('php', $result, true);
        $posCtype = array_search('ext-ctype', $result, true);
        $posLibxml2 = array_search('libxml2', $result, true);

        $this->assertIsInt($posPhp);
        $this->assertIsInt($posCtype);
        $this->assertIsInt($posLibxml2);

        // ext-ctype was promoted to php's deps, so it must come before php
        $this->assertLessThan($posPhp, $posCtype, 'ext-ctype should come before php (promoted dep)');
        // libxml2 is a native dep of php, so it must also come before php
        $this->assertLessThan($posPhp, $posLibxml2, 'libxml2 should come before php');
    }

    public function testResolveVirtualTargetNotInInputDoesNotPromote(): void
    {
        // php-cli is a virtual-target but NOT in the input request,
        // so its deps should NOT be injected into php
        $this->loadConfig([
            'php-cli' => ['type' => 'virtual-target', 'depends' => ['php', 'ext-ctype']],
            'php' => ['type' => 'target', 'depends' => ['libxml2']],
            'ext-ctype' => ['type' => 'php-extension'],
            'libxml2' => ['type' => 'library'],
        ]);

        // Only php is requested, not php-cli
        $result = DependencyResolver::resolve(['php']);

        // ext-ctype should NOT be in the result since php-cli was not requested
        $this->assertNotContains('ext-ctype', $result);
        $this->assertSame(['libxml2', 'php'], $result);
    }

    // ──────────────────────────────────────────────
    //  Dependency overrides
    // ──────────────────────────────────────────────

    public function testResolveDependencyOverridesAddDeps(): void
    {
        $this->loadConfig([
            'a' => ['type' => 'library'],
            'b' => ['type' => 'library'],
            'c' => ['type' => 'library'],
        ]);

        // Override: a now depends on b and c
        $result = DependencyResolver::resolve(['a'], dependency_overrides: [
            'a' => ['b', 'c'],
        ]);

        $posA = array_search('a', $result, true);
        $posB = array_search('b', $result, true);
        $posC = array_search('c', $result, true);

        $this->assertLessThan($posA, $posB, 'b should come before a (override)');
        $this->assertLessThan($posA, $posC, 'c should come before a (override)');
    }

    public function testResolveDependencyOverridesMergeWithExisting(): void
    {
        $this->loadConfig([
            'a' => ['type' => 'library', 'depends' => ['b']],
            'b' => ['type' => 'library'],
            'c' => ['type' => 'library'],
        ]);

        // a natively depends on b, override adds c
        $result = DependencyResolver::resolve(['a'], dependency_overrides: [
            'a' => ['c'],
        ]);

        $this->assertContains('b', $result);
        $this->assertContains('c', $result);
        $posA = array_search('a', $result, true);
        $posB = array_search('b', $result, true);
        $posC = array_search('c', $result, true);
        $this->assertLessThan($posA, $posB, 'b should come before a');
        $this->assertLessThan($posA, $posC, 'c should come before a');
    }

    // ──────────────────────────────────────────────
    //  Error handling
    // ──────────────────────────────────────────────

    public function testResolveUnknownPackageThrowsException(): void
    {
        $this->loadConfig([
            'zlib' => ['type' => 'library'],
        ]);

        $this->expectException(WrongUsageException::class);
        $this->expectExceptionMessage('does not exist in config');

        DependencyResolver::resolve(['nonexistent']);
    }

    public function testResolveUnregisteredDependencyThrowsException(): void
    {
        // a depends on b, but b is not in the config
        $this->loadConfig([
            'a' => ['type' => 'library', 'depends' => ['b']],
        ]);

        $this->expectException(WrongUsageException::class);
        $this->expectExceptionMessage('not exist');

        DependencyResolver::resolve(['a']);
    }

    // ──────────────────────────────────────────────
    //  Reverse dependency map ($why parameter)
    // ──────────────────────────────────────────────

    public function testReverseDependencyMap(): void
    {
        // a -> b -> c
        $this->loadConfig([
            'a' => ['type' => 'target', 'depends' => ['b']],
            'b' => ['type' => 'library', 'depends' => ['c']],
            'c' => ['type' => 'library'],
        ]);

        $why = [];
        DependencyResolver::resolve(['a'], why: $why);

        $this->assertArrayHasKey('c', $why, 'c is depended upon');
        $this->assertContains('b', $why['c'], 'b depends on c');
        $this->assertArrayHasKey('b', $why, 'b is depended upon');
        $this->assertContains('a', $why['b'], 'a depends on b');
    }

    public function testReverseDependencyMapOnlyIncludesResolvedPackages(): void
    {
        // a -> b -> c, but only requesting a
        // d is not in the resolved set
        $this->loadConfig([
            'a' => ['type' => 'library', 'depends' => ['b']],
            'b' => ['type' => 'library', 'depends' => ['c']],
            'c' => ['type' => 'library'],
            'd' => ['type' => 'library', 'depends' => ['c']], // not in input
        ]);

        $why = [];
        DependencyResolver::resolve(['a'], why: $why);

        // d should NOT appear in the reverse map since it's not in the resolved set
        $this->assertArrayNotHasKey('d', $why);
    }

    // ──────────────────────────────────────────────
    //  getSubDependencies
    // ──────────────────────────────────────────────

    public function testGetSubDependenciesLinearChain(): void
    {
        // a -> b -> c -> d
        $this->loadConfig([
            'a' => ['type' => 'target', 'depends' => ['b']],
            'b' => ['type' => 'library', 'depends' => ['c']],
            'c' => ['type' => 'library', 'depends' => ['d']],
            'd' => ['type' => 'library'],
        ]);

        $subDeps = DependencyResolver::getSubDependencies('a', ['a', 'b', 'c', 'd']);

        // Should return [d, c, b] in dependency order (a not included)
        $this->assertNotContains('a', $subDeps);
        $this->assertSame(['d', 'c', 'b'], $subDeps);
    }

    public function testGetSubDependenciesPackageNotInResolvedSet(): void
    {
        $this->loadConfig([
            'a' => ['type' => 'library', 'depends' => ['b']],
            'b' => ['type' => 'library'],
        ]);

        $subDeps = DependencyResolver::getSubDependencies('nonexistent', ['a', 'b']);

        $this->assertSame([], $subDeps);
    }

    public function testGetSubDependenciesWithSuggests(): void
    {
        $this->loadConfig([
            'a' => ['type' => 'target', 'depends' => ['b'], 'suggests' => ['c']],
            'b' => ['type' => 'library'],
            'c' => ['type' => 'library'],
        ]);

        // Without include_suggests: only b is a sub-dep
        $without = DependencyResolver::getSubDependencies('a', ['a', 'b', 'c'], include_suggests: false);
        $this->assertSame(['b'], $without);

        // With include_suggests: both b and c are sub-deps
        $with = DependencyResolver::getSubDependencies('a', ['a', 'b', 'c'], include_suggests: true);
        $this->assertContains('b', $with);
        $this->assertContains('c', $with);
    }

    public function testGetSubDependenciesOnlyIncludesResolvedDeps(): void
    {
        // a depends on b and c, but c is not in the resolved set
        $this->loadConfig([
            'a' => ['type' => 'target', 'depends' => ['b', 'c']],
            'b' => ['type' => 'library'],
            'c' => ['type' => 'library'],
        ]);

        // c is NOT in the resolved set
        $subDeps = DependencyResolver::getSubDependencies('a', ['a', 'b']);

        $this->assertContains('b', $subDeps);
        $this->assertNotContains('c', $subDeps, 'c is not in the resolved set, should be excluded');
    }

    // ──────────────────────────────────────────────
    //  Edge cases & defensive
    // ──────────────────────────────────────────────

    public function testResolveEmptyInput(): void
    {
        $this->loadConfig([
            'zlib' => ['type' => 'library'],
        ]);

        $result = DependencyResolver::resolve([]);

        $this->assertSame([], $result);
    }

    public function testResolveWithStringAndPackageInstanceMixed(): void
    {
        $this->loadConfig([
            'a' => ['type' => 'library'],
            'b' => ['type' => 'library'],
        ]);

        // Pass one as string, one as a mock Package
        $mockPackage = $this->createMockPackage('a');

        $result = DependencyResolver::resolve([$mockPackage, 'b']);

        $this->assertContains('a', $result);
        $this->assertContains('b', $result);
    }

    public function testResolveDuplicateInputPackages(): void
    {
        // Requesting the same package twice should not duplicate it in output
        $this->loadConfig([
            'zlib' => ['type' => 'library'],
        ]);

        $result = DependencyResolver::resolve(['zlib', 'zlib']);

        $this->assertSame(['zlib'], $result);
    }

    /**
     * Documents the current behavior for circular dependencies.
     * The algorithm does not detect cycles; it silently resolves them
     * using the visited-set to break infinite recursion. This test
     * locks in the current behavior so any intentional change is caught.
     */
    public function testCircularDependencyDoesNotLoopInfinitely(): void
    {
        // a -> b -> a  (circular)
        $this->loadConfig([
            'a' => ['type' => 'library', 'depends' => ['b']],
            'b' => ['type' => 'library', 'depends' => ['a']],
        ]);

        // Must not hang — should complete and return both packages
        $result = DependencyResolver::resolve(['a']);

        $this->assertCount(2, $result);
        $this->assertContains('a', $result);
        $this->assertContains('b', $result);
    }

    // ──────────────────────────────────────────────
    //  Helpers
    // ──────────────────────────────────────────────

    /**
     * Load package configurations directly into PackageConfig.
     * Uses reflection to inject fixture data without needing YAML files on disk.
     *
     * @param array<string, array{type: string, depends?: string[], suggests?: string[]}> $configs
     */
    private function loadConfig(array $configs): void
    {
        $reflection = new \ReflectionClass(PackageConfig::class);
        $property = $reflection->getProperty('package_configs');

        $existing = $property->getValue();
        if (!is_array($existing)) {
            $existing = [];
        }

        foreach ($configs as $name => $config) {
            $existing[$name] = $config;
        }

        $property->setValue(null, $existing);
    }

    /**
     * Reset PackageConfig to empty state.
     */
    private function resetPackageConfig(): void
    {
        $reflection = new \ReflectionClass(PackageConfig::class);
        $property = $reflection->getProperty('package_configs');
        $property->setValue(null, []);
    }

    /**
     * Create a minimal mock Package object that returns a given name.
     */
    private function createMockPackage(string $name): object
    {
        return new class($name) {
            public function __construct(private string $name) {}

            public function getName(): string
            {
                return $this->name;
            }
        };
    }
}
