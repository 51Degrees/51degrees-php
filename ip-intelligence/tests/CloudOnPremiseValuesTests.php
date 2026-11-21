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

declare(strict_types=1);

namespace fiftyone\pipeline\ipintelligence\tests;

use fiftyone\pipeline\cloudrequestengine\CloudRequestEngine;
use fiftyone\pipeline\cloudrequestengine\HttpClient;
use fiftyone\pipeline\core\AspectPropertyValue;
use fiftyone\pipeline\core\PipelineBuilder;
use fiftyone\pipeline\ipintelligence\IpIntelligenceCloud;
use fiftyone\pipeline\ipintelligence\SwigData;
use fiftyone\pipeline\ipintelligence\tests\classes\StubSwigEngine;
use fiftyone\pipeline\ipintelligence\tests\classes\StubSwigList;
use fiftyone\pipeline\ipintelligence\tests\classes\StubSwigResult;
use fiftyone\pipeline\ipintelligence\tests\classes\StubSwigValue;
use fiftyone\pipeline\ipintelligence\tests\classes\StubWeightedValue;
use PHPUnit\Framework\TestCase;

/**
 * The cloud and on-premise IP Intelligence engines give the same values for
 * the same Properties, so that either can be used in a Pipeline without a
 * change to the code that reads the results.
 *
 * The on-premise results are stood in for, so the test needs neither the
 * extension nor a data file. The cloud service's answer is the JSON for
 * the same results, in the forms the specification gives for weighted
 * values, WKT values and a Property with no value, so it needs no resource
 * key or network.
 *
 * @see https://github.com/51Degrees/specifications/blob/main/pipeline-specification/README.md#engine
 * @see https://github.com/51Degrees/specifications/blob/main/pipeline-specification/features/weighted-values.md#serializing
 * @see https://github.com/51Degrees/specifications/blob/main/pipeline-specification/features/value-types.md#wkt
 */
class CloudOnPremiseValuesTests extends TestCase
{
    private const NO_VALUE = 'No value for this IP.';

    /**
     * The Properties compared, by lower case name, as the on-premise engine
     * lists them from the data file.
     */
    private const ON_PREMISE_PROPERTIES = [
        'registeredname' => ['name' => 'RegisteredName', 'type' => 'string'],
        'registeredowner' => ['name' => 'RegisteredOwner', 'type' => 'string'],
        'areas' => ['name' => 'Areas', 'type' => 'wkb'],
        'mcc' => ['name' => 'Mcc', 'type' => 'string'],
        'countrycodesgeographical' => [
            'name' => 'CountryCodesGeographical',
            'type' => 'WeightedString'
        ],
        'accuracyradiusmin' => [
            'name' => 'AccuracyRadiusMin',
            'type' => 'WeightedInt'
        ],
        'latitude' => ['name' => 'Latitude', 'type' => 'WeightedDouble'],
        'isproxy' => ['name' => 'IsProxy', 'type' => 'WeightedBool'],
    ];

    /**
     * The same Properties as the cloud service lists them.
     */
    private const CLOUD_PROPERTIES = [
        ['Name' => 'RegisteredName', 'Type' => 'String', 'Category' => 'IP Metrics'],
        ['Name' => 'RegisteredOwner', 'Type' => 'String', 'Category' => 'IP Metrics'],
        ['Name' => 'Areas', 'Type' => 'WktString', 'Category' => 'Geography'],
        ['Name' => 'Mcc', 'Type' => 'WeightedString', 'Category' => 'Network'],
        ['Name' => 'CountryCodesGeographical', 'Type' => 'WeightedString', 'Category' => 'Geography'],
        ['Name' => 'AccuracyRadiusMin', 'Type' => 'WeightedInt32', 'Category' => 'Geography'],
        ['Name' => 'Latitude', 'Type' => 'WeightedDouble', 'Category' => 'Geography'],
        ['Name' => 'IsProxy', 'Type' => 'WeightedBoolean', 'Category' => 'IP Metrics'],
    ];

    /**
     * The cloud service's answer for the same results. A weighted value is
     * written as the Serializing section of weighted-values.md writes it, a
     * WKT value as an object holding the text, and a Property with no value
     * as null with its reason.
     */
    private const CLOUD_JSON = <<<'JSON'
{
    "ip": {
        "registeredname": "Example Networks",
        "registeredowner": null,
        "registeredownernullreason": "No value for this IP.",
        "areas": { "value": "POLYGON((1 1,2 2,1 2,1 1))" },
        "mcc": [
            { "rawweighting": 52428, "value": "234" },
            { "rawweighting": 13107, "value": "235" }
        ],
        "countrycodesgeographical": [
            { "rawweighting": 32768, "value": "GB" },
            { "rawweighting": 32767, "value": "FR" }
        ],
        "accuracyradiusmin": [ { "rawweighting": 65535, "value": 25 } ],
        "latitude": [ { "rawweighting": 65535, "value": 51.458 } ],
        "isproxy": [ { "rawweighting": 65535, "value": false } ]
    },
    "javascriptProperties": []
}
JSON;

