# Environment variable names

Every environment variable that the packages, examples, tests and CI scripts
of this repository read or set has a name that starts with `_51DEGREES_`,
because a POSIX shell cannot set a name that starts with a digit. Each name
holds one thing, and no name is filled from another.
[`ci/check-environment-variable-names.ps1`](ci/check-environment-variable-names.ps1)
fails on any other name, apart from the ones the operating system, the
runner, PHP or another tool defines, and the secrets only CI uses, which no
customer sets and which are named for what they do. That script lists both.

## Keys

In CI each resource key and each license key is filled from the
organization secret of exactly its name. common-ci puts each one into the environment under that name.

| Name | What it holds | Read by |
| --- | --- | --- |
| `_51DEGREES_RESOURCE_KEY` | The resource key a person sets to run a cloud example. CI has no secret of this name. A test of an example gives the example the key it checks under this name. | The cloud examples of `device-detection/cloud`, `ip-intelligence` and `location`, and the 51Did creator context example |
| `_51DEGREES_RESOURCE_KEY_BESPOKE` | A resource key that carries every property the tests ask for | The cloud tests of `device-detection/cloud`, `ip-intelligence`, `location` and `pipeline/cloudrequestengine`, the browser run of the cloud example, and the 51Did live test |
| `_51DEGREES_RESOURCE_KEY_BASIC` | A resource key on the basic data tier | The 51Did live test, and the Human Confidence test of `device-detection/cloud` that checks a key whose products do not offer `HumanConfidenceSource` gets none |
| `_51DEGREES_RESOURCE_KEY_51DID` | The resource key of the 51Did account | The 51Did live test |
| `_51DEGREES_LICENSE_KEY` | A 51Degrees license key | The packages' `ci/fetch-assets.ps1`, to download the paid data files, and the Human Confidence and 51Did creator context examples, which send it to the cloud to redeem a sealed result |
| `_51DEGREES_LICENSE_KEY_BESPOKE` | A license key that carries the CloudV5Bespoke product | The Accept-CH tests of `device-detection/cloud`, which ask the cloud for one property at a time with it, and the test of the Human Confidence example, which gives it to the example as `_51DEGREES_LICENSE_KEY` |
| `_51DEGREES_LICENSE_KEY_51DID` | The license key of the 51Did account, used with `_51DEGREES_RESOURCE_KEY_51DID` | The 51Did live test |

## Settings

| Name | What it holds | Read by |
| --- | --- | --- |
| `_51DEGREES_CLOUD_ENDPOINT` | The address of the cloud service, including `/api/v4/`. The cloud request engine uses an address given in code or configuration first, then this variable, then the public service when both are blank (unset, empty or only whitespace). `cloud-51did.yml` reads it from the organization variable of the same name, and the build jobs have it once common-ci puts the organization's `_51DEGREES_` variables into the environment. | The published packages `51degrees/fiftyone.pipeline.cloudrequestengine` and `51degrees/fiftyone.pipeline.did`, and every cloud example and test |
| `_51DEGREES_DD_PATH` | The path of a Device Detection data file | The on-premise examples of `device-detection/onpremise` and `ip-intelligence`, their tests, and the fork safety test |
| `_51DEGREES_IPI_PATH` | The path of an IP Intelligence data file | The `51degrees/fiftyone.ipintelligence` package, which looks for the translations beside the data file when they are not in the data submodule, and its on-premise examples and tests |
| `_51DEGREES_EVIDENCE_YAML` | The path of a YAML evidence file, such as `20000 Evidence Records.yml` | The fork safety test, `device-detection/onpremise/configuration-tests/testForkSafety.php` |
| `_51DEGREES_EXAMPLE_URL` | The address of the web example that is already running, set by the browser runs in `device-detection/cloud/ci/run-integration-tests.ps1` and `device-detection/onpremise/ci/run-integration-tests.ps1` | The shared browser tests, [selenium-api-tests](https://github.com/51Degrees/selenium-api-tests) |
| `_51DEGREES_EXAMPLE_LANG` | The language of that example, `php`, set by the same browser runs | The shared browser tests |

The reader tests set `_51DEGREES_TEST_SET` and `_51DEGREES_TEST_UNSET` for
themselves and read them back.

## Workflows

| Name | What it holds | Set and read by |
| --- | --- | --- |
| `MIRROR_PUSH_TOKEN` | A token that can push to the mirror repositories Packagist reads, and nothing else. It comes from the organization secret of the same name. It is a secret only CI uses, so it has no `_51DEGREES_` prefix. | `.github/workflows/mirror.yml`, `ci/mirror-tags.ps1` and `.github/workflows/did-publish.yml` |
| `_51DEGREES_INPUT_VERSION`, `_51DEGREES_VERSION`, `_51DEGREES_TREE` | The version given to a dry run, the version being published, and the tree published under it | `.github/workflows/did-publish.yml` |
| `_51DEGREES_CONFIGURE_OPTIONS` | The options `./configure` is given for one extension | `.github/workflows/extensions-thread-safe-php.yml` |
| `_51DEGREES_COMMON_CI` | The folder of a common-ci checkout, where the release plan scripts look for `steps/release-plan.ps1` | Set by `.github/workflows/release-plan.yml` and `ci/publish-package.Tests.ps1`, read by `ci/packages.ps1` |

## Adding a name

A new name starts with `_51DEGREES_`, holds one thing, and is added to this
list in the same change. A resource key or a license key gets an
organization secret of exactly its name. A setting only this repository uses lives in this repository's own
files rather than in a variable, as the SWIG version does in each
`on-premise/generate-wrapper.sh`. Names that are no longer read are listed in
the pull request that removes them, not here.
