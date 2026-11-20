<?php

declare(strict_types=1);

namespace Tests\StaticPHP\Config;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

/**
 * @internal
 */
class OpenSSLSourceTest extends TestCase
{
    public function testReleaseAndMirrorSelectOnlyStableOpenSSL4Archives(): void
    {
        $config = Yaml::parseFile(__DIR__ . '/../../../config/pkg/lib/openssl.yml')['openssl']['artifact'];
        $this->assertTrue($config['source']['prefer-stable']);

        foreach ([
            'openssl-4.1.0.tar.gz' => true,
            'openssl-4.0.3.tar.gz' => true,
            'openssl-3.6.5.tar.gz' => false,
            'openssl-3.5.9.tar.gz' => false,
            'openssl-4.1.0-alpha1.tar.gz' => false,
            'openssl-4.1.0.tar.gz.asc' => false,
            'openssl-4.1.0.tar.gz.sha256' => false,
            'other-openssl-4.1.0.tar.gz' => false,
        ] as $filename => $expected) {
            $this->assertSame($expected, (bool) preg_match('|' . $config['source']['match'] . '|', $filename), $filename);
            $this->assertSame($expected, (bool) preg_match($config['source-mirror']['regex'], '<a href="' . $filename . '">'), $filename);
        }

        preg_match($config['source-mirror']['regex'], '<a href="openssl-4.1.0.tar.gz">', $matches);
        $this->assertSame('openssl-4.1.0.tar.gz', $matches['file']);
        $this->assertSame('4.1.0', $matches['version']);
    }
}
