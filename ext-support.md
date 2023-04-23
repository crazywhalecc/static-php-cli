# Extension List

> - yes: supported and tested
> - untested: supported, but not tested
> - empty: not supported yet
> - no with issue link: not supported yet due to issue
> - partial with issue link: supported but not perfect due to issue

|            | Linux                                                          | macOS                                                               | Windows |
|------------|----------------------------------------------------------------|---------------------------------------------------------------------|---------|
| bcmath     | yes                                                            | yes                                                                 |         |
| bz2        | yes                                                            | yes                                                                 |         |
| calendar   | yes                                                            | yes                                                                 |         |
| ctype      | yes                                                            | yes                                                                 |         |
| curl       | yes                                                            | yes                                                                 |         |
| dba        | yes                                                            | yes                                                                 |         | 
| dom        | yes                                                            | yes                                                                 |         |
| enchant    |                                                                |                                                                     |         |
| event      |                                                                |                                                                     |         |
| exif       | yes                                                            | yes                                                                 |         |
| filter     | yes                                                            | yes                                                                 |         |
| fileinfo   | yes                                                            |                                                                     |         |
| ftp        | yes                                                            | yes                                                                 |         |
| gd         | yes, untested                                                  | yes                                                                 |         |
| gettext    |                                                                |                                                                     |         |
| gmp        | yes, untested                                                  | yes, untested                                                       |         |
| iconv      | yes                                                            |                                                                     |         |
| inotify    | yes                                                            | yes                                                                 |         |
| mbstring   | yes                                                            | yes                                                                 |         |
| mcrypt     |                                                                | [faulty](https://github.com/crazywhalecc/static-php-cli/issues/32)  |         |
| mongodb    | yes, untested                                                  |                                                                     |         |
| mysqli     |                                                                |                                                                     |         |
| mysqlnd    | yes                                                            | yes                                                                 |         |
| openssl    | yes                                                            | yes                                                                 |         |
| pcntl      | yes, untested                                                  | yes                                                                 |         |
| pdo        | yes                                                            | yes                                                                 |         |
| pdo_mysql  | yes                                                            | yes                                                                 |         |
| pdo_sqlite | yes                                                            | yes                                                                 |         |
| pdo_pgsql  |                                                                |                                                                     |         |
| phar       | yes                                                            | yes                                                                 |         |
| posix      | yes                                                            | yes                                                                 |         |
| protobuf   | yes, untested                                                  |                                                                     |         |
| readline   |                                                                |                                                                     |         |
| redis      | yes                                                            | yes                                                                 |         |
| session    | yes                                                            | yes                                                                 |         |
| shmop      | yes, untested                                                  |                                                                     |         |
| simplexml  | yes, untested                                                  | yes, untested                                                       |         |
| soap       | yes, untested                                                  |                                                                     |         |
| sockets    | yes                                                            | yes                                                                 |         |
| sqlite3    | yes, untested                                                  | yes, untested                                                       |         |
| swow       | yes                                                            | [no](https://github.com/crazywhalecc/static-php-cli/issues/32)      |         |
| swoole     | [no](https://github.com/crazywhalecc/static-php-cli/issues/32) | [partial](https://github.com/crazywhalecc/static-php-cli/issues/32) |         |
| tokenizer  | yes                                                            | yes                                                                 |         |
| xml        | yes                                                            | yes                                                                 |         |
| xmlreader  | yes, untested                                                  | yes, untested                                                       |         |
| xmlwriter  | yes, untested                                                  | yes, untested                                                       |         |
| zip        | yes, untested                                                  | yes                                                                 |         |
| zlib       | yes                                                            | yes                                                                 |         |

## Additional Requirements

- phpmicro requires PHP >= 8.0
- swoole >= 5.0 requires PHP >= 8.0
- swow requires PHP >= 8.0

## Typical Extension List Example

Here are some extension list example for different use.

- For general use: `"bcmath,bz2,calendar,ctype,curl,dom,exif,fileinfo,ftp,filter,gd,iconv,xml,mbstring,mysqlnd,openssl,pcntl,pdo,pdo_mysql,pdo_sqlite,phar,posix,redis,simplexml,soap,sockets,sqlite3,tokenizer,xmlwriter,xmlreader,zlib,zip"`
- For static-php-cli self: `"posix,pcntl,phar,tokenizer,iconv,zlib"`
- Minimum, with no extension: `""`

## Limitations

- swow and swoole cannot be compiled at the same time.
- openssl needs manual configuration for ssl certificate. (TODO: I will write a wiki about it)
- some extensions need system configuration, e.g. `curl` and `openssl` will search ssl certificate on your system.

## Bugs and TODOs

See [#32](https://github.com/crazywhalecc/static-php-cli/issues/32).
