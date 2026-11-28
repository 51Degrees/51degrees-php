> [!IMPORTANT]
> This package is developed in the monorepo https://github.com/51Degrees/51degrees-php, under `pipeline/cloudrequestengine/`. The repository `pipeline-php-cloudrequestengine` is now the read-only Packagist mirror of `51degrees/fiftyone.pipeline.cloudrequestengine`, published from the monorepo from version 4.5.203. Please refer to the monorepo for issues and PRs.

# 51Degrees PHP Cloud Request Engine

![51Degrees](https://raw.githubusercontent.com/51Degrees/51degrees-php/main/.github/images/logo-160.png "Data rewards the curious") **PHP Pipeline API**

[Developer Documentation](https://51degrees.com/documentation/_pipeline_api__concepts__pipeline_basics.html?utm_source=packagist&utm_medium=package&utm_campaign=51degrees-php&utm_content=fiftyone.pipeline.cloudrequestengine&utm_term=51degrees-php-cloud-request-engine "developer documentation")

## Introduction
This project contains the source code for the CloudrequestEngine for the PHP implementation of the 51Degrees Pipeline API.

The Pipeline is a generic micro-services aggregation solution with the ability to add a range of 51Degrees and/or custom plug ins (Engines) 

## Examples

[examples/CloudEngine.php](examples/CloudEngine.php) creates a cloud engine,
being a flow element whose results come from a call to a remote service. The
Cloud Request Engine sends the date of birth to a star sign service and the
engine reads the star sign from the answer. The service is
[examples/StarSignService.php](examples/StarSignService.php), which answers in
the shape the 51Degrees cloud service does. The example starts it with PHP's
built-in web server on a free port of the same machine and stops it at the
end, so the example needs no settings, no resource key and no network. Run it
from this folder.

```
composer install
php examples/CloudEngine.php
```

`tests/ExampleTests.php` runs it in its own PHP with no settings, as CI does.

## Human Confidence

Add a `DerivedCloudEngine` after the Cloud Request Engine and read
`HumanConfidence` and `HumanConfidenceSource` from its `derived` Element
Data. Where the browser ran the 51Degrees browser tests, the page gets a
sealed result, and the engine redeems it. Your code never redeems one.

1. For each page, call `newPage()` on a `HumanConfidenceTransactions`.
   Load the client script from the page's `scriptUrl()`, which carries a
   new transaction, and give the page its `pageId()`.
2. Have the page send the sealed result back as the parameter
   `humanconfidence`, with its page id as `humanconfidencepage`.
3. Process that request with the Pipeline as usual. HumanConfidenceSource
   is `Active`, and HumanConfidence is the redeemed `High`, `Medium`,
   or `Low`, or `Medium` where the sealed result could not be redeemed.

Redeeming needs a License Key in the engine's `licenseKey` setting. Each
page id redeems once. Where the request has no page id, or one with no
transaction kept for it, the sealed result isn't sent and HumanConfidence
is `Medium`. Only a query parameter or form field carries a sealed result.
One sent as a header or a cookie is never sent on or cached, and no
transaction from that request is sent either. A sealed result redeems within 10 seconds
of being made. The transactions are kept in files in the system's
temporary folder. Where a
site runs on more than one machine, give `HumanConfidenceTransactions` a
folder they share, and pass it to the engine as its
`humanConfidenceTransactions` setting.

The folder must be one the user the site runs as owns, and no one else can
open. On Linux and macOS a folder that's there already and belongs to
another user, or that its group or other users can use (anything but mode
`0700`), is refused: `newPage()` throws, naming the folder, and nothing is
redeemed from it. A folder that is a link is refused everywhere. PHP can't
read a folder's owner or access list on Windows, so there only the link is
checked, and the default folder is in the user's own temporary folder.

A `DerivedCloudEngine` can also go in a Pipeline with no Cloud Request
Engine. Nothing is redeemed then. A request with a sealed result reads
`Medium` and `Active`, and any other request gives both Properties with no
value and the reason. To get the redeemed value, add the Cloud Request
Engine before it.

## Tests

To run the tests in this repository, make sure PHPUnit 9.6 is installed then, in the root of this repo, call:

```
phpunit --fail-on-warning --log-junit test-results.xml
```


## Development

When making changes to this repository, it may be necessary to link to a local development version of pipeline dependencies. For information on this, see [Composer local path](https://getcomposer.org/doc/05-repositories.md#path).

For exmaple, if a development version of `51degrees/fiftyone.pipeline.core` was stored locally, the location would be added with:

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

- [Build your own](https://51degrees.com/build-your-own?utm_source=packagist&utm_medium=package&utm_campaign=51degrees-php&utm_content=fiftyone.pipeline.cloudrequestengine&utm_term=find-out-more-build-your-own)
- [Pipeline API documentation](https://51degrees.com/documentation/_pipeline_api__concepts__pipeline_basics.html?utm_source=packagist&utm_medium=package&utm_campaign=51degrees-php&utm_content=fiftyone.pipeline.cloudrequestengine&utm_term=find-out-more-documentation)
- [Pipeline API examples](https://51degrees.com/documentation/_pipeline_api__examples__index.html?utm_source=packagist&utm_medium=package&utm_campaign=51degrees-php&utm_content=fiftyone.pipeline.cloudrequestengine&utm_term=find-out-more-examples)
- [Cloud service documentation](https://51degrees.com/documentation/_services__cloud__index.html?utm_source=packagist&utm_medium=package&utm_campaign=51degrees-php&utm_content=fiftyone.pipeline.cloudrequestengine&utm_term=find-out-more-cloud-service)
- https://github.com/51Degrees/51degrees-php
