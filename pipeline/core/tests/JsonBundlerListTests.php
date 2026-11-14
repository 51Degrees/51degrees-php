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

/**
 * 'Items' is a list of Element Data given as it is, as the hardware profiles
 * are, with one item an array keyed by Property name and one an Element Data
 * instance. 'Wrapped' is a list of Element Data inside an
 * AspectPropertyValue, and 'Names' a list of plain values.
 */
class ListValuesElement extends FlowElement
{
    public string $dataKey = 'lists';

    public array $properties = [
        'Items' => ['type' => 'array'],
        'Wrapped' => ['type' => 'array'],
        'Names' => ['type' => 'array'],
    ];

    public function processInternal($flowData): void
    {
        $flowData->setElementData(new ElementDataDictionary($this, [
            'Items' => [
                [
                    'WithValue' => new AspectPropertyValue(null, 'First'),
                    'Reasoned' => new AspectPropertyValue('Not available'),
                    'Unreasoned' => new AspectPropertyValue(),
                    'NullValue' => new AspectPropertyValue(null, null),
                    'PlainNull' => null,
                    'Plain' => 'Second',
                ],
                new ElementDataDictionary($this, [
                    'WithValue' => new AspectPropertyValue(null, 'Third'),
                    'Reasoned' => new AspectPropertyValue('Not matched'),
                    'PlainNull' => null,
                ]),
            ],
            'Wrapped' => new AspectPropertyValue(null, [
                [
                    'WithValue' => new AspectPropertyValue(null, 'Fourth'),
                    'Reasoned' => new AspectPropertyValue('Not known'),
                    'NullValue' => new AspectPropertyValue(null, null),
                ],
            ]),
            'Names' => new AspectPropertyValue(null, ['One', 'Two']),
        ]));
    }
}

/**
 * Each item of a list of Element Data is written as an object whose
 * Properties follow the rules for a Property assigned no value and for one
 * whose value is null.
 */
class JsonBundlerListTests extends TestCase
{
    /**
     * One Property of the 'lists' entry as the web page receives it, after
     * JSON encoding.
     *
     * @return array<int, mixed>
     */
    private function json(string $property): array
    {
        $flowData = (new PipelineBuilder([]))
            ->add(new ListValuesElement())
            ->build()
            ->createFlowData();
        $flowData->process();

        $lists = $flowData->jsonbundler->json['lists'];
        $this->assertArrayHasKey($property, $lists);
        $text = json_encode($lists[$property]);
        $this->assertIsString($text, json_last_error_msg());

        return json_decode($text, true);
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private static function sorted(array $item): array
    {
        ksort($item);

        return $item;
    }

    public function testEachItemIsAnObjectOfItsPropertiesValues()
    {
        $items = $this->json('items');

        $this->assertCount(2, $items);
        $this->assertSame([
            'plain' => 'Second',
            'reasoned' => null,
            'reasonednullreason' => 'Not available',
            'unreasoned' => null,
            'unreasonednullreason' => 'This instance does not have a set value',
            'withvalue' => 'First',
        ], self::sorted($items[0]));
        $this->assertSame([
            'reasoned' => null,
            'reasonednullreason' => 'Not matched',
            'withvalue' => 'Third',
        ], self::sorted($items[1]));
    }

    public function testAnItemPropertyThatIsNullHasNoEntryAndNoNullReason()
    {
        $items = $this->json('items');

        foreach (['nullvalue', 'plainnull'] as $name) {
            $this->assertArrayNotHasKey($name, $items[0]);
            $this->assertArrayNotHasKey($name . 'nullreason', $items[0]);
        }
        $this->assertArrayNotHasKey('plainnull', $items[1]);
    }

    public function testAListInsideAnAspectPropertyValueIsWrittenTheSameWay()
    {
        $items = $this->json('wrapped');

        $this->assertCount(1, $items);
        $this->assertSame([
            'reasoned' => null,
            'reasonednullreason' => 'Not known',
            'withvalue' => 'Fourth',
        ], self::sorted($items[0]));
    }

    public function testAListOfPlainValuesIsWrittenAsItIs()
    {
        $this->assertSame(['One', 'Two'], $this->json('names'));
    }
}
