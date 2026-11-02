# 51Degrees PHP

This repository holds the source of all 51Degrees PHP packages for the
Pipeline API. The packages are grouped by product: `pipeline`,
`device-detection`, `location` and `ip-intelligence`. Each package keeps its
own `composer.json`, `readme.md` and licence, and is still published to
Composer as a separate package.

## Directory map

| Directory | Composer package | Packagist mirror | What it is |
|---|---|---|---|
| [pipeline/core](pipeline/core) | `51degrees/fiftyone.pipeline.core` | pipeline-php-core | Core library for the 51Degrees Pipeline API. |
| [pipeline/engines](pipeline/engines) | `51degrees/fiftyone.pipeline.engines` | pipeline-php-engines | Engines shared functionality library for 51Degrees Pipeline API. |
| [pipeline/cloudrequestengine](pipeline/cloudrequestengine) | `51degrees/fiftyone.pipeline.cloudrequestengine` | pipeline-php-cloudrequestengine | Cloud request engine for 51Degrees Pipeline API. Used to send requests to the 51Degrees cloud service. |
| [pipeline/did](pipeline/did) | `51degrees/fiftyone.pipeline.did` | pipeline-php-did | Strongly typed reader for the 51Did (51Degrees Identifier) returned by the 51Degrees Cloud service, and a client for the cloud's 51Did endpoints. |
| [pipeline/translation](pipeline/translation) | `51degrees/fiftyone.pipeline.translation` | None | Generic translation engine for the 51Degrees Pipeline API. |
| [device-detection/cloud](device-detection/cloud) | `51degrees/fiftyone.devicedetection` | device-detection-php | Device detection engines for the 51Degrees Pipeline API. Identify devices, operating systems, browsers, and crawlers from HTTP headers. |
| [device-detection/onpremise](device-detection/onpremise) | `51degrees/fiftyone.devicedetection` | None | On-premise device detection engines for PHP built for the 51Degrees Pipeline API. Parse HTTP headers locally. |
| [location](location) | `51degrees/fiftyone.geolocation` | location-php | Geolocation engines for 51Degrees Pipeline API. Perform reverse geocoding using longitude and latitude to populate postal addresses. |
| [ip-intelligence](ip-intelligence) | `51degrees/fiftyone.ipintelligence` | None yet | IP intelligence engines for the 51Degrees Pipeline API. Identify the country, region, network and approximate location of an IP address, via the cloud service or on-premise with a local data file. |

`device-detection/onpremise` uses the same package name as
`device-detection/cloud`. It is not on Packagist. Projects use it through a
Composer path repository.

## What is where

| Path | Contents |
| --- | --- |
| The package directories above | One Composer package each, published to its mirror as it is |
| `cxx` | Submodule of [51degrees-cxx](https://github.com/51Degrees/51degrees-cxx), with the C and C++ code and the data file submodules |
| `pipeline/did/owid-php` | Submodule of [owid-php](https://github.com/SWAN-community/owid-php), the OWID library `pipeline/did` uses |
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

The data files in `cxx/device-detection-data` are stored with
[Git LFS](https://git-lfs.github.com/). Run `git lfs pull` in that folder to
fetch them.

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
- `pipeline/translation` has a `Translation` suite and no `tests`
  directory.
- `device-detection/onpremise` is not in the root `composer.json`. The
  root autoloader maps its namespace to `device-detection/cloud`.

The tests that use the cloud need a resource key. See each package's readme
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

Do not edit a package's `composer.json` or `ci/` directory to make the
monorepo work. CI publishes each package directory to its mirror as it is.
Put monorepo glue in root files only, for example the root `composer.json`.

## History

The old repositories were brought in with `git subtree add`, without
`--squash`, so commit SHAs match the originals. Use `git log --follow` to trace
a file back through the import.

## Licence

The packages are licensed under the European Union Public Licence 1.2
(EUPL-1.2). Each package directory has its own `LICENSE` file.
