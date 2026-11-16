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

    public function testGettingStarted()
    {
        $this->runWithoutKey('cloud/gettingstarted.php');
    }

    /**
     * The example reads the key from the environment variable, or else
     * from its configuration file, which holds a placeholder.
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
        $this->serveWithoutKey('cloud/webIntegration.php');
    }

    /**
     * The page the readme's web server serves first, which says what the
     * key is for rather than stopping with an error.
     */
    public function testIndex()
    {
        $this->serveWithoutKey('index.php');
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
        $this->assertStringNotContainsString(
            '<br',
            $run->output,
            "A console example printed an HTML line break.\n" . $run->printed()
        );
        $this->assertLinksStandApart($run->output, $run->printed());
        $run->assertEndsWithFindOutMore();
    }

    /**
     * The sentence that says where to create a resource key gives the
     * configuration page link after a space and the word "at", so that the
     * word before it does not run into it, as "this exampleat https://" did.
     */
    private function assertLinksStandApart(string $text, string $printed): void
    {
        $this->assertMatchesRegularExpression(
            '#Create a resource key[^\n]* at ' . preg_quote(self::CONFIGURE, '#') . '#',
            $text,
            "The sentence about creating a resource key runs a word into its link.\n" . $printed
        );
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
        $this->assertLinksStandApart(strip_tags($page), $run->printed());
        $run->assertPageEndsWithFindOutMore();
    }
}
