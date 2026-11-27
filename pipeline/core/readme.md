> [!IMPORTANT]
> This package is developed in the monorepo https://github.com/51Degrees/51degrees-php, under `pipeline/core/`. The repository `pipeline-php-core` is now the read-only Packagist mirror of `51degrees/fiftyone.pipeline.core`, published from the monorepo from version 4.5.203. Please refer to the monorepo for issues and PRs.

# 51Degrees PHP Pipeline Core

![51Degrees](https://raw.githubusercontent.com/51Degrees/51degrees-php/main/.github/images/logo-160.png "Data rewards the curious") **PHP Pipeline API**

[Developer Documentation](https://51degrees.com/documentation/_pipeline_api__concepts__pipeline_basics.html?utm_source=packagist&utm_medium=package&utm_campaign=51degrees-php&utm_content=fiftyone.pipeline.core&utm_term=51degrees-php-pipeline-core "developer documentation")

## Introduction
This project contains the core source code for the PHP implementation of the 51Degrees Pipeline API.

The Pipeline is a generic micro-services aggregation solution with the ability to add a range of 
51Degrees and/or custom plug ins (Engines) 

## Dependencies

The [tested versions](https://51degrees.com/documentation/_product_summaries__tested_versions.html?utm_source=packagist&utm_medium=package&utm_campaign=51degrees-php&utm_content=fiftyone.pipeline.core&utm_term=dependencies) page shows 
the PHP versions that we currently test against. The software may run fine against other versions, 
but additional caution should be applied.

## Examples

To run the examples, you first need to install dependencies. Navigate to this package's folder, which is `pipeline/core` in the monorepo, and execute:

```
composer install
```

This will create the vendor directory containing autoload.php. Now navigate to the examples 
directory and start a PHP server with the relevant file. For example:

```
php -S localhost:5151 CustomFlowElement.php
```

This will start a local web server listening on port 5151. Open your web browser and browse to http://localhost:5151/ to see the example in action.

`Pipeline.php` writes to the console, so run it with `php Pipeline.php` from the same directory.

There are several examples available that demonstrate how to make use of the Pipeline API in 
isolation. These are described in the table below.
If you want examples that demonstrate how to use 51Degrees products such as device detection, 
then these are available in the corresponding [repository](https://github.com/51Degrees/51degrees-php/tree/main/device-detection/cloud) 
and on our [website](https://51degrees.com/documentation/_device_detection__examples__index.html?utm_source=packagist&utm_medium=package&utm_campaign=51degrees-php&utm_content=fiftyone.pipeline.core&utm_term=examples).

| Example                                | Description |
|----------------------------------------|-------------|
| Pipeline                               | Demonstrates adding some sample flow elements to a pipeline and processing some data with them. |
| CustomFlowElement                      | Demonstrates how to write your own flow element, which can then be added to a pipeline to perform processing. |
| OnPremiseEngine                        | In [pipeline/engines](https://github.com/51Degrees/51degrees-php/tree/main/pipeline/engines). Demonstrates an on-premise engine, which gives the star sign for a date of birth from a data file. |
| CloudEngine                            | In [pipeline/cloudrequestengine](https://github.com/51Degrees/51degrees-php/tree/main/pipeline/cloudrequestengine). Demonstrates a cloud engine, which gives the star sign for a date of birth from a remote service. |
| DerivedProperty                        | In [pipeline/derivedproperty](https://github.com/51Degrees/51degrees-php/tree/main/pipeline/derivedproperty). Demonstrates the derived property element, which computes properties from properties other elements have produced. |

## Web applications

`Utils::processWebRequest($pipeline)` handles a page request in one call. It creates a Flow Data,
adds the request's headers, cookies, query and form parameters as Evidence, and processes it.
The processed Flow Data is kept, so any other part of the application reads the results with
`Utils::getFlowData()` without being handed it. It is closed when PHP finishes the request, or
when the next request's call replaces it in a PHP that serves one request after another.

A PHP that serves one request after another, as a worker does, keeps the Flow Data until that next
call. So a request there that has not called `Utils::processWebRequest()` gets the previous
request's Flow Data from `Utils::getFlowData()`. Call `Utils::processWebRequest()` first in every
request that reads it.

So these methods suit a PHP that handles one request at a time in each process or thread. In a
runtime that interleaves requests in one thread, such as one that runs them as coroutines, the next
request's call to `Utils::processWebRequest()` closes and replaces the Flow Data an earlier request
may still use. Code there passes on the Flow Data that `Utils::processWebRequest()` returns instead
of reading it from `Utils::getFlowData()`.

A Flow Data holds the Evidence and results of one request. PHP runs each request on one thread,
and a Flow Data must not be used by two threads at once. The caller is responsible for any
locking, so code that hands a Flow Data to another thread coordinates that itself.

```php
use fiftyone\pipeline\core\Utils;

// Where the request is handled. This Flow Data must not be used by two threads at once. The
// caller is responsible for any locking.
$flowData = Utils::processWebRequest($pipeline);
Utils::setResponseHeader($flowData);

// Anywhere else while the same request is handled.
$device = Utils::getFlowData()->device;
```

Requests for the client-side JavaScript and JSON need a Flow Data that has not been processed, so
answer those with `Utils::javascriptResponse()` and `Utils::jsonResponse()`, as the device
detection Getting Started web examples do.

## Flow Data and threads

A Flow Data holds the Evidence and the results of one request. PHP runs each request on one thread, so nothing else can reach a Flow Data while a request uses it, and this package offers no parallel processing. A Flow Data must not be used by two threads at once, so create one for each request, process it once, and never keep it for another request or hand it to another thread. A Flow Data takes no lock, so code that shares one between threads is responsible for any locking. The web examples create a Flow Data for each request, as `$pipeline->createFlowData()` in each one shows.

## Tests
To run the tests in this repository, make sure PHPUnit 9.6 is installed then, in the root of this repo, call:
```
phpunit --fail-on-warning --log-junit test-results.xml
```

## Mustache template
`javascript-templates` is not a full-fledged PHP package installable via `composer`, however, it is shipped as a regularly updated, static dependency of this package.

## Find out more

- [Build your own](https://51degrees.com/build-your-own?utm_source=packagist&utm_medium=package&utm_campaign=51degrees-php&utm_content=fiftyone.pipeline.core&utm_term=find-out-more-build-your-own)
- [Pipeline API documentation](https://51degrees.com/documentation/_pipeline_api__concepts__pipeline_basics.html?utm_source=packagist&utm_medium=package&utm_campaign=51degrees-php&utm_content=fiftyone.pipeline.core&utm_term=find-out-more-documentation)
- [Pipeline API examples](https://51degrees.com/documentation/_pipeline_api__examples__index.html?utm_source=packagist&utm_medium=package&utm_campaign=51degrees-php&utm_content=fiftyone.pipeline.core&utm_term=find-out-more-examples)
- https://github.com/51Degrees/51degrees-php
