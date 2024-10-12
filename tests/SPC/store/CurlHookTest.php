<?php

declare(strict_types=1);

namespace SPC\Tests\store;

use PHPUnit\Framework\TestCase;
use SPC\store\CurlHook;

/**
 * @internal
 */
class CurlHookTest extends TestCase
{
    public function testSetupGithubToken()
    {
        $header = [];
        CurlHook::setupGithubToken('GET', 'https://example.com', $header);
        if (getenv('GITHUB_TOKEN') === false) {
            $this->assertEmpty($header);
        } else {
            $this->assertEquals(['Authorization: Bearer ' . getenv('GITHUB_TOKEN')], $header);
        }
        $header = [];
        putenv('GITHUB_TOKEN=token');
        CurlHook::setupGithubToken('GET', 'https://example.com', $header);
        $this->assertEquals(['Authorization: Bearer token'], $header);
    }
}
