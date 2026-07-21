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
        $token = getenv('GITHUB_TOKEN');
        if ($token === false || $token === '') {
            return [];
        }
        if (($user = getenv('GITHUB_USER')) !== false && $user !== '') {
            logger()->debug("Using 'GITHUB_TOKEN' with user {$user} for authentication");
            $encoded = base64_encode("{$user}:{$token}");
            spc_add_log_filter([$user, $token, $encoded]);
            return ["Authorization: Basic {$encoded}"];
        }
        logger()->debug("Using 'GITHUB_TOKEN' for authentication");
        spc_add_log_filter($token);
        return ["Authorization: Bearer {$token}"];
    }
}
