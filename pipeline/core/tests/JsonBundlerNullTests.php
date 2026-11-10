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
        'Reasoned' => ['type' => 'string'],
        'PlainNull' => ['type' => 'string'],
        'NullValue' => ['type' => 'string'],
        'NotSet' => ['type' => 'string'],
        'PlainScript' => ['type' => 'javascript'],
    ];

    public function processInternal($flowData): void
    {
        $flowData->setElementData(new ElementDataDictionary($this, [
            'IsFalse' => new AspectPropertyValue(null, false),
            'Zero' => new AspectPropertyValue(null, 0),
            'Empty' => new AspectPropertyValue(null, ''),
            'Missing' => new AspectPropertyValue(),
            'Reasoned' => new AspectPropertyValue('No match'),
            'PlainNull' => null,
            'NullValue' => new AspectPropertyValue(null, null),
            'PlainScript' => null,
        ]));
    }
}

/**
 * Three Properties whose metadata sets the delay execution flag, one with a
 * value, one assigned no value and one plain null.
 */
class DelayedValuesElement extends FlowElement
{
    public string $dataKey = 'delayed';

    public array $properties = [
        'WithValue' => ['type' => 'javascript', 'delayexecution' => true],
        'NoValue' => ['type' => 'javascript', 'delayexecution' => true],
        'PlainNull' => ['type' => 'javascript', 'delayexecution' => true],
    ];

    public function processInternal($flowData): void
    {
        $flowData->setElementData(new ElementDataDictionary($this, [
            'WithValue' => new AspectPropertyValue(null, 'void 0;'),
            'NoValue' => new AspectPropertyValue('Not available'),
            'PlainNull' => null,
        ]));
    }
}

/**
 * The JSON Bundler writes null, with a string nullreason, only for a Property
 * that has been assigned no value. A plain null has no entry.
 */
class JsonBundlerNullTests extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function fullJson(): array
    {
        $flowData = (new PipelineBuilder([]))
            ->add(new FalsyValuesElement())
            ->build()
            ->createFlowData();
        $flowData->process();

        return $flowData->jsonbundler->json;
    }

    /**
     * @return array<string, mixed>
     */
    private function json(): array
    {
        return $this->fullJson()['falsy'];
    }

    public function testAPlainNullHasNoEntryAndNoNullReason()
    {
        $json = $this->json();

        foreach (['plainnull', 'nullvalue', 'notset', 'plainscript'] as $name) {
            $this->assertArrayNotHasKey($name, $json);
            $this->assertArrayNotHasKey($name . 'nullreason', $json);
        }
    }

    public function testAPlainNullJavaScriptPropertyIsStillListed()
    {
        $this->assertContains(
            'falsy.plainscript',
            $this->fullJson()['javascriptProperties']
        );
    }

    public function testTheNullReasonIsTheMessageGiven()
    {
        $json = $this->json();

        $this->assertNull($json['reasoned']);
        $this->assertSame('No match', $json['reasonednullreason']);
    }

    public function testANoValueWithoutAMessageSaysItHasNoSetValue()
    {
        $this->assertSame(
            'This instance does not have a set value',
            $this->json()['missingnullreason']
        );
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

    public function testDelayExecutionIsWrittenOnlyForAPropertyWithAValue()
    {
        $flowData = (new PipelineBuilder([]))
            ->add(new DelayedValuesElement())
            ->build()
            ->createFlowData();
        $flowData->process();
        $json = $flowData->jsonbundler->json['delayed'];

        $this->assertTrue($json['withvaluedelayexecution']);
        $this->assertArrayNotHasKey('novaluedelayexecution', $json);
        $this->assertArrayNotHasKey('plainnulldelayexecution', $json);
    }
}
