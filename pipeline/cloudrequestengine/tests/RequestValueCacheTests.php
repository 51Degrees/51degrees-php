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

use fiftyone\pipeline\cloudrequestengine\CloudRequestEngine;
use fiftyone\pipeline\cloudrequestengine\HttpClient;
use fiftyone\pipeline\core\PipelineBuilder;
use fiftyone\pipeline\engines\AspectDataDictionary;
use fiftyone\pipeline\engines\DataKeyedCache;
use PHPUnit\Framework\TestCase;

/**
 * A cache holding the Cloud Request Engine's responses.
 */
class ArrayCache extends DataKeyedCache
{
    /**
     * @var array<string, mixed>
     */
    public array $entries = [];

    public function get($cacheKey)
    {
        return $this->entries[json_encode($cacheKey)] ?? null;
    }

    public function set($cacheKey, $value): void
    {
        $this->entries[json_encode($cacheKey)] = $value;
    }
}

/**
 * A response that holds a value created for one request, a top-level member
 * named fodid in any case, is neither stored in the cache nor served from it.
 * Every other response is cached as before.
 */
class RequestValueCacheTests extends TestCase
{
    /**
     * @var array<string>
     */
    private array $sent = [];

    private function engine(string $response, ArrayCache $cache): CloudRequestEngine
    {
        $this->sent = [];
        $client = $this->createMock(HttpClient::class);
        $client->method('makeCloudRequest')->willReturnCallback(
            function ($type, $url, $content, $origin) use ($response) {
                if (strpos($url, 'accessibleProperties') !== false) {
                    return '{"Products": {"device": {"DataTier": "tier",' .
                        '"Properties": [{"Name": "value","Type": "String",' .
                        '"Category": "Device"}]}}}';
                }
                if (strpos($url, 'evidencekeys') !== false) {
                    return '["header.User-Agent"]';
                }
                $this->sent[] = (string) $content;

                return $response;
            }
        );

        $engine = new CloudRequestEngine([
            'resourceKey' => 'resource_key',
            'httpClient' => $client
        ]);
        $engine->setCache($cache);

        return $engine;
    }

    /**
     * Process two requests with the same Evidence and return the number of
     * requests that went to the cloud service.
     */
    private function requestsSent(string $response, ?ArrayCache $cache = null): int
    {
        $cache = $cache ?? new ArrayCache();
        $pipeline = (new PipelineBuilder([
            'addJavaScriptBuilder' => false,
            'useSetHeaderProperties' => false,
        ]))->add($this->engine($response, $cache))->build();

        for ($request = 0; $request < 2; $request++) {
            $flowData = $pipeline->createFlowData();
            $flowData->evidence->set('header.user-agent', 'same');
            $flowData->process();
        }

        return count($this->sent);
    }

    public function testAResponseWithoutAFodidIsCached()
    {
        $this->assertSame(1, $this->requestsSent('{"device":{"value":"1"}}'));
    }

    public function testAResponseWithATopLevelFodidIsNotCached()
    {
        $this->assertSame(
            2,
            $this->requestsSent('{"device":{"value":"1"},"fodid":{"value":"a"}}')
        );
    }

    public function testTheMemberNameIsMatchedInAnyCase()
    {
        $this->assertSame(
            2,
            $this->requestsSent('{"device":{"value":"1"},"FodId":"a"}')
        );
    }

    public function testAFodidBelowTheTopLevelIsCached()
    {
        $this->assertSame(
            1,
            $this->requestsSent('{"device":{"value":"1","fodid":"a"}}')
        );
    }

    public function testAnEntryHoldingAFodidIsNotServed()
    {
        $cache = new ArrayCache();
        $pipelineEngine = $this->engine('{"device":{"value":"live"}}', $cache);
        $held = new AspectDataDictionary(
            $pipelineEngine,
            ['cloud' => '{"fodid":{"value":"old"}}']
        );
        $cache->entries[json_encode(['header.user-agent' => 'same'])] = $held;

        $pipeline = (new PipelineBuilder([
            'addJavaScriptBuilder' => false,
            'useSetHeaderProperties' => false,
        ]))->add($pipelineEngine)->build();
        $flowData = $pipeline->createFlowData();
        $flowData->evidence->set('header.user-agent', 'same');
        $flowData->process();

        $this->assertCount(1, $this->sent);
        $this->assertStringContainsString(
            'live',
            $flowData->get('cloud')->get('cloud')
        );
    }
}
