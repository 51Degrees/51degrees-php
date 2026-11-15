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

namespace fiftyone\pipeline\cloudrequestengine\tests;

use fiftyone\pipeline\cloudrequestengine\CloudEngine;
use fiftyone\pipeline\cloudrequestengine\CloudRequestEngine;
use fiftyone\pipeline\core\PipelineBuilder;
use fiftyone\pipeline\engines\AspectDataDictionary;
use PHPUnit\Framework\TestCase;

/**
 * A Cloud Aspect Engine reads a WKT value written as an object with one
 * member, value, as the text, which is what the on-premise Engine gives, as
 * value-types.md in the Pipeline specification asks. It also takes the text
 * alone.
 */
class CloudEngineWktTests extends TestCase
{
    // The Property metadata the cloud service gives, keyed by lower case
    // name as the Cloud Request Engine keeps it.
    private const PROPERTIES = [
        'ip' => [
            'areas' => ['name' => 'Areas', 'type' => 'WktString'],
            'shape' => ['name' => 'Shape', 'type' => 'wkb'],
            'record' => ['name' => 'Record', 'type' => 'String'],
            'countrycodes' => [
                'name' => 'CountryCodes',
                'type' => 'IReadOnlyList<IWeightedValue<String>>'
            ],
        ],
    ];

    /**
     * Processes a FlowData whose Cloud Request Engine answered with the
     * JSON given, and returns the value of each Property.
     *
     * @return array<string, mixed>
     */
    private function values(string $response): array
    {
        $engine = new CloudEngine();
        $engine->dataKey = 'ip';
        $cloud = $this->createMock(CloudRequestEngine::class);
        $cloud->dataKey = 'cloud';
        $cloud->flowElementProperties = self::PROPERTIES;

        $pipeline = (new PipelineBuilder([
            'addJavaScriptBuilder' => false,
            'useSetHeaderProperties' => false,
        ]))
            ->add($cloud)
            ->add($engine)
            ->build();
        $flowData = $pipeline->createFlowData();
        try {
            $flowData->setElementData(
                new AspectDataDictionary($cloud, ['cloud' => $response])
            );
            $engine->process($flowData);

            $values = [];
            foreach ($flowData->get('ip')->asDictionary() as $name => $value) {
                $values[$name] = $value->value;
            }

            return $values;
        } finally {
            $flowData->close();
        }
    }

    public function testAWktObjectIsReadAsTheText()
    {
        $values = $this->values(
            '{"ip": {"areas": {"value": "POINT(2 4)"}, ' .
            '"shape": {"value": "POLYGON((1 1,2 2,1 1))"}}}'
        );

        $this->assertSame('POINT(2 4)', $values['areas']);
        $this->assertSame('POLYGON((1 1,2 2,1 1))', $values['shape']);
    }

    public function testWktTextAloneIsReadAsTheText()
    {
        $values = $this->values('{"ip": {"areas": "POINT(2 4)"}}');

        $this->assertSame('POINT(2 4)', $values['areas']);
    }

    public function testValuesOfOtherPropertiesAreReadAsTheyAre()
    {
        $values = $this->values(
            '{"ip": {"record": {"value": "kept"}, "countrycodes": ' .
            '[{"value": "GB", "rawweighting": 65535}]}}'
        );

        $this->assertSame(['value' => 'kept'], $values['record']);
        $this->assertSame(
            [['value' => 'GB', 'rawweighting' => 65535]],
            $values['countrycodes']
        );
    }
}
