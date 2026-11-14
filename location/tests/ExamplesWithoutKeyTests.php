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

use fiftyone\pipeline\geolocation\tests\classes\ExampleRun;
use PHPUnit\Framework\TestCase;

/**
 * Runs the examples as someone runs them from a fresh clone, with no
 * resource key set, each in its own PHP. Each must say where to create a
 * resource key, with a tagged link to the configuration page, and end with
 * the Find out more section, with every 51degrees.com link it prints
 * tagged. What status an example exits with when no key is set is not
 * checked here.
 */
class ExamplesWithoutKeyTests extends TestCase
{
    // The start of the configuration page links the examples give.
    private const CONFIGURE = 'https://51degrees.com/configure';

    public function testGettingStarted()
    {
        $this->runWithoutKey('cloud/gettingstarted.php');
    }

    /**
     * The example reads the key from its configuration file, which holds a
     * placeholder.
     */
    public function testConfigureFromFile()
    {
        $this->runWithoutKey('cloud/configurefromfile.php');
    }

    public function testCombiningServices()
    {
        $this->runWithoutKey('cloud/combiningservices.php');
    }

    public function testWebIntegration()
    {
        $this->runWithoutKey('cloud/webIntegration.php', ExampleRun::WEB_REQUEST);
    }

    /**
     * The page the readme's web server serves first, which says what the
     * key is for rather than stopping with an error.
     */
    public function testIndex()
    {
        $run = $this->runWithoutKey('index.php', ExampleRun::WEB_REQUEST);
        $this->assertStringContainsString('_51DEGREES_RESOURCE_KEY', $run->output);
    }

    /**
     * @param array<string, string> $set Environment variables to add
     */
    private function runWithoutKey(string $script, array $set = []): ExampleRun
    {
        $run = ExampleRun::run(
            __DIR__ . "/../examples/{$script}",
            [],
            ExampleRun::RESOURCE_KEY_VARIABLES,
            $set
        );
        $this->assertStringContainsString(self::CONFIGURE, $run->output, $run->printed());
        $run->assertEndsWithFindOutMore();

        return $run;
    }
}
