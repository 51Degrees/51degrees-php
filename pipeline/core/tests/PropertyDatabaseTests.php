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

namespace fiftyone\pipeline\core\tests;

use fiftyone\pipeline\core\ElementDataDictionary;
use fiftyone\pipeline\core\FlowElement;
use fiftyone\pipeline\core\Pipeline;
use PHPUnit\Framework\TestCase;

/**
 * PHP builds the pipeline on every request, so the database of property
 * metadata that getWhere searches is built when it is first used rather than
 * when the pipeline is built.
 */
class PropertyDatabaseTests extends TestCase
{
    /**
     * An element with one integer property that counts how often its
     * properties are asked for.
     */
    private function countingElement(): FlowElement
    {
        return new class() extends FlowElement {
            public string $dataKey = 'counted';

            public int $asked = 0;

            public array $properties = [
                'integer' => ['name' => 'Integer', 'type' => 'int']
            ];

            public function getProperties(): array
            {
                ++$this->asked;

                return parent::getProperties();
            }

            public function processInternal($flowData): void
            {
                $flowData->setElementData(
                    new ElementDataDictionary($this, ['integer' => 5])
                );
            }
        };
    }

    public function testBuildingAPipelineDoesNotAskForProperties(): void
    {
        $element = $this->countingElement();

        new Pipeline([$element], []);

        $this->assertSame(0, $element->asked);
    }

    /**
     * The first search builds the database and the next one uses it.
     */
    public function testTheFirstSearchBuildsTheDatabase(): void
    {
        $element = $this->countingElement();
        $pipeline = new Pipeline([$element], []);
        $flowData = $pipeline->createFlowData();
        $flowData->process();

        $this->assertSame(['integer' => 5], $flowData->getWhere('type', 'int'));
        $this->assertSame(1, $element->asked);

        $this->assertSame([], $flowData->getWhere('type', 'string'));
        $this->assertSame(1, $element->asked);
    }

    /**
     * The database can still be read as a property of the pipeline, and
     * holds what it held when the pipeline built it at once.
     */
    public function testThePropertyDatabaseCanBeReadDirectly(): void
    {
        $element = $this->countingElement();
        $pipeline = new Pipeline([$element], []);

        $property = [
            'name' => 'Integer',
            'type' => 'int',
            'flowElement' => 'counted',
        ];
        $this->assertSame(
            [
                'name' => ['integer' => ['integer' => $property]],
                'type' => ['int' => ['integer' => $property]],
            ],
            $pipeline->propertyDatabase
        );
    }

    /**
     * A change to an element's properties after the database is built is
     * searched once the element updates its property list.
     */
    public function testAnUpdateAfterTheFirstSearchIsSearched(): void
    {
        $element = $this->countingElement();
        $pipeline = new Pipeline([$element], []);
        $flowData = $pipeline->createFlowData();
        $flowData->process();
        $this->assertSame([], $flowData->getWhere('testing', 'true'));

        $element->properties['integer']['testing'] = 'true';
        $this->assertSame([], $flowData->getWhere('testing', 'true'));

        $element->updatePropertyList();
        $this->assertSame(['integer' => 5], $flowData->getWhere('testing', 'true'));
    }
}
