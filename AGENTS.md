# Notes for automated changes

If you have been asked to get an example running, follow
[Run the examples](#run-the-examples). It gives every step in order, from a
fresh clone to the output. The sections after it are notes on what is not
obvious from the code, for changing it. The [README](README.md) describes the
packages, the mirrors and the releases.

## Run the examples

Run each command from the repository root unless the step says otherwise.
Device Detection's on-premise `gettingStartedConsole.php` is the quickest
example to show, because its data file is about 35 MB. IP Intelligence's
on-premise `gettingstarted-console.php` needs a download of about 230 MB that
unpacks to about 460 MB, so run it second.

Each on-premise example needs a PHP extension built from the C code in `cxx`.
CI builds the extensions on Linux and macOS only (`Get-SkipReason` in
`ci/packages.ps1`), and the product readmes recommend the Windows Subsystem
for Linux on Windows. So on Windows, clone and run everything inside WSL, and
use the Linux commands below.

### Prerequisites

- Git, and [Git LFS](https://git-lfs.github.com/) for the Device Detection
  data file.
- PHP 8.0 or later, which the two on-premise packages require, with
  `phpize` and `php-config`, and [Composer](https://getcomposer.org/). CI
  tests PHP 8.0 to 8.5 on Linux and 8.1 to 8.5 on macOS (`ci/options.json`).
- A C and C++ compiler and `make`. On Ubuntu, `ip-intelligence/OnPremise.md`
  gives `sudo apt-get install -y php-cli php-dev swig build-essential autoconf pkg-config`,
  and `device-detection/onpremise/readme.md` adds `libatomic1`.
- SWIG 4.1 or later for IP Intelligence, which generates its wrapper when it
  builds. On macOS, CI installs it with `brew install swig`.
- For the IP Intelligence data file, PowerShell 7 (`pwsh`), or `curl`,
  `gunzip` and `md5sum` (`md5` on macOS) for the shell script.

Clone the repository with its submodules.

```bash
git clone --recurse-submodules https://github.com/51Degrees/51degrees-php.git
cd 51degrees-php
```

In a clone made without `--recurse-submodules`, fetch the submodules.

```bash
git submodule update --init --recursive
```

### Data files

The examples use the free Lite data files, which need no licence key. They
come from the `cxx` submodule, and two layouts exist. Read the "Getting the
code" section of `README.md` in your checkout to find out which one applies.

- **The README says to run `git lfs pull`.** This is the layout on `rc` and
  `main` today. The files are in `cxx/device-detection-data` and
  `cxx/ip-intelligence-data`. Follow "Without cxx/data".
- **The README says to run `cxx/data/get-data-files.sh`.** The draft pull
  request [#23](https://github.com/51Degrees/51degrees-php/pull/23) moves the
  examples to the [data](https://github.com/51Degrees/data) repository, which
  cxx carries as `cxx/data`. Once it is merged, follow "With cxx/data".

Go by the README rather than by whether `cxx/data` exists, because the folder
can arrive with a newer `cxx` before the examples here read it.

#### Without cxx/data

The Device Detection Lite file is stored with Git LFS. Install Git LFS, for
example with `sudo apt-get install git-lfs` on Ubuntu, then pull the file in
its submodule.

```bash
git lfs install
cd cxx/device-detection-data
git lfs pull
cd ../..
```

A script in `cxx/ip-intelligence-data` downloads the IP Intelligence Lite
file. Run it from inside that folder, because it saves the file in the folder
it runs from.

```bash
cd cxx/ip-intelligence-data
./get-lite-file-from-azure.sh
cd ../..
```

`pwsh ./get-lite-file-from-azure.ps1` does the same. Either script also
downloads the Asn file over the copy committed in the submodule, so
`git status` then shows the submodule as modified, which is expected.

#### With cxx/data

```bash
sh cxx/data/get-data-files.sh 51Degrees-LiteV4.1.hash 51Degrees-LiteV41.ipi
```

The script downloads the two Lite files into `cxx/data/device-detection` and
`cxx/data/ip-intelligence`, and keeps a file that is already there.

#### Check the files

- `cxx/device-detection-data/51Degrees-LiteV4.1.hash` is more than 10 MB. A
  file of about 130 bytes that starts with
  `version https://git-lfs.github.com/spec/v1` is a Git LFS pointer, which
  means Git LFS has not pulled the file.
- `cxx/ip-intelligence-data/51Degrees-LiteV41.ipi` is more than 100 MB.

With `cxx/data`, the same files are in `cxx/data/device-detection` and
`cxx/data/ip-intelligence`. Use those paths in the steps below.

### Device Detection

Build the extension, as the README and the package's `ci/build-project.ps1`
do. The build reads the C sources from a copy in `on-premise/cxx/src`.

```bash
cd device-detection/onpremise/on-premise
rm -rf cxx && mkdir cxx && cp -R ../../../cxx/src cxx/src
phpize
./configure
make
cd ..
```

The engine loads its data file when PHP starts, from the
`FiftyOneDegreesHashEngine.data_file` setting, so the example takes no data
file argument. Write the settings `ci/build-project.ps1` writes into a
`php.ini` in `device-detection/onpremise`, then install the package's own
dependencies, as CI does for this package.

```bash
cat > php.ini <<EOF
extension = "$PWD/on-premise/modules/FiftyOneDegreesHashEngine.so"
FiftyOneDegreesHashEngine.data_file = "$PWD/../../cxx/device-detection-data/51Degrees-LiteV4.1.hash"
FiftyOneDegreesHashEngine.allow_unmatched = false
EOF
composer install
```

Run the example with PHP reading that file as well as its own settings, as
`ci/run-unit-tests.ps1` sets `PHP_INI_SCAN_DIR`.

```bash
PHP_INI_SCAN_DIR=":$PWD" php examples/onpremise/gettingStartedConsole.php
cd ../..
```

### IP Intelligence

Build the extension, as `ip-intelligence/OnPremise.md` and the package's
`ci/build-project.ps1` do.

```bash
cd ip-intelligence/on-premise
rm -rf cxx && mkdir cxx && cp -R ../../cxx/src cxx/src
swig -c++ -php -module FiftyOneDegreesIpIntelligenceEngine -outdir src/php8 -o src/php8/ipi_wrap.cxx ipi-php.i
cp src/php8/* .
phpize
./configure --enable-FiftyOneDegreesIpIntelligenceEngine
make
cd ..
composer install
```

Load the extension with `-d`, as `ip-intelligence/OnPremise.md` shows, and
give the Lite file as the first argument.

```bash
php -d extension="$PWD/on-premise/modules/FiftyOneDegreesIpIntelligenceEngine.so" examples/onpremise/gettingstarted-console.php "$PWD/../cxx/ip-intelligence-data/51Degrees-LiteV41.ipi"
cd ..
```

Give the path, because without one the example searches
`ip-intelligence/assets` and then `cxx/ip-intelligence-data` for the names in
`IPI_DATA_FILE_NAMES` in `examples/classes/ExampleConstants.php`. The Lite
file's name, `51Degrees-LiteV41.ipi`, is not one of them, and the Asn file
committed in `cxx/ip-intelligence-data` is, so the example would use the Asn
file. The `IPINTELLIGENCEDATAFILE` environment variable can name the file
instead.

### Correct output

The Device Detection example processes three sets of evidence. For each it
prints `Input values:` and the evidence, then `Results:` with `Mobile Device`,
`Platform Name`, `Platform Version`, `Browser Name` and `Browser Version`, all
of which the Lite file has. It prints nothing about the data file, because
the messages go to `fiftyone\pipeline\core\Logger`, whose `logInternal` writes
nothing.

The IP Intelligence example looks up four addresses, being
`116.154.188.222`, `1.3.32.31`, `45.236.48.61` and
`2001:0db8:085a:0000:0000:8a2e:0370:7334`. For each it prints `Input values:`
with the address, then `Results:` with the 16 properties in `OUTPUT_PROPERTIES`,
from `RegisteredName` to `TimeZoneOffset`. A weighted value prints as the
value followed by its raw weighting in brackets. The Lite file has only
RegisteredCountry, RegisteredName and RegisteredOwner. `outputProperty` in
`examples/classes/ExampleUtils.php` catches the error a missing property
raises, so the other lines read `(not available - ...)` and the example
carries on.

The paid data files have many more properties, as the
[pricing page](https://51degrees.com/pricing?utm_source=github&utm_medium=docs&utm_campaign=51degrees-php&utm_content=agents.md&utm_term=correct-output)
describes.

### Cloud examples

The cloud examples need no extension and no data file, but they need a
resource key, which a person creates in the configurator, so ask for one if
none is set. The Device Detection example says a key with the properties it
needs can be created
[here](https://configure.51degrees.com/g3gMZdPY?utm_source=github&utm_medium=docs&utm_campaign=51degrees-php&utm_content=agents.md&utm_term=cloud-examples).

`device-detection/cloud/readme.md` runs the example from the package's
`examples` folder after a `composer install` in the package.

```bash
cd device-detection/cloud
composer install
cd examples
resource_key=YOUR_RESOURCE_KEY php cloud/gettingStartedConsole.php
cd ../../..
```

The example reads `_51DEGREES_RESOURCE_KEY` first, then `resource_key`, or
takes the key as its first argument. It prints the same `Input values:` and
`Results:` blocks. The IP Intelligence cloud example,
`ip-intelligence/examples/cloud/gettingstarted-console.php`, takes the key as
its first argument or from `resource_key`, after a `composer install` in
`ip-intelligence`.

### Pitfalls

- **No vendor folder in the package.** Every example loads the
  `vendor/autoload.php` of its own package, which the root `composer install`
  does not create. Run `composer install` in the package folder, as above.
  After one in `device-detection/cloud`, the root `ci/build-project.ps1`
  refuses to run until that `vendor` folder is removed, as "CI" below says.
- **The extension or the data file setting is missing.** The Device Detection
  engine reads both from PHP's settings when PHP starts, so run the example
  with `PHP_INI_SCAN_DIR` as above, and keep the data file where `php.ini`
  says. Without its `-d extension=...`, the IP Intelligence example stops
  with `The FiftyOneDegreesIpIntelligenceEngine extension is not loaded`.
- **A Git LFS pointer instead of the data file.** The engine cannot load it,
  so the example fails before it prints any results. Check the file sizes as
  above.
- **The Asn file in place of the Lite file.** The IP Intelligence example uses
  the Asn file when it is given no path, as "IP Intelligence" above explains,
  and then every property line reads `(not available - ...)`.
- **SWIG older than 4.1.** `ip-intelligence/OnPremise.md` says SWIG 4.1 or
  newer is needed for PHP 8. Regenerating the wrapper also rewrites the
  committed `src/php8` files, which `git status` then shows as modified.
- **A checkout made on Windows.** Git for Windows usually checks files out
  with `core.autocrlf=true`, and neither this repository nor
  `cxx/ip-intelligence-data` has a `.gitattributes`, so the shell scripts get
  Windows line endings and the shell cannot run them. Clone inside WSL rather
  than using a Windows checkout from WSL.
- **The old IP Intelligence download in `ip-intelligence/OnPremise.md`.** Its
  `curl` address, `.../enterpriseipi/51Degrees-LiteIpiV41.ipi.gz`, answered
  404 on 4 November 2026. Use the script in `cxx/ip-intelligence-data`, which
  downloads `51Degrees-IPIV4LiteIpiV41.ipi.gz`.
- **What CI does not test.** CI runs the examples' classes through PHPUnit
  (the packages' `tests/ExampleTests.php`), not the example scripts. It
  tests Device Detection with a paid data file (`ci/run-unit-tests.ps1` skips
  the tests without one), and IP Intelligence with the Lite file that
  `ci/fetch-assets.ps1` links into `ip-intelligence/assets`.

## Directories

- `pipeline/core`: `51degrees/fiftyone.pipeline.core`
- `pipeline/engines`: `51degrees/fiftyone.pipeline.engines`
- `pipeline/cloudrequestengine`: `51degrees/fiftyone.pipeline.cloudrequestengine`
- `pipeline/did`: `51degrees/fiftyone.pipeline.did`
- `pipeline/translation`: `51degrees/fiftyone.pipeline.translation`
- `device-detection/cloud`: `51degrees/fiftyone.devicedetection`
- `device-detection/onpremise`: `51degrees/fiftyone.devicedetection` (same name, path repository only)
- `location`: `51degrees/fiftyone.geolocation`
- `ip-intelligence`: `51degrees/fiftyone.ipintelligence`
- `cxx`: submodule with the C and C++ code and the data files that the two
  on-premise extensions build from and test with.

## Dependency order

core -> engines -> cloudrequestengine -> device-detection/cloud, location, ip-intelligence.
ip-intelligence also requires translation.
translation and device-detection/onpremise require core and engines.
did requires no other 51Degrees package.
The `require` section of each package `composer.json` is the source of truth.

## Rules

1. Each package directory, including its `composer.json` and `ci/`, is
   published to its mirror repository as it is. Do not edit them to make
   the monorepo work. Put monorepo glue in root files only.
2. After a change in a package, run that package's unit tests with the root
   autoloader. After a change in core, engines or cloudrequestengine, also
   run the tests of every package that depends on it.
3. Pull requests go to `rc`, not `main`, and stay drafts until they have
   been reviewed, because the nightly merges any pull request into `rc` that
   is not a draft and has no review requested. When a release is wanted, one
   pull request merges `rc` into `main`, and the "Only rc into main" check
   fails a pull request into `main` from any other branch.

## Running tests

```bash
git submodule update --init --recursive
composer install
cd pipeline/core
../../vendor/bin/phpunit --bootstrap ../../vendor/autoload.php --testsuite Unit
```

`location` and `ip-intelligence` sit one level higher, so there the root
`vendor` is `../vendor`.

Check the suite names in the package's `phpunit.xml`. `location` has
only `Integration`. `pipeline/translation` has `Translation`.
`device-detection/onpremise` is not in the root `composer.json`, so the
root autoloader does not load its `src`.

## CI

The workflows call common-ci, which runs the scripts in the root `ci/` once
for the whole repository. Those run each package's own `ci/` scripts with
`-RepoName <repository>/<package>`. `ci/packages.ps1` lists the packages, the
mirrors, and which packages test against the root `vendor` and which install
their own. Add a package there, not in the workflows.

`ci/build-project.ps1` writes a `vendor/autoload.php` into each package that
tests against the root `vendor`. A package `vendor` folder from a
`composer install` run inside the package stops it, as the tests would then
use that copy.

## Versions

The repository has one version, tagged as a plain version such as `4.5.203`,
and every package is released with it. `ci/get-next-package-version.ps1`
moves only the patch number. `mirror.yml` puts the tag on every mirror, and
`did-publish.yml` on the mirror of `pipeline/did`.

## More

`README.md` explains the mirrors, the releases and the on-premise build.
