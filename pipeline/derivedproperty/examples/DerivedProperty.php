<?php
/* *********************************************************************
 * This Original Work is copyright of 51 Degrees Mobile Experts Limited.
 * Copyright 2026 51 Degrees Mobile Experts Limited, Davidson House,
 * Forbury Square, Reading, Berkshire, United Kingdom RG1 3EU.
 *
 * This Original Work is licensed under the European Union Public Licence
 * (EUPL) v.1.2 and is subject to its terms as set out below.
 *
 * If a copy of the EUPL was not distributed with this file, You can obtain
 * one at https://opensource.org/licenses/EUPL-1.2.
 *
 * The 'Compatible Licences' set out in the Appendix to the EUPL (as may be
 * amended by the European Commission) shall be deemed incompatible for
 * the purposes of the Work and the provisions of the compatibility
 * clause in Article 5 of the EUPL shall not apply.
 *
 * If using the Work as, or as part of, a network application, by
 * including the attribution notice(s) required under Article 5 of the EUPL
 * in the end user terms of the application under an appropriate heading,
 * such notice(s) shall fulfill the requirements of that article.
 * ********************************************************************* */

/*
 * @example DerivedProperty.php
 *
 * The derived property element computes new properties from properties
 * other elements in the pipeline have already produced, following a script
 * written in YAML or JSON. The element holds no data file, sends no request
 * and needs no resource key, so this example needs no settings and no
 * network.
 *
 * The example shows
 *
 * 1. a script held in the example as text and given to the builder,
 * 2. a script read from ReaderEngagement.yaml beside the example,
 * 3. the same pipeline built from derived-property-options.json, which
 *    names the element and gives it the script file in its build
 *    parameters, and
 * 4. what the caller sees when a source property a script names is not
 *    available on the request, which is a property with no value and a
 *    message naming what was missing.
 *
 * The source properties come from SessionElement.php beside the example,
 * which stands in for device detection or IP intelligence.
 *
 * Run it from the package folder after composer install.
 *
 *     php examples/DerivedProperty.php
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/SessionElement.php';

use fiftyone\pipeline\core\AspectPropertyValue;
use fiftyone\pipeline\core\FlowData;
use fiftyone\pipeline\core\Pipeline;
use fiftyone\pipeline\core\PipelineBuilder;
use fiftyone\pipeline\derivedproperty\DerivedPropertyElementBuilder;

// A script held as text in the example. It reads the same source
// properties as ReaderEngagement.yaml and gives whether every test passed,
// so one element can run more than one script over the same properties.
const READER_ENGAGEMENT_IS_HIGH = <<<'YAML'
Format: 1
Name: ReaderEngagementIsHigh
Version: "1.0.0"
Output:
  Name: ReaderEngagementIsHigh
  Description: Whether every one of the ReaderEngagement tests passed on this request.
  ValueType: bool
  IsList: false
  Category: Example
Checks:
  SeveralPages: { Property: session.PagesViewed, Ge: 3 }
  LongEnough:   { Property: session.SecondsSincePageLoad, Ge: 30 }
  PointerMoved: { Property: session.PointerMoved, Eq: true }
Rules:
  - When: { Failed: Checks, Eq: 0 }
    Then: true
  - Else: false
YAML;

/**
 * Writes a heading underlined to its length.
 */
function heading(string $text): void
{
    echo $text . "\n" . str_repeat('=', strlen($text)) . "\n\n";
}

/**
 * One property as a line of output, giving its value or the reason it has
 * none.
 */
function describe(string $name, AspectPropertyValue $property): string
{
    if (!$property->hasValue) {
        return "  {$name} has no value. {$property->noValueMessage}\n";
    }
    $value = $property->value;
    if (is_bool($value)) {
        $value = $value ? 'true' : 'false';
    }

    return "  {$name} is {$value}.\n";
}

/**
 * Processes two requests, one carrying every source property and one that
 * does not say whether the pointer moved, and writes the source properties
 * and the derived properties of each.
 *
 * @param array<string> $derived The names of the derived properties
 * @return FlowData The second request
 */
