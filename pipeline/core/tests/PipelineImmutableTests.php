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
use fiftyone\pipeline\core\Pipeline;
use PHPUnit\Framework\TestCase;

/**
 * An element that records that it ran, under the Data Key it is given.
 */
class RanElement extends FlowElement
{
    public array $properties = [
        'ran' => ['type' => 'bool'],
    ];

    public function __construct(string $dataKey)
    {
        parent::__construct();
        $this->dataKey = $dataKey;
    }

    public function processInternal(FlowData $flowData): void
    {
        $flowData->setElementData(new ElementDataDictionary($this, [
            'ran' => true,
        ]));
    }
}

/**
 * A Pipeline cannot be changed once it is built, as conceptual-overview.md
 * in the Pipeline specification asks, and its elements can still be read.
 */
class PipelineImmutableTests extends TestCase
{
    private static function pipeline(): Pipeline
    {
        return new Pipeline([new RanElement('first')], []);
    }

    /**
     * The Data Keys of the elements that ran.
     *
     * @return array<string>
     */
    private static function ran(Pipeline $pipeline): array
    {
        $flowData = $pipeline->createFlowData();
        try {
            $flowData->process();

            return array_keys($flowData->data);
        } finally {
            $flowData->close();
        }
    }

    public function testTheElementsCanBeRead()
    {
        $pipeline = self::pipeline();

        $this->assertCount(1, $pipeline->flowElements);
        $this->assertSame('first', $pipeline->flowElements[0]->dataKey);
        $this->assertTrue(isset($pipeline->flowElementsList['first']));
        $this->assertFalse(isset($pipeline->flowElementsList['second']));
        $this->assertSame(
            $pipeline->flowElements[0],
            $pipeline->flowElementsList['first'] ?? null
        );
        $this->assertNull($pipeline->flowElementsList['second'] ?? null);
        $this->assertSame('first', $pipeline->getElement('first')->dataKey);
    }

    public function testAnElementAddedToTheListDoesNotRun()
    {
        $pipeline = self::pipeline();

        $pipeline->flowElements[] = new RanElement('second');

        $this->assertSame(['first'], self::ran($pipeline));
        $this->assertCount(1, $pipeline->flowElements);
    }

    public function testAnElementRemovedFromTheListStillRuns()
    {
        $pipeline = self::pipeline();

        unset($pipeline->flowElements[0]);

        $this->assertSame(['first'], self::ran($pipeline));
    }

    public function testAnElementAddedByDataKeyIsNotFound()
    {
        $pipeline = self::pipeline();

        $pipeline->flowElementsList['second'] = new RanElement('second');

        $this->assertFalse(isset($pipeline->flowElementsList['second']));
    }

    public function testTheListsCannotBeReplaced()
    {
        $pipeline = self::pipeline();

        foreach (['flowElements', 'flowElementsList'] as $name) {
            $refusal = null;
            try {
                $pipeline->{$name} = [];
            } catch (\Exception $e) {
                $refusal = $e->getMessage();
            }
            $this->assertNotNull($refusal, "{$name} was replaced.");
            $this->assertStringContainsString($name, $refusal);
        }
        $this->assertSame(['first'], self::ran($pipeline));
    }
}
