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
use fiftyone\pipeline\core\Logger;
use fiftyone\pipeline\core\PipelineBuilder;
use fiftyone\pipeline\engines\AspectDataDictionary;
use PHPUnit\Framework\TestCase;

/**
 * A Cloud Aspect Engine whose product is missing from the Resource Key does
 * not fail the Pipeline build. It builds with empty Property metadata, logs
 * the missing product once, and logs nothing on each response.
 */
class MissingProductTests extends TestCase
{
    /**
     * @var array<array<string, string>>
     */
    private array $logged = [];

    private function pipeline(array $productProperties)
    {
        $this->logged = [];
        $test = $this;
        $logger = new class ('trace', ['test' => $test]) extends Logger {
            public function logInternal(array $log): void
            {
                $this->settings['test']->record($log);
            }
        };

        $engine = new CloudEngine();
        $engine->dataKey = 'testElement';

        $cloud = $this->createMock(CloudRequestEngine::class);
        $cloud->dataKey = 'cloud';
        $cloud->flowElementProperties = $productProperties;

        $pipeline = (new PipelineBuilder([
            'addJavaScriptBuilder' => false,
            'useSetHeaderProperties' => false,
        ]))
            ->addLogger($logger)
            ->add($cloud)
            ->add($engine)
            ->build();

        return [$pipeline, $cloud, $engine];
    }

    /**
     * @param array<string, string> $log
     */
    public function record(array $log): void
    {
        $this->logged[] = $log;
    }

    private function process($pipeline, $cloud)
    {
        $flowData = $pipeline->createFlowData();
        $flowData->setElementData(new AspectDataDictionary(
            $cloud,
            ['cloud' => '{"javascriptProperties": []}']
        ));
        $pipeline->flowElementsList['testElement']->process($flowData);

        return $flowData;
    }

    public function testBuildsWithAMissingProduct()
    {
        [$pipeline] = $this->pipeline([]);

        $this->assertSame([], $pipeline->flowElementsList['testElement']->properties);
    }

    public function testProcessesWithAMissingProductAndLogsOnce()
    {
        [$pipeline, $cloud] = $this->pipeline([]);

        for ($request = 0; $request < 3; $request++) {
            $flowData = $this->process($pipeline, $cloud);
            $this->assertSame([], $flowData->get('testElement')->asDictionary());
        }

        $this->assertCount(1, $this->logged);
        $this->assertStringContainsString('testElement', $this->logged[0]['message']);
    }

    public function testLogsNothingWhenTheProductIsPresent()
    {
        [$pipeline, $cloud] = $this->pipeline([
            'testElement' => ['one' => ['name' => 'one', 'type' => 'string']]
        ]);
        $flowData = $pipeline->createFlowData();
        $flowData->setElementData(new AspectDataDictionary(
            $cloud,
            ['cloud' => '{"testElement": {"one": "x"}}']
        ));
        $pipeline->flowElementsList['testElement']->process($flowData);

        $this->assertSame('x', $flowData->get('testElement')->get('one')->value);
        $this->assertSame([], $this->logged);
    }
}
