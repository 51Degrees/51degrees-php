# 51Degrees PHP Device Detection On-Premise

![51Degrees](https://raw.githubusercontent.com/51Degrees/51degrees-php/main/.github/images/logo-160.png "Data rewards the curious") **PHP Pipeline API**

[Developer Documentation](https://51degrees.com/documentation/_device_detection__quickstart.html?utm_source=packagist&utm_medium=package&utm_campaign=51degrees-php&utm_content=fiftyone.devicedetection&utm_term=51degrees-php-device-detection-on-premise "developer documentation")

## Introduction
This project contains the on-premise version of 51Degrees Device Detection for the Pipeline API.

When using on-premise device detection engines in the PHP pipeline, the appropriate extensions will need to be installed.

## Dependencies

The [tested versions](https://51degrees.com/documentation/_product_summaries__tested_versions.html?utm_source=packagist&utm_medium=package&utm_campaign=51degrees-php&utm_content=fiftyone.devicedetection&utm_term=dependencies) page shows 
the PHP versions that we currently test against. The software may run fine against other versions, 
but additional caution should be applied.

### Data file

In order to perform device detection on-premise, you will need to use a 51Degrees data file. 
The 'data' sub-module of 'cxx' has a script that downloads a free, 'lite' file. That file has
a significantly reduced set of properties. The Enterprise data file has every device property,
and the examples show the properties it adds as needing it.
[Contact us](https://51degrees.com/contact-us?utm_source=github&utm_medium=readme&utm_campaign=51degrees-php&utm_content=readme.md&utm_term=data-file) for a copy.
To download the lite file into 'cxx/data/device-detection', run this from the root of the repository:

```
sh cxx/data/get-data-files.sh 51Degrees-LiteV4.1.hash
```

On Windows, run `cxx/data/get-data-files.ps1 51Degrees-LiteV4.1.hash` in PowerShell instead.
Then set `FiftyOneDegreesHashEngine.data_file` in php.ini to that file, as
[Configuration](#configuration) describes. The engine loads the file when PHP starts, so the
file must be there first.

## Install

Device detection on-premise uses a native binary. (i.e. compiled from C code to target a specific 
platform/architecture). This section explains how to build the binary and how to build the PHP 
extension that uses it.

### Pre-requisites

- php & php-dev
  - `sudo apt-get install php php-dev` 
- Install C build tools:
  - Windows:
    - You will need either Visual Studio 2019 or the [C++ Build Tools](https://visualstudio.microsoft.com/visual-cpp-build-tools/) installed.
      - Minimum platform toolset version is `v142`
      - Minimum Windows SDK version is `10.0.18362.0`
  - Linux/MacOS:
    - `sudo apt-get install g++ make libatomic1`
- Pull git submodules:
  - `git submodule update --init --recursive`

### Build Steps

Now, we can create the extension. The build looks for the C sources under `on-premise/cxx/src`,
so copy them there from the `cxx` submodule at the root of the repository first.
`ci/build-project.ps1` does the same. Navigate to the `on-premise` directory and install it with:

``` bash
mkdir -p cxx && cp -R ../../../cxx/src cxx/
phpize
./configure
make
sudo make install
```

The Hash engine extension will then be installed into the PHP extensions directory and can 
then be added to the active php.ini file.

### Regenerating swig wrapper files.

The wrapper files in `on-premise/src/php8` are generated from
`on-premise/hash-php.i` and are never edited by hand. When `hash-php.i` or the
`cxx` submodule changes, install SWIG 4.5.1, the version that generated the
committed files and the IP Intelligence wrapper, and run this from the root of
the repository with the `cxx` submodule checked out.

```
git clone --depth 1 --branch v4.5.1 https://github.com/swig/swig.git
cd swig
./autogen.sh
./configure
make
sudo make install
cd ..
sh device-detection/onpremise/on-premise/generate-wrapper.sh
```

Commit the changed `hash_wrap.cxx` and `php_FiftyOneDegreesHashEngine.h`. The
`PHP wrapper check` workflow runs the same script with `--check` and fails when
the committed files differ from what it generates.

### Windows 

Although building extensions on Windows is possible, we recommend using the 
[Windows Subsystem for Linux](https://docs.microsoft.com/en-us/windows/wsl/install-win10) 
and following the instructions for Linux above.

## Configuration

The minimum configuration needed for the extensions is to add it to the active php.ini file, 
and set the data file. For example:

<pre>
<code>extension=/usr/lib/php/<span style="background-color: #FFFF00">20170718</span>/FiftyOneDegreesHashEngine.so
FiftyOneDegreesHashEngine.data_file=/<span style="background-color: #FFFF00">path to your file</span>/51Degrees-LiteV4.1.hash
</code>
</pre>
is enough to set up the Hash extension with default configuration options.

NOTE: Make sure to check the highlighted parts. The first is dependent on the
PHP version. This location will be printed when installing. The second is the
path to where you have stored your 51Degrees data file.

### More Options

| Option | Type | Description | Default |
| ------ | ---- | ----------- | ------- |
| `required_properties` | `string` | List of properties which are required. Properties not in this list will not be returned. | `""` (all properties) |
| `performance_profile` | `string` | PHP on premise can only run with the in-memory `MaxPerformance` profile, because it is the only profile that is safe under a process manager such as `Apache MPM` or `php-fpm`. See [Running Under Process Manager](#running-under-process-manager). Leave this unset or set it to `MaxPerformance`. Configuring any other value raises an exception explaining that in-memory is the only supported configuration. | `MaxPerformance` (enforced) |
| `difference` | `int` | The difference value to allow when matching (`-1` to disable). | `0` |
| `drift` | `int` | The drift to allow when matching (`-1` to disable). | `0` |
| `allow_unmatched` | on or off | On if evidence that cannot be matched should give the values of the default profile for each component, rather than no values. | off |
| `use_predictive_graph` | on or off | On if the predictive optimized graph should be used for processing. | on |
| `use_performance_graph` | on or off | On if the performance optimized graph should be used for processing. | off |
| `concurrency` | `int` | Maximum number of potential concurrent threads that the engine will be used with. We recommend that this should not be set higher than the number of CPUs available on your machine and this should never be set to be less than or equal to 0. `NOTE`: If a value which is less than or equal to 0 is specified, default value will be used. | `10` |
| `update_matched_useragent` | on or off | On if the detection should record the matched characters from the target User-Agent. | on |
| `max_matched_useragent_length` | `int` | Number of characters to consider in the matched User-Agent. Ignored if `update_matched_useragent` is off. | `500` |

The on or off settings are read the way PHP reads its own. `on`, `yes` and
`true` in any case, and a number other than `0`, are on, written with or
without quotes, so `FiftyOneDegreesHashEngine.use_performance_graph = true`
turns the performance graph on. Anything else, an empty value included, is
off. A setting left out of php.ini keeps the default in the table.

The engine needs at least one of the two graphs. Turning `use_predictive_graph`
off without turning `use_performance_graph` on stops PHP when it starts, with
the message "The configuration provided was not valid".

### Running Under Process Manager

When a `PHP` program is run under a process manager such as `Apache MPM`, `php-fpm` or any other process manager, the whole data file must be loaded into memory. The engine is therefore always built with the in-memory `MaxPerformance` profile, and no action is required to be safe under a process manager. Configuring any other `performance_profile` raises a clear exception explaining that `MaxPerformance` is the only supported configuration, so the misconfiguration cannot be missed.

The reason enforcement is needed is that, in other profiles such as `Balanced`, `BalancedTemp`, `LowMemory` or `Default`, only a part of the data file is loaded into memory so from time to time, calls to read data from disk are required. These calls use file handles from a file handle pool which was designed to optimise the performance. However, this pool was created when the Device Detection engine module is loaded in the main process, so when the process manager spawns child processes in response to incoming requests by forking the main process, this pool is copied to the child processes, causing the file handles to be shared. When multiple processes call to load data from file using shared file handles, problems can occur such as the file position being changed unwantedly by another child process. For this reason the engine always uses `MaxPerformance`, where no call to the data file is required.

While this is an issue in multi-processing environment, it is not a problem in multi-threading environment as the file handles are not shared and are managed by the file handle pool.

#### Apache MPM

When running under Apache MPM, only `prefork` mode is supported, because the engine is built in memory when PHP starts and each worker is forked from that process. The extension is enabled in `php.ini` as described above.

#### Refresh Internal Data

To reload the data file, *refreshData()* needs to be called as described in this [example](https://51degrees.com/documentation/_device_detection__data_file_updates.html?utm_source=packagist&utm_medium=package&utm_campaign=51degrees-php&utm_content=fiftyone.devicedetection&utm_term=refresh-internal-data) . Thus, unless a reload is performed in the main process and all child processes, the data will not be updated. There is not a proven solution to do so yet, so we recommend a full server restart to be performed instead.

## Examples

Before running the examples, the configuration for the native module MUST be
added to the php.ini file. See [Configuration](#configuration).

The gettingStartedWeb example uses the data file named by the `_51DEGREES_DD_PATH`
environment variable in place of the php.ini one, when it is set. If that is a
free file in `cxx/data` that has not been downloaded yet, the example runs the
download script for it first.

To run the examples, you will need PHP and composer installed.
Once these are available, install the dependencies required by the examples.
The examples load the `vendor/autoload.php` of this package, which the
`composer install` at the repository root does not create, so navigate to
`device-detection/onpremise` and execute:

```
composer install
```

This will create the vendor directory containing autoload.php.
Now, still in `device-detection/onpremise`, start a PHP server with the relevant file. For example:

```
php -S localhost:3000 examples/onpremise/gettingStartedWeb.php
```

This will start a local web server listening on port 3000. 
Open your web browser and browse to http://localhost:3000/ to see the example in action.
Run a console example from the same folder, for example
`php examples/onpremise/gettingStartedConsole.php`. Every example ends with a
"Find out more" section that lists where to go next.

The table below describes the examples that are available.

| Example                                | Description |
|----------------------------------------|-------------|
| gettingStartedConsole                  | How to use the 51Degrees on-premise device detection API to determine details about a device based on its User-Agent and User-Agent Client Hints HTTP header values. |
| gettingStartedWeb                      | How to use on-premise device detection to determine details about a device as part of a simple PHP website. |
| metadataConsole                        | How to access the meta-data that relates to the device detection algorithm. |
| manualDataUpdate                       | How to update the device detection data file when a new one is available. |
| matchMetrics                           | Demonstrate the metrics that supply information such as confidence in the result. |
| failureToMatch                         | Demonstrates the functionality available when device detection is unable to identify the details of the device. |
| userAgentClientHints-Web               | Legacy example. Retained for the associated automated tests. See GettingStarted-Web instead. |

With php.ini naming the free Lite data file, the examples show each property only the Enterprise
data file has, such as HardwareVendor, HardwareName and DeviceType, as needing it, and give a
link to contact us about a copy.

## Tests

### PHPUnit

This repo has tests for the examples. To run the tests, make sure PHPUnit is installed then, in the root of this repo, call:

```
phpunit --fail-on-warning --display-warnings --log-junit test-results.xml
```

### Performance

Performance tests for the engine can be found in the `performance-tests` directory. 
To build this, the dependencies listed on [ApacheBench](https://github.com/51degrees/apachebench)
must be installed.

To build the tests, enter the `performance-tests` directory and run the following.

```
mkdir build
cd build
cmake ..
cmake --build .
```

Once the build is completed, ensure that the `php.ini` file is correctly configured, and run 
the tests from the build directory with:

```
./runPerf.sh
```

This will give two output files: `calibrate.out` and `process.out`. These detail the performance 
for a calibration page where there is no pipeline processing, and for a page which processes the 
request through a pipeline containing the device detection on-premise engine, and fetches 
properties.

## Development

When making changes to this repository, it may be necessary to link to a local development 
version of pipeline dependencies. For information on this, 
see [Composer local path](https://getcomposer.org/doc/05-repositories.md#path).

For exmaple, if a development version of `51degrees/fiftyone.pipeline.core` 
was stored locally, the location would be added with:

```
"repositories": [
	{
		"type": "path",
		"url": "../../path/to/packages/pipeline-php-core"
	}
]
```

then the dependency changed to:

```
"51degrees/fiftyone.pipeline.core": "*"
```

## Find out more

- [Device Detection](https://51degrees.com/device-detection?utm_source=packagist&utm_medium=package&utm_campaign=51degrees-php&utm_content=fiftyone.devicedetection&utm_term=find-out-more-device-detection)
- [Device Detection documentation](https://51degrees.com/documentation/_device_detection__quickstart.html?utm_source=packagist&utm_medium=package&utm_campaign=51degrees-php&utm_content=fiftyone.devicedetection&utm_term=find-out-more-documentation)
- [Pricing](https://51degrees.com/pricing?utm_source=packagist&utm_medium=package&utm_campaign=51degrees-php&utm_content=fiftyone.devicedetection&utm_term=find-out-more-pricing)
- https://github.com/51Degrees/51degrees-php
- https://github.com/51Degrees/51degrees-cxx
