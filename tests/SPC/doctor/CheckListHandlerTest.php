<?php

declare(strict_types=1);

namespace SPC\Tests\doctor;

use PHPUnit\Framework\TestCase;
use SPC\doctor\DoctorHandler;

/**
 * @internal
 */
final class CheckListHandlerTest extends TestCase
{
    public function testRunChecksReturnsListOfCheck(): void
    {
        $list = new DoctorHandler();

        $id = $list->getValidCheckList();
        foreach ($id as $item) {
            $this->assertInstanceOf('SPC\doctor\AsCheckItem', $item);
        }
    }
}
