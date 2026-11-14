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

use fiftyone\pipeline\core\AspectPropertyValue;
use fiftyone\pipeline\ipintelligence\examples\classes\ExampleUtils;
use PHPUnit\Framework\TestCase;

/**
 * The getting started examples, cloud and on-premise, print each property
 * through ExampleUtils::outputProperty, which shows each weighted value
 * with its raw weighting in brackets after it.
 */
class ExampleWeightingTests extends TestCase
{
    public function testAWeightedValueIsPrintedWithItsWeighting(): void
    {
        $data = new class {
            public function get(string $name): AspectPropertyValue
            {
                return new AspectPropertyValue(null, [
                    ['value' => 'GB', 'rawweighting' => 52428],
                    ['value' => 'FR', 'rawweighting' => 13107],
                ]);
            }
        };
        $output = fopen('php://memory', 'w+');

        ExampleUtils::outputProperty($data, 'RegisteredCountry', $output);

        rewind($output);
        $this->assertSame(
            "\tRegisteredCountry: GB (52428), FR (13107)\n",
            stream_get_contents($output)
        );
    }
}
