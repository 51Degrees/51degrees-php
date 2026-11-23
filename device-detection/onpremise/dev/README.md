# Local development helpers

## Building and testing locally with Docker

The on-premise extension is a Linux/macOS build, so the easiest way to compile
and test it on Windows is from WSL with Docker.

```bash
# from a WSL shell, at the repository root
git submodule update --init --recursive
sh cxx/data/get-data-files.sh 51Degrees-LiteV4.1.hash
device-detection/onpremise/dev/docker-build-test.sh        # defaults to PHP 8.3
device-detection/onpremise/dev/docker-build-test.sh 7.4    # or pick a PHP version
```

The script compiles the extension in a clean `php:<version>-cli` image (no host
toolchain or licensed data file needed) and runs the fork-safety reproduction
for [issue #38](https://github.com/51Degrees/device-detection-php-onpremise/issues/38)
against the free Lite data file, which the second command downloads into
`cxx/data/device-detection`.

A successful run ends with `PASS` and shows that a streaming profile keeps an
open file handle and corrupts under a fork, while the in-memory `MaxPerformance`
profile keeps no handle and stays correct. That is why the module forces
`MaxPerformance` for PHP. See `configuration-tests/testForkSafety.php`.
