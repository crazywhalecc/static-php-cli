<?php

declare(strict_types=1);

namespace SPC\store;

class CurlHook
{
    /**
     * 执行 GitHub Token 的 Curl 头添加
     *
     * @param string $method  修改的 method
     * @param string $url     修改的链接
     * @param array  $headers 修改的 headers
     */
    public static function setupGithubToken(string &$method, string &$url, array &$headers): void
    {
        if (!getenv('GITHUB_TOKEN')) {
            return;
        }
        if (getenv('GITHUB_USER')) {
            $auth = base64_encode(getenv('GITHUB_USER') . ':' . getenv('GITHUB_TOKEN'));
            $headers[] = "Authorization: Basic {$auth}";
            logger()->info("using basic github token for {$method} {$url}");
        } else {
            $auth = getenv('GITHUB_TOKEN');
            $headers[] = "Authorization: Bearer {$auth}";
            logger()->info("using bearer github token for {$method} {$url}");
        }
    }
}
