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

use fiftyone\pipeline\devicedetection\tests\classes\ExampleRun;
use PHPUnit\Framework\TestCase;

/**
 * Runs the cloud examples as someone runs them from a fresh clone, with no
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

    public function testGettingStartedConsole()
    {
        $this->runWithoutKey('cloud/gettingStartedConsole.php');
    }

    public function testMetadataConsole()
    {
        $this->runWithoutKey('cloud/metadataConsole.php');
    }

    public function testNativeModelLookupConsole()
    {
        $this->runWithoutKey('cloud/nativeModelLookupConsole.php');
    }

    public function testTacLookupConsole()
    {
        $this->runWithoutKey('cloud/tacLookupConsole.php');
    }

    public function testFailureToMatch()
    {
        $this->runWithoutKey('cloud/failureToMatch.php');
    }

    public function testGettingStartedWeb()
    {
        $this->runWithoutKey('cloud/gettingStartedWeb.php', ExampleRun::WEB_REQUEST);
    }

    public function testUserAgentClientHintsWeb()
    {
        $this->runWithoutKey('cloud/userAgentClientHints-Web.php', ExampleRun::WEB_REQUEST);
    }

    /**
     * The conversion example needs no key and no cloud service.
     */
    public function testGhevToHttp()
    {
        $run = ExampleRun::run(
            __DIR__ . '/../examples/ghev-http-conversion/ghev-to-http.php',
            [],
            ExampleRun::RESOURCE_KEY_VARIABLES
        );
        $this->assertSame(0, $run->status, $run->printed());
        $this->assertStringContainsString('Sec-CH-UA-Platform', $run->output);
        $run->assertEndsWithFindOutMore();
    }

    /**
     * Runs the example with no resource key and checks what it printed.
     *
     * @param array<string, string> $set Environment variables to add
     */
    private function runWithoutKey(string $script, array $set = []): void
    {
        $run = ExampleRun::run(
            __DIR__ . "/../examples/{$script}",
            [],
            ExampleRun::RESOURCE_KEY_VARIABLES,
            $set
        );
        $this->assertStringContainsString(self::CONFIGURE, $run->output, $run->printed());
        $run->assertEndsWithFindOutMore();
    }
}