    /**
     * The on-premise engine's results for the same IP address.
     */
    private static function onPremise(): SwigData
    {
        $result = new StubSwigResult([
            'RegisteredName' => new StubSwigValue(true, 'Example Networks'),
            'RegisteredOwner' => new StubSwigValue(false, null, self::NO_VALUE),
            'Areas' => new StubSwigValue(true, 'POLYGON((1 1,2 2,1 2,1 1))'),
            'Mcc' => new StubSwigValue(true, new StubSwigList([
                new StubWeightedValue('234', 52428),
                new StubWeightedValue('235', 13107),
            ])),
            'CountryCodesGeographical' => new StubSwigValue(true, new StubSwigList([
                new StubWeightedValue('GB', 32768),
                new StubWeightedValue('FR', 32767),
            ])),
            'AccuracyRadiusMin' => new StubSwigValue(true, new StubSwigList([
                new StubWeightedValue(25, 65535),
            ])),
            'Latitude' => new StubSwigValue(true, new StubSwigList([
                new StubWeightedValue(51.458, 65535),
            ])),
            'IsProxy' => new StubSwigValue(true, new StubSwigList([
                new StubWeightedValue(false, 65535),
            ])),
        ]);

        return new SwigData(
            new StubSwigEngine(self::ON_PREMISE_PROPERTIES),
            $result
        );
    }

    /**
     * Each value the cloud engine gives, by Property name, read with the
     * name as the Property lists it.
     *
     * @return array<string, AspectPropertyValue>
     */
    private static function cloud(): array
    {
        $client = new class() extends HttpClient {
            public function makeCloudRequest(
                string $type,
                string $url,
                ?string $content,
                ?string $originHeader
            ): string {
                if (stripos($url, 'accessibleProperties') !== false) {
                    return (string) json_encode(['Products' => ['ip' => [
                        'DataTier' => 'CloudV4Free',
                        'Properties' => CloudOnPremiseValuesTests::cloudProperties(),
                    ]]]);
                }
                if (stripos($url, 'evidencekeys') !== false) {
                    return '["query.client-ip-51d"]';
                }

                return CloudOnPremiseValuesTests::cloudJson();
            }
        };
        $pipeline = (new PipelineBuilder([
            'addJavaScriptBuilder' => false,
            'useSetHeaderProperties' => false,
        ]))
            ->add(new CloudRequestEngine([
                'resourceKey' => 'test_resource_key',
                'httpClient' => $client,
            ]))
            ->add(new IpIntelligenceCloud())
            ->build();
        try {
            $flowData = $pipeline->createFlowData();
            try {
                $flowData->evidence->set('query.client-ip-51d', '192.0.2.1');
                $flowData->process();

                $values = [];
                foreach (self::ON_PREMISE_PROPERTIES as $property) {
                    $values[$property['name']] =
                        $flowData->get('ip')->get($property['name']);
                }

                return $values;
            } finally {
                $flowData->close();
            }
        } finally {
            $pipeline->close();
        }
    }

    /**
     * @return array<array<string, string>>
     */
    public static function cloudProperties(): array
    {
        return self::CLOUD_PROPERTIES;
    }

    public static function cloudJson(): string
    {
        return self::CLOUD_JSON;
    }

    /**
     * Each Property has a value from both engines, or no value from both,
     * and a value is identical, type and order included, so that code
     * written against one engine's results reads the other's the same way.
     */
    public function testBothEnginesGiveIdenticalValues(): void
    {
        $onPremise = self::onPremise();
        $cloud = self::cloud();

        foreach (self::ON_PREMISE_PROPERTIES as $property) {
            $name = $property['name'];
            $expected = $onPremise->get($name);
            $actual = $cloud[$name];

            $this->assertInstanceOf(AspectPropertyValue::class, $expected, $name);
            $this->assertInstanceOf(AspectPropertyValue::class, $actual, $name);
            $this->assertSame($expected->hasValue, $actual->hasValue, $name);
            if ($expected->hasValue) {
                $this->assertSame($expected->value, $actual->value, $name);
            } else {
                $this->assertSame(
                    $expected->noValueMessage,
                    $actual->noValueMessage,
                    $name
                );
            }
        }
    }

    /**
     * Each item of a weighted value is read by the same keys from both
     * engines, value and rawweighting.
     */
    public function testWeightedValuesAreReadByTheSameKeys(): void
    {
        $onPremise = self::onPremise()->get('Mcc')->value;
        $cloud = self::cloud()['Mcc']->value;

        $this->assertCount(2, $cloud);
        foreach ([$onPremise, $cloud] as $values) {
            $this->assertSame('234', $values[0]['value']);
            $this->assertSame(52428, $values[0]['rawweighting']);
            $this->assertSame('235', $values[1]['value']);
            $this->assertSame(13107, $values[1]['rawweighting']);
        }
    }
}
