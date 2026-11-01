# 51Degrees PHP Cloud Request Engine

![51Degrees](https://51degrees.com/img/logo.png?utm_source=github&utm_medium=readme&utm_campaign=pipeline-php-cloudrequestengine&utm_content=readme.md&utm_term=51degrees-php-cloud-request-engine "Data rewards the curious") **PHP Pipeline API**

[Developer Documentation](https://51degrees.com/documentation/index.html?utm_source=github&utm_medium=readme&utm_campaign=pipeline-php-cloudrequestengine&utm_content=readme.md&utm_term=51degrees-php-cloud-request-engine "developer documentation")

## Introduction
This project contains the source code for the CloudrequestEngine for the PHP implementation of the 51Degrees Pipeline API.

The Pipeline is a generic micro-services aggregation solution with the ability to add a range of 51Degrees and/or custom plug ins (Engines) 

## Tests

To run the tests in this repository, make sure PHPUnit is installed then, in the root of this repo, call:

```
phpunit --fail-on-warning --display-warnings --log-junit test-results.xml
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