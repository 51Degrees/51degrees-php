# 51Degrees Pipeline Derived Properties (PHP)

`DerivedPropertyElement` for the 51Degrees Pipeline API computes one more
property from the properties other elements in the pipeline have already
produced, such as `HumanConfidence` from Device Detection and IP Intelligence
properties.

How a property is computed is written in a **script**, which is one YAML or
JSON file describing one output property. The format and the scripts
51Degrees ships are kept in
[derived-properties](https://github.com/51Degrees/derived-properties), whose
[format reference](https://github.com/51Degrees/derived-properties/blob/main/docs/format-1.md)
is the contract every language's element is tested against. The .NET and Rust
elements read the same scripts and run the same cases.

The element holds no data file, sends no request over the network, keeps
nothing between one request and the next, and reads no evidence.

## The one rule

A script names source properties inside its conditions. Where every one of
those properties can be read, the checks and the rules run and a value is
chosen. Where any one of them cannot be read, the script has no value, and
the message names every property that was missing and what its element said
about it.

## The scripts this package carries

The package carries every script in the `scripts` folder of
derived-properties, today `HumanConfidence` and `IsGdpr`, in its own
`scripts` folder. `BuiltInScripts::names()` lists them.

## Using it

Add the element **after** the elements that supply the properties its scripts
name, and read the result under the element data key `derived`.

```php
use fiftyone\pipeline\core\PipelineBuilder;
use fiftyone\pipeline\derivedproperty\DerivedPropertyElementBuilder;

$derived = (new DerivedPropertyElementBuilder())
    // A script this package carries.
    ->addBuiltInScript('HumanConfidence')
    // A file, or every file a wildcard in the file name matches.
    ->addScriptFile('derived/*.yaml')
    // The text of a script held by your own code, as YAML or JSON.
    ->addScript('MyProperty', $scriptText)
    ->build();

$pipeline = (new PipelineBuilder())
    ->add($cloudRequestEngine)
    ->add($deviceDetectionCloud)
    ->add($ipIntelligenceCloud)
    ->add($derived)
    ->build();

$flowData = $pipeline->createFlowData();
$flowData->evidence->setFromWebRequest();
$flowData->process();

$humanConfidence = $flowData->derived->humanconfidence;
if ($humanConfidence->hasValue) {
    echo $humanConfidence->value;
} else {
    // Names every property that could not be read, and why.
    echo $humanConfidence->noValueMessage;
}
```

Each value is an `AspectPropertyValue`, and reading `value` where there is
none raises the no value message, as for any other element.

## Example

[examples/DerivedProperty.php](examples/DerivedProperty.php) needs no
settings, no resource key, no data file and no network. A small element in
[examples/SessionElement.php](examples/SessionElement.php) supplies the
source properties, standing in for device detection or IP intelligence. The
example shows

1. a script held in the example as text and given to `addScript`,
2. a script read from [examples/ReaderEngagement.yaml](examples/ReaderEngagement.yaml)
   with `addScriptFile`,
3. the same pipeline built from
   [examples/derived-property-options.json](examples/derived-property-options.json)
   with `PipelineBuilder::buildFromConfig`, and
4. a request that does not carry a source property a script names, where
   the derived property has no value and its message names the property
   that was missing and what its element said.

Run it from this folder.

```bash
composer install
php examples/DerivedProperty.php
```

`tests/ExampleTests.php` runs it in its own PHP with no settings, as CI does.

## Configuration file

`PipelineBuilder::buildFromConfig` builds the element as it builds any other,
passing the `BuildParameters` to the constructor.

- `Scripts`, the names of scripts this package carries.
- `ScriptFiles`, paths to script files, where a file name may hold a wildcard.

Each is a list, or one string with commas between the entries, where an entry
holding a comma is wrapped in double quotes. Any other setting is refused, so
a setting written wrongly cannot leave its scripts out without a word.

```json
{
  "PipelineOptions": {
    "Elements": [
      {
        "BuilderName": "fiftyone\\pipeline\\derivedproperty\\DerivedPropertyElement",
        "BuildParameters": {
          "Scripts": "HumanConfidence, IsGdpr",
          "ScriptFiles": "derived/*.yaml"
        }
      }
    ]
  }
}
```

## When the pipeline is built

Building a pipeline holding the element logs, at information level, each
script's name, version, format, where it came from and the property it
produces, at warning level that a script is deprecated, and at debug level
each script as canonical JSON.

The build fails with a `PipelineConfigurationException` where no element in
the pipeline supplies a property a script reads, or where another element
already writes a property a script writes. An element that lists no
properties yet, as a cloud engine does until its first response, may supply
any property under its own element data key.

## Reading YAML

PHP has no YAML reader of its own, so the package uses
[symfony/yaml](https://github.com/symfony/yaml), reading plain words as YAML
1.2 does, so `yes` and `no` stay strings as the reference tools read them.

## Tests

The tests run every case file in the `tests` folder of derived-properties,
which is the `derived-properties` submodule at the root of this repository,
including the scripts under `tests/invalid` that must be refused. From the
repository root, with PHPUnit 9.6:

```bash
git submodule update --init derived-properties
composer install
cd pipeline/derivedproperty
phpunit --testsuite Unit
```

`ci/update-packages.ps1` moves the submodule to the head of derived-properties,
copies its scripts into `scripts`, and runs `ci/compile-built-in-scripts.php`,
which writes each script compiled into `compiled` as a PHP file. PHP builds
the pipeline on every request, so the element reads a built in script from
its compiled file, which opcache keeps in memory, and parses no YAML, while
the SHA-256 of the script file matches the one the compiled file holds.
`tests/BuiltInScriptsTests.php` fails when the copy differs from the
submodule, or a compiled file differs from the script beside it.
