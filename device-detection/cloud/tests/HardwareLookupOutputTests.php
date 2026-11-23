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

namespace fiftyone\pipeline\devicedetection\tests;

use fiftyone\pipeline\devicedetection\tests\classes\HardwareLookupOutput;
use PHPUnit\Framework\TestCase;

/**
 * Tests the check the live TAC and native model tests make of the
 * examples' output, with made-up output, so it needs no key.
 */
class HardwareLookupOutputTests extends TestCase
{
    // The values the TAC example looks up.
    private const TACS = ['35925406', '86386802'];

    // A heading the TAC example prints for each value.
    private const HEADING = "Which devices are associated with the TAC '%s'?";

    public function testADeviceWithAVendorAndAModel()
    {
        $this->assertTrue(HardwareLookupOutput::hasVendorAndModel('Apple iPhone 6 (A1586)'));
    }

    public function testAModelHoldingParentheses()
    {
        $this->assertTrue(HardwareLookupOutput::hasVendorAndModel(
            'Samsung Galaxy S10 (SM-G973F (Global))'
        ));
    }

    public function testAVendorWithNoValue()
    {
        $this->assertFalse(HardwareLookupOutput::hasVendorAndModel(
            'Unknown (HardwareVendor is a paid feature. You need a licence key to retrieve data.) iPhone 6 (A1586)'
        ));
    }

    public function testAModelWithNoValue()
    {
        $this->assertFalse(HardwareLookupOutput::hasVendorAndModel(
            'Apple iPhone 6 (Unknown (HardwareModel is a paid feature. You need a licence key to retrieve data.))'
        ));
    }

    public function testAModelNotInTheResults()
    {
        $this->assertFalse(HardwareLookupOutput::hasVendorAndModel(
            "Apple iPhone 6 (Unknown (the property 'hardwaremodel' is not in the results, so the current resource key does not include it))"
        ));
    }

    public function testAnEmptyModel()
    {
        $this->assertFalse(HardwareLookupOutput::hasVendorAndModel('Apple iPhone 6 ()'));
    }

    public function testTheLineForNoDevices()
    {
        $this->assertFalse(HardwareLookupOutput::hasVendorAndModel(
            'No devices are associated with this TAC.'
        ));
    }

    public function testOutputListingADeviceForEveryValuePasses()
    {
        $this->assertNull(HardwareLookupOutput::failure(
            $this->tacOutput([
                '35925406' => ['Apple iPhone 6 (A1586)'],
                '86386802' => ['Unknown (no value) 5217 (5217)', 'Coolpad 5200 (5200)']
            ]),
            'TAC lookup',
            'TAC',
            self::TACS
        ));
    }

    public function testAValueWithNoDevicesFails()
    {
        $output = $this->tacOutput([
            '35925406' => ['Apple iPhone 6 (A1586)'],
            '86386802' => ['No devices are associated with this TAC.']
        ]);

        $this->assertSame(
            "No device with a vendor and a model was listed for the TAC '86386802'.\n{$output}",
            HardwareLookupOutput::failure($output, 'TAC lookup', 'TAC', self::TACS)
        );
    }

    public function testAMissingHeadingFails()
    {
        $output = $this->tacOutput(['35925406' => ['Apple iPhone 6 (A1586)']]);

        $this->assertSame(
            "No device with a vendor and a model was listed for the TAC '86386802'.\n{$output}",
            HardwareLookupOutput::failure($output, 'TAC lookup', 'TAC', self::TACS)
        );
    }

    /**
     * The device lines are the ones under the value's own heading.
     */
    public function testDevicesUnderAnotherHeadingFail()
    {
        $output = $this->tacOutput([
            '35925406' => [],
            '86386802' => ['Apple iPhone 6 (A1586)']
        ]);

        $this->assertSame(
            "No device with a vendor and a model was listed for the TAC '35925406'.\n{$output}",
            HardwareLookupOutput::failure($output, 'TAC lookup', 'TAC', self::TACS)
        );
    }

    public function testTheLineSayingTheKeyLacksTheLookupFailsNamingTheKey()
    {
        $output = 'The resource key does not include HardwarePlatform.Profiles, ' .
            'the hardware lookup this example needs, so no devices are listed. ' .
            'Create a resource key that includes it at https://51degrees.com/configure/QKyYH5XT, ' .
            'or contact us about it at https://51degrees.com/contact-us.';

        $this->assertSame(
            'The resource key in _51DEGREES_RESOURCE_KEY_BESPOKE does not include ' .
            "HardwarePlatform.Profiles, which the native model lookup needs.\n{$output}",
            HardwareLookupOutput::failure(
                $output,
                'native model lookup',
                'native model name',
                ['SC-03L', 'iPhone11,8']
            )
        );
    }

    /**
     * Output as the TAC example prints it, with each value's heading and
     * its device lines, each after a tab.
     *
     * @param array<string, string[]> $devices The device lines of each value
     */
    private function tacOutput(array $devices): string
    {
        $lines = [];
        foreach ($devices as $value => $deviceLines) {
            $lines[] = sprintf(self::HEADING, $value);
            foreach ($deviceLines as $line) {
                $lines[] = "\t{$line}";
            }
        }

        return implode("\n", $lines);
    }
}
