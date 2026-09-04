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

use fiftyone\pipeline\ipintelligence\IpIntelligenceOnPremise;
use fiftyone\pipeline\ipintelligence\IpIntelligenceOnPremisePipelineBuilder;
use fiftyone\pipeline\ipintelligence\tests\classes\Constants;
use PHPUnit\Framework\TestCase;

/**
 * On-premise IP intelligence tests.
 *
 * These tests require the native FiftyOneDegreesIpIntelligenceEngine
 * extension to be built and loaded, and an .ipi data file to be available.
 * When either is missing the tests are skipped (the same convention used by
 * device-detection-php-onpremise), so the suite is green in environments
 * where the native engine has not been built.
 */
class IpIntelligenceOnPremiseTests extends TestCase
{
    private static ?string $dataFile = null;

    public static function setUpBeforeClass(): void
    {
        self::$dataFile = self::locateDataFile();
    }

    protected function setUp(): void
    {
        if (!extension_loaded('FiftyOneDegreesIpIntelligenceEngine')) {
            $this->markTestSkipped('The FiftyOneDegreesIpIntelligenceEngine native extension is not loaded.');
        }
        if (self::$dataFile === null) {
            $this->markTestSkipped('No .ipi data file found (set IPINTELLIGENCEDATAFILE or place a file under ip-intelligence-data).');
        }
    }

    private static function locateDataFile(): ?string
    {
        $env = getenv(Constants::IP_INTELLIGENCE_DATA_FILE_ENV_VAR);
        if ($env && file_exists($env)) {
            return $env;
        }

        $candidates = array_merge(
            glob(__DIR__ . '/../assets/*.ipi') ?: [],
            glob(__DIR__ . '/../ip-intelligence-data/*.ipi') ?: [],
            glob(__DIR__ . '/../on-premise/ip-intelligence-cxx/ip-intelligence-data/*.ipi') ?: []
        );

        foreach ($candidates as $candidate) {
            if (file_exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function buildEngine(): IpIntelligenceOnPremise
    {
        return new IpIntelligenceOnPremise([
            'dataFile' => self::$dataFile,
            'performanceProfile' => 'MaxPerformance'
        ]);
    }

    public function testElementDataKey(): void
    {
        $engine = $this->buildEngine();
        $this->assertSame('ip', $engine->dataKey);
    }

    public function testPropertiesPopulated(): void
    {
        $engine = $this->buildEngine();
        $properties = $engine->getProperties();
        $this->assertNotEmpty($properties);
        // The synthetic echo properties are always present.
        $this->assertArrayHasKey('ip', $properties);
        $this->assertArrayHasKey('ipv6', $properties);
    }

    public function testEvidenceKeyFilter(): void
    {
        $engine = $this->buildEngine();
        $filter = $engine->getEvidenceKeyFilter();
        // The engine should accept at least one client-ip evidence key.
        $accepts = $filter->filterEvidenceKey('query.client-ip')
            || $filter->filterEvidenceKey('server.client-ip')
            || $filter->filterEvidenceKey('query.client-ip-51d');
        $this->assertTrue($accepts);
    }

    public function testProcessIpv4(): void
    {
        $pipeline = (new IpIntelligenceOnPremisePipelineBuilder([
            'dataFile' => self::$dataFile,
            'performanceProfile' => 'MaxPerformance'
        ]))->build();

        $flowData = $pipeline->createFlowData();
        $flowData->evidence->set('query.client-ip', Constants::IPV4_ADDRESS);
        $result = $flowData->process();

        $ip = $result->ip;

        // The supplied IP is echoed back.
        $this->assertTrue($ip->ip->hasValue);
        $this->assertSame(Constants::IPV4_ADDRESS, $ip->ip->value);

        // RegisteredName is a core IP intelligence property. It is only
        // present in some data files (for example it is absent from the ASN
        // file), and get() throws for a property the data file does not
        // contain, so only assert on it when the engine actually exposes it.
        $properties = $pipeline->getElement('ip')->getProperties();
        if (array_key_exists('registeredname', $properties)) {
            $registeredName = $ip->get('registeredname');
            $this->assertNotNull($registeredName);
            if ($registeredName->hasValue) {
                $this->assertNotNull($registeredName->value);
            } else {
                $this->assertNotNull($registeredName->noValueMessage);
            }
        }
    }

    public function testInvalidPerformanceProfileRejected(): void
    {
        $this->expectException(\Exception::class);
        new IpIntelligenceOnPremise([
            'dataFile' => self::$dataFile,
            'performanceProfile' => 'NotARealProfile'
        ]);
    }
}
