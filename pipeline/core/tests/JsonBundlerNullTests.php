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
use fiftyone\pipeline\core\FlowElement;
use fiftyone\pipeline\core\PipelineBuilder;
use PHPUnit\Framework\TestCase;

class FalsyValuesElement extends FlowElement
{
    public string $dataKey = 'falsy';

    public array $properties = [
        'IsFalse' => ['type' => 'bool'],
        'Zero' => ['type' => 'int'],
        'Empty' => ['type' => 'string'],
        'Missing' => ['type' => 'string'],
    ];

    public function processInternal($flowData): void
    {
        $flowData->setElementData(new ElementDataDictionary($this, [
            'IsFalse' => new AspectPropertyValue(null, false),
            'Zero' => new AspectPropertyValue(null, 0),
            'Empty' => new AspectPropertyValue(null, ''),
            'Missing' => new AspectPropertyValue(),
        ]));
    }
}

/**
 * The JSON Bundler adds a nullreason only for a Property that has no value,
 * and always as a string.
 */
class JsonBundlerNullTests extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function json(): array
    {
        $flowData = (new PipelineBuilder([]))
            ->add(new FalsyValuesElement())
            ->build()
            ->createFlowData();
        $flowData->process();

        return $flowData->jsonbundler->json['falsy'];
    }

    public function testFalseZeroAndEmptyValuesAreWrittenAsTheyAre()
    {
        $json = $this->json();

        $this->assertFalse($json['isfalse']);
        $this->assertSame(0, $json['zero']);
        $this->assertSame('', $json['empty']);
        $this->assertArrayNotHasKey('isfalsenullreason', $json);
        $this->assertArrayNotHasKey('zeronullreason', $json);
        $this->assertArrayNotHasKey('emptynullreason', $json);
    }

    public function testAPropertyWithNoValueHasAStringNullReason()
    {
        $json = $this->json();

        $this->assertNull($json['missing']);
        $this->assertIsString($json['missingnullreason']);
        $this->assertNotSame('', $json['missingnullreason']);
    }
}
