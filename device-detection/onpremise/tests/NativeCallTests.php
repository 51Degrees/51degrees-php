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
use fiftyone\pipeline\core\Pipeline;
use fiftyone\pipeline\core\PipelineBuilder;
use fiftyone\pipeline\devicedetection\DeviceDetectionOnPremise;
use fiftyone\pipeline\devicedetection\tests\classes\RecordingEngine;
use PHPUnit\Framework\TestCase;

/**
 * What the on-premise engine asks of the native engine, recorded by a
 * stand-in that passes each call on to the engine PHP built when it started.
 * Processing wraps the native results and reads no value or metadata, a
 * value is read only when it is asked for, and a property's metadata is
 * looked up once by an engine instance.
 */
class NativeCallTests extends TestCase
{
    private const IPHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 11_2 like ' .
        'Mac OS X) AppleWebKit/604.4.7 (KHTML, like Gecko) Mobile/15C114';

    private const REQUESTS = 3;

    protected function setUp(): void
    {
        if (!extension_loaded('FiftyOneDegreesHashEngine')) {
            $this->markTestSkipped('The FiftyOneDegreesHashEngine native extension is not loaded.');
        }
    }

    public function testProcessingReadsNoValueAndNoMetadata(): void
    {
        $native = null;
        $pipeline = self::pipeline($native);
        $flowData = self::process($pipeline);
        try {
            $this->assertSame([], $native->reads);
            $this->assertSame(0, $native->metaDataReads);
            $this->assertSame(0, $native->requiredPropertiesReads);
        } finally {
            $flowData->close();
            $pipeline->close();
        }
    }

    public function testAValueIsReadOnlyWhenItIsAskedFor(): void
    {
        $native = null;
        $pipeline = self::pipeline($native);
        $flowData = self::process($pipeline);
        try {
            $isMobile = $flowData->device->get('ismobile');

            $this->assertTrue($isMobile->value);
            $this->assertSame([['getValueAsBool', 'IsMobile']], $native->reads);
        } finally {
            $flowData->close();
            $pipeline->close();
        }
    }

    public function testAPropertysMetadataIsLookedUpOnce(): void
    {
        $native = null;
        $pipeline = self::pipeline($native);
        try {
            for ($request = 0; $request < self::REQUESTS; ++$request) {
                $flowData = self::process($pipeline);
                $flowData->device->get('ismobile');
                $flowData->close();
            }

            $this->assertCount(self::REQUESTS, $native->reads);
            $this->assertSame(1, $native->metaDataReads);
            $this->assertSame(1, $native->requiredPropertiesReads);
        } finally {
            $pipeline->close();
        }
    }

    /**
     * A pipeline of the engine class, with the recording stand-in in place
     * of the native engine, and no other element that reads values.
     */
    private static function pipeline(?RecordingEngine &$native): Pipeline
    {
        $engine = new DeviceDetectionOnPremise();
        $native = new RecordingEngine($engine->engine);
        $engine->engine = $native;

        return (new PipelineBuilder([
            'addJavaScriptBuilder' => false,
            'useSetHeaderProperties' => false,
        ]))->add($engine)->build();
    }

    private static function process(Pipeline $pipeline): FlowData
    {
        $flowData = $pipeline->createFlowData();
        $flowData->evidence->set('header.user-agent', self::IPHONE);
        $flowData->process();

        return $flowData;
    }
}
