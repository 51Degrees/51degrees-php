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

namespace fiftyone\pipeline\geolocation\tests;

use fiftyone\pipeline\geolocation\examples\cloud\classes\Constants;
use fiftyone\pipeline\geolocation\examples\cloud\classes\EnvironmentVariable;
use fiftyone\pipeline\geolocation\tests\classes\ExampleRun;
use fiftyone\pipeline\geolocation\tests\classes\TestKeys;
use PHPUnit\Framework\TestCase;

/**
 * Runs the examples with the resource key of the tests, given to them under
 * the name the examples read.
 */
class ExampleTests extends TestCase
{
    /** @var string|false What the examples' variable held before the test */
    private $previous = false;

    protected function setUp(): void
    {
        $key = EnvironmentVariable::read(TestKeys::RESOURCE_KEY_VARIABLE);
        if ($key === '') {
            $this->fail('Set ' . TestKeys::RESOURCE_KEY_VARIABLE .
                ' to run the examples with a resource key.');
        }
        $this->previous = getenv(Constants::RESOURCE_KEY_VARIABLE);
        putenv(Constants::RESOURCE_KEY_VARIABLE . '=' . $key);
    }

    protected function tearDown(): void
    {
        putenv($this->previous === false
            ? Constants::RESOURCE_KEY_VARIABLE
            : Constants::RESOURCE_KEY_VARIABLE . '=' . $this->previous);
    }

    public function testExamples()
    {
        ob_start(); // hide output
        include __DIR__ . '/../examples/index.php';
        ob_end_clean(); // discard output

        // The page sets $javascript only when a resource key is set.
        $this->assertNotEmpty(
            $javascript ?? null,
            'The page gave no JavaScript for the key in ' .
            TestKeys::RESOURCE_KEY_VARIABLE . '.'
        );
    }

    /**
     * The web integration page builds its pipeline for each request and
     * keeps no copy of it in a file, because a pipeline read back from a
     * file keeps the resource key it was built with.
     */
    public function testWebIntegrationLeavesNoPipelineBehind()
    {
        foreach (self::pipelineFiles() as $stale) {
            unlink($stale);
        }

        $run = ExampleRun::serve(__DIR__ . '/../examples/cloud/webIntegration.php');

        $this->assertSame(200, $run->httpStatus, $run->printed());
        $this->assertSame([], self::pipelineFiles());
    }

    /**
     * Every serialized pipeline file in the examples folder, in the folders
     * below it, and beside it, as a name built without a separator lands
     * beside its folder rather than in it.
     *
     * @return string[]
     */
    private static function pipelineFiles(): array
    {
        $examples = __DIR__ . '/../examples';
        $found = array_merge(
            (array) glob($examples . '/*.pipeline'),
            (array) glob($examples . '/*/*.pipeline'),
            (array) glob(dirname($examples) . '/*.pipeline')
        );

        return array_values(array_filter($found, 'is_file'));
    }
}
