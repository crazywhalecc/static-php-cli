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
    public static function setupGithubToken(string $method, string $url, array &$headers): void
    {
        $token = getenv('GITHUB_TOKEN');
        if (!$token) {
            logger()->debug('no github token found, skip');
            return;
        }
        if (getenv('GITHUB_USER')) {
            $auth = base64_encode(getenv('GITHUB_USER') . ':' . $token);
            $he = "Authorization: Basic {$auth}";
            if (!in_array($he, $headers)) {
                $headers[] = $he;
            }
            logger()->info("using basic github token for {$method} {$url}");
        } else {
            $auth = $token;
            $he = "Authorization: Bearer {$auth}";
            if (!in_array($he, $headers)) {
                $headers[] = $he;
            }
            logger()->info("using bearer github token for {$method} {$url}");
        }
    }
}
