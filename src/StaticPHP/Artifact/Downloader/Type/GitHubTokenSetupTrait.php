<?php

declare(strict_types=1);

namespace StaticPHP\Artifact\Downloader\Type;

trait GitHubTokenSetupTrait
{
    public function getGitHubTokenHeaders(): array
    {
        return self::getGitHubTokenHeadersStatic();
    }

    public static function getGitHubTokenHeadersStatic(): array
    {
        // GITHUB_TOKEN support
        if (($token = getenv('GITHUB_TOKEN')) !== false && ($user = getenv('GITHUB_USER')) !== false) {
            logger()->debug("Using 'GITHUB_TOKEN' with user {$user} for authentication");
            return ['Authorization: Basic ' . base64_encode("{$user}:{$token}")];
        }
        if (($token = getenv('GITHUB_TOKEN')) !== false) {
            logger()->debug("Using 'GITHUB_TOKEN' for authentication");
            return ["Authorization: Bearer {$token}"];
        }
        return [];
    }
}
