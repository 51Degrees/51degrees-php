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

namespace fiftyone\pipeline\core\tests;

use fiftyone\pipeline\core\AspectPropertyValue;
use fiftyone\pipeline\core\ElementDataDictionary;
use fiftyone\pipeline\core\FlowData;
use fiftyone\pipeline\core\FlowElement;
use fiftyone\pipeline\core\PipelineBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Properties holding a shape in well-known text, with the types the
 * on-premise data file and the cloud service give them, beside a string
 * Property holding the same text.
 */
class WktValuesElement extends FlowElement
{
    public string $dataKey = 'shapes';

    public array $properties = [
        'Areas' => ['type' => 'WKB'],
        'Reduced' => ['type' => 'wkbr'],
        'Cloud' => ['type' => 'WktString'],
        'Text' => ['type' => 'wkt'],
        'NoArea' => ['type' => 'WKB'],
        'Name' => ['type' => 'string'],
    ];

    public function processInternal(FlowData $flowData): void
    {
        $flowData->setElementData(new ElementDataDictionary($this, [
            'Areas' => new AspectPropertyValue(null, 'POINT(2 4)'),
            'Reduced' => new AspectPropertyValue(null, 'POINT(1 3)'),
            'Cloud' => new AspectPropertyValue(null, 'POINT(5 6)'),
            'Text' => new AspectPropertyValue(null, 'POINT(7 8)'),
            'NoArea' => new AspectPropertyValue('No area'),
            'Name' => new AspectPropertyValue(null, 'POINT(2 4)'),
        ]));
    }
}

/**
 * The JSON Bundler writes a WKT value as an object with one member, value,
 * holding the text, as value-types.md in the Pipeline specification asks.
 */
class JsonBundlerWktTests extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private static function json(): array
    {
        $flowData = (new PipelineBuilder([]))
            ->add(new WktValuesElement())
            ->build()
            ->createFlowData();
        try {
            $flowData->process();

            return $flowData->jsonbundler->json['shapes'];
        } finally {
            $flowData->close();
        }
    }

    public function testAWktValueIsAnObjectHoldingTheText()
    {
        $json = self::json();

        $this->assertSame(['value' => 'POINT(2 4)'], $json['areas']);
        $this->assertSame(['value' => 'POINT(1 3)'], $json['reduced']);
        $this->assertSame(['value' => 'POINT(5 6)'], $json['cloud']);
        $this->assertSame(['value' => 'POINT(7 8)'], $json['text']);
        $this->assertSame(
            '{"value":"POINT(2 4)"}',
            json_encode($json['areas'])
        );
    }

    public function testAWktPropertyWithNoValueIsNullWithItsReason()
    {
        $json = self::json();

        $this->assertNull($json['noarea']);
        $this->assertSame('No area', $json['noareanullreason']);
    }

    public function testAStringPropertyHoldingWktIsAString()
    {
        $this->assertSame('POINT(2 4)', self::json()['name']);
    }
}
