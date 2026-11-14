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

namespace fiftyone\pipeline\ipintelligence\tests;

use fiftyone\pipeline\ipintelligence\examples\classes\ExampleUtils;
use fiftyone\pipeline\ipintelligence\tests\classes\ExampleRun;
use fiftyone\pipeline\ipintelligence\tests\classes\OnPremiseTestCase;

/**
 * The on-premise getting started example shows each weighted value with its
 * weighting, which it prints in brackets after the value, as a raw
 * weighting from 1 to 65535.
 */
class ExampleWeightingTests extends OnPremiseTestCase
{
    // A property line whose value ends with a raw weighting in brackets.
    private const WEIGHTED_LINE = '/^\t[^:\n]+: \S[^\n]* \((\d+)\)$/m';

    private const MAX_RAW_WEIGHTING = 65535;

    public function testTheGettingStartedExampleShowsEachValuesWeighting(): void
    {
        $run = ExampleRun::run(
            __DIR__ . '/../examples/onpremise/gettingstarted-console.php',
            ['-d', ExampleUtils::DATA_FILE_SETTING . '=' . realpath(self::$dataFile)]
        );
        $this->assertSame(0, $run->status, $run->printed());

        preg_match_all(self::WEIGHTED_LINE, $run->output, $matches);

        $this->assertNotSame([], $matches[1], $run->output);
        foreach ($matches[1] as $weighting) {
            $this->assertGreaterThan(0, (int) $weighting);
            $this->assertLessThanOrEqual(self::MAX_RAW_WEIGHTING, (int) $weighting);
        }
    }
}
