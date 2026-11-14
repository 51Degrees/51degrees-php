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

## Building in code

`TranslationEngineBuilder` builds the engine with the options of the
Translation Engine specification.

- `setSourceElementDataKey`, the element whose values are translated.
  Required.
- `addSource`, a source file. A path with a `*` or `?` wildcard adds every
  file it matches, and a relative path is resolved against the working
  directory. Given a name and contents, it adds the contents with nothing
  read from disk.
- `addTranslation`, a property of the source element and the property its
  translation goes in.
- `setFixedLanguage`, a locale to always translate into, such as `en_GB`.
  Otherwise the locale comes from the evidence.
- `setMissingTranslationBehavior`, being `Original` (the default),
  `EmptyString` or `FlowError`, in any case.
- `setElementDataKey`, the engine's own element data key, `translation`
  unless set. An engine's element data replaces any other under the same
  key, so two engines in one pipeline each need their own.

Each source is named `[name].[locale].yml` or `[name].[locale].yaml`, such as
`countries.fr_FR.yml`, and holds one `source: translation` line for each
value. The locale in the name is the language the file holds.

```php
use fiftyone\pipeline\translation\MissingTranslationBehavior;
use fiftyone\pipeline\translation\TranslationEngineBuilder;

$countryTranslation = (new TranslationEngineBuilder())
    ->setSourceElementDataKey('ip')
    ->addSource('country-langs/*.yml')
    ->addTranslation('Country', 'CountryTranslated')
    ->setMissingTranslationBehavior(MissingTranslationBehavior::ORIGINAL)
    ->build();
```

`build()` refuses an engine that would translate nothing, being one with no
source element, no translation, no source, or no source for its fixed
language, and `addSource` refuses a source whose name gives no locale.

## Configuration file

`PipelineBuilder::buildFromConfig` builds the engine from a configuration file,
as it builds any element, passing the `BuildParameters` to the constructor as
one settings array. The settings use the option names of the Translation Engine
specification, or the same names in camelCase, the style other PHP elements'
settings use.

- `SourceElementDataKey`, the element whose values are translated. Required.
- `FixedLanguage`, a locale to always translate into, such as `en_GB`.
  Otherwise the locale comes from the evidence.
- `MissingTranslationBehavior`, being `Original` (the default), `EmptyString`
  or `FlowError`, in any case.

```json
{
  "PipelineOptions": {
    "Elements": [
      {
        "BuilderName": "fiftyone\\pipeline\\translation\\TranslationEngine",
        "BuildParameters": {
          "SourceElementDataKey": "ip",
          "FixedLanguage": "en_GB",
          "MissingTranslationBehavior": "Original"
        }
      }
    ]
  }
}
```

An engine built this way uses the element data key `translation`. This engine
does not read sources and translations from settings, so it has none and
returns no properties. An engine for one particular translation adds its own
and is the one to name in a configuration file. The country translation
engines in ip-intelligence are engines of that kind, named with no
`BuildParameters`, because their constructors take the folder of translation
files rather than settings.

## Classes

- `TranslationEngine` - the base pipeline engine.
- `TranslationEngineBuilder` - builds a `TranslationEngine` in code.
- `Translator` - translates a value using one locale's map.
- `Languages` - Accept-Language parsing and locale resolution.
- `MissingTranslationBehavior` - Original, EmptyString or FlowError.
- `YamlMap` - minimal parser for the flat `key: value` translation files.

## Find out more

- [Build your own](https://51degrees.com/build-your-own?utm_source=packagist&utm_medium=package&utm_campaign=51degrees-php&utm_content=fiftyone.pipeline.translation&utm_term=find-out-more-build-your-own)
- [Pipeline API documentation](https://51degrees.com/documentation/_pipeline_api__concepts__pipeline_basics.html?utm_source=packagist&utm_medium=package&utm_campaign=51degrees-php&utm_content=fiftyone.pipeline.translation&utm_term=find-out-more-documentation)
- [Pipeline API examples](https://51degrees.com/documentation/_pipeline_api__examples__index.html?utm_source=packagist&utm_medium=package&utm_campaign=51degrees-php&utm_content=fiftyone.pipeline.translation&utm_term=find-out-more-examples)
- https://github.com/51Degrees/51degrees-php
