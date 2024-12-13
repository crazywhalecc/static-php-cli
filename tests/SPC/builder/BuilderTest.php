<?php

declare(strict_types=1);

namespace SPC\Tests\builder;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SPC\builder\BuilderBase;
use SPC\builder\BuilderProvider;
use SPC\builder\Extension;
use SPC\builder\LibraryBase;
use SPC\exception\RuntimeException;
use SPC\exception\WrongUsageException;
use SPC\store\FileSystem;
use SPC\util\CustomExt;
use SPC\util\DependencyUtil;
use Symfony\Component\Console\Input\ArgvInput;

/**
 * @internal
 */
class BuilderTest extends TestCase
{
    private BuilderBase $builder;

    public static function setUpBeforeClass(): void
    {
        BuilderProvider::makeBuilderByInput(new ArgvInput());
        BuilderProvider::getBuilder();
    }

    public function setUp(): void
    {
        $this->builder = BuilderProvider::makeBuilderByInput(new ArgvInput());
        [$extensions, $libs] = DependencyUtil::getExtsAndLibs(['mbregex']);
        $this->builder->proveLibs($libs);
        CustomExt::loadCustomExt();
        foreach ($extensions as $extension) {
            $class = CustomExt::getExtClass($extension);
            $ext = new $class($extension, $this->builder);
            $this->builder->addExt($ext);
        }
        foreach ($this->builder->getExts() as $ext) {
            $ext->checkDependency();
        }
    }

    public function testMakeBuilderByInput(): void
    {
        $this->assertInstanceOf(BuilderBase::class, BuilderProvider::makeBuilderByInput(new ArgvInput()));
        $this->assertInstanceOf(BuilderBase::class, BuilderProvider::getBuilder());
    }

    public function testGetLibAndGetLibs()
    {
        $this->assertIsArray($this->builder->getLibs());
        $this->assertInstanceOf(LibraryBase::class, $this->builder->getLib('onig'));
    }

    public function testGetExtAndGetExts()
    {
        $this->assertIsArray($this->builder->getExts());
        $this->assertInstanceOf(Extension::class, $this->builder->getExt('mbregex'));
    }

    public function testHasCpp()
    {
        // mbregex doesn't have cpp
        $this->assertFalse($this->builder->hasCpp());
    }

    public function testMakeExtensionArgs()
    {
        $this->assertStringContainsString('--enable-mbstring', $this->builder->makeExtensionArgs());
    }

    public function testIsLibsOnly()
    {
        // mbregex is not libs only
        $this->assertFalse($this->builder->isLibsOnly());
    }

    public function testGetPHPVersionID()
    {
        if (file_exists(SOURCE_PATH . '/php-src/main/php_version.h')) {
            $file = SOURCE_PATH . '/php-src/main/php_version.h';
            $cnt = preg_match('/PHP_VERSION_ID (\d+)/m', file_get_contents($file), $match);
            if ($cnt !== 0) {
                $this->assertEquals(intval($match[1]), $this->builder->getPHPVersionID());
            } else {
                $this->expectException(RuntimeException::class);
                $this->builder->getPHPVersionID();
            }
        } else {
            $this->expectException(WrongUsageException::class);
            $this->builder->getPHPVersionID();
        }
    }

    public function testGetPHPVersion()
    {
        if (file_exists(SOURCE_PATH . '/php-src/main/php_version.h')) {
            $file = SOURCE_PATH . '/php-src/main/php_version.h';
            $cnt = preg_match('/PHP_VERSION "(\d+\.\d+\.\d+)"/', file_get_contents($file), $match);
            if ($cnt !== 0) {
                $this->assertEquals($match[1], $this->builder->getPHPVersion());
            } else {
                $this->expectException(RuntimeException::class);
                $this->builder->getPHPVersion();
            }
        } else {
            $this->expectException(WrongUsageException::class);
            $this->builder->getPHPVersion();
        }
    }

    public function testGetPHPVersionFromArchive()
    {
        $lock = file_exists(DOWNLOAD_PATH . '/.lock.json') ? file_get_contents(DOWNLOAD_PATH . '/.lock.json') : false;
        if ($lock === false) {
            $this->assertFalse($this->builder->getPHPVersionFromArchive());
        } else {
            $lock = json_decode($lock, true);
            $file = $lock['php-src']['filename'] ?? null;
            if ($file === null) {
                $this->assertFalse($this->builder->getPHPVersionFromArchive());
            } else {
                $cnt = preg_match('/php-(\d+\.\d+\.\d+)/', $file, $match);
                if ($cnt !== 0) {
                    $this->assertEquals($match[1], $this->builder->getPHPVersionFromArchive());
                } else {
                    $this->assertFalse($this->builder->getPHPVersionFromArchive());
                }
            }
        }
    }

