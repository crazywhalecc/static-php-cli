<?php

declare(strict_types=1);

namespace Tests\StaticPHP\Runtime\Shell;

use PHPUnit\Framework\TestCase;
use StaticPHP\Runtime\Shell\DefaultShell;
use StaticPHP\Util\FileSystem;

/**
 * @internal
 */
class DefaultShellTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/spc-default-shell-test-' . bin2hex(random_bytes(4));
        mkdir("{$this->dir}/app/public", 0755, true);
        file_put_contents("{$this->dir}/app/public/index.php", '<?php echo "ok";');
    }

    protected function tearDown(): void
    {
        FileSystem::removeDir($this->dir);
    }

    public function testExecuteTarCreateArchivesTheDirectoryContents(): void
    {
        $shell = new DefaultShell();
        $shell->executeTarCreate("{$this->dir}/app", "{$this->dir}/app.tar");
        mkdir("{$this->dir}/out");
        $shell->executeTarExtract("{$this->dir}/app.tar", "{$this->dir}/out", 'none', 0);

        $this->assertSame('<?php echo "ok";', file_get_contents("{$this->dir}/out/public/index.php"));
    }
}
