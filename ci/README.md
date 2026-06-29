# CI scripts

These scripts mirror the CI used by
[device-detection-php-onpremise](https://github.com/51Degrees/device-detection-php-onpremise)
and [device-detection-php](https://github.com/51Degrees/device-detection-php). They
delegate to the shared `common-ci` PHP steps (checked out under `php/` in CI).

| Script | Purpose |
|---|---|
| `setup-environment.ps1` | Set up the PHP version for the build. |
| `fetch-assets.ps1` | Download the Lite `.ipi` data file used by the on-premise tests. |
| `build-project.ps1` | Generate the SWIG wrapper, build the native on-premise extension, then run the standard PHP build. |
| `run-unit-tests.ps1` | Run PHPUnit; on-premise tests skip when no extension/data file is present. |
| `run-integration-tests.ps1` | Run the cloud integration tests with a resource key. |
| `options.json` | Repository CI options (package name, supported PHP versions). |

## Required secrets / keys

- `resource_key` — a 51Degrees cloud resource key authorised for IP intelligence
  (`ip.*`) properties, used by the live cloud and mixed-cloud tests.
- An IP intelligence licence key (for the distributor) is only needed for
  automatic data-file updates, which the tests do not exercise.

## Notes

- The native on-premise extension is built under Linux/WSL (see
  [../OnPremise.md](../OnPremise.md)). SWIG 4.1+ is required for PHP 8.
- On Windows, live cloud tests need a CA bundle: run PHP with
  `-d curl.cainfo=/path/to/cacert.pem`. Linux CI uses the system CA store.
