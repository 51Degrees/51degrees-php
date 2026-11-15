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

namespace fiftyone\pipeline\devicedetection\tests;

use fiftyone\pipeline\core\FlowData;
use fiftyone\pipeline\core\FlowElement;
use fiftyone\pipeline\core\PipelineBuilder;
use fiftyone\pipeline\devicedetection\DeviceDetectionCloud;
use fiftyone\pipeline\devicedetection\HardwareProfileCloud;
use fiftyone\pipeline\devicedetection\tests\classes\FakeCloudRequestEngine;
use fiftyone\pipeline\engines\AspectData;
use PHPUnit\Framework\TestCase;

/**
 * The cloud engines populate Aspect Data, as every Device Detection engine
 * must. These run against a fixed cloud response, so they need no network
 * connection and no resource key.
 */
class DataModelTests extends TestCase
{
    private const RESPONSE = <<<'JSON'
        {
            "device": {
                "ismobile": true
            },
            "hardware": {
                "profiles": [
                    {
                        "hardwarevendor": "Apple"
                    }
                ]
            }
        }
        JSON;

    public function testTheCloudEngineGivesAspectData(): void
    {
        $flowData = self::process(new DeviceDetectionCloud());

        $this->assertInstanceOf(AspectData::class, $flowData->get('device'));
        $this->assertTrue($flowData->device->get('ismobile')->value);
    }

    public function testTheHardwareProfileEngineGivesAspectData(): void
    {
        $flowData = self::process(new HardwareProfileCloud());

        $this->assertInstanceOf(AspectData::class, $flowData->get('hardware'));
        $this->assertCount(1, $flowData->hardware->profiles);
    }

    private static function process(FlowElement $engine): FlowData
    {
        $pipeline = (new PipelineBuilder([
            'addJavaScriptBuilder' => false,
            'useSetHeaderProperties' => false,
        ]))
            ->add(new FakeCloudRequestEngine(self::RESPONSE))
            ->add($engine)
            ->build();
        $flowData = $pipeline->createFlowData();
        $flowData->process();

        return $flowData;
    }
}
