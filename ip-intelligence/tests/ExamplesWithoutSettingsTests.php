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
use fiftyone\pipeline\ipintelligence\examples\classes\GettingStartedConsoleOnPremise;
use fiftyone\pipeline\ipintelligence\examples\classes\MetadataConsoleOnPremise;
use fiftyone\pipeline\ipintelligence\examples\classes\MixedConsoleOnPremise;
use fiftyone\pipeline\ipintelligence\examples\classes\OfflineProcessingConsoleOnPremise;
use fiftyone\pipeline\ipintelligence\examples\classes\PerformanceConsoleOnPremise;
use fiftyone\pipeline\ipintelligence\examples\classes\SuspiciousConsoleOnPremise;
use fiftyone\pipeline\ipintelligence\examples\classes\TranslationConsoleOnPremise;
use PHPUnit\Framework\TestCase;

/**
 * Runs the on-premise examples on the free data files a fresh clone
 * downloads, the Lite file and, for the performance example, the ASN file.
 * Each example must finish, show the properties only the Enterprise data
 * file has as needing it, and give the link to ask for a copy.
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
        $output = $this->memoryStream();
        (new GettingStartedConsoleOnPremise())->run($this->liteDataFile(), $output);
        $text = $this->readStream($output);
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
        $output = $this->memoryStream();
        (new MetadataConsoleOnPremise())->run($this->liteDataFile(), $output);
        $text = $this->readStream($output);
        $this->assertStringContainsString(
            'This example used a free data file.',
            $text
        );
        $this->assertStringContainsString(self::CONTACT_US, $text);
    }

    public function testOfflineProcessing(): void
    {
        $output = $this->memoryStream();
        (new OfflineProcessingConsoleOnPremise())->run(
            $this->liteDataFile(),
            ['116.154.188.222', '1.3.32.31'],
            $output
        );
        $text = $this->readStream($output);
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
        $output = $this->memoryStream();
        (new PerformanceConsoleOnPremise())->run(
            $this->freeDataFile(ExampleConstants::ASN_DATA_FILE_NAME),
            1.0,
            $output
        );
        $text = $this->readStream($output);
        $this->assertMatchesRegularExpression('/Detections: [1-9]/', $text);
        $this->assertStringContainsString(
            'This example used a free data file.',
            $text
        );
        $this->assertStringContainsString(self::CONTACT_US, $text);
    }

    public function testSuspicious(): void
    {
        $output = $this->memoryStream();
        (new SuspiciousConsoleOnPremise())->run($this->liteDataFile(), $output);
        $text = $this->readStream($output);
        $this->assertStringContainsString(
            'Finding suspicious IP addresses needs the',
            $text
        );
        $this->assertStringContainsString(self::CONTACT_US, $text);
        $this->assertStringNotContainsString('IsSuspicious', $text);
    }

    public function testTranslation(): void
    {
        $output = $this->memoryStream();
        (new TranslationConsoleOnPremise())->run($this->liteDataFile(), $output);
        $text = $this->readStream($output);
        $this->assertStringContainsString(
            'Listing the countries an IP address is most likely in needs ' .
            'the CountryCodesGeographical and CountryCodesPopulation properties',
            $text
        );
        $this->assertStringContainsString(self::CONTACT_US, $text);
    }

    public function testMixed(): void
    {
        if (!class_exists(MixedConsoleOnPremise::DEVICE_DETECTION_ONPREMISE_CLASS) ||
            !extension_loaded('FiftyOneDegreesHashEngine')) {
            $this->markTestSkipped(
                'The device detection on-premise package and extension are not installed.'
            );
        }
        $output = $this->memoryStream();
        (new MixedConsoleOnPremise())->run($this->liteDataFile(), $output);
        $text = $this->readStream($output);
        $this->assertStringContainsString(ExampleUtils::NEEDS_ENTERPRISE, $text);
        $this->assertStringContainsString(
            'Some of these properties need the Enterprise data file.',
            $text
        );
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

    /**
     * @return resource
     */
    private function memoryStream()
    {
        return fopen('php://memory', 'rw+');
    }

    /**
     * @param resource $stream
     */
    private function readStream($stream): string
    {
        rewind($stream);

        return (string) stream_get_contents($stream);
    }
}
