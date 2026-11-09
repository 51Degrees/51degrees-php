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

namespace fiftyone\pipeline\cloudrequestengine\tests;

use fiftyone\pipeline\cloudrequestengine\CloudEngine;
use fiftyone\pipeline\cloudrequestengine\CloudRequestEngine;
use fiftyone\pipeline\cloudrequestengine\HttpClient;
use fiftyone\pipeline\core\PipelineBuilder;
use PHPUnit\Framework\TestCase;

/**
 * The Cloud Request Engine accepts only the keys the cloud service lists,
 * and the Cloud Aspect Engines, which read its response, accept none.
 */
class AcceptedEvidenceTests extends TestCase
{
    /**
     * The POST bodies sent to the test cloud service.
     *
     * @var array<string>
     */
    private array $sent = [];

    private function cloudEngine(): CloudRequestEngine
    {
        $client = $this->createMock(HttpClient::class);
        $client->method('makeCloudRequest')->willReturnCallback(
            function ($type, $url, $content, $origin) {
                if (strpos($url, 'accessibleProperties') !== false) {
                    return '{"Products": {"device": {"DataTier": "tier",' .
                        '"Properties": [{"Name": "value","Type": "String",' .
                        '"Category": "Device"}]}}}';
                }
                if (strpos($url, 'evidencekeys') !== false) {
                    return '["header.User-Agent","query.User-Agent"]';
                }
                $this->sent[] = (string) $content;

                return '{"device":{"value":"1"}}';
            }
        );

        return new CloudRequestEngine([
            'resourceKey' => 'resource_key',
            'httpClient' => $client
        ]);
    }

    public function testCloudAspectEngineAcceptsNoEvidence()
    {
        $engine = new CloudEngine();

        foreach (['header.user-agent', 'header.if-none-match', 'query.tac',
            'server.client-ip'] as $key) {
            $this->assertFalse(
                $engine->filterEvidenceKey($key),
                "The Cloud Aspect Engine accepts {$key}"
            );
        }
    }

    /**
     * The engine knows the cloud service's keys before any Evidence is set,
     * so the Evidence the cloud service uses is kept, and it accepts no
     * other key.
     */
    public function testCloudRequestEngineAcceptsOnlyTheCloudsKeys()
    {
        $engine = $this->cloudEngine();

        $this->assertTrue($engine->filterEvidenceKey('header.user-agent'));
        $this->assertTrue($engine->filterEvidenceKey('Query.User-Agent'));
        foreach (['header.if-none-match', 'header.x-test', 'cookie.51d_test',
            'server.client-ip'] as $key) {
            $this->assertFalse(
                $engine->filterEvidenceKey($key),
                "The Cloud Request Engine accepts {$key}"
            );
        }
    }

    /**
     * In a web Pipeline holding the Cloud Request Engine, a header the cloud
     * service uses reaches it, and a header no element uses is neither kept
     * nor sent.
     */
    public function testACloudPipelineSendsTheHeadersTheCloudUses()
    {
        $engine = $this->cloudEngine();
        $pipeline = (new PipelineBuilder())->add($engine)->build();
        $flowData = $pipeline->createFlowData();

        $flowData->evidence->set('header.user-agent', 'iPhone');
        $flowData->evidence->set('header.if-none-match', '"etag"');
        $flowData->evidence->set('header.x-test', 'not used');
        $flowData->process();

        $this->assertCount(1, $this->sent);
        parse_str($this->sent[0], $form);
        $this->assertSame('iPhone', $form['user-agent'] ?? null);
        $this->assertArrayNotHasKey('if-none-match', $form);
        $this->assertArrayNotHasKey('x-test', $form);
        $this->assertNull($flowData->evidence->get('header.x-test'));
    }
}
