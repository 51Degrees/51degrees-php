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

use fiftyone\pipeline\cloudrequestengine\Constants as CloudConstants;
use fiftyone\pipeline\core\FlowElement;
use fiftyone\pipeline\core\Pipeline;
use fiftyone\pipeline\core\PipelineBuilder;
use fiftyone\pipeline\devicedetection\DeviceDetectionCloud;
use fiftyone\pipeline\devicedetection\HardwareProfileCloud;
use fiftyone\pipeline\devicedetection\tests\classes\FakeCloudRequestEngine;
use PHPUnit\Framework\TestCase;

/**
 * Each cloud engine initializes itself from a Cloud Request Engine, which
 * must have been added to the pipeline before it. A pipeline without one,
 * or with one added after the engine, is refused when it is built. These run
 * against a fixed cloud response, so they need no network connection and no
 * resource key.
 */
class CloudPipelineOrderTests extends TestCase
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

    public function testDeviceDetectionCloudWithNoCloudRequestEngineIsRefused(): void
    {
        $this->expectRefusal();
        self::build([new DeviceDetectionCloud()]);
    }

    public function testDeviceDetectionCloudBeforeTheCloudRequestEngineIsRefused(): void
    {
        $this->expectRefusal();
        self::build([
            new DeviceDetectionCloud(),
            new FakeCloudRequestEngine(self::RESPONSE),
        ]);
    }

    public function testDeviceDetectionCloudAfterTheCloudRequestEngineProcesses(): void
    {
        $pipeline = self::build([
            new FakeCloudRequestEngine(self::RESPONSE),
            new DeviceDetectionCloud(),
        ]);
        $flowData = $pipeline->createFlowData();
        $flowData->process();

        $this->assertTrue($flowData->device->get('ismobile')->value);
    }

    public function testHardwareProfileCloudWithNoCloudRequestEngineIsRefused(): void
    {
        $this->expectRefusal();
        self::build([new HardwareProfileCloud()]);
    }

    public function testHardwareProfileCloudBeforeTheCloudRequestEngineIsRefused(): void
    {
        $this->expectRefusal();
        self::build([
            new HardwareProfileCloud(),
            new FakeCloudRequestEngine(self::RESPONSE),
        ]);
    }

    public function testHardwareProfileCloudAfterTheCloudRequestEngineProcesses(): void
    {
        $pipeline = self::build([
            new FakeCloudRequestEngine(self::RESPONSE),
            new HardwareProfileCloud(),
        ]);
        $flowData = $pipeline->createFlowData();
        $flowData->process();

        $this->assertCount(1, $flowData->hardware->profiles);
    }

    private function expectRefusal(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage(CloudConstants::CLOUDREQUESTENGINE_EXCEPTION_MESSAGE);
    }

    /**
     * @param array<FlowElement> $elements in the order they are added
     */
    private static function build(array $elements): Pipeline
    {
        $builder = new PipelineBuilder([
            'addJavaScriptBuilder' => false,
            'useSetHeaderProperties' => false,
        ]);
        foreach ($elements as $element) {
            $builder->add($element);
        }

        return $builder->build();
    }
}
