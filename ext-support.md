# Extension List

> - yes: supported and tested
> - untested: supported but not tested
> - empty: not supported yet
> - faulty with issue link: not supported yet due to issue

|            | Linux    | macOS                                                              | Windows |
|------------|----------|--------------------------------------------------------------------|---------|
| bcmath     | yes      | yes                                                                |         |
| bz2        | yes      | untested                                                           |         |
| calendar   | yes      | yes                                                                |         |
| ctype      | yes      | yes                                                                |         |
| curl       | untested | yes                                                                |         |
| date       |          | yes                                                                |         | 
| dba        | yes      | yes                                                                |         | 
| dom        | untested | untested                                                           |         |
| event      |          |                                                                    |         |
| exif       |          | yes                                                                |         |
| filter     |          | yes                                                                |         |
| fileinfo   |          |                                                                    |         |
| ftp        |          | yes                                                                |         |
| gd         |          | untested                                                           |         |
| gmp        |          | untested                                                           |         |
| hash       | yes      | yes                                                                |         |
| iconv      | untested |                                                                    |         |
| inotify    |          |                                                                    |         |
| json       | yes      | yes                                                                |         |
| libxml     |          | yes                                                                |         |
| mbstring   |          | yes                                                                |         |
| mcrypt     |          | [faulty](https://github.com/crazywhalecc/static-php-cli/issues/32) |         |
| mongodb    |          |                                                                    |         |
| mysqli     |          |                                                                    |         |
| mysqlnd    |          | yes                                                                |         |
| openssl    | untested | yes                                                                |         |
| pcntl      | untested | untested                                                           |         |
| pcre       |          | yes                                                                |         |
| pdo        |          | yes                                                                |         |
| pdo_mysql  |          | yes                                                                |         |
| pdo_sqlite |          | yes                                                                |         |
| pdo_pgsql  |          |                                                                    |         |
| phar       | yes      | yes                                                                |         |
| posix      | yes      | yes                                                                |         |
| protobuf   |          |                                                                    |         |
| readline   |          |                                                                    |         |
| redis      |          | yes                                                                |         |
| Reflection |          | yes                                                                |         |
| session    |          | yes                                                                |         |
| shmop      |          |                                                                    |         |
| simplexml  |          | untested                                                           |         |
| soap       |          |                                                                    |         |
| sockets    |          |                                                                    |         |
| sqlite3    |          | untested                                                           |         |
| swow       |          |                                                                    |         |
| swoole     |          | [faulty](https://github.com/crazywhalecc/static-php-cli/issues/32) |         |
| tokenizer  |          | yes                                                                |         |
| xml        |          | yes                                                                |         |
| xmlreader  |          | untested                                                           |         |
| xmlwriter  |          | untested                                                           |         |
| zip        |          | yes                                                                |         |
| zlib       | yes      | yes                                                                |         |

## Additional Requirements

- phpmicro requires PHP >= 8.0
- swoole >= 5.0 requires PHP >= 8.0
- swow requires PHP >= 8.0

## Bugs

See [#32](https://github.com/crazywhalecc/static-php-cli/issues/32).
