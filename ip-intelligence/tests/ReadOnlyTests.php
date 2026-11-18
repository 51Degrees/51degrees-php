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

use fiftyone\pipeline\core\PipelineBuilder;
use fiftyone\pipeline\ipintelligence\IpIntelligencePipelineBuilder;
use fiftyone\pipeline\ipintelligence\tests\classes\Constants;
use fiftyone\pipeline\ipintelligence\tests\classes\MockHttpClient;
use fiftyone\pipeline\ipintelligence\tests\classes\MockIpEngine;
use fiftyone\pipeline\ipintelligence\translation\CountriesTranslationEngine;
use fiftyone\pipeline\ipintelligence\translation\CountryCodeTranslationEngine;
use PHPUnit\Framework\TestCase;

/**
 * The ElementData of IpIntelligenceCloud, CountryCodeTranslationEngine and
 * CountriesTranslationEngine is read only once each has finished. The
 * on-premise engine's test is ReadOnlyOnPremiseTests.
 */
class ReadOnlyTests extends TestCase
{
    public function testIpIntelligenceCloudElementDataIsReadOnlyOnceProcessed(): void
    {
        $pipeline = (new IpIntelligencePipelineBuilder([
            'resourceKey' => 'mock_resource_key',
            'httpClient' => new MockHttpClient()
        ]))->build();
        $flowData = $pipeline->createFlowData();
        $flowData->evidence->set('query.client-ip-51d', Constants::IPV4_ADDRESS);
        $flowData->process();

        $this->expectExceptionMessage('has finished processing');
        $flowData->ip->probe = 1;
    }

    public function testTheTranslationEnginesElementDataIsReadOnlyOnceProcessed(): void
    {
        $codes = new CountryCodeTranslationEngine();
        $names = new CountriesTranslationEngine();
        $flowData = (new PipelineBuilder([]))
            ->add(new MockIpEngine(
                [['value' => 'GB', 'rawweighting' => 1]],
                [['value' => 'GB', 'rawweighting' => 1]]
            ))
            ->add($codes)
            ->add($names)
            ->build()->createFlowData();
        $flowData->process();

        foreach (array_unique([$codes->dataKey, $names->dataKey]) as $key) {
            try {
                $flowData->get($key)->probe = 1;
                $this->fail("A write to the $key data did not throw.");
            } catch (\Exception $e) {
                $this->assertStringContainsString('has finished processing', $e->getMessage(), $key);
            }
        }
    }
}
