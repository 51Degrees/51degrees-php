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

use fiftyone\pipeline\core\AspectPropertyValue;
use fiftyone\pipeline\core\PipelineBuilder;
use fiftyone\pipeline\devicedetection\DeviceDetectionCloud;
use fiftyone\pipeline\devicedetection\HardwareProfileCloud;
use fiftyone\pipeline\devicedetection\tests\classes\FakeCloudRequestEngine;
use PHPUnit\Framework\TestCase;

/**
 * Each hardware profile is read the way the results of the cloud Device
 * Detection engine are, and still reads as the array of values it used to
 * be. The tests run against a fixed cloud response, so they need no
 * resource key and no network connection.
 */
class HardwareProfileDataTests extends TestCase
{
    // The same values for the device and for its one hardware profile.
    private const RESPONSE = <<<'JSON'
        {
            "device": {
                "hardwarevendor": "Apple",
                "hardwarename": ["iPhone 6"]
            },
            "hardware": {
                "profiles": [
                    {
                        "hardwarevendor": "Apple",
                        "hardwarename": ["iPhone 6"]
                    }
                ]
            }
        }
        JSON;

    public function testEachProfileIsTheDataTheCloudEngineGives(): void
    {
        [$device, $profile] = $this->process();

        $this->assertInstanceOf(get_class($device), $profile);
    }

    public function testAProfileIsReadLikeTheDeviceResults(): void
    {
        [$device, $profile] = $this->process();

        foreach (['hardwarevendor', 'hardwarename'] as $name) {
            $this->assertEquals($device->get($name), $profile->get($name));
            $this->assertEquals($device->{$name}, $profile->{$name});
        }
        $this->assertEquals($device->asDictionary(), $profile->asDictionary());
    }

    public function testAMissingPropertyIsReportedLikeTheDeviceResults(): void
    {
        [$device, $profile] = $this->process();

        $this->assertNotNull(self::thrown(static function () use ($device) {
            $device->get('screenpixelswidth');
        }));
        $this->assertNotNull(self::thrown(static function () use ($profile) {
            $profile->get('screenpixelswidth');
        }));
    }

    public function testAProfileStillReadsAsAnArray(): void
    {
        [, $profile] = $this->process();

        $this->assertSame('Apple', $profile['hardwarevendor']->value);
        $this->assertTrue(isset($profile['hardwarevendor']));
        $this->assertFalse(isset($profile['screenpixelswidth']));
        $this->assertNull($profile['screenpixelswidth'] ?? null);
        $this->assertCount(2, $profile);

        $names = [];
        foreach ($profile as $name => $value) {
            $names[] = $name;
            $this->assertInstanceOf(AspectPropertyValue::class, $value);
        }
        $this->assertSame(['hardwarevendor', 'hardwarename'], $names);

        $this->assertSame(
            json_encode([
                'hardwarevendor' => new AspectPropertyValue(null, 'Apple'),
                'hardwarename' => new AspectPropertyValue(null, ['iPhone 6']),
            ]),
            json_encode($profile)
        );
    }

    public function testAProfileCannotBeChanged(): void
    {
        [, $profile] = $this->process();

        $this->expectException(\LogicException::class);
        $profile['hardwarevendor'] = new AspectPropertyValue(null, 'Other');
    }

    /**
     * Process the fixed response with the cloud Device Detection engine and
     * the hardware profile engine, and return the device results and the
     * first hardware profile.
     *
     * @return array{0: mixed, 1: mixed}
     */
    private function process(): array
    {
        $pipeline = (new PipelineBuilder())
            ->add(new FakeCloudRequestEngine(self::RESPONSE))
            ->add(new DeviceDetectionCloud())
            ->add(new HardwareProfileCloud())
            ->build();

        $flowData = $pipeline->createFlowData();
        $flowData->process();

        $this->assertSame([], $flowData->errors);

        return [$flowData->device, $flowData->hardware->profiles[0]];
    }

    /**
     * The exception the function throws, or null when it throws none.
     */
    private static function thrown(callable $read): ?\Exception
    {
        try {
            $read();
        } catch (\Exception $e) {
            return $e;
        }

        return null;
    }
}
