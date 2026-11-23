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

use fiftyone\pipeline\engines\tests\classes\ExampleRun;
use PHPUnit\Framework\TestCase;

/**
 * Runs the examples as someone runs them from a fresh clone, each in its
 * own PHP with no settings.
 */
class ExampleTests extends TestCase
{
    /**
     * The on-premise engine reads its star signs from the file beside the
     * example, gives the star sign for the date of birth, gives no value
     * with a reason when there is no date of birth, and ends with the links
     * to find out more.
     */
    public function testOnPremiseEngineExample(): void
    {
        $run = ExampleRun::run(
            __DIR__ . '/../examples/OnPremiseEngine.php',
            [],
            ExampleRun::RESOURCE_KEY_VARIABLES
        );

        $this->assertSame(0, $run->status, $run->printed());
        $this->assertStringContainsString(
            'With a date of birth of 1992-12-18, the star sign is Sagittarius.',
            $run->output,
            $run->printed()
        );
        $this->assertStringContainsString(
            'With no date of birth, the star sign has no value. No date of ' .
                "birth was given in the evidence 'query.dateOfBirth'.",
            $run->output,
            $run->printed()
        );
        $run->assertEndsWithFindOutMore();
    }
}
