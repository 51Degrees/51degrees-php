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

namespace fiftyone\pipeline\engines\tests;

session_start();

use fiftyone\pipeline\core\PipelineBuilder;
use fiftyone\pipeline\engines\AspectDataDictionary;
use fiftyone\pipeline\engines\Engine;
use fiftyone\pipeline\engines\SessionCache;
use PHPUnit\Framework\TestCase;

// Test creating engine

class ExampleAspectEngine extends Engine
{
    public string $dataKey = 'example';

    public array $properties = [
        'integer' => [
            'type' => 'int'
        ],
        'boolean' => [
            'type' => 'bool'
        ]
    ];

    public array $restrictedProperties = ['integer'];

    public function processInternal($flowData): void
    {
        $data = new AspectDataDictionary($this, ['integer' => 5]);

        $flowData->setElementData($data);
    }
}

class EngineTests extends TestCase
{
    public function createAndProcess()
    {
        $engine = new ExampleAspectEngine();

        $engine->setCache(new SessionCache(2));

        // Simple pipeline

        $pipeline = (new PipelineBuilder())->add($engine)->build();

        $flowData = $pipeline->createFlowData();

        $flowData->process();

        return $flowData;
    }

    public function testEngine()
    {
        $flowData = $this->createAndProcess();
        $this->assertSame(5, $flowData->get('example')->get('integer'));
    }

    // Test missing property service
    public function testMissingProperty()
    {
        $flowData = $this->createAndProcess();

        try {
            $flowData->get('example')->get('test');
        } catch (\Exception $e) {
            $missingPropertyError = true;
        }

        $this->assertTrue($missingPropertyError);
    }

    // Test restricted property list
    public function testRestrictedProperty()
    {
        $flowData = $this->createAndProcess();

        try {
            $flowData->get('example')->get('boolean');
        } catch (\Exception $e) {
            $restrictedProperties = true;
        }

        $this->assertTrue($restrictedProperties);
    }
}