function processRequests(Pipeline $pipeline, array $derived): FlowData
{
    $requests = [
        'Request 1, with every source property.' => [
            SessionElement::PAGES_VIEWED => '5',
            SessionElement::SECONDS_SINCE_PAGE_LOAD => '120',
            SessionElement::POINTER_MOVED => 'true'
        ],
        'Request 2, which does not say whether the pointer moved.' => [
            SessionElement::PAGES_VIEWED => '5',
            SessionElement::SECONDS_SINCE_PAGE_LOAD => '120'
        ]
    ];
    foreach ($requests as $title => $evidence) {
        echo "{$title}\n";
        $flowData = $pipeline->createFlowData();
        foreach ($evidence as $key => $value) {
            $flowData->evidence->set($key, $value);
        }
        $flowData->process();

        // The source properties, read as any element's properties are.
        echo describe('session.PagesViewed', $flowData->session->pagesviewed);
        echo describe(
            'session.SecondsSincePageLoad',
            $flowData->session->secondssincepageload
        );
        echo describe('session.PointerMoved', $flowData->session->pointermoved);

        // The derived properties, under the element data key derived.
        foreach ($derived as $name) {
            echo describe("derived.{$name}", $flowData->derived->{$name});
        }
        echo "\n";
    }

    return $flowData;
}

// A path in the configuration file is read from the working directory, so
// the example works from its own folder wherever it was started.
chdir(__DIR__);

//! [code]
heading('1. Built with the builder in code');

$derived = (new DerivedPropertyElementBuilder())
    // A script file. A path may hold a wildcard, so scripts/*.yaml adds
    // every script in a folder.
    ->addScriptFile(__DIR__ . '/ReaderEngagement.yaml')
    // A script held as text in code, as YAML or as JSON. The name must
    // equal the Name the script gives itself.
    ->addScript('ReaderEngagementIsHigh', READER_ENGAGEMENT_IS_HIGH)
    // addBuiltInScript('HumanConfidence') would add a script this package
    // carries. This example uses none, so it stands on its own.
    ->build();

$pipeline = (new PipelineBuilder())
    // The derived property element goes after the elements that supply
    // the properties its scripts read.
    ->add(new SessionElement())
    ->add($derived)
    ->build();

$flowData = processRequests(
    $pipeline,
    ['ReaderEngagement', 'ReaderEngagementIsHigh']
);

// Reading value where there is none raises an exception with the same
// message, so check hasValue first.
$engagement = $flowData->derived->readerengagement;
try {
    echo "  derived.ReaderEngagement is {$engagement->value}.\n";
} catch (Exception $e) {
    echo $e->getMessage() === $engagement->noValueMessage
        ? "  Reading the value of derived.ReaderEngagement raised an " .
            "exception with the same message.\n"
        : "  Reading the value of derived.ReaderEngagement raised " .
            "'{$e->getMessage()}'.\n";
}
echo "\nThe second request has no derived values, because a property the " .
    "scripts name was\nnot there. Each message names the property and what " .
    "the element that supplies it\nsaid, rather than giving a value " .
    "resting on part of the evidence.\n\n";
//! [code]

//! [configuration]
heading('2. Built from derived-property-options.json');

// The configuration file names each element by its class and gives the
// derived property element its script file. Script text held in code
// cannot be given in a configuration file, so this pipeline runs
// ReaderEngagement.yaml alone.
$pipeline = (new PipelineBuilder())
    ->buildFromConfig('derived-property-options.json');

processRequests($pipeline, ['ReaderEngagement']);
//! [configuration]

// Find out more, the links for the person running this example to follow
// up.
$findOutMore = [
    'This example' =>
        'https://github.com/51Degrees/51degrees-php/tree/main/pipeline/derivedproperty',
    'The script format and the scripts 51Degrees ships' =>
        'https://github.com/51Degrees/derived-properties',
    'Run a script in your browser' =>
        'https://51degrees.github.io/derived-properties/',
    'Device detection, which supplies properties a script can read' =>
        'https://github.com/51Degrees/51degrees-php/tree/main/device-detection',
    'IP intelligence, which supplies more' =>
        'https://github.com/51Degrees/51degrees-php/tree/main/ip-intelligence',
    'The Derived Property Element specification' =>
        'https://github.com/51Degrees/specifications/blob/main/pipeline-specification/pipeline-elements/derived-property-element.md',
    'The Pipeline API' =>
        'https://51degrees.com/documentation/_pipeline_api__concepts__pipeline_basics.html?utm_source=code&utm_medium=example&utm_campaign=51degrees-php&utm_content=examples-derivedproperty.php&utm_term=find-out-more-pipeline-api',
    'Contact us' =>
        'https://51degrees.com/contact-us?utm_source=code&utm_medium=example&utm_campaign=51degrees-php&utm_content=examples-derivedproperty.php&utm_term=find-out-more-contact'
];
echo "Find out more\n-------------\n";
foreach ($findOutMore as $label => $url) {
    echo "{$label}: {$url}\n";
}
