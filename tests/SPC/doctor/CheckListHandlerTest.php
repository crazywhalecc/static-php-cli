<?php

declare(strict_types=1);

namespace SPC\Tests\doctor;

use PHPUnit\Framework\TestCase;
use SPC\doctor\CheckListHandler;

/**
 * @internal
 */
final class CheckListHandlerTest extends TestCase
{
    public function testRunChecksReturnsListOfCheck(): void
    {
        $list = new CheckListHandler();

        $id = $list->runChecks();
        foreach ($id as $item) {
            $this->assertInstanceOf('SPC\doctor\AsCheckItem', $item);
        }
    }
}
