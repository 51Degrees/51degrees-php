> [!IMPORTANT]
> This package is developed in the monorepo https://github.com/51Degrees/51degrees-php, under `pipeline/engines/`. The repository `pipeline-php-engines` is now the read-only Packagist mirror of `51degrees/fiftyone.pipeline.engines`, published from the monorepo from version 4.5.203. Please refer to the monorepo for issues and PRs.

# 51Degrees PHP Pipeline Engines

![51Degrees](https://51degrees.com/img/logo.png?utm_source=packagist&utm_medium=package&utm_campaign=51degrees-php&utm_content=fiftyone.pipeline.engines&utm_term=51degrees-php-pipeline-engines "Data rewards the curious") **PHP Pipeline API**

[Developer Documentation](https://51degrees.com/pipeline-php/index.html?utm_source=packagist&utm_medium=package&utm_campaign=51degrees-php&utm_content=fiftyone.pipeline.engines&utm_term=51degrees-php-pipeline-engines "developer documentation")

## Introduction
This project contains the source code for the 'engines' functionality for the PHP implementation of the 51Degrees Pipeline API.
		
## Tests
To run the tests in this repository, make sure PHPUnit is installed then, in the root of this repo, call:
```
phpunit --fail-on-warning --display-warnings --log-junit test-results.xml
```


## Development

When making changes to `pipeline.php.engines` repository, it may be necessary to link to a local development version of pipeline dependencies. For information on this, see [Composer local path](https://getcomposer.org/doc/05-repositories.md#path).

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

- [Build your own](https://51degrees.com/build-your-own?utm_source=packagist&utm_medium=package&utm_campaign=51degrees-php&utm_content=fiftyone.pipeline.engines&utm_term=find-out-more-build-your-own)
- [Pipeline API documentation](https://51degrees.com/documentation/_pipeline_api__index.html?utm_source=packagist&utm_medium=package&utm_campaign=51degrees-php&utm_content=fiftyone.pipeline.engines&utm_term=find-out-more-documentation)
- [Pipeline API examples](https://51degrees.com/documentation/_pipeline_api__examples__index.html?utm_source=packagist&utm_medium=package&utm_campaign=51degrees-php&utm_content=fiftyone.pipeline.engines&utm_term=find-out-more-examples)
- https://github.com/51Degrees/51degrees-php
