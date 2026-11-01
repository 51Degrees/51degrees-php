# 51Degrees Geo-Location Engines

![51Degrees](https://51degrees.com/img/logo.png?utm_source=github&utm_medium=readme&utm_campaign=location-php&utm_content=readme.md&utm_term=51degrees-geo-location-engines "Data rewards the curious") **Pipeline API**

[Developer Documentation](https://51degrees.com/location-php/index.html?utm_source=github&utm_medium=readme&utm_campaign=location-php&utm_content=readme.md&utm_term=51degrees-geo-location-engines "developer documentation")

## Introduction

This repository contains the geo-location engines for the PHP implementation of the Pipeline API.

## Pre-requesites

The Pipeline engines are written in PHP and target 5 and 7.

## Packages
- **geoLocation** - A Node.js engine which retrieves geo-location results by consuming data from the 51Degrees cloud service.
- **geoLocationPipelineBuilder** - Contains the geo-location engine builders.

## Installation

You can either reference the projects in this repository or you can reference the [Composer][composer] packages in your project:

```
"require": {
    "51degrees/fiftyone.geolocation": "@dev",
    ...
```

Make sure to select the latest version from [Composer.][composer]

#### Configuration Options

 - String ``type`` - The name of the type of geolocation service to use.
 - String ``resourceKey`` - Resource Key is evidence used within the Cloud service for monitoring usage. [Obtain a resource key](https://configure.51degrees.com?utm_source=github&utm_medium=readme&utm_campaign=location-php&utm_content=readme.md&utm_term=configuration-options).
 - Array ``restrictedProperties`` - The properties to populate values for in the result (all are populated by default).

## Examples

Examples can be found in the `examples` folder. See below for a list of examples.

|Example|Description|Implemtation|
|-------|-----------|------------|
|gettingstarted.php|This example uses geo-location to determine the country from a longitude and latitude.|Cloud|
|configurefromfile.php|This examples creates a pipeline from a configuration file to set up geo-location.|Cloud|
|combiningservices.php|This example uses geo-location alongside device detection to determine the country and device.|Cloud|
|webIntegration.php|This example demonstrates how to integrate the geo-location solution into a website.|Cloud|

Ensure the `resource_key` environment variable is set to your resource key prior to running the example scripts.

To run the examples, go to the `examples` directory and run:

`php -S localhost:8080`

The examples will now be available to view in a browser. For example, the cloud getting started example will be available
by browsing to `localhost:8080/cloud/gettingstarted.php`.

## Tests

In this repository, there are tests using phpunit. 
You will need to install the package dependencies to run them:

`composer install`

To run the tests, then call:

`phpunit --fail-on-warning --display-warnings --log-junit test-results.xml`

## Project documentation

For complete documentation on the Pipeline API and associated engines, see the [51Degrees documentation site][Documentation].

[Documentation]: https://51degrees.com/documentation/index.html?utm_source=github&utm_medium=readme&utm_campaign=location-php&utm_content=readme.md&utm_term=project-documentation
[composer]: https://packagist.org/packages/51degrees/fiftyone.geolocation
