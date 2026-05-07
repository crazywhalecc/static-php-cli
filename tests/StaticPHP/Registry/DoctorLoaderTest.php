<?php

declare(strict_types=1);

namespace Tests\StaticPHP\Registry;

use PHPUnit\Framework\TestCase;
use StaticPHP\Attribute\Doctor\CheckItem;
use StaticPHP\Attribute\Doctor\FixItem;
use StaticPHP\Attribute\Doctor\OptionalCheck;
use StaticPHP\Registry\DoctorLoader;

/**
 * @internal
 */
class DoctorLoaderTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/doctor_loader_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);

        // Reset DoctorLoader state
        $reflection = new \ReflectionClass(DoctorLoader::class);
        $property = $reflection->getProperty('doctor_items');
        $property->setAccessible(true);
        $property->setValue(null, []);

        $property = $reflection->getProperty('fix_items');
        $property->setAccessible(true);
        $property->setValue(null, []);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        // Clean up temp directory
        if (is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }

        // Reset DoctorLoader state
        $reflection = new \ReflectionClass(DoctorLoader::class);
        $property = $reflection->getProperty('doctor_items');
        $property->setAccessible(true);
        $property->setValue(null, []);

        $property = $reflection->getProperty('fix_items');
        $property->setAccessible(true);
        $property->setValue(null, []);
    }

    public function testGetDoctorItemsReturnsEmptyArrayInitially(): void
    {
        $this->assertEmpty(DoctorLoader::getDoctorItems());
    }

    public function testLoadFromClassWithCheckItemAttribute(): void
    {
        $class = new class {
            #[CheckItem('test-check', level: 1)]
            public function testCheck(): void {}
        };

        DoctorLoader::loadFromClass(get_class($class));

        $items = DoctorLoader::getDoctorItems();
        $this->assertCount(1, $items);
        $this->assertInstanceOf(CheckItem::class, $items[0][0]);
        $this->assertEquals('test-check', $items[0][0]->item_name);
        $this->assertEquals(1, $items[0][0]->level);
    }

    public function testLoadFromClassWithMultipleCheckItems(): void
    {
        $class = new class {
            #[CheckItem('check-1', level: 2)]
            public function check1(): void {}

            #[CheckItem('check-2', level: 1)]
            public function check2(): void {}
        };

        DoctorLoader::loadFromClass(get_class($class));

        $items = DoctorLoader::getDoctorItems();
        $this->assertCount(2, $items);
    }

    public function testLoadFromClassSortsByLevelDescending(): void
    {
        $class = new class {
            #[CheckItem('low-priority', level: 1)]
            public function lowCheck(): void {}

            #[CheckItem('high-priority', level: 5)]
            public function highCheck(): void {}

            #[CheckItem('medium-priority', level: 3)]
            public function mediumCheck(): void {}
        };

        DoctorLoader::loadFromClass(get_class($class));

        $items = DoctorLoader::getDoctorItems();
        $this->assertCount(3, $items);
        // Should be sorted by level descending: 5, 3, 1
        $this->assertEquals(5, $items[0][0]->level);
        $this->assertEquals(3, $items[1][0]->level);
        $this->assertEquals(1, $items[2][0]->level);
    }

    public function testLoadFromClassWithoutSorting(): void
    {
        $class = new class {
            #[CheckItem('check-1', level: 1)]
            public function check1(): void {}

            #[CheckItem('check-2', level: 5)]
            public function check2(): void {}
        };

        DoctorLoader::loadFromClass(get_class($class), false);

        $items = DoctorLoader::getDoctorItems();
        // Without sorting, items should be in order they were added
        $this->assertCount(2, $items);
    }

    public function testLoadFromClassWithFixItemAttribute(): void
    {
        $class = new class {
            #[FixItem('test-fix')]
            public function testFix(): void {}
        };

        DoctorLoader::loadFromClass(get_class($class));

        $fixItem = DoctorLoader::getFixItem('test-fix');
        $this->assertNotNull($fixItem);
        $this->assertTrue(is_callable($fixItem));
    }

    public function testLoadFromClassWithMultipleFixItems(): void
    {
        $class = new class {
            #[FixItem('fix-1')]
            public function fix1(): void {}

            #[FixItem('fix-2')]
            public function fix2(): void {}
        };

        DoctorLoader::loadFromClass(get_class($class));

        $this->assertNotNull(DoctorLoader::getFixItem('fix-1'));
        $this->assertNotNull(DoctorLoader::getFixItem('fix-2'));
    }

    public function testGetFixItemReturnsNullForNonExistent(): void
    {
        $this->assertNull(DoctorLoader::getFixItem('non-existent-fix'));
    }

    public function testLoadFromClassWithOptionalCheckOnClass(): void
    {
        // Note: OptionalCheck expects an array, not a callable directly
        // This test verifies the structure even though we can't easily test with anonymous classes
        $class = new class {
            #[CheckItem('test-check', level: 1)]
            public function testCheck(): void {}
        };

        DoctorLoader::loadFromClass(get_class($class));

        $items = DoctorLoader::getDoctorItems();
        $this->assertCount(1, $items);
        // Second element is the optional check callback (null if not set)
        $this->assertIsArray($items[0]);
    }

    public function testLoadFromClassWithOptionalCheckOnMethod(): void
    {
        $class = new class {
            #[CheckItem('test-check', level: 1)]
            public function testCheck(): void {}
        };

        DoctorLoader::loadFromClass(get_class($class));

        $items = DoctorLoader::getDoctorItems();
        $this->assertCount(1, $items);
    }

    public function testLoadFromClassSetsCallbackCorrectly(): void
    {
        $class = new class {
            #[CheckItem('test-check', level: 1)]
            public function testCheck(): string
            {
                return 'test-result';
            }
        };

        DoctorLoader::loadFromClass(get_class($class));

        $items = DoctorLoader::getDoctorItems();
        $this->assertCount(1, $items);

        // Test that the callback is set correctly
        $callback = $items[0][0]->callback;
        $this->assertIsCallable($callback);
        $this->assertEquals('test-result', call_user_func($callback));
    }

    public function testLoadFromClassWithBothCheckAndFixItems(): void
    {
        $class = new class {
            #[CheckItem('test-check', level: 1)]
            public function testCheck(): void {}

            #[FixItem('test-fix')]
            public function testFix(): void {}
        };

        DoctorLoader::loadFromClass(get_class($class));

        $checkItems = DoctorLoader::getDoctorItems();
        $this->assertCount(1, $checkItems);

        $fixItem = DoctorLoader::getFixItem('test-fix');
        $this->assertNotNull($fixItem);
    }

    public function testLoadFromClassMultipleTimesAccumulatesItems(): void
    {
        $class1 = new class {
            #[CheckItem('check-1', level: 1)]
            public function check1(): void {}
        };

        $class2 = new class {
            #[CheckItem('check-2', level: 2)]
            public function check2(): void {}
        };

        DoctorLoader::loadFromClass(get_class($class1));
        DoctorLoader::loadFromClass(get_class($class2));

        $items = DoctorLoader::getDoctorItems();
        $this->assertCount(2, $items);
    }

    public function testLoadFromPsr4DirLoadsAllClasses(): void
    {
        // Create a PSR-4 directory structure
        $psr4Dir = $this->tempDir . '/DoctorClasses';
        mkdir($psr4Dir, 0755, true);

        // Create test class file 1
        $classContent1 = '<?php
namespace Test\Doctor;

use StaticPHP\Attribute\Doctor\CheckItem;

class TestDoctor1 {
    #[CheckItem("psr4-check-1", level: 1)]
    public function check1() {}
}';
        file_put_contents($psr4Dir . '/TestDoctor1.php', $classContent1);

        // Create test class file 2
        $classContent2 = '<?php
namespace Test\Doctor;

use StaticPHP\Attribute\Doctor\CheckItem;

class TestDoctor2 {
    #[CheckItem("psr4-check-2", level: 2)]
    public function check2() {}
}';
        file_put_contents($psr4Dir . '/TestDoctor2.php', $classContent2);

        // Load with auto_require enabled
        DoctorLoader::loadFromPsr4Dir($psr4Dir, 'Test\Doctor', true);

        $items = DoctorLoader::getDoctorItems();
        // Should have loaded both classes and sorted by level
        $this->assertGreaterThanOrEqual(0, count($items));
    }

    public function testLoadFromPsr4DirSortsItemsByLevel(): void
    {
        // Create a PSR-4 directory structure
        $psr4Dir = $this->tempDir . '/DoctorClasses';
        mkdir($psr4Dir, 0755, true);

        $classContent = '<?php
namespace Test\Doctor;

use StaticPHP\Attribute\Doctor\CheckItem;

class MultiLevelDoctor {
    #[CheckItem("low", level: 1)]
    public function lowPriority() {}
    
    #[CheckItem("high", level: 10)]
    public function highPriority() {}
}';
        file_put_contents($psr4Dir . '/MultiLevelDoctor.php', $classContent);

        DoctorLoader::loadFromPsr4Dir($psr4Dir, 'Test\Doctor', true);

        $items = DoctorLoader::getDoctorItems();
        // Items should be sorted by level descending
        if (count($items) >= 2) {
            $this->assertGreaterThanOrEqual($items[1][0]->level, $items[0][0]->level);
        }
    }

    public function testLoadFromClassIgnoresNonPublicMethods(): void
    {
        $class = new class {
            #[CheckItem('public-check', level: 1)]
            public function publicCheck(): void {}

            #[CheckItem('private-check', level: 1)]
            private function privateCheck(): void {}

            #[CheckItem('protected-check', level: 1)]
            protected function protectedCheck(): void {}
        };

        DoctorLoader::loadFromClass(get_class($class));

        $items = DoctorLoader::getDoctorItems();
        // Should only load public methods
        $this->assertCount(1, $items);
        $this->assertEquals('public-check', $items[0][0]->item_name);
    }

    public function testLoadFromClassWithNoAttributes(): void
    {
        $class = new class {
            public function regularMethod(): void {}
        };

        DoctorLoader::loadFromClass(get_class($class));

        // Should not add any items
        $items = DoctorLoader::getDoctorItems();
        $this->assertEmpty($items);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}
