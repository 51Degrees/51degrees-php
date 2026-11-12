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

The engine is built once, when PHP starts, so that no request opens the data
file again, and every on-premise example uses it. Name the data file in
`php.ini` as well, for example the free Lite file:

```ini
FiftyOneDegreesIpIntelligenceEngine.data_file=/full/path/to/cxx/data/ip-intelligence/51Degrees-LiteV41.ipi
```

## Running under a process manager

The engine the extension builds when PHP starts, from the `data_file` setting
above, always holds the whole data file in memory, which is the
`MaxPerformance` performance profile, as device detection does. PHP is
normally run under a process manager, such as Apache MPM prefork or php-fpm,
that forks the worker processes after that engine is built. Every other
profile keeps the data file open, and forking would share those file handles
and their read positions between the workers, which corrupts the results.
Loading the whole file leaves no handle to share, and it uses more memory than
the other profiles, which read parts of the file as they need them.

Leave the `FiftyOneDegreesIpIntelligenceEngine.performance_profile` setting
out, or set it to `MaxPerformance`. Any other profile there raises an
exception when `IpIntelligenceOnPremise` takes up that engine, explaining that
`MaxPerformance` is the only supported profile for it.

`IpIntelligenceOnPremise` still takes a `dataFile` setting, which builds a
new engine from the data file every time the class is constructed, with its
own file handles and any `performanceProfile`. Under a web server PHP
constructs the class in every request, so the data file is opened and read
in every request, the whole file with `MaxPerformance`. Outside the command
line such an engine logs a warning saying so, through the Pipeline's logger.
Use the engine built when PHP starts instead.

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

Name the data file in `php.ini`, as above, and PHP builds the engine from it
when it starts. A console example started without that engine runs again in
a PHP started with a data file, keeping the options PHP was started with
where the system gives them, such as one loading the extension. The data
file is the one in its first argument, the `_51DEGREES_IPI_PATH` environment
variable, `assets/` or `cxx/data/ip-intelligence`, in that order, and when it
finds none it runs the script to download the Lite file. The tests look in
the same places, apart from the argument, and skip when they find no file.
