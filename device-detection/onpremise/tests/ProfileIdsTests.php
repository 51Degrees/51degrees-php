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
use fiftyone\pipeline\devicedetection\SwigHelpers;
use PHPUnit\Framework\TestCase;

/**
 * The engine gives the ids of the profiles that hold a value of a property
 * through the native getProfileIdsForPropertyValue, matching the property
 * name and the value exactly, in ascending order with each id once. Every
 * data file, the free Lite file included, holds the property used here.
 */
class ProfileIdsTests extends TestCase
{
    private const PROPERTY = 'PlatformName';

    private const VALUE = 'iOS';

    private const IPHONE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 11_2 like ' .
        'Mac OS X) AppleWebKit/604.4.7 (KHTML, like Gecko) Mobile/15C114';

    private const VALUES_IN_ANOTHER_CASE = ['ios', 'IOS', 'Ios'];

    private const NAMES_IN_ANOTHER_CASE = ['platformname', 'PLATFORMNAME', 'Platformname'];

    protected function setUp(): void
    {
        if (!extension_loaded('FiftyOneDegreesHashEngine')) {
            $this->markTestSkipped('The FiftyOneDegreesHashEngine native extension is not loaded.');
        }
    }

    /**
     * The ids include the platform profile a detection of an iPhone finds.
     */
    public function testTheProfilesHoldingTheValueAreGiven(): void
    {
        $ids = self::profileIds(self::PROPERTY, self::VALUE);
        $this->assertNotSame([], $ids);

        $pipeline = (new PipelineBuilder([
            'addJavaScriptBuilder' => false,
            'useSetHeaderProperties' => false,
        ]))->add(new DeviceDetectionOnPremise())->build();
        $flowData = $pipeline->createFlowData();
        try {
            $flowData->evidence->set('header.user-agent', self::IPHONE);
            $flowData->process();
            $platformName = $flowData->device->get('platformname')->value;
            $deviceId = $flowData->device->get('deviceid')->value;
        } finally {
            $flowData->close();
            $pipeline->close();
        }

        $this->assertSame(self::VALUE, $platformName);
        $found = array_map('intval', explode('-', $deviceId));
        $this->assertNotSame(
            [],
            array_intersect($found, $ids),
            "None of the profiles of device id {$deviceId} is in the ids."
        );
    }

    public function testTheIdsAscendAndEachAppearsOnce(): void
    {
        $ids = self::profileIds(self::PROPERTY, self::VALUE);
        $expected = array_values(array_unique($ids));
        sort($expected);

        $this->assertSame($expected, $ids);
    }

    public function testAValueInAnotherCaseGivesNoProfiles(): void
    {
        foreach (self::VALUES_IN_ANOTHER_CASE as $value) {
            $this->assertSame([], self::profileIds(self::PROPERTY, $value), $value);
        }
    }

    public function testAPropertyNameInAnotherCaseGivesNoProfiles(): void
    {
        foreach (self::NAMES_IN_ANOTHER_CASE as $name) {
            $this->assertSame([], self::profileIds($name, self::VALUE), $name);
        }
    }

    /**
     * @return array<int>
     */
    private static function profileIds(string $property, string $value): array
    {
        $engine = new DeviceDetectionOnPremise();

        return SwigHelpers::vectorToArray(
            $engine->engine->getProfileIdsForPropertyValue($property, $value)
        );
    }
}
