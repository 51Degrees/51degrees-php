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
`config.m4`). Its `data` submodule, which holds the translations and the
links to the free data files, is initialised by the `--recursive` flag.

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

The tests and examples use the free Lite `.ipi` data file. Download it into
`cxx/data/ip-intelligence` with the
[data](https://github.com/51Degrees/data) repository's script, from the root
of the repository:

```bash
sh cxx/data/get-data-files.sh 51Degrees-LiteV41.ipi
```

On Windows, run `cxx/data/get-data-files.ps1 51Degrees-LiteV41.ipi` in
PowerShell instead. The script downloads the file from the 51Degrees
Distributor, https://distributor.51degrees.com/api/v2/free/ipi-lite-v41,
which always serves the latest Lite file and needs no License Key.

Give the engine a data file with the `dataFile` setting. The on-premise
examples take the file from their first argument, the
`IPINTELLIGENCEDATAFILE` environment variable, `assets/` or
`cxx/data/ip-intelligence`, in that order. When they find none, they run the
script to download the Lite file. The tests look in the same places, apart
from the argument, and skip when they find no file.
