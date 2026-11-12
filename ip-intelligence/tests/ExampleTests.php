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

use fiftyone\pipeline\ipintelligence\examples\classes\GetAllPropertiesConsoleCloud;
use fiftyone\pipeline\ipintelligence\examples\classes\GettingStartedConsoleCloud;
use fiftyone\pipeline\ipintelligence\examples\classes\GettingStartedConsoleOnPremise;
use fiftyone\pipeline\ipintelligence\examples\classes\MetadataConsoleCloud;
use fiftyone\pipeline\ipintelligence\examples\classes\MetadataConsoleOnPremise;
use fiftyone\pipeline\ipintelligence\examples\classes\MixedConsoleCloud;
use fiftyone\pipeline\ipintelligence\examples\classes\OfflineProcessingConsoleOnPremise;
use fiftyone\pipeline\ipintelligence\examples\classes\EnvironmentVariable;
use fiftyone\pipeline\ipintelligence\tests\classes\Constants;
use fiftyone\pipeline\ipintelligence\tests\classes\MockHttpClient;
use PHPUnit\Framework\TestCase;

class ExampleTests extends TestCase
{
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

    private function mockedCloudSettings(): array
    {
        return ['resourceKey' => 'mock_resource_key', 'httpClient' => new MockHttpClient()];
    }

    private function liveResourceKey(): ?string
    {
        $key = EnvironmentVariable::read(
            Constants::RESOURCE_KEY_VARIABLE,
            Constants::RESOURCE_KEY_ENV_VAR,
            '!!YOUR_RESOURCE_KEY!!'
        );

        return $key === '' ? null : $key;
    }

    // ---- Cloud examples (run offline via the mock) ----

    public function testCloudGettingStarted(): void
    {
        $output = $this->memoryStream();
        (new GettingStartedConsoleCloud())->run($this->mockedCloudSettings(), $output);
        $text = $this->readStream($output);

        $this->assertStringContainsString('Input values:', $text);
        $this->assertStringContainsString('Results:', $text);
        $this->assertStringContainsString('RegisteredName', $text);
        $this->assertStringNotContainsString('Fatal error', $text);
    }

    public function testCloudMetadata(): void
    {
        $output = $this->memoryStream();
        (new MetadataConsoleCloud())->run($this->mockedCloudSettings(), $output);
        $text = $this->readStream($output);

        $this->assertStringContainsString('IP intelligence properties', $text);
        $this->assertStringContainsString('CountryCode', $text);
    }

    public function testCloudGetAllProperties(): void
    {
        $output = $this->memoryStream();
        (new GetAllPropertiesConsoleCloud())->run($this->mockedCloudSettings(), $output);
        $text = $this->readStream($output);

        $this->assertStringContainsString('All IP intelligence properties', $text);
        $this->assertStringContainsString('Mcc', $text);
    }

    public function testCloudMixed(): void
    {
        $resourceKey = $this->liveResourceKey();
        if ($resourceKey === null) {
            $this->markTestSkipped('No resource_key set - the mixed cloud example needs a live key authorised for both device detection and IP intelligence.');
        }

        $output = $this->memoryStream();
        (new MixedConsoleCloud())->run(['resourceKey' => $resourceKey], $output);
        $text = $this->readStream($output);

        $this->assertStringContainsString('Device Detection Results:', $text);
        $this->assertStringContainsString('IP Intelligence Results:', $text);
    }

    // ---- On-premise examples, with the engine built when PHP started ----

    private function skipUnlessOnPremiseAvailable(): void
    {
        if (!extension_loaded('FiftyOneDegreesIpIntelligenceEngine')) {
            $this->markTestSkipped('The FiftyOneDegreesIpIntelligenceEngine native extension is not loaded.');
        }
        if (\ipi_engine_get() === null) {
            $this->markTestSkipped('No start-up engine. Start PHP with FiftyOneDegreesIpIntelligenceEngine.data_file set to run this test.');
        }
    }

    public function testOnPremiseGettingStarted(): void
    {
        $this->skipUnlessOnPremiseAvailable();
        $output = $this->memoryStream();
        (new GettingStartedConsoleOnPremise())->run($output);
        $text = $this->readStream($output);

        $this->assertStringContainsString('Input values:', $text);
        $this->assertStringContainsString('Results:', $text);
    }

    public function testOnPremiseMetadata(): void
    {
        $this->skipUnlessOnPremiseAvailable();
        $output = $this->memoryStream();
        (new MetadataConsoleOnPremise())->run($output);
        $text = $this->readStream($output);

        $this->assertStringContainsString('IP intelligence properties', $text);
    }

    public function testOnPremiseOfflineProcessing(): void
    {
        $this->skipUnlessOnPremiseAvailable();
        $output = $this->memoryStream();
        (new OfflineProcessingConsoleOnPremise())->run(['116.154.188.222', '1.3.32.31'], $output);
        $text = $this->readStream($output);

        $this->assertStringContainsString('query.client-ip:', $text);
    }
}