    public function testGetMicroVersion()
    {
        $file = FileSystem::convertPath(SOURCE_PATH . '/php-src/sapi/micro/php_micro.h');
        if (!file_exists($file)) {
            $this->assertFalse($this->builder->getMicroVersion());
        } else {
            $content = file_get_contents($file);
            $ver = '';
            preg_match('/#define PHP_MICRO_VER_MAJ (\d)/m', $content, $match);
            $ver .= $match[1] . '.';
            preg_match('/#define PHP_MICRO_VER_MIN (\d)/m', $content, $match);
            $ver .= $match[1] . '.';
            preg_match('/#define PHP_MICRO_VER_PAT (\d)/m', $content, $match);
            $ver .= $match[1];
            $this->assertEquals($ver, $this->builder->getMicroVersion());
        }
    }

    public static function providerGetBuildTypeName(): array
    {
        return [
            [BUILD_TARGET_CLI, 'cli'],
            [BUILD_TARGET_FPM, 'fpm'],
            [BUILD_TARGET_MICRO, 'micro'],
            [BUILD_TARGET_EMBED, 'embed'],
            [BUILD_TARGET_ALL, 'cli, micro, fpm, embed'],
            [BUILD_TARGET_CLI | BUILD_TARGET_EMBED, 'cli, embed'],
        ];
    }

    /**
     * @dataProvider providerGetBuildTypeName
     */
    public function testGetBuildTypeName(int $target, string $name): void
    {
        $this->assertEquals($name, $this->builder->getBuildTypeName($target));
    }

    public function testGetOption()
    {
        // we cannot assure the option exists, so just tests default value
        $this->assertEquals('foo', $this->builder->getOption('bar', 'foo'));
    }

    public function testGetOptions()
    {
        $this->assertIsArray($this->builder->getOptions());
    }

    public function testSetOptionIfNotExist()
    {
        $this->assertEquals(null, $this->builder->getOption('bar'));
        $this->builder->setOptionIfNotExist('bar', 'foo');
        $this->assertEquals('foo', $this->builder->getOption('bar'));
    }

    public function testSetOption()
    {
        $this->assertEquals(null, $this->builder->getOption('bar'));
        $this->builder->setOption('bar', 'foo');
        $this->assertEquals('foo', $this->builder->getOption('bar'));
    }

    public function testGetEnvString()
    {
        $this->assertIsString($this->builder->getEnvString());
        putenv('TEST_SPC_BUILDER=foo');
        $this->assertStringContainsString('TEST_SPC_BUILDER=foo', $this->builder->getEnvString(['TEST_SPC_BUILDER']));
    }

    public function testValidateLibsAndExts()
    {
        $this->builder->validateLibsAndExts();
        $this->assertTrue(true);
    }

    public static function providerEmitPatchPoint(): array
    {
        return [
            ['before-libs-extract'],
            ['after-libs-extract'],
            ['before-php-extract'],
            ['after-php-extract'],
            ['before-micro-extract'],
            ['after-micro-extract'],
            ['before-exts-extract'],
            ['after-exts-extract'],
            ['before-php-buildconf'],
            ['before-php-configure'],
            ['before-php-make'],
            ['before-sanity-check'],
        ];
    }

    /**
     * @dataProvider providerEmitPatchPoint
     */
    public function testEmitPatchPoint(string $point)
    {
        $code = '<?php if (patch_point() === "' . $point . '") echo "GOOD:' . $point . '";';
        // emulate patch point
        $this->builder->setOption('with-added-patch', ['/tmp/patch-point.' . $point . '.php']);
        FileSystem::writeFile('/tmp/patch-point.' . $point . '.php', $code);
        $this->expectOutputString('GOOD:' . $point);
        $this->builder->emitPatchPoint($point);
    }

    public function testEmitPatchPointNotExists()
    {
        $this->expectOutputRegex('/failed to run/');
        $this->expectException(RuntimeException::class);
        $this->builder->setOption('with-added-patch', ['/tmp/patch-point.not_exsssists.php']);
        $this->builder->emitPatchPoint('not-exists');
    }
}
