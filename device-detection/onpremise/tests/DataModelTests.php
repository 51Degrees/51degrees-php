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

use fiftyone\pipeline\core\PipelineBuilder;
use fiftyone\pipeline\devicedetection\DeviceDetectionOnPremise;
use fiftyone\pipeline\engines\AspectData;
use PHPUnit\Framework\TestCase;

/**
 * The on-premise engine populates Aspect Data, as every Device Detection
 * engine must.
 */
class DataModelTests extends TestCase
{
    private const IPHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 11_2 like ' .
        'Mac OS X) AppleWebKit/604.4.7 (KHTML, like Gecko) Mobile/15C114';

    protected function setUp(): void
    {
        if (!extension_loaded('FiftyOneDegreesHashEngine')) {
            $this->markTestSkipped('The FiftyOneDegreesHashEngine native extension is not loaded.');
        }
    }

    public function testTheEngineGivesAspectData(): void
    {
        $pipeline = (new PipelineBuilder([
            'addJavaScriptBuilder' => false,
            'useSetHeaderProperties' => false,
        ]))->add(new DeviceDetectionOnPremise())->build();
        $flowData = $pipeline->createFlowData();
        try {
            $flowData->evidence->set('header.user-agent', self::IPHONE);
            $flowData->process();

            $this->assertInstanceOf(AspectData::class, $flowData->get('device'));
            $this->assertTrue($flowData->device->get('ismobile')->value);
        } finally {
            $flowData->close();
            $pipeline->close();
        }
    }
}
