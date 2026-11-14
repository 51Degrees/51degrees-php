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

use fiftyone\pipeline\devicedetection\examples\onpremise\classes\ExampleUtils;
use fiftyone\pipeline\devicedetection\tests\classes\ExampleRun;
use PHPUnit\Framework\TestCase;

/**
 * Runs the on-premise examples as they run once php.ini names the free Lite
 * data file, as the readme says to set it up, with no other settings. Each
 * runs in its own PHP process, because the engine loads its data file when
 * PHP starts. Each example must finish, show the properties only the
 * Enterprise data file has as needing it, give the link to ask for a copy,
 * and end with the Find out more section, with every 51degrees.com link it
 * prints tagged.
 */
class ExamplesWithoutSettingsTests extends TestCase
{
    // The start of every link the examples give to ask for a copy.
    private const CONTACT_US = 'https://51degrees.com/contact-us?';

    private const LITE_DATA_FILE = __DIR__ .
        '/../../../cxx/data/device-detection/51Degrees-LiteV4.1.hash';

    public static function setUpBeforeClass(): void
    {
        ExampleUtils::downloadMissingDataFile(self::LITE_DATA_FILE);
    }

    public function testGettingStartedConsole()
    {
        $printed = $this->runExample('gettingStartedConsole.php');
        $this->assertStringContainsString(
            'Hardware Vendor: ' . ExampleUtils::NEEDS_ENTERPRISE,
            $printed
        );
        $this->assertStringContainsString(
            'Some of these properties need the Enterprise data file. ' .
            'Contact us for a copy at ' . self::CONTACT_US,
            $printed
        );
    }

    public function testMetadataConsole()
    {
        $printed = $this->runExample('metadataConsole.php');
        $this->assertStringContainsString(
            'This example used the free Lite data file.',
            $printed
        );
        $this->assertStringContainsString(self::CONTACT_US, $printed);
    }

    public function testMatchMetrics()
    {
        $printed = $this->runExample('matchMetrics.php');
        $this->assertStringContainsString(
            'This example used the free Lite data file.',
            $printed
        );
        $this->assertStringContainsString(self::CONTACT_US, $printed);
        // The discussion of the match properties is a full address that a
        // console reader can follow, which runExample checks is tagged.
        $this->assertStringContainsString(
            'For a discussion of what the match properties mean, see: ' .
            'https://51degrees.com/documentation/_device_detection__hash.html?',
            $printed
        );
    }

    public function testFailureToMatch()
    {
        $printed = $this->runExample('failureToMatch.php');
        $this->assertStringContainsString(
            'Hardware Vendor: ' . ExampleUtils::NEEDS_ENTERPRISE,
            $printed
        );
        $this->assertStringContainsString(
            'Some of these properties need the Enterprise data file. ' .
            'Contact us for a copy at ' . self::CONTACT_US,
            $printed
        );
    }

    public function testManualDataUpdate()
    {
        $printed = $this->runExample('manualDataUpdate.php');
        $this->assertStringContainsString(
            'Hardware Vendor: ' . ExampleUtils::NEEDS_ENTERPRISE,
            $printed
        );
        $this->assertStringContainsString(
            'Some of these properties need the Enterprise data file. ' .
            'Contact us for a copy at ' . self::CONTACT_US,
            $printed
        );
    }

    public function testGettingStartedWeb()
    {
        $run = $this->runPage('gettingStartedWeb.php');
        $this->assertStringContainsString(self::CONTACT_US, $run->output);
    }

    public function testUserAgentClientHintsWeb()
    {
        $run = $this->runPage('userAgentClientHints-Web.php');
        $this->assertStringContainsString(
            ExampleUtils::NEEDS_ENTERPRISE,
            $run->output
        );
        $this->assertStringContainsString(self::CONTACT_US, $run->output);
    }

    /**
     * Runs an example script with php.ini's data file set to the Lite file,
     * checks that it finished and ended with Find out more, and returns what
     * it printed.
     */
    private function runExample(string $script): string
    {
        $run = $this->runWithLiteFile($script, []);
        $run->assertEndsWithFindOutMore();

        return $run->output . $run->errors;
    }

    /**
     * Renders a web example's page for a request for the home page, with
     * php.ini's data file set to the Lite file, and checks that it finished
     * and ended with Find out more.
     */
    private function runPage(string $script): ExampleRun
    {
        $run = $this->runWithLiteFile($script, ExampleRun::WEB_REQUEST);
        $run->assertPageEndsWithFindOutMore();

        return $run;
    }

    /**
     * @param array<string, string> $set Environment variables to add
     */
    private function runWithLiteFile(string $script, array $set): ExampleRun
    {
        $dataFile = realpath(self::LITE_DATA_FILE);
        $this->assertNotFalse($dataFile, 'The Lite data file is missing.');
        $run = ExampleRun::run(
            __DIR__ . "/../examples/onpremise/{$script}",
            ['-d', "FiftyOneDegreesHashEngine.data_file={$dataFile}"],
            [ExampleUtils::DD_PATH_VARIABLE, ExampleUtils::DATA_FILE_ENV_VAR],
            $set
        );
        $this->assertSame(0, $run->status, $run->printed());

        return $run;
    }
}
