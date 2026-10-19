# Troubleshooting

Various failures may be encountered in the process of using StaticPHP,
here will describe how to check the errors by yourself and report an Issue.

## Download Failure

Problems with downloading resources are one of the most common problems with spc.
The main reason is that the addresses used for SPC download resources are generally the official website of the corresponding project or GitHub, etc.,
and these websites may occasionally go down and block IP addresses.
After encountering a download failure,
you can try to call the download command multiple times.

When downloading extensions, you may eventually see errors like `curl: (56) The requested URL returned error: 403` which are often caused by GitHub rate limiting.
You can verify this by adding `-vvv` to the command and will see something like `[DEBU] Running command (no output) : curl -sfSL   "https://api.github.com/repos/openssl/openssl/releases"`.

To fix this, [create](https://github.com/settings/tokens) a personal access token on GitHub and set it as an environment variable `GITHUB_TOKEN=<XXX>`.

If you confirm that the address is indeed inaccessible,
you can submit an Issue or PR to update the url or download type.

## Download Through a Proxy

If your network requires a proxy to reach the download sources, you can either set `ALL_PROXY`
directly, or use `SPC_PROXY`:

```shell
# curl and git already honour ALL_PROXY by themselves
ALL_PROXY="socks5h://127.0.0.1:1080" spc download --for-extensions="curl,openssl"

# SPC_PROXY additionally covers the Go toolchain (FrankenPHP), which ignores ALL_PROXY
SPC_PROXY="socks5h://127.0.0.1:1080" spc build:php "curl" --build-cli
```

`socks5h` resolves hostnames on the proxy side, which is usually what you want.
StaticPHP passes the value to curl and git as `ALL_PROXY`, and to Go as `HTTP_PROXY`/`HTTPS_PROXY`
(rewriting `socks5h://` to `socks5://`, the only SOCKS form Go understands).

To make the setting permanent, add it to `config/env.ini` or `config/env.custom.ini`:

```ini
[global]
SPC_PROXY="socks5h://127.0.0.1:1080"
```

## Doctor Can't Fix Something

In most cases, the doctor module can automatically repair and install missing system environments,
but there are also special circumstances where the automatic repair function cannot be used normally.

Due to system limitations (for example, software such as Visual Studio cannot be automatically installed under Windows),
the automatic repair function cannot be used for some projects.
When encountering a function that cannot be automatically repaired,
if you encounter the words `Some check items can not be fixed`,
it means that it cannot be automatically repaired.
Please submit an issue according to the method displayed on the terminal or repair the environment yourself.

## Compile Error

When you encounter a compilation error, if the `-vvv` log is not enabled, please enable the debug log first,
and then determine the command that reported the error.
The error terminal output is very important for fixing compilation errors.
When submitting an issue, please upload the last error fragment of the terminal log (or the entire terminal log output),
and include the `spc` command and parameters used.

If you are rebuilding, please refer to the [First Build - Debugging and Rebuilding](./first-build#debugging-and-rebuilding) section.

