# 51Degrees Pipeline Translation (PHP)

A generic translation engine for the
[51Degrees Pipeline API](https://github.com/51Degrees/51degrees-php/tree/main/pipeline/core).

It reads one or more properties produced by an upstream pipeline element,
translates each value into the language resolved from the request evidence,
and writes the translated values under its own element data key. Values may be
a plain string, a list of strings, or a weighted list of strings (an array of
`['value' => string, 'weighting' => number]` entries); for weighted lists the
weighting is preserved and only the value is translated.

This package provides the base behaviour. Concrete engines (for example the
country code and countries translation engines in
[ip-intelligence](https://github.com/51Degrees/51degrees-php/tree/main/ip-intelligence))
configure it with a source element data key, a set of source to destination
property name pairs, the per-locale translation maps, and a missing-translation
behaviour.

## Locale resolution

The locale is taken from the first non-blank of these evidence keys, in order:

1. `query.translation`
2. `query.accept-language`
3. `header.accept-language`

The value is parsed as an `Accept-Language` list (quality ordered). The first
candidate that exactly matches an available locale wins. If a candidate is the
base language (`en`), resolution stops and the values are left untranslated
(the source data is already English). A two letter candidate falls back to the
first available locale that starts with it (`fr` matches `fr_FR`).

## Classes

- `TranslationEngine` - the base pipeline engine.
- `Translator` - translates a value using one locale's map.
- `Languages` - Accept-Language parsing and locale resolution.
- `MissingTranslationBehavior` - Original, EmptyString or FlowError.
- `YamlMap` - minimal parser for the flat `key: value` translation files.

## Find out more

- [Build your own](https://51degrees.com/build-your-own?utm_source=packagist&utm_medium=package&utm_campaign=51degrees-php&utm_content=fiftyone.pipeline.translation&utm_term=find-out-more-build-your-own)
- [Pipeline API documentation](https://51degrees.com/documentation/_pipeline_api__concepts__pipeline_basics.html?utm_source=packagist&utm_medium=package&utm_campaign=51degrees-php&utm_content=fiftyone.pipeline.translation&utm_term=find-out-more-documentation)
- [Pipeline API examples](https://51degrees.com/documentation/_pipeline_api__examples__index.html?utm_source=packagist&utm_medium=package&utm_campaign=51degrees-php&utm_content=fiftyone.pipeline.translation&utm_term=find-out-more-examples)
- https://github.com/51Degrees/51degrees-php
