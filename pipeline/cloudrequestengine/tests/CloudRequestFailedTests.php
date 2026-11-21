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

namespace fiftyone\pipeline\cloudrequestengine\tests;

use fiftyone\pipeline\cloudrequestengine\CloudEngine;
use fiftyone\pipeline\cloudrequestengine\CloudRequestEngine;
use fiftyone\pipeline\cloudrequestengine\CloudRequestException;
use fiftyone\pipeline\cloudrequestengine\HttpClient;
use fiftyone\pipeline\core\FlowData;
use fiftyone\pipeline\core\Pipeline;
use fiftyone\pipeline\core\PipelineBuilder;
use fiftyone\pipeline\engines\DataKeyedCache;
use PHPUnit\Framework\TestCase;

/**
 * When the Cloud Request Engine's request fails, a cloud engine's Element
 * Data gives the CloudRequestFailed reason for every property, as step 1 of
 * deciding the reason in the properties specification says, and the Cloud
 * Request Engine's error stays the Flow Data's error.
 */
class CloudRequestFailedTests extends TestCase
{
    private const PROPERTIES = '{"Products": {"device": {"Properties": [' .
        '{"Name": "IsMobile", "Type": "Boolean"}]}}}';
    private const EVIDENCE_KEYS = '["query.User-Agent"]';
    private const DATA = '{"device":{"ismobile":true}}';
    private const FAILED = "Property 'ismobile' not found in data for " .
        "element 'device'. This is because an upstream cloud request " .
        'failed, so no data was available to populate the property.';

    /**
     * The kinds of request that fail, being 'properties', 'keys' or 'data'.
     *
     * @var array<string>
     */
    private array $failing = [];

    public function testAFailedRequestGivesCloudRequestFailed(): void
    {
        $this->failing = ['data'];
        [$pipeline] = $this->pipeline(true);

        $flowData = self::process($pipeline);

        $this->assertSame(['cloud-response'], array_keys($flowData->errors));
        $this->assertSame(self::FAILED, self::messageOf($flowData));
    }

    public function testAFailedPropertyListRequestGivesCloudRequestFailed(): void
    {
        $this->failing = ['properties'];
        [$pipeline] = $this->pipeline(true);

        $flowData = self::process($pipeline);

        $this->assertSame(['cloud-response'], array_keys($flowData->errors));
        $this->assertSame(self::FAILED, self::messageOf($flowData));
    }

    /**
     * Without suppression, processing still throws the Cloud Request
     * Engine's error.
     */
    public function testTheCloudRequestErrorIsStillThrown(): void
    {
        $this->failing = ['data'];
        [$pipeline] = $this->pipeline(false);
        $flowData = $pipeline->createFlowData();
        $flowData->evidence->set('query.User-Agent', 'iPhone');

        try {
            $flowData->process();
            $this->fail('No exception was thrown.');
        } catch (CloudRequestException $e) {
            $this->assertStringContainsString('data down', $e->getMessage());
        }
        $this->assertSame(self::FAILED, self::messageOf($flowData));
    }

    /**
     * The Element Data of a failed request is not cached, so the next
     * request asks the cloud service again and gets its values.
     */
    public function testAFailedRequestIsNotCached(): void
    {
        $this->failing = ['data'];
        [$pipeline, $device] = $this->pipeline(true);
        $device->setCache(new class () extends DataKeyedCache {
            /** @var array<string, mixed> */
            private array $entries = [];

            public function get($cacheKey)
            {
                return $this->entries[serialize($cacheKey)] ?? null;
            }

            public function set($cacheKey, $value): void
            {
                $this->entries[serialize($cacheKey)] = $value;
            }
        });

        self::process($pipeline);
        $this->failing = [];
        $flowData = self::process($pipeline);

        $this->assertSame([], $flowData->errors);
        $this->assertTrue($flowData->get('device')->get('ismobile')->value);
    }

    /**
     * A Pipeline with a Cloud Request Engine whose client answers each
     * request, other than the kinds in $failing, and a cloud engine for
     * the device product.
     *
     * @return array{Pipeline, CloudEngine}
     */
    private function pipeline(bool $suppress): array
    {
        $client = $this->createStub(HttpClient::class);
        $client->method('makeCloudRequest')->willReturnCallback(
            function (string $type, string $url): string {
                $lower = strtolower($url);
                if (strpos($lower, 'accessibleproperties') !== false) {
                    $kind = 'properties';
                } elseif (strpos($lower, 'evidencekeys') !== false) {
                    $kind = 'keys';
                } else {
                    $kind = 'data';
                }
                if (in_array($kind, $this->failing, true)) {
                    throw new CloudRequestException("{$kind} down", 503);
                }

                return [
                    'properties' => self::PROPERTIES,
                    'keys' => self::EVIDENCE_KEYS,
                    'data' => self::DATA,
                ][$kind];
            }
        );
        $device = new CloudEngine();
        $device->dataKey = 'device';
        $pipeline = (new PipelineBuilder([
            'addJavaScriptBuilder' => false,
            'useSetHeaderProperties' => false,
            'suppressProcessExceptions' => $suppress,
        ]))
            ->add(new CloudRequestEngine([
                'resourceKey' => 'resourcekey',
                'httpClient' => $client,
            ]))
            ->add($device)
            ->build();

        return [$pipeline, $device];
    }

    private static function process(Pipeline $pipeline): FlowData
    {
        $flowData = $pipeline->createFlowData();
        $flowData->evidence->set('query.User-Agent', 'iPhone');
        $flowData->process();

        return $flowData;
    }

    /**
     * The message thrown when ismobile is read from the device Element
     * Data.
     */
    private static function messageOf(FlowData $flowData): string
    {
        try {
            $flowData->get('device')->get('ismobile');
        } catch (\Exception $e) {
            return $e->getMessage();
        }

        return 'nothing was thrown';
    }
}
