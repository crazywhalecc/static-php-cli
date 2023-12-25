<?php

declare(strict_types=1);

use Swoole\Coroutine;
use Swoole\Coroutine\Http2\Client;
use Swoole\Coroutine\WaitGroup;
use Swoole\Http2\Request;

assert(function_exists('swoole_cpu_num'));
assert(class_exists('Swoole\Coroutine\Redis'));

co::set([
    'trace_flags' => SWOOLE_TRACE_HTTP2,
    'log_level' => 0,
]);

Swoole\Coroutine\run(function () {
    $domain = 'api.github.com';
    $cli = new Client($domain, 443, true);
    $cli->set([
        'timeout' => -1,
        'ssl_host_name' => $domain,
    ]);
    $cli->connect();
    $req = new Request();
    $req->method = 'GET';
    $req->path = '/repos/webtorrent/webtorrent';
    $req->headers = [
        'host' => $domain,
        'user-agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        'accept' => 'application/json',
        'accept-encoding' => 'gzip',
    ];

    $cli->send($req);
    $response = $cli->recv();
    // var_dump(json_decode($response->data));
    assert($response->statusCode == 200);

    Coroutine::create(function () {
        $content = file_get_contents('https://www.xinhuanet.com/');
        assert(strlen($content) > 0);
    });

    Coroutine::create(function () {
        Coroutine::sleep(1);
    });

    $wg = new WaitGroup();
    $ret = [];
    for ($i = 0; $i < 10; ++$i) {
        $wg->add();
        go(function () use ($wg, $i, &$ret) {
            co::sleep(0.1);
            $ret[$i] = $i;
            $wg->done();
        });
    }
    $wg->wait();
    assert(count($ret) == 10);

    $ip = Swoole\Coroutine\System::gethostbyname('www.baidu.com', AF_INET, 0.5);
    echo 'baidu.com dns: ' . $ip . PHP_EOL;
});
