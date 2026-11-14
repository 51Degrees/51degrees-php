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

- Git.
- PHP 8.0 or later, which the two on-premise packages require, with
  `phpize` and `php-config`, and [Composer](https://getcomposer.org/). CI
  tests PHP 8.0 to 8.5 on Linux and 8.1 to 8.5 on macOS (`ci/options.json`).
- A C and C++ compiler and `make`. On Ubuntu, `ip-intelligence/OnPremise.md`
  gives `sudo apt-get install -y php-cli php-dev build-essential autoconf pkg-config`,
  and `device-detection/onpremise/readme.md` adds `libatomic1`.
- SWIG 4.5.1 only to regenerate a wrapper. Both extensions' wrappers are
  committed, and each package's `on-premise/generate-wrapper.sh` rewrites its
  own (`--check` compares it), so never edit `hash_wrap.cxx` or
  `ipi_wrap.cxx` by hand. Both must come from the same SWIG, because the two
  extensions share one SWIG type table in a PHP that loads both.
- For the data files, `curl`, `gzip` and `md5sum` (`md5` on macOS), which the
  data repository's shell script needs, and network access to
  `distributor.51degrees.com`.

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

The examples use the free Lite data files, which need no licence key. They are
not stored in this repository. The `cxx` submodule holds the
[data](https://github.com/51Degrees/data) repository as `cxx/data`, which has a
link to each file, and a script downloads them into `cxx/data/device-detection`
and `cxx/data/ip-intelligence`. Download the two Lite files.

```bash
sh cxx/data/get-data-files.sh 51Degrees-LiteV4.1.hash 51Degrees-LiteV41.ipi
```

The script keeps a file that is already there, and with no names it downloads
every free file. Each on-premise console example also downloads its Lite file
itself when it finds no data file, so this step only saves waiting for the
download when the example first runs.

#### Check the files

- `cxx/data/device-detection/51Degrees-LiteV4.1.hash` is more than 10 MB.
- `cxx/data/ip-intelligence/51Degrees-LiteV41.ipi` is more than 100 MB.

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

Install the package's own dependencies, as CI does for this package, and run
the example with the extension loaded by `-d`, as for IP Intelligence below.

```bash
composer install
php -d extension="$PWD/on-premise/modules/FiftyOneDegreesHashEngine.so" examples/onpremise/gettingStartedConsole.php
```

The engine is built when PHP starts, from the
`FiftyOneDegreesHashEngine.data_file` setting. With no setting, PHP starts
with no engine, and the example takes the data file its first argument names,
then the one the `_51DEGREES_DD_PATH` environment variable names, then the
Lite file in `cxx/data/device-detection`, which it downloads when it is
missing (`getDataFile` in `examples/onpremise/classes/ExampleUtils.php`). It
then runs again in a PHP started with that file. Before the example's own
output it writes to standard error
`No device detection engine was built when PHP started, so the example runs again in a PHP that builds one from`
followed by the file, then the command, after the download's own lines when
it downloads the file.

A web example cannot start PHP again, so start it with the launcher, which
finds the data file in the same way and starts the PHP built-in server on
port 3000 with it, passing on the `-d extension=...` it was given. Open
http://localhost:3000/ and stop the server with Ctrl+C.

```bash
php -d extension="$PWD/on-premise/modules/FiftyOneDegreesHashEngine.so" examples/onpremise/serve.php gettingStartedWeb.php
```

To have PHP build the engine once when it starts, as a web server needs, write
the settings `ci/build-project.ps1` writes into a `php.ini` in
`device-detection/onpremise`, and run PHP reading that file as well as its own
settings, as `ci/run-unit-tests.ps1` sets `PHP_INI_SCAN_DIR`.

```bash
cat > php.ini <<EOF
extension = "$PWD/on-premise/modules/FiftyOneDegreesHashEngine.so"
FiftyOneDegreesHashEngine.data_file = "$PWD/../../cxx/data/device-detection/51Degrees-LiteV4.1.hash"
FiftyOneDegreesHashEngine.allow_unmatched = false
EOF
PHP_INI_SCAN_DIR=":$PWD" php examples/onpremise/gettingStartedConsole.php
cd ../..
```

### IP Intelligence

Build the extension, as `ip-intelligence/OnPremise.md` and the package's
`ci/build-project.ps1` do.

```bash
cd ip-intelligence/on-premise
rm -rf cxx && mkdir cxx && cp -R ../../cxx/src cxx/src
phpize
./configure --enable-FiftyOneDegreesIpIntelligenceEngine
make
cd ..
composer install
```

Load the extension with `-d`, as `ip-intelligence/OnPremise.md` shows, and
give the Lite file as the first argument.

```bash
php -d extension="$PWD/on-premise/modules/FiftyOneDegreesIpIntelligenceEngine.so" examples/onpremise/gettingstarted-console.php "$PWD/../cxx/data/ip-intelligence/51Degrees-LiteV41.ipi"
cd ..
```

Start the IP Intelligence web example from the repository root with its
launcher, which finds the data file as the next paragraph says and starts
the PHP built-in server on port 3000 with it. Open http://localhost:3000/.

```bash
php -d extension="$PWD/ip-intelligence/on-premise/modules/FiftyOneDegreesIpIntelligenceEngine.so" ip-intelligence/examples/onpremise/serve.php web
```

The example uses the argument when that file is there. Otherwise it takes the
file the `_51DEGREES_IPI_PATH` environment variable names, then any `.ipi`
file in `ip-intelligence/assets`, then a file in `cxx/data/ip-intelligence`
with a name in `IPI_DATA_FILE_NAMES` in `examples/classes/ExampleConstants.php`.
When it finds none, it downloads the Lite file into `cxx/data/ip-intelligence`
(`getDataFile` in `examples/classes/ExampleUtils.php`).

### Device Detection and IP Intelligence together

The mixed examples in `ip-intelligence/examples/onpremise/mixed` and
`ip-intelligence/examples/onpremise/web-mixed` need both extensions, built as
above, loaded in one PHP in either order, each with its data file. They load
the Device Detection on-premise classes from `device-detection/onpremise/src`
(`findDeviceDetectionOnPremise` in `examples/classes/ExampleUtils.php`).

```bash
cd ip-intelligence
BOTH="-d extension=$PWD/../device-detection/onpremise/on-premise/modules/FiftyOneDegreesHashEngine.so
 -d FiftyOneDegreesHashEngine.data_file=$PWD/../cxx/data/device-detection/51Degrees-LiteV4.1.hash
 -d extension=$PWD/on-premise/modules/FiftyOneDegreesIpIntelligenceEngine.so
 -d FiftyOneDegreesIpIntelligenceEngine.data_file=$PWD/../cxx/data/ip-intelligence/51Degrees-LiteV41.ipi"
php $BOTH examples/onpremise/mixed/gettingstarted-console.php
cd ..
```

Start the mixed web page from the repository root with the launcher, loading
only the two extensions. The launcher finds both data files, downloading the
free Lite files when they are missing, and starts the PHP built-in server on
port 3000 with them. Open http://localhost:3000/.

```bash
php -d extension="$PWD/device-detection/onpremise/on-premise/modules/FiftyOneDegreesHashEngine.so" -d extension="$PWD/ip-intelligence/on-premise/modules/FiftyOneDegreesIpIntelligenceEngine.so" ip-intelligence/examples/onpremise/serve.php web-mixed
```

`tests/MixedExamplesTests.php` runs the mixed console example and the mixed
web page with both extensions in each order, and
`tests/ServeWebExamplesTests.php` starts both web pages with the launcher, in
CI.

### Correct output

When the IP Intelligence example downloads its data file, it first writes two
lines to standard error, being
`51Degrees-LiteV41.ipi is missing, so it is being downloaded by running` and
the command. The script's `Downloading` and `Saved` lines follow when the
download ends.

The Device Detection example processes three sets of evidence. For each it
prints `Input values:` and the evidence, then `Results:` with `Mobile Device`,
`Platform Name`, `Platform Version`, `Browser Name` and `Browser Version`, all
of which the Lite file has, then `Hardware Vendor`, `Hardware Name` and
`Device Type`, which only the Enterprise data file has, so with the Lite file
they read `needs the Enterprise data file`. After the three it prints
`Some of these properties need the Enterprise data file. Contact us for a copy
at` and the tagged contact page. It prints nothing about the data file,
because the messages go to `fiftyone\pipeline\core\Logger`, whose
`logInternal` writes nothing.

The IP Intelligence example looks up four addresses, being
`116.154.188.222`, `1.3.32.31`, `45.236.48.61` and
`2001:0db8:085a:0000:0000:8a2e:0370:7334`. For each it prints `Input values:`
with the address, then `Results:` with the 16 properties in `OUTPUT_PROPERTIES`,
from `RegisteredName` to `TimeZoneOffset`. A weighted value prints as the
value followed by its raw weighting in brackets. The Lite file has only
RegisteredCountry, RegisteredName and RegisteredOwner, so the other lines read
`needs the Enterprise data file` (`outputEnterpriseProperty` in
`examples/classes/ExampleUtils.php`). After the four it prints the same
sentence and contact page as the Device Detection example.

Every example, console or web, cloud or on-premise, ends with a `Find out
more` section, a heading underlined with hyphens and then one `label: address`
line for each link, or the same list at the foot of a web page. A cloud
example run with no resource key prints where to create one and then the same
section. Each package's helper (`ExampleUtils` in Device Detection and IP
Intelligence, `Constants` in location) writes it, the pipeline core and 51Did
examples write their own, and every 51degrees.com link an example prints
carries `utm_source=code&utm_medium=example`, the campaign `51degrees-php` and
the example's file as `utm_content`.

The paid data files have many more properties, as the
[pricing page](https://51degrees.com/pricing?utm_source=github&utm_medium=docs&utm_campaign=51degrees-php&utm_content=agents.md&utm_term=correct-output)
describes.

### Cloud examples

The cloud examples need no extension and no data file, but they need a
resource key, which a person creates in the configurator, so ask for one if
none is set. The Device Detection example says a key with the properties it
needs can be created
[here](https://51degrees.com/configure/g3gMZdPY?utm_source=github&utm_medium=docs&utm_campaign=51degrees-php&utm_content=agents.md&utm_term=cloud-examples).

`device-detection/cloud/readme.md` runs the example from the package's
`examples` folder after a `composer install` in the package.

```bash
cd device-detection/cloud
composer install
cd examples
_51DEGREES_RESOURCE_KEY=YOUR_RESOURCE_KEY php cloud/gettingStartedConsole.php
cd ../../..
```

The example reads `_51DEGREES_RESOURCE_KEY`, or takes the key as its first
argument. It prints the same `Input values:` and
`Results:` blocks. The IP Intelligence cloud example,
`ip-intelligence/examples/cloud/gettingstarted-console.php`, takes the key as
its first argument or from `_51DEGREES_RESOURCE_KEY`, after a `composer install` in
`ip-intelligence`. The Device Detection, IP Intelligence and location examples
still read the old name `resource_key` when `_51DEGREES_RESOURCE_KEY` is unset
or empty, and write to the PHP error log that the old name is deprecated.

### Pitfalls

- **No vendor folder in the package.** Every example loads the
  `vendor/autoload.php` of its own package, which the root `composer install`
  does not create. Run `composer install` in the package folder, as above.
  After one in `device-detection/cloud`, the root `ci/build-project.ps1`
  refuses to run until that `vendor` folder is removed, as "CI" below says.
- **The extension is not loaded.** Each on-premise example needs its
  extension, from `php.ini` or `-d extension=...` as above. Without it, the
  Device Detection example stops with
  `The FiftyOneDegreesHashEngine extension is not loaded` and the IP
  Intelligence example with
  `The FiftyOneDegreesIpIntelligenceEngine extension is not loaded`.
- **A data file that is not there.** `cxx/data` holds a `.link` for each file
  rather than the file. Each on-premise example downloads its Lite file when
  it is missing, so it needs the network and the tools under "Prerequisites".
  A `php.ini` naming a Device Detection data file that is missing, or that
  the engine cannot be built from, leaves PHP with no engine and a warning
  when it starts, and the example then runs again with the file it finds,
  unless that is the file `php.ini` names.
  Check the file sizes as above.
- **A wrapper from another SWIG.** Running `swig` by hand, or with a version
  other than 4.5.1, rewrites the committed `src/php8` files, which
  `git status` then shows as modified. An IP Intelligence wrapper from SWIG
  4.2.0 crashed PHP when it loaded before the Device Detection extension,
  so restore the committed files with `git checkout`.
- **A checkout made on Windows.** Git for Windows usually checks files out
  with `core.autocrlf=true`, and this repository has no `.gitattributes`, so
  its shell scripts and the `config.m4` files that `phpize` reads get Windows
  line endings, which a Linux shell does not expect. Clone inside WSL rather
  than using a Windows checkout from WSL.
- **What CI tests.** CI runs the example scripts, each in its own PHP as a
  person runs them, with no settings beyond php.ini naming a free data file.
  `device-detection/onpremise/tests/ExamplesWithoutSettingsTests.php` runs
  every Device Detection on-premise example, `ip-intelligence/tests` runs the
  IP Intelligence on-premise ones in `ExamplesWithoutSettingsTests.php` and
  `WebExamplesWithoutSettingsTests.php`, and the `ExamplesWithoutKeyTests.php`
  of `device-detection/cloud`, `ip-intelligence` and `location` run every
  cloud example with no resource key. Pipeline core's `tests/ExampleTests.php`
  and 51Did's `tests/ExamplesTest.php` run theirs the same way. Each checks
  that the example ends with Find out more and that every 51degrees.com link
  it prints is tagged. `device-detection/onpremise/tests/StartWithoutDataFileTests.php`
  and `ip-intelligence/tests/ServeWebExamplesTests.php` also run the Device
  Detection console examples and both packages' web examples, through their
  launchers, with nothing set at all, so the data file is found or downloaded.
  The cloud examples' classes also run with a key, or
  against a mocked cloud service for IP Intelligence, in each package's
  `tests/ExampleTests.php`. The Device Detection on-premise suite runs only
  when the TAC data file is there (`ci/run-unit-tests.ps1` returns early
  without it), the location suite only with a resource key, and neither
  on-premise package is built on Windows or on PHP 7.4. IP Intelligence uses
  the Lite file that `ci/fetch-assets.ps1` links into `ip-intelligence/assets`.

## Environment variable names

[common-ci's list of environment variable names](https://github.com/51Degrees/common-ci/blob/d8a9c596eabf69dd5581a08e9360cdd1c9b3d9e8/environment-variables.md)
holds every environment variable name that the packages, tests, examples and
CI scripts of every language read, with what each name holds and the
deprecated names each replaced. The list decides. Use the names it gives, and
when a change needs a new name, or reads an old name again, change
[`environment-variables.json`](https://github.com/51Degrees/common-ci/blob/d8a9c596eabf69dd5581a08e9360cdd1c9b3d9e8/environment-variables.json)
in common-ci in the same piece of work. This repository's own name
check, [`ci/check-environment-variable-names.ps1`](ci/check-environment-variable-names.ps1), still keeps a
list of its own. Where the two differ, common-ci's list is the one to
follow.

## Directories

- `pipeline/core`: `51degrees/fiftyone.pipeline.core`
- `pipeline/engines`: `51degrees/fiftyone.pipeline.engines`
- `pipeline/cloudrequestengine`: `51degrees/fiftyone.pipeline.cloudrequestengine`
- `pipeline/did`: `51degrees/fiftyone.pipeline.did`
- `pipeline/translation`: `51degrees/fiftyone.pipeline.translation`
- `pipeline/derivedproperty`: `51degrees/fiftyone.pipeline.derivedproperty`.
  Its `scripts` folder is a copy of the `scripts` folder of the
  `derived-properties` submodule at the root, and its tests run the
  submodule's case files, so never edit a script there. Change the script in
  derived-properties, then run `pipeline/derivedproperty/ci/update-packages.ps1`.
- `device-detection/cloud`: `51degrees/fiftyone.devicedetection`
- `device-detection/onpremise`: `51degrees/fiftyone.devicedetection` (same name, path repository only)
- `location`: `51degrees/fiftyone.geolocation`
- `ip-intelligence`: `51degrees/fiftyone.ipintelligence`
- `cxx`: submodule with the C and C++ code and the data files that the two
  on-premise extensions build from and test with. The data files are in
  `cxx/data/device-detection` and `cxx/data/ip-intelligence`, where each free
  file is a `<file>.link` until `cxx/data/get-data-files.sh` or `.ps1`
  downloads it beside the link.

## Dependency order

core -> engines -> cloudrequestengine -> device-detection/cloud, location, ip-intelligence.
ip-intelligence also requires translation.
translation and device-detection/onpremise require core and engines.
did requires no other 51Degrees package.
derivedproperty requires core.
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
only `Integration`.
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

## Tests that fail on timing

A test that fails on a time limit, a performance threshold or a timeout is
fixed, not run again until it passes. Either the limit is set to fit the
slowest runner the test runs on, with each runner's measurement behind it, or
the reason it takes too long is found and fixed. A failure that comes and goes
costs everyone who meets it the time to look into it.
