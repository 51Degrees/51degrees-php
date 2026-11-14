# CI scripts

These scripts mirror the CI used by
[device-detection-php-onpremise](https://github.com/51Degrees/51degrees-php/tree/main/device-detection/onpremise)
and [device-detection-php](https://github.com/51Degrees/51degrees-php/tree/main/device-detection/cloud). They
delegate to the shared `common-ci` PHP steps (checked out under `php/` in CI).

**Every script `common-ci` names must exist, even when it has nothing to do.**
`steps/run-script.ps1` resolves the path with `Get-Command` under
`$ErrorActionPreference = 'Stop'`, so a missing file throws "The term ... is not
recognized" and fails the whole job rather than being skipped. Several of the
scripts below are therefore deliberate no-ops.

| Script | Called by | Purpose |
|---|---|---|
| `setup-environment.ps1` | build-and-test, publish prebuild/test | Set up the PHP version for the build. |
| `fetch-assets.ps1` | Configure (prefetch), build-and-test, publish test | Download the `.ipi` data files. Must declare **only** parameters named after workflow secrets - the `prefetch_assets` action passes nothing else. |
| `build-project.ps1` | build-and-test | Build the native on-premise extension from the committed SWIG wrapper, clone the translation package, then run the standard PHP build. |
| `run-unit-tests.ps1` | build-and-test | Run the PHPUnit `Unit` suite. |
| `run-integration-tests.ps1` | build-and-test, publish test | Run the PHPUnit `Integration` suite with a resource key. |
| `run-performance-tests.ps1` | build-and-test, publish test | Only invoked when a matrix entry sets `RunPerformance`. Not yet implemented - see the note in the script. |
| `get-next-package-version.ps1` | publish configure | Resolve the next semantic version via GitVersion. |
| `build-package-requirements.ps1` | publish prebuild | No-op: nothing to prepare before packaging a Composer library. |
| `build-package.ps1` | publish build | Write the placeholder artifact the publish workflow expects. |
| `install-package.ps1` | publish package/test | Rebuild the project in place; there is no package to install. |
| `publish-package.ps1` | publish package | No-op: Packagist picks the new tag up via webhook. |
| `update-packages.ps1` | package update | No-op: dependencies are Composer constraints and no `composer.lock` is committed. |
| `options.json` | Configure | The build matrix, as an **array** of entries - `common-ci` feeds it to `strategy.matrix.options` and reads `Image`, `Language` and `LanguageVersion` from each. `RunPerformance` is `false` everywhere: enabling it turns on ComparePerformance, which would find no results. |

`generate-accessors.ps1` is absent because there is no `nightly-data-file-change`
workflow here - PHP reads properties by name and generates no accessor classes.

## Required secrets / keys

- `resource_key` — a 51Degrees cloud resource key authorised for IP intelligence
  (`ip.*`) properties, used by the live cloud and mixed-cloud tests.
- An IP intelligence licence key (for the distributor) is only needed for
  automatic data-file updates, which the tests do not exercise.

## Notes

- The native on-premise extension is built under Linux/WSL (see
  [../OnPremise.md](../OnPremise.md)). The build needs no SWIG, as the
  wrapper is committed and `../on-premise/generate-wrapper.sh` regenerates it
  with SWIG 4.5.1.
- On Windows, live cloud tests need a CA bundle: run PHP with
  `-d curl.cainfo=/path/to/cacert.pem`. Linux CI uses the system CA store.
