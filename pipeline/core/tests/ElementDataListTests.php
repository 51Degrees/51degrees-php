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

use fiftyone\pipeline\core\ElementDataDictionary;
use fiftyone\pipeline\core\FlowData;
use fiftyone\pipeline\core\FlowElement;
use fiftyone\pipeline\core\Logger;
use fiftyone\pipeline\core\Pipeline;
use PHPUnit\Framework\TestCase;

/**
 * Adds ElementData under its data key holding a name.
 */
class NamedElement extends FlowElement
{
    public string $name;

    public function __construct(string $dataKey, string $name)
    {
        parent::__construct();
        $this->dataKey = $dataKey;
        $this->name = $name;
    }

    public function processInternal(FlowData $flowData): void
    {
        $flowData->setElementData(new ElementDataDictionary($this, [
            'name' => $this->name,
        ]));
    }
}

/**
 * A FlowData keeps its ElementData in a list. Each ElementData is found by
 * its key, in any letter case, and listed in Pipeline order, being the order
 * the FlowElements were added, with the newest under each key.
 */
class ElementDataListTests extends TestCase
{
    public function testElementDataIsFoundAndListedInPipelineOrder(): void
    {
        $flowData = self::processed([
            new NamedElement('first', 'first'),
            new NamedElement('second', 'second'),
            new NamedElement('third', 'third'),
        ]);

        foreach (['first', 'second', 'third'] as $key) {
            $this->assertSame($key, $flowData->get($key)->get('name'));
            $this->assertSame($key, $flowData->tryGet(strtoupper($key))->get('name'));
        }
        $this->assertSame(['first', 'second', 'third'], self::names($flowData));
    }

    public function testDataAddedAgainUnderOneKeyReplacesTheEarlierInItsPlace(): void
    {
        $flowData = self::processed([
            new NamedElement('same', 'earlier'),
            new NamedElement('after', 'after'),
            new NamedElement('same', 'replaced'),
        ]);

        $this->assertSame('replaced', $flowData->get('same')->get('name'));
        $this->assertSame(['replaced', 'after'], self::names($flowData));
    }

    public function testDataTheCallerAddsComesAfterTheFlowElementsData(): void
    {
        $caller = new NamedElement('caller', 'caller');
        $pipeline = self::pipeline([
            new NamedElement('first', 'first'),
            new NamedElement('second', 'second'),
        ]);
        $flowData = $pipeline->createFlowData();
        $flowData->setElementData(new ElementDataDictionary($caller, [
            'name' => 'caller',
        ]));
        $flowData->process();

        $this->assertSame(['first', 'second', 'caller'], self::names($flowData));
    }

    public function testAMissingKeyIsNullFromTryGetAndAnErrorNamingTheKeysInOrder(): void
    {
        $flowData = self::processed([
            new NamedElement('first', 'first'),
            new NamedElement('second', 'second'),
        ]);

        $this->assertNull($flowData->tryGet('missing'));
        $this->expectExceptionMessage('first,second');
        $flowData->get('missing');
    }

    public function testTheDeprecatedDataArrayIsReadAndWrittenByKey(): void
    {
        $element = new NamedElement('first', 'first');
        $flowData = self::processed([$element, new NamedElement('second', 'second')]);

        $this->assertSame(['first', 'second'], array_keys($flowData->data));
        $this->assertTrue(isset($flowData->data['second']));

        $flowData->data = ['only' => new ElementDataDictionary($element, ['name' => 'only'])];
        $this->assertSame('only', $flowData->get('only')->get('name'));
        $this->assertSame(['only'], array_keys($flowData->data));
    }

    /**
     * The names in the FlowData's ElementData, as listElementData gives it.
     *
     * @return array<int, string>
     */
    private static function names(FlowData $flowData): array
    {
        return array_map(
            function ($data) {
                return $data->get('name');
            },
            $flowData->listElementData()
        );
    }

    private static function pipeline(array $elements): Pipeline
    {
        return new Pipeline($elements, ['logger' => new Logger(null)]);
    }

    private static function processed(array $elements): FlowData
    {
        $flowData = self::pipeline($elements)->createFlowData();
        $flowData->process();

        return $flowData;
    }
}
