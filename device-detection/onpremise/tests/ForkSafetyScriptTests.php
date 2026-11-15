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

use fiftyone\pipeline\devicedetection\tests\classes\ExampleRun;
use PHPUnit\Framework\TestCase;

/**
 * The fork-safety test script refuses to run without a User-Agents file,
 * the same way every time, rather than passing or failing by chance.
 */
class ForkSafetyScriptTests extends TestCase
{
    private const SCRIPT = __DIR__ . '/../configuration-tests/testForkSafety.php';

    // Runs of the script without the file, which must all give the same
    // result.
    private const RUNS = 3;

    // The exit code the script gives when something it needs is missing.
    private const MISSING = 2;

    public function testWithNoUserAgentsFileItRefusesEveryTime(): void
    {
        for ($run = 0; $run < self::RUNS; ++$run) {
            // Both names the script reads the file from are left out.
            $result = ExampleRun::run(
                self::SCRIPT,
                [],
                ['_51DEGREES_UA_CSV', 'FIFTYONE_UA_CSV']
            );

            $this->assertSame(self::MISSING, $result->status, $result->errors);
            $this->assertStringContainsString(
                'User-Agents file not found (set _51DEGREES_UA_CSV',
                $result->errors
            );
        }
    }

    public function testWithAUserAgentsFileThatIsNotThereItRefuses(): void
    {
        $missing = sys_get_temp_dir() . '/no-such-user-agents.csv';

        $result = ExampleRun::run(
            self::SCRIPT,
            [],
            [],
            ['_51DEGREES_UA_CSV' => $missing]
        );

        $this->assertSame(self::MISSING, $result->status, $result->errors);
        $this->assertStringContainsString($missing, $result->errors);
    }
}
