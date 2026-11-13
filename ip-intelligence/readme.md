# 51Degrees IP Intelligence Engines (PHP)

![51Degrees](https://raw.githubusercontent.com/51Degrees/51degrees-php/main/.github/images/logo-160.png "Data rewards the curious")

**IP Intelligence in PHP**: Identify the country, region, network, and approximate
location associated with an IP address, either via the 51Degrees cloud service or
on-premise using a local data file.

This package is built on the
[51Degrees Pipeline API](https://github.com/51Degrees/51degrees-php/tree/main/pipeline/core) and mirrors
the structure of [device-detection-php](https://github.com/51Degrees/51degrees-php/tree/main/device-detection/cloud)
and [ip-intelligence-dotnet](https://github.com/51Degrees/51degrees-dotnet/tree/main/ip-intelligence).

## Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Cloud](#cloud)
- [On-premise](#on-premise)
- [Mixed (device detection + IP intelligence)](#mixed)
- [Examples](#examples)
- [Tests](#tests)

## Requirements

- PHP 8.0 or above with the `curl`, `json` and `mbstring` extensions.
- For **cloud** operation: a 51Degrees resource key, obtained from
  [51degrees.com/configure](https://51degrees.com/configure/?utm_source=packagist&utm_medium=package&utm_campaign=51degrees-php&utm_content=fiftyone.ipintelligence&utm_term=cloud), authorised for IP
  intelligence (`ip.*`) properties.
- For **on-premise** operation: the native `FiftyOneDegreesIpIntelligenceEngine`
  extension (built from the `cxx` submodule at the root of the repository via SWIG) and
  an `.ipi` data file. See [OnPremise.md](OnPremise.md).

## Installation

```bash
composer require 51degrees/fiftyone.ipintelligence
```

> `51degrees/fiftyone.pipeline.translation` is not on Packagist yet, so
> `composer.json` resolves it from [`pipeline/translation`](../pipeline/translation)
> in this repository, through a Composer path repository.

## Cloud

```php
use fiftyone\pipeline\ipintelligence\IpIntelligencePipelineBuilder;

$pipeline = (new IpIntelligencePipelineBuilder([
    'resourceKey' => 'YOUR_RESOURCE_KEY'
]))->build();

$flowData = $pipeline->createFlowData();
$flowData->evidence->set('query.client-ip', '116.154.188.222');
$result = $flowData->process();

$country = $result->ip->country;
if ($country->hasValue) {
    echo $country->value;       // e.g. "China"
} else {
    echo $country->noValueMessage;
}
```

Weighted properties (for example `mcc`, `countrycodesgeographical` and
`countrycodespopulation`) are returned as an array of
`['value' => ..., 'rawweighting' => ...]` entries, where `rawweighting` is the
0 to 65535 integer that the cloud service returns and that
ip-intelligence-dotnet exposes as `IWeightedValue.RawWeighting`.

## On-premise

The on-premise engine requires the native extension, which builds the engine
once, when PHP starts, from the data file php.ini names, so that no request
loads the data file. See [OnPremise.md](OnPremise.md) for how to build the
extension, and set these in php.ini:

```ini
extension=/full/path/to/FiftyOneDegreesIpIntelligenceEngine.so
FiftyOneDegreesIpIntelligenceEngine.data_file=/full/path/to/51Degrees-LiteV41.ipi
```

The pipeline then takes no settings.

```php
use fiftyone\pipeline\ipintelligence\IpIntelligenceOnPremisePipelineBuilder;

$pipeline = (new IpIntelligenceOnPremisePipelineBuilder())->build();

$flowData = $pipeline->createFlowData();
$flowData->evidence->set('query.client-ip', '116.154.188.222');
$result = $flowData->process();

echo $result->ip->registeredname->value;
```

That engine always holds the whole data file in memory, as
[Running under a process manager](OnPremise.md#running-under-a-process-manager)
explains.

## Mixed

IP intelligence can be combined with
[device detection](https://github.com/51Degrees/51degrees-php/tree/main/device-detection/cloud) in a single
pipeline. See `examples/cloud/mixed/` and `examples/onpremise/mixed/`.

## Examples

In-repo under `examples/`:

| Example | Mode | Description |
|---|---|---|
| `cloud/gettingstarted-console.php` | Cloud | Look up several IP addresses. |
| `cloud/metadata-console.php` | Cloud | List available properties. |
| `cloud/getallproperties-console.php` | Cloud | Print every property for one IP. |
| `cloud/mixed/gettingstarted-console.php` | Cloud | Device detection + IP intelligence. |
| `onpremise/gettingstarted-console.php` | On-premise | Look up several IP addresses. |
| `onpremise/metadata-console.php` | On-premise | List properties and evidence keys. |
| `onpremise/offlineprocessing-console.php` | On-premise | Batch process IP addresses. |
| `onpremise/performance-console.php` | On-premise | Measure lookups per second. |
| `onpremise/suspicious-console.php` | On-premise | Flag IP addresses that look suspicious. |
| `onpremise/translation-console.php` | On-premise | List the countries an IP address is most likely in. |
| `onpremise/mixed/gettingstarted-console.php` | On-premise | Device detection + IP intelligence. |
| `onpremise/web/index.php` | On-premise | Web page with the country list. |
| `onpremise/web-mixed/index.php` | On-premise | Web page with device detection + IP intelligence. |

Every on-premise example, console and web alike, uses the engine the
extension builds when PHP starts, from the data file php.ini names in
`FiftyOneDegreesIpIntelligenceEngine.data_file`, as
[OnPremise.md](OnPremise.md) describes. Name the free Lite data file there,
or start PHP with it, as in this command from the root of the repository.

```bash
php -d FiftyOneDegreesIpIntelligenceEngine.data_file="$PWD/cxx/data/ip-intelligence/51Degrees-LiteV41.ipi" ip-intelligence/examples/onpremise/gettingstarted-console.php
```

A console example started without the engine runs again in a PHP started
with a data file, so it runs with nothing set in php.ini. The file is the
one its first argument or `_51DEGREES_IPI_PATH` names, else one in `assets/`
or `cxx/data/ip-intelligence`, else the Lite file, downloaded the first
time. The performance example takes the far smaller free ASN file instead
of the Lite file, as any data file will do for a benchmark. The engine
always holds the whole data file in memory, so an example run with the
Enterprise data file needs memory for all of it. Each example shows the properties only the
Enterprise data file has as needing it, and gives a link to
[contact us](https://51degrees.com/contact-us?utm_source=packagist&utm_medium=package&utm_campaign=51degrees-php&utm_content=fiftyone.ipintelligence&utm_term=examples)
about a copy. The suspicious and translation examples need those properties
throughout, so with a free data file they say which and stop.

## Tests

```bash
composer install
vendor/bin/phpunit
```

Cloud tests run offline against a mocked HTTP client. A live cloud test runs when
the `resource_key` environment variable is set. On-premise tests run when the
native extension is built and an `.ipi` data file is available, and are
skipped otherwise. Set `_51DEGREES_IPI_PATH`, place a file under `assets/`,
or download the Lite file into `cxx/data/ip-intelligence` at the root of the
repository with `sh cxx/data/get-data-files.sh 51Degrees-LiteV41.ipi`. The
tests of the examples run each example in its own PHP, started with
`FiftyOneDegreesIpIntelligenceEngine.data_file` naming a free data file,
and the other tests that need the engine built when PHP starts run when
php.ini names a data file.
