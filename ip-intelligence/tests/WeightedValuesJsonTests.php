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

use fiftyone\pipeline\core\FlowData;
use fiftyone\pipeline\core\PipelineBuilder;
use fiftyone\pipeline\ipintelligence\SwigData;
use fiftyone\pipeline\ipintelligence\tests\classes\StubSwigEngine;
use fiftyone\pipeline\ipintelligence\tests\classes\StubSwigList;
use fiftyone\pipeline\ipintelligence\tests\classes\StubSwigResult;
use fiftyone\pipeline\ipintelligence\tests\classes\StubSwigValue;
use fiftyone\pipeline\ipintelligence\tests\classes\StubWeightedValue;
use PHPUnit\Framework\TestCase;

/**
 * The JSON Builder Element writes each weighted value of the on-premise
 * engine's results as an object whose keys are value and rawweighting, in
 * lower case, as the Serializing section of weighted-values.md in the
 * Pipeline specification requires. The native results are stood in for, so
 * the test runs without the extension or a data file.
 */
class WeightedValuesJsonTests extends TestCase
{
    /**
     * A weighted property of each value type, by lower case name, as the
     * engine lists them.
     */
    private const PROPERTIES = [
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

    public function testWeightedValuesAreWrittenWithLowerCaseKeys(): void
    {
        $result = new StubSwigResult([
            'CountryCodesGeographical' => new StubSwigValue(true, new StubSwigList([
                new StubWeightedValue('GB', 52428),
                new StubWeightedValue('FR', 13107),
            ])),
            'AccuracyRadiusMin' => new StubSwigValue(true, new StubSwigList([
                new StubWeightedValue(25, 65535),
            ])),
            'Latitude' => new StubSwigValue(true, new StubSwigList([
                new StubWeightedValue(51.5, 65535),
            ])),
            'IsProxy' => new StubSwigValue(true, new StubSwigList([
                new StubWeightedValue(false, 65535),
            ])),
        ]);
        // Gives the stood-in results as the real engine gives its own.
        $engine = new class(self::PROPERTIES, $result) extends StubSwigEngine {
            private StubSwigResult $result;

            public function __construct(array $properties, StubSwigResult $result)
            {
                parent::__construct($properties);
                $this->result = $result;
            }

            public function processInternal(FlowData $flowData): void
            {
                $flowData->setElementData(new SwigData($this, $this->result));
            }
        };
        $pipeline = (new PipelineBuilder())->add($engine)->build();
        $flowData = $pipeline->createFlowData();
        $flowData->process();

        // As the JSON endpoint writes it.
        $text = (string) json_encode($flowData->jsonbundler->json);
        $json = json_decode($text, true)['ip'];

        foreach (array_keys(self::PROPERTIES) as $name) {
            $this->assertArrayHasKey($name, $json);
            $this->assertIsArray($json[$name], $name);
            $this->assertNotSame([], $json[$name], $name);
            foreach ($json[$name] as $item) {
                $keys = array_keys($item);
                sort($keys);
                $this->assertSame(['rawweighting', 'value'], $keys, $name);
            }
        }
    }
}
