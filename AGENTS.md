# Notes for automated changes

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

`ci/select-options.ps1` chooses the entries a pull request runs. The standard
set is PHP 8.5 and 7.4 on Linux and PHP 8.3 on Windows, with the Selenium
contract on 8.5 alone. A change to a `composer.json`, a `ci/` folder or a file
outside the package folders adds the other PHP versions on Linux. macOS and
performance run only when `rc` itself or the release pull request is built,
which `.github/workflows/release-pull-request.yml` does on every entry.

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
