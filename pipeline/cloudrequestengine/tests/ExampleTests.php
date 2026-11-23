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

namespace fiftyone\pipeline\cloudrequestengine\tests;

use fiftyone\pipeline\cloudrequestengine\tests\classes\ExampleRun;
use PHPUnit\Framework\TestCase;

/**
 * Runs the examples as someone runs them from a fresh clone, each in its
 * own PHP with no settings.
 */
class ExampleTests extends TestCase
{
    // Settings that would point a Cloud Request Engine somewhere else. The
    // example must not need them, so they are left out of its run.
    private const SETTINGS = [
        '_51DEGREES_RESOURCE_KEY',
        '_51DEGREES_CLOUD_ENDPOINT'
    ];

    /**
     * The cloud engine starts the star sign service beside it, gets the
     * star sign for the date of birth from the service, gives no value
     * with the service's reason when there is no date of birth, stops the
     * service and ends with the links to find out more.
     */
    public function testCloudEngineExample(): void
    {
        $run = ExampleRun::run(
            __DIR__ . '/../examples/CloudEngine.php',
            [],
            self::SETTINGS
        );

        $this->assertSame(0, $run->status, $run->printed());
        $this->assertMatchesRegularExpression(
            '~^The star sign service is running at http://127\.0\.0\.1:[0-9]+/\.$~m',
            $run->output,
            $run->printed()
        );
        $this->assertStringContainsString(
            'With a date of birth of 1992-12-18, the star sign is Sagittarius.',
            $run->output,
            $run->printed()
        );
        $this->assertStringContainsString(
            'With no date of birth, the star sign has no value. No date of ' .
                'birth was sent to the star sign service.',
            $run->output,
            $run->printed()
        );
        $this->assertStringContainsString(
            'The star sign service has stopped.',
            $run->output,
            $run->printed()
        );
        $run->assertEndsWithFindOutMore();
    }
}
