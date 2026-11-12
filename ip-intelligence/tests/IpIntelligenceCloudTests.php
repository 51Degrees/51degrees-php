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

use fiftyone\pipeline\ipintelligence\IpIntelligenceCloud;
use fiftyone\pipeline\ipintelligence\IpIntelligencePipelineBuilder;
use fiftyone\pipeline\ipintelligence\tests\classes\Constants;
use fiftyone\pipeline\ipintelligence\tests\classes\MockHttpClient;
use PHPUnit\Framework\TestCase;

class IpIntelligenceCloudTests extends TestCase
{
    /**
     * Build a cloud pipeline backed by the MockHttpClient so the tests run
     * with no network access or resource key.
     */
    private function buildMockedPipeline()
    {
        $builder = new IpIntelligencePipelineBuilder([
            'resourceKey' => 'mock_resource_key',
            'httpClient' => new MockHttpClient()
        ]);

        return $builder->build();
    }

    /**
     * A Resource Key with no IP Intelligence product still builds the
     * Pipeline, and each request gives empty IP Intelligence data rather
     * than an error.
     */
    public function testMissingProductBuildsAndGivesEmptyData(): void
    {
        $client = new class extends MockHttpClient {
            public function makeCloudRequest(string $type, string $url, ?string $content, ?string $originHeader): string
            {
                if (strpos($url, 'accessibleProperties') !== false) {
                    return '{"Products": {"device": {"DataTier": "tier", "Properties": [{"Name": "IsMobile", "Type": "Boolean", "Category": "Device"}]}}}';
                }

                if (strpos($url, '.json') !== false) {
                    return '{"device": {"ismobile": true}, "javascriptProperties": []}';
                }

                return parent::makeCloudRequest($type, $url, $content, $originHeader);
            }
        };
        $pipeline = (new IpIntelligencePipelineBuilder([
            'resourceKey' => 'mock_resource_key',
            'httpClient' => $client
        ]))->build();

        for ($request = 0; $request < 2; $request++) {
            $flowData = $pipeline->createFlowData();
            $flowData->evidence->set('query.client-ip-51d', Constants::IPV4_ADDRESS);
            $flowData->process();

            $this->assertSame([], $flowData->get('ip')->asDictionary());
        }
    }

    public function testElementDataKey(): void
    {
        $engine = new IpIntelligenceCloud();
        $this->assertSame('ip', $engine->dataKey);
    }

    public function testScalarPropertyValues(): void
    {
        $pipeline = $this->buildMockedPipeline();
        $flowData = $pipeline->createFlowData();
        $flowData->evidence->set('query.client-ip-51d', Constants::IPV4_ADDRESS);
        $result = $flowData->process();

        $ip = $result->ip;

        $this->assertTrue($ip->registeredname->hasValue);
        $this->assertSame('51 Degrees Mobile Experts Limited', $ip->registeredname->value);
        $this->assertSame('GB', $ip->countrycode->value);
        $this->assertSame('GB', $ip->registeredcountry->value);
        $this->assertEqualsWithDelta(51.4578, $ip->latitude->value, 0.0001);
        $this->assertEqualsWithDelta(-0.9732, $ip->longitude->value, 0.0001);
        $this->assertSame(25, $ip->accuracyradiusmin->value);
        $this->assertFalse($ip->iseu->value);
    }

    public function testWeightedPropertyValues(): void
    {
        $pipeline = $this->buildMockedPipeline();
        $flowData = $pipeline->createFlowData();
        $flowData->evidence->set('query.client-ip-51d', Constants::IPV4_ADDRESS);
        $result = $flowData->process();

        $mcc = $result->ip->mcc;

        $this->assertTrue($mcc->hasValue);
        $weighted = $mcc->value;
        $this->assertIsArray($weighted);
        $this->assertCount(2, $weighted);
        $this->assertSame('234', $weighted[0]['value']);
        $this->assertSame(52428, $weighted[0]['rawweighting']);
        $this->assertSame('235', $weighted[1]['value']);
    }

