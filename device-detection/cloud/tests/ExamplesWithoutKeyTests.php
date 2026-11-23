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
 * resource key set, the console examples each in its own PHP and the pages
 * with PHP's built-in server. Each must name the environment variable to
 * set, say where to create a resource key, with a tagged link to the
 * configuration page, end with the Find out more section, with every
 * 51degrees.com link it prints tagged, and end successfully, a console
 * example with exit status 0 and a page with HTTP status 200.
 */
class ExamplesWithoutKeyTests extends TestCase
{
    // The start of the configuration page links the examples give.
    private const CONFIGURE = 'https://51degrees.com/configure';

    // The environment variable the examples are told to set.
    private const RESOURCE_KEY_VARIABLE = '_51DEGREES_RESOURCE_KEY';

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
        $this->serveWithoutKey('cloud/gettingStartedWeb.php');
    }

    public function testUserAgentClientHintsWeb()
    {
        $this->serveWithoutKey('cloud/userAgentClientHints-Web.php');
    }

    public function testHumanConfidenceWeb()
    {
        $this->serveWithoutKey('cloud/humanConfidenceWeb.php');
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
     * Runs the console example with no resource key and checks what it
     * printed and that it exited with status 0.
     */
    private function runWithoutKey(string $script): void
    {
        $run = ExampleRun::run(
            __DIR__ . "/../examples/{$script}",
            [],
            ExampleRun::RESOURCE_KEY_VARIABLES
        );
        $this->assertSame(0, $run->status, $run->printed());
        $this->assertStringContainsString(self::RESOURCE_KEY_VARIABLE, $run->output, $run->printed());
        $this->assertStringContainsString(self::CONFIGURE, $run->output, $run->printed());
        $run->assertEndsWithFindOutMore();
    }

    /**
     * Serves the page with no resource key and checks what it showed and
     * that it answered with HTTP status 200.
     */
    private function serveWithoutKey(string $script): void
    {
        $run = ExampleRun::serve(
            __DIR__ . "/../examples/{$script}",
            ExampleRun::RESOURCE_KEY_VARIABLES
        );
        $this->assertSame(200, $run->httpStatus, $run->printed());
        $page = html_entity_decode($run->output);
        $this->assertStringContainsString(self::RESOURCE_KEY_VARIABLE, $page, $run->printed());
        $this->assertStringContainsString(self::CONFIGURE, $page, $run->printed());
        $run->assertPageEndsWithFindOutMore();
    }
}
