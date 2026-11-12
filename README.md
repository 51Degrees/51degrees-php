# 51Degrees Device Detection and IP Intelligence for PHP

<img src=".github/images/logo.png" alt="51Degrees" title="Data rewards the curious" width="160">

Our User-Agent parser for PHP supports Device Detection, browser detection, mobile detection, and crawler detection on-premise or in the cloud. IP Intelligence adds IP geolocation, including IP to country data.

It reads User-Agent and User-Agent Client Hints, HTTP request headers that carry browser and device information. For cloud bot detection, Human Confidence reports confidence that a person is viewing the page.

## Install

Install the development workspace below. The directory map explains the separate Composer packages and mirrors.

```bash
git clone --recurse-submodules https://github.com/51Degrees/51degrees-php.git
cd 51degrees-php
composer install
```

Try the [User-Agent tester](https://51degrees.com/developers/user-agent-tester?utm_source=github&utm_medium=readme&utm_campaign=51degrees-php&utm_content=readme&utm_term=install) and [IP tester](https://51degrees.com/developers/ip-tester?utm_source=github&utm_medium=readme&utm_campaign=51degrees-php&utm_content=readme&utm_term=install). For cloud use, [create a Resource Key](https://configure.51degrees.com/?utm_source=github&utm_medium=readme&utm_campaign=51degrees-php&utm_content=readme&utm_term=install).

Created from `pipeline-php-core`, `pipeline-php-engines`, `pipeline-php-cloudrequestengine`, `pipeline-php-did`, `device-detection-php`, `device-detection-php-onpremise`, and `location-php`, this repository continues their work.

The folder table and product guides below show where to find usage examples and how to build and test the source.

Our PHP packages use the Pipeline API, which combines data services in one processing flow. The packages are grouped by product: `pipeline`,
`device-detection`, `location` and `ip-intelligence`. Each package keeps its
own `composer.json` and `readme.md`, and is still published to Composer as
a separate package.

## Directory map

| Directory | Composer package | Packagist mirror | What it is |
|---|---|---|---|
| [pipeline/core](pipeline/core) | `51degrees/fiftyone.pipeline.core` | pipeline-php-core | Core library for the 51Degrees Pipeline API. |
| [pipeline/engines](pipeline/engines) | `51degrees/fiftyone.pipeline.engines` | pipeline-php-engines | Engines shared functionality library for 51Degrees Pipeline API. |
| [pipeline/cloudrequestengine](pipeline/cloudrequestengine) | `51degrees/fiftyone.pipeline.cloudrequestengine` | pipeline-php-cloudrequestengine | Cloud request engine for 51Degrees Pipeline API. Used to send requests to the 51Degrees cloud service. |
| [pipeline/did](pipeline/did) | `51degrees/fiftyone.pipeline.did` | pipeline-php-did | Strongly typed reader for the 51Did (51Degrees Identifier) returned by the 51Degrees Cloud service, and a client for the cloud's 51Did endpoints. |
| [pipeline/translation](pipeline/translation) | `51degrees/fiftyone.pipeline.translation` | None | Generic translation engine for the 51Degrees Pipeline API. |
| [device-detection/cloud](device-detection/cloud) | `51degrees/fiftyone.devicedetection` | device-detection-php | Device detection engines for the 51Degrees Pipeline API. Identify devices, operating systems, browsers, and crawlers from HTTP headers. |
| [device-detection/onpremise](device-detection/onpremise) | `51degrees/fiftyone.devicedetection` | None | On-premise Device Detection engines for PHP built for the 51Degrees Pipeline API. Parse HTTP headers locally. |
| [location](location) | `51degrees/fiftyone.geolocation` | location-php | Geolocation engines for 51Degrees Pipeline API. Perform reverse geocoding using longitude and latitude to populate postal addresses. |
| [ip-intelligence](ip-intelligence) | `51degrees/fiftyone.ipintelligence` | None yet | IP Intelligence engines for the 51Degrees Pipeline API. Identify the country, region, network and approximate location of an IP address, via the cloud service or on-premise with a local data file. |

`device-detection/onpremise` uses the same package name as
`device-detection/cloud`. It is not on Packagist. Projects use it through a
Composer path repository.

## What is where

| Path | Contents |
| --- | --- |
| The package directories above | One Composer package each, published to its mirror as it is |
| `cxx` | Submodule of [51degrees-cxx](https://github.com/51Degrees/51degrees-cxx), with the C and C++ code, and the [data](https://github.com/51Degrees/data) repository as its `data` submodule |
| `pipeline/did/owid-php` | Submodule of [owid-php](https://github.com/SWAN-community/owid-php), the OWID library `pipeline/did` uses |
| `javascript-templates` | Submodule of [javascript-templates](https://github.com/51Degrees/javascript-templates), the source of the template `pipeline/core` serves. The package commits a copy in `pipeline/core/javascript-templates`, because Packagist serves an archive of the tag and an archive has no submodules. `pipeline/core/ci/update-packages.ps1` refreshes the copy, and the JavaScript templates check fails when the copy differs from the submodule |
| `ci` | The scripts the shared 51Degrees CI workflows call |
| `.github/workflows` | The CI workflows, and the ones that keep the mirrors up to date |
| `composer.json` | The development install for the whole repository. It is never published |

## Dependencies between packages

The `require` sections of the package `composer.json` files give this order:

- `pipeline/core` has no 51Degrees dependency.
- `pipeline/engines` requires core.
- `pipeline/cloudrequestengine` requires core and engines.
- `device-detection/cloud`, `location` and `ip-intelligence` require
  core, engines and cloudrequestengine.
- `ip-intelligence` also requires translation.
- `pipeline/translation` and `device-detection/onpremise` require core
  and engines.
- `pipeline/did` requires no other 51Degrees package. It requires the OWID
  library from the `owid-php` submodule.

## Versions and releases

The repository has one version, tagged as a plain version such as `4.5.203`.
Every package is released together with that number, including a package
that did not change. `ci/get-next-package-version.ps1` moves only the patch
number.

Packagist does not read this repository. It reads the old repositories, which
are now read-only mirrors. The mirror of each directory is in the table above
and in `ci/packages.ps1`.

1. On every push to `main`, `mirror.yml` runs `git subtree split` for each
   mirrored directory and pushes the result to the `main` branch of its
   mirror.
2. The nightly publish tags `main` here with the next version.
3. That tag starts `mirror.yml` again, which puts the same tag on the split
   of each directory, on its mirror. `pipeline/did` gets a commit that
   `did-publish.yml` assembles instead, with the OWID source copied in,
   because Packagist serves an archive of the tag and an archive has no
   submodules.
4. Packagist sees the tag on each mirror and makes the release, as it did
   before the merge.

A version tag must be on `main` and above every version on the mirrors, or
the workflows refuse it and tag nothing.

`pipeline/translation`, `device-detection/onpremise` and
`ip-intelligence` have no mirror, so they are not released to Packagist
from here.

## Getting the code

```bash
git clone --recurse-submodules https://github.com/51Degrees/51degrees-php.git
cd 51degrees-php
composer install
```

The root `composer.json` uses Composer path repositories. Each 51Degrees
package resolves to its directory in this repository, not to a copy from
Packagist. A change in `pipeline/core` is seen at once by every package
that uses it.

The free data files go in `cxx/data`, which is the
[data](https://github.com/51Degrees/data) repository. Each one is listed there
as a `<file>.link` holding the address the file is downloaded from, and the
repository's scripts save the file beside its link. From the root of the
repository, download the Device Detection and IP Intelligence Lite files with

```bash
sh cxx/data/get-data-files.sh 51Degrees-LiteV4.1.hash 51Degrees-LiteV41.ipi
```

or with `cxx/data/get-data-files.ps1` and the same names in PowerShell. With
no names, the scripts download every file. The IP Intelligence examples
download the Lite file themselves when they find no data file.

Git leaves the folder of a removed submodule on disk. When `cxx` no longer
includes its old `device-detection-data` and `ip-intelligence-data`
submodules, delete those two folders from `cxx` in an existing clone.

## Tests

Run the unit tests of one package with the root autoloader:

```bash
cd pipeline/core
../../vendor/bin/phpunit --bootstrap ../../vendor/autoload.php --testsuite Unit
```

`location` and `ip-intelligence` sit one level higher, so there the root
`vendor` is `../vendor`.

Each package's `phpunit.xml` names its test suites. Some packages differ:

- `location` has only an `Integration` suite.
- `device-detection/onpremise` is not in the root `composer.json`. The
  root autoloader maps its namespace to `device-detection/cloud`.

The tests that use the cloud need a Resource Key. See each package's readme
for the keys and data files its tests use.

## Building the on-premise extensions

`device-detection/onpremise` and `ip-intelligence` each build a PHP
extension from the C code in `cxx/src`. Each package's `ci/build-project.ps1`
copies `cxx/src` into its `on-premise/cxx` folder before `phpize`, because PHP
names each object after the path of its source and the two extensions build
the shared code differently. By hand:

```bash
cd device-detection/onpremise/on-premise
rm -rf cxx && mkdir cxx && cp -R ../../../cxx/src cxx/src
phpize
./configure
make
```

The package [readme](device-detection/onpremise/readme.md) lists the build
requirements and how to install the extension.

## CI

The workflows in `.github/workflows` call the shared workflows in
[common-ci](https://github.com/51Degrees/common-ci), which run the scripts in
`ci/` once for the whole repository, on one build matrix from
`ci/options.json`. Most of those scripts run the script of the same name in
each package's own `ci/` folder. `ci/packages.ps1` lists the packages and how
each is built and tested.

## Contributing

Open issues and pull requests in this repository only. Do not open them on the
mirrors.

Pull requests go to the `rc` branch, not `main`. When a release is wanted, one
pull request merges `rc` into `main`.

Do not edit a package's `composer.json` or `ci/` directory to make the
monorepo work. CI publishes each package directory to its mirror as it is.
Put monorepo glue in root files only, for example the root `composer.json`.

## History

The PHP repositories listed below continue as package mirrors where shown in the directory map.

| Earlier repository | What it held |
| --- | --- |
| [pipeline-php-core](https://github.com/51Degrees/pipeline-php-core) | The Pipeline API core |
| [pipeline-php-engines](https://github.com/51Degrees/pipeline-php-engines) | Pipeline engines |
| [pipeline-php-cloudrequestengine](https://github.com/51Degrees/pipeline-php-cloudrequestengine) | The cloud request engine |
| [pipeline-php-did](https://github.com/51Degrees/pipeline-php-did) | 51Did |
| [device-detection-php](https://github.com/51Degrees/device-detection-php) | Device Detection |
| [device-detection-php-onpremise](https://github.com/51Degrees/device-detection-php-onpremise) | On-premise Device Detection |
| [location-php](https://github.com/51Degrees/location-php) | Location |


The old repositories were brought in with `git subtree add`, without
`--squash`, so commit SHAs match the originals. Use `git log --follow` to trace
a file back through the import.

## License

The packages are licensed under the European Union Public Licence 1.2
(EUPL-1.2), whose text is in [LICENSE](LICENSE), with the two additional
terms stated in the header of every source file. Each package's
`composer.json` declares `EUPL-1.2`, which is what Packagist shows.

## Find out more

- [Device Detection](https://51degrees.com/device-detection?utm_source=github&utm_medium=readme&utm_campaign=51degrees-php&utm_content=readme&utm_term=find-out-more)
- [IP Intelligence](https://51degrees.com/ip-intelligence?utm_source=github&utm_medium=readme&utm_campaign=51degrees-php&utm_content=readme&utm_term=find-out-more)
- [Bots and humans](https://51degrees.com/bots-and-humans?utm_source=github&utm_medium=readme&utm_campaign=51degrees-php&utm_content=readme&utm_term=find-out-more)
- [Documentation](https://51degrees.com/documentation/index.html?utm_source=github&utm_medium=readme&utm_campaign=51degrees-php&utm_content=readme&utm_term=find-out-more)
- https://github.com/51Degrees/51degrees-cxx
