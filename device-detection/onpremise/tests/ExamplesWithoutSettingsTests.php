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
use PHPUnit\Framework\TestCase;

/**
 * Runs the on-premise console examples as they run once php.ini names the
 * free Lite data file, as the readme says to set it up, with no other
 * settings. Each runs in its own PHP process, because the engine loads its
 * data file when PHP starts. Each example must finish, show the properties
 * only the Enterprise data file has as needing it, and give the link to ask
 * for a copy.
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
    }

    /**
     * Runs an example script with php.ini's data file set to the Lite file,
     * and returns what it printed.
     */
    private function runExample(string $script): string
    {
        $dataFile = realpath(self::LITE_DATA_FILE);
        $this->assertNotFalse($dataFile, 'The Lite data file is missing.');
        $command = escapeshellarg(PHP_BINARY) . ' -d ' .
            escapeshellarg("FiftyOneDegreesHashEngine.data_file={$dataFile}") .
            ' ' . escapeshellarg(__DIR__ . "/../examples/onpremise/{$script}") .
            ' 2>&1';
        exec($command, $lines, $status);
        $printed = implode("\n", $lines);
        $this->assertSame(0, $status, $printed);

        return $printed;
    }
}