    public function testMissingPropertyNullReason(): void
    {
        $pipeline = $this->buildMockedPipeline();
        $flowData = $pipeline->createFlowData();
        $flowData->evidence->set('query.client-ip-51d', Constants::IPV4_ADDRESS);
        $result = $flowData->process();

        $ip = $result->ip->ip;

        $this->assertFalse($ip->hasValue);
        $this->assertStringStartsWith('The results are empty', $ip->noValueMessage);
    }

    public function testGetProperties(): void
    {
        $pipeline = $this->buildMockedPipeline();
        // Trigger a process so the cloud properties are fetched.
        $flowData = $pipeline->createFlowData();
        $flowData->evidence->set('query.client-ip-51d', Constants::IPV4_ADDRESS);
        $flowData->process();

        $properties = $pipeline->getElement('ip')->getProperties();

        $this->assertArrayHasKey('countrycode', $properties);
        $this->assertSame('CountryCode', $properties['countrycode']['name']);
        $this->assertSame('String', $properties['countrycode']['type']);
        $this->assertSame('Mcc', $properties['mcc']['name']);
        $this->assertSame('WeightedString', $properties['mcc']['type']);
    }

    public function testEvidenceKeyFilter(): void
    {
        $pipeline = $this->buildMockedPipeline();
        $cloud = $pipeline->getElement('cloud');
        // Force the engine to populate its evidence keys.
        $flowData = $pipeline->createFlowData();
        $flowData->evidence->set('query.client-ip-51d', Constants::IPV4_ADDRESS);
        $flowData->process();

        $filter = $cloud->getEvidenceKeyFilter();
        $this->assertTrue($filter->filterEvidenceKey('query.client-ip-51d'));
    }

    /**
     * Live integration test - only runs when a real resource key has been
     * supplied via the resource_key environment variable.
     */
    public function testLiveCloud(): void
    {
        $resourceKey = getenv(Constants::RESOURCE_KEY_ENV_VAR);
        if (!$resourceKey || $resourceKey === '!!YOUR_RESOURCE_KEY!!') {
            $this->markTestSkipped('No resource_key environment variable set - skipping live cloud test.');
        }

        $settings = ['resourceKey' => $resourceKey];
        $endPoint = getenv('_51DEGREES_CLOUD_ENDPOINT');
        if ($endPoint) {
            $settings['cloudEndPoint'] = $endPoint;
        }

        // Pre-flight connectivity probe. A local environment without a
        // configured CA bundle (typical on Windows where curl.cainfo is
        // unset) would otherwise fail with an SSL error that is unrelated
        // to the engine under test, so skip rather than fail in that case.
        $base = $settings['cloudEndPoint'] ?? 'https://cloud.51degrees.com/api/v4/';
        if (function_exists('curl_init')) {
            $ch = curl_init($base . 'accessibleProperties?resource=' . $resourceKey);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_NOBODY, true);
            curl_exec($ch);
            $errno = curl_errno($ch);
            curl_close($ch);
            if ($errno !== 0) {
                $this->markTestSkipped(sprintf(
                    'Cloud endpoint not reachable in this environment (curl errno %d). ' .
                    'On Windows set curl.cainfo to a cacert.pem bundle to enable live testing.',
                    $errno
                ));
            }
        }

        $pipeline = (new IpIntelligencePipelineBuilder($settings))->build();
        $flowData = $pipeline->createFlowData();
        $flowData->evidence->set('query.client-ip-51d', Constants::IPV4_ADDRESS);
        $result = $flowData->process();

        // The country property should be present and authorised for the key.
        $country = $result->ip->get('country');
        $this->assertNotNull($country);
        if ($country->hasValue) {
            $this->assertNotNull($country->value);
        } else {
            $this->assertNotNull($country->noValueMessage);
        }
    }
}
