# Building the on-premise IP intelligence extension

The on-premise IP intelligence engine is a native PHP extension
(`FiftyOneDegreesIpIntelligenceEngine`) generated with [SWIG](https://www.swig.org/)
from the IP intelligence C/C++ sources in 51degrees-cxx (included as the `cxx`
submodule at the root of the repository).

The **cloud** engine does **not** require this extension - only the on-premise
engine does.

## Prerequisites

- PHP 8.0+ with development headers (`php-dev` / `php-devel`, providing `phpize`
  and `php-config`).
- A C++17 compiler (`g++`/`clang++`) and `make`, `autoconf`, `pkg-config`.
- **SWIG 4.1.0 or newer** (required for PHP 8). SWIG 4.1+ registers the wrapped
  classes directly inside the extension, so no separate `.php` proxy file is
  produced or required.

On Ubuntu:

```bash
sudo apt-get install -y php-cli php-dev swig build-essential autoconf pkg-config
```

On Windows, building a PHP extension requires the matching PHP SDK; the supported
route on developer machines is to build under WSL (Ubuntu) as above. 51Degrees'
CI builds the Windows binary under the PHP SDK.

## Initialise the submodules

```bash
git submodule update --init --recursive
```

The `cxx` submodule reads the reduced-file data format used by the current 4.5
`.ipi` files, so the build sets `FIFTYONE_DEGREES_REDUCED_FILE=1` (see
`config.m4`). Its own data submodules (`ip-intelligence-data` and
`device-detection-data`) are initialised by the `--recursive` flag.

## Build

```bash
cd on-premise

# The build looks for the C sources under on-premise/cxx/src, so copy them from
# the cxx submodule first. ci/build-project.ps1 does the same.
mkdir -p cxx && cp -R ../../cxx/src cxx/

# Generate the SWIG wrapper (committed for releases, regenerate after changing
# ipi-php.i or bumping the submodule):
swig -c++ -php -module FiftyOneDegreesIpIntelligenceEngine \
    -outdir src/php8 -o src/php8/ipi_wrap.cxx ipi-php.i
cp src/php8/* .

phpize
./configure --enable-FiftyOneDegreesIpIntelligenceEngine
make -j"$(nproc)"
```

This produces `on-premise/modules/FiftyOneDegreesIpIntelligenceEngine.so`.

The C/C++ sources are compiled with `FIFTYONE_DEGREES_LARGE_DATA_FILE_SUPPORT=1`,
`FIFTYONE_DEGREES_REDUCED_FILE=1` and `_FILE_OFFSET_BITS=64`. These must match the
`cxx` CMake build of the IP intelligence copy of common (the `.NET` and Java
bindings inherit them via CMake); building without them reads modern data files with the wrong struct
layout.

## Enable the extension

```bash
php -d extension="$PWD/modules/FiftyOneDegreesIpIntelligenceEngine.so" your-script.php
```

or add to `php.ini`:

```ini
extension=/full/path/to/FiftyOneDegreesIpIntelligenceEngine.so
```

## Data file

Download a Lite `.ipi` data file (used by the tests and examples):

```bash
curl -L -o assets/51Degrees-LiteIpiV41.ipi.gz \
  https://51ddatafiles.blob.core.windows.net/enterpriseipi/51Degrees-LiteIpiV41.ipi.gz
gzip -d assets/51Degrees-LiteIpiV41.ipi.gz
```

Point the engine at it via the `dataFile` setting, the `IPINTELLIGENCEDATAFILE`
environment variable, or by placing it under `assets/`. The tests and examples
also fall back to the files in `cxx/ip-intelligence-data` at the root of the
repository.
