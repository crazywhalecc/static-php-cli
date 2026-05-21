<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use ZM\Logger\ConsoleLogger;

/**
 * @internal
 */
class GlobalsFunctionsTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['spc_log_filters'] = null;
    }

    protected function tearDown(): void
    {
        $GLOBALS['spc_log_filters'] = null;
    }

    public function testAddLogFilterDeduplicates(): void
    {
        spc_add_log_filter('secret-value');
        spc_add_log_filter('secret-value');
        spc_add_log_filter(['secret-value', 'other']);

        $this->assertSame(['secret-value', 'other'], $GLOBALS['spc_log_filters']);
    }

    public function testWriteLogMasksRegisteredValues(): void
    {
        spc_add_log_filter(['octocat', 'ghp_abcdef1234567890']);

        $stream = fopen('php://memory', 'r+');
        spc_write_log($stream, 'user=octocat token=ghp_abcdef1234567890');
        rewind($stream);
        $written = stream_get_contents($stream);
        fclose($stream);

        $this->assertSame('user=*** token=***', $written);
    }

    public function testLoggerCallbackMasksOutput(): void
    {
        $token = 'ghp_abcdef1234567890';
        spc_add_log_filter($token);

        $stream = fopen('php://memory', 'r+');
        $logger = new ConsoleLogger(LogLevel::DEBUG, $stream, false);
        $logger->addLogCallback(function ($level, &$output, &$message, &$context, bool $shouldLog) {
            global $spc_log_filters;
            if (!is_array($spc_log_filters)) {
                $spc_log_filters = [];
            }
            $output = str_replace($spc_log_filters, '***', $output);
            $message = str_replace($spc_log_filters, '***', $message);
            $context = array_map(function ($item) use ($spc_log_filters) {
                if (is_string($item)) {
                    return str_replace($spc_log_filters, '***', $item);
                }
                return $item;
            }, $context);
            return true;
        });

        $logger->debug("[PASSTHRU] curl -H\"Authorization: Bearer {$token}\" https://api.github.com/x");

        rewind($stream);
        $written = stream_get_contents($stream);
        fclose($stream);

        $this->assertStringNotContainsString($token, $written);
        $this->assertStringContainsString('***', $written);
    }

    public function testGitHubTokenTraitRegistersEncodedBasicAuthBlob(): void
    {
        $user = 'octocat';
        $token = 'ghp_abcdef1234567890';
        $original_token = getenv('GITHUB_TOKEN');
        $original_user = getenv('GITHUB_USER');

        putenv("GITHUB_TOKEN={$token}");
        putenv("GITHUB_USER={$user}");

        try {
            $headers = \StaticPHP\Artifact\Downloader\Type\GitHubRelease::getGitHubTokenHeadersStatic();

            $encoded = base64_encode("{$user}:{$token}");
            $this->assertSame(["Authorization: Basic {$encoded}"], $headers);
            $this->assertContains($user, $GLOBALS['spc_log_filters']);
            $this->assertContains($token, $GLOBALS['spc_log_filters']);
            $this->assertContains($encoded, $GLOBALS['spc_log_filters']);
        } finally {
            $original_token === false ? putenv('GITHUB_TOKEN') : putenv("GITHUB_TOKEN={$original_token}");
            $original_user === false ? putenv('GITHUB_USER') : putenv("GITHUB_USER={$original_user}");
        }
    }
}
