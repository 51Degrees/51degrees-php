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

use fiftyone\pipeline\ipintelligence\examples\classes\ExampleConstants;
use fiftyone\pipeline\ipintelligence\examples\classes\ExampleUtils;
use PHPUnit\Framework\TestCase;

/**
 * Runs the on-premise console examples as they run once php.ini names a
 * free data file, as OnPremise.md says to set it up, with no other
 * settings. That is the Lite file and, for the performance example, the ASN
 * file. Each runs in its own PHP process, because the extension builds its
 * engine from the data file when PHP starts. Each example must finish, show
 * the properties only the Enterprise data file has as needing it, and give
 * the link to ask for a copy. MixedExamplesTests runs the examples that use
 * the Device Detection extension as well.
 */
class ExamplesWithoutSettingsTests extends TestCase
{
    // The start of every link the examples give to ask for a copy.
    private const CONTACT_US = 'https://51degrees.com/contact-us?';

    // Where the data repository keeps the free data files.
    private const DATA = __DIR__ . '/../../cxx/data/ip-intelligence';

    protected function setUp(): void
    {
        if (!extension_loaded('FiftyOneDegreesIpIntelligenceEngine')) {
            $this->markTestSkipped(
                'The FiftyOneDegreesIpIntelligenceEngine native extension is not loaded.'
            );
        }
    }

    public function testGettingStarted(): void
    {
        $text = $this->runExample('gettingstarted-console.php');
        $this->assertStringContainsString(
            "\tCountry: " . ExampleUtils::NEEDS_ENTERPRISE,
            $text
        );
        $this->assertStringContainsString(
            'Some of these properties need the Enterprise data file. ' .
            'Contact us for a copy at ' . self::CONTACT_US,
            $text
        );
    }

    public function testMetadata(): void
    {
        $text = $this->runExample('metadata-console.php');
        $this->assertStringContainsString(
            'This example used a free data file.',
            $text
        );
        $this->assertStringContainsString(self::CONTACT_US, $text);
    }

    public function testOfflineProcessing(): void
    {
        $text = $this->runExample('offlineprocessing-console.php');
        $this->assertStringContainsString(
            "\tCountry: " . ExampleUtils::NEEDS_ENTERPRISE,
            $text
        );
        $this->assertStringContainsString(
            '# Some of these properties need the Enterprise data file.',
            $text
        );
    }

    public function testPerformance(): void
    {
        $text = $this->runExample(
            'performance-console.php',
            $this->freeDataFile(ExampleConstants::ASN_DATA_FILE_NAME),
            ['', '1']
        );
        $this->assertMatchesRegularExpression('/Detections: [1-9]/', $text);
        $this->assertStringContainsString(
            'This example used a free data file.',
            $text
        );
        $this->assertStringContainsString(self::CONTACT_US, $text);
    }

    public function testSuspicious(): void
    {
        $text = $this->runExample('suspicious-console.php');
        $this->assertStringContainsString(
            'Finding suspicious IP addresses needs the',
            $text
        );
        $this->assertStringContainsString(self::CONTACT_US, $text);
        $this->assertStringNotContainsString('IsSuspicious', $text);
    }

    public function testTranslation(): void
    {
        $text = $this->runExample('translation-console.php');
        $this->assertStringContainsString(
            'Listing the countries an IP address is most likely in needs ' .
            'the CountryCodesGeographical and CountryCodesPopulation properties',
            $text
        );
        $this->assertStringContainsString(self::CONTACT_US, $text);
    }

    /**
     * With no data file in php.ini, an example runs again in a PHP that
     * builds the engine when it starts, from the data file the example
     * finds, so that it runs with nothing set in php.ini. The Lite file is
     * named, because CI also has the Enterprise file, which the example
     * would find first.
     */
    public function testWithNoDataFileInPhpIniTheExampleStillRuns(): void
    {
        $dataFile = realpath($this->liteDataFile());
        $this->assertNotFalse($dataFile, 'The Lite data file is missing.');
        $command = escapeshellarg(PHP_BINARY) . ' -d ' .
            escapeshellarg(ExampleUtils::DATA_FILE_SETTING . '=') . ' ' .
            escapeshellarg(
                __DIR__ . '/../examples/onpremise/gettingstarted-console.php'
            ) . ' ' . escapeshellarg($dataFile) . ' 2>&1';
        exec($command, $lines, $status);
        $printed = implode("\n", $lines);
        $this->assertSame(0, $status, $printed);
        $this->assertStringContainsString(
            'No IP intelligence engine was built when PHP started, so the ' .
            "example runs again in a PHP that builds one from {$dataFile}.",
            $printed
        );
        $this->assertStringContainsString(
            "\tCountry: " . ExampleUtils::NEEDS_ENTERPRISE,
            $printed
        );
    }

    /**
     * Runs an example script with php.ini's data file set to $dataFile, the
     * Lite file unless another is given, and returns what it printed.
     *
     * @param array<string> $args
     */
    private function runExample(
        string $script,
        ?string $dataFile = null,
        array $args = []
    ): string {
        $dataFile = realpath($dataFile ?? $this->liteDataFile());
        $this->assertNotFalse($dataFile, 'The data file is missing.');
        $command = escapeshellarg(PHP_BINARY) . ' -d ' .
            escapeshellarg(ExampleUtils::DATA_FILE_SETTING . "={$dataFile}") .
            ' ' . escapeshellarg(__DIR__ . "/../examples/onpremise/{$script}");
        foreach ($args as $arg) {
            $command .= ' ' . escapeshellarg($arg);
        }
        exec($command . ' 2>&1', $lines, $status);
        $printed = implode("\n", $lines);
        $this->assertSame(0, $status, $printed);

        return $printed;
    }

    /**
     * The free Lite data file. CI fetches it into the package's assets
     * folder under its asset name, and a fresh clone downloads it into the
     * data repository.
     */
    private function liteDataFile(): string
    {
        $asset = __DIR__ . '/../assets/51Degrees-LiteIpiV41.ipi';
        if (is_file($asset)) {
            return $asset;
        }

        return $this->freeDataFile(ExampleConstants::LITE_DATA_FILE_NAME);
    }

    /**
     * A free data file in the data repository, downloaded when it is
     * missing.
     */
    private function freeDataFile(string $name): string
    {
        $path = self::DATA . '/' . $name;
        $this->assertTrue(
            ExampleUtils::downloadMissingDataFile($path),
            "The free data file {$name} could not be found or downloaded."
        );

        return $path;
    }
}
